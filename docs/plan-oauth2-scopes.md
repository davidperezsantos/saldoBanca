> **Estado: implementado y verificado.** Decisiones tomadas (a pedido del usuario, con criterio de
> máxima seguridad): migrar a `resource.action` fino, un solo mecanismo declarativo
> (`#[RequireScope]` reemplaza `oauth2_scopes` de ruta por completo), y `requireAll` siempre
> (`requireAny` disponible en el servicio pero sin usarse hasta que haga falta). Ver "Implementación
> realizada" al final de este documento.

# Plan de factibilidad — Autorización OAuth2 por scopes (resource.action)

> Evalúa el spec "Implementación de autorización OAuth2 basada en Scopes para API" contra lo que
> ya existe en el proyecto (construido en la Fase 1 del [plan de mejoras](plan-mejoras-saldo-banca.md),
> sobre `league/oauth2-server-bundle`). Conclusión corta: **el mecanismo central del spec ya está
> implementado por la librería**; lo que falta es la granularidad `resource.action` y algunas
> comodidades de desarrollo (servicio de chequeo, atributo `#[RequireScope]`, `requireAny`).

## 1. Lo que el spec pide y ya existe hoy (verificado leyendo el código de la librería)

| Requisito del spec | Estado | Evidencia |
|---|---|---|
| Cliente existe, habilitado, secret correcto | ✅ Ya lo hace `league/oauth2-server` (grant `client_credentials`) | Estándar de la librería, en uso desde Fase 1 |
| Scopes solicitados deben existir en el catálogo | ✅ Ya lo hace | `league_oauth2_server.yaml` → `scopes.available` |
| **Scopes solicitados deben pertenecer al cliente** (el corazón del spec) | ✅ **Ya lo hace, sin código nuestro** | `vendor/league/oauth2-server-bundle/src/Repository/ScopeRepository.php:102-127` (`setupScopes()`): compara los scopes pedidos contra `$client->getScopes()`; si alguno no está, `throw OAuthServerException::invalidScope(...)` |
| Si un scope no pertenece al cliente → `400 {"error": "invalid_scope"}`, no se emite token | ✅ Ya lo hace | `OAuthServerException::invalidScope()` es el error estándar RFC 6749, formato ya compatible con lo pedido |
| Endpoint declara qué scope necesita, token debe tenerlo | ✅ Ya lo hace (semántica **AND**, todos los listados) | `CheckScopeListener::checkPassport()`: `array_diff($requestedScopes, $badge->getScopes())` → si sobra algo sin cubrir, `InsufficientScopesException` (403) |
| Modelo `OAuthClient` con scopes asignados | ✅ Ya existe, pero como columna array en `oauth2_client.scopes`, no como entidad separada | Confirmado: `SELECT column_name FROM information_schema.columns WHERE table_name='oauth2_client'` → columna `scopes` |
| Admin gestiona los scopes de cada cliente | ✅ Ya existe | `Admin/OAuthClientController` + UI en `/oauth-clients` (checkbox/lista de scopes al crear/editar) |

**Importante — un comportamiento del bundle que hay que conocer (no es un bug, es su diseño):**
si un cliente se crea **sin ningún scope asignado**, `setupScopes()` lo trata como *sin
restricción* y le deja pedir cualquier scope del catálogo, no como *sin permisos*. Ya está
documentado en `league_oauth2_server.yaml` (comentario sobre `default: ['api']`). Al crear
clientes nuevos hay que asignarles explícitamente sus scopes — nunca dejarlos en blanco pensando
que eso los deja "sin acceso".

## 2. Lo que el spec pide y **no** existe todavía

### 2.1 Granularidad `resource.action` (customers.read, sales.write, etc.)

Hoy los scopes son de grano grueso, uno por recurso completo: `accounts`, `recharges`,
`transfers`, `invoices`, `authorized`, `balance`. Un cliente con scope `recharges` puede listar
**y** completar **y** cancelar recargas — no hay forma de darle solo lectura.

**Factible: sí.** No requiere cambiar de librería ni de arquitectura, es:
1. Expandir `league_oauth2_server.yaml` → `scopes.available` a la lista fina (ej.
   `recharges.read`, `recharges.create`, `recharges.complete`, `recharges.cancel`, ...).
2. Cambiar el `defaults: ['oauth2_scopes' => [...]]` de cada ruta en los controladores `Api/*`
   para pedir el scope específico en vez del genérico (mecánico, ruta por ruta).
3. Actualizar los clientes OAuth2 ya creados (si los hay) para reasignarles los scopes finos
   equivalentes a los gruesos que tenían.

**Riesgo a decidir:** es un cambio incompatible hacia atrás — cualquier integración externa que
ya esté usando los scopes gruesos actuales dejaría de funcionar hasta que se le reasignen los
nuevos. Como el sistema aún no tiene partners reales en producción (todo lo probado hasta ahora
fueron clientes de prueba creados y borrados en esta conversación), el costo de romper
compatibilidad hoy es bajo — pero vale confirmarlo contigo antes de tocar esto.

