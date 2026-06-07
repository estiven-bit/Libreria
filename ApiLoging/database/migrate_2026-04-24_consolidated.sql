-- Migración consolidada para el release 2026-04-24 sobre la prod previa
-- (release 2026-04-22). Aplica en un solo paso todos los cambios de schema
-- introducidos en esta iteración: hardening seguridad v2, ban + force-logout,
-- single-session enforcement y geo login alerts.
--
-- Idempotente en su mayor parte (IF NOT EXISTS / CREATE TABLE IF NOT EXISTS).
-- Excepción: la FK `fk_users_banned_by` no tiene `IF NOT EXISTS` en MySQL.
-- Si al re-ejecutar ves `Duplicate key name 'fk_users_banned_by'`, ignora
-- y continúa — significa que la FK ya estaba creada.
--
-- Ejecución: pega el archivo entero en phpMyAdmin (pestaña SQL dentro de la
-- BBDD de auth) o ejecuta `mysql -u user -p db < migrate_2026-04-24_consolidated.sql`.

-- =====================================================
-- 1. Hardening seguridad v2
--    · password_changed_at: invalida JWTs anteriores a un cambio de password
--    · revoked_tokens.token_hash: almacena SHA-256 del JWT en vez del plano
-- =====================================================

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER password;

ALTER TABLE revoked_tokens
  ADD COLUMN IF NOT EXISTS token_hash CHAR(64) NULL AFTER token;

ALTER TABLE revoked_tokens
  MODIFY COLUMN token TEXT NULL;

-- Backfill: rellena el hash de los tokens revocados antiguos para que el
-- middleware siga reconociéndolos como revocados tras el cambio de columna.
UPDATE revoked_tokens
SET token_hash = SHA2(token, 256)
WHERE token_hash IS NULL AND token IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_revoked_token_hash ON revoked_tokens (token_hash);

-- =====================================================
-- 2. Ban + admin force-logout
--    · banned_at / banned_by: rechazo de JWTs en middleware cuando el user
--      está baneado, con trazabilidad de qué admin aplicó el ban.
--    · sessions_invalidated_at: rechaza JWTs emitidos antes del timestamp
--      (usado por force-logout y por el propio ban).
-- =====================================================

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS banned_at DATETIME NULL AFTER is_email_verified,
  ADD COLUMN IF NOT EXISTS banned_by INT NULL AFTER banned_at,
  ADD COLUMN IF NOT EXISTS sessions_invalidated_at DATETIME NULL AFTER banned_by;

ALTER TABLE users
  ADD CONSTRAINT fk_users_banned_by FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_users_banned_at ON users (banned_at);

-- =====================================================
-- 3. Single-session enforcement
--    · current_session_id: el middleware compara contra el sid del JWT; si
--      no coincide, la sesión se considera cerrada (login en otro dispositivo).
-- =====================================================

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS current_session_id CHAR(64) NULL AFTER sessions_invalidated_at;

CREATE INDEX IF NOT EXISTS idx_users_current_session_id ON users (current_session_id);

-- =====================================================
-- 4. Geo login alerts
--    · login_locations: registra cada login con país; dispara email cuando
--      detecta un país distinto al habitual.
--    · require_password_reset: el usuario debe cambiar password en el próximo
--      login (se activa cuando rechaza una alerta con "no fui yo").
-- =====================================================

CREATE TABLE IF NOT EXISTS login_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ip VARCHAR(45) NOT NULL,
  country_code CHAR(2) NULL,
  country_name VARCHAR(100) NULL,
  user_agent VARCHAR(512) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'neutral',
  token_hash CHAR(64) NULL,
  token_expires_at DATETIME NULL,
  token_used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_login_locations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_login_locations_user_created ON login_locations (user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_login_locations_token_hash ON login_locations (token_hash);

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS require_password_reset TINYINT(1) NOT NULL DEFAULT 0;
