<?php
/**
 * Email Settings tab — Reply-To / Cc / Bcc for event confirmation emails.
 *
 * @var array<string, mixed> $email_settings
 * @var string $selected_event_code
 * @var int $selected_event_id
 * @var string $email_preview_html
 * @var string $email_preview_subject
 */

$email_settings = is_array($email_settings ?? null) ? $email_settings : [];
$reply_to_value = (string) ($email_settings['reply_to'] ?? '');
$cc_text = (string) ($email_settings['cc_text'] ?? '');
$bcc_text = (string) ($email_settings['bcc_text'] ?? '');
$email_preview_html = (string) ($email_preview_html ?? '');
$email_preview_subject = (string) ($email_preview_subject ?? '');
$profile_form_action = rm_event_profile_url($selected_event_code, $selected_event_id, ['tab' => 'email-settings']);

$header_reply_to = $reply_to_value !== '' ? $reply_to_value : rm_email_default_reply_to();
$header_cc = isset($email_settings['cc']) && is_array($email_settings['cc'])
    ? $email_settings['cc']
    : rm_email_parse_address_list($cc_text);
$header_bcc = isset($email_settings['bcc']) && is_array($email_settings['bcc'])
    ? $email_settings['bcc']
    : rm_email_parse_address_list($bcc_text);
$header_cc_display = $header_cc !== [] ? implode(', ', $header_cc) : '—';
$header_bcc_display = $header_bcc !== [] ? implode(', ', $header_bcc) : '—';
?>
<h2 class="text-lg font-semibold text-slate-900">Email Preview</h2>
<p class="mb-2 text-sm text-slate-500">
    Preview the confirmation email that will be sent to the registrant (sample data).
</p>
<div class="w-full grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col min-h-[300px]">
        <div class="px-4 py-3 border-b border-slate-200">
            <p>
                <span class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Subject: </span>
                <span class="text-xs text-slate-800"><?php echo esc_html($email_preview_subject !== '' ? $email_preview_subject : '—'); ?></span>
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-1">
                <p>
                    <span class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Reply-To: </span>
                    <span class="text-xs text-slate-800 break-all"><?php echo esc_html($header_reply_to); ?></span>
                </p>
                <p>
                    <span class="text-[11px] font-medium uppercase tracking-wider text-slate-400">CC: </span>
                    <span class="text-xs text-slate-800 break-all"><?php echo esc_html($header_cc_display); ?></span>
                </p>
                <p>
                    <span class="text-[11px] font-medium uppercase tracking-wider text-slate-400">BCC: </span>
                    <span class="text-xs text-slate-800 break-all"><?php echo esc_html($header_bcc_display); ?></span>
                </p>
            </div>
        </div>
        <?php if ($email_preview_html !== '') : ?>
            <div class="relative flex-1 min-h-[560px] overflow-hidden bg-slate-100">
                <iframe
                    title="Confirmation email preview"
                    class="absolute top-0 left-0 border-0 bg-slate-100 origin-top-left scale-[0.8] w-[125%] h-[125%] min-h-[700px]"
                    sandbox=""
                    srcdoc="<?php echo htmlspecialchars($email_preview_html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                ></iframe>
            </div>
        <?php else : ?>
            <div class="flex-1 flex items-center justify-center p-8 text-center">
                <p class="text-sm text-slate-500">Email preview could not be rendered.</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="p-5 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Email Addresses</h2>
            <p class="mt-1 text-sm text-slate-500">
                Configure Reply-To, CC, and BCC addresses used when registration confirmation emails are sent for this event.
            </p>
        </div>
        <form method="post" action="<?php echo esc_url($profile_form_action); ?>" class="p-5 space-y-5">
            <input type="hidden" name="rm_action" value="save_email_settings" />
            <?php wp_nonce_field('rm_event_profile', 'rm_event_profile_nonce'); ?>

            <fieldset class="rounded-lg border border-indigo-300 p-4 space-y-4">
                <legend class="text-sm font-medium text-indigo-700 px-1">Outbound headers</legend>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_email_reply_to">Reply-To</label>
                    <input
                        type="email"
                        id="rm_email_reply_to"
                        name="reply_to"
                        value="<?php echo esc_attr($reply_to_value); ?>"
                        placeholder="e.g. events@biblesociety.sg"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                    />
                    <p class="mt-1 text-[11px] text-slate-500">
                        Where registrant replies go. Leave blank to use the default
                        (<?php echo esc_html(rm_email_default_reply_to()); ?>).
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_email_cc">CC</label>
                    <textarea
                        id="rm_email_cc"
                        name="cc"
                        rows="4"
                        placeholder="One email per line, or comma-separated"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none"
                    ><?php echo esc_textarea($cc_text); ?></textarea>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Staff copies of confirmation emails. One address per line, or separated by commas.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1" for="rm_email_bcc">BCC</label>
                    <textarea
                        id="rm_email_bcc"
                        name="bcc"
                        rows="3"
                        placeholder="One email per line, or comma-separated"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none"
                    ><?php echo esc_textarea($bcc_text); ?></textarea>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Blind copies — recipients are hidden from the registrant and from CC recipients.
                    </p>
                </div>
            </fieldset>

            <div class="pt-1">
                <button type="submit" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 transition">
                    Save email settings
                </button>
            </div>
        </form>
    </div>
</div>
