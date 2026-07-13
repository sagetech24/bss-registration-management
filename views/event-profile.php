<?php
$selected_event = is_array($selected_event ?? null) ? $selected_event : null;
$selected_event_code = (string) ($selected_event_code ?? '');
$selected_event_id = (int) ($selected_event_id ?? 0);
$uses_v2 = !empty($uses_v2);
$registration_config = is_array($registration_config ?? null) ? $registration_config : rm_registration_config_defaults();
$config_present = is_array($registration_config_present ?? null) ? $registration_config_present : [];
$promotions = is_array($promotions ?? null) ? $promotions : [];
$summary = is_array($summary ?? null) ? $summary : [
    'total'         => 0,
    'paid_count'    => 0,
    'pending_count' => 0,
    'total_revenue' => 0.0,
];
$active_package_count = (int) ($active_package_count ?? 0);
$event_card = is_array($event_card ?? null) ? $event_card : [];
$profile_flash = is_array($profile_flash ?? null) ? $profile_flash : null;
$registrants_href = (string) ($registrants_href ?? $page_url);
$registration_href = (string) ($registration_href ?? '');
$event_price_display = (string) ($event_price_display ?? 'FREE');
$profile_error = (string) ($profile_error ?? '');
$profile_form_action = rm_event_profile_url($selected_event_code, $selected_event_id);

$event_title = $selected_event !== null
    ? (string) ($selected_event['title'] ?? 'Untitled event')
    : 'Event';
$code_label = $selected_event_code !== '' ? rtrim($selected_event_code, '_') : '';
$thumb_url = (string) ($event_card['thumb_url'] ?? '');
$date_block = (string) ($event_card['date_block'] ?? '');
$venue_show = (string) ($event_card['venue_show'] ?? '');

$mode_value = (string) ($registration_config['mode'] ?? 'individual');
$preset_value = (string) ($registration_config['form']['preset'] ?? 'full');
$group_min = (int) ($registration_config['group']['min'] ?? 1);
$group_max = (int) ($registration_config['group']['max'] ?? 1);
$pricing_model = (string) ($registration_config['pricing']['model'] ?? 'flat');
$base_price = $registration_config['pricing']['base_price'] ?? null;
$base_price_value = $base_price !== null && $base_price !== '' ? (string) $base_price : '';

$promotions_json = [];
foreach ($promotions as $promo) {
    if (!is_array($promo)) {
        continue;
    }
    $promotions_json[] = [
        'id'                  => (int) ($promo['id'] ?? 0),
        'title'               => (string) ($promo['title'] ?? ''),
        'slug'                => (string) ($promo['slug'] ?? ''),
        'description'         => (string) ($promo['description'] ?? ''),
        'registration_mode'   => (string) ($promo['registration_mode'] ?? 'group_flat'),
        'member_min'          => (int) ($promo['member_min'] ?? 1),
        'member_max'          => (int) ($promo['member_max'] ?? 1),
        'require_all_members' => !empty($promo['require_all_members']),
        'package_price'       => (float) ($promo['package_price'] ?? 0),
        'is_active'           => !empty($promo['is_active']),
        'sort_order'          => (int) ($promo['sort_order'] ?? 0),
        'valid_from_local'    => (string) ($promo['valid_from_local'] ?? ''),
        'valid_until_local'   => (string) ($promo['valid_until_local'] ?? ''),
        'package_href'        => (string) ($promo['package_href'] ?? ''),
    ];
}
?>

<?php if ($selected_event === null) : ?>
    <div class="p-6 bg-white border border-slate-200 rounded-xl text-slate-600">
        <?php echo esc_html($profile_error !== '' ? $profile_error : 'Event could not be loaded.'); ?>
        <div class="mt-4">
            <a href="<?php echo esc_url($page_url); ?>" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">Back to events</a>
        </div>
    </div>
<?php else : ?>

