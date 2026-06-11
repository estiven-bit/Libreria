# Rendimiento del login (BFF / IdP)

## Optimizaciones incluidas

1. **Email de nuevo dispositivo en segundo plano** — el SMTP ya no bloquea la redirección tras login.
2. **Cron Vercel** — `GET /bff/health` cada 5 minutos mantiene la función caliente (`vercel.json`).
3. **Callback con perfil** — `/bff/callback` devuelve `user` y el frontend evita `/bff/me` justo después del login.
4. **`/bff/me` más ligero** — reutiliza `claims_cache` y limita `last_seen` a 1 UPDATE/minuto.
5. **Precalentamiento** — el frontend llama a `/bff/health` al cargar la app.

## Keep-alive manual (si el cron de Vercel no está disponible en tu plan)

Configura un ping externo cada 5 minutos a:

```
https://TU-BFF.vercel.app/bff/health
```

Servicios gratuitos: [cron-job.org](https://cron-job.org), UptimeRobot, etc.

## Migrar la base de datos (no automatizable en código)

Para reducir latencia adicional, mueve MySQL cerca de Vercel (PlanetScale, Neon, Railway, etc.) y actualiza `DB_HOST` en el `.env` del IdP.