### 2.2 `ScopeAuthorizationService` (`hasScope`/`requireScope`/`requireAny`/`requireAll`)

No existe. Hoy la única forma de exigir un scope es declarativa, vía `oauth2_scopes` en la ruta,
y el bundle exige que el token tenga **todos** los que liste la ruta (equivalente a tu
`requireAll`, pero fijo a nivel de ruta, no invocable a mano).

Lo que falta específicamente:
- Chequeo **programático** dentro de un servicio o controlador (no solo declarativo en la ruta) —
  útil si la lógica de negocio necesita ramificar según qué scope tiene el token, no solo
  bloquear/permitir el endpoint entero.
- **`requireAny`**: el bundle no lo soporta — su semántica es siempre AND. Si quieres un endpoint
  usable con *cualquiera* de dos scopes (ej. `reports.read` **o** `settings.manage`), hoy no hay
  forma de expresarlo con `oauth2_scopes` de ruta.

**Factible: sí, con esfuerzo moderado.** Se construye leyendo el token actual vía
`Symfony\Bundle\SecurityBundle\Security` (ya lo usamos en `ApiTransactionLogListener` de la Fase
4) y comparando contra sus scopes (expuestos como roles `ROLE_OAUTH2_<scope>` por el bundle, o vía
el objeto de token OAuth2 directamente).

### 2.3 Atributo `#[RequireScope('customers.read')]`

No existe. Es una alternativa ergonómica a `defaults: ['oauth2_scopes' => [...]]` en el
`#[Route]` — funcionalmente cercana a lo que ya tenemos, pero más legible y permitiría, combinado
con el `ScopeAuthorizationService` de arriba, soportar `requireAny`/`requireAll` a nivel de
atributo (cosa que el mecanismo actual de ruta no permite).

**Factible: sí.** Requiere: la clase de atributo, y un listener en `kernel.controller` (no
`kernel.request`, porque recién en `kernel.controller` se sabe qué método/controlador se va a
ejecutar) que lea el atributo por reflection y llame al `ScopeAuthorizationService`.

**A decidir:** ¿reemplaza por completo el mecanismo de `oauth2_scopes` en las rutas (una sola
forma de declarar scopes en todo el proyecto), o convive con él (el atributo solo para los casos
que necesiten `requireAny`, el resto sigue con `oauth2_scopes` de ruta)? Recomiendo lo primero —
tener dos mecanismos declarativos distintos para lo mismo es confuso — pero implica re-anotar
todas las rutas ya existentes de la Fase 1/3/4.

### 2.4 Entidad `OAuthClientScope` separada (con `enabled` por asignación)

**No recomendado tal como está especificado.** El bundle ya modela "scopes asignados a un
cliente" (columna `scopes` en `oauth2_client`, gestionada por `ClientManagerInterface`). Crear una
entidad `OAuthClientScope` paralela significaría **dos fuentes de verdad** para lo mismo: el
`ScopeRepository::setupScopes()` de la librería seguiría mirando la columna `scopes` del bundle
(no una tabla nueva nuestra) para decidir qué se autoriza en el `/token` — así que una entidad
`OAuthClientScope` propia no participaría en la validación real a menos que reescribamos también
el `ScopeRepositoryInterface`/`ClientManagerInterface` del bundle para leer de ahí, lo cual es
posible (el bundle soporta persistencia custom, ver `using-custom-persistence-managers.md`) pero
es una cirugía mayor para conseguir algo que el modelo actual ya resuelve.

**Alternativa recomendada** si lo que se busca es el flag `enabled` por scope (desactivar
`customers.delete` para un cliente puntual sin tocar los demás, con auditoría de cuándo se quitó):
usar directamente `--remove-scope`/`--add-scope` del comando ya existente
(`league:oauth2-server:update-client`), y si se quiere trazabilidad de esos cambios, loggearlos en
`TransactionLog` (ya cableado en la Fase 4) en vez de modelar una tabla nueva.

## 3. Plan de tareas (si se aprueba avanzar)

1. **Confirmar el catálogo `resource.action`** — de los recursos ya expuestos en `/api/v1/*`
   (accounts, recharges, transfers, invoices, authorized, balance, exchange-rates,
   exchange-providers, payment-gateways, history, webhooks) más las acciones reales que cada uno
   soporta (no todos tienen create/update/delete — ej. `history` es solo lectura).
2. Actualizar `league_oauth2_server.yaml` con el catálogo fino.
3. Re-anotar cada ruta de `Controller/Api/*` con su scope específico (reemplaza el
   `oauth2_scopes` grueso actual).
