-- Esquema OAuth2 / OIDC para ApiLoging (Pruebas).
--
-- Aplica sobre la BBDD `libreriagabi_users` ya existente:
--   mysql -u root libreriagabi_users < oauth2_schema.sql
--
-- Tablas nuevas, no toca la `users` existente. El UserRepository de OIDC
-- consulta `users` directamente para autenticar y emitir claims.

-- USE libreriagabi_users;

-- Aplicaciones cliente registradas (Librería Gabi).
CREATE TABLE IF NOT EXISTS oauth_clients (
  id VARCHAR(80) PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  secret VARCHAR(255) NULL,
  redirect_uri TEXT NOT NULL,
  is_confidential TINYINT(1) NOT NULL DEFAULT 0,
  is_revoked TINYINT(1) NOT NULL DEFAULT 0,
  allowed_grants VARCHAR(255) NOT NULL DEFAULT 'authorization_code,refresh_token',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Scopes disponibles. OIDC mínimo: openid, profile, email.
CREATE TABLE IF NOT EXISTS oauth_scopes (
  id VARCHAR(80) PRIMARY KEY,
  description VARCHAR(255) NOT NULL
);

-- Códigos de autorización (one-shot, vida corta ≤10 min).
-- `session_id` enlaza con user_sessions: si la sesión del IdP que originó el
-- código es revocada, el access_token derivado también queda inválido.
CREATE TABLE IF NOT EXISTS oauth_auth_codes (
  id VARCHAR(100) PRIMARY KEY,
  user_id INT NOT NULL,
  client_id VARCHAR(80) NOT NULL,
  scopes TEXT NOT NULL,
  session_id CHAR(64) NULL,
  is_revoked TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  CONSTRAINT fk_authcodes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_authcodes_client FOREIGN KEY (client_id) REFERENCES oauth_clients(id) ON DELETE CASCADE
);
CREATE INDEX idx_authcodes_expires ON oauth_auth_codes (expires_at);

-- Access tokens emitidos. Permite revocación server-side (ban, force-logout).
-- `session_id` igual que en oauth_auth_codes: vincula el token a la sesión
-- del IdP que lo emitió.
CREATE TABLE IF NOT EXISTS oauth_access_tokens (
  id VARCHAR(100) PRIMARY KEY,
  user_id INT NOT NULL,
  client_id VARCHAR(80) NOT NULL,
  scopes TEXT NOT NULL,
  session_id CHAR(64) NULL,
  is_revoked TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  CONSTRAINT fk_acctokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_acctokens_client FOREIGN KEY (client_id) REFERENCES oauth_clients(id) ON DELETE CASCADE
);
CREATE INDEX idx_acctokens_expires ON oauth_access_tokens (expires_at);
CREATE INDEX idx_acctokens_user ON oauth_access_tokens (user_id);

-- Refresh tokens, encadenados con access_token.
CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
  id VARCHAR(100) PRIMARY KEY,
  access_token_id VARCHAR(100) NOT NULL,
  is_revoked TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  CONSTRAINT fk_rtokens_atoken FOREIGN KEY (access_token_id) REFERENCES oauth_access_tokens(id) ON DELETE CASCADE
);
CREATE INDEX idx_rtokens_expires ON oauth_refresh_tokens (expires_at);

-- Scopes mínimos OIDC.
INSERT IGNORE INTO oauth_scopes (id, description) VALUES
  ('openid', 'Identifica al usuario (sub claim, requerido en OIDC)'),
  ('profile', 'Acceso a nombre, username y datos de perfil'),
  ('email', 'Acceso al email del usuario');

-- Clientes registrados del ecosistema (dev / Pruebas).
INSERT IGNORE INTO oauth_clients (id, name, redirect_uri, is_confidential, allowed_grants) VALUES
  ('libreria-gabi-dev', 'Librería Gabi (Dev)', 'http://localhost:5173/oidc-callback', 0, 'authorization_code,refresh_token');
