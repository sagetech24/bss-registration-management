<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php
            $page_title = is_array($event_present ?? null) && ($event_present['title'] ?? '') !== ''
                ? wp_strip_all_tags((string) $event_present['title'])
                : 'Event Registration';
            echo esc_html($page_title);
        ?></title>

        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900">
        <div class="min-h-screen flex flex-col">
            <header class="bg-white border-b border-slate-200">
                <div class="mx-auto max-w-6xl px-4 py-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Bible Society of Singapore</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-900">Event Registration</h1>
                </div>
            </header>

            <main class="flex-1 mx-auto w-full max-w-6xl px-4 py-8">
                <?php include $view_file; ?>
            </main>

            <footer class="border-t border-slate-200 bg-white">
                <div class="mx-auto max-w-6xl px-4 py-4 text-center text-sm text-slate-500">
                    &copy; <?php echo esc_html((string) current_time('Y')); ?> Bible Society of Singapore
                </div>
            </footer>
        </div>
    </body>
</html>
