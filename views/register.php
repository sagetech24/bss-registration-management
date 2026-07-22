<?php
$event_present = is_array($event_present ?? null) ? $event_present : null;
$form_input = is_array($form_input ?? null) ? $form_input : rm_registration_form_defaults();
$uses_v2 = !empty($uses_v2);
$form_schema = is_array($form_schema ?? null) ? $form_schema : ['fields' => []];
$is_group_mode = !empty($is_group_mode);
$registration_config = is_array($registration_config ?? null) ? $registration_config : [];
$mode = (string) ($registration_config['mode'] ?? 'group_flat');
$group_limits = is_array($group_limits ?? null) ? $group_limits : ['min' => 1, 'max' => 1, 'require_all_members' => false];
$pricing_preview = is_array($pricing_preview ?? null) ? $pricing_preview : [];
$members_input = is_array($members_input ?? null) ? $members_input : [];
$promotion_present = is_array($promotion_present ?? null) ? $promotion_present : null;
$active_promotion = is_array($active_promotion ?? null) ? $active_promotion : null;
$individual_href = (string) ($individual_href ?? '');
$input_class = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$form_errors = is_array($form_errors ?? null) ? $form_errors : [];
$success_message = (string) ($success_message ?? '');
$order_number = (string) ($order_number ?? '');
$registration_receipt = is_array($registration_receipt ?? null) ? $registration_receipt : null;
$error_message = (string) ($error_message ?? '');
$responses = $members_input[0] ?? rm_form_empty_responses($form_schema);
$privacy_policy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
?>

<section class="space-y-6">
    <?php if ($error_message !== '' && $event_present === null) : ?>
        <div class="bg-white border border-rose-200 rounded-xl shadow-sm p-6">
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                <?php echo esc_html($error_message); ?>
            </div>
            <?php if ($individual_href !== '') : ?>
                <p class="mt-4 text-sm text-slate-600">
                    <a href="<?php echo esc_url($individual_href); ?>" class="font-medium text-indigo-700 hover:text-indigo-900">
                        Register individually instead
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="mx-auto w-full">
            <?php if ($registration_receipt !== null) : ?>
                <?php include __DIR__ . '/partials/registration-receipt.php'; ?>
            <?php elseif ($success_message !== '') : ?>
                <div class="bg-white border border-emerald-200 rounded-xl shadow-sm p-6">
                    <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800">
                        <p class="font-medium"><?php echo esc_html($success_message); ?></p>
                        <?php if ($order_number !== '') : ?>
                            <p class="mt-2 text-sm">
                                Your order number:
                                <span class="font-semibold"><?php echo esc_html($order_number); ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <?php if ($promotion_present !== null) : ?>
                    <div class="mb-10 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600">Registration package</p>
                        <h3 class="mt-1 text-2xl font-semibold text-slate-900">
                            <?php echo esc_html($promotion_present['title']); ?>
                        </h3>
                        <?php if ($promotion_present['description'] !== '') : ?>
                            <p class="mt-1 text-sm text-slate-600"><?php echo 'About: ' . esc_html($promotion_present['description']); ?></p>
                        <?php endif; ?>
                        <?php if ($promotion_present['member_rule'] !== '') : ?>
                            <span class="text-slate-500 text-sm">Required:</span>
                            <span class="text-slate-500 text-sm italic">
                                <?php echo '(' . esc_html($promotion_present['member_rule']) . ')'; ?>
                            </span>
                        <?php endif; ?>
                        <p class="mt-1 text-sm text-slate-700">
                            <?php if (($promotion_present['original_price_display'] ?? '') !== '') : ?>
                                <span class="text-red-500 text-lg line-through">
                                    <?php echo esc_html($promotion_present['original_price_display']); ?>
                                </span>
                            <?php endif; ?>
                            <span class="text-slate-800 text-lg font-semibold">
                                <?php echo esc_html($promotion_present['price_display']); ?>
                            </span>
                        </p>
                        <?php if ($mode !== 'individual') : ?>
                            <p class="mt-3 text-sm">
                                <a href="<?php echo esc_url($individual_href); ?>" class="font-medium text-indigo-700 hover:text-indigo-900">
                                    Register individually instead
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                $guest_schema = is_array($guest_schema ?? null) ? $guest_schema : ['fields' => [], 'enabled' => false];
                $guests_input = is_array($guests_input ?? null) ? $guests_input : [];
                $has_guests = !empty($guest_schema['enabled']);
                $use_wizard = $uses_v2 && ($is_group_mode || $has_guests);
                ?>
                <?php if ($use_wizard) : ?>
                    <?php if ($error_message !== '') : ?>
                        <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                            <?php echo esc_html($error_message); ?>
                        </div>
                    <?php endif; ?>
                    <?php include __DIR__ . '/partials/register-wizard.php'; ?>
                <?php else : ?>
                    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                        <?php if ($error_message !== '') : ?>
                            <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                                <?php echo esc_html($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo esc_url($page_url); ?>" class="space-y-5">
                            <?php wp_nonce_field('rm_register', 'rm_register_nonce'); ?>
                            <?php if ($active_promotion !== null) : ?>
                                <input type="hidden" name="event_promotion_id" value="<?php echo esc_attr((string) (int) $active_promotion['id']); ?>" />
                            <?php endif; ?>

                            <?php if ($uses_v2) : ?>
                                <?php
                                $responses = $members_input[0] ?? rm_form_empty_responses($form_schema);
                                include __DIR__ . '/partials/dynamic-form.php';
                                ?>
                            <?php else : ?>
                                <?php
                                $title_options = rm_registration_title_options();
                                include __DIR__ . '/partials/register-legacy-fields.php';
                                ?>
                            <?php endif; ?>

                            <div class="pt-2 flex justify-between">
                                <button
                                    type="submit"
                                    class="w-full sm:w-auto rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 transition"
                                >
                                    Submit Registration
                                </button>
                                <a
                                    href="<?php echo esc_url($page_url); ?>"
                                    class="flex gap-2 items-center text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50 rounded p-3 duration-300 transition"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18" />
                                    </svg>
                                    Back
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                <p class="text-xs text-slate-500 leading-relaxed mt-4">
                    * By providing your contact details, you consent to our collection, use and disclosure of your personal data as described in our
                    <?php if ($privacy_policy_url !== '') : ?>
                        <a href="<?php echo esc_url($privacy_policy_url); ?>" class="font-medium text-indigo-700 hover:text-indigo-900">
                            privacy policy
                        </a>
                    <?php else : ?>
                        privacy policy
                    <?php endif; ?>
                    on our website. We do strive to limit the amount of personal data we collect to that which is sufficient to support the intended purpose of the collection.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
