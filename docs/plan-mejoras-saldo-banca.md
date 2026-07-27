# Plan de mejoras — Sistema de Gestión de Saldo

> Basado en la revisión senior del código actual (entidades, servicios y controladores en
> `src/Entity/Balance`, `src/Services/Balance`, `src/Controller/Api`, `config/packages/security.yaml`).
> Objetivo: cerrar riesgos de seguridad y de integridad financiera antes de seguir sumando features,
> y luego completar los flujos de negocio que hoy están a medias (trazas, consumo de autorizados).

Estado: **plan aprobado, en ejecución**. Cada fase se marca como `[ ]` pendiente / `[x]` hecha a
medida que se implemente. No mezclar fases: cada una debe cerrar con pruebas antes de pasar a la
siguiente, porque las fases 1 y 2 tocan el camino de dinero real.

---

## Fase 0 — Antes de tocar código

- [ ] Confirmar con negocio si aplica normativa AML/KYC/OFAC para las remesas (ver Fase 5). Esto
  puede cambiar el diseño de `Account`/`Recharge`, mejor saberlo ahora.
- [x] Confirmar el flujo real de "consumo" de saldo de un `AuthorizedUser`. **Respondido:** el negocio
  (el sistema tercero) genera una factura por el producto/servicio que el cliente eligió, y hay dos
  formas de cobrarla contra el saldo:
  1. **Flujo PIN (implementable ya):** el cliente o autorizado le da su PIN al negocio, el negocio lo
     teclea en su propio sistema, y ese sistema llama a saldoBanca a validar que el PIN corresponde a
     esa cuenta/autorizado, está vigente y es correcto, y con eso ejecuta el cargo contra la factura.
  2. **Flujo APK (a futuro):** el cliente paga desde su propia app; el negocio muestra "pagar por
     APK", la app del cliente confirma, y esa confirmación debe volver al sistema del negocio como
     "factura pagada". Pendiente de diseñar el mecanismo de ida/vuelta cuando exista la app — no se
     implementa en esta ronda.
  - **Gap detectado al revisar el código:** hoy `pinCode` solo existe en `AuthorizedUser`, no en
    `Account`/`User`. Si el propio dueño de la cuenta (no un autorizado) también va a pagar con PIN,
    hay que decidir: (a) agregar `pinCode` también a `Account`, o (b) que
    `RegistrationService::registerClient()` cree automáticamente un `AuthorizedUser` "de sí mismo"
    para reutilizar todo el mecanismo de PIN/límites que ya existe. **Pendiente de decisión antes de
    diseñar `AuthorizedService::spend()` en la Fase 4** (opción b evita duplicar el concepto de PIN
    y límites en dos entidades distintas — recomendado, a confirmar).

---

## Fase 1 — Seguridad de la API (bloqueante, riesgo activo)

**Problema:** el firewall `api` en `security.yaml` es `security: false` + `PUBLIC_ACCESS`. La única
protección es una API key estática (`X-API-KEY`) y **no se aplica en la mayoría de los endpoints
mutating**: `recharges/{id}/complete|cancel|fail`, `transfers/{id}/process|cancel`,
`invoices/{id}/pay|cancel|refund`, todo `/authorized`, y `GET /balance/{accountId}`. Hoy cualquiera
sin credenciales puede acreditar saldo o mover dinero entre cuentas.

Ya existe `league/oauth2-server-bundle` configurado (emite tokens `client_credentials`) pero no
está conectado a ningún firewall/endpoint real — está construido y no se usa.

**Tareas:**
- [x] Decidir mecanismo de auth para `/api`: **OAuth2 (`league/oauth2-server-bundle`) para sistemas
  externos** (`client_id`/`client_secret`, ya son las credenciales que se gestionan en
  `/oauth-clients`), **JWT (lexik) reservado para el login de usuarios/negocios**
  (`/api/v1/login`, `/register`, `/register/client`, `/register/business` — quedan públicos porque
  ahí es donde se *emite* el JWT, no donde se consume). Confirmado con el usuario.
- [x] Generado un par de llaves RSA propio para OAuth2 en `config/oauth/{private,public}.key`
  (antes reusaba por error el mismo par que Lexik JWT en `config/jwt/*.pem`, con passphrases
  distintas — bug latente detectado al revisar `.env`). `.env` y `.gitignore` actualizados.
- [x] `config/packages/security.yaml`: firewall `api_public` (`^/api/v1/(login|register)`, público,
  solo emite tokens) + firewall `api` (`^/api`, `oauth2: true`) + `access_control` exige
  `IS_AUTHENTICATED_FULLY` en `^/api`.
