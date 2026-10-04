-- Administrator role.
--
-- The administrator area reads every table in the schema, so it is gated on a
-- role stored on the account rather than on a secret URL or a shared password
-- in the code. Existing accounts stay ordinary users; promote one with
--
--   php scripts/create-admin.php --email=you@example.com
--
-- MySQL 5.7 has no "ADD COLUMN IF NOT EXISTS", so this runs once and is then
-- recorded in dat_migrations.

ALTER TABLE dat_users ADD COLUMN role VARCHAR(16) NOT NULL DEFAULT 'user' AFTER status;
