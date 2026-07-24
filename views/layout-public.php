<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php
            if (!empty($event_not_found)) {
                $page_title = 'Event not found';
            } elseif (is_array($event_present ?? null) && ($event_present['title'] ?? '') !== '') {
                $page_title = wp_strip_all_tags((string) $event_present['title']);
            } else {
                $page_title = 'Event Registration';
            }
            echo esc_html($page_title);
        ?></title>

        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900">
        <div class="min-h-screen flex flex-col">
            <!-- <header class="bg-white border-b border-slate-200">
                <div class="mx-auto max-w-6xl px-4 py-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Bible Society of Singapore</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Event Registration</h1>
                </div>
            </header> -->

            <?php
                $page_width = 'max-w-4xl';
                $banner_url = is_array($event_present ?? null)
                    ? trim((string) ($event_present['thumb_url'] ?? ''))
                    : '';
                $has_banner = $banner_url !== '';
            ?>
            <main class="flex-1 w-full pb-8">
                <div
                    class="relative min-h-[440px] flex items-center justify-center bg-cover bg-center <?php echo $has_banner ? '' : 'bg-slate-900'; ?>"
                    <?php if ($has_banner) : ?>
                        style="background-image: url('<?php echo esc_url($banner_url); ?>');"
                    <?php endif; ?>
                >
                    <?php if ($has_banner) : ?>
                        <div
                            class="absolute inset-0"
                            style="background: linear-gradient(180deg, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.75) 100%);"
                            aria-hidden="true"
                        ></div>
                    <?php endif; ?>

                    <div class="relative mx-auto w-full <?php echo esc_attr($page_width); ?> px-4 py-8 <?php echo $has_banner ? 'text-white' : 'text-slate-900'; ?>">
                        <h1 class="text-3xl font-bold <?php echo $has_banner ? 'text-white' : 'text-slate-900'; ?>">
                            <?php echo esc_html($page_title); ?>
                        </h1>
                        <?php
                        $page_description = is_array($event_present ?? null)
                            ? wp_trim_words(trim((string) ($event_present['description'] ?? '')), 60, '…')
                            : '';
                        ?>
                        <?php if ($page_description !== '') : ?>
                            <p class="mt-3 text-base leading-relaxed <?php echo $has_banner ? 'text-white/90' : 'text-slate-600'; ?>">
                                <?php echo wp_kses_post($page_description); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (is_array($event_present ?? null)) : ?>
                            <ul class="mt-5 space-y-1 text-base <?php echo $has_banner ? 'text-white/90' : 'text-slate-700'; ?>">
                                <?php if (($event_present['date_display'] ?? '') !== '') : ?>
                                    <li class="flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mt-0.5 size-4 shrink-0 <?php echo $has_banner ? 'text-white' : 'text-slate-700'; ?>" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                        <span class="text-sm"><?php echo esc_html((string) $event_present['date_display']); ?></span>
                                    </li>
                                <?php endif; ?>

                                <?php if (($event_present['time_display'] ?? '') !== '') : ?>
                                    <li class="flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mt-0.5 size-4 shrink-0 <?php echo $has_banner ? 'text-white' : 'text-slate-700'; ?>" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        <span class="text-sm"><?php echo esc_html((string) $event_present['time_display']); ?></span>
                                    </li>
                                <?php endif; ?>

                                <?php if (($event_present['venue'] ?? '') !== '') : ?>
                                    <li class="flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mt-0.5 size-4 shrink-0 <?php echo $has_banner ? 'text-white' : 'text-slate-700'; ?>" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        <span class="text-sm"><?php echo esc_html((string) $event_present['venue']); ?></span>
                                    </li>
                                <?php endif; ?>
                                <?php
                                $promotion_present = is_array($promotion_present ?? null) ? $promotion_present : null;
                                $event_amount = trim((string) ($event_present['amount_display'] ?? ''));
                                $package_amount = $promotion_present !== null
                                    ? trim((string) ($promotion_present['price_display'] ?? ''))
                                    : '';
                                ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mx-auto w-full <?php echo $page_width; ?> px-4 py-8">
                    <?php include $view_file; ?>
                </div>
            </main>

            <footer class="mt-auto border-t border-slate-200 bg-white">
                <div class="mx-auto <?php echo $page_width; ?> px-4 py-4 text-center gap-2 md:gap-0 text-sm text-slate-500 flex flex-col md:flex-row items-center justify-between">
                    <p class="text-left text-xs">&copy; <?php echo esc_html((string) current_time('Y')); ?> Bible Society of Singapore</p>
                    <p class="text-right items-center gap-4">
                        <a href="<?php echo esc_url(home_url('/privacy-policy')); ?>" class="text-xs text-slate-500 hover:text-blue-700">Privacy Policy</a>
                        <span class="text-xs">|</span>
                        <a href="<?php echo esc_url(home_url('/terms-of-service')); ?>" class="text-xs text-slate-500 hover:text-blue-700">Terms of Service</a>
                    </p>
                </div>
            </footer>
        </div>
    </body>
</html>
