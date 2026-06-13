<header class="bg-white border-b border-slate-200">
    <div class="mx-auto max-w-7xl px-4 py-4 flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-semibold truncate">Registration Management</h1>
            <p class="text-sm text-slate-500 mt-1">Welcome, <?php echo esc_html($welcome_name); ?>.</p>
        </div>

        <div class="hidden lg:flex items-center gap-2">
            <a href="<?php echo esc_url($page_url); ?>" class="text-sm px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 transition">
                Refresh
            </a>
            <a href="<?php echo esc_url(wp_logout_url($page_url)); ?>" class="text-sm px-3 py-2 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 transition">
                Log out
            </a>
        </div>
    </div>
</header>