- [x] `config/packages/league_oauth2_server.yaml`: añadido scope `balance` (faltaba) a la lista de
  `available` (`api, profile, accounts, balance, recharges, transfers, invoices, authorized`).
- [x] Añadido `oauth2_scopes` por ruta (vía `defaults` del atributo `#[Route]`) en **todos** los
  controladores de `src/Controller/Api/*` que tocan dinero o datos de cuenta: Account, Recharge,
  Transfer, Invoice, Authorized, Balance, History — incluyendo los que antes no tenían ningún check
  (`recharges/.../complete|cancel|fail`, `transfers/.../process|cancel`, `invoices/.../pay|cancel|refund`,
  todo `/authorized`, `balance/{accountId}`, `history`).
- [x] Eliminado `checkApiKey()` de `BaseController` y todos sus usos; eliminado el parámetro
  `app.api_key` de `services.yaml` (la key estática `API_KEY_SISTEMA_EXTERNO` ya no se usa en código).
- [x] Verificado end-to-end con servidor local + cliente OAuth2 de prueba: sin token → 401 en todos
  los endpoints antes abiertos; con token pero scope insuficiente → 403; con scope correcto → 200/
  lógica de negocio normal. Cliente de prueba eliminado al terminar.
- [ ] Rate limiting básico en `/api` (symfony/rate-limiter) para mitigar fuerza bruta y abuso — queda
  pendiente, no bloqueante para cerrar esta fase.
- [x] `enable_password_grant` desactivado en `league_oauth2_server.yaml` (decisión del usuario): un
  solo camino de auth por tipo de actor — JWT para usuarios, OAuth2 client_credentials para sistemas
  externos, sin solapes.

**Criterio de cierre:** ningún endpoint de `/api/v1/*` responde 200 sin token válido y scope
correcto; verificado manualmente con curl (ver arriba). Pendiente: convertir esa verificación manual
en un test automatizado de regresión (se puede hacer más adelante, no bloquea seguir con la Fase 2).

**Fase 1: cerrada.**

---

## Fase 2 — Atomicidad e integridad del dinero (bloqueante, riesgo activo)

**Problema:** `BalanceService::transferBalance()` hace `deductBalance()` + `addBalance()` como dos
flushes independientes, sin transacción de BD. Si el segundo falla, el dinero desaparece (se
debita origen, nunca se acredita destino). No hay bloqueo pesimista ni columna de versión en
`AccountBalance`, así que dos requests concurrentes sobre la misma cuenta pueden pisarse el saldo,
y dos llamadas paralelas a `.../complete` pueden duplicar una acreditación (TOCTOU sobre el check
de `status`).

**Tareas:**
- [x] `AccountBalanceRepository::findByAccountAndCurrencyForUpdate()` — mismo lookup de siempre pero
  con `LockMode::PESSIMISTIC_WRITE` (`SELECT ... FOR UPDATE`). `BalanceService::addBalance()` y
  `deductBalance()` ahora lo usan y además envuelven todo su cuerpo en
  `$entityManager->wrapInTransaction(...)`.
- [x] `BalanceService::transferBalance()` bloquea primero **ambas** filas de `AccountBalance`
  (origen y destino) en un orden estable (`sort()` de los dos accountId) antes de mutar nada, para
  que dos transferencias concurrentes en direcciones opuestas entre las mismas cuentas no hagan
  deadlock; todo dentro de una única transacción (Doctrine anida `wrapInTransaction` sin problema,
  así que las llamadas internas a `deductBalance`/`addBalance` participan de la misma transacción).
- [x] Cerrada la ventana TOCTOU de estado: se agregó `markStatusIfCurrent($id, $expected, $new): bool`
  a `RechargeRepository`, `TransferRepository` e `InvoicePaymentRepository` — un único `UPDATE ...
  WHERE id = :id AND status = :expected` atómico (el propio `UPDATE` de Postgres serializa el acceso
  a la fila, sin necesidad de un `SELECT FOR UPDATE` aparte). Se usa en
  `RechargeService::completeRecharge/cancelRecharge/failRecharge`,
  `TransferService::processTransfer/cancelTransfer` e
  `InvoiceService::processPayment/cancelPayment/refundPayment`, reemplazando el patrón anterior de
  "leer entidad → comparar status en PHP → escribir".
