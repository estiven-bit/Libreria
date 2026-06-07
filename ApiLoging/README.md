# ApiLoging

API de **autenticación central** de Librería Gabi. Única fuente de
verdad de la identidad de usuarios.

## Qué hace

- Registro y login centralizados.
- Verificación de email, recuperación y cambio de contraseña, cambio de email.
- Emisión y validación de JWT firmados.
- Rate-limiting (anti fuerza bruta) + account lockout.
- Geo login alerts (detección de login desde país nuevo).
- Single-session enforcement (un solo dispositivo activo por usuario).
- Force-logout y ban desde panel admin.
- Sincronización de usuarios con Notion (espejo operativo).

## Stack

- PHP 8.1+ (arquitectura MVC propia).
- MySQL/MariaDB.
- Composer (vendor autoload).
- GeoIP2 + MaxMind GeoLite2 para geo alerts.

## Cómo arrancar (dev)

```bash
composer install                 # solo la primera vez
cp .env.example .env             # ajustar BD + JWT_SECRET + mail
php -S localhost:8000            # arranca la API
```

Primera vez con BD:

```sql
SOURCE database/schema.sql;
```

Requisitos: **PHP 8.1+**, **MySQL/MariaDB**, **Composer**.

## Servicio en el ecosistema

- **Consumido por**: frontend de Librería Gabi — vía `Authorization: Bearer <jwt>` en cada request.
- **Consumido por**: backend — valida el mismo JWT con el `JWT_SECRET` compartido.
- **No depende** de otros backends. Es la raíz del árbol de identidad.

## Dominio en producción

`https://libreria-backend-verdadero.vercel.app`

## Endpoints

**Públicos:**
- `POST /auth/register`
- `POST /auth/login`
- `GET /auth/verify-email?token=...`
- `POST /auth/resend-verification`
- `POST /auth/request-password-reset`
- `POST /auth/reset-password`

**Protegidos** (requieren JWT):
- `GET /auth/me`
- `POST /auth/logout`
- `POST /auth/update-username` / `update-name` / `update-phone`
- `POST /auth/request-email-change`
- `GET /auth/confirm-email-change?token=...`
- `POST /auth/change-password`
- `POST /auth/confirm-login-location` (geo alerts)

**Admin** (requieren JWT + rol admin):
- `GET /auth/admin/users`
- `POST /auth/admin/update-role`
- `POST /auth/admin/ban-user`
- `POST /auth/admin/force-logout`

## Variables de entorno

**Base de datos:**
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`

**JWT:**
- `JWT_SECRET` (debe ser el mismo en todos los backends que validen)
- `JWT_TTL_SECONDS` (default 86400 = 24h)
- `JWT_ISSUER`

**Seguridad:**
- `APP_ENV` (`local` / `production`)
- `CORS_ALLOWED_ORIGINS` (incluir todos los frontends, apex + www)
- `REDIRECT_ALLOWED_ORIGINS` (destinos válidos de `returnTo`)

**Correo:**
- `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`
- `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM`, `MAIL_FROM_NAME`

**Flujos de email:**
- `EMAIL_VERIFY_URL_BASE`, `EMAIL_VERIFY_REDIRECT_URL`
- `EMAIL_CHANGE_VERIFY_URL_BASE`, `EMAIL_CHANGE_REDIRECT_URL`
- `PASSWORD_RESET_URL_BASE`

Ejemplos completos en [`.env.example`](.env.example) y [`.env.production.example`](.env.production.example).

## Estructura

```
ApiLoging/
├── index.php                   # punto de entrada y enrutado
├── config/                     # Env, Database, Cors
├── controllers/AuthController.php
├── middleware/AuthMiddleware.php
├── models/User.php
├── services/                   # JwtService, MailService, RateLimiter, NotionService, ...
├── utils/Security.php
├── database/                   # schema.sql, oauth2_schema.sql, migrate_*.sql
├── data/                       # MaxMind GeoLite2 (gitignored)
└── scripts/cleanup.php         # OP-1: limpieza periódica (cron diario)
```

## Notas operativas

- `JWT_SECRET` debe coincidir entre ApiLoging y todos los backends que validen tokens.
- `CORS_ALLOWED_ORIGINS` y `REDIRECT_ALLOWED_ORIGINS` deben listar **apex + www** de cada dominio.
- En producción hay un **cron diario** (`0 0 * * *`) ejecutando `scripts/cleanup.php` para purgar `rate_limits` y `revoked_tokens` antiguos.

## Más documentación

- [FUNCIONALIDAD.md](FUNCIONALIDAD.md) — funcionalidades detalladas.
- [README raíz del repo](../README.md) — visión global del ecosistema.
- [docs/HARDENING_APILOGING_PENDIENTE.md](../docs/HARDENING_APILOGING_PENDIENTE.md) — hardening cerrado y pendiente.
- [docs/ECOSYSTEM_AUTH_SSO_HUB.md](../docs/ECOSYSTEM_AUTH_SSO_HUB.md) — spec del SSO Hub.
