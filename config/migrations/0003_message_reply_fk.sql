-- Reply threading had no foreign key, so an owner reply could point at a message
-- that no longer existed. With ON DELETE CASCADE, when the retention removes a
-- finder message its reply goes with it.

ALTER TABLE dat_messages
  ADD CONSTRAINT fk_dat_messages_reply
  FOREIGN KEY (reply_to_id) REFERENCES dat_messages (id) ON DELETE CASCADE;