- [x] Migración `Version20260715135001`: `CHECK` a nivel de BD en `balance_account_balance` para que
  `availablebalance`, `reservedbalance` y `pendingbalance` nunca puedan quedar negativos, como
  defensa adicional a la validación de `deductBalance()`. Aplicada contra la BD de desarrollo.
- [x] **Test de concurrencia real** (servidor local + cliente OAuth2 de prueba, 10 requests paralelas
  con `curl ... &` + `wait`, datos de prueba borrados al terminar):
  - `PUT /recharges/{id}/complete` × 10 simultáneas → **1** `200 OK`, 9 × `400 "not in pending
    status"`; saldo final acreditado **una sola vez** (100.00, no 1000.00).
  - `PUT /transfers/{id}/process` × 10 simultáneas → **1** `200 OK`, 9 × `400`; saldo origen
    debitado y destino acreditado **una sola vez** (40.00 exactos en ambos lados, no múltiplos).

**Criterio de cierre:** ejecución concurrente (script con 10 requests paralelas) sobre la misma
recarga/transferencia no duplica saldo ni lo pierde. **Verificado.**

**Fase 2: cerrada.** Pendiente (no bloqueante): convertir la prueba manual de concurrencia en un test
automatizado de regresión (se puede hacer junto con el de la Fase 1 más adelante).

---

## Fase 3 — Webhook idempotente y verificable

**Problema:** `RechargeService::processExternalRecharge()` no verifica si ya existe una recarga con
el mismo `referenceNumber`/`transactionId` antes de crear una nueva (los reintentos de la pasarela
duplican crédito). No hay verificación de firma del payload — solo la API key genérica.

**Tareas:**
- [x] Nueva entidad `WebhookEvent` (`balance_webhook_event`) que registra **todo** intento de webhook
  — pasarela, payload crudo, si la firma fue válida, status (`processed`/`duplicate`/
  `rejected_signature`/`unknown_gateway`/`misconfigured`/`invalid_payload`/`error`), la `Recharge`
  vinculada si aplica, y el mensaje de error — independientemente de si el procesamiento tuvo éxito.
- [x] Índice único parcial en BD (migración `Version20260715142504`):
  `UNIQUE (externalsystem, referencenumber) WHERE ambos NOT NULL` en `balance_recharge` — las
  recargas manuales (sin sistema externo) no se restringen. Respaldo a nivel de aplicación:
  `RechargeRepository::findByExternalReference()` + `RechargeService::processWebhookRecharge()`
  devuelven la recarga ya existente en vez de reprocesar, y si dos entregas llegan a la vez
  (`UniqueConstraintViolationException`), se recupera el registro ganador en vez de fallar.
- [x] Verificación de firma HMAC-SHA256 por pasarela: `WebhookService::handleRechargeWebhook()` lee
  `PaymentGateway.config['webhook_secret']` (columna json ya existente) y compara con
  `hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureHeader)` — nunca confía en el
  payload sin verificar la firma primero. Pasarela desconocida/inactiva o firma inválida →
  `WebhookAuthenticationException` → 401, y queda igual registrado en `WebhookEvent`.
- [x] Nuevo endpoint público (autenticado por firma, no por OAuth2) `POST
  /api/v1/webhooks/recharges/{gatewayCode}` (`WebhookController`), con header
  `X-Webhook-Signature`. Añadido a `access_control` como `PUBLIC_ACCESS` específicamente para ese
  path (el resto de `/api` sigue exigiendo OAuth2).
- [x] Recarga vía webhook se crea **y completa** en el mismo paso (`processWebhookRecharge`) — la
  pasarela ya está confirmando que el dinero llegó, no hace falta un paso manual de "completar"
  aparte como en el flujo de partner/API directa.
- [x] **Verificado end-to-end** (servidor local + cuenta y pasarela de prueba insertadas directo en
  BD, firma calculada con `hash_hmac` real, datos borrados al terminar):
  - Firma válida → `200`, recarga creada y completada, saldo acreditado (75.50).
  - Misma entrega repetida exacta (mismo `referenceNumber`) → `200`, **mismo** id de recarga
    devuelto, saldo se mantuvo en 75.50 (no se duplicó a 151.00).
  - Firma inválida → `401`, balance sin tocar.
  - Pasarela desconocida → `401`.
  - Los 4 intentos quedaron registrados en `balance_webhook_event` con el status correcto
    (`processed`, `duplicate`, `rejected_signature`, `unknown_gateway`).

