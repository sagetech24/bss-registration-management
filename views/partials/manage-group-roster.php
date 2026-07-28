<?php
$members = is_array($members ?? null) ? $members : [];
$group_meta = is_array($group_meta ?? null) ? $group_meta : [];
$package_label = (string) ($package_label ?? '');
$confirmation_number = (string) ($confirmation_number ?? '');
$can_add = !empty($can_add);
$member_count = (int) ($group_meta['member_count'] ?? count($members));
$member_max = (int) ($group_meta['member_max'] ?? $member_count);
$slots_remaining = (int) ($group_meta['slots_remaining'] ?? max(0, $member_max - $member_count));
$form_schema = is_array($form_schema ?? null) ? $form_schema : ['fields' => []];
$form_errors = is_array($form_errors ?? null) ? $form_errors : [];
$member_input = is_array($member_input ?? null) ? $member_input : [];
$registration_config = is_array($registration_config ?? null) ? $registration_config : [];
$page_url = (string) ($page_url ?? '');
$manage_token = (string) ($manage_token ?? '');
$locale = (string) ($locale ?? 'en');
$ui_strings = is_array($ui_strings ?? null) ? $ui_strings : (function_exists('rm_public_ui_strings') ? rm_public_ui_strings($locale) : []);
$t = static function (string $key, array $replace = []) use ($ui_strings, $locale): string {
    if (isset($ui_strings[$key])) {
        $text = $ui_strings[$key];
    } else {
        $text = function_exists('rm__') ? rm__($key, $locale) : $key;
    }

    foreach ($replace as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }

    return $text;
};
$event_coverage = rm_registration_coverage($registration_config);
$phone_country_codes = rm_phone_country_codes();
$schema_json = wp_json_encode($form_schema);
$member_json = wp_json_encode($member_input !== [] ? $member_input : new stdClass());
$coverage_json = wp_json_encode($event_coverage);
$phone_codes_json = wp_json_encode($phone_country_codes);
$errors_json = wp_json_encode($form_errors);
$strings_json = wp_json_encode($ui_strings);
$input_class = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$phone_local_class = 'w-full rounded-r-lg rounded-l-none border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$phone_dial_class = 'min-w-[6rem] rounded-l-lg rounded-r-none border border-r-0 border-slate-300 bg-slate-50 px-2 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none';
$phone_fixed_class = 'inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600';
?>

<div class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 space-y-1">
    <?php if ($package_label !== '') : ?>
        <p class="text-sm text-slate-700">
            <span class="font-medium text-slate-900"><?php echo esc_html($t('manage.package')); ?>:</span>
            <?php echo esc_html($package_label); ?>
        </p>
    <?php endif; ?>
    <?php if ($confirmation_number !== '') : ?>
        <p class="text-sm text-slate-700">
            <span class="font-medium text-slate-900"><?php echo esc_html($t('manage.confirmation')); ?>:</span>
            <?php echo esc_html($confirmation_number); ?>
        </p>
    <?php endif; ?>
    <p class="text-sm text-slate-700">
        <span class="font-medium text-slate-900"><?php echo esc_html($t('manage.members')); ?>:</span>
        <?php echo esc_html($t('manage.members_of', ['count' => $member_count, 'max' => $member_max])); ?>
        <?php if ($slots_remaining > 0) : ?>
            <span class="text-indigo-700">(<?php echo esc_html($t('email.slots_remaining', ['count' => $slots_remaining])); ?>)</span>
        <?php endif; ?>
    </p>
</div>

