<?php
$profile_form_action = rm_event_profile_url($selected_event_code, $selected_event_id, ['tab' => 'settings']);
$uses_v2 = !empty($uses_v2);
$registration_config = is_array($registration_config ?? null) ? $registration_config : rm_registration_config_defaults();
$config_present = is_array($registration_config_present ?? null) ? $registration_config_present : [];

$mode_value = (string) ($registration_config['mode'] ?? 'individual');
$coverage_value = (string) ($registration_config['coverage'] ?? RM_EVENT_COVERAGE_LOCAL);
$preset_value = (string) ($registration_config['form']['preset'] ?? 'full');
$group_min = (int) ($registration_config['group']['min'] ?? 1);
$group_max = (int) ($registration_config['group']['max'] ?? 1);
$pricing_model = (string) ($registration_config['pricing']['model'] ?? 'flat');
$currency_value = (string) ($registration_config['pricing']['currency'] ?? 'SGD');
$base_price = $registration_config['pricing']['base_price'] ?? null;
$base_price_value = $base_price !== null && $base_price !== '' ? (string) $base_price : '';
$selected_form_field_keys = rm_form_selected_field_keys($registration_config);
$custom_required_field_keys = rm_form_custom_required_field_keys();
$core_field_definitions = rm_form_core_field_definitions();
$admin_custom_fields = rm_form_present_admin_custom_fields($registration_config);
$custom_field_types = rm_form_allowed_custom_field_types();

$guests_config = isset($registration_config['guests']) && is_array($registration_config['guests']) ? $registration_config['guests'] : [];
$guests_enabled = !empty($guests_config['enabled']);
$guest_label_singular = (string) ($guests_config['label_singular'] ?? 'Guest');
$guest_label_plural = (string) ($guests_config['label_plural'] ?? 'Guests');
$guest_min = (int) ($guests_config['min'] ?? 0);
$guest_max = (int) ($guests_config['max'] ?? 0);
$guest_price = $guests_config['price'] ?? 0;
$guest_price_value = (string) $guest_price;
$admin_guest_fields = rm_form_present_admin_guest_fields($registration_config);
?>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('rmEventProfileSettings', () => ({
        usesV2: <?php echo $uses_v2 ? 'true' : 'false'; ?>,
        enableV2: <?php echo $uses_v2 ? 'true' : 'false'; ?>,
        mode: <?php echo wp_json_encode($mode_value); ?>,
        formPreset: <?php echo wp_json_encode($preset_value); ?>,
        customFields: <?php echo wp_json_encode($admin_custom_fields); ?>,
        showCustomFields: false,
        guestsEnabled: <?php echo $guests_enabled ? 'true' : 'false'; ?>,
        guestFields: <?php echo wp_json_encode($admin_guest_fields); ?>,
        showGuestFields: false,
        addGuestField() {
            this.guestFields.push({
                key: '',
                label: '',
                type: 'text',
                required: true,
                optionsText: '',
            });
            this.showGuestFields = true;
        },
        removeGuestField(index) {
            this.guestFields.splice(index, 1);
        },
        addCustomField() {
            this.customFields.push({
                key: '',
                label: '',
                type: 'text',
                required: true,
                optionsText: '',
            });
            this.showCustomFields = true;
        },
        removeCustomField(index) {
            this.customFields.splice(index, 1);
        },
        needsOptions(type) {
            return type === 'select' || type === 'radio';
        },
    }));
});
</script>

<div
    class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-full"
    x-data="rmEventProfileSettings()"