**Criterio de cierre:** reenviar el mismo webhook dos veces no duplica el crédito; un payload con
firma inválida se rechaza sin tocar balance. **Verificado.**

**Fase 3: cerrada.**

---

## Fase 4 — Trazabilidad completa y consumo de autorizados

**Problema:** `TransactionLog` (ip, user agent, request/response, actor) existe pero no se usa en
ningún servicio — hoy es código muerto. La reserva de saldo al crear un `AuthorizedUser` no genera
`BalanceMovement` (movimiento invisible). No existe ningún método para que un autorizado
efectivamente gaste su saldo reservado: `usedToday`/`usedThisMonth` nunca se incrementan en el
código (solo se resetean por cron), por lo que los límites diario/mensual son inertes.

**Diseño acordado con el usuario (respuestas a Fase 0):**
- El titular de la cuenta también paga con PIN: se le crea automáticamente un `AuthorizedUser`
  "de sí mismo" (misma tabla, mismo `User` ya existente, sin duplicar el concepto de PIN/límites).
- El PIN es de un solo uso y rotativo: al concluir un cargo con éxito se genera uno nuevo aleatorio
  y se notifica por WhatsApp — el que se acaba de usar deja de servir.
- El cargo puede pagar una factura existente pasando su **número** legible (ej. `FAC-2000`), no el
  `id` interno — el texto del movimiento queda como "Pago de la factura FAC-2000".

**Tareas:**
- [x] `AuthorizedService::ensureSelfAuthorized(Account $account)`: crea (si no existe) el autorizado
  "de sí mismo", vinculado al `User` ya existente de la cuenta (sin crear un segundo login). Sin
  `maxAmount` — su gasto sale directo de `availableBalance`, no hay nada que reservarle a sí mismo.
  Cableado en `RegistrationService::registerClient()` y `approveBusiness()`.
- [x] `BalanceService::reserveBalance()`/`releaseBalance()` — mueven dinero entre disponible y
  reservado dejando `BalanceMovement` (tipo `reserve`/`release`), reemplazando la mutación manual
  de `AccountBalance` que antes hacía `createAuthorized`/`deleteAuthorized`/`changeStatus`
  directamente sin dejar rastro. `BalanceService::deductReservedBalance()` nueva para el consumo
  (tipo `authorized_spend`).
- [x] `AuthorizedService::spend()`: valida PIN (`hash_equals`) y `checkLimits()`, resuelve monto desde
  `invoiceNumber` si viene (vía `InvoiceService::findByAccountAndNumber()`), descuenta de
  `reservedBalance` (autorizado delegado) o `availableBalance` (titular/self, sin cupo reservado),
  incrementa `usedToday`/`usedThisMonth`, marca la factura pagada si aplica
  (`InvoiceService::markPaidExternally()`, con el mismo `markStatusIfCurrent` atómico de la Fase 2),
  rota el PIN, y notifica el nuevo PIN por WhatsApp (best-effort, plantilla `new_pin.txt.twig`).
- [x] Endpoint `POST /api/v1/authorized/{id}/charge` (`oauth2_scopes: ['authorized']`), acepta
  `pinCode`, `invoiceNumber` opcional, `amount`/`notes` para cargos sin factura.
- [x] **Bug real encontrado y corregido durante la verificación:** `deleteAuthorized`/`changeStatus`
  liberaban/re-reservaban `maxAmount` (el tope original fijo) en vez de lo que realmente quedaba
  reservado tras los gastos — esto sobre-acreditaba `availableBalance` al desactivar un autorizado
  que ya había gastado parte de su cupo, y además permitía que un autorizado gastara indefinidamente
  en montos pequeños más allá de su cupo real, drenando el de otros autorizados de la misma cuenta.
  Se agregó el campo `AuthorizedUser.reservedAmount` (cuánto le queda realmente a *ese* autorizado
  del agregado `AccountBalance.reservedBalance`), usado en `checkLimits()`, `spend()`, y en la
  liberación/reactivación en vez de `maxAmount`. Migración `Version20260715161432` con backfill para
  autorizados ya existentes.
- [x] `TransactionLog` cableado vía `ApiTransactionLogListener` (`kernel.response`), registra toda
  mutación (`POST`/`PUT`/`PATCH`/`DELETE`) bajo `/api/v1` salvo `login`/`register`: actor (cliente
  OAuth2), ip, user agent, payload (con `password`/`pinCode`/`client_secret`/etc. redactados),
  respuesta, y resultado. `TransactionLog.account` se volvió nullable (migración
  `Version20260715154820`) porque no toda mutación resuelve a una única cuenta; se hace
  best-effort (route param → body → respuesta) y se registra igual con `account = null` si no se
  puede resolver. El listener nunca puede tumbar la respuesta real: si el `EntityManager` del
  request ya quedó cerrado por una excepción anterior, o si el propio logging falla, se omite en
  silencio (se descubrió este caso real durante la verificación, ver abajo).
