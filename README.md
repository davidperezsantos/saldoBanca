# Saldo Banca - Sistema de Gestión de Saldo

Sistema de gestión de saldo para usuarios y negocios, con soporte para recargas, transferencias, usuarios autorizados y pagos de facturas.

## Características Principales

- **Gestión de Cuentas**: Crear y administrar cuentas de saldo para usuarios y negocios
- **Recargas**: Recargar saldo desde sistemas externos o directamente por administradores
- **Transferencias**: Transferir saldo entre cuentas
- **Usuarios Autorizados**: Gestionar personas autorizadas a utilizar el saldo
- **Pagos de Facturas**: Registrar pagos de facturas como comprobantes
- **Histórico**: Mantener un registro completo de todas las transacciones
- **Dashboard**: Panel de control con estadísticas y resúmenes

## Tecnologías

- **Backend**: Symfony 7.4 / PHP 8.2+
- **Frontend**: Vue 3 + PrimeVue 4 + Tailwind CSS 4
- **Base de Datos**: PostgreSQL 16
- **Build Tool**: Vite 8

## Requisitos

- PHP 8.2 o superior
- PostgreSQL 16
- Composer
- Node.js 18+ y npm

## Instalación

### 1. Clonar el repositorio

```bash
git clone <repository-url>
cd saldoBanca
```

### 2. Instalar dependencias PHP

```bash
composer install
```

### 3. Instalar dependencias frontend

```bash
npm install
```

### 4. Configurar base de datos

```bash
# Copiar archivo de entorno
cp .env .env.local

# Editar .env.local con tus credenciales de base de datos
```

### 5. Iniciar Docker (opcional)

```bash
docker-compose up -d
```

### 6. Ejecutar migraciones

```bash
php bin/console doctrine:migrations:migrate
```

### 7. Compilar assets

```bash
npm run build
```

### 8. Iniciar servidor de desarrollo

```bash
symfony server:start
```

## Estructura del Proyecto

```
saldoBanca/
├── assets/
│   ├── js/saldo/           # Componentes Vue
│   ├── styles/             # Estilos CSS
│   └── translations/       # Traducciones i18n
├── config/
│   ├── packages/           # Configuración de paquetes
│   ├── permissions.yaml    # Permisos del sistema
│   └── services.yaml       # Servicios
├── migrations/
│   ├── Main/               # Migraciones base de datos principal
│   └── Tenant/             # Migraciones de tenant
├── src/
│   ├── Controller/         # Controladores
│   ├── DTO/                # Data Transfer Objects
│   ├── Entity/             # Entidades Doctrine
│   ├── Http/               # Respuestas HTTP
│   ├── Repository/         # Repositorios
│   └── Services/           # Servicios de negocio
└── templates/              # Templates Twig
```

## API Endpoints

### API Externa (para integración)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/v1/accounts` | Crear cuenta |
| GET | `/api/v1/accounts/{number}` | Consultar cuenta |
| POST | `/api/v1/recharges` | Registrar recarga |
| POST | `/api/v1/invoices/payment` | Registrar pago de factura |
| POST | `/api/v1/balance/check` | Verificar saldo |
| POST | `/api/v1/transfers` | Crear transferencia |
| GET | `/api/v1/authorized/{doc}/verify` | Verificar autorizado |

### API Interna (para frontend)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/saldo/accounts` | Listar cuentas |
| POST | `/saldo/accounts` | Crear cuenta |
| GET | `/saldo/recharges` | Listar recargas |
| POST | `/saldo/recharges` | Crear recarga |
| GET | `/saldo/transfers` | Listar transferencias |
| POST | `/saldo/transfers` | Crear transferencia |
| GET | `/saldo/authorized` | Listar autorizados |
| POST | `/saldo/authorized` | Crear autorizado |
| GET | `/saldo/invoices` | Listar facturas |
| POST | `/saldo/invoices` | Crear factura |

## Modelo de Datos

### Account (Cuenta)
- `accountNumber`: Número de cuenta único
- `accountType`: Tipo de cuenta (business/personal)
- `businessName`: Nombre del negocio
- `documentType`: Tipo de documento (NIT/CC/RIF/RUC)
- `documentNumber`: Número de documento
- `status`: Estado (active/suspended/closed)
- `defaultCurrency`: Moneda predeterminada

### AccountBalance (Saldo)
- `currency`: Moneda
- `availableBalance`: Saldo disponible
- `pendingBalance`: Saldo pendiente
- `reservedBalance`: Saldo reservado
- `totalRecharged`: Total recargado
- `totalTransferred`: Total transferido
- `totalInvoiced`: Total facturado

### Recharge (Recarga)
- `amount`: Monto
- `currency`: Moneda
- `rechargeType`: Tipo (external/api/admin/manual)
- `referenceNumber`: Número de referencia
- `status`: Estado (pending/completed/failed/cancelled)

### Transfer (Transferencia)
- `originAccount`: Cuenta origen
- `destinationAccount`: Cuenta destino
- `amount`: Monto
- `currency`: Moneda
- `status`: Estado (pending/completed/failed/cancelled)

### AuthorizedUser (Usuario Autorizado)
- `userName`: Nombre del usuario
- `userEmail`: Correo del usuario
- `documentNumber`: Número de documento
- `maxAmount`: Monto máximo
- `dailyLimit`: Límite diario
- `monthlyLimit`: Límite mensual
- `status`: Estado (active/suspended)

### InvoicePayment (Pago de Factura)
- `invoiceNumber`: Número de factura
- `invoiceDate`: Fecha de factura
- `amount`: Monto
- `taxAmount`: Impuesto
- `totalAmount`: Monto total
- `status`: Estado (pending/paid/cancelled/refunded)

## Seguridad

- **API Key**: Autenticación para API externa
- **RBAC**: Roles y permisos
- **Auditoría**: Todas las transacciones se registran
- **IP Logging**: Se registra IP de cada operación

## Desarrollo

### Comandos Útiles

```bash
# Crear migración
php bin/console doctrine:migrations:diff

# Ejecutar migraciones
php bin/console doctrine:migrations:migrate

# Compilar assets en desarrollo
npm run dev

# Compilar assets para producción
npm run build

# Limpiar caché
php bin/console cache:clear
```

### Variables de Entorno

```env
# Base de datos
DATABASE_URL="postgresql://saldo_user:saldo_password@127.0.0.1:5432/saldo_banca?serverVersion=16&charset=utf8"

# API Key para integración externa
API_KEY_SISTEMA_EXTERNO="tu-api-key-aqui"

# Clave de encriptación
ENCRYPTION_KEY="tu-clave-encriptacion-32-chars"
```

## Licencia

Propietario - Todos los derechos reservados
