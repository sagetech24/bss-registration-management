<?php
$event_present = is_array($event_present ?? null) ? $event_present : null;
$form_input = is_array($form_input ?? null) ? $form_input : rm_registration_form_defaults();
$title_options = rm_registration_title_options();
$input_class = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$form_errors = is_array($form_errors ?? null) ? $form_errors : [];
$success_message = (string) ($success_message ?? '');
$order_number = (string) ($order_number ?? '');
$error_message = (string) ($error_message ?? '');
?>

<section class="space-y-6">
    <?php if ($error_message !== '' && $event_present === null) : ?>
        <div class="bg-white border border-rose-200 rounded-xl shadow-sm p-6">
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                <?php echo esc_html($error_message); ?>
            </div>
        </div>
    <?php else : ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <div class="lg:col-span-8">
                <?php if ($success_message !== '') : ?>
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
                    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                        <?php if ($error_message !== '') : ?>
                            <div class="mb-5 p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                                <?php echo esc_html($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo esc_url($page_url); ?>" class="space-y-5">
                            <?php wp_nonce_field('rm_register', 'rm_register_nonce'); ?>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label for="nric" class="block text-sm font-medium text-slate-700 mb-2">NRIC (Last 4-digit)</label>
                                    <input
                                        id="nric"
                                        name="nric"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['nric']); ?>"
                                        required
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['nric']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['nric'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['nric']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="title" class="block text-sm font-medium text-slate-700 mb-2">Title</label>
                                    <select
                                        id="title"
                                        name="title"
                                        required
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['title']) ? 'border-rose-400' : ''; ?>"
                                    >
                                        <option value="">Please select</option>
                                        <?php foreach ($title_options as $title_option) : ?>
                                            <option value="<?php echo esc_attr($title_option); ?>" <?php selected($form_input['title'], $title_option); ?>>
                                                <?php echo esc_html($title_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($form_errors['title'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['title']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="christianName" class="block text-sm font-medium text-slate-700 mb-2">Christian name</label>
                                    <input
                                        id="christianName"
                                        name="christianName"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['christianName']); ?>"
                                        required
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['christianName']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['christianName'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['christianName']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="familyName" class="block text-sm font-medium text-slate-700 mb-2">Family name</label>
                                    <input
                                        id="familyName"
                                        name="familyName"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['familyName']); ?>"
                                        required
                                        autocomplete="family-name"
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['familyName']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['familyName'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['familyName']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="givenName" class="block text-sm font-medium text-slate-700 mb-2">Given name</label>
                                    <input
                                        id="givenName"
                                        name="givenName"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['givenName']); ?>"
                                        required
                                        autocomplete="given-name"
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['givenName']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['givenName'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['givenName']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="certificateName" class="block text-sm font-medium text-slate-700 mb-2">Certificate name</label>
                                    <input
                                        id="certificateName"
                                        name="certificateName"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['certificateName']); ?>"
                                        required
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['certificateName']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['certificateName'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['certificateName']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="email" class="block text-sm font-medium text-slate-700 mb-2">Email address</label>
                                    <input
                                        id="email"
                                        name="email"
                                        type="email"
                                        value="<?php echo esc_attr($form_input['email']); ?>"
                                        required
                                        autocomplete="email"
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['email']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['email'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['email']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="contact" class="block text-sm font-medium text-slate-700 mb-2">Contact number</label>
                                    <input
                                        id="contact"
                                        name="contact"
                                        type="tel"
                                        value="<?php echo esc_attr($form_input['contact']); ?>"
                                        required
                                        autocomplete="tel"
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['contact']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['contact'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['contact']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="address1" class="block text-sm font-medium text-slate-700 mb-2">Address 1</label>
                                    <input
                                        id="address1"
                                        name="address1"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['address1']); ?>"
                                        required
                                        autocomplete="address-line1"
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['address1']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['address1'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['address1']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="address2" class="block text-sm font-medium text-slate-700 mb-2">Address 2</label>
                                    <input
                                        id="address2"
                                        name="address2"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['address2']); ?>"
                                        autocomplete="address-line2"
                                        class="<?php echo esc_attr($input_class); ?>"
                                    />
                                </div>

                                <div>
                                    <label for="postcode" class="block text-sm font-medium text-slate-700 mb-2">Postal code</label>
                                    <input
                                        id="postcode"
                                        name="postcode"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['postcode']); ?>"
                                        required
                                        autocomplete="postal-code"
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['postcode']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['postcode'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['postcode']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="churchName" class="block text-sm font-medium text-slate-700 mb-2">Church name</label>
                                    <input
                                        id="churchName"
                                        name="churchName"
                                        type="text"
                                        value="<?php echo esc_attr($form_input['churchName']); ?>"
                                        required
                                        class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['churchName']) ? 'border-rose-400' : ''; ?>"
                                    />
                                    <?php if (isset($form_errors['churchName'])) : ?>
                                        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['churchName']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="pt-2 flex justify-between">
                                <button
                                    type="submit"
                                    class="w-full sm:w-auto rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 transition"
                                >
                                    Submit Registration
                                </button>
                                <!-- add a Back button -->
                                <a
                                    href="<?php echo esc_url($page_url); ?>"
                                    class="w-full flex gap-2 items-center sm:w-auto text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50 rounded p-3 duration-300 transition"
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
            </div>

            <?php if (is_array($event_present)) : ?>
                <aside class="lg:col-span-4">
                    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        <?php if ($event_present['thumb_url'] !== '') : ?>
                            <img
                                class="h-68 w-full object-cover"
                                src="<?php echo esc_url($event_present['thumb_url']); ?>"
                                alt="<?php echo esc_attr($event_present['title']); ?>"
                            />
                        <?php endif; ?>

                        <div class="p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">You are registering for</p>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900"><?php echo $event_present['title']; ?></h2>

                            <?php if ($event_present['program_code'] !== '') : ?>
                                <p class="mt-2 text-sm text-slate-500">
                                    Code: <?php echo esc_html(rtrim($event_present['program_code'], '_')); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($event_present['date_display'] !== '') : ?>
                                <p class="mt-3 text-sm text-slate-700"><?php echo 'Date: ' . esc_html($event_present['date_display']); ?></p>
                            <?php endif; ?>

                            <?php if ($event_present['venue'] !== '') : ?>
                                <p class="mt-1 text-sm text-slate-700"><?php echo 'Venue: ' . esc_html($event_present['venue']); ?></p>
                            <?php endif; ?>

                            <p class="mt-1 text-sm text-slate-700">
                                <?php echo 'Price: ' . esc_html($event_present['amount_display'] ?? 'FREE'); ?>
                            </p>
                        </div>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
