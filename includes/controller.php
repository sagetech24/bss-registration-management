<?php

/**
 * @return array<string, mixed>
 */
function rm_build_context(): array
{
    $view_action = rm_get_view_action();

    if (rm_is_public_view($view_action)) {
        return rm_build_register_context();
    }

    return rm_build_dashboard_context();
}

/**
 * @return array<string, mixed>
 */
function rm_build_register_context(): array
{
    $view_action = rm_get_view_action();
    if ($view_action === 'payment-return') {
        rm_handle_payment_return();
    }

    $event_code = rm_get_event_code();
    $package_slug = rm_get_registration_package_slug();
    $url_args = [];
    if ($event_code !== '') {
        $url_args['event_code'] = $event_code;
    }
    if ($package_slug !== '') {
        $url_args['package'] = $package_slug;
    }
    $page_url = rm_registration_url($url_args);
    $form_input = rm_get_registration_form_input();

    $context = [
        'view_action'         => 'register',
        'is_public_layout'    => true,
        'page_url'            => $page_url,
        'event_code'          => $event_code,
        'package_slug'        => $package_slug,
        'event'               => null,
        'event_present'       => null,
        'active_promotion'    => null,
        'promotion_present'   => null,
        'form_input'          => $form_input,
        'form_errors'         => [],
        'success_message'     => '',
        'order_number'        => '',
        'error_message'       => '',
        'individual_href'     => '',
    ];

    if ($event_code === '') {
        $context['error_message'] = 'No event was selected. Please use a valid registration link.';

        return $context;
    }

    $event_fetch = rm_fetch_event($event_code);
    if ($event_fetch['error'] !== '') {
        $context['error_message'] = $event_fetch['error'];

        return $context;
    }

    if (!is_array($event_fetch['event']) || empty($event_fetch['event'])) {
        $context['error_message'] = 'This event could not be found.';

        return $context;
    }

    $event = $event_fetch['event'];
    $context['event'] = $event;
    $context['event_present'] = rm_present_registration_event($event);
    $context['uses_v2'] = rm_event_uses_v2_registration($event);
    $context['individual_href'] = rm_registration_url(['event_code' => $event_code]);

    $promotion = null;
    if ($context['uses_v2']) {
        $resolved = rm_resolve_registration_promotion($event);
        if (!$resolved['ok']) {
            $context['error_message'] = $resolved['error'];
            $context['event_present'] = null;

            return $context;
        }
        $promotion = $resolved['promotion'];
    } elseif ($package_slug !== '') {
        $context['error_message'] = 'This registration package is not available.';
        $context['event_present'] = null;

        return $context;
    }

    $context['active_promotion'] = $promotion;
    $context['promotion_present'] = $promotion !== null
        ? rm_present_event_promotion($promotion)
        : null;
    $context['registration_config'] = rm_effective_registration_config($event, $promotion);
    $context['form_schema'] = rm_parse_form_schema($event);
    $context['is_group_mode'] = rm_effective_is_group_mode($event, $promotion);
    $context['group_limits'] = rm_effective_group_limits($event, $promotion);
    $context['pricing_preview'] = rm_present_registration_pricing(
        $event,
        rm_calculate_registration_pricing($event, [[]], $promotion)
    );
    $context['members_input'] = [];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $flash = rm_consume_registration_success_flash(rm_get_registration_flash_key());
        if ($flash !== null) {
            $context['success_message'] = rm_registration_success_message($flash['status']);
            $context['order_number'] = $flash['order_number'];
        }

        return $context;
    }

    if (
        !isset($_POST['rm_register_nonce'])
        || !wp_verify_nonce(
            sanitize_text_field(wp_unslash((string) $_POST['rm_register_nonce'])),
            'rm_register'
        )
    ) {
        $context['error_message'] = 'Your session has expired. Please submit the form again.';

        return $context;
    }

    $form_errors = [];
    if ($context['uses_v2']) {
        $members_post = rm_parse_members_from_post();
        if ($members_post !== []) {
            $context['members_input'] = $members_post;
        } elseif (!$context['is_group_mode']) {
            $context['members_input'] = [rm_form_responses_from_post($context['form_schema'])];
        }
    } else {
        $form_errors = rm_validate_registration_input($form_input);
    }

    if ($form_errors !== []) {
        $context['form_errors'] = $form_errors;

        return $context;
    }

    $result = rm_submit_registration($event, $form_input);
    if (!$result['ok']) {
        $context['error_message'] = $result['error'];
        if (!empty($result['form_errors']) && is_array($result['form_errors'])) {
            $context['form_errors'] = $result['form_errors'];
        }

        return $context;
    }

    $checkout_registrant = $form_input;
    if ($context['uses_v2'] && $result['pending_id'] > 0) {
        $pending_row = rm_payment_load_pending($result['pending_id']);
        if (is_array($pending_row) && !empty($pending_row['_primary'])) {
            $checkout_registrant = rm_v2_registrant_for_payment($pending_row['_primary']);
        }
    }

    if ($result['status'] === 'pending_payment' && $result['pending_id'] > 0) {
        $checkout = rm_payment_initiate_checkout(
            $result['pending_id'],
            $event,
            $checkout_registrant,
            $event_code
        );

        if ($checkout['ok'] && $checkout['url'] !== '') {
            if (!rm_payment_redirect_to_checkout($checkout['url'])) {
                $context['error_message'] = 'Payment could not be started. Please try again or contact us.';

                return $context;
            }
        }

        $context['error_message'] = $checkout['error'] !== ''
            ? $checkout['error']
            : 'Payment could not be started. Please try again or contact us.';

        return $context;
    }

    $flash_key = rm_store_registration_success_flash(
        $result['order_number'],
        $result['status']
    );
    $redirect_args = [
        'event_code' => $event_code,
        'registered' => $flash_key,
    ];
    if ($package_slug !== '') {
        $redirect_args['package'] = $package_slug;
    }
    wp_safe_redirect(rm_registration_url($redirect_args));
    exit;
}

