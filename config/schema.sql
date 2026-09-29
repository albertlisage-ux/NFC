-- Digital Asset Tag Portal - MySQL / MariaDB schema
-- Idempotent: safe to run repeatedly (CREATE TABLE IF NOT EXISTS only).
-- Table prefix dat_ keeps the portal isolated inside a shared schema.

CREATE TABLE IF NOT EXISTS dat_users (
  id            CHAR(36)     NOT NULL,
  email         VARCHAR(190) NOT NULL,
  username      VARCHAR(60)  NOT NULL,
  display_name  VARCHAR(80)      NULL,
  password_hash VARCHAR(255) NOT NULL,
  status        TINYINT      NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  last_login_at DATETIME         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_dat_users_email (email),
  UNIQUE KEY uniq_dat_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dat_assets (
  id            CHAR(36)     NOT NULL,
  public_id     VARCHAR(12)  NOT NULL,
  owner_id      CHAR(36)     NOT NULL,
  type          TINYINT      NOT NULL,
  name          VARCHAR(120) NOT NULL,
  description   TEXT             NULL,
  status        TINYINT      NOT NULL DEFAULT 1,
  metadata_json LONGTEXT         NULL,
  -- Optional WhatsApp number for finders. Never rendered as text on a public
  -- page; it only becomes a wa.me button the owner has to switch on.
  contact_whatsapp VARCHAR(32)   NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  deleted_at    DATETIME         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_dat_assets_public_id (public_id),
  KEY idx_dat_assets_owner (owner_id),
  KEY idx_dat_assets_status (status),
  CONSTRAINT fk_dat_assets_owner FOREIGN KEY (owner_id) REFERENCES dat_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dat_tags (
  id          CHAR(36)     NOT NULL,
  asset_id    CHAR(36)     NOT NULL,
  type        TINYINT      NOT NULL,
  label       VARCHAR(80)      NULL,
  nfc_payload TEXT             NULL,
  url         VARCHAR(255)     NULL,
  status      TINYINT      NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL,
  replaced_at DATETIME         NULL,
  PRIMARY KEY (id),
  KEY idx_dat_tags_asset (asset_id),
  CONSTRAINT fk_dat_tags_asset FOREIGN KEY (asset_id) REFERENCES dat_assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dat_asset_images (
  id                 CHAR(36)     NOT NULL,
  asset_id           CHAR(36)     NOT NULL,
  object_key         VARCHAR(255) NOT NULL,
  original_file_name VARCHAR(190)     NULL,
  mime_type          VARCHAR(60)      NULL,
  byte_size          INT UNSIGNED      NULL,
  position           SMALLINT     NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_dat_asset_images_asset (asset_id, position),
  CONSTRAINT fk_dat_asset_images_asset FOREIGN KEY (asset_id) REFERENCES dat_assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dat_messages (
  id           CHAR(36)   NOT NULL,
  asset_id     CHAR(36)   NOT NULL,
  sender_token VARCHAR(64)    NULL,
  direction    TINYINT    NOT NULL DEFAULT 1,
  reply_to_id  CHAR(36)       NULL,
  content      TEXT       NOT NULL,
  status       TINYINT    NOT NULL DEFAULT 1,
  created_at   DATETIME   NOT NULL,
  expires_at   DATETIME       NULL,
  PRIMARY KEY (id),
  KEY idx_dat_messages_asset (asset_id),
  KEY idx_dat_messages_token (sender_token),
  KEY idx_dat_messages_status (status),
  CONSTRAINT fk_dat_messages_asset FOREIGN KEY (asset_id) REFERENCES dat_assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dat_message_rate (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash    CHAR(64)        NOT NULL,
  asset_id   CHAR(36)            NULL,
  created_at DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_dat_message_rate_lookup (ip_hash, asset_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guest sessions let a visitor create one tag before registering. The assets
-- belong to a temporary guest account which is handed over to the real account
-- on registration, and removed after the retention period otherwise.
CREATE TABLE IF NOT EXISTS dat_guest_sessions (
  id         CHAR(36)     NOT NULL,
  user_id    CHAR(36)     NOT NULL,
  ip_hash    CHAR(64)         NULL,
  created_at DATETIME     NOT NULL,
  expires_at DATETIME     NOT NULL,
  claimed_at DATETIME         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_dat_guest_sessions_user (user_id),
  KEY idx_dat_guest_sessions_ip (ip_hash, created_at),
  CONSTRAINT fk_dat_guest_sessions_user FOREIGN KEY (user_id) REFERENCES dat_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dat_login_logs (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    CHAR(36)            NULL,
  identifier VARCHAR(190)    NOT NULL,
  ip_hash    CHAR(64)            NULL,
  user_agent VARCHAR(255)        NULL,
  success    TINYINT         NOT NULL DEFAULT 0,
  reason     VARCHAR(80)         NULL,
  created_at DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_dat_login_logs_identifier (identifier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
