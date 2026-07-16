<?php
$is_public = !empty($is_public_layout);
$event_code_display = (string) ($selected_event_code ?? $event_code ?? '');
$error_message = (string) ($error_message ?? '');
$back_href = $is_public
    ? home_url('/')
    : (string) ($page_url ?? rm_page_url());
$back_label = $is_public ? 'Go to homepage' : 'Back to events';
?>

<section
    class="flex flex-col items-center justify-center text-center px-4 py-16 sm:py-24 opacity-0 translate-y-3 transition duration-500 ease-out"
    x-data
    x-init="$nextTick(() => { $el.classList.remove('opacity-0', 'translate-y-3'); $el.classList.add('opacity-100', 'translate-y-0'); })"
>
    <p class="text-7xl sm:text-8xl font-semibold tracking-tight text-slate-200 select-none">404</p>

    <h2 class="mt-4 text-2xl sm:text-3xl font-semibold text-slate-900">
        Event not found
    </h2>

    <p class="mt-3 text-sm sm:text-base text-slate-600 max-w-md leading-relaxed">
        <?php if ($event_code_display !== '') : ?>
            We couldn't find an event for
            <span class="font-medium text-slate-800"><?php echo esc_html($event_code_display); ?></span>.
            The link may be incorrect or the event may no longer be available.
        <?php elseif ($error_message !== '') : ?>
            <?php echo esc_html($error_message); ?>
        <?php else : ?>
            The event you're looking for doesn't exist or is no longer available.
        <?php endif; ?>
    </p>

    <div class="mt-8">
        <a
            href="<?php echo esc_url($back_href); ?>"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 transition"
        >
            <?php if ($is_public) : ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
            <?php else : ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
            <?php endif; ?>
            <?php echo esc_html($back_label); ?>
        </a>
    </div>
</section>
