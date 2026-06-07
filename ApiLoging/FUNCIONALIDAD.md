# Funcionalidad de ApiLoging

## Propósito Central
ApiLoging es el motor de identidad y el "Single Source of Truth" (única fuente de verdad) para todos los usuarios de Librería Gabi. Su función principal es centralizar la autenticación para que un usuario solo necesite una cuenta para acceder a todos los productos.

## Procesos de Negocio
1. **Registro Seguro**: Valida que los correos sean únicos y maneja un estado de "pendiente" hasta que el usuario confirma su dirección mediante un token temporal.
2. **Emisión de Identidad (JWT)**: Tras un login exitoso, genera un JSON Web Token (JWT) firmado que contiene el perfil básico del usuario (nombre, rol, email). Este token es el "pasaporte" que el usuario presentará ante los demás servicios.
3. **Gestión de Seguridad**: 
   - Implementa un sistema de revocación de tokens (Logout) para invalidar sesiones.
   - Posee un registrador de eventos de seguridad (SecurityLogger) para auditar accesos sospechosos o fallidos.
   - Controla el ritmo de peticiones (Rate Limiter) para proteger el sistema contra ataques de fuerza bruta.
4. **Sincronización con Notion**: Actúa como un puente operativo, enviando los datos de los usuarios a una base de datos de Notion para que el equipo de gestión tenga visibilidad inmediata de los nuevos registros.

## Roles de Usuario
- `user`: Usuario estándar con acceso a las aplicaciones de consumo.
- `real`: Usuario con permisos específicos (probablemente relacionados con el sector inmobiliario/real estate).
- `admin`: Superusuario con capacidad para listar todos los usuarios, cambiar roles y forzar la sincronización con Notion.
