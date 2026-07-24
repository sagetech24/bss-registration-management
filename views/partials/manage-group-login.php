<?php
$error_message = (string) ($error_message ?? '');
$page_url = (string) ($page_url ?? '');
$manage_token = (string) ($manage_token ?? '');
$input_class = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
?>

<?php if ($error_message !== '') : ?>
    <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm">
        <?php echo esc_html($error_message); ?>
    </div>
<?php endif; ?>

<div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
    <p class="text-sm font-medium text-slate-800">Verify your registration</p>
    <p class="mt-1 text-sm text-slate-600">
        Enter the confirmation number and primary email from your payment confirmation.
        If you opened a manage link from your email, you can skip this once the link is valid.
    </p>
</div>

<form method="post" action="<?php echo esc_url($page_url); ?>" class="space-y-4 max-w-lg">
    <?php wp_nonce_field('rm_group_manage', 'rm_group_manage_nonce'); ?>
    <input type="hidden" name="rm_group_manage_action" value="login" />
    <?php if ($manage_token !== '') : ?>
        <input type="hidden" name="t" value="<?php echo esc_attr($manage_token); ?>" />
    <?php endif; ?>

    <div>
        <label for="rm_mg_confirmation" class="block text-sm font-medium text-slate-700 mb-1.5">Confirmation number</label>
        <input
            id="rm_mg_confirmation"
            type="text"
            name="confirmation_number"
            required
            autocomplete="off"
            class="<?php echo esc_attr($input_class); ?>"
            placeholder="Confirmation number"
        />
    </div>

    <div>
        <label for="rm_mg_email" class="block text-sm font-medium text-slate-700 mb-1.5">Primary email</label>
        <input
            id="rm_mg_email"
            type="email"
            name="email"
            required
            autocomplete="email"
            class="<?php echo esc_attr($input_class); ?>"
            placeholder="Email used at registration"
        />
    </div>

    <button
        type="submit"
        class="inline-flex items-center justify-center rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 transition"
    >
        Continue
    </button>
</form>