>
    <div class="p-5 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-slate-900">Event Settings</h2>
        <p class="mt-1 text-sm text-slate-500">Default registration config for the public form (no package param).</p>
    </div>
    <form method="post" action="<?php echo esc_url($profile_form_action); ?>" class="p-5 space-y-4">
        <input type="hidden" name="rm_action" value="save_registration_settings" />
        <?php wp_nonce_field('rm_event_profile', 'rm_event_profile_nonce'); ?>

        <?php if (!$uses_v2) : ?>
            <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3">
                <input type="checkbox" name="enable_v2" value="1" class="mt-1 rounded border-slate-300 text-indigo-700 focus:ring-indigo-600" x-model="enableV2" />
                <span>
                    <span class="block text-sm font-medium text-amber-900">Enable v2 registration</span>
                    <span class="block text-xs text-amber-800 mt-0.5">Writes settings.registration so this event uses the new flow and packages.</span>
                </span>
            </label>
        <?php else : ?>
            <input type="hidden" name="enable_v2" value="1" />
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-4" x-show="usesV2 || enableV2" x-cloak>
            <div class="space-y-4">
                <fieldset class="rounded-lg border border-teal-400 p-4 space-y-4">
                    <legend class="text-sm font-medium text-teal-700 px-1">Main Event Settings</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_mode">RegistrationMode</label>
                            <select id="rm_mode" name="mode" x-model="mode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                                <option value="individual">Individual</option>
                                <option value="group_flat">Group (flat package)</option>
                                <option value="group_per_head">Group (per-head tiers)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_coverage">Event Coverage</label>
                            <select id="rm_coverage" name="coverage" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                                <option value="local" <?php selected($coverage_value, RM_EVENT_COVERAGE_LOCAL); ?>>Local Event (Singapore Only)</option>
                                <option value="international" <?php selected($coverage_value, RM_EVENT_COVERAGE_INTERNATIONAL); ?>>International</option>
                            </select>
                            <!-- <p class="mt-1 text-[11px] text-slate-500">Controls the contact number country-code prepend on the public form.</p> -->
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_pricing_model">Pricing model</label>
                            <select id="rm_pricing_model" name="pricing_model" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                                <option value="flat" <?php selected($pricing_model, 'flat'); ?>>Flat</option>
                                <option value="package_slots" <?php selected($pricing_model, 'package_slots'); ?>>Package slots</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_currency">Currency</label>
                            <select id="rm_currency" name="currency" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                                <?php foreach (rm_registration_currencies() as $currency_code) : ?>
                                    <option value="<?php echo esc_attr($currency_code); ?>" <?php selected($currency_value, $currency_code); ?>>
                                        <?php echo esc_html($currency_code); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500">SGD is the main currency for HitPay payments.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_base_price">Base price override</label>
                            <div class="flex">
                                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600"><?php echo esc_html($currency_value); ?></span>
                                <input id="rm_base_price" type="number" min="0" step="0.01" name="base_price" value="<?php echo esc_attr($base_price_value); ?>" placeholder="Leave blank for event price" class="w-full rounded-r-lg rounded-l-none border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            </div>
                        </div>
                        <div class="col-span-2 grid grid-cols-2 gap-3" x-show="mode !== 'individual'" x-cloak>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_group_min">Group min</label>
                                <input id="rm_group_min" type="number" min="1" name="group_min" value="<?php echo esc_attr((string) $group_min); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_group_max">Group max</label>
                                <input id="rm_group_max" type="number" min="1" name="group_max" value="<?php echo esc_attr((string) $group_max); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            </div>
                        </div>
                    </div>
                </fieldset>
    
                <fieldset class="rounded-lg border border-rose-200 p-4 space-y-4">
                    <legend class="text-sm font-medium text-rose-700 px-1">Accept Guests</legend>
                    <label class="flex items-start gap-3">
                        <input type="hidden" name="guests_enabled" value="" />
                        <input type="checkbox" name="guests_enabled" value="1" class="mt-1 rounded border-slate-300 text-indigo-700 focus:ring-indigo-600" x-model="guestsEnabled" <?php checked($guests_enabled); ?> />
                        <span>
                            <span class="block text-sm font-medium text-slate-800">Enable guest registration</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Let registrants add guests (kids, relatives, etc.) with a separate form and price.</span>
                        </span>
                    </label>
    
                    <div x-show="guestsEnabled" x-cloak class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_guest_label_singular">Label (singular)</label>
                                <input id="rm_guest_label_singular" type="text" name="guest_label_singular" value="<?php echo esc_attr($guest_label_singular); ?>" placeholder="e.g. Child, Relative" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_guest_label_plural">Label (plural)</label>
                                <input id="rm_guest_label_plural" type="text" name="guest_label_plural" value="<?php echo esc_attr($guest_label_plural); ?>" placeholder="e.g. Children, Relatives" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            </div>
                        </div>
    
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_guest_min">Min guests</label>
                                <input id="rm_guest_min" type="number" min="0" name="guest_min" value="<?php echo esc_attr((string) $guest_min); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_guest_max">Max guests</label>
                                <input id="rm_guest_max" type="number" min="0" name="guest_max" value="<?php echo esc_attr((string) $guest_max); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_guest_price">Price per guest</label>
                                <div class="flex">
                                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600"><?php echo esc_html($currency_value); ?></span>
                                    <input id="rm_guest_price" type="number" min="0" step="0.01" name="guest_price" value="<?php echo esc_attr($guest_price_value); ?>" placeholder="0 = free" class="w-full rounded-r-lg rounded-l-none border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                                </div>
                            </div>
                        </div>
    
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <input type="hidden" name="guest_fields_submitted" value="1" />
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-medium text-rose-800">Guest form fields</p>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 border bg-rose-100 rounded-md px-2 py-0.5 text-xs font-medium text-rose-700 hover:bg-rose-50 transition"
                                            @click="showGuestFields = !showGuestFields"
                                        >
                                            <span x-text="showGuestFields ? 'Show less' : 'Show more'"></span>
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="2"
                                                stroke="currentColor"
                                                class="size-3.5 transition-transform duration-300"
                                                :class="showGuestFields ? 'rotate-180' : ''"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Define what information to collect for each guest (e.g. Name, Age).</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-rose-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-rose-50 transition"
                                    @click="addGuestField()"
                                >
                                    Add field
                                </button>
                            </div>

                            <div
                                class="grid transition-[grid-template-rows] duration-300 ease-in-out"
                                :class="showGuestFields ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            >
                                <div class="overflow-hidden">
                                    <div class="mt-4 space-y-3" x-show="guestFields.length > 0">
                                        <template x-for="(gf, gi) in guestFields" :key="gi">
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3">
                                                <input type="hidden" :name="'guest_fields[' + gi + '][key]'" :value="gf.key" />
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-medium text-slate-600 mb-1" :for="'rm_guest_field_label_' + gi">Label</label>
                                                        <input
                                                            type="text"
                                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                            :id="'rm_guest_field_label_' + gi"
                                                            :name="'guest_fields[' + gi + '][label]'"
                                                            x-model="gf.label"
                                                            placeholder="e.g. Given name, Age"
                                                            required
                                                        />
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-slate-600 mb-1" :for="'rm_guest_field_type_' + gi">Type</label>
                                                        <select
                                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                            :id="'rm_guest_field_type_' + gi"
                                                            :name="'guest_fields[' + gi + '][type]'"
                                                            x-model="gf.type"
                                                        >
                                                            <?php foreach ($custom_field_types as $type_key) : ?>
                                                                <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $type_key))); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                                        <input
                                                            type="checkbox"
                                                            class="rounded border-slate-300 text-indigo-700 focus:ring-indigo-600"
                                                            :name="'guest_fields[' + gi + '][required]'"
                                                            value="1"
                                                            x-model="gf.required"
                                                        />
                                                        Required field
                                                    </label>
                                                    <button
                                                        type="button"
                                                        class="text-sm font-medium text-rose-700 hover:text-rose-900"
                                                        @click="removeGuestField(gi)"
                                                    >
                                                        Remove
                                                    </button>
                                                </div>

                                                <div x-show="needsOptions(gf.type)" x-cloak>
                                                    <label class="block text-xs font-medium text-slate-600 mb-1" :for="'rm_guest_field_options_' + gi">Options</label>
                                                    <textarea
                                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                        :id="'rm_guest_field_options_' + gi"
                                                        :name="'guest_fields[' + gi + '][options]'"
                                                        x-model="gf.optionsText"
                                                        rows="3"
                                                        placeholder="Option A&#10;Option B"
                                                    ></textarea>
                                                    <p class="mt-1 text-[11px] text-slate-500">One option per line (or comma-separated).</p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <p class="mt-3 text-xs text-slate-500" x-show="guestFields.length === 0">
                                        No guest fields yet. Use <span class="font-medium text-slate-700">Add field</span> to define guest form fields such as Name, Age, etc.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>

            <fieldset class="rounded-lg border border-amber-300 p-4 space-y-4">
                <legend class="text-sm font-medium text-amber-500 px-1">Form Preset</legend>
                <div class="space-y-2">
                    <?php
                    $form_preset_options = [
                        'minimal' => [
                            'label' => 'Minimal',
                            'fields' => 'Given name, Family name, Email address, Contact number',
                        ],
                        'standard' => [
                            'label' => 'Standard',
                            'fields' => 'Given name, Family name, Email address, Contact number, Title, NRIC (Last 4 digits)',
                        ],
                        'full' => [
                            'label' => 'Full',
                            'fields' => 'Given name, Family name, Email address, Contact number, Title, NRIC (Last 4 digits), Christian name, Certificate name, Address 1, Address 2, Postal code, Church name',
                        ],
                        'custom' => [
                            'label' => 'Custom',
                            'fields' => 'Pick core fields and add your own (e.g. Age, Employer, Civil Status). Required by default: Given name, Family name, Email',
                        ],
                    ];
                    foreach ($form_preset_options as $preset_key => $preset_option) :
                        $preset_input_id = 'rm_form_preset_' . $preset_key;
                        ?>
                        <label for="<?php echo esc_attr($preset_input_id); ?>" class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 cursor-pointer hover:bg-slate-50 has-[:checked]:border-indigo-300 has-[:checked]:bg-indigo-50/50">
                            <input
                                id="<?php echo esc_attr($preset_input_id); ?>"
                                type="radio"
                                name="form_preset"
                                value="<?php echo esc_attr($preset_key); ?>"
                                class="mt-1 border-slate-300 text-indigo-700 focus:ring-indigo-600"
                                x-model="formPreset"
                                <?php checked($preset_value, $preset_key); ?>
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-900"><?php echo esc_html($preset_option['label']); ?></span>
                                <span class="block text-xs text-slate-500 mt-0.5"><?php echo esc_html($preset_option['fields']); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <template x-if="formPreset === 'custom'">
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="custom_fields_submitted" value="1" />

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-medium text-slate-800">Core fields</p>
                            <p class="mt-1 text-xs text-slate-500">Select which built-in fields appear on the public registration form. Given name, Family name, and Email are always required.</p>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <?php foreach ($core_field_definitions as $field_key => $field_def) :
                                    $is_required_field = in_array($field_key, $custom_required_field_keys, true);
                                    $is_checked = in_array($field_key, $selected_form_field_keys, true);
                                    $field_input_id = 'rm_form_field_' . $field_key;
                                    ?>
                                    <label for="<?php echo esc_attr($field_input_id); ?>" class="flex items-start gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 <?php echo $is_required_field ? 'opacity-90' : ''; ?>">
                                        <?php if ($is_required_field) : ?>
                                            <input type="hidden" name="form_fields[]" value="<?php echo esc_attr($field_key); ?>" />
                                            <input
                                                id="<?php echo esc_attr($field_input_id); ?>"
                                                type="checkbox"
                                                value="<?php echo esc_attr($field_key); ?>"
                                                class="mt-0.5 rounded border-slate-300 text-indigo-700 focus:ring-indigo-600"
                                                checked
                                                disabled
                                            />
                                        <?php else : ?>
                                            <input
                                                id="<?php echo esc_attr($field_input_id); ?>"
                                                type="checkbox"
                                                name="form_fields[]"
                                                value="<?php echo esc_attr($field_key); ?>"
                                                class="mt-0.5 rounded border-slate-300 text-indigo-700 focus:ring-indigo-600"
                                                <?php checked($is_checked); ?>
                                            />
                                        <?php endif; ?>
                                        <span class="min-w-0">
                                            <span class="block text-sm text-slate-800"><?php echo esc_html((string) ($field_def['label'] ?? $field_key)); ?></span>
                                            <?php if ($is_required_field) : ?>
                                                <span class="block text-[11px] text-slate-500 mt-0.5">Required</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-medium text-slate-800">Additional custom fields</p>
                                        <button
                                            type="button"
                                            class="inline-flex items-center border border-amber-300 bg-amber-50 gap-1 rounded-md px-2 py-0.5 text-xs font-medium text-amber-700 hover:bg-amber-100 transition"
                                            @click="showCustomFields = !showCustomFields"
                                        >
                                            <span x-text="showCustomFields ? 'Show less' : 'Show more'"></span>
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="2"
                                                stroke="currentColor"
                                                class="size-3.5 transition-transform duration-300"
                                                :class="showCustomFields ? 'rotate-180' : ''"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Add fields such as Age, Employer, or Civil Status. Select/radio options go one per line.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex whitespace-nowrap items-center justify-center rounded-lg border border-slate-300 bg-amber-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-amber-50 transition"
                                    @click="addCustomField()"
                                >
                                    Add field
                                </button>
                            </div>

                            <div
                                class="grid transition-[grid-template-rows] duration-300 ease-in-out"
                                :class="showCustomFields ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            >
                                <div class="overflow-hidden">
                                    <div class="mt-4 space-y-3" x-show="customFields.length > 0">
                                        <template x-for="(field, index) in customFields" :key="index">
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3">
                                                <input type="hidden" :name="'custom_fields[' + index + '][key]'" :value="field.key" />
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-medium text-slate-600 mb-1" :for="'rm_custom_label_' + index">Label</label>
                                                        <input
                                                            type="text"
                                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                            :id="'rm_custom_label_' + index"
                                                            :name="'custom_fields[' + index + '][label]'"
                                                            x-model="field.label"
                                                            placeholder="e.g. Age, Employer, Civil Status"
                                                            required
                                                        />
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-slate-600 mb-1" :for="'rm_custom_type_' + index">Type</label>
                                                        <select
                                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                            :id="'rm_custom_type_' + index"
                                                            :name="'custom_fields[' + index + '][type]'"
                                                            x-model="field.type"
                                                        >
                                                            <?php foreach ($custom_field_types as $type_key) : ?>
                                                                <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $type_key))); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                                        <input
                                                            type="checkbox"
                                                            class="rounded border-slate-300 text-indigo-700 focus:ring-indigo-600"
                                                            :name="'custom_fields[' + index + '][required]'"
                                                            value="1"
                                                            x-model="field.required"
                                                        />
                                                        Required field
                                                    </label>
                                                    <button
                                                        type="button"
                                                        class="text-sm font-medium text-rose-700 hover:text-rose-900"
                                                        @click="removeCustomField(index)"
                                                    >
                                                        Remove
                                                    </button>
                                                </div>

                                                <div x-show="needsOptions(field.type)" x-cloak>
                                                    <label class="block text-xs font-medium text-slate-600 mb-1" :for="'rm_custom_options_' + index">Options</label>
                                                    <textarea
                                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                        :id="'rm_custom_options_' + index"
                                                        :name="'custom_fields[' + index + '][options]'"
                                                        x-model="field.optionsText"
                                                        rows="3"
                                                        placeholder="Single&#10;Married&#10;Widow"
                                                    ></textarea>
                                                    <p class="mt-1 text-[11px] text-slate-500">One option per line (or comma-separated).</p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <p class="mt-3 text-center p-4 border border-slate-200 rounded-lg text-xs text-slate-500" x-show="customFields.length === 0">
                                        No additional fields yet. Use <span class="font-medium text-slate-700">Add field</span> to create Age, Employer, Civil Status, and more.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </fieldset>

        </div>

        <div class="pt-2">
            <button type="submit" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 transition">
                Save settings
            </button>
        </div>
    </form>
</div>
