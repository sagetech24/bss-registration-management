-- Display-only compare-at / list price for package strike-through on public form

ALTER TABLE `event_promotions`
    ADD COLUMN `compare_at_price` DECIMAL(10,2) NULL DEFAULT NULL AFTER `package_price`;
