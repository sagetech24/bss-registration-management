<?php

/**
 * Idempotent install of event registration v2 tables.
 *
 * @return array{ok: bool, error: string, created: list<string>}
 */
function rm_install_event_registration_tables(): array
{
    global $wpdb;

    $migration_file = dirname(__DIR__) . '/migrations/001_event_registration_tables.sql';
    if (!is_readable($migration_file)) {
        return [
            'ok'      => false,
            'error'   => 'Migration file not found.',
            'created' => [],
        ];
    }

    $sql = file_get_contents($migration_file);
    if (!is_string($sql) || trim($sql) === '') {
        return [
            'ok'      => false,
            'error'   => 'Migration file is empty.',
            'created' => [],
        ];
    }

    $tables_before = rm_schema_list_event_registration_tables();
    $statements = rm_schema_split_sql_statements($sql);
    $created = [];

    foreach ($statements as $statement) {
        $result = $wpdb->query($statement);
        if ($result === false) {
            return [
                'ok'      => false,
                'error'   => $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to run migration statement.',
                'created' => $created,
            ];
        }
    }

    $tables_after = rm_schema_list_event_registration_tables();
    foreach ($tables_after as $table) {
        if (!in_array($table, $tables_before, true)) {
            $created[] = $table;
        }
    }

    $promo_install = rm_install_event_promotions_schema();
    if (!$promo_install['ok']) {
        return [
            'ok'      => false,
            'error'   => $promo_install['error'],
            'created' => array_merge($created, $promo_install['created']),
        ];
    }

    return [
        'ok'      => true,
        'error'   => '',
        'created' => array_merge($created, $promo_install['created']),
    ];
}

/**
 * Idempotent install of event_promotions + header FK columns.
 *
 * @return array{ok: bool, error: string, created: list<string>}
 */
function rm_install_event_promotions_schema(): array
{
    global $wpdb;

    $created = [];

    if (!rm_schema_table_exists('event_promotions')) {
        $sql = 'CREATE TABLE IF NOT EXISTS `event_promotions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event_id` INT UNSIGNED NOT NULL,
            `slug` VARCHAR(64) NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT NULL,
            `registration_mode` ENUM(\'individual\', \'group_flat\', \'group_per_head\') NOT NULL DEFAULT \'group_flat\',
            `member_min` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `member_max` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `require_all_members` TINYINT(1) NOT NULL DEFAULT 0,
            `package_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `pricing_config` JSON NULL,
            `valid_from` DATETIME NULL DEFAULT NULL,
            `valid_until` DATETIME NULL DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_event_slug` (`event_id`, `slug`),
            KEY `idx_event_active` (`event_id`, `is_active`, `valid_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $result = $wpdb->query($sql);
        if ($result === false) {
            return [
                'ok'      => false,
                'error'   => $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to create event_promotions.',
                'created' => $created,
            ];
        }

        $created[] = 'event_promotions';
    }

    foreach (['event_registration', 'event_registration_pendings'] as $table) {
        if (!rm_schema_table_exists($table)) {
            continue;
        }

        if (rm_schema_column_exists($table, 'event_promotion_id')) {
            continue;
        }

        $alter = $wpdb->query(
            "ALTER TABLE `{$table}`
             ADD COLUMN `event_promotion_id` INT UNSIGNED NULL DEFAULT NULL AFTER `promo_id`,
             ADD KEY `idx_event_promotion_id` (`event_promotion_id`)"
        );

        if ($alter === false) {
            return [
                'ok'      => false,
                'error'   => $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : "Failed to add event_promotion_id to {$table}.",
                'created' => $created,
            ];
        }

        $created[] = $table . '.event_promotion_id';
    }

    return [
        'ok'      => true,
        'error'   => '',
        'created' => $created,
    ];
}

/**
 * @return list<string>
 */
function rm_schema_list_event_registration_tables(): array
{
    $expected = [
        'event_registration',
        'event_registrant',
        'event_registration_pendings',
        'event_registrant_pendings',
    ];

    $found = [];
    foreach ($expected as $table) {
        if (rm_schema_table_exists($table)) {
            $found[] = $table;
        }
    }

    return $found;
}

function rm_schema_table_exists(string $table): bool
{
    global $wpdb;

    $like = $wpdb->esc_like($table);
    $exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $like)
    );

    return $exists === $table;
}

function rm_schema_column_exists(string $table, string $column): bool
{
    global $wpdb;

    if (!rm_schema_table_exists($table)) {
        return false;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE %s',
            $column
        ),
        ARRAY_A
    );

    return is_array($row) && $row !== [];
}

/**
 * @return list<string>
 */
function rm_schema_split_sql_statements(string $sql): array
{
    $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*\n/', $sql) ?: [];
    $statements = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $statements[] = $part;
        }
    }

    return $statements;
}

/**
 * @return bool
 */
function rm_event_registration_tables_exist(): bool
{
    $tables = rm_schema_list_event_registration_tables();

    return count($tables) === 4;
}

/**
 * @return bool
 */
function rm_event_promotions_schema_ready(): bool
{
    if (!rm_schema_table_exists('event_promotions')) {
        return false;
    }

    if (!rm_event_registration_tables_exist()) {
        return true;
    }

    return rm_schema_column_exists('event_registration', 'event_promotion_id')
        && rm_schema_column_exists('event_registration_pendings', 'event_promotion_id');
}
