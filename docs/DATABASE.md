# Database review and structure

Review of the live installation (MySQL 5.7.35, eight tables, schema inspected
with `SHOW CREATE TABLE` and `information_schema`).

The portal shares the `visionary_db` schema with the existing site and prefixes
everything with `dat_`, so nothing here touches the other application.

## Tables

| Table | Purpose | Grows with |
|-------|---------|-----------|
| `dat_users` | Owner accounts, password hash only | one row per account |
| `dat_assets` | The core entity: one asset, one stable public ID | one row per asset |
| `dat_tags` | Physical tags attached to an asset (QR, NFC, later RFID) | a few per asset |
| `dat_asset_images` | Photo metadata; the binary lives in `storage/uploads` | photos per asset |
| `dat_messages` | Anonymous finder messages and owner replies | traffic |
| `dat_message_rate` | Throttle rows behind the anonymous contact form | traffic |
| `dat_guest_sessions` | Temporary accounts for the guest tag flow | visitors of `/start` |
| `dat_login_logs` | Login attempts, with a salted IP hash | sign-ins |
| `dat_migrations` | Which migration files have been applied | one per migration |

Relations are all `ON DELETE CASCADE` from the owner downwards, so removing an
account removes its assets, tags, photos and messages in one statement.

## What the review found

### 1. Retention was promised but never executed

`dat_purge_expired_messages()` existed and was tested, but no page or script ever
called it. The privacy page promises that anonymous messages expire after the
configured number of days; in reality they were kept forever.

Fixed by `includes/maintenance.php`: one housekeeping pass that

| Job | Rule |
|-----|------|
| Expired messages | `expires_at` in the past (replies go with them, see below) |
| Expired guest sessions | `expires_at` in the past, including their assets |
| Rate-limit rows | older than 24 hours |
| Login log | older than 90 days |

The pass runs on roughly one request in twenty, so no cron job is required and
no single visitor pays for the work. A scheduler can call
`dat_run_maintenance()` directly instead, and the random gate stops firing.

The same review found that `dat_purge_expired_messages()` never returned its row
count, which would have hidden a failing delete.

### 2. Indexes that did not match the queries

| Change | Why |
|--------|-----|
| `dat_assets` `(owner_id, status)` replaces two single-column indexes | the dashboard reads "my assets, not deleted" in one pass |
| `dat_messages` `(asset_id, sender_token, created_at)` replaces two indexes | the thread lookup always filters both, and this index still serves the foreign key |
| `dat_messages` dropped `status` index | three distinct values on a small table, the index cost more than it saved |
| `dat_messages` added `expires_at` index | retention deletes by expiry and must not scan |
| `dat_tags` `(asset_id, status)` | tag lists are always read per asset |
| `dat_guest_sessions`, `dat_login_logs`, `dat_message_rate` time indexes | the new housekeeping scans run regularly |

### 3. Types that were wider or looser than needed

| Column | Before | After | Why |
|--------|--------|-------|-----|
| `dat_assets.public_id` | `varchar(12)` utf8mb4 | `varchar(12)` ascii binary | only A-Z0-9 ever goes in, exact matching, a quarter of the index size |
| `dat_assets.tag_target` | `varchar(16)` | `enum('portal','whatsapp')` | MySQL 5.7 really does enforce ENUM, CHECK is parsed and ignored |
| `dat_assets.metadata_json` | `longtext` | `json` | invalid JSON is rejected at write time instead of discovered later |
| `dat_messages.sender_token` | `varchar(64)` utf8mb4 | `char(32)` ascii | always 16 random bytes in hex |
| `ip_hash` columns | `char(64)` utf8mb4 | `char(64)` ascii binary | hex digest, exact matching |

### 4. Missing foreign key

`dat_messages.reply_to_id` had no constraint, so a reply could point at a message
that no longer existed. It now has a self-referencing key with
`ON DELETE CASCADE`: when retention removes a finder message, the owner's reply
goes with it instead of being orphaned.

### 5. Dead column

`dat_guest_sessions.claimed_at` was never written, because claiming deletes the
guest account and the cascade removes the session row. Removed, and the purge
query simplified.

## Structure going forward

```
config/schema.sql          baseline for a fresh install
config/migrations/0001…    one file per change, applied once, in order
scripts/migrate.php        applies the baseline, then pending migrations
```

`dat_migrations` records what has run. A database without portal tables counts
as a fresh install: the baseline already contains every migration's effect, so
they are recorded as applied rather than executed. That keeps a first install
and a long-running installation on exactly the same structure.

## Deliberate decisions

- **No CHECK constraints.** MySQL 5.7 parses and ignores them, so they would give
  a false sense of safety. Values are constrained by ENUM where possible and
  validated in the application layer.
- **No JSON indexes.** The metadata is always read with its asset row, never
  queried by key, so no generated columns are needed.
- **DATETIME, not TIMESTAMP.** Times are stored in the application timezone
  (Europe/Berlin) which the connection sets, avoiding the 2038 limit.
- **Soft delete for assets.** Status `5` plus `deleted_at` keeps a printed tag
  answering instead of returning a 404.

## Running it

```bash
php scripts/migrate.php     # idempotent, safe on every deploy
```
