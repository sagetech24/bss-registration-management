-- Export cursor for Google Apps Script / spreadsheet sync (google4 equivalent).

ALTER TABLE `event_registrant`
  ADD COLUMN `reported` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD KEY `idx_event_reported` (`event_id`, `reported`);
