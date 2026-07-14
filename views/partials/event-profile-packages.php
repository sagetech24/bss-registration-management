<?php
$profile_form_action = rm_event_profile_url($selected_event_code, $selected_event_id, ['tab' => 'packages']);
$promotions = is_array($promotions ?? null) ? $promotions : [];
$uses_v2 = !empty($uses_v2);

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

$event_profile_alpine_config = [
    'usesV2'     => (bool) $uses_v2,
    'promotions' => $promotions_json,
];
?>

<script>
document.addEventListener('alpine:init', () => {
    const profileConfig = <?php echo wp_json_encode($event_profile_alpine_config); ?>;

    Alpine.data('rmEventProfilePackages', () => ({
        usesV2: !!profileConfig.usesV2,
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

<div class="bg-white border border-slate-200 rounded-xl shadow-sm" x-data="rmEventProfilePackages()">
    <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Promotion Packages</h2>
            <p class="mt-1 text-sm text-slate-500">Named packages with their own price and member rules.</p>
        </div>
        <button
            type="button"
            class="inline-flex items-center gap-1 justify-center rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed"
            <?php echo $uses_v2 ? '' : 'disabled'; ?>
            @click="openCreate()"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>

            <span class="hidden sm:inline">Add package</span>
        </button>
    </div>

    <?php if (!$uses_v2) : ?>
        <div class="p-5 text-sm text-slate-600">
            Enable v2 registration in Event Settings before creating packages.
        </div>
    <?php elseif ($promotions === []) : ?>
        <div class="p-8 text-center">
            <p class="mb-2 text-2xl font-semibold text-slate-300">No packages yet.</p>
            <p class="text-sm italic text-slate-500">Add a Couple, Company, or other package to generate alternate registration URLs.</p>
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
                                <!-- <div class="text-xs text-slate-500 mt-0.5">slug: <?php //echo esc_html((string) ($promo['slug'] ?? '')); ?></div> -->
                                <?php if (!empty($promo['package_href'])) : ?>
                                    <button
                                        type="button"
                                        class="mt-1 text-[10px] bg-indigo-50 text-indigo-700 hover:text-indigo-900 rounded-md px-2 py-1 inline-flex items-center gap-1"
                                        @click="copyPromoUrl(<?php echo (int) ($promo['id'] ?? 0); ?>)"
                                        x-text="copiedPromoId === <?php echo (int) ($promo['id'] ?? 0); ?> ? 'Copied!' : 'Copy URL'"
                                    >
                                </button>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?php echo esc_html((string) ($promo['registration_mode_label'] ?? $promo['registration_mode'] ?? '')); ?></td>
                            <td class="px-4 py-3 text-slate-700"><?php echo esc_html((string) ($promo['member_rule'] ?? '')); ?></td>
                            <td class="px-4 py-3 text-slate-900 font-medium"><?php echo esc_html((string) ($promo['price_display'] ?? '')); ?></td>
                            <td class="px-4 py-3">
                                <?php if (!empty($promo['is_active'])) : ?>
                                    <span class="inline-flex rounded-full border border-emerald-500 bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                <?php else : ?>
                                    <span class="inline-flex rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactive</span>
                                <?php endif; ?>
                                <div class="mt-1 text-[11px] text-slate-400 flex flex-col">
                                    <span class="">Start: <?php echo 'Start:' .  esc_html((string) ($promo['valid_from_display'] ?? '—')); ?></span>
                                    <span class="">End: <?php echo esc_html((string) ($promo['valid_until_display'] ?? '—')); ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button" class="text-xs font-medium text-indigo-700 hover:text-indigo-900" @click="openEdit(<?php echo (int) ($promo['id'] ?? 0); ?>)">Edit</button>
                                <span class="text-slate-600">|</span>
                                <form method="post" action="<?php echo esc_url($profile_form_action); ?>" class="inline">
                                    <input type="hidden" name="rm_action" value="<?php echo !empty($promo['is_active']) ? 'deactivate_promotion' : 'activate_promotion'; ?>" />
                                    <input type="hidden" name="promotion_id" value="<?php echo esc_attr((string) (int) ($promo['id'] ?? 0)); ?>" />
                                    <?php wp_nonce_field('rm_event_profile', 'rm_event_profile_nonce'); ?>
                                    <button type="submit" class="text-xs font-medium text-slate-600 hover:text-slate-900 <?php echo !empty($promo['is_active']) ? 'text-red-700 hover:text-red-900' : 'text-emerald-700 hover:text-emerald-900'; ?>">
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

    <div
        x-show="modalOpen"
        x-cloak
        class="fixed inset-0 -top-2 z-40 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-slate-900/40" @click="closeModal()"></div>
        <div class="relative w-full max-w-xl bg-white rounded-xl shadow-xl border border-slate-200 max-h-[97vh] overflow-y-auto">
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
                    <div class="text-[10px] text-slate-500 mt-1 flex flex-col">
                        <em><strong class="text-slate-800">Individual:</strong> Price apply to individual registrant.</em>
                        <em><strong class="text-slate-800">Group flat:</strong> Price apply to all members in the group.</em>
                        <em><strong class="text-slate-800">Group per-head:</strong> Price apply to each registrant in the group.</em>
                    </div>
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
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Package price</label>
                        <div class="flex">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><?php echo esc_html((string) ($event_currency ?? 'SGD')); ?></span>
                            <input type="number" min="0" step="0.01" name="package_price" x-model="form.package_price" class="w-full rounded-r-lg rounded-l-none border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Sort order</label>
                        <input type="number" min="0" name="sort_order" x-model="form.sort_order" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                </div>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="require_all_members" value="1" x-model="form.require_all_members" class="rounded border-slate-300 text-indigo-700 focus:ring-indigo-600" />
                    <div>
                        <p class="text-sm font-medium text-slate-700">Require all members at checkout</p>
                        <p class="text-[11px] text-slate-500 leading-tight"><strong>Strict mode:</strong> All members must be present at checkout otherwise add later. (Applied to group flat and group per-head modes only)</p>
                    </div>
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
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="closeModal()">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800">Save package</button>
                </div>
            </form>
        </div>
    </div>
</div>