- [x] **Bug preexistente encontrado de paso (no introducido en esta fase):** `InvoicePayment`
  mapeaba `invoiceDate`/`dueDate`/`paymentDate` como Doctrine `type: 'date'` con propiedades
  `\DateTimeImmutable` — Doctrine's `DateType` espera `\DateTime` mutable, así que **toda** creación
  de factura fallaba con `Could not convert PHP value... to type DateType`, cerrando el
  `EntityManager` para el resto del proceso. Corregido a `type: 'date_immutable'` (mismo tipo de
  columna en BD, sin migración de esquema necesaria — verificado con `doctrine:schema:update
  --dump-sql`).
- [ ] Nota abierta, no resuelta en esta fase: `AuthorizedService::updateAuthorized()` permite
  cambiar `maxAmount` sin ajustar la reserva/`reservedAmount` real — gap preexistente (no introducido
  aquí), pero ahora más visible con `reservedAmount` en juego. Requiere lógica de "resize" de reserva
  (reservar más o liberar la diferencia) antes de permitir editar `maxAmount` de un autorizado activo.
- [ ] Flujo APK (pago desde app propia del cliente) — pendiente de diseño futuro, según lo acordado
  en la Fase 0; no se implementa en esta ronda.

**Criterio de cierre:** toda reserva/liberación/consumo de saldo de un autorizado queda en
`BalanceMovement`; los límites diario/mensual efectivamente bloquean cuando se alcanzan.
**Verificado end-to-end** (servidor local + cuenta/autorizados de prueba, datos borrados al
terminar):
- Registro de cliente → autorizado "de sí mismo" creado automáticamente con PIN aleatorio.
- Cargo con PIN incorrecto → rechazado, nada se toca.
- Cargo del titular (self, sin cupo) con `invoiceNumber` → factura pagada, `availableBalance`
  descontado, PIN rotado, movimiento `authorized_spend` con descripción "Pago de la factura ...".
- Autorizado delegado con `maxAmount=50`: reserva correcta (available -50/reserved +50) → cargo de
  60 rechazado por límite → cargo de 20 aplicado correctamente contra `reservedBalance` (available
  sin cambio, reserved -20) → desactivar libera exactamente lo que quedaba reservado (30, no 50) →
  reactivar vuelve a reservar exactamente esos mismos 30.
- `TransactionLog` registrando cada mutación con el actor (`f4client`), incluidos los intentos
  fallidos con `status = 'error'`.

**Fase 4: cerrada.**

---

## Completitud de API — paridad Admin ↔ API

**Motivo:** el usuario agregó reset de contraseña en `LoginController` (panel web) y notó que no
existe equivalente en `/api/v1/*`. Se hizo una auditoría completa comparando cada ruta `admin_*`
contra su equivalente `api_*` para encontrar más huecos del mismo tipo.

