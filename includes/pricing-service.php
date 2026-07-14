<?php

/**
 * Resolve active early-bird promo from bss_specials.
 *
 * @return array{id: int, price: float, note: string}|null
 */
function rm_pricing_active_promo(int $event_id): ?array
{
    global $wpdb;

    if ($event_id < 1) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT `id`, `price`, `note` FROM `bss_specials`
             WHERE `events` = %d AND `earlybird` >= CURDATE()
             ORDER BY `earlybird` ASC
             LIMIT 1',
            $event_id
        ),
        ARRAY_A
    );

    if (!is_array($row) || !isset($row['price']) || !is_numeric($row['price'])) {
        return null;
    }

    return [
        'id'    => isset($row['id']) ? (int) $row['id'] : 0,
        'price' => (float) $row['price'],
        'note'  => isset($row['note']) ? (string) $row['note'] : '',
    ];
}

/**
 * @param array<string, mixed> $event
 */
function rm_pricing_base_price(array $event): array
{
    $event_id = isset($event['id']) ? absint($event['id']) : 0;
    $promo = rm_pricing_active_promo($event_id);
    $event_price = rm_event_registration_price($event);

    if ($promo !== null && $promo['price'] > 0) {
        return [
            'base_price'   => $promo['price'],
            'promo_id'     => $promo['id'],
            'promo_note'   => $promo['note'],
            'event_price'  => $event_price,
            'using_promo'  => true,
        ];
    }

    return [
        'base_price'   => $event_price,
        'promo_id'     => null,
        'promo_note'   => '',
        'event_price'  => $event_price,
        'using_promo'  => false,
    ];
}

/**
 * When a package promotion is selected, package_price is authoritative —
 * early-bird bss_specials is not stacked on top.
 *
 * @param array<string, mixed> $event
 * @param list<array<string, mixed>> $members
 * @param array<string, mixed>|null $promotion
 * @return array{
 *     subtotal: float,
 *     discount_total: float,
 *     total_amount: float,
 *     promo_id: int|null,
 *     event_promotion_id: int|null,
 *     base_price: float,
 *     members: list<array{index: int, role: string, unit_price: float, discount_percent: float}>,
 *     pricing_snapshot: array<string, mixed>
 * }
 */
