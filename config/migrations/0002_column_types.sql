-- Column types tightened.
--
--   * public_id only ever holds A-Z and digits, so it moves to an ascii binary
--     collation: exact matching, a smaller index, no Unicode lookalikes
--   * tag_target becomes ENUM, which MySQL 5.7 really does enforce
--   * metadata_json becomes a real JSON column, so invalid JSON is rejected at
--     write time instead of being discovered later
--   * tokens and hashes become fixed-length ascii

ALTER TABLE dat_assets
  MODIFY public_id VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  MODIFY tag_target ENUM('portal','whatsapp') NOT NULL DEFAULT 'portal',
  MODIFY metadata_json JSON NULL;

ALTER TABLE dat_messages
  MODIFY sender_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL;

ALTER TABLE dat_guest_sessions
  MODIFY ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;

ALTER TABLE dat_message_rate
  MODIFY ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

ALTER TABLE dat_login_logs
  MODIFY ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;
