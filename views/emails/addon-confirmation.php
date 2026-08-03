<?php
/**
 * Add-on confirmation email template.
 *
 * @var array<string, mixed> $event
 * @var list<array<string, mixed>> $guests
 * @var string $primary_name
 * @var string $confirmation_number
 * @var string $order_number
 * @var string $amount_display
 * @var string $payment_method
 * @var bool $show_payment
 * @var string $add_guests_url
 * @var string $guest_label_plural
 */

$event = is_array($event ?? null) ? $event : [];
$guests = is_array($guests ?? null) ? $guests : [];
$locale = function_exists('rm_normalize_locale')
    ? (rm_normalize_locale((string) ($locale ?? 'en')) ?: 'en')
    : 'en';
$html_lang = function_exists('rm_locale_html_lang') ? rm_locale_html_lang($locale) : 'en';
$et = static function (string $key, array $replace = []) use ($locale): string {
    return function_exists('rm__') ? rm__($key, $locale, $replace) : $key;
};

$event_title = esc_html((string) ($event['title'] ?? $et('email.section.event')));
$greet_name = esc_html((string) ($primary_name ?? $et('wizard.role.registrant')));
$default_logo = 'https://www.bible.org.sg/wp-content/uploads/2015/05/SIBDMainLogo-Black.png';
$logo_url = trim((string) ($event['logo_url'] ?? $event['thumb'] ?? ''));
if ($logo_url === '') {
    $logo_url = $default_logo;
}
$guest_section_label = trim((string) ($guest_label_plural ?? ''));
if ($guest_section_label === '') {
    $guest_section_label = $et('email.section.guests');
}
$guest_label_singular = trim((string) ($guest_label_singular ?? 'Guest'));
if ($guest_label_singular === '') {
    $guest_label_singular = 'Guest';
}
$guest_replace = ['guest' => $guest_section_label];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($html_lang); ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo esc_html($et('email.addon_title', $guest_replace)); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f0f0f1;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f0f1;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #dcdcde;border-radius:4px;">
                <tr>
                    <td style="padding:28px 28px 16px 28px;text-align:center;border-bottom:1px solid #f0f0f1;">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Bible Society of Singapore" width="180" style="max-width:180px;height:auto;" />
                        <p style="margin:16px 0 0 0;font-size:12px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;color:#1a5f4a;">
                            <?php echo esc_html($et('email.addon_banner', $guest_replace)); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 28px 8px 28px;">
                        <p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;color:#1d2327;">
                            <?php echo esc_html($et('email.hello', ['name' => $greet_name])); ?>
                        </p>
                        <p style="margin:0;font-size:15px;line-height:1.6;color:#1d2327;">
                            <?php echo esc_html($et('email.addon_thank_you', $guest_replace)); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 28px 8px 28px;">
                        <p style="margin:0 0 8px 0;font-size:13px;font-weight:bold;letter-spacing:0.04em;text-transform:uppercase;color:#1a5f4a;">
                            <?php echo esc_html($et('email.section.event')); ?>
                        </p>
                        <p style="margin:0;font-size:15px;font-weight:bold;color:#1d2327;"><?php echo $event_title; ?></p>
                        <p style="margin:8px 0 0 0;font-size:13px;color:#646970;">
                            <?php echo esc_html($et('email.label.confirmation')); ?>:
                            <strong><?php echo esc_html((string) ($confirmation_number ?? '')); ?></strong>
                        </p>
                    </td>
                </tr>
                <?php if (!empty($show_payment)) : ?>
                <tr>
                    <td style="padding:12px 28px 8px 28px;">
                        <p style="margin:0 0 8px 0;font-size:13px;font-weight:bold;letter-spacing:0.04em;text-transform:uppercase;color:#1a5f4a;">
                            <?php echo esc_html($et('email.section.payment')); ?>
                        </p>
                        <p style="margin:0;font-size:14px;color:#1d2327;">
                            <?php echo esc_html($et('email.label.amount_paid')); ?>:
                            <strong><?php echo esc_html((string) ($amount_display ?? '')); ?></strong>
                        </p>
                        <p style="margin:6px 0 0 0;font-size:14px;color:#1d2327;">
                            <?php echo esc_html($et('email.label.payment_method')); ?>:
                            <?php echo esc_html((string) ($payment_method ?? 'N/A')); ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($guests !== []) : ?>
                <tr>
                    <td style="padding:20px 28px 8px 28px;">
                        <p style="margin:0 0 10px 0;font-size:13px;font-weight:bold;letter-spacing:0.04em;text-transform:uppercase;color:#1a5f4a;">
                            <?php echo esc_html($guest_section_label); ?>
                        </p>
                        <?php foreach ($guests as $gi => $guest) :
                            if (!is_array($guest)) {
                                continue;
                            }
                            $guest_fields = isset($guest['fields']) && is_array($guest['fields']) ? $guest['fields'] : [];
                            $guest_heading = trim((string) ($guest['heading'] ?? ''));
                            if ($guest_heading === '') {
                                $guest_heading = ($guest_label_singular ?? 'Guest') . ' ' . ($gi + 1);
                            }
                            ?>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #dcdcde;border-radius:3px;margin-bottom:12px;">
                            <tr>
                                <td colspan="2" style="padding:10px 14px;font-size:13px;font-weight:bold;color:#1d2327;background-color:#f6f7f7;border-bottom:1px solid #dcdcde;">
                                    <?php echo esc_html($guest_heading); ?>
                                </td>
                            </tr>
                            <?php if ($guest_fields !== []) :
                                foreach ($guest_fields as $field) :
                                    if (!is_array($field)) {
                                        continue;
                                    }
                                    ?>
                            <tr>
                                <td style="padding:10px 14px;font-size:13px;color:#646970;width:38%;border-bottom:1px solid #f0f0f1;"><?php echo esc_html((string) ($field['label'] ?? '')); ?></td>
                                <td style="padding:10px 14px;font-size:13px;color:#1d2327;border-bottom:1px solid #f0f0f1;"><?php echo esc_html((string) ($field['value'] ?? '')); ?></td>
                            </tr>
                                <?php endforeach;
                            else : ?>
                            <tr>
                                <td style="padding:10px 14px;font-size:13px;color:#646970;width:38%;">Name</td>
                                <td style="padding:10px 14px;font-size:13px;color:#1d2327;"><?php echo esc_html((string) ($guest['full_name'] ?? '')); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php $add_guests_url = trim((string) ($add_guests_url ?? '')); ?>
                <?php if ($add_guests_url !== '') : ?>
                <tr>
                    <td style="padding:12px 28px 8px 28px;">
                        <p style="margin:0 0 12px 0;">
                            <a href="<?php echo esc_url($add_guests_url); ?>" style="display:inline-block;background-color:#1d4ed8;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:12px 18px;border-radius:4px;">
                                <?php echo esc_html($et('email.add_guests_cta', $guest_replace)); ?>
                            </a>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding:28px 28px 32px 28px;border-top:1px solid #f0f0f1;">
                        <p style="margin:0;font-size:12px;line-height:1.6;color:#646970;"><?php echo esc_html($et('email.footer.org')); ?></p>
                        <p style="margin:8px 0 0 0;font-size:12px;line-height:1.6;color:#646970;"><?php echo esc_html($et('email.footer.reply')); ?></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
