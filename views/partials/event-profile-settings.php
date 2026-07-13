<?php
$profile_form_action = rm_event_profile_url($selected_event_code, $selected_event_id, ['tab' => 'settings']);
$uses_v2 = !empty($uses_v2);
$registration_config = is_array($registration_config ?? null) ? $registration_config : rm_registration_config_defaults();
$config_present = is_array($registration_config_present ?? null) ? $registration_config_present : [];

$mode_value = (string) ($registration_config['mode'] ?? 'individual');
$preset_value = (string) ($registration_config['form']['preset'] ?? 'full');
$group_min = (int) ($registration_config['group']['min'] ?? 1);
$group_max = (int) ($registration_config['group']['max'] ?? 1);
$pricing_model = (string) ($registration_config['pricing']['model'] ?? 'flat');
$base_price = $registration_config['pricing']['base_price'] ?? null;
$base_price_value = $base_price !== null && $base_price !== '' ? (string) $base_price : '';
?>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('rmEventProfileSettings', () => ({
        usesV2: <?php echo $uses_v2 ? 'true' : 'false'; ?>,
        enableV2: <?php echo $uses_v2 ? 'true' : 'false'; ?>,
        mode: <?php echo wp_json_encode($mode_value); ?>,
    }));
});
</script>

<div
    class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-2xl"
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

        <div class="space-y-4" x-show="usesV2 || enableV2" x-cloak>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="rm_mode">Mode</label>
                <select id="rm_mode" name="mode" x-model="mode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="individual">Individual</option>
                    <option value="group_flat">Group (flat package)</option>
                    <option value="group_per_head">Group (per-head tiers)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="rm_form_preset">Form preset</label>
                <select id="rm_form_preset" name="form_preset" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="minimal" <?php selected($preset_value, 'minimal'); ?>>Minimal</option>
                    <option value="standard" <?php selected($preset_value, 'standard'); ?>>Standard</option>
                    <option value="full" <?php selected($preset_value, 'full'); ?>>Full</option>
                </select>
                <?php if (!empty($config_present['custom_field_count'])) : ?>
                    <p class="mt-1 text-xs text-slate-500">
                        <?php echo esc_html((string) (int) $config_present['custom_field_count']); ?> custom field(s) preserved on save.
                    </p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-3" x-show="mode !== 'individual'" x-cloak>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="rm_group_min">Group min</label>
                    <input id="rm_group_min" type="number" min="1" name="group_min" value="<?php echo esc_attr((string) $group_min); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="rm_group_max">Group max</label>
                    <input id="rm_group_max" type="number" min="1" name="group_max" value="<?php echo esc_attr((string) $group_max); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="rm_pricing_model">Pricing model</label>
                <select id="rm_pricing_model" name="pricing_model" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="flat" <?php selected($pricing_model, 'flat'); ?>>Flat</option>
                    <option value="package_slots" <?php selected($pricing_model, 'package_slots'); ?>>Package slots</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="rm_base_price">Base price override</label>
                <input id="rm_base_price" type="number" min="0" step="0.01" name="base_price" value="<?php echo esc_attr($base_price_value); ?>" placeholder="Leave blank for event price" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 transition">
                Save settings
            </button>
        </div>
    </form>
</div>
