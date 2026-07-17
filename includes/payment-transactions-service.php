<?php

/**
 * @return array<string, mixed>
 */
function rm_build_payment_transactions_shell_context(): array
{
    $event_id = rm_get_event_id();
    $environment = rm_payment_environment($event_id);
    $api_args = ['action' => 'payment-transactions-data'];

    if ($event_id > 0) {
        $api_args['event_id'] = $event_id;
    }

    return [
        'payment_environment'               => $environment,
        'payment_transactions_initial_page' => rm_get_payment_transactions_page(),
        'payment_transactions_api_url'      => add_query_arg($api_args, rm_page_url()),
        'payment_transactions_event_id'     => $event_id,
    ];
}

/**
 * @return array<string, mixed>
 */
function rm_build_payment_transactions_data(): array
{
    $event_id = rm_get_event_id();
    $environment = rm_payment_environment($event_id);
    $result = rm_hitpay_fetch_registration_charges_for_event($event_id, $environment);

    $summary = [
        'total'           => 0,
        'succeeded_count' => 0,
        'total_amount'    => 0.0,
    ];

    $all_rows = [];

    if ($result['ok']) {
        foreach ($result['data'] as $charge) {
            $all_rows[] = rm_present_payment_transaction_row($charge);
        }

        $summary['total'] = count($all_rows);
        foreach ($all_rows as $row) {
            if ($row['is_succeeded']) {
                $summary['succeeded_count']++;
                $summary['total_amount'] += $row['amount'];
            }
        }
    }

    $per_page = rm_payment_transactions_per_page();
    $current_page = rm_get_payment_transactions_page();
    $total = count($all_rows);
    $total_pages = max(1, (int) ceil($total / $per_page));

    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }

    $offset = ($current_page - 1) * $per_page;
    $rows = array_slice($all_rows, $offset, $per_page);

    return [
        'ok'          => $result['ok'],
        'rows'        => $rows,
        'summary'     => $summary,
        'error'       => $result['ok'] ? '' : $result['error'],
        'environment' => $environment,
        'event_id'    => $event_id,
        'pagination'  => [
            'current_page' => $current_page,
            'total_pages'  => $total_pages,
            'per_page'     => $per_page,
            'total'        => $total,
            'has_prev'     => $current_page > 1,
            'has_next'     => $current_page < $total_pages,
            'from'         => $total > 0 ? $offset + 1 : 0,
            'to'           => $total > 0 ? min($offset + $per_page, $total) : 0,
        ],
    ];
}

function rm_format_payment_transaction_datetime(string $datetime): string
{
    $timestamp = trim($datetime) !== '' ? (strtotime($datetime) ?: 0) : 0;

    return $timestamp > 0 ? wp_date('M j, Y g:iA', $timestamp) : 'N/A';
}

/**
 * @return array<string, mixed>
 */
function rm_present_payment_transaction_row(array $charge): array
{
    $summary = rm_hitpay_summarize_charge($charge);
    $status = strtolower(trim($summary['status']));
    $is_succeeded = in_array($status, ['succeeded', 'completed', 'success', 'succeeded_manually'], true);

    $amount = (float) $summary['amount'];
    $currency = strtoupper(trim($summary['currency']));
    $display_currency = $currency !== '' ? $currency : 'SGD';
    $amount_display = $display_currency . ' ' . number_format_i18n($amount, 2);

    $paid_at = trim($summary['paid_at']);
    $paid_display = rm_format_payment_transaction_datetime($paid_at);
    $created_display = rm_format_payment_transaction_datetime((string) ($summary['created_at'] ?? ''));

    $status_label = $status !== ''
        ? ucwords(str_replace('_', ' ', $status))
        : 'Unknown';

    $parsed_reference = rm_payment_resolve_reference($summary['reference_number']);
    $event_id = (int) ($parsed_reference['event_id'] ?? 0);
    $pending_id = (int) ($parsed_reference['pending_id'] ?? 0);
    $customer_phone = trim($summary['customer_phone'] ?? '');
    $event_details = rm_get_event_details($event_id);

    return [
        'charge_id'            => $summary['charge_id'],
        'reference_number'     => $summary['reference_number'],
        'order_reference_number' => $summary['order_reference_number'],
        'event_id'             => $event_id,
        'event'                => $event_details,
        'event_id_display'     => $event_id > 0
            ? ($event_details['display'] !== '' ? $event_details['display'] : (string) $event_id)
            : 'N/A',
        'pending_id'           => $pending_id,
        'pending_id_display'   => $pending_id > 0 ? (string) $pending_id : 'N/A',
        'payment_request_id'   => $summary['payment_request_id'],
        'status'               => $status_label,
        'is_succeeded'         => $is_succeeded,
        'amount'               => $amount,
        'amount_display'       => $amount_display,
        'currency'             => $currency !== '' ? $currency : 'N/A',
        'payment_method'       => $summary['payment_method'],
        'payment_method_logo'  => $summary['payment_method_logo'],
        'customer_name'        => $summary['customer_name'] !== '' ? $summary['customer_name'] : 'N/A',
        'customer_email'       => $summary['customer_email'] !== '' ? $summary['customer_email'] : 'N/A',
        'customer_phone'       => $customer_phone !== '' ? $customer_phone : 'N/A',
        'paid_display'         => $paid_display,
        'created_display'      => $created_display,
        'channel'              => trim((string) ($summary['channel'] ?? '')) !== ''
            ? trim((string) $summary['channel'])
            : 'N/A',
        'remark'               => trim($summary['remark']) !== '' ? trim($summary['remark']) : 'N/A',
    ];
}
