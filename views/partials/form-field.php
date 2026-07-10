<?php
/**
 * Render a single schema-driven form field.
 *
 * Expected variables: $field, $value, $name_prefix, $input_class, $form_errors
 */
$field = is_array($field ?? null) ? $field : [];
$key = (string) ($field['key'] ?? '');
$type = (string) ($field['type'] ?? 'text');
$label = (string) ($field['label'] ?? $key);
$required = !empty($field['required']);
$placeholder = (string) ($field['placeholder'] ?? '');
$name = ($name_prefix ?? '') . $key;
$error_key = ($error_prefix ?? '') . $key;
$field_error = is_array($form_errors ?? null) && isset($form_errors[$error_key])
    ? $form_errors[$error_key]
    : (is_array($form_errors ?? null) && isset($form_errors[$key]) ? $form_errors[$key] : null);
$input_class = (string) ($input_class ?? 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none');
$field_class = $input_class . ($field_error ? ' border-rose-400' : '');
$field_id = esc_attr($name);

if ($key === '' || $type === 'hidden') {
    return;
}
?>

<div>
    <label for="<?php echo $field_id; ?>" class="block text-sm font-medium text-slate-700 mb-2">
        <?php echo esc_html($label); ?>
        <?php if ($required) : ?>
            <span class="text-rose-500">*</span>
        <?php endif; ?>
    </label>

    <?php if ($type === 'textarea') : ?>
        <textarea
            id="<?php echo $field_id; ?>"
            name="<?php echo esc_attr($name); ?>"
            <?php echo $required ? 'required' : ''; ?>
            placeholder="<?php echo esc_attr($placeholder); ?>"
            class="<?php echo esc_attr($field_class); ?>"
            rows="3"
        ><?php echo esc_textarea(is_scalar($value ?? '') ? (string) $value : ''); ?></textarea>

    <?php elseif ($type === 'select') : ?>
        <select
            id="<?php echo $field_id; ?>"
            name="<?php echo esc_attr($name); ?>"
            <?php echo $required ? 'required' : ''; ?>
            class="<?php echo esc_attr($field_class); ?>"
        >
            <option value="">Please select</option>
            <?php foreach (($field['options'] ?? []) as $option) : ?>
                <?php
                $opt_value = is_array($option) ? (string) ($option['value'] ?? '') : (string) $option;
                $opt_label = is_array($option) ? (string) ($option['label'] ?? $opt_value) : (string) $option;
                ?>
                <option value="<?php echo esc_attr($opt_value); ?>" <?php selected((string) ($value ?? ''), $opt_value); ?>>
                    <?php echo esc_html($opt_label); ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ($type === 'radio') : ?>
        <div class="space-y-2">
            <?php foreach (($field['options'] ?? []) as $option) : ?>
                <?php
                $opt_value = is_array($option) ? (string) ($option['value'] ?? '') : (string) $option;
                $opt_label = is_array($option) ? (string) ($option['label'] ?? $opt_value) : (string) $option;
                $radio_id = $field_id . '_' . sanitize_key($opt_value);
                ?>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="radio"
                        id="<?php echo esc_attr($radio_id); ?>"
                        name="<?php echo esc_attr($name); ?>"
                        value="<?php echo esc_attr($opt_value); ?>"
                        <?php checked((string) ($value ?? ''), $opt_value); ?>
                        <?php echo $required ? 'required' : ''; ?>
                        class="text-indigo-600 focus:ring-indigo-500"
                    />
                    <?php echo esc_html($opt_label); ?>
                </label>
            <?php endforeach; ?>
        </div>

    <?php elseif ($type === 'checkbox') : ?>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input
                type="checkbox"
                id="<?php echo $field_id; ?>"
                name="<?php echo esc_attr($name); ?>"
                value="1"
                <?php checked(!empty($value)); ?>
                <?php echo $required ? 'required' : ''; ?>
                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            <?php echo esc_html($placeholder !== '' ? $placeholder : $label); ?>
        </label>

    <?php elseif ($type === 'checkbox_group') : ?>
        <div class="space-y-2">
            <?php
            $selected = is_array($value ?? null) ? $value : [];
            foreach (($field['options'] ?? []) as $option) :
                $opt_value = is_array($option) ? (string) ($option['value'] ?? '') : (string) $option;
                $opt_label = is_array($option) ? (string) ($option['label'] ?? $opt_value) : (string) $option;
                $checkbox_id = $field_id . '_' . sanitize_key($opt_value);
                ?>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        id="<?php echo esc_attr($checkbox_id); ?>"
                        name="<?php echo esc_attr($name); ?>[]"
                        value="<?php echo esc_attr($opt_value); ?>"
                        <?php checked(in_array($opt_value, $selected, true)); ?>
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <?php echo esc_html($opt_label); ?>
                </label>
            <?php endforeach; ?>
        </div>

    <?php else : ?>
        <?php
        $html_type = match ($type) {
            'email'  => 'email',
            'number' => 'number',
            'date'   => 'date',
            'phone'  => 'tel',
            default  => 'text',
        };
        ?>
        <input
            id="<?php echo $field_id; ?>"
            name="<?php echo esc_attr($name); ?>"
            type="<?php echo esc_attr($html_type); ?>"
            value="<?php echo esc_attr(is_scalar($value ?? '') ? (string) $value : ''); ?>"
            <?php echo $required ? 'required' : ''; ?>
            placeholder="<?php echo esc_attr($placeholder); ?>"
            class="<?php echo esc_attr($field_class); ?>"
        />
    <?php endif; ?>

    <?php if ($field_error) : ?>
        <p class="mt-1 text-sm text-rose-600"><?php echo esc_html((string) $field_error); ?></p>
    <?php endif; ?>
</div>
