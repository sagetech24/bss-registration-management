<?php

/**
 * SVG path markup for a sidebar nav key (Heroicons outline).
 */
function rm_nav_item_icon(string $key): string
{
    $icons = [
        'events' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />',
        'registrants' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />',
        'payment-transactions' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />',
        'migrate-registrant' => '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.174.1.331.244.463.407l1.15.868c.476.358.697.96.54 1.513l-.5 1.498c-.14.42-.02.888.31 1.15l1.064.845c.45.357.56 1.005.26 1.47l-1.293 2.005c-.3.465-.902.653-1.402.44l-1.28-.545a1.875 1.875 0 0 0-1.724.19l-.998.75c-.38.285-.876.285-1.256 0l-.998-.75a1.875 1.875 0 0 0-1.724-.19l-1.28.545c-.5.213-1.102.025-1.402-.44L3.93 12.48c-.3-.465-.19-1.113.26-1.47l1.064-.845c.33-.262.45-.73.31-1.15l-.5-1.498a1.875 1.875 0 0 1 .54-1.513l1.15-.868c.132-.163.289-.307.463-.407.332-.184.582-.496.645-.87l.213-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
    ];

    return $icons[$key] ?? '';
}

function rm_render_nav_item(string $key, string $label, string $href, string $active): void
{
    $is_active = $key === $active;
    $classes = $is_active
        ? 'bg-slate-900 text-white'
        : 'text-slate-100 hover:bg-slate-700 hover:text-white';

    $icon = rm_nav_item_icon($key);

    echo '<a href="' . esc_url($href) . '" class="group flex items-center gap-3 px-4 py-3 text-md ' . esc_attr($classes) . '">';
    if ($icon !== '') {
        echo '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 shrink-0 opacity-80 group-hover:opacity-100" aria-hidden="true">' . $icon . '</svg>';
    }
    echo '<span>' . esc_html($label) . '</span>';
    echo '</a>';
}

/**
 * @param array<string, mixed> $context
 */
function rm_render(string $view, array $context): void
{
    extract($context, EXTR_SKIP);

    $view_file = __DIR__ . '/../views/' . $view . '.php';
    if (!file_exists($view_file)) {
        wp_die('View not found.');
    }

    $layout_file = !empty($is_public_layout)
        ? __DIR__ . '/../views/layout-public.php'
        : __DIR__ . '/../views/layout.php';

    include $layout_file;
}