function rm_calculate_registration_pricing(array $event, array $members, ?array $promotion = null): array
{
    $config = rm_effective_registration_config($event, $promotion);
    $mode = $config['mode'];
    $member_count = count($members);

    if ($promotion !== null) {
        $base_price = (float) $promotion['package_price'];
        $base = [
            'base_price'  => $base_price,
            'promo_id'    => null,
            'promo_note'  => '',
            'event_price' => rm_event_registration_price($event),
            'using_promo' => false,
        ];
    } else {
        $base = rm_pricing_base_price($event);
        $base_price = $base['base_price'];
    }

    $priced_members = [];
    $subtotal = 0.0;
    $total = 0.0;
    $discount_total = 0.0;

    if ($mode === RM_REGISTRATION_MODE_GROUP_FLAT) {
        $package_price = $base_price;
        $subtotal = $package_price;
        $total = $package_price;

        foreach ($members as $index => $member) {
            $role = $index === 0 ? 'primary' : 'member';
            $unit = $member_count > 0 ? round($package_price / $member_count, 2) : 0.0;
            $priced_members[] = [
                'index'            => $index,
                'role'             => $role,
                'unit_price'       => $unit,
                'discount_percent' => 0.0,
            ];
        }
    } elseif ($mode === RM_REGISTRATION_MODE_GROUP_PER_HEAD) {
        $slots = rm_pricing_expand_slots($config, $member_count);
        $slot_subtotal = 0.0;

        foreach ($members as $index => $member) {
            $slot = $slots[$index] ?? ['role' => 'member', 'discount_percent' => 0.0];
            $discount_percent = (float) ($slot['discount_percent'] ?? 0.0);
            $unit_price = round($base_price * (1 - $discount_percent / 100), 2);
            $slot_subtotal += $base_price;
            $total += $unit_price;
            $discount_total += ($base_price - $unit_price);

            $priced_members[] = [
                'index'            => $index,
                'role'             => (string) ($slot['role'] ?? ($index === 0 ? 'primary' : 'addon')),
                'unit_price'       => $unit_price,
                'discount_percent' => $discount_percent,
            ];
        }

        $subtotal = $slot_subtotal;
    } else {
        $unit_price = $base_price;
        $subtotal = $unit_price;
        $total = $unit_price;

        $priced_members[] = [
            'index'            => 0,
            'role'             => 'primary',
            'unit_price'       => $unit_price,
            'discount_percent' => 0.0,
        ];
    }

    $event_promotion_id = $promotion !== null ? (int) ($promotion['id'] ?? 0) : null;
    if ($event_promotion_id !== null && $event_promotion_id < 1) {
        $event_promotion_id = null;
    }

    $pricing_snapshot = [
        'mode'                => $mode,
        'base_price'          => $base_price,
        'event_price'         => $base['event_price'],
        'promo_id'            => $base['promo_id'],
        'promo_note'          => $base['promo_note'],
        'using_promo'         => $base['using_promo'],
        'event_promotion_id'  => $event_promotion_id,
        'package_slug'        => $promotion !== null ? (string) ($promotion['slug'] ?? '') : null,
        'package_title'       => $promotion !== null ? (string) ($promotion['title'] ?? '') : null,
        'package_price'       => $promotion !== null ? (float) $promotion['package_price'] : null,
        'member_count'        => $member_count,
        'pricing_model'       => $config['pricing']['model'] ?? 'flat',
        'group'               => $config['group'],
        'slots'               => $config['pricing']['slots'] ?? [],
        'members'             => $priced_members,
        'subtotal'            => round($subtotal, 2),
        'discount_total'      => round($discount_total, 2),
        'total_amount'        => round($total, 2),
        'calculated_at'       => current_time('mysql'),
    ];

    return [
        'subtotal'            => round($subtotal, 2),
        'discount_total'      => round($discount_total, 2),
        'total_amount'        => round($total, 2),
        'promo_id'            => $base['promo_id'],
        'event_promotion_id'  => $event_promotion_id,
        'base_price'          => $base_price,
        'members'             => $priced_members,
        'pricing_snapshot'    => $pricing_snapshot,
    ];
}

/**
 * @return list<array{role: string, discount_percent: float}>
 */
function rm_pricing_expand_slots(array $config, int $member_count): array
{
    $slots_config = $config['pricing']['slots'] ?? [];
    $expanded = [];

    if (is_array($slots_config) && $slots_config !== []) {
        foreach ($slots_config as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $count = max(0, (int) ($slot['count'] ?? 0));
            $role = (string) ($slot['role'] ?? 'member');
            $discount = (float) ($slot['discount_percent'] ?? 0.0);

            for ($i = 0; $i < $count; $i++) {
                $expanded[] = [
                    'role'             => $role,
                    'discount_percent' => $discount,
                ];
            }
        }
    }

    while (count($expanded) < $member_count) {
        $expanded[] = [
            'role'             => count($expanded) === 0 ? 'primary' : 'addon',
            'discount_percent' => 0.0,
        ];
    }

    return array_slice($expanded, 0, $member_count);
}

/**
 * @param array<string, mixed> $event
 * @param array{total_amount: float, base_price: float, promo_id: int|null, pricing_snapshot: array<string, mixed>} $pricing
 */
function rm_present_registration_pricing(array $event, array $pricing): array
{
    $currency = rm_registration_currency($event);
    $total_display = rm_format_currency($pricing['total_amount'], $currency);
    $base_display = rm_format_currency($pricing['base_price'], $currency);

    return [
        'total_amount'   => $pricing['total_amount'],
        'total_display'  => $total_display,
        'base_price'     => $pricing['base_price'],
        'base_display'   => $base_display,
        'has_promo'      => !empty($pricing['promo_id']),
        'member_pricing' => $pricing['pricing_snapshot']['members'] ?? [],
    ];
}
