<section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    <div class="lg:col-span-12 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-9">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
                <div class="p-5 border-b border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">Events</h2>
                            <p class="text-sm text-slate-500 mt-1">Filter by date, year, and search by title / code / venue.</p>
                        </div>
                    </div>
                </div>
    
                <div class="p-5">
                    <form method="get" action="<?php echo esc_url($page_url); ?>" class="flex flex-col gap-4">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-slate-700 mb-2" for="event_filter">Date filter</label>
                                <select
                                    id="event_filter"
                                    name="event_filter"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                    onchange="this.form.submit()"
                                >
                                    <option value="" <?php selected($event_filter, ''); ?>>All events</option>
                                    <?php foreach ($event_filter_options as $filter_key => $filter_label) : ?>
                                        <option value="<?php echo esc_attr($filter_key); ?>" <?php selected($event_filter, $filter_key); ?>>
                                            <?php echo esc_html($filter_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex-1">
                                <label class="block text-sm font-medium text-slate-700 mb-2" for="event_year">Year</label>
                                <select
                                    id="event_year"
                                    name="event_year"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                    onchange="var y=parseInt(this.value,10),cy=<?php echo (int) current_time('Y'); ?>;if(this.value!==''&&!isNaN(y)&&y<cy){document.getElementById('event_filter').value='';}this.form.submit();"
                                >
                                    <option value="" <?php selected($event_year, ''); ?>>All years</option>
                                    <?php foreach ($event_years as $year) : ?>
                                        <option value="<?php echo esc_attr($year); ?>" <?php selected($event_year, $year); ?>>
                                            <?php echo esc_html($year); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
    
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-slate-700 mb-2" for="event_search">Search</label>
                                <input
                                    id="event_search"
                                    type="search"
                                    name="event_search"
                                    value="<?php echo esc_attr($event_search); ?>"
                                    placeholder="Search events..."
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                />
                            </div>
                        </div>
    
                        <div class="flex items-center justify-between">
                            <?php if (empty($error_message)) : ?>
                                <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <?php if ($has_active_event_filters) : ?>
                                    <p class="text-sm text-slate-600">
                                        Total
                                        <span class="font-semibold text-slate-900"><?php echo esc_html((string) $event_count); ?></span>
                                        <?php echo esc_html($event_count === 1 ? 'result found' : 'results found'); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-center justify-end gap-3">
                                <button type="submit" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 transition">
                                    Apply
                                </button>
                                <?php if ($has_active_event_filters) : ?>
                                    <a
                                        href="<?php echo esc_url($page_url); ?>"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                                    >
                                        Clear filters
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <aside class="lg:col-span-3">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h3 class="text-lg font-semibold">Quick Tips</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <li>New Events (WordPress EVENT posts) appear above legacy bss_events cards.</li>
                    <li>Use the date and year filters to narrow the event list.</li>
                    <li>Use search to match title, program code, venue, and description.</li>
                    <li>Open <span class="font-medium text-slate-800">Dashboard</span> for settings, packages, and summary stats.</li>
                    <li>Use <span class="font-medium text-slate-800">Registrants</span> for the full attendee table.</li>
                </ul>
            </div>
        </aside>
    </div>
    <div class="lg:col-span-12">
        <div class="mt-6">
            <?php if (!empty($error_message)) : ?>
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800">
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>

            <?php
            $cpt_events = isset($cpt_events) && is_array($cpt_events) ? $cpt_events : [];
            $has_cpt_events = rm_count_filtered_events($cpt_events) > 0;
            $has_legacy_events = !empty($events) && rm_count_filtered_events($events) > 0;
            ?>

            <?php if ($has_cpt_events) : ?>
                <div class="mb-10">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <h3 class="text-2xl font-semibold text-slate-900">New Events</h3>
                        <p class="text-sm text-slate-500">WordPress EVENT posts</p>
                    </div>
                    <?php foreach ($cpt_events as $year => $events_list) : ?>
                        <?php if (!is_array($events_list) || count($events_list) === 0) {
                            continue;
                        } ?>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold text-slate-700 mb-3"><?php echo esc_html((string) $year); ?></h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                                <?php foreach ($events_list as $event) : ?>
                                    <?php
                                    if (!is_array($event)) {
                                        continue;
                                    }
                                    $card = rm_present_event_card($event, $page_url);
                                    ?>
                                    <article class="bg-white border border-indigo-200 rounded-xl overflow-hidden shadow-sm flex flex-col h-full">
                                        <?php if ($card['thumb_url'] !== '') : ?>
                                            <img class="h-48 w-full object-cover" src="<?php echo esc_url($card['thumb_url']); ?>" alt="<?php echo esc_attr($card['title']); ?>" />
                                        <?php else : ?>
                                            <div class="h-48 w-full bg-slate-100 flex items-center justify-center text-slate-400 text-sm">
                                                No image
                                            </div>
                                        <?php endif; ?>

                                        <div class="p-4 flex-1">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <h4 class="font-semibold text-slate-900"><?php echo esc_html($card['title']); ?></h4>
                                                    <?php if ($card['program_code'] !== '') : ?>
                                                        <p class="text-xs mt-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-bold font-mono text-indigo-700">
                                                            <?php echo esc_html(rtrim($card['program_code'], '_')); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($card['date_block'] !== '') : ?>
                                                <p class="text-xs text-slate-700 mt-3 truncate">
                                                    <strong>Date:</strong> &nbsp;
                                                    <?php echo $card['date_block']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if ($card['venue_show'] !== '') : ?>
                                                <p class="text-xs text-slate-700 mt-1 truncate"><strong>Venue:</strong> &nbsp;
                                                <?php echo esc_html($card['venue_show']); ?></p>
                                            <?php endif; ?>

                                            <?php if (!empty($card['categories']) && is_array($card['categories'])) : ?>
                                                <div class="mt-3 flex flex-wrap gap-1.5">
                                                    <?php foreach ($card['categories'] as $category_name) : ?>
                                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                                            <?php echo esc_html((string) $category_name); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="border-t border-slate-200 mt-auto">
                                            <div class="flex justify-center items-center gap-2 py-2 px-2.5">
                                                <div class="text-center">
                                                    <a href="<?php echo esc_url($card['profile_href']); ?>" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">
                                                        Dashboard
                                                    </a>
                                                </div>
                                                <span class="text-slate-400">|</span>
                                                <div class="text-center">
                                                    <a href="<?php echo esc_url($card['registrants_href']); ?>" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">
                                                        Registrants
                                                    </a>
                                                </div>
                                                <?php if ($card['registration_href'] !== '') : ?>
                                                    <span class="text-slate-400">|</span>
                                                    <div class="text-center">
                                                        <a href="<?php echo esc_url($card['registration_href']); ?>" class="text-xs font-medium text-indigo-700 hover:text-indigo-900" target="_blank" rel="noopener noreferrer">
                                                            Form
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <!-- display this if it s CTP events and not legacy events -->
                                                <?php if ($card['edit_url'] !== '') : ?>
                                                    <span class="text-slate-400">|</span>
                                                    <div class="text-center">
                                                        <a href="<?php echo esc_url($card['edit_url']); ?>" class="text-xs font-medium text-indigo-700 hover:text-indigo-900" target="_blank" rel="noopener noreferrer">
                                                            Edit
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($error_message) && !$has_cpt_events && !$has_legacy_events) : ?>
                <div class="p-6 text-slate-600 border border-slate-200 rounded-xl bg-white">
                    No events found for this filter.
                </div>
            <?php elseif ($has_legacy_events) : ?>
                <?php if ($has_cpt_events) : ?>
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <h3 class="text-2xl font-semibold text-slate-900">Legacy Events</h3>
                        <p class="text-sm text-slate-500">From bss_events</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($events as $year => $events_list) : ?>
                    <?php if (!is_array($events_list) || count($events_list) === 0) continue; ?>

                    <div class="mt-8">
                        <h3 class="text-3xl font-semibold text-slate-900 mb-4"><?php echo esc_html((string) $year); ?></h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                            <?php foreach ($events_list as $event) : ?>
                                <?php
                                if (!is_array($event)) {
                                    continue;
                                }
                                $card = rm_present_event_card($event, $page_url);
                                ?>

                                <article class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col h-full">
                                    <?php if ($card['thumb_url'] !== '') : ?>
                                        <img class="h-48 w-full object-cover" src="<?php echo esc_url($card['thumb_url']); ?>" alt="<?php echo esc_attr($card['title']); ?>" />
                                    <?php else : ?>
                                        <div class="h-48 w-full bg-slate-100 flex items-center justify-center text-slate-400 text-sm">
                                            No image
                                        </div>
                                    <?php endif; ?>

                                    <div class="p-4 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <h4 class="font-semibold text-slate-900"><?php echo $card['title']; ?></h4>
                                                <?php if ($card['program_code'] !== '') : ?>
                                                    <p class="text-xs text-slate-500 mt-1">Code: <?php echo esc_html(rtrim($card['program_code'], '_')); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($event_filter !== '') : ?>
                                                <span class="shrink-0 uppercase inline-flex items-center rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-medium text-indigo-700">
                                                    <?php echo esc_html($event_filter); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($card['date_block'] !== '') : ?>
                                            <p class="text-sm text-slate-700 mt-3">
                                                <?php echo $card['date_block']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($card['venue_show'] !== '') : ?>
                                            <p class="text-sm text-slate-500 mt-1"><?php echo esc_html($card['venue_show']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="border-t border-slate-200 mt-auto">
                                        <div class="grid grid-cols-3 gap-2 p-4">
                                            <a href="<?php echo esc_url($card['profile_href']); ?>" class="text-xs font-medium text-indigo-700 hover:text-indigo-900 flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 shrink-0">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                                </svg>
                                                Dashboard
                                            </a>
                                            <a href="<?php echo esc_url($card['registrants_href']); ?>" class="text-xs font-medium text-indigo-700 hover:text-indigo-900 flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 shrink-0">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                                </svg>
                                                Registrants
                                            </a>
                                            <a href="<?php echo esc_url($card['registration_href']); ?>" class="text-xs font-medium text-indigo-700 hover:text-indigo-900 flex items-center gap-1" target="_blank" rel="noopener noreferrer">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 shrink-0">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                                </svg>
                                                Form
                                            </a>
                                        </div>
                                        <?php if (!empty($card['package_urls']) && is_array($card['package_urls'])) : ?>
                                            <div class="border-t border-slate-100 px-4 pb-4 pt-2 space-y-1">
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Package links</p>
                                                <?php foreach ($card['package_urls'] as $package_url) : ?>
                                                    <?php if (!is_array($package_url)) {
                                                        continue;
                                                    } ?>
                                                    <a
                                                        href="<?php echo esc_url((string) ($package_url['href'] ?? '')); ?>"
                                                        class="block text-[11px] text-indigo-700 hover:text-indigo-900 truncate"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        title="<?php echo esc_attr((string) ($package_url['href'] ?? '')); ?>"
                                                    >
                                                        <?php echo esc_html((string) ($package_url['title'] ?? 'Package')); ?>
                                                        <?php if (!empty($package_url['price_display'])) : ?>
                                                            <span class="text-slate-400">(<?php echo esc_html((string) $package_url['price_display']); ?>)</span>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