<?php
$event_profile_alpine_config = [
    'usesV2'     => (bool) $uses_v2,
    'mode'       => $mode_value,
    'promotions' => $promotions_json,
];
?>

<script>
document.addEventListener('alpine:init', () => {
    const profileConfig = <?php echo wp_json_encode($event_profile_alpine_config); ?>;

    Alpine.data('rmEventProfile', () => ({
        usesV2: !!profileConfig.usesV2,
        enableV2: !!profileConfig.usesV2,
        mode: profileConfig.mode || 'individual',
        promotions: Array.isArray(profileConfig.promotions) ? profileConfig.promotions : [],
        modalOpen: false,
        copiedPromoId: 0,
        _copiedTimer: null,
        form: {
            id: 0,
            title: '',
            slug: '',
            description: '',
            registration_mode: 'group_flat',
            member_min: 2,
            member_max: 2,
            require_all_members: true,
            package_price: 0,
            is_active: true,
            sort_order: 0,
            valid_from_local: '',
            valid_until_local: '',
        },
        blankForm() {
            return {
                id: 0,
                title: '',
                slug: '',
                description: '',
                registration_mode: 'group_flat',
                member_min: 2,
                member_max: 2,
                require_all_members: true,
                package_price: 0,
                is_active: true,
                sort_order: 0,
                valid_from_local: '',
                valid_until_local: '',
            };
        },
        openCreate() {
            this.form = this.blankForm();
            this.modalOpen = true;
        },
        openEdit(id) {
            const promo = this.promotions.find((row) => Number(row.id) === Number(id));
            if (!promo) {
                return;
            }
            this.form = {
                id: promo.id,
                title: promo.title || '',
                slug: promo.slug || '',
                description: promo.description || '',
                registration_mode: promo.registration_mode || 'group_flat',
                member_min: promo.member_min || 1,
                member_max: promo.member_max || 1,
                require_all_members: !!promo.require_all_members,
                package_price: promo.package_price || 0,
                is_active: !!promo.is_active,
                sort_order: promo.sort_order || 0,
                valid_from_local: promo.valid_from_local || '',
                valid_until_local: promo.valid_until_local || '',
            };
            this.modalOpen = true;
        },
        closeModal() {
            this.modalOpen = false;
        },
        async copyPromoUrl(id) {
            const promo = this.promotions.find((row) => Number(row.id) === Number(id));
            const url = promo && promo.package_href ? String(promo.package_href) : '';
            if (url === '') {
                return;
            }

            let copied = false;
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(url);
                    copied = true;
                } catch (e) {
                    copied = false;
                }
            }

            if (!copied) {
                const input = document.createElement('textarea');
                input.value = url;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.top = '0';
                input.style.left = '0';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.focus();
                input.select();
                try {
                    copied = document.execCommand('copy');
                } catch (e) {
                    copied = false;
                }
                document.body.removeChild(input);
            }

            if (!copied) {
                window.prompt('Copy package URL', url);
                return;
            }

            this.copiedPromoId = Number(id);
            window.clearTimeout(this._copiedTimer);
            this._copiedTimer = window.setTimeout(() => {
                this.copiedPromoId = 0;
            }, 2000);
        },
    }));
});
</script>

