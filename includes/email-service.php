<?php

/**
 * Registration confirmation email (webhook-triggered).
 *
 * Set RM_EMAIL_SEND_ENABLED to true in wp-config.php to send via wp_mail.
 * When false (default), the rendered email is logged only.
 */

if (!defined('RM_EMAIL_SEND_ENABLED')) {
    define('RM_EMAIL_SEND_ENABLED', false);
}

function rm_email_send_enabled(): bool
{
    return (bool) apply_filters('rm_email_send_enabled', (bool) RM_EMAIL_SEND_ENABLED);
}

/**
 * @return array{ok: bool, error: string, sent: bool, dry_run: bool, skipped: bool}
 */
function rm_email_result(
    bool $ok,
    string $error = '',
    bool $sent = false,
    bool $dry_run = false,
    bool $skipped = false
): array {
    return [
        'ok'      => $ok,
        'error'   => $error,
        'sent'    => $sent,
        'dry_run' => $dry_run,
        'skipped' => $skipped,
    ];
}

/**
 * Send (or dry-run log) a payment confirmation email for a finalized registration.
 *
 * @return array{ok: bool, error: string, sent: bool, dry_run: bool, skipped: bool}
 */
function rm_email_send_payment_confirmation(string $order_number): array
{
    $order_number = trim($order_number);
    if ($order_number === '') {
        return rm_email_result(false, 'Order number is required.');
    }

    $context = rm_email_load_confirmation_context($order_number);
    if ($context === null) {
        return rm_email_result(false, 'Registration could not be found for confirmation email.');
    }

    if (!empty($context['already_sent'])) {
        return rm_email_result(true, '', false, false, true);
    }

    $to = trim((string) ($context['to_email'] ?? ''));
    if ($to === '' || !is_email($to)) {
        return rm_email_result(false, 'Primary registrant email is missing or invalid.');
    }

    $event_title = trim((string) ($context['event']['title'] ?? 'Event'));
    $subject = sanitize_text_field($event_title . ' — Registration confirmed (' . $order_number . ')');

    $body = rm_email_render('payment-confirmation', $context);
    if ($body === '') {
        return rm_email_result(false, 'Confirmation email template could not be rendered.');
    }

    $headers = rm_email_build_headers($context);

    if (!rm_email_send_enabled()) {
        rm_email_log_dry_run($to, $subject, $body, $order_number);

        return rm_email_result(true, '', false, true);
    }

    $sent = wp_mail($to, $subject, $body, $headers);
    if (!$sent) {
        error_log('[rm_email] Failed to send confirmation email to ' . $to . ' for order ' . $order_number);

        return rm_email_result(false, 'Could not send confirmation email. Check mail configuration.');
    }

    if (!rm_email_mark_confirmation_sent($context)) {
        error_log(
            '[rm_email] Confirmation email sent to ' . $to
            . ' but failed to update is_email_confirmation_sent for order ' . $order_number
        );

        return rm_email_result(
            true,
            'Email sent but failed to update confirmation-sent flag.',
            true
        );
    }

    return rm_email_result(true, '', true);
}

/**
 * @param array<string, mixed> $context
 * @return list<string>
 */
function rm_email_build_headers(array $context): array
{
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $event_email = trim((string) ($context['event']['email'] ?? ''));
    if ($event_email === '') {
        return $headers;
    }

    foreach (explode(',', $event_email) as $cc) {
        $cc = trim($cc);
        if ($cc !== '' && is_email($cc)) {
            $headers[] = 'Cc: ' . $cc;
        }
    }

    return $headers;
}

function rm_email_log_dry_run(string $to, string $subject, string $body, string $order_number): void
{
    $preview = wp_strip_all_tags($body);
    $preview = preg_replace('/\s+/u', ' ', is_string($preview) ? $preview : '') ?? '';
    if (function_exists('mb_substr')) {
        $preview = mb_substr($preview, 0, 400);
    } else {
        $preview = substr($preview, 0, 400);
    }

    error_log(
        '[rm_email] dry_run=1'
        . ' order=' . $order_number
        . ' to=' . $to
        . ' subject=' . $subject
        . ' body_len=' . strlen($body)
        . ' preview=' . $preview
    );
}

/**
 * @param array<string, mixed> $context
 */
