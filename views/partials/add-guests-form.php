<?php
$guest_schema = is_array($guest_schema ?? null) ? $guest_schema : ['fields' => [], 'enabled' => false];
$guests_input = is_array($guests_input ?? null) ? $guests_input : [];
$capacity_meta = is_array($capacity_meta ?? null) ? $capacity_meta : [];
$page_url = (string) ($page_url ?? '');
$locale = (string) ($locale ?? 'en');
$event_currency = (string) ($event_currency ?? 'SGD');
$coverage = (string) ($coverage ?? 'local');
$ui_strings = is_array($ui_strings ?? null) ? $ui_strings : [];
$phone_country_codes = function_exists('rm_phone_country_codes') ? rm_phone_country_codes() : [];
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
$input_class = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$phone_dial_class = 'rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-2 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$phone_fixed_class = 'inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600';
$phone_local_class = 'w-full rounded-r-lg rounded-l-none border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$slots = $capacity_meta['slots_remaining'] ?? null;
$label_plural = (string) ($guest_schema['label_plural'] ?? 'Guests');
$guest_price = (float) ($guest_schema['price'] ?? 0);
?>

<div
    x-data="rmAddGuestsForm()"
    x-init="init(<?php echo esc_attr(wp_json_encode($guest_schema)); ?>, <?php echo esc_attr(wp_json_encode($guests_input)); ?>, <?php echo esc_attr(wp_json_encode($ui_strings)); ?>, <?php echo esc_attr(wp_json_encode($phone_country_codes)); ?>, <?php echo esc_attr(wp_json_encode(['coverage' => $coverage, 'currency' => $event_currency])); ?>)"
    class="space-y-4"
>
    <p class="text-sm text-slate-600">
        <?php if ($slots !== null) : ?>
            <?php echo esc_html($t('guest_manage.slots_remaining', ['count' => (string) $slots, 'guest' => $label_plural])); ?>
        <?php else : ?>
            <?php echo esc_html($t('guest_manage.add_guests_intro', ['guest' => $label_plural])); ?>
        <?php endif; ?>
    </p>

    <form method="post" action="<?php echo esc_url($page_url); ?>" @submit="beforeSubmit" class="space-y-4">
        <?php wp_nonce_field('rm_guest_manage', 'rm_guest_manage_nonce'); ?>
        <input type="hidden" name="rm_guest_manage_action" value="submit_guests" />
        <input type="hidden" name="lang" value="<?php echo esc_attr($locale); ?>" />
        <input type="hidden" name="guests_json" :value="serializedGuests" />

        <?php include __DIR__ . '/guest-fields.php'; ?>

        <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm" x-show="guests.length > 0 && guestSchema.price > 0" x-cloak>
            <p class="font-medium text-indigo-900">
                <span x-text="guests.length"></span>
                ×
                <span x-text="formatCurrency(parseFloat(guestSchema.price))"></span>
                =
                <span x-text="formatCurrency(guests.length * parseFloat(guestSchema.price || 0))"></span>
            </p>
        </div>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 transition disabled:opacity-60"
            :disabled="isSubmitting || guests.length < 1"
        >
            <span x-show="!isSubmitting" x-text="guestSchema.price > 0 ? <?php echo esc_attr(wp_json_encode($t('guest_manage.submit_pay'))); ?> : <?php echo esc_attr(wp_json_encode($t('guest_manage.submit_free'))); ?>"></span>
            <span x-show="isSubmitting" x-cloak><?php echo esc_html($t('wizard.btn.checking_out')); ?></span>
        </button>
    </form>
</div>

<script>
function rmAddGuestsForm() {
    return {
        guestSchema: { fields: [], enabled: false, min: 1, max: 1, price: 0, label_singular: 'Guest', label_plural: 'Guests' },
        guests: [],
        guestErrors: {},
        serializedGuests: '[]',
        isSubmitting: false,
        strings: {},
        coverage: 'local',
        currency: 'SGD',
        phoneCountryCodes: [],
        t(key, replace = {}) {
            let text = (this.strings && this.strings[key]) ? this.strings[key] : key;
            Object.keys(replace || {}).forEach((name) => {
                text = text.split('{' + name + '}').join(String(replace[name]));
            });
            return text;
        },
        get defaultDial() { return '+65'; },
        init(guestSchema, guestsInput, strings, phoneCountryCodes, opts) {
            this.guestSchema = Object.assign({ fields: [], enabled: false, min: 1, max: 1, price: 0 }, guestSchema || {});
            this.strings = strings || {};
            this.phoneCountryCodes = Array.isArray(phoneCountryCodes) ? phoneCountryCodes : [];
            this.coverage = (opts && opts.coverage) || 'local';
            this.currency = (opts && opts.currency) || 'SGD';
            this.guests = Array.isArray(guestsInput) && guestsInput.length
                ? guestsInput.map((g) => this.hydratePhoneFields(g, this.guestSchema.fields))
                : [];
            if (this.guests.length < 1) this.addGuest();
        },
        emptyGuest() {
            const guest = {};
            (this.guestSchema.fields || []).forEach((field) => {
                if (field.type === 'phone') {
                    guest[field.key] = '';
                    guest[field.key + '__dial'] = this.defaultDial;
                    guest[field.key + '__local'] = '';
                } else {
                    guest[field.key] = field.type === 'checkbox' ? false : '';
                }
            });
            return guest;
        },
        hydratePhoneFields(row, fields) {
            const out = Object.assign({}, row || {});
            (fields || []).forEach((field) => {
                if (field.type !== 'phone') return;
                if (out[field.key + '__local'] !== undefined) return;
                const full = String(out[field.key] || '');
                out[field.key + '__dial'] = this.defaultDial;
                out[field.key + '__local'] = full.replace(/^\+\d+/, '');
            });
            return out;
        },
        syncPhone(row, key) {
            const dial = row[key + '__dial'] || this.defaultDial;
            const local = String(row[key + '__local'] || '').replace(/\D/g, '');
            row[key] = local ? dial + local : '';
        },
        addGuest() {
            if (this.guests.length < this.guestSchema.max) this.guests.push(this.emptyGuest());
        },
        removeGuest() {
            if (this.guests.length > 1) this.guests.pop();
        },
        wideField(field) {
            return ['textarea', 'radio', 'checkbox_group'].includes(field.type);
        },
        fieldPlaceholder(field) {
            const placeholder = String(field.placeholder || '').trim();
            return placeholder !== '' ? placeholder : String(field.label || '');
        },
        inputType(type) {
            if (type === 'email') return 'email';
            if (type === 'number') return 'number';
            if (type === 'date') return 'date';
            if (type === 'phone') return 'tel';
            return 'text';
        },
        formatCurrency(amount) {
            const n = isNaN(amount) ? 0 : amount;
            return this.currency + ' ' + n.toFixed(2);
        },
        serializeRow(row, fields) {
            const out = {};
            (fields || []).forEach((field) => {
                if (field.type === 'phone') {
                    this.syncPhone(row, field.key);
                    out[field.key] = row[field.key] || '';
                } else {
                    out[field.key] = row[field.key];
                }
            });
            return out;
        },
        beforeSubmit(e) {
            this.serializedGuests = JSON.stringify(
                this.guests.map((g) => this.serializeRow(g, this.guestSchema.fields))
            );
            this.isSubmitting = true;
        },
    };
}
</script>
