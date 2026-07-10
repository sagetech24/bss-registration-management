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
    global $wpdb;

    $expected = [
        'event_registration',
        'event_registrant',
        'event_registration_pendings',
        'event_registrant_pendings',
    ];

    $found = [];
    foreach ($expected as $table) {
        $like = $wpdb->esc_like($table);
        $exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $like)
        );
        if ($exists === $table) {
            $found[] = $table;
        }
    }

    return $found;
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
