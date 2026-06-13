<?php

function rm_render_nav_item(string $key, string $label, string $href, string $active): void
{
    $is_active = $key === $active;
    $classes = $is_active
        ? 'bg-slate-800 text-white'
        : 'text-slate-300 hover:bg-slate-800 hover:text-white';

    echo '<a href="' . esc_url($href) . '" class="group flex items-center rounded-lg px-3 py-2 text-sm ' . esc_attr($classes) . '">';
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
