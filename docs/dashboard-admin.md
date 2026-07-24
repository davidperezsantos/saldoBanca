# Dashboard administrativo — qué mostrar ahora y qué más adelante

> Documento de diseño, no de implementación todavía. Objetivo: acordar qué datos entran en la
> primera versión del dashboard (con lo que ya existe hoy en el modelo) y qué queda para después
> porque depende de fases del [plan de mejoras](plan-mejoras-saldo-banca.md) que aún no están
> cerradas (Fase 3: webhooks, Fase 4: trazas y consumo de autorizados, Fase 5: conciliación/AML).

## Alcance

Este dashboard es para el **panel de administración interno** (`admin/dashboard.html.twig` +
`Dashboard.vue`, protegido por el voter `dashboard:view`) — o sea, para el equipo que opera
saldoBanca, no para que un cliente/negocio vea su propia cuenta. Un dashboard de autoservicio para
el propio cliente es un tema aparte (ver "Más adelante" al final).

## Estado actual (lo que ya existe)

`DashboardService::getStats()` + `Dashboard.vue` ya muestran: total de cuentas / activas, total
recargado y transferido (histórico completo, sin filtro de periodo), facturas pendientes, y las
últimas 5 recargas/transferencias. Dos problemas detectados al revisarlo, a corregir en la V1:

1. **`totalBalance` sesuma `SUM(availablebalance)` de *todas* las cuentas sin agrupar por moneda.**
   El sistema es multi-moneda (`AccountBalance.currency`), así que hoy ese número mezcla USD + EUR +
   CUP como si fueran la misma unidad — es un dato inválido. Ni siquiera se muestra en el frontend
   actualmente (se calcula pero se descarta), así que no ha causado daño visible todavía, pero hay
   que corregirlo antes de exponerlo.
2. **Los totales de recargas/transferencias son histórico completo, sin ventana de tiempo.** No dicen
   nada sobre la tendencia reciente (¿hoy? ¿esta semana?), y a medida que crezcan las tablas, sumar
   todo en cada carga de página se vuelve cada vez más lento.

## V1 — qué mostrar ahora (con datos que ya existen)

Todo lo de esta sección se puede construir hoy mismo sin esperar ninguna fase pendiente del plan de
mejoras; son consultas de agregación sobre tablas que ya existen y ya se llenan correctamente.

### 1. Saldo del sistema, por moneda
- Fuente: `balance_account_balance`, `GROUP BY currency`, `SUM(availablebalance)`.
- Mostrar como lista/chips por moneda (ej. "USD 128,430.00 · EUR 4,200.00"), nunca como un solo
  número sumado. Corrige el bug de `totalBalance` mencionado arriba.
- Adicional útil: `SUM(reservedbalance)` por moneda, para ver cuánto dinero está "comprometido" en
  autorizados (no disponible pero tampoco gastado).

### 2. Volumen de operaciones, con ventana de tiempo
- Recargas / transferencias / facturas: total y monto, agrupado por `status`, filtrado por
  `createdat >= NOW() - INTERVAL` con selector hoy / 7 días / 30 días (parámetro de query, default
  7 días).
- Fuente: `balance_recharge`, `balance_transfer`, `balance_invoice_payment` — ya tienen `status` y
  `createdat`/`currency`. Igual que el saldo, agrupar montos por moneda, no sumarlos entre sí.
- Por qué importa mostrar **todos** los estados (no solo completado): las recargas/transferencias en
  `pending` que llevan tiempo ahí son la señal más directa de algo atascado operativamente (ej. un
  webhook que nunca llegó, o alguien que olvidó aprobar una transferencia). Failed/cancelled también
  interesan para ver tasa de fallo.

### 3. Cuentas
- Total, activas, **pendientes de aprobación** (`status = 'pending'`, son negocios que se
  auto-registraron vía `/register/business` y esperan que un operador los apruebe —
  hoy esto no se ve en ningún lado del dashboard y es una cola de trabajo real del equipo).
  Suspendidas/inactivas también.
- Desglose por `accountType` (`client` vs `business`) — son audiencias distintas para el negocio.

### 4. Autorizados
- Total activos, saldo total reservado (`reservedbalance`, ya cubierto en el punto 1, pero vale la
  pena destacarlo aquí en contexto de autorizados).