function rm_email_mark_confirmation_sent(array $context): bool
{
    global $wpdb;

    $source = (string) ($context['source'] ?? '');

    if ($source === 'v2') {
        $registration_id = isset($context['registration_id']) ? (int) $context['registration_id'] : 0;
        if ($registration_id < 1) {
            return false;
        }

        $updated = $wpdb->update(
            'event_registration',
            [
                'is_email_confirmation_sent' => 1,
                'updated_at'                 => current_time('mysql'),
            ],
            ['id' => $registration_id],
            ['%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    if ($source === 'legacy') {
        $order_number = trim((string) ($context['order_number'] ?? ''));
        if ($order_number === '') {
            return false;
        }

        $updated = $wpdb->update(
            'bss_registrant',
            ['isEmailConfirmationSent' => 1],
            ['orderNumber' => $order_number],
            ['%d'],
            ['%s']
        );

        return $updated !== false;
    }

    return false;
}

/**
 * @return array<string, mixed>|null
 */
function rm_email_load_confirmation_context(string $order_number): ?array
{
    $order_number = trim($order_number);
    if ($order_number === '') {
        return null;
    }

    if (function_exists('rm_event_registration_tables_exist') && rm_event_registration_tables_exist()) {
        $v2 = rm_email_load_v2_confirmation_context($order_number);
        if ($v2 !== null) {
            return $v2;
        }
    }

    return rm_email_load_legacy_confirmation_context($order_number);
}

/**
 * @return array<string, mixed>|null
 */
function rm_email_load_v2_confirmation_context(string $order_number): ?array
{
    global $wpdb;

    $header = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `event_registration` WHERE `primary_order_number` = %s LIMIT 1',
            $order_number
        ),
        ARRAY_A
    );

    if (!is_array($header) || $header === []) {
        $line = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT `registration_id` FROM `event_registrant` WHERE `order_number` = %s LIMIT 1',
                $order_number
            ),
            ARRAY_A
        );

        if (!is_array($line) || empty($line['registration_id'])) {
            return null;
        }

        $header = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `event_registration` WHERE `id` = %d LIMIT 1',
                (int) $line['registration_id']
            ),
            ARRAY_A
        );

        if (!is_array($header) || $header === []) {
            return null;
        }
    }

    $registration_id = (int) ($header['id'] ?? 0);
    $event_id = (int) ($header['event_id'] ?? 0);
    if ($registration_id < 1 || $event_id < 1) {
        return null;
    }

    $lines = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM `event_registrant`
             WHERE `registration_id` = %d
             ORDER BY `member_index` ASC, `id` ASC',
            $registration_id
        ),
        ARRAY_A
    );

    if (!is_array($lines)) {
        $lines = [];
    }

    $members = [];
    $guest_lines = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        if (($line['role'] ?? '') === 'addon') {
            $guest_lines[] = $line;
        } else {
            $members[] = rm_email_present_person_line($line);
        }
    }

    $guest_schema = rm_email_resolve_guest_schema($header, $event_id);
    $guests = [];
    foreach ($guest_lines as $gi => $guest_line) {
        $guests[] = rm_email_present_guest_line(
            $guest_line,
            $guest_schema['fields'],
            $guest_schema['label_singular'],
            $gi
        );
    }

    $primary = $members[0] ?? null;
    $to_email = trim((string) ($header['primary_email'] ?? ''));
    if ($to_email === '' && is_array($primary)) {
        $to_email = trim((string) ($primary['email'] ?? ''));
    }

    $event = rm_email_load_event_row($event_id);
    $package_label = function_exists('rm_package_label_from_header')
        ? rm_package_label_from_header($header)
        : 'Individual';

    $amount = isset($header['total_amount']) ? (float) $header['total_amount'] : 0.0;
    $currency = 'SGD';
    if (function_exists('rm_get_event_by_id') && function_exists('rm_registration_currency')) {
        $source = function_exists('rm_infer_event_source') ? rm_infer_event_source($event_id) : '';
        $event_row = rm_get_event_by_id($event_id, $source);
        if (is_array($event_row) && $event_row !== []) {
            $currency = rm_registration_currency($event_row);
        }
    }
    $payment_option = trim((string) ($header['payment_option'] ?? ''));
    if ($payment_option === '' || $payment_option === 'N/A') {
        $payment_method = 'N/A';
    } elseif (function_exists('rm_payment_normalize_option')) {
        $payment_method = rm_payment_normalize_option($payment_option);
    } else {
        $payment_method = $payment_option;
    }

    $already_sent = (int) ($header['is_email_confirmation_sent'] ?? 0) === 1;

    return [
        'source'              => 'v2',
        'registration_id'     => $registration_id,
        'order_number'        => (string) ($header['primary_order_number'] ?? $order_number),
        'confirmation_number' => (string) ($header['confirmation_number'] ?? ''),
        'already_sent'        => $already_sent,
        'to_email'            => $to_email,
        'event'               => $event,
        'primary'             => $primary,
        'members'             => $members,
        'guests'              => $guests,
        'guest_label_singular'=> $guest_schema['label_singular'],
        'guest_label_plural'  => $guest_schema['label_plural'],
        'package_label'       => $package_label,
        'amount_display'      => $currency . ' ' . number_format($amount, 2),
        'payment_method'      => $payment_method,
        'payment_status'      => (string) ($header['payment_status'] ?? ''),
        'registration_mode'   => (string) ($header['registration_mode'] ?? ''),
        'member_count'        => (int) ($header['member_count'] ?? count($members)),
        'show_members'        => count($members) > 1,
        'show_guests'         => $guests !== [],
        'show_package'        => $package_label !== '' && strcasecmp($package_label, 'Individual') !== 0,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function rm_email_load_legacy_confirmation_context(string $order_number): ?array
{
    global $wpdb;

    $registrant = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM `bss_registrant` WHERE `orderNumber` = %s LIMIT 1',
            $order_number
        ),
        ARRAY_A
    );

    if (!is_array($registrant) || $registrant === []) {
        return null;
    }

    $event_id = isset($registrant['events']) ? absint($registrant['events']) : 0;
    $event = rm_email_load_event_row($event_id);

    $christian = trim((string) ($registrant['christianName'] ?? ''));
    $given = trim((string) ($registrant['givenName'] ?? ''));
    $first = $christian !== '' ? $christian : $given;
    $last = trim((string) ($registrant['familyName'] ?? ''));
    $full_name = trim($first . ' ' . $last);

    $primary = [
        'full_name'   => $full_name !== '' ? $full_name : 'Registrant',
        'email'       => trim((string) ($registrant['email'] ?? '')),
        'contact'     => trim((string) ($registrant['contact'] ?? '')),
        'church_name' => trim((string) ($registrant['churchName'] ?? '')),
        'title'       => trim((string) ($registrant['title'] ?? '')),
        'order_number'=> (string) ($registrant['orderNumber'] ?? $order_number),
        'role'        => 'primary',
        'role_label'  => 'Primary',
    ];

    $amount = isset($registrant['amount']) ? (float) $registrant['amount'] : 0.0;
    $payment_option = trim((string) ($registrant['paymentOption'] ?? ''));
    if ($payment_option === '' || $payment_option === 'N/A') {
        $payment_method = 'N/A';
    } elseif (function_exists('rm_payment_normalize_option')) {
        $payment_method = rm_payment_normalize_option($payment_option);
    } else {
        $payment_method = $payment_option;
    }

    $already_sent = (string) ($registrant['isEmailConfirmationSent'] ?? '0') === '1';

    return [
        'source'              => 'legacy',
        'registration_id'     => 0,
        'order_number'        => (string) ($registrant['orderNumber'] ?? $order_number),
        'confirmation_number' => '',
        'already_sent'        => $already_sent,
        'to_email'            => $primary['email'],
        'event'               => $event,
        'primary'             => $primary,
        'members'             => [$primary],
        'guests'              => [],
        'guest_label_singular'=> 'Guest',
        'guest_label_plural'  => 'Guests',
        'package_label'       => '',
        'amount_display'      => 'SGD ' . number_format($amount, 2),
        'payment_method'      => $payment_method,
        'payment_status'      => 'paid',
        'registration_mode'   => '',
        'member_count'        => 1,
        'show_members'        => false,
        'show_guests'         => false,
        'show_package'        => false,
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_email_load_event_row(int $event_id): array
{
    $empty = [
        'id'           => $event_id,
        'title'        => 'Event',
        'email'        => '',
        'venue'        => '',
        'date_display' => '',
        'thumb'        => '',
        'logo_url'     => '',
    ];

    if ($event_id < 1) {
        return $empty;
    }

    $event = null;
    if (function_exists('rm_get_event_by_id')) {
        $source = function_exists('rm_infer_event_source')
            ? rm_infer_event_source($event_id)
            : '';
        $event = rm_get_event_by_id($event_id, $source);

        // Legacy callers may store CPT ids without a source hint.
        if ((!is_array($event) || $event === []) && $source !== 'cpt' && function_exists('rm_get_cpt_event_by_id')) {
            $cpt = rm_get_cpt_event_by_id($event_id);
            if (is_array($cpt) && $cpt !== []) {
                $event = $cpt;
            }
        }
    } else {
        global $wpdb;
        $event = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM `bss_events` WHERE `id` = %d LIMIT 1', $event_id),
            ARRAY_A
        );
    }

    if (!is_array($event) || $event === []) {
        return $empty;
    }

    $title = trim((string) ($event['title'] ?? ''));
    $venue = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) ($event['venue'] ?? ''))) ?? '');
    $date_display = function_exists('rm_format_event_date_display')
        ? rm_format_event_date_display($event)
        : '';
    $thumb = rm_email_normalize_asset_url(trim((string) ($event['thumb'] ?? '')));
    $email = trim((string) ($event['email'] ?? ''));
    if ($email === '' && function_exists('rm_event_decode_settings')) {
        $settings = rm_event_decode_settings($event);
        $email = trim((string) ($settings['email'] ?? $settings['contactEmail'] ?? ''));
    }

    return [
        'id'           => $event_id,
        'title'        => $title !== '' ? $title : 'Event',
        'email'        => $email,
        'venue'        => $venue,
        'date_display' => $date_display,
        'thumb'        => $thumb,
        'logo_url'     => $thumb,
    ];
}

