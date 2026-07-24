# Manual de integración — API SaldoGrin

Guía para desarrolladores externos que quieran integrar sus sistemas (punto de venta, e-commerce, app móvil, etc.) con la API REST de SaldoGrin. Si buscas el manual del panel de administración, consulta [`manual-usuario.md`](manual-usuario.md).

## Índice

1. [Qué puedes hacer con esta API](#1-qué-puedes-hacer-con-esta-api)
2. [Antes de empezar](#2-antes-de-empezar)
3. [Autenticación (OAuth2 client credentials)](#3-autenticación-oauth2-client-credentials)
4. [Conceptos clave](#4-conceptos-clave)
5. [Flujo 1 — Dar de alta un cliente y cobrarle una factura con PIN](#5-flujo-1--dar-de-alta-un-cliente-y-cobrarle-una-factura-con-pin)
6. [Flujo 2 — Vender a un usuario autorizado (con o sin PIN)](#6-flujo-2--vender-a-un-usuario-autorizado-con-o-sin-pin)
7. [Flujo 3 — Recargar saldo](#7-flujo-3--recargar-saldo)
8. [Flujo 4 — Transferencia entre cuentas](#8-flujo-4--transferencia-entre-cuentas)
9. [Manejo de errores](#9-manejo-de-errores)
10. [Scopes disponibles](#10-scopes-disponibles)
11. [Referencia de endpoints](#11-referencia-de-endpoints)
12. [Preguntas frecuentes](#12-preguntas-frecuentes)

---

## 1. Qué puedes hacer con esta API

La API de SaldoGrin expone las mismas operaciones que usa el panel administrativo, pensadas para que un sistema externo (por ejemplo, el backend de un negocio afiliado) las dispare automáticamente: crear cuentas de clientes, emitir facturas, cobrarlas, mover saldo entre cuentas, recargar saldo, registrar las cuentas de pago de un negocio y consultar el historial e información de tasas de cambio.

Toda la API vive bajo el prefijo `/api/v1` y responde siempre en JSON con el mismo sobre de respuesta:

```json
{
  "success": true,
  "message": "Descripción legible",
  "data": { }
}
```

En errores, `success` es `false` y aparece además `errors`/`errorType` (ver [sección 9](#9-manejo-de-errores)).

## 2. Antes de empezar

Necesitas que un administrador de SaldoGrin te cree un **cliente OAuth2** desde el panel (`Administración → Clientes OAuth2`), con:

- Un `client_id` y `client_secret` (los genera el sistema al crear el cliente).
- El grant `client_credentials` habilitado.
- Los **scopes** que tu integración necesite (ver [sección 10](#10-scopes-disponibles)) — solo se conceden los permisos explícitamente asignados, así que pide únicamente lo que vayas a usar.

También puedes explorar todos los endpoints de forma interactiva en Swagger UI: `/api/docs/` (spec JSON en `/api/docs.json`).

## 3. Autenticación (OAuth2 client credentials)

Todas las llamadas a `/api/v1/*` (salvo login/register/password-reset/webhooks) requieren un header `Authorization: Bearer <access_token>`. Ese token se obtiene con el grant `client_credentials` contra `/oauth/token`:

```mermaid
sequenceDiagram
    participant Neg as Sistema del negocio
    participant OAuth as POST /oauth/token
    participant API as API /api/v1/*

    Neg->>OAuth: client_id + client_secret + grant_type=client_credentials
    OAuth-->>Neg: access_token (Bearer), expires_in
    Neg->>API: Authorization: Bearer access_token
    API-->>Neg: 200 + data
    Note over Neg,API: El token expira (ver expires_in);<br/>al vencer, repetir la primera llamada.
```

Ejemplo con `curl` (Basic Auth con `client_id:client_secret`):

```bash
curl -X POST https://tu-dominio/oauth/token \
  -u "MI_CLIENT_ID:MI_CLIENT_SECRET" \
  -d grant_type=client_credentials \
  -d scope="accounts.create invoices.create invoices.pay"
```

Respuesta:

```json
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "eyJ0eXAiOiJKV1QiLCJ..."
}
```

A partir de aquí, cada request incluye:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJ...
Content-Type: application/json
```

> El endpoint `POST /api/v1/login` (JWT usuario/contraseña) es para usuarios internos/app móvil, **no** para integraciones servidor-a-servidor — no lo uses desde tu backend.

## 4. Conceptos clave

| Concepto | Qué es |
|---|---|
| **Account** | Una cuenta (cliente o negocio) con saldo, número de cuenta y, opcionalmente, un PIN de verificación para autorizar pagos de facturas. |
| **AuthorizedUser** | Una persona autorizada a operar contra el saldo de una `Account` (ej. un empleado con un cupo). Tiene su propio PIN y límites (`maxAmount`, `dailyLimit`, `monthlyLimit`). |
| **Invoice** | Una factura/cobro pendiente contra una `Account`. Se paga con el PIN vigente de esa cuenta. |
| **PayoutAccount** | Una cuenta bancaria/de pago real de un `Account` de tipo negocio, registrada para saber a dónde transferirle el dinero al liquidar una conciliación con ese negocio. Cada una tiene una única moneda. |
| **PIN** | Código de un solo uso de 4 dígitos, enviado por WhatsApp al teléfono registrado. Se genera automáticamente al crear la cuenta/autorizado, y **rota** (se genera uno nuevo) después de cada uso exitoso. También puedes pedir uno nuevo bajo demanda con `request-pin` (máx. 1 cada 60 segundos). |
| **paymentMethod: efectivo** | Al cobrarle a un `AuthorizedUser`, puedes marcar el cobro como pagado en efectivo (`efectivo`): no se descuenta saldo interno, pero **sí** cuenta contra sus límites diario/mensual, igual que un cobro por `saldo`. |

## 5. Flujo 1 — Dar de alta un cliente y cobrarle una factura con PIN

Este es el flujo típico de un negocio que factura contra la cuenta de un cliente y necesita que el propio cliente confirme el pago con un código enviado a su WhatsApp.

```mermaid
sequenceDiagram
    participant Neg as Negocio (tu backend)
    participant API as API SaldoGrin
    participant Cli as Cliente (WhatsApp)

    Neg->>API: POST /accounts (datos del cliente)
    API-->>Neg: 201 { id, accountNumber }
    Note over API,Cli: Al crear la cuenta se envía<br/>un PIN inicial por WhatsApp

    Neg->>API: POST /invoices/payment (accountId, monto)
    API-->>Neg: 201 { invoiceNumber, status: pending }

    Neg->>API: POST /accounts/{id}/request-pin
    API->>Cli: WhatsApp con el código (ej. 4821)
    API-->>Neg: 200 Código enviado

    Cli-->>Neg: Le da el código al negocio (en caja, por teléfono, etc.)

    Neg->>API: PUT /invoices/{id}/pay { pinCode }
    API-->>Neg: 200 { status: paid }
    Note over API,Cli: El PIN usado se invalida y<br/>se envía uno nuevo por WhatsApp
```

Pasos en detalle:

1. **Crear la cuenta** — `POST /api/v1/accounts` con `businessName`, `documentNumber`, `phone`, etc. (scope `accounts.create`). El sistema genera y envía el primer PIN automáticamente.
2. **Crear la factura** — `POST /api/v1/invoices/payment` con `accountId`, `amount`, `totalAmount`, `currency` (scope `invoices.create`).
3. **Pedir el código** — `POST /api/v1/accounts/{id}/request-pin` (scope `accounts.request_pin`). Envía un PIN nuevo por WhatsApp al teléfono de la cuenta; no se puede repedir antes de 60 segundos.
4. **Pagar la factura** — `PUT /api/v1/invoices/{id}/pay` con `{"pinCode": "4821"}` (scope `invoices.pay`). Si el PIN es correcto, la factura pasa a `paid` y se rota el PIN (queda uno nuevo listo para el próximo cobro).

Si la cuenta nunca tuvo un PIN configurado (caso raro, cuentas antiguas), `pay` responde con un error indicando que se generó uno nuevo — hay que repetir el paso 3 y reintentar.

## 6. Flujo 2 — Vender a un usuario autorizado (con o sin PIN)

Un `AuthorizedUser` es alguien con cupo propio contra la cuenta de un negocio (por ejemplo, un empleado). El negocio pide el código, el autorizado lo recibe por WhatsApp, y el negocio lo usa para registrar el cargo.

```mermaid
sequenceDiagram
    participant Neg as Negocio (tu backend)
    participant API as API SaldoGrin
    participant Aut as Autorizado (WhatsApp)

    Neg->>API: POST /authorized/{id}/request-pin
    API->>Aut: WhatsApp con el código
    API-->>Neg: 200 Código enviado

    Aut-->>Neg: Comparte el código

    Neg->>API: POST /authorized/{id}/charge<br/>{ pinCode, amount o invoiceNumber, paymentMethod }
    alt paymentMethod = saldo (default)
        API-->>API: Descuenta el saldo interno de la cuenta
    else paymentMethod = efectivo
        API-->>API: No descuenta saldo,<br/>pero sí cuenta contra los límites del autorizado
    end
    API-->>Neg: 200 { usedToday, usedThisMonth, invoiceStatus }
    Note over API,Aut: El PIN rota automáticamente tras el cargo
```

- **Pedir el código** — `POST /api/v1/authorized/{id}/request-pin` (scope `authorized.request_pin`).
- **Cobrar** — `POST /api/v1/authorized/{id}/charge` (scope `authorized.charge`) con:
  - `pinCode` (obligatorio).
  - `invoiceNumber` (opcional): si la pasas, marca esa factura pendiente como pagada.
  - `amount`/`notes` (opcional): si no hay factura, registra el movimiento suelto.
  - `paymentMethod`: `"saldo"` (default) o `"efectivo"`. En `efectivo` el dinero se cobró en persona y no se mueve saldo interno, pero el cargo **sí** se descuenta del cupo diario/mensual del autorizado, igual que un cobro por `saldo`.
- Los límites (`maxAmount` por operación, `dailyLimit`, `monthlyLimit`) se validan siempre, sin importar el método de pago.

## 7. Flujo 3 — Recargar saldo

`POST /api/v1/recharges` (scope `recharges.create`) crea una recarga en estado `pending`; luego se confirma con `PUT /recharges/{id}/complete` (acredita el saldo, scope `recharges.complete`), o se marca `PUT /recharges/{id}/cancel` / `PUT /recharges/{id}/fail` según corresponda. Si tu pasarela de pago llama de vuelta a SaldoGrin, existe además el webhook público `POST /api/v1/webhooks/recharges/{gatewayCode}` (sin autenticación OAuth2, protegido por la validación propia de cada pasarela).

## 8. Flujo 4 — Transferencia entre cuentas

`POST /api/v1/transfers` (scope `transfers.create`) crea la transferencia; `PUT /transfers/{id}/process` (scope `transfers.process`) la ejecuta debitando/acreditando ambas cuentas. Antes de crearla puedes consultar `GET /transfers/limits/{accountId}` (scope `transfers.read`) para saber cuánto cupo le queda a la cuenta ese día/mes.

## 9. Manejo de errores

Todas las excepciones de negocio se traducen a un código HTTP consistente:

| Código HTTP | Tipo de error | Significado |
|---|---|---|
| `422` | `validation` | El request está mal formado o le faltan campos obligatorios. |
| `404` | `not_found` | El recurso (cuenta, factura, autorizado, etc.) no existe. |
| `409` | `business` | Regla de negocio violada (saldo insuficiente, PIN inválido, límite excedido, estado no permitido, cooldown de PIN activo, etc.). |
| `400` | — | Error genérico no clasificado. |

Cuerpo de un error típico:

```json
{
  "success": false,
  "message": "Espera un minuto antes de solicitar otro código",
  "errors": null,
  "errorType": "business"
}
```

Recomendación: en tu integración, trata `409`/`errorType: business` como errores esperables del negocio (mostrar el `message` tal cual al usuario final suele ser suficiente, están redactados en español para uso directo).

## 10. Scopes disponibles

Pide al administrador solo los scopes que tu integración vaya a usar:

| Scope | Habilita |
|---|---|
| `accounts.read` | Listar/ver cuentas |
| `accounts.create` | Crear cuentas |
| `accounts.update` | Actualizar datos de cuenta |
| `accounts.status` | Activar/suspender/cerrar cuentas |
| `accounts.request_pin` | Pedir un PIN nuevo para una cuenta |
| `payout_accounts.read` | Listar/ver las cuentas de pago de un negocio |
| `payout_accounts.create` | Registrar una cuenta de pago para un negocio |
| `payout_accounts.update` | Actualizar una cuenta de pago |
| `payout_accounts.delete` | Eliminar una cuenta de pago |
| `authorized.read` | Listar/ver/verificar autorizados |
| `authorized.create` | Crear autorizados |
| `authorized.update` | Actualizar autorizados |
| `authorized.delete` | Eliminar autorizados |
| `authorized.status` | Activar/desactivar autorizados |
| `authorized.charge` | Cobrar contra el cupo de un autorizado |
| `authorized.reset_password` | Enviar enlace de restablecimiento a un autorizado |
| `authorized.request_pin` | Pedir un PIN nuevo para un autorizado |
| `balance.read` | Consultar saldo |
| `recharges.read` / `.create` / `.complete` / `.cancel` / `.fail` | Ciclo de vida de recargas |
| `transfers.read` / `.create` / `.process` / `.cancel` | Ciclo de vida de transferencias |
| `invoices.read` / `.create` / `.pay` / `.cancel` / `.refund` | Ciclo de vida de facturas |
| `history.read` | Historial de movimientos |
| `exchange_rates.read` | Tipos de cambio vigentes |
| `exchange_providers.read` | Proveedores de tipo de cambio |
| `payment_gateways.read` | Pasarelas de pago configuradas |

## 11. Referencia de endpoints

Todas las rutas cuelgan de `/api/v1`. La columna **Scope** es el permiso OAuth2 requerido.

### Cuentas

| Método | Ruta | Scope | Descripción |
|---|---|---|---|
| GET | `/accounts` | `accounts.read` | Listar cuentas (filtros: `accountType`, `status`, `search`, paginado) |
| POST | `/accounts` | `accounts.create` | Crear cuenta |
| GET | `/accounts/{number}` | `accounts.read` | Detalle de cuenta por número |
| PUT | `/accounts/{id}` | `accounts.update` | Actualizar cuenta |
| PUT | `/accounts/{id}/status` | `accounts.status` | Cambiar estado (`active`/`suspended`/`closed`) |
| POST | `/accounts/{id}/request-pin` | `accounts.request_pin` | Enviar un PIN nuevo por WhatsApp |

### Cuentas de pago de negocios

Cuentas reales (bancarias/de pago) que un negocio registra para indicar a dónde debe transferírsele
el dinero al liquidar una conciliación (ver `manual-usuario.md`, sección Cuentas → Cuentas de pago).
Solo aplica a cuentas con `accountType: "business"` — si el `accountId` no es de tipo negocio, todas
las rutas responden `409 business`.

| Método | Ruta | Scope | Descripción |
|---|---|---|---|
| GET | `/accounts/{accountId}/payout-accounts` | `payout_accounts.read` | Listar cuentas de pago del negocio |
| POST | `/accounts/{accountId}/payout-accounts` | `payout_accounts.create` | Registrar una cuenta de pago (`alias`, `currency`, `accountNumber` obligatorios; `bankName`, `swift`, `accountHolder` opcionales) |
| PUT | `/accounts/{accountId}/payout-accounts/{id}` | `payout_accounts.update` | Actualizar una cuenta de pago (incluye `isActive` para desactivarla sin borrarla) |
| DELETE | `/accounts/{accountId}/payout-accounts/{id}` | `payout_accounts.delete` | Eliminar una cuenta de pago |

### Usuarios autorizados

| Método | Ruta | Scope | Descripción |
|---|---|---|---|
| GET | `/authorized` | `authorized.read` | Listar autorizados (filtros: `accountId`, `status`) |
| POST | `/authorized` | `authorized.create` | Crear autorizado |
| GET | `/authorized/{id}` | `authorized.read` | Detalle de autorizado |
| GET | `/authorized/{doc}/verify` | `authorized.read` | Verificar por número de documento |
| PUT | `/authorized/{id}` | `authorized.update` | Actualizar autorizado |
| PUT | `/authorized/{id}/status` | `authorized.status` | Activar/desactivar |
| DELETE | `/authorized/{id}` | `authorized.delete` | Eliminar (libera cupo reservado) |
| POST | `/authorized/{id}/request-pin` | `authorized.request_pin` | Enviar un PIN nuevo por WhatsApp |
| POST | `/authorized/{id}/charge` | `authorized.charge` | Cobrar (`pinCode`, `invoiceNumber`/`amount`, `paymentMethod`) |
| POST | `/authorized/{id}/reset-password` | `authorized.reset_password` | Enviar enlace de restablecimiento |

### Facturas

| Método | Ruta | Scope | Descripción |
|---|---|---|---|
| GET | `/invoices` | `invoices.read` | Listar facturas (filtros: `accountId`, `status`, paginado) |
| POST | `/invoices/payment` | `invoices.create` | Crear factura |
| GET | `/invoices/{number}?accountId=...` | `invoices.read` | Detalle por número (requiere `accountId`) |
| PUT | `/invoices/{id}/pay` | `invoices.pay` | Pagar (`pinCode`) |
| PUT | `/invoices/{id}/cancel` | `invoices.cancel` | Cancelar |
| PUT | `/invoices/{id}/refund` | `invoices.refund` | Reembolsar |
| GET | `/invoices/summary/{accountId}` | `invoices.read` | Resumen (pendientes/pagadas/vencidas) |

### Saldo e historial

| Método | Ruta | Scope | Descripción |
|---|---|---|---|
| GET | `/balance/{accountId}` | `balance.read` | Consultar saldo |
| POST | `/balance/check` | `balance.read` | Verificar disponibilidad de saldo |
| GET | `/history` | `history.read` | Historial de movimientos |

### Recargas

| Método | Ruta | Scope | Descripción |
|---|---|---|---|
| GET | `/recharges` | `recharges.read` | Listar |
| POST | `/recharges` | `recharges.create` | Crear (estado `pending`) |
| GET | `/recharges/{id}` | `recharges.read` | Detalle |
| PUT | `/recharges/{id}/complete` | `recharges.complete` | Confirmar y acreditar saldo |
| PUT | `/recharges/{id}/cancel` | `recharges.cancel` | Cancelar |
| PUT | `/recharges/{id}/fail` | `recharges.fail` | Marcar como fallida |
| POST | `/webhooks/recharges/{gatewayCode}` | *(público)* | Callback de la pasarela de pago |

### Transferencias

| Método | Ruta | Scope | Descripción |
|---|---|---|---|
| GET | `/transfers` | `transfers.read` | Listar |
| POST | `/transfers` | `transfers.create` | Crear |
| GET | `/transfers/{id}` | `transfers.read` | Detalle |
| PUT | `/transfers/{id}/process` | `transfers.process` | Ejecutar (debita/acredita) |
| PUT | `/transfers/{id}/cancel` | `transfers.cancel` | Cancelar |
| GET | `/transfers/limits/{accountId}` | `transfers.read` | Cupo diario/mensual disponible |

### Tasas de cambio y catálogos

| Método | Ruta | Scope | Descripción |
|---|---|---|---|
| GET | `/exchange-rates` | `exchange_rates.read` | Tasas vigentes |
| GET | `/exchange-rate/convert` | `exchange_rates.read` | Convertir un monto entre monedas |
| GET | `/exchange-providers` | `exchange_providers.read` | Proveedores de tipo de cambio |
| GET | `/payment-gateways` | `payment_gateways.read` | Pasarelas de pago configuradas |

### Registro y autenticación

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| POST | `/oauth/token` | — | Obtener access token (`client_credentials`) — ver [sección 3](#3-autenticación-oauth2-client-credentials) |
| POST | `/register/client` | público | Auto-registro de un cliente (crea `User` + `Account` + token JWT) |
| POST | `/register/business` | público | Auto-registro de un negocio (queda `pending` de aprobación) |
| POST | `/login` | público | Login JWT usuario/contraseña — **uso interno/mobile**, no para integraciones servidor-a-servidor |
| POST | `/password-reset/request` / `/confirm` | público | Restablecimiento de contraseña por WhatsApp |

Para el detalle exacto de cada payload (campos, tipos, ejemplos) usa siempre `/api/docs/` como fuente de verdad — esta tabla es un mapa de navegación, no el contrato completo.

## 12. Preguntas frecuentes

**¿El PIN de una cuenta y el de un autorizado son la misma cosa?**
No. Cada `Account` tiene su propio PIN (para pagar facturas con `PUT /invoices/{id}/pay`) y cada `AuthorizedUser` tiene el suyo (para `POST /authorized/{id}/charge`). Son independientes.

**¿Cada cuánto puedo pedir un PIN nuevo?**
Como máximo uno cada 60 segundos por cuenta/autorizado — es una protección contra spam de WhatsApp, no un límite de uso de la API en general.

**¿Qué pasa si el PIN se vence o alguien lo intenta varias veces mal?**
El PIN no vence por tiempo (es un código simple de un solo uso), pero se invalida y se genera uno nuevo automáticamente en cuanto se usa con éxito. Si el PIN es incorrecto, el endpoint de cobro/pago responde `409 business` con el mensaje correspondiente y el código sigue vigente para reintentar.

**¿Puedo usar el mismo cliente OAuth2 para todo?**
Sí, pero se recomienda pedir solo los scopes que tu integración realmente use — es más fácil de auditar y limita el impacto si el `client_secret` se filtra.

**¿Cómo sé si mi negocio tiene split de moneda o comisión del sistema configurados?**
Eso se define desde el panel de administración (no vía API) por cuenta de negocio. Afecta cómo se liquida la conciliación con el negocio, no el flujo de cobro al cliente descrito en este manual.

**¿Puede mi backend registrar las cuentas de pago del negocio en vez de cargarlas a mano en el panel?**
Sí — con los scopes `payout_accounts.*` (ver [sección 10](#10-scopes-disponibles) y la tabla en
[sección 11](#11-referencia-de-endpoints)) puedes crear, actualizar, listar o eliminar las cuentas de
pago de un negocio (`accountId` debe ser una cuenta con `accountType: "business"`). Recordá que cada
cuenta de pago tiene una sola moneda, y que si el negocio tiene split configurado va a necesitar al
menos una cuenta activa por cada moneda del split para poder aprobar sus conciliaciones.
