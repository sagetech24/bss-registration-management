<header class="bg-white border-b border-slate-200">
    <div class="mx-auto max-w-7xl px-4 py-4 flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-semibold truncate">Registration Management v2.0</h1>
            <!-- <p class="text-sm text-slate-500 mt-1">Welcome, <?php //echo esc_html($welcome_name); ?>.</p> -->
        </div>

        <div class="hidden lg:flex items-center gap-2">
            <a href="<?php echo esc_url($page_url); ?>" class="flex gap-1 items-center border border-slate-500 text-sm px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Refresh
            </a>
            <a href="<?php echo esc_url(wp_logout_url($page_url)); ?>" class="flex gap-1 items-center border border-rose-500 text-sm px-3 py-2 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                <span class="text-rose-700">Log out</span>
            </a>
        </div>
    </div>
</header>