<div class="overflow-x-auto rounded-lg border border-slate-200">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium"><?php echo esc_html($t('email.label.name')); ?></th>
                <th class="px-4 py-3 font-medium"><?php echo esc_html($t('email.label.email')); ?></th>
                <th class="px-4 py-3 font-medium"><?php echo esc_html($t('email.label.role')); ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if ($members === []) : ?>
                <tr>
                    <td colspan="3" class="px-4 py-4 text-slate-500"><?php echo esc_html($t('manage.no_members')); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($members as $member) :
                    if (!is_array($member)) {
                        continue;
                    }
                    ?>
                    <tr>
                        <td class="px-4 py-3 text-slate-900"><?php echo esc_html((string) ($member['full_name'] ?? '')); ?></td>
                        <td class="px-4 py-3 text-slate-700"><?php echo esc_html((string) ($member['email'] ?? '')); ?></td>
                        <td class="px-4 py-3 text-slate-700"><?php echo esc_html((string) ($member['role_label'] ?? $t('manage.role_member'))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (!$can_add) : ?>
    <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-sm">
        <?php echo esc_html($t('manage.roster_complete')); ?>
    </div>
<?php else : ?>
    <div
        class="space-y-4"
        x-data="rmManageGroupAdd()"
        x-init="init(<?php echo esc_attr($schema_json); ?>, <?php echo esc_attr($member_json); ?>, <?php echo esc_attr($coverage_json); ?>, <?php echo esc_attr($phone_codes_json); ?>, <?php echo esc_attr($errors_json); ?>, <?php echo esc_attr($strings_json); ?>)"
    >
        <div class="border-t border-slate-200 pt-4">
            <h3 class="text-lg font-semibold text-slate-900"><?php echo esc_html($t('manage.add_member')); ?></h3>
            <p class="mt-1 text-sm text-slate-600">
                <?php echo esc_html($t('manage.add_member_payment_note')); ?>
            </p>
        </div>

        <form method="post" action="<?php echo esc_url($page_url); ?>" @submit="prepareSubmit($event)" class="space-y-4">
            <?php wp_nonce_field('rm_group_manage', 'rm_group_manage_nonce'); ?>
            <input type="hidden" name="rm_group_manage_action" value="add_member" />
            <input type="hidden" name="lang" value="<?php echo esc_attr($locale); ?>" />
            <?php if ($manage_token !== '') : ?>
                <input type="hidden" name="t" value="<?php echo esc_attr($manage_token); ?>" />
            <?php endif; ?>
            <input type="hidden" name="member_json" :value="serializedMember" />

            <fieldset class="rounded-lg border border-slate-200 p-4 space-y-4">
                <legend class="text-sm font-medium text-slate-700 px-1"><?php echo esc_html($t('manage.member_details')); ?></legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <template x-for="field in schema.fields" :key="'add-' + field.key">
                        <div :class="wideField(field) ? 'sm:col-span-2' : ''">
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                <span x-text="field.label"></span>
                                <span x-show="field.required" class="text-rose-500">*</span>
                            </label>
                            <template x-if="field.type === 'textarea'">
                                <textarea
                                    class="<?php echo esc_attr($input_class); ?>"
                                    :class="fieldErrors[field.key] ? 'border-rose-400' : ''"
                                    rows="3"
                                    x-model="member[field.key]"
                                    :placeholder="fieldPlaceholder(field)"
                                    @input="delete fieldErrors[field.key]"
                                ></textarea>
                            </template>
                            <template x-if="field.type === 'select'">
                                <select
                                    class="<?php echo esc_attr($input_class); ?>"
                                    :class="fieldErrors[field.key] ? 'border-rose-400' : ''"
                                    x-model="member[field.key]"
                                    @change="delete fieldErrors[field.key]"
                                >
                                    <option value="" x-text="fieldPlaceholder(field)"></option>
                                    <template x-for="opt in (field.options || [])" :key="opt.value || opt">
                                        <option :value="opt.value || opt" x-text="opt.label || opt"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="field.type === 'phone'">
                                <div class="flex">
                                    <template x-if="coverage === 'local'">
                                        <span class="<?php echo esc_attr($phone_fixed_class); ?>">+65</span>
                                    </template>
                                    <template x-if="coverage !== 'local'">
                                        <select class="<?php echo esc_attr($phone_dial_class); ?>" x-model="member[field.key + '__dial']" @change="syncPhone(field.key); delete fieldErrors[field.key]">
                                            <template x-for="country in phoneCountryCodes" :key="country.dial + country.label">
                                                <option :value="country.dial" x-text="country.dial + ' ' + country.label"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <input
                                        type="tel"
                                        class="<?php echo esc_attr($phone_local_class); ?>"
                                        :class="fieldErrors[field.key] ? 'border-rose-400' : ''"
                                        x-model="member[field.key + '__local']"
                                        @input="syncPhone(field.key); delete fieldErrors[field.key]"
                                        :placeholder="t('fields.contact.placeholder')"
                                    />
                                </div>
                            </template>
                            <template x-if="!['textarea','select','checkbox','checkbox_group','radio','phone'].includes(field.type)">
                                <input
                                    class="<?php echo esc_attr($input_class); ?>"
                                    :class="fieldErrors[field.key] ? 'border-rose-400' : ''"
                                    :type="inputType(field.type)"
                                    x-model="member[field.key]"
                                    :placeholder="field.type === 'date' ? '' : fieldPlaceholder(field)"
                                    @input="delete fieldErrors[field.key]"
                                />
                            </template>
                            <p x-show="fieldErrors[field.key]" class="mt-1 text-sm text-rose-600" x-text="fieldErrors[field.key]" x-cloak></p>
                        </div>
                    </template>
                </div>
            </fieldset>

            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 transition"
            >
                <?php echo esc_html($t('manage.add_member')); ?>
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('rmManageGroupAdd', () => ({
            schema: { fields: [] },
            member: {},
            fieldErrors: {},
            serializedMember: '{}',
            coverage: 'local',
            phoneCountryCodes: [],
            strings: {},
            get defaultDial() {
                return '+65';
            },
            t(key, replace = {}) {
                let text = this.strings[key] || key;
                Object.keys(replace).forEach((name) => {
                    text = text.split('{' + name + '}').join(String(replace[name]));
                });
                return text;
            },
            init(schema, memberInput, coverage, phoneCountryCodes, errors, strings) {
                this.schema = schema || { fields: [] };
                this.coverage = coverage || 'local';
                this.phoneCountryCodes = Array.isArray(phoneCountryCodes) ? phoneCountryCodes : [];
                this.strings = strings && typeof strings === 'object' ? strings : {};
                this.fieldErrors = errors && typeof errors === 'object' ? Object.assign({}, errors) : {};
                const seed = memberInput && typeof memberInput === 'object' && !Array.isArray(memberInput)
                    ? memberInput
                    : {};
                this.member = this.hydratePhoneFields(Object.keys(seed).length ? seed : this.emptyMember());
            },
            emptyMember() {
                const member = {};
                (this.schema.fields || []).forEach((field) => {
                    if (field.type === 'phone') {
                        member[field.key] = '';
                        member[field.key + '__dial'] = this.defaultDial;
                        member[field.key + '__local'] = '';
                    } else {
                        member[field.key] = field.type === 'checkbox' ? false : (field.type === 'checkbox_group' ? [] : '');
                    }
                });
                return member;
            },
            hydratePhoneFields(row) {
                const data = Object.assign({}, row || {});
                (this.schema.fields || []).forEach((field) => {
                    if (field.type !== 'phone') return;
                    const parts = this.splitPhone(data[field.key] || '');
                    data[field.key + '__dial'] = parts.dial;
                    data[field.key + '__local'] = parts.local;
                    data[field.key] = this.composePhone(parts.dial, parts.local);
                });
                return data;
            },
            splitPhone(value) {
                const raw = String(value || '').trim();
                if (raw === '') {
                    return { dial: this.defaultDial, local: '' };
                }
                const match = raw.match(/^(\+\d+)\s*(.*)$/);
                if (match) {
                    return { dial: match[1], local: (match[2] || '').replace(/\s+/g, '') };
                }
                return { dial: this.defaultDial, local: raw.replace(/\s+/g, '') };
            },
            composePhone(dial, local) {
                const d = String(dial || this.defaultDial);
                const l = String(local || '').replace(/\s+/g, '');
                return l === '' ? '' : (d + ' ' + l).trim();
            },
            syncPhone(key) {
                if (this.coverage === 'local') {
                    this.member[key + '__dial'] = '+65';
                }
                this.member[key] = this.composePhone(this.member[key + '__dial'], this.member[key + '__local']);
            },
            wideField(field) {
                return field && (field.type === 'textarea' || field.type === 'checkbox_group' || field.type === 'radio' || field.width === 'full');
            },
            inputType(type) {
                if (type === 'email') return 'email';
                if (type === 'number') return 'number';
                if (type === 'date') return 'date';
                return 'text';
            },
            fieldPlaceholder(field) {
                return (field && field.placeholder) ? field.placeholder : (field && field.label ? field.label : '');
            },
            prepareSubmit(event) {
                (this.schema.fields || []).forEach((field) => {
                    if (field.type === 'phone') {
                        this.syncPhone(field.key);
                    }
                });
                const payload = {};
                (this.schema.fields || []).forEach((field) => {
                    if (field.type === 'phone') {
                        payload[field.key] = this.member[field.key] || '';
                    } else {
                        payload[field.key] = this.member[field.key];
                    }
                });
                this.serializedMember = JSON.stringify(payload);
            },
        }));
    });
    </script>
<?php endif; ?>