**Encontrado y cerrado:**
- [x] **Reset de contraseña self-service**, ausente por completo en la API. Se extrajo la lógica que
  vivía inline en `LoginController` (violaba la regla de "acciones de BD en servicios, no en
  controladores") a un `PasswordResetService` nuevo, compartido entre el panel web y la API:
  `POST /api/v1/password-reset/request` (por username/email, siempre responde el mismo mensaje
  genérico exista o no la cuenta) y `POST /api/v1/password-reset/confirm` (por token). Ambas
  públicas (`PUBLIC_ACCESS`, sin OAuth2 — por definición el usuario no está autenticado en este flujo).
- [x] **Autorizados**: faltaban `GET /api/v1/authorized/{id}` (show), `DELETE
  /api/v1/authorized/{id}`, y `POST /api/v1/authorized/{id}/reset-password` (éste distinto del de
  arriba: es el estilo "admin resetea a un autorizado puntual que gestiona", con scope `authorized`,
  no self-service). Se agregó `AuthorizedService::getAuthorized()` que no existía.
- [x] **Cuentas**: faltaban `PUT /api/v1/accounts/{id}` (update) y `PUT /api/v1/accounts/{id}/status`
  (activar/suspender/cerrar) — los métodos de servicio ya existían (`AccountService::updateAccount/
  changeStatus`), solo faltaba exponerlos.

**Se decidió dejar solo en Admin** (no son gaps): Exchange Providers/Payment Gateways
`create/update/status` (tienen secretos de configuración), Exchange Rates `fetch` manual, y
OAuth Clients/Roles/Users/Dashboard (administración interna).

**Verificado end-to-end** (servidor local, cliente OAuth2 y cuenta de prueba, todo limpiado al
terminar): reset de contraseña completo (request → token generado → confirm → login con la
contraseña vieja falla, con la nueva funciona → token reutilizado falla); `authorized` show/reset-
password/delete; `accounts` update/status. Todo correcto.

---

## Fase 5 — Endurecimiento y cumplimiento (mejoras, no bloqueantes)

- [x] Hashear `pinCode` de `AuthorizedUser` en vez de guardarlo en texto plano (hoy es un campo de
  datos, siendo que autoriza montos — debería tratarse como credencial). `AuthorizedUser` ahora
  implementa `PasswordAuthenticatedUserInterface` (mismo mecanismo que `User::password`, mismo
  `UserPasswordHasherInterface` que ya estaba inyectado en `AuthorizedService` pero solo se usaba
  para el password de login). `getPassword()` delega en `getPinCode()`. Columna ensanchada de
  `VARCHAR(4)` a `VARCHAR(255)` (migración `Version20260716111332`, con backfill de los PIN
  existentes aún en texto plano vía `password_hash()` en el propio `up()` — no se puede hacer con
  SQL puro). `spend()` valida con `isPasswordValid()` en vez de `hash_equals()` directo sobre el
  campo; la rotación de PIN de un solo uso (`createAuthorized`, `ensureSelfAuthorized`, `spend`,
  `updateAuthorized`) hashea antes de guardar y mantiene el valor en texto plano solo en una
  variable local para la notificación por WhatsApp (`notifyNewPin`), que ya no lee el PIN de vuelta
  de la entidad. De paso, `updateAuthorized()` dejó de anular el PIN vigente en cada actualización
  que no incluyera `pinCode` explícito (antes lo ponía en `null` incondicionalmente — bug
  preexistente, ahora ineludible de tocar porque hashear `null` no tiene sentido).
  **Verificado end-to-end** (servidor local, cliente OAuth2 y cuenta de prueba, datos borrados al
  terminar): PIN creado vía API queda hasheado en BD (bcrypt, 60 chars); PIN incorrecto rechazado
  sin tocar nada; PIN correcto aplica el cargo y rota a un nuevo hash (el PIN anterior deja de servir
  de inmediato); `update` sin `pinCode` preserva el hash existente; `update` con `pinCode` nuevo lo
  rehashea y el nuevo PIN funciona para el siguiente cargo.
- [x] Campos de límite por cuenta (`maxPerTransfer`, `maxDaily`, `maxMonthly`) en `Account` y
  enforcement real en `TransferService::createTransfer`/`processTransfer` — hoy
  `getTransferLimits()` solo informa el saldo disponible, no valida nada. Migración
  `Version20260716115749` (columnas nullable, `NULL` = sin límite, sin cambio de comportamiento
  para cuentas existentes). Nuevo `TransferRepository::sumCompletedAmountSince()` (DQL, suma
  transferencias `completed` desde una fecha) usado por `TransferService::checkTransferLimits()`,
  que valida `maxPerTransfer` contra el monto y `maxDaily`/`maxMonthly` contra el acumulado +
  el monto nuevo. Se llama tanto en `createTransfer` (validación temprana) como en
  `processTransfer` (revalidación justo antes de mover el dinero, dentro de la misma transacción,
  por si otras transferencias de la misma cuenta se completaron entre la creación y el
  procesamiento). `getTransferLimits()` ahora también devuelve `usedToday`/`usedThisMonth`.
  Expuesto en `AccountDto`/`AccountController` (create/update/list) y documentado en el endpoint
  `GET /transfers/limits/{accountId}`. **Verificado end-to-end** (cuentas y cliente OAuth2 de
  prueba, datos borrados al terminar): transferencia por encima de `maxPerTransfer` rechazada en
  `createTransfer`; transferencias que agotan `maxDaily` rechazadas; una transferencia creada
  *antes* de agotarse el cupo pero procesada *después* de que otras la agotaran es rechazada en
  `processTransfer` (revalidación funcionando, no solo un chequeo de creación).
- [x] Proceso de conciliación bancaria: comparar lo acreditado en el sistema contra los movimientos
  reales de la cuenta/pasarela del exterior, con reporte de descuadres. Nueva entidad
  `BankReconciliation` (`balance_bank_reconciliation`, migración `Version20260716161127`) — un
  registro por corrida, con contadores (`totalMatched/Mismatched/MissingInternal/MissingExternal`)
  y el detalle de descuadres en JSON. `ReconciliationService::reconcile(gatewayCode,
  externalTransactions, periodStart?, periodEnd?, performedBy?)` recibe la lista de movimientos que
  el externo reporta (el sistema no le habla directo a ningún banco/pasarela real — eso lo trae el
  llamador, ej. un export/reporte) y la compara contra `Recharge` por
  `(externalSystem, referenceNumber)` vía `RechargeRepository::findByExternalReference` (ya
  existente de la Fase 3) más el nuevo `findCompletedByExternalSystemSince()`. Cuatro tipos de
  descuadre: `amount_mismatch` (existe en ambos lados, monto distinto), `missing_internal` (el
  externo lo reporta, el sistema no lo tiene), `not_completed_internal` (el sistema lo tiene pero
  nunca se acreditó), `missing_external` (el sistema acreditó dinero que el externo no reporta).
  Nuevo `Admin\ReconciliationController` (`POST /reconciliation/{gatewayCode}`,
  `GET /reconciliation`, `GET /reconciliation/{id}`), permisos `reconciliation:run`/`view`
  otorgados a los roles `admin` y `super_admin`. **Verificado end-to-end** (cuenta y 3 recargas de
  prueba con `externalSystem=test_gateway`, sesión de admin real vía login, datos borrados al
  terminar): una recarga con monto igual al reportado -> `matched`; una con monto distinto ->
  `amount_mismatch`; una completada en el sistema pero omitida en la lista externa ->
  `missing_external`; una referencia inventada solo en la lista externa -> `missing_internal`. Los
  4 contadores y el detalle JSON coinciden exactamente con lo esperado.
- [x] Numeración correlativa propia para comprobantes de recarga/transferencia generados por el
  sistema (no solo para `InvoicePayment`, que depende del número que manda el sistema externo).
  Nueva tabla `balance_document_sequence` (un contador por tipo de comprobante, pre-sembrada en la
  migración `Version20260716162259` con `recharge_receipt`/`transfer_receipt` en 0 para que
  siempre haya una fila que bloquear, sin competir por insertarla la primera vez) +
  `DocumentSequenceRepository::findForUpdate()` (mismo patrón de `SELECT ... FOR UPDATE` vía
  `LockMode::PESSIMISTIC_WRITE` que `AccountBalanceRepository` de la Fase 2, sin SQL crudo) +
  `DocumentNumberService::next(documentType, prefix)` que incrementa dentro de
  `wrapInTransaction`. `RechargeService::createRecharge()`/`TransferService::createTransfer()`
  asignan `Recharge.receiptNumber`/`Transfer.receiptNumber` (columna nueva, `UNIQUE`, formato
  `REC-00000001`/`TRA-00000001`) al crear. Backfill de recargas/transferencias ya existentes en la
  misma migración, numeradas en orden de `createdAt` vía `ROW_NUMBER() OVER (ORDER BY createdat)`,
  dejando el contador continuando después del máximo backfillado. Expuesto en
  `RechargeController`/`TransferController` (list/create/show).
  **Verificado end-to-end** (cliente OAuth2 y cuentas de prueba, datos borrados al terminar): dos
  recargas creadas en secuencia -> `REC-00000003`, `REC-00000004`; **10 recargas creadas en
  paralelo** (`curl ... &` + `wait`, mismo patrón de prueba de concurrencia de la Fase 2) ->
  **10 números únicos y consecutivos**, sin duplicados ni saltos; transferencia nueva ->
  `TRA-00000001`.
- [ ] Evaluar controles AML/KYC/sanciones (OFAC) si la normativa de remesas lo exige — pendiente de
  confirmación de negocio (Fase 0).
- [x] Separar errores de validación vs. errores de negocio en las respuestas de la API (hoy todo cae
  en un 400 genérico), para que los sistemas integrados puedan reaccionar distinto a cada caso.
  Tres excepciones tipadas nuevas en `App\Exception`: `ValidationException` (request mal formado,
  el caller puede corregirlo — extiende `\InvalidArgumentException`) -> **422**,
  `NotFoundException` (el recurso referenciado no existe) -> **404**, `BusinessException` (request
  válido pero la operación no procede por el estado del negocio: saldo insuficiente, estado
  inválido, límite excedido, PIN incorrecto, etc. — extiende `\RuntimeException`) -> **409**.
  `ApiResponse::fromException()` centraliza el mapeo (nuevo campo `errorType` en la respuesta:
  `validation`/`not_found`/`business`, además del status code, para que el integrador pueda
  discriminar por código o por campo); `BaseController::handleException()` es el atajo para
  controladores que ya extienden `BaseController`. Cualquier excepción no reclasificada sigue
  cayendo en el 400 genérico de siempre (sin romper nada pendiente de migrar). Reclasificados los
  ~80 `throw new \RuntimeException(...)` de `AccountService`, `BalanceService`, `RechargeService`,
  `InvoiceService`, `TransferService`, `AuthorizedService`, `WebhookService`,
  `RegistrationService`, `PasswordResetService`; todos los catch genéricos de
  `Controller/Api/*Controller` (y `Admin\AccountController::approveBusiness`, que ya intentaba
  distinguir 400/404/409 a mano con más código y menos precisión) ahora usan
  `handleException()`/`fromException()` en vez de forzar 400 siempre.
  **Verificado end-to-end** (cliente OAuth2 y cuenta de prueba, datos borrados al terminar):
  transferencia desde una cuenta inexistente -> `404` + `errorType: not_found`; transferencia a la
  misma cuenta -> `422` + `errorType: validation`; registrar un autorizado con un documento ya
  usado -> `409` + `errorType: business`.
- [x] Auditoría de cambios en entidades administrativas (ej. Gedmo Loggable) en vez de depender de
  que cada servicio llame manualmente a un log. Instalado `stof/doctrine-extensions-bundle` +
  `gedmo/doctrine-extensions`, con `loggable: true` en `config/packages/stof_doctrine_extensions.yaml`
  (el username se toma automáticamente del token de seguridad autenticado). `#[Gedmo\Loggable]` +
  `#[Gedmo\Versioned]` por campo en `User`, `Role`, `PaymentGateway`, `ExchangeRateProvider` —
  entidades administrativas sin ningún log hoy (a diferencia de Account/Recharge/Transfer/Invoice/
  AuthorizedUser, que ya tienen `TransactionLog`/`BalanceMovement` de la Fase 4). Campos con
  credenciales quedan **fuera** de `#[Versioned]` a propósito (no deben terminar en texto plano en
  la tabla de auditoría): `User.password`/`resetToken`, `PaymentGateway.config` (tiene
  `webhook_secret`), `ExchangeRateProvider.apiKey`/`password`/`token`/`config`.
  **Bug de compatibilidad encontrado y corregido durante la instalación:** la entidad de log que
  trae Gedmo (`AbstractLogEntry`) mapea su columna `data` como type `"array"` (serializado en PHP),
  un tipo de Doctrine DBAL **eliminado en la versión 4** que usa este proyecto — rompía
  `cache:clear`. Doctrine tampoco permite cambiar el tipo de una columna heredada vía
  `#[ORM\AttributeOverride]` (solo nombre/longitud/nullable). Se creó `App\Entity\LogEntry`
  implementando `LogEntryInterface` directamente (no extiende `AbstractLogEntry`), con `data`
  mapeado como `json` desde el inicio; cada entidad auditada pasa
  `#[Gedmo\Loggable(logEntryClass: LogEntry::class)]` para usarla. Migración
  `Version20260716164925` crea `ext_log_entries`. **Verificado end-to-end** (usuario admin de
  prueba vía login real de sesión, cambio revertido y datos borrados al terminar): actualizar el
  `label` de un rol vía `POST /admin/roles/{id}/update` generó una fila en `ext_log_entries` con
  `action=update`, `object_class=App\Entity\Role`, el `username` del admin autenticado, y el diff
  exacto (`{"label":"Cajero (test gedmo)"}`) en `data`.

---

## Orden de ejecución sugerido

1. Fase 1 (seguridad API) — riesgo activo, más urgente que cualquier feature nueva.
2. Fase 2 (atomicidad) — riesgo activo de pérdida/duplicación de dinero.
3. Fase 3 (webhook idempotente) — cierra la puerta de entrada de dinero duplicado.
4. Fase 4 (trazas + consumo autorizado) — completa el negocio tal como lo describiste.
5. Fase 5 — mejoras incrementales, se pueden intercalar con desarrollo de features nuevas.