function rm_email_normalize_asset_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $url) === 1) {
        return esc_url_raw($url);
    }

    if (str_starts_with($url, '//')) {
        return esc_url_raw('https:' . $url);
    }

    if (str_starts_with($url, '/')) {
        return esc_url_raw(home_url($url));
    }

    return esc_url_raw(home_url('/' . ltrim($url, '/')));
}

/**
 * @param array<string, mixed> $header
 * @return array{
 *     fields: list<array<string, mixed>>,
 *     label_singular: string,
 *     label_plural: string
 * }
 */
function rm_email_resolve_guest_schema(array $header, int $event_id): array
{
    $defaults = [
        'fields'         => [],
        'label_singular' => 'Guest',
        'label_plural'   => 'Guests',
    ];

    $snapshot_raw = $header['form_schema_snapshot'] ?? null;
    $snapshot = [];
    if (is_string($snapshot_raw) && $snapshot_raw !== '') {
        $decoded = json_decode($snapshot_raw, true);
        if (is_array($decoded)) {
            $snapshot = $decoded;
        }
    } elseif (is_array($snapshot_raw)) {
        $snapshot = $snapshot_raw;
    }

    if (isset($snapshot['guest']) && is_array($snapshot['guest'])) {
        $defaults['fields'] = $snapshot['guest'];
    }

    if (isset($snapshot['guest_meta']) && is_array($snapshot['guest_meta'])) {
        $singular = trim((string) ($snapshot['guest_meta']['label_singular'] ?? ''));
        $plural = trim((string) ($snapshot['guest_meta']['label_plural'] ?? ''));
        if ($singular !== '') {
            $defaults['label_singular'] = $singular;
        }
        if ($plural !== '') {
            $defaults['label_plural'] = $plural;
        }
    }

    if ($event_id > 0 && function_exists('rm_get_event_by_id') && function_exists('rm_parse_guest_form_schema')) {
        $source = function_exists('rm_infer_event_source') ? rm_infer_event_source($event_id) : '';
        $event = rm_get_event_by_id($event_id, $source);
        if ((!is_array($event) || $event === []) && $source !== 'cpt' && function_exists('rm_get_cpt_event_by_id')) {
            $event = rm_get_cpt_event_by_id($event_id);
        }
        if (is_array($event) && $event !== []) {
            $guest_schema = rm_parse_guest_form_schema($event);
            if ($defaults['fields'] === [] && !empty($guest_schema['fields']) && is_array($guest_schema['fields'])) {
                $defaults['fields'] = $guest_schema['fields'];
            }
            $singular = trim((string) ($guest_schema['label_singular'] ?? ''));
            $plural = trim((string) ($guest_schema['label_plural'] ?? ''));
            if ($singular !== '') {
                $defaults['label_singular'] = $singular;
            }
            if ($plural !== '') {
                $defaults['label_plural'] = $plural;
            }
        }
    }

    return $defaults;
}

