<?php

/**
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function rm_present_event_card(array $event, string $page_url): array
{
    $title = $event['title'] ?? __('Untitled event', 'act-mini');
    $program_code = isset($event['programCode']) ? trim((string) $event['programCode']) : '';
    $thumb_url = isset($event['thumb']) ? trim((string) $event['thumb']) : '';

    $date_block = '';
    if (!empty($event['customDate'])) {
        $date_block = wp_kses_post((string) $event['customDate']);
    } else {
        $sd = !empty($event['startDate']) ? strtotime((string) $event['startDate']) : false;
        $ed = !empty($event['endDate']) ? strtotime((string) $event['endDate']) : false;

        if ($sd) {
            $parts = [esc_html(date_i18n(get_option('date_format'), $sd))];
            $st = isset($event['startTime']) ? trim((string) $event['startTime']) : '';
            $et = isset($event['endTime']) ? trim((string) $event['endTime']) : '';

            if ($st !== '') {
                $parts[] = esc_html($st);
            }
            if ($ed && date('Y-m-d', $ed) !== date('Y-m-d', $sd)) {
                $parts[] = '–';
                $parts[] = esc_html(date_i18n(get_option('date_format'), $ed));
                if ($et !== '') {
                    $parts[] = esc_html($et);
                }
            } elseif ($et !== '' && $et !== $st) {
                $parts[] = '– ' . esc_html($et);
            }

            $date_block = implode(' ', $parts);
        }
    }

    $venue_raw = isset($event['venue']) ? wp_strip_all_tags((string) $event['venue']) : '';
    $venue_raw = trim(preg_replace('/\s+/u', ' ', $venue_raw));
    $venue_show = $venue_raw !== '' ? wp_trim_words($venue_raw, 12, '…') : '';

    return [
        'title'            => $title,
        'program_code'     => $program_code,
        'thumb_url'        => $thumb_url,
        'date_block'       => $date_block,
        'venue_show'       => $venue_show,
        'registrants_href' => add_query_arg(
            [
                'action'     => 'get-event',
                'event_code' => $program_code,
            ],
            $page_url
        ),
        'registration_href' => rm_registration_url(
            $program_code !== '' ? ['event_code' => $program_code] : []
        ),
    ];
}
