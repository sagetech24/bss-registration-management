<aside class="hidden md:flex w-64 shrink-0 flex-col sticky top-0 h-screen overflow-y-auto bg-gradient-to-b from-slate-800 to-indigo-800 text-slate-100">
    <div class="px-5 py-6 border-b border-slate-800 flex gap-2 items-start justify-start">
        <?php
        $site_logo = function_exists('ot_get_option') ? (string) ot_get_option('act_logo') : '';
        if ($site_logo !== '') :
            ?>
            <img
                src="<?php echo esc_url($site_logo); ?>"
                alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                class="w-12 h-12 object-contain shrink-0"
            >
        <?php endif; ?>
        <div class="flex flex-col items-start justify-start">
            <h1 class="text-lg font-semibold">Event Portal v2.0</h1>
            <p class="text-[13px] text-slate-400 font-light leading-none">Bible Society of Singapore</p>
        </div>
    </div>

    <nav class="">
        <div class="flex items-center gap-2 px-4 py-3">
            <h2 class="">
                <span class="text-slate-200 italic text-lg">Hello!</span> 
                <span class="text-slate-100 text-lg font-semibold capitalize underline">
                    <?php echo esc_html(wp_get_current_user()->display_name); ?>
                </span>
            </h2>
        </div>
        <?php rm_render_nav_item('events', 'Events', add_query_arg([], $page_url), $active_nav); ?>
        <?php
            // $registrants_href = add_query_arg(['action' => 'get-event-registrants', 'event_code' => ''], $page_url);
            // rm_render_nav_item('registrants', 'Registrants', $registrants_href, $active_nav);
        ?>

        <?php
        // $payment_transactions_href = add_query_arg(['action' => 'payment-transactions'], $page_url);
        // rm_render_nav_item('payment-transactions', 'Payment Transactions', $payment_transactions_href, $active_nav);
        ?>

        <?php //rm_render_nav_item('settings', 'Settings', '#', $active_nav); ?>
    </nav>
</aside>
