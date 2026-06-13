<aside class="hidden md:flex w-64 flex-col bg-slate-900 text-slate-100">
    <div class="px-5 py-6 border-b border-slate-800">
        <div class="text-lg font-semibold">BSS Admin</div>
        <div class="text-xs text-slate-400 mt-1">Registration Manager</div>
    </div>

    <nav class="px-3 py-4 space-y-1">
        <?php rm_render_nav_item('events', 'Events', add_query_arg([], $page_url), $active_nav); ?>

        <?php
        $registrants_href = add_query_arg(['action' => 'get-event', 'event_code' => ''], $page_url);
        rm_render_nav_item('registrants', 'Registrants', $registrants_href, $active_nav);
        ?>

        <?php rm_render_nav_item('settings', 'Settings', '#', $active_nav); ?>
    </nav>
</aside>
