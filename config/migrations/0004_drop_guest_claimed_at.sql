-- dat_guest_sessions.claimed_at was never written: claiming deletes the guest
-- account, and the cascade removes the session row with it. Retention now looks
-- at expires_at alone.

ALTER TABLE dat_guest_sessions DROP COLUMN claimed_at;