/**
 * @param mixed $value
 */
function rm_email_format_field_value(mixed $value): string
{
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            if (is_scalar($item) || $item === null) {
                $part = trim((string) $item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }
        }

        return implode(', ', $parts);
    }

    return trim((string) $value);
}

/**
 * Build guest display rows to match checkout (schema field labels + values).
 *
 * @param array<string, mixed> $line
 * @param list<array<string, mixed>> $guest_fields
 * @return array<string, mixed>
 */
function rm_email_present_guest_line(array $line, array $guest_fields, string $label_singular, int $index): array
{
    $person = rm_email_present_person_line($line);
    $label_singular = trim($label_singular) !== '' ? trim($label_singular) : 'Guest';
    $heading = $label_singular . ' ' . ($index + 1);
    if ($person['full_name'] !== '' && $person['full_name'] !== 'Registrant') {
        $heading .= ': ' . $person['full_name'];
    }

    $custom = [];
    $custom_raw = $line['custom_responses'] ?? null;
    if (is_string($custom_raw) && $custom_raw !== '') {
        $decoded = json_decode($custom_raw, true);
        if (is_array($decoded)) {
            $custom = $decoded;
        }
    } elseif (is_array($custom_raw)) {
        $custom = $custom_raw;
    }

    $core_columns = [
        'nric', 'title', 'christian_name', 'given_name', 'family_name', 'certificate_name',
        'email', 'contact', 'address1', 'address2', 'postcode', 'church_name',
    ];

    $fields = [];
    if ($guest_fields !== []) {
        foreach ($guest_fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = sanitize_key((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $label = trim((string) ($field['label'] ?? $key));
            $value = '';

            if (($field['source'] ?? 'custom') === 'core') {
                $maps_to = (string) ($field['maps_to'] ?? $key);
                if (in_array($maps_to, $core_columns, true)) {
                    $value = rm_email_format_field_value($line[$maps_to] ?? '');
                }
            }

            if ($value === '' && array_key_exists($key, $custom)) {
                $value = rm_email_format_field_value($custom[$key]);
            }

            if ($value === '' && in_array($key, $core_columns, true)) {
                $value = rm_email_format_field_value($line[$key] ?? '');
            }

            if ($value === '') {
                continue;
            }

            $fields[] = [
                'key'   => $key,
                'label' => $label !== '' ? $label : $key,
                'value' => $value,
            ];
        }
    } else {
        // Fallback when schema snapshot is missing: core + custom values.
        $fallback_map = [
            'given_name'   => 'Given name',
            'family_name'  => 'Family name',
            'email'        => 'Email',
            'contact'      => 'Contact',
            'title'        => 'Title',
            'church_name'  => 'Church',
        ];
        foreach ($fallback_map as $key => $label) {
            $value = rm_email_format_field_value($line[$key] ?? '');
            if ($value === '') {
                continue;
            }
            $fields[] = [
                'key'   => $key,
                'label' => $label,
                'value' => $value,
            ];
        }
        foreach ($custom as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $display = rm_email_format_field_value($value);
            if ($display === '') {
                continue;
            }
            $fields[] = [
                'key'   => sanitize_key($key),
                'label' => ucwords(str_replace(['_', '-'], ' ', $key)),
                'value' => $display,
            ];
        }
    }

    $person['heading'] = $heading;
    $person['fields'] = $fields;
    $person['role_label'] = $label_singular;

    return $person;
}

/**
 * @param array<string, mixed> $line
 * @return array<string, mixed>
 */
function rm_email_present_person_line(array $line): array
{
    $christian = trim((string) ($line['christian_name'] ?? ''));
    $given = trim((string) ($line['given_name'] ?? ''));
    $first = $christian !== '' ? $christian : $given;
    $last = trim((string) ($line['family_name'] ?? ''));
    $full_name = trim($first . ' ' . $last);

    $role = (string) ($line['role'] ?? 'member');
    $role_label = 'Member';
    if ($role === 'primary') {
        $role_label = 'Primary';
    } elseif ($role === 'addon') {
        $role_label = 'Guest';
    }

    return [
        'full_name'    => $full_name !== '' ? $full_name : 'Registrant',
        'email'        => trim((string) ($line['email'] ?? '')),
        'contact'      => trim((string) ($line['contact'] ?? '')),
        'church_name'  => trim((string) ($line['church_name'] ?? '')),
        'title'        => trim((string) ($line['title'] ?? '')),
        'order_number' => trim((string) ($line['order_number'] ?? '')),
        'role'         => $role,
        'role_label'   => $role_label,
        'heading'      => '',
        'fields'       => [],
    ];
}

/**
 * @param array<string, mixed> $vars
 */
function rm_email_render(string $template, array $vars): string
{
    $template = preg_replace('/[^a-z0-9\-]/', '', strtolower($template)) ?? '';
    if ($template === '') {
        return '';
    }

    $path = dirname(__DIR__) . '/views/emails/' . $template . '.php';
    if (!is_readable($path)) {
        error_log('[rm_email] Template not found: ' . $template);

        return '';
    }

    extract($vars, EXTR_SKIP);

    ob_start();
    include $path;
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
}