- Autorizados cerca de su límite (`usedToday` / `dailyLimit` sobre un umbral, ej. >80%) — sirve para
  detectar patrones de uso intensivo. **Ojo:** hoy `usedToday`/`usedThisMonth` nunca se incrementan
  en el código (ver Fase 4 del plan), así que este punto muestra siempre "0% usado" hasta que se
  implemente el consumo real — hay que decidir si se incluye ahora (con ese hueco visible) o se deja
  para cuando la Fase 4 esté lista. Recomiendo dejarlo fuera del V1 y agregarlo cuando exista el
  primer movimiento real de consumo, para no mostrar una métrica que sabemos que está en cero.

### 5. Actividad reciente (ya existe, se mantiene)
- Últimas recargas y transferencias (ya implementado). Sugerido: agregar también últimas facturas
  (`balance_invoice_payment`), que hoy no aparece en el dashboard pese a ser una de las tres
  operaciones principales del sistema.

### 6. Tasas de cambio vigentes
- Fuente: `ExchangeRate` / `ExchangeRateProvider` (ya existen, alimentan `RechargeService` y el
  endpoint `/api/v1/exchange-rate/convert`). Mostrar la tasa activa por proveedor y hace cuánto se
  actualizó (`fetchedAt`) — si backend deja de refrescar tasas, es visible aquí antes de que alguien
  se queje de una recarga mal convertida.

## V2 y más adelante — depende de fases aún no cerradas

Esto no se puede construir bien todavía porque los datos que necesita no existen o no son confiables
aún. Se agrega en cuanto la fase correspondiente cierre:

- **Salud de webhooks** (recargas externas recibidas, cuántas rechazadas por firma inválida, cuántas
  detectadas como duplicado/reintento) — depende de la **Fase 3** (idempotencia + verificación de
  firma), que todavía no está implementada. Hoy no hay tabla de eventos de webhook que registrar.
- **Actividad real de autorizados** (consumo efectivo, ranking de autorizados más activos, alertas de
  límite alcanzado de verdad) — depende de la **Fase 4** (`AuthorizedService::spend()` y el diseño
  de flujo PIN/APK que definiste). Sin eso, cualquier métrica de "consumo" sería aire.
- **Trazabilidad por actor** (quién hizo qué operación, desde qué sistema externo/IP) — depende de
  cablear `TransactionLog` (también Fase 4), hoy es una entidad sin usar.
- **Salud de integraciones OAuth2** (qué cliente externo llama cuánto, tasa de error 401/403, último
  uso por cliente) — útil para saber si un partner dejó de integrar correctamente, pero requiere
  loggear las llamadas por cliente OAuth2, que hoy no se registra en ningún lado.
- **Conciliación y riesgo/AML** (descuadres contra la pasarela externa, alertas de montos inusuales)
  — depende de la **Fase 5** del plan, todavía sin fecha.
- **Series de tiempo / gráficas de tendencia** (evolución de saldo total, volumen diario en el
  tiempo) — técnicamente posible ya con los datos actuales, pero sumar todo el histórico en cada
  carga de página no escala; requiere una tabla de snapshots agregados (ej. un job diario que
  guarde el cierre del día) en vez de calcular en vivo. Se deja para cuando el volumen de datos lo
  justifique.
- **Dashboard de autoservicio para el cliente/negocio** (que cada cuenta vea *su propio* saldo,
  histórico y facturas, no el sistema completo) — distinto del dashboard admin de este documento.
  Depende de que exista al menos un endpoint protegido por JWT de usuario (hoy el JWT solo se emite
  en login/registro, nada lo consume todavía — ver nota abierta de la Fase 1 del plan de mejoras).
- **Exportación de reportes** (CSV/PDF de movimientos, facturación, etc. para contabilidad) — no
  depende de ninguna fase pendiente, es simplemente trabajo adicional no priorizado aún.

## Consideraciones de diseño a respetar en la implementación

- **Nunca sumar montos de distintas monedas** en un solo número — todo agregado monetario va
  agrupado por `currency`.
- **Todo agregado de volumen debe tener ventana de tiempo**, no histórico completo sin filtro, tanto
  por relevancia (qué pasó *recientemente*) como por rendimiento a futuro.
- **No mostrar una métrica que hoy está estructuralmente en cero** (caso `usedToday` de autorizados)
  sin dejar claro que es un placeholder — mejor omitirla hasta que el dato sea real.
- El dashboard admin ve datos de **todas** las cuentas; cualquier futuro dashboard de autoservicio
  por cuenta necesita su propio control de acceso (scoped al `accountId` del usuario autenticado),
  no es una variante del mismo endpoint con un filtro opcional.
