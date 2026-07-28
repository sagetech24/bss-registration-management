<?php

const RM_LOCALE_EN = 'en';
const RM_LOCALE_ZH_CN = 'zh_CN';

/**
 * @return list<string>
 */
function rm_supported_locales(): array
{
    return [RM_LOCALE_EN, RM_LOCALE_ZH_CN];
}

/**
 * @return array<string, string> locale => admin label
 */
function rm_locale_labels(): array
{
    return [
        RM_LOCALE_EN    => 'English',
        RM_LOCALE_ZH_CN => '简体中文 (Simplified Chinese)',
    ];
}

/**
 * Normalize a raw lang token to a supported locale, or empty when unknown.
 */
function rm_normalize_locale(string $raw): string
{
    $raw = strtolower(str_replace('-', '_', trim($raw)));

    return match ($raw) {
        'en', 'en_us', 'en_sg' => RM_LOCALE_EN,
        'zh', 'zh_cn', 'zh_sg', 'cn', 'chs' => RM_LOCALE_ZH_CN,
        default => '',
    };
}

/**
 * Resolve public UI locale: ?lang= override → event default → en.
 *
 * @param array<string, mixed> $event
 */
function rm_resolve_locale(array $event, ?string $request_lang = null): string
{
    if ($request_lang === null && isset($_GET['lang'])) {
        $request_lang = (string) wp_unslash($_GET['lang']);
    }

    if (is_string($request_lang) && $request_lang !== '') {
        $from_request = rm_normalize_locale($request_lang);
        if ($from_request !== '') {
            return $from_request;
        }
    }

    $config = function_exists('rm_parse_registration_config')
        ? rm_parse_registration_config($event)
        : [];
    $from_event = rm_normalize_locale((string) ($config['locale'] ?? ''));
    if ($from_event !== '') {
        return $from_event;
    }

    return RM_LOCALE_EN;
}

/**
 * HTML lang attribute for a locale.
 */
function rm_locale_html_lang(string $locale): string
{
    return $locale === RM_LOCALE_ZH_CN ? 'zh-CN' : 'en';
}

/**
 * @return array<string, string>
 */
function rm_load_lang_catalog(string $locale): array
{
    static $cache = [];

    $locale = rm_normalize_locale($locale) !== '' ? rm_normalize_locale($locale) : RM_LOCALE_EN;
    if (isset($cache[$locale])) {
        return $cache[$locale];
    }

    $path = dirname(__DIR__) . '/lang/' . $locale . '.php';
    $strings = [];
    if (is_readable($path)) {
        $loaded = include $path;
        if (is_array($loaded)) {
            $strings = $loaded;
        }
    }

    if ($locale !== RM_LOCALE_EN) {
        $en_path = dirname(__DIR__) . '/lang/' . RM_LOCALE_EN . '.php';
        $en = [];
        if (is_readable($en_path)) {
            $loaded_en = include $en_path;
            if (is_array($loaded_en)) {
                $en = $loaded_en;
            }
        }
        $strings = array_merge($en, $strings);
    }

    $cache[$locale] = $strings;

    return $strings;
}

/**
 * Translate a catalog key. Missing keys fall back to English, then the key itself.
 *
 * @param array<string, string|int|float> $replace Placeholder map, e.g. ['field' => 'Email']
 */
function rm__(string $key, string $locale = RM_LOCALE_EN, array $replace = []): string
{
    $locale = rm_normalize_locale($locale) !== '' ? rm_normalize_locale($locale) : RM_LOCALE_EN;
    $catalog = rm_load_lang_catalog($locale);
    $text = $catalog[$key] ?? null;

    if (!is_string($text) || $text === '') {
        if ($locale !== RM_LOCALE_EN) {
            $en = rm_load_lang_catalog(RM_LOCALE_EN);
            $text = $en[$key] ?? null;
            if ((!is_string($text) || $text === '') && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[rm_i18n] Missing translation key: ' . $key);
            }
        }
        if (!is_string($text) || $text === '') {
            $text = $key;
        }
    }

    foreach ($replace as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }

    return $text;
}

/**
 * Public chrome strings for Alpine / PHP views for a locale.
 *
 * @return array<string, string>
 */