<section class="space-y-6" x-data="rmEventProfile()">
    <?php if (is_array($profile_flash) && ($profile_flash['message'] ?? '') !== '') : ?>
        <?php $flash_ok = ($profile_flash['type'] ?? '') === 'success'; ?>
        <div class="rounded-lg border px-4 py-3 text-sm <?php echo $flash_ok ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'; ?>">
            <?php echo esc_html((string) $profile_flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="flex flex-col md:flex-row">
            <?php if ($thumb_url !== '') : ?>
                <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($event_title); ?>" class="md:w-96 md:h-96 h-44 md:h-auto object-cover shrink-0" />
            <?php else : ?>
                <div class="md:w-56 h-44 bg-slate-100 flex items-center justify-center text-slate-400 text-sm shrink-0">No image</div>
            <?php endif; ?>

            <div class="flex-1 p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Event dashboard</p>
                        <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?php echo $event_title; ?></h1>
                        <?php if ($code_label !== '') : ?>
                            <p class="mt-1 text-sm text-slate-500">Code: <?php echo esc_html($code_label); ?></p>
                        <?php endif; ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?php echo $uses_v2 ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600'; ?>">
                                <?php echo $uses_v2 ? 'v2 registration' : 'Legacy registration'; ?>
                            </span>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                <?php echo esc_html($event_price_display); ?>
                            </span>
                        </div>
                        <?php if ($date_block !== '') : ?>
                            <p class="mt-3 text-sm text-slate-700">
                                <?php echo $date_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($venue_show !== '') : ?>
                            <p class="mt-1 text-sm text-slate-500"><?php echo esc_html($venue_show); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-wrap gap-2 shrink-0">
                        <a href="<?php echo esc_url($page_url); ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Events
                        </a>
                        <a href="<?php echo esc_url($registrants_href); ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Registrants
                        </a>
                        <?php if ($registration_href !== '') : ?>
                            <a href="<?php echo esc_url($registration_href); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg bg-indigo-700 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-800">
                                Public form
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <?php
        $stat_tiles = [
            ['label' => 'Total', 'value' => (string) (int) ($summary['total'] ?? 0)],
            ['label' => 'Paid', 'value' => (string) (int) ($summary['paid_count'] ?? 0)],
            ['label' => 'Pending', 'value' => (string) (int) ($summary['pending_count'] ?? 0)],
            ['label' => 'Revenue', 'value' => '$' . number_format_i18n((float) ($summary['total_revenue'] ?? 0), 2)],
            ['label' => 'Active packages', 'value' => (string) $active_package_count],
        ];
        foreach ($stat_tiles as $tile) :
            ?>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400"><?php echo esc_html($tile['label']); ?></p>
                <p class="mt-2 text-xl font-semibold text-slate-900"><?php echo esc_html($tile['value']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
        <div class="xl:col-span-5 bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="p-5 border-b border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900">Registration settings</h2>
                <p class="mt-1 text-sm text-slate-500">Default config for the public form (no package param).</p>
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

        <div class="xl:col-span-7 bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Registration packages</h2>
                    <p class="mt-1 text-sm text-slate-500">Named promotions with their own price and member rules.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed"
                    <?php echo $uses_v2 ? '' : 'disabled'; ?>
                    @click="openCreate()"
                >
                    Add package
                </button>
            </div>

            <?php if (!$uses_v2) : ?>
                <div class="p-5 text-sm text-slate-600">
                    Enable v2 registration in settings before creating packages.
                </div>
            <?php elseif ($promotions === []) : ?>
                <div class="p-5 text-center">
                    <p class="mb-2 text-2xl font-semibold text-slate-300">No packages yet.</p> 
                    <p class="mb-2 text-sm italic text-slate-500">Add a Couple, Company, or other package to generate alternate registration URLs.</p>
                </div>
            <?php else : ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">Package</th>
                                <th class="px-4 py-3 font-medium">Mode</th>
                                <th class="px-4 py-3 font-medium">Members</th>
                                <th class="px-4 py-3 font-medium">Price</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($promotions as $promo) : ?>
                                <?php if (!is_array($promo)) {
                                    continue;
                                } ?>
                                <tr class="align-top">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900"><?php echo esc_html((string) ($promo['title'] ?? '')); ?></div>
                                        <div class="text-xs text-slate-500 mt-0.5">slug: <?php echo esc_html((string) ($promo['slug'] ?? '')); ?></div>
                                        <?php if (!empty($promo['package_href'])) : ?>
                                            <button
                                                type="button"
                                                class="mt-1 text-xs text-indigo-700 hover:text-indigo-900"
                                                @click="copyPromoUrl(<?php echo (int) ($promo['id'] ?? 0); ?>)"
                                                x-text="copiedPromoId === <?php echo (int) ($promo['id'] ?? 0); ?> ? 'Copied!' : 'Copy URL'"
                                            ></button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700"><?php echo esc_html((string) ($promo['registration_mode_label'] ?? $promo['registration_mode'] ?? '')); ?></td>
                                    <td class="px-4 py-3 text-slate-700"><?php echo esc_html((string) ($promo['member_rule'] ?? '')); ?></td>
                                    <td class="px-4 py-3 text-slate-900 font-medium"><?php echo esc_html((string) ($promo['price_display'] ?? '')); ?></td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($promo['is_active'])) : ?>
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                        <?php else : ?>
                                            <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactive</span>
                                        <?php endif; ?>
                                        <div class="mt-1 text-[11px] text-slate-400">
                                            <?php echo esc_html((string) ($promo['valid_from_display'] ?? '—')); ?>
                                            →
                                            <?php echo esc_html((string) ($promo['valid_until_display'] ?? '—')); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button" class="text-xs font-medium text-indigo-700 hover:text-indigo-900" @click="openEdit(<?php echo (int) ($promo['id'] ?? 0); ?>)">Edit</button>
                                        <form method="post" action="<?php echo esc_url($profile_form_action); ?>" class="inline ml-2">
                                            <input type="hidden" name="rm_action" value="<?php echo !empty($promo['is_active']) ? 'deactivate_promotion' : 'activate_promotion'; ?>" />
                                            <input type="hidden" name="promotion_id" value="<?php echo esc_attr((string) (int) ($promo['id'] ?? 0)); ?>" />
                                            <?php wp_nonce_field('rm_event_profile', 'rm_event_profile_nonce'); ?>
                                            <button type="submit" class="text-xs font-medium text-slate-600 hover:text-slate-900">
                                                <?php echo !empty($promo['is_active']) ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div
        x-show="modalOpen"
        x-cloak
        class="fixed inset-0 -top-8 z-40 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-slate-900/40" @click="closeModal()"></div>
        <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900" x-text="form.id ? 'Edit package' : 'Add package'"></h3>
                <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeModal()">&times;</button>
            </div>
            <form method="post" action="<?php echo esc_url($profile_form_action); ?>" class="p-5 space-y-4">
                <input type="hidden" name="rm_action" value="save_promotion" />
                <input type="hidden" name="promotion_id" :value="form.id || ''" />
                <?php wp_nonce_field('rm_event_profile', 'rm_event_profile_nonce'); ?>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Title</label>
                    <input type="text" name="title" x-model="form.title" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Slug</label>
                    <input type="text" name="slug" x-model="form.slug" placeholder="auto from title if blank" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                    <textarea name="description" x-model="form.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Registration mode</label>
                    <select name="registration_mode" x-model="form.registration_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        <option value="individual">Individual</option>
                        <option value="group_flat">Group flat</option>
                        <option value="group_per_head">Group per-head</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Member min</label>
                        <input type="number" min="1" name="member_min" x-model="form.member_min" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Member max</label>
                        <input type="number" min="1" name="member_max" x-model="form.member_max" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Package price</label>
                    <input type="number" min="0" step="0.01" name="package_price" x-model="form.package_price" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="require_all_members" value="1" x-model="form.require_all_members" class="rounded border-slate-300 text-indigo-700 focus:ring-indigo-600" />
                    Require all members at checkout
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-slate-300 text-indigo-700 focus:ring-indigo-600" />
                    Active
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Valid from</label>
                        <input type="datetime-local" name="valid_from" x-model="form.valid_from_local" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Valid until</label>
                        <input type="datetime-local" name="valid_until" x-model="form.valid_until_local" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Sort order</label>
                    <input type="number" min="0" name="sort_order" x-model="form.sort_order" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="closeModal()">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800">Save package</button>
                </div>
            </form>
        </div>
    </div>
</section>

<style>[x-cloak]{display:none!important;}</style>

<?php endif; ?>
