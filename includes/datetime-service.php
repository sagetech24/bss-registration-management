<?php

/**
 * Parse a datetime string to a Unix timestamp.
 *
 * Naive MySQL-style values (admin / current_time('mysql')) are interpreted in the
 * WordPress site timezone. Strings that include a timezone (ISO 8601) use that offset.
 *
 * @return int 0 when empty or unparseable.
 */
function rm_parse_datetime_to_timestamp(string $datetime): int
{
    $datetime = trim($datetime);
    if ($datetime === '') {
        return 0;
    }

    if (rm_datetime_string_has_timezone($datetime)) {
        $timestamp = strtotime($datetime);

        return $timestamp !== false ? $timestamp : 0;
    }

    $parsed = date_create($datetime, wp_timezone());

    return $parsed instanceof DateTimeInterface ? $parsed->getTimestamp() : 0;
}

/**
 * Format a datetime string for display in the WordPress site timezone.
 */
function rm_format_site_datetime(string $datetime, string $format = 'M j, Y g:iA'): string
{
    $timestamp = rm_parse_datetime_to_timestamp($datetime);

    return $timestamp > 0 ? wp_date($format, $timestamp) : 'N/A';
}

/**
 * Normalize admin-entered naive datetimes for MySQL storage.
 *
 * @param mixed $value
 * @return string|null|false Null when empty, false when invalid, string datetime otherwise.
 */
function rm_normalize_site_datetime($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $raw .= ' 00:00:00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
        $raw .= ':00';
    }

    $parsed = date_create($raw, wp_timezone());
    if (!$parsed instanceof DateTimeInterface) {
        return false;
    }

    return $parsed->format('Y-m-d H:i:s');
}

/**
 * @internal
 */
function rm_datetime_string_has_timezone(string $datetime): bool
{
    if (preg_match('/[Zz]$/', $datetime)) {
        return true;
    }

    if (preg_match('/[+-]\d{2}:?\d{2}$/', $datetime)) {
        return true;
    }

    return (bool) preg_match('/T\d{2}:\d{2}:\d{2}[+-]\d{2}/', $datetime);
}