4. Construir `ScopeAuthorizationService` + `#[RequireScope]` + listener en `kernel.controller`,
   y migrar las rutas al nuevo atributo (o dejarlo conviviendo, según lo que se decida en 2.3).
5. Actualizar la UI de `/oauth-clients` (admin) para elegir scopes finos por checkbox
   agrupados por recurso, en vez de campo de texto libre.
6. Probar end-to-end: cliente con solo `recharges.read` no puede completar una recarga (403), un
   `invalid_scope` real al pedir un scope no asignado en `/oauth/token` (400).

## 4. Decisiones tomadas

- Migrar a `resource.action` fino: **sí** (sin clientes reales, costo cero).
- `#[RequireScope]` reemplaza `oauth2_scopes` de ruta **por completo**, no conviven — un solo
  mecanismo declarativo, para no dejar pasar por descuido un endpoint sin proteger.
- `requireAll` siempre por defecto; `requireAny` queda disponible en el servicio pero sin usarse
  hasta que aparezca un caso real (nunca es más seguro ofrecer una vía alternativa que no hace
  falta).

## 5. Implementación realizada

- **Catálogo de 30 scopes** `resource.action` en `config/packages/league_oauth2_server.yaml`
  (accounts, authorized, balance, recharges, transfers, invoices, history, exchange_rates,
  exchange_providers, payment_gateways — cada uno con las acciones reales que soporta, no
  create/read/update/delete genérico para todos). `default: ['balance.read']` — no puede ser
  vacío por restricción del propio bundle (`AddClientDefaultScopesListener` exige al menos un
  elemento); es la red de seguridad para un cliente creado sin elegir scopes, no un scope pensado
  para usarse a propósito.
- **`App\Security\Attribute\RequireScope`**: atributo `#[RequireScope('recharges.complete')]`,
  semántica siempre AND.
- **`App\Security\ScopeAuthorizationService`**: `getScopes()/hasScope()/requireScope()/
  requireAny()/requireAll()`, lee los scopes directo de `OAuth2Token::getScopes()` (no hace falta
  parsear roles `ROLE_OAUTH2_*`).
- **`App\EventListener\RequireScopeListener`** (`kernel.controller`): aplica el atributo antes de
  cada controlador de `/api/v1`. **Fail-closed a propósito**: si una ruta de negocio no pública no
  tiene `#[RequireScope]`, lanza excepción en vez de dejarla pasar sin restricción.
- **Las 40 rutas de negocio de `/api/v1/*` re-anotadas** con su scope específico (todos los
  controladores `Controller/Api/*`), reemplazando el `oauth2_scopes` de ruta de la Fase 1.
- **`App\Command\AuditOAuthScopesCommand`** (`app:oauth:audit-scopes`): además del fail-closed en
  tiempo de ejecución, lista todas las rutas de `/api/v1` y sus scopes, y falla si alguna quedó sin
  `#[RequireScope]` — para detectarlo en desarrollo/CI sin tener que golpear cada endpoint a mano.
- **UI de `/oauth-clients`**: chips de scope agrupados por recurso, con **etiqueta en español**
  (ej. "Completar (acreditar) recargas" en vez de `recharges.complete`) tanto al crear/editar un
  cliente como en la vista de detalle — a pedido del usuario, para que quien administre los
  clientes OAuth2 no necesite conocer los nombres técnicos internos. El código técnico queda como
  tooltip (`title`) por si hace falta referenciarlo.
- **Bug encontrado y corregido de paso**: los rechazos de autorización (`AccessDeniedException`
  por scope insuficiente o por falta de autenticación) devolvían la página HTML de error de
  Symfony en vez de JSON — inconsistente con el resto de una API JSON. Nuevo
  `App\EventListener\ApiExceptionListener` normaliza ambos casos al mismo contrato `ApiResponse`,
  distinguiendo correctamente 401 (sin autenticar) de 403 (autenticado, sin el scope) — Symfony
  lanza la misma clase de excepción para ambos casos, hay que mirar si hay un usuario autenticado
  para no perder esa distinción.

**Verificado end-to-end** (servidor local, cliente OAuth2 de prueba con scopes limitados,
eliminado al terminar):
- `invalid_scope` real: pedir en `/oauth/token` un scope no asignado al cliente → `400
  {"error":"invalid_scope",...}`.
- Token con `recharges.read` únicamente: acceder a `GET /recharges` → `200`; intentar `PUT
  /recharges/{id}/complete` → `403 {"success":false,"message":"Missing required scope(s):
  recharges.complete"}`.
- Sin token → `401 {"success":false,"message":"Authentication required"}` (antes de corregir el
  `ApiExceptionListener`, esto daba 403 — regresión detectada y corregida en la misma verificación).
- `php bin/console app:oauth:audit-scopes` → las 40 rutas de `/api/v1` (no públicas) tienen
  `#[RequireScope]`, ninguna quedó sin anotar.
