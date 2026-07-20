<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Bible Society of Singapore - Registration Management v2</title>
        <meta name="robots" content="noindex,nofollow" />

        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900">
        <div class="flex min-h-screen">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <div class="flex-1 flex flex-col">
                <?php include __DIR__ . '/partials/header.php'; ?>

                <main class="flex-1 mx-auto w-full max-w-7xl px-4 py-8">
                    <?php include $view_file; ?>
                </main>
            </div>
        </div>
    </body>
</html>
