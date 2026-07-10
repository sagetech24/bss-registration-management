<?php
/**
 * Loop schema fields and render each via form-field partial.
 *
 * Expected: $form_schema, $responses, $name_prefix, $error_prefix, $input_class, $form_errors
 */
$schema_fields = is_array($form_schema['fields'] ?? null) ? $form_schema['fields'] : [];
$responses = is_array($responses ?? null) ? $responses : [];
$name_prefix = (string) ($name_prefix ?? '');
$error_prefix = (string) ($error_prefix ?? '');
$input_class = (string) ($input_class ?? 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none');
$form_errors = is_array($form_errors ?? null) ? $form_errors : [];
?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <?php foreach ($schema_fields as $field) : ?>
        <?php
        if (!is_array($field)) {
            continue;
        }

        $key = (string) ($field['key'] ?? '');
        $type = (string) ($field['type'] ?? 'text');
        $col_span = in_array($type, ['textarea', 'radio', 'checkbox_group'], true) ? 'sm:col-span-2' : '';
        $value = $responses[$key] ?? null;
        ?>
        <div class="<?php echo esc_attr($col_span); ?>">
            <?php
            include __DIR__ . '/form-field.php';
            ?>
        </div>
    <?php endforeach; ?>
</div>
