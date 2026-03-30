# Pagina Web Libreria

Ecommerce base para libreria infantil con frontend Vue 3 y backend PHP 8+.

## Estructura

- `frontend`: SPA en Vue 3.
- `backend`: API REST en PHP 8+.
- `database`: scripts SQL.
- `api`: ejemplos de endpoints.
- `services`, `controllers`, `models`, `middlewares`, `routes`, `components`, `views`: carpetas de referencia alineadas a la arquitectura.

## Requisitos

- PHP 8+
- MySQL
- Node.js 18+

## Instalacion rapida

1. Crear base de datos y ejecutar:
   - `database/schema.sql`
   - `database/seed.sql`
2. Configurar variables en `backend/.env.example`.
3. Iniciar backend con Apache o Nginx apuntando a `backend/public`.
4. Iniciar frontend:
   - `npm install`
   - `npm run dev`

## Seguridad incluida

- Hashing con bcrypt
- Prepared statements (PDO)
- Proteccion basica contra XSS
- CSRF por token
- Rate limiting basico
- JWT para sesiones seguras
- Uso de HTTPS recomendado en produccion

## Notas

- Pasarela de pago esta preparada con `PaymentService`.
- El carrito persiste en base de datos y localStorage.
- SEO basico con meta tags y titulos dinamicos.