function rm_public_ui_strings(string $locale): array
{
    $keys = [
        'wizard.step.registrant',
        'wizard.step.leader',
        'wizard.step.members',
        'wizard.step.guests',
        'wizard.step.review',
        'wizard.heading.registration_info',
        'wizard.heading.leader_info',
        'wizard.heading.review',
        'wizard.heading.member',
        'wizard.btn.next',
        'wizard.btn.back',
        'wizard.btn.checkout',
        'wizard.btn.checking_out',
        'wizard.btn.add_member',
        'wizard.btn.remove_last_member',
        'wizard.btn.add_guest',
        'wizard.note.prefix',
        'wizard.guests.intro',
        'wizard.guests.slots_remaining',
        'wizard.guests.capacity_full',
        'wizard.total_estimated',
        'wizard.final_amount_note',
        'wizard.summary.additional_member',
        'wizard.summary.guest_intro',
        'wizard.summary.guest_intro_optional',
        'wizard.this_field',
        'wizard.free',
        'wizard.role.registrant',
        'wizard.role.leader',
        'wizard.role.member',
        'wizard.role.member_n',
        'wizard.alert.exact_registrants',
        'wizard.alert.min_guests',
        'validation.required',
        'validation.email',
        'validation.date',
        'validation.contact',
        'validation.contact_sg',
        'validation.nric',
        'validation.postcode',
        'validation.unknown_field',
        'validation.invalid_selection',
        'validation.must_be_number',
        'validation.min_value',
        'validation.max_value',
        'form.please_select',
        'register.order_number',
        'register.privacy',
        'register.individual_instead',
        'receipt.confirmed',
        'receipt.emailed',
        'receipt.confirmation_number',
        'receipt.back_to_event',
        'receipt.register_another',
        'manage.heading',
        'manage.add_member',
        'manage.member_details',
        'manage.no_members',
        'manage.package',
        'manage.confirmation',
        'manage.members',
        'manage.role_member',
        'manage.role_leader',
        'manage.new_members_note',
        'manage.add_member_payment_note',
        'manage.verify_title',
        'manage.verify_desc',
        'manage.confirmation_number_label',
        'manage.confirmation_placeholder',
        'manage.primary_email_label',
        'manage.email_placeholder',
        'manage.continue',
        'manage.close_page',
        'manage.members_of',
        'manage.roster_complete',
        'manage.error.no_event',
        'manage.error.event_not_found',
        'manage.error.session_expired',
        'manage.error.login_not_found',
        'manage.error.wrong_event',
        'manage.error.not_paid',
        'manage.error.not_group_flat',
        'manage.error.require_all_at_checkout',
        'manage.error.roster_complete',
        'manage.error.invalid_registration',
        'manage.error.rate_limited',
        'manage.error.invalid_member_data',
        'manage.error.registration_not_found',
        'manage.success.loaded',
        'manage.success.member_added_more',
        'manage.success.member_added_done',
        'email.subject',
        'email.title',
        'email.banner',
        'email.hello',
        'email.thank_you',
        'email.review',
        'email.section.event',
        'email.section.payment',
        'email.section.registrant',
        'email.section.members',
        'email.section.guests',
        'email.label.date_time',
        'email.label.venue',
        'email.label.confirmation',
        'email.label.package',
        'email.label.amount_paid',
        'email.label.payment_method',
        'email.label.name',
        'email.label.email',
        'email.label.contact',
        'email.label.role',
        'email.label.registration_number',
        'email.label.church',
        'email.role.primary',
        'email.role.member',
        'email.role.guest',
        'email.complete_roster',
        'email.add_remaining',
        'email.slots_remaining',
        'email.footer.org',
        'email.footer.reply',
        'email.yes',
        'email.no',
        'fields.nric.label',
        'fields.nric.placeholder',
        'fields.title.label',
        'fields.title.placeholder',
        'fields.christian_name.label',
        'fields.christian_name.placeholder',
        'fields.given_name.label',
        'fields.given_name.placeholder',
        'fields.family_name.label',
        'fields.family_name.placeholder',
        'fields.certificate_name.label',
        'fields.certificate_name.placeholder',
        'fields.email.label',
        'fields.email.placeholder',
        'fields.contact.label',
        'fields.contact.placeholder',
        'fields.address1.label',
        'fields.address1.placeholder',
        'fields.address2.label',
        'fields.address2.placeholder',
        'fields.postcode.label',
        'fields.postcode.placeholder',
        'fields.church_name.label',
        'fields.church_name.placeholder',
        'title.Mr',
        'title.Mrs',
        'title.Ms',
        'title.Miss',
        'title.Dr',
        'title.Rev',
        'title.Ps',
    ];

    $out = [];
    foreach ($keys as $key) {
        $out[$key] = rm__($key, $locale);
    }

    return $out;
}

/**
 * Append or replace lang query arg on a URL.
 */
function rm_url_with_lang(string $url, string $locale): string
{
    $locale = rm_normalize_locale($locale) !== '' ? rm_normalize_locale($locale) : RM_LOCALE_EN;

    return add_query_arg('lang', $locale, $url);
}