function rm_handle_payment_return(): void
{
    $event_code = rm_get_event_code();
    $pending_id = rm_get_pending_id();
    $payment_reference = rm_get_payment_reference();
    $payment_status = rm_get_payment_status();

    if ($event_code === '' || $pending_id < 1) {
        wp_safe_redirect(rm_registration_url(['event_code' => $event_code]));
        exit;
    }

    if ($payment_status !== 'completed' || $payment_reference === '') {
        $flash_key = rm_store_registration_success_flash('', 'payment_failed');
        wp_safe_redirect(
            rm_registration_url([
                'event_code' => $event_code,
                'registered' => $flash_key,
            ])
        );
        exit;
    }

    $result = rm_payment_handle_completed($pending_id, $payment_reference);
    if (!$result['ok']) {
        $flash_key = rm_store_registration_success_flash('', 'payment_failed');
        wp_safe_redirect(
            rm_registration_url([
                'event_code' => $event_code,
                'registered' => $flash_key,
            ])
        );
        exit;
    }

    $flash_key = rm_store_registration_success_flash(
        $result['order_number'],
        'confirmed'
    );
    wp_safe_redirect(
        rm_registration_url([
            'event_code' => $event_code,
            'registered' => $flash_key,
        ])
    );
    exit;
}

/**
 * @return array<string, mixed>
 */
function rm_build_dashboard_context(): array
{
    rm_require_login();

    $view_action = rm_get_view_action();

    if ($view_action === 'get-event-profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        rm_handle_event_profile_post();
    }

    $event_search = rm_get_event_search();
    $page_url = rm_page_url();

    $fetch = rm_fetch_registration_events();
    $event_years = rm_get_available_event_years($fetch['events']);
    $event_year = rm_get_event_year($event_years);
    $event_filter = rm_get_event_filter($event_year);
    $events = rm_filter_events($fetch['events'], $event_filter, $event_search, $event_year);

    $context = [
        'event_count'              => rm_count_filtered_events($events),
        'has_active_event_filters' => rm_has_active_event_filters($event_filter, $event_year, $event_search),
        'welcome_name'         => rm_get_welcome_name(),
        'view_action'          => $view_action,
        'active_nav'           => rm_active_nav($view_action),
        'page_url'             => $page_url,
        'event_filter'         => $event_filter,
        'event_filter_options' => rm_event_filter_options(),
        'event_year'           => $event_year,
        'event_years'          => $event_years,
        'event_search'         => $event_search,
        'events'               => $events,
        'error_message'        => $fetch['error'],
    ];

    if ($view_action === 'get-event-registrants') {
        $event_code = rm_get_event_code();
        if ($event_code === '') {
            wp_safe_redirect(rm_page_url());
            exit;
        }

        $registrants_context = rm_build_registrants_context($fetch['events'], $event_code, rm_get_event_id());

        if (!empty($registrants_context['event_not_found'])) {
            $context['event_not_found'] = true;
            $context['selected_event_code'] = $registrants_context['selected_event_code'];
        } else {
            $context = array_merge($context, $registrants_context);
        }
    }

    if ($view_action === 'get-event-profile') {
        $event_code = rm_get_event_code();
        $event_id = rm_get_event_id();
        if ($event_code === '' && $event_id < 1) {
            wp_safe_redirect(rm_page_url());
            exit;
        }

        $profile_context = rm_build_event_profile_context($fetch['events'], $event_code, $event_id);

        if (!empty($profile_context['event_not_found'])) {
            $context['event_not_found'] = true;
            $context['selected_event_code'] = $profile_context['selected_event_code'] ?? $event_code;
        } elseif (($profile_context['selected_event'] ?? null) === null && $event_code === '' && $event_id < 1) {
            wp_safe_redirect(rm_page_url());
            exit;
        } else {
            $context = array_merge($context, $profile_context);
        }
    }

    if ($view_action === 'payment-transactions') {
        $context = array_merge($context, rm_build_payment_transactions_shell_context());
    }

    return $context;
}
