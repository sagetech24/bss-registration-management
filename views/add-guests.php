<?php
$event_present = is_array($event_present ?? null) ? $event_present : null;
$needs_login = !empty($needs_login);
$access_ok = !empty($access_ok);
$can_add = !empty($can_add);
$error_message = (string) ($error_message ?? '');
$success_message = (string) ($success_message ?? '');
$guests = is_array($guests ?? null) ? $guests : [];
$capacity_meta = is_array($capacity_meta ?? null) ? $capacity_meta : [];
$event_addon_full = !empty($event_addon_full);
$guest_schema = is_array($guest_schema ?? null) ? $guest_schema : ['fields' => [], 'enabled' => false];
$form_errors = is_array($form_errors ?? null) ? $form_errors : [];
$guests_input = is_array($guests_input ?? null) ? $guests_input : [];
$confirmation_number = (string) ($confirmation_number ?? '');
$primary_name = (string) ($primary_name ?? '');
$primary_order = (string) ($primary_order ?? '');
$page_url = (string) ($page_url ?? '');
$event_landing_href = (string) ($event_landing_href ?? '');
$event_currency = (string) ($event_currency ?? 'SGD');
$registration_config = is_array($registration_config ?? null) ? $registration_config : [];
$coverage = (string) ($registration_config['coverage'] ?? 'local');
$locale = (string) ($locale ?? 'en');
$ui_strings = is_array($ui_strings ?? null) ? $ui_strings : (function_exists('rm_public_ui_strings') ? rm_public_ui_strings($locale) : []);
$add_guests_success = is_array($add_guests_success ?? null) ? $add_guests_success : null;
$payment_failed = isset($_GET['payment_failed']) && (string) wp_unslash($_GET['payment_failed']) === '1';
$page_closed = isset($_GET['closed']) && (string) wp_unslash($_GET['closed']) === '1';
$guests_cfg = is_array($registration_config['guests'] ?? null) ? $registration_config['guests'] : [];
$guest_label_plural = trim((string) (
    $guest_schema['label_plural']
    ?? $capacity_meta['label_plural']
    ?? $guests_cfg['label_plural']
    ?? 'Guests'
));
if ($guest_label_plural === '') {
    $guest_label_plural = 'Guests';
}
$t = static function (string $key, array $replace = []) use ($ui_strings, $locale): string {
    if (isset($ui_strings[$key])) {
        $text = $ui_strings[$key];
        foreach ($replace as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    return function_exists('rm__') ? rm__($key, $locale, $replace) : $key;
};
?>

<section class="space-y-6">
    <?php if ($error_message !== '' && $event_present === null) : ?>
        <div class="bg-white border border-rose-200 rounded-xl shadow-sm p-6">
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                <?php echo esc_html($error_message); ?>
            </div>
        </div>
    <?php else : ?>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 space-y-4">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600"><?php echo esc_html($t('guest_manage.heading', ['guest' => $guest_label_plural])); ?></p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900"><?php echo esc_html($t('guest_manage.title', ['guest' => $guest_label_plural])); ?></h2>
                <?php if ($event_present !== null) : ?>
                    <p class="mt-2 text-sm text-slate-600"><?php echo esc_html((string) ($event_present['title'] ?? '')); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($page_closed) : ?>
                <div
                    id="rm-add-guests-closed-notice"
                    class="hidden p-4 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 text-sm opacity-100 transition-opacity"
                    style="transition-duration: 4s;"
                >
                    <?php echo esc_html($t('guest_manage.closed_notice')); ?>
                </div>
            <?php endif; ?>

            <?php if ($add_guests_success !== null) : ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-sm">
                    <p class="font-medium"><?php echo esc_html($t('guest_manage.success.added', ['count' => (string) ($add_guests_success['guest_count'] ?? 0)])); ?></p>
                    <p class="mt-1"><?php echo esc_html($t('guest_manage.success.emailed')); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($payment_failed) : ?>
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm">
                    <?php echo esc_html($t('guest_manage.error.payment_failed')); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message !== '' && $add_guests_success === null) : ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-sm">
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($access_ok) : ?>
                <?php if ($error_message !== '') : ?>
                    <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm">
                        <?php echo esc_html($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm space-y-1">
                    <?php if ($primary_name !== '') : ?>
                        <p><span class="font-medium text-slate-700"><?php echo esc_html($t('guest_manage.primary_registrant')); ?>:</span> <?php echo esc_html($primary_name); ?></p>
                    <?php endif; ?>
                    <?php if ($primary_order !== '') : ?>
                        <p><span class="font-medium text-slate-700"><?php echo esc_html($t('register.order_number')); ?>:</span> <?php echo esc_html($primary_order); ?></p>
                    <?php endif; ?>
                    <?php if ($confirmation_number !== '') : ?>
                        <p><span class="font-medium text-slate-700"><?php echo esc_html($t('receipt.confirmation_number')); ?>:</span> <?php echo esc_html($confirmation_number); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($guests !== []) : ?>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800 mb-2"><?php echo esc_html($t('guest_manage.existing_guests')); ?></h3>
                        <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                            <?php foreach ($guests as $guest) : ?>
                                <?php
                                if (!is_array($guest)) {
                                    continue;
                                }
                                $heading = trim((string) ($guest['heading'] ?? ''));
                                if ($heading === '') {
                                    $heading = (string) ($guest['full_name'] ?? '—');
                                }
                                $guest_fields = is_array($guest['fields'] ?? null) ? $guest['fields'] : [];
                                ?>
                                <li class="px-4 py-3 text-sm flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900"><?php echo esc_html($heading); ?></p>
                                        <?php foreach ($guest_fields as $field) : ?>
                                            <?php
                                            if (!is_array($field)) {
                                                continue;
                                            }
                                            $field_label = trim((string) ($field['label'] ?? ''));
                                            $field_value = trim((string) ($field['value'] ?? ''));
                                            if ($field_value === '') {
                                                continue;
                                            }
                                            ?>
                                            <p class="text-xs text-slate-500">
                                                <?php
                                                echo esc_html(
                                                    $field_label !== '' ? $field_label . ': ' . $field_value : $field_value
                                                );
                                                ?>
                                            </p>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (!empty($guest['order_number'])) : ?>
                                        <span class="text-slate-500 shrink-0"><?php echo esc_html((string) $guest['order_number']); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($can_add && !empty($guest_schema['enabled'])) : ?>
                    <?php include __DIR__ . '/partials/add-guests-form.php'; ?>
                <?php elseif (!$can_add) : ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm">
                        <?php
                        echo esc_html(
                            $event_addon_full
                                ? $t('guest_manage.error.event_full', ['guest' => $guest_label_plural])
                                : $t('guest_manage.error.capacity_full', ['guest' => $guest_label_plural])
                        );
                        ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($needs_login) : ?>
                <?php include __DIR__ . '/partials/add-guests-lookup.php'; ?>
            <?php elseif ($error_message !== '') : ?>
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm">
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($event_landing_href !== '' || $access_ok) : ?>
                <div class="border-t border-slate-200 pt-4 flex flex-wrap items-center gap-3">
                    <?php if ($event_landing_href !== '') : ?>
                        <a
                            href="<?php echo esc_url($event_landing_href); ?>"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                        >
                            <?php echo esc_html($t('receipt.back_to_event')); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($access_ok) : ?>
                        <form
                            method="post"
                            action="<?php echo esc_url($page_url); ?>"
                            class="inline"
                            id="rm-add-guests-close-form"
                            onsubmit="return window.rmCloseAddGuestsPage ? window.rmCloseAddGuestsPage(event) : true;"
                        >
                            <?php wp_nonce_field('rm_guest_manage', 'rm_guest_manage_nonce'); ?>
                            <input type="hidden" name="rm_guest_manage_action" value="logout" />
                            <input type="hidden" name="lang" value="<?php echo esc_attr($locale); ?>" />
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-700 px-5 py-2.5 text-sm font-medium text-slate-100 hover:bg-slate-900 transition"
                            >
                                <?php echo esc_html($t('manage.close_page')); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    var cookieName = <?php echo wp_json_encode(defined('RM_GUEST_MANAGE_COOKIE') ? RM_GUEST_MANAGE_COOKIE : 'rm_guest_manage'); ?>;

    function clearClientState() {
        try {
            if (window.sessionStorage) {
                sessionStorage.clear();
            }
        } catch (e) {}

        try {
            if (window.localStorage) {
                var keys = [];
                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (key && (key.indexOf('rm_') === 0 || key.indexOf('rm') === 0 || key.indexOf('guest') !== -1)) {
                        keys.push(key);
                    }
                }
                keys.forEach(function (k) {
                    localStorage.removeItem(k);
                });
            }
        } catch (e) {}

        try {
            var expire = 'Thu, 01 Jan 1970 00:00:00 GMT';
            var paths = ['/', window.location.pathname];
            paths.forEach(function (path) {
                document.cookie = cookieName + '=; expires=' + expire + '; path=' + path + '; SameSite=Lax';
                document.cookie = cookieName + '=; expires=' + expire + '; path=' + path;
            });
        } catch (e) {}
    }

    window.rmCloseAddGuestsPage = function (event) {
        clearClientState();
        return true;
    };

    var params = new URLSearchParams(window.location.search);
    if (params.get('closed') === '1') {
        clearClientState();
        window.close();
        setTimeout(function () {
            var notice = document.getElementById('rm-add-guests-closed-notice');
            if (!notice) {
                return;
            }
            notice.classList.remove('hidden');
            // Allow the browser to paint fully opaque before fading.
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    notice.classList.add('opacity-0');
                });
            });
            setTimeout(function () {
                notice.classList.add('hidden');
                notice.setAttribute('aria-hidden', 'true');
            }, 3000);
        }, 250);
    }
})();
</script>
