-- Index review.
--
-- Every query the portal actually runs now has an index that covers it, and the
-- indexes that were never useful (low cardinality, or a left-most prefix of a
-- new composite) are gone so writes stay cheap.

-- InnoDB will not drop an index that a foreign key still needs, so each
-- composite is created before the single-column index it replaces is dropped.

-- The dashboard reads "my assets, not deleted" in one pass.
ALTER TABLE dat_assets ADD INDEX idx_dat_assets_owner_status (owner_id, status);
ALTER TABLE dat_assets DROP INDEX idx_dat_assets_owner;
ALTER TABLE dat_assets DROP INDEX idx_dat_assets_status;

-- Thread lookup, and the composite keeps serving the asset foreign key.
ALTER TABLE dat_messages ADD INDEX idx_dat_messages_thread (asset_id, sender_token, created_at);
ALTER TABLE dat_messages DROP INDEX idx_dat_messages_asset;
ALTER TABLE dat_messages DROP INDEX idx_dat_messages_token;
-- Status holds three values on a small table: the index cost more than it saved.
ALTER TABLE dat_messages DROP INDEX idx_dat_messages_status;
-- Retention deletes by expiry and must not scan the table.
ALTER TABLE dat_messages ADD INDEX idx_dat_messages_expiry (expires_at);

-- Tag lists are always read per asset and filtered by status.
ALTER TABLE dat_tags ADD INDEX idx_dat_tags_asset_status (asset_id, status);
ALTER TABLE dat_tags DROP INDEX idx_dat_tags_asset;

-- Housekeeping scans.
ALTER TABLE dat_guest_sessions ADD INDEX idx_dat_guest_sessions_expiry (expires_at);
ALTER TABLE dat_login_logs ADD INDEX idx_dat_login_logs_time (created_at);
ALTER TABLE dat_message_rate ADD INDEX idx_dat_message_rate_time (created_at);
