<?php
$selected_event_code = (string) ($selected_event_code ?? '');
?>

<section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    <div class="lg:col-span-12">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8 text-center">
            <p class="text-6xl font-bold text-slate-200">404</p>
            <h2 class="mt-4 text-2xl font-semibold text-slate-900">Event not found</h2>
            <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                <?php if ($selected_event_code !== '') : ?>
                    No event details were found for program code
                    <span class="font-medium text-slate-800"><?php echo esc_html($selected_event_code); ?></span>.
                <?php else : ?>
                    The requested event could not be found.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a
                    href="<?php echo esc_url($page_url); ?>"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition"
                >
                    Back to events
                </a>
            </div>
        </div>
    </div>
</section>
