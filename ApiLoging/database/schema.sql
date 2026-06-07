-- Esquema canónico de ApiLoging.
--
-- Instalación limpia:
--   mysql -u root < schema.sql
-- Para aplicar este esquema sobre una BBDD existente (no vacía), revisa
-- primero si tus tablas coinciden con la definición actual; las cláusulas
-- `CREATE TABLE IF NOT EXISTS` no recrearán tablas antiguas con columnas
-- faltantes — en ese caso hay que migrar manualmente o desde `git log` de
-- la carpeta database/ (las migraciones históricas se eliminaron del repo,
-- pero quedan en el historial).

-- CREATE DATABASE IF NOT EXISTS libreriagabi_users
--   CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;
-- 
-- USE libreriagabi_users;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  password_changed_at DATETIME NULL,
  name VARCHAR(255) NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  phone VARCHAR(30),
  role VARCHAR(50) NOT NULL DEFAULT 'user',
  is_email_verified TINYINT(1) NOT NULL DEFAULT 0,
  banned_at DATETIME NULL,
  banned_by INT NULL,
  sessions_invalidated_at DATETIME NULL,
  current_session_id CHAR(64) NULL,
  require_password_reset TINYINT(1) NOT NULL DEFAULT 0,
  email_verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_banned_by FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_users_banned_at ON users (banned_at);
CREATE INDEX idx_users_current_session_id ON users (current_session_id);

CREATE TABLE IF NOT EXISTS pending_registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  -- URL donde reenviar al usuario tras confirmar el email (la usa
  -- OAuthController::idpRegister; sin esto el INSERT falla con
  -- "Unknown column" y el registro devuelve HTTP 500).
  return_to VARCHAR(2048) NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_pending_registrations_expiry ON pending_registrations (expires_at);

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_email_verification_user ON email_verification_tokens (user_id);
CREATE INDEX idx_email_verification_expiry ON email_verification_tokens (expires_at);

CREATE TABLE IF NOT EXISTS email_change_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  new_email VARCHAR(255) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_change_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_email_change_user ON email_change_tokens (user_id);
CREATE INDEX idx_email_change_expiry ON email_change_tokens (expires_at);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_password_reset_user ON password_reset_tokens (user_id);
CREATE INDEX idx_password_reset_expiry ON password_reset_tokens (expires_at);

-- Log de intentos de reset de contraseña (para rate-limit por email/IP).
-- OAuthController::idpForgotPassword cuenta filas con `created_at > NOW() -
-- INTERVAL 1 HOUR` para limitar a 3 intentos/email/h y 10 intentos/IP/h.
-- Sin esta tabla, el endpoint /idp/forgot-password peta con HTTP 500.
CREATE TABLE IF NOT EXISTS password_reset_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_password_reset_req_email_time ON password_reset_requests (email, created_at);
CREATE INDEX idx_password_reset_req_ip_time ON password_reset_requests (ip, created_at);

CREATE TABLE IF NOT EXISTS revoked_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token TEXT NULL,
  token_hash CHAR(64) NULL,
  revoked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_revoked_token_hash ON revoked_tokens (token_hash);
CREATE INDEX idx_revoked_token_prefix ON revoked_tokens (token(255));

CREATE TABLE IF NOT EXISTS rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_hash CHAR(64) NOT NULL UNIQUE,
  scope_name VARCHAR(100) NOT NULL,
  attempts INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_rate_limits_scope_updated ON rate_limits (scope_name, updated_at);

CREATE TABLE IF NOT EXISTS security_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(100) NOT NULL,
  user_id INT NULL,
  ip_address VARCHAR(45) NOT NULL,
  context_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_security_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_security_events_type_created ON security_events (event_type, created_at);
CREATE INDEX idx_security_events_user_created ON security_events (user_id, created_at);

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

CREATE INDEX idx_login_locations_user_created ON login_locations (user_id, created_at);
CREATE INDEX idx_login_locations_token_hash ON login_locations (token_hash);

-- Sesiones activas del IdP (una fila por dispositivo+IP de cada login). El
-- selector de cuentas y el "Dispositivos activos" de /idp/settings vienen de
-- aquí. recordSession() inserta cada vez que un usuario entra; logout marca
-- revoked_at. Sin esta tabla, /idp/verify-email peta con HTTP 500.
CREATE TABLE IF NOT EXISTS user_sessions (
  id CHAR(64) NOT NULL PRIMARY KEY,
  user_id INT NOT NULL,
  ip VARCHAR(64) NOT NULL,
  country_code VARCHAR(8) NULL,
  country_name VARCHAR(64) NULL,
  user_agent VARCHAR(512) NULL,
  device_label VARCHAR(140) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_sessions_user ON user_sessions (user_id);
CREATE INDEX idx_sessions_revoked ON user_sessions (revoked_at);

-- Alertas "nuevo dispositivo/ubicación" enviadas al usuario tras login. El
-- email lleva 2 botones (Sí soy yo / No lo soy) que llevan a
-- /idp/login-confirm?token=...&action=ok|deny; este token vive aquí.
CREATE TABLE IF NOT EXISTS login_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_session_id CHAR(64) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  action VARCHAR(20) NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_alerts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_login_alerts_user ON login_alerts (user_id);
CREATE INDEX idx_login_alerts_session ON login_alerts (user_session_id);
