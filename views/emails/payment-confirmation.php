<?php
/**
 * Payment confirmation email template.
 *
 * Expected vars from rm_email_render(): event, primary, members, guests,
 * order_number, confirmation_number, package_label, amount_display,
 * payment_method, show_members, show_guests, show_package.
 *
 * @var array<string, mixed> $event
 * @var array<string, mixed>|null $primary
 * @var list<array<string, mixed>> $members
 * @var list<array<string, mixed>> $guests
 * @var string $order_number
 * @var string $confirmation_number
 * @var string $package_label
 * @var string $amount_display
 * @var string $payment_method
 * @var bool $show_members
 * @var bool $show_guests
 * @var bool $show_package
 */

$event = is_array($event ?? null) ? $event : [];
$primary = is_array($primary ?? null) ? $primary : null;
$members = is_array($members ?? null) ? $members : [];
$guests = is_array($guests ?? null) ? $guests : [];

$event_title = esc_html((string) ($event['title'] ?? 'Event'));
$event_date = trim((string) ($event['date_display'] ?? ''));
$event_venue = trim((string) ($event['venue'] ?? ''));
$greet_name = esc_html((string) ($primary['full_name'] ?? 'Registrant'));
$logo_url = 'https://www.bible.org.sg/wp-content/uploads/2015/05/SIBDMainLogo-Black.png';

/**
 * @param array<string, mixed> $person
 */
$rm_email_detail_rows = static function (array $person): array {
    $rows = [];
    $map = [
        'full_name'   => 'Name',
        'email'       => 'Email',
        'contact'     => 'Contact',
        'church_name' => 'Church',
        'order_number'=> 'Registration number',
    ];
    foreach ($map as $key => $label) {
        $value = trim((string) ($person[$key] ?? ''));
        if ($value !== '') {
            $rows[] = ['label' => $label, 'value' => $value];
        }
    }

    return $rows;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registration Confirmed | Bible Society of Singapore</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f2f4;font-family:Arial,Helvetica,sans-serif;color:#2c3338;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f2f4;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:4px;overflow:hidden;">
                    <tr>
                        <td align="center" style="background-color:#e8eaed;padding:20px 24px;">
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Bible Society of Singapore" width="140" style="display:block;border:0;height:auto;" />
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:#1a5f4a;padding:28px 24px;">
                            <p style="margin:0;font-size:24px;line-height:1.3;font-weight:bold;color:#ffffff;">
                                Registration confirmed
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#3c434a;">
                                Hello <?php echo $greet_name; ?>,
                            </p>
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#3c434a;">
                                Thank you. Your registration for <strong style="color:#1d2327;"><?php echo $event_title; ?></strong>
                                has been confirmed after successful payment.
                            </p>
                            <p style="margin:0 0 24px 0;font-size:15px;line-height:1.6;color:#3c434a;">
                                Please review the details below. If anything is incorrect, reply to this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 8px 28px;">
                            <p style="margin:0 0 10px 0;font-size:13px;font-weight:bold;letter-spacing:0.04em;text-transform:uppercase;color:#1a5f4a;">
                                Event
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #dcdcde;border-radius:3px;">
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;width:38%;border-bottom:1px solid #f0f0f1;">Event</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;border-bottom:1px solid #f0f0f1;"><strong><?php echo $event_title; ?></strong></td>
                                </tr>
                                <?php if ($event_date !== '') : ?>
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;border-bottom:1px solid #f0f0f1;">Date &amp; time</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;border-bottom:1px solid #f0f0f1;"><?php echo esc_html($event_date); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($event_venue !== '') : ?>
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;">Venue</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;"><?php echo esc_html($event_venue); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 28px 8px 28px;">
                            <p style="margin:0 0 10px 0;font-size:13px;font-weight:bold;letter-spacing:0.04em;text-transform:uppercase;color:#1a5f4a;">
                                Payment summary
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #dcdcde;border-radius:3px;">
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;width:38%;border-bottom:1px solid #f0f0f1;">Registration number</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;border-bottom:1px solid #f0f0f1;"><strong><?php echo esc_html((string) $order_number); ?></strong></td>
                                </tr>
                                <?php if (trim((string) ($confirmation_number ?? '')) !== '') : ?>
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;border-bottom:1px solid #f0f0f1;">Confirmation number</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;border-bottom:1px solid #f0f0f1;"><?php echo esc_html((string) $confirmation_number); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($show_package) && trim((string) ($package_label ?? '')) !== '') : ?>
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;border-bottom:1px solid #f0f0f1;">Package</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;border-bottom:1px solid #f0f0f1;"><?php echo esc_html((string) $package_label); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;border-bottom:1px solid #f0f0f1;">Amount paid</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;border-bottom:1px solid #f0f0f1;"><strong><?php echo esc_html((string) ($amount_display ?? '')); ?></strong></td>
                                </tr>
                                <?php if (trim((string) ($payment_method ?? '')) !== '' && (string) $payment_method !== 'N/A') : ?>
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;">Payment method</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;"><?php echo esc_html((string) $payment_method); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>

                    <?php if (is_array($primary)) : ?>
                    <tr>
                        <td style="padding:20px 28px 8px 28px;">
                            <p style="margin:0 0 10px 0;font-size:13px;font-weight:bold;letter-spacing:0.04em;text-transform:uppercase;color:#1a5f4a;">
                                Registrant
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #dcdcde;border-radius:3px;">
                                <?php
                                $primary_rows = $rm_email_detail_rows($primary);
                                $last_index = count($primary_rows) - 1;
                                foreach ($primary_rows as $i => $row) :
                                    $border = $i < $last_index ? 'border-bottom:1px solid #f0f0f1;' : '';
                                    ?>
                                <tr>
                                    <td style="padding:12px 14px;font-size:14px;color:#646970;width:38%;<?php echo $border; ?>"><?php echo esc_html($row['label']); ?></td>
                                    <td style="padding:12px 14px;font-size:14px;color:#1d2327;<?php echo $border; ?>"><?php echo esc_html($row['value']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php if (!empty($show_members) && count($members) > 1) : ?>
                    <tr>
                        <td style="padding:20px 28px 8px 28px;">
                            <p style="margin:0 0 10px 0;font-size:13px;font-weight:bold;letter-spacing:0.04em;text-transform:uppercase;color:#1a5f4a;">
                                Group members
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #dcdcde;border-radius:3px;">
                                <tr>
                                    <td style="padding:10px 14px;font-size:12px;font-weight:bold;color:#646970;background-color:#f6f7f7;border-bottom:1px solid #dcdcde;">Name</td>
                                    <td style="padding:10px 14px;font-size:12px;font-weight:bold;color:#646970;background-color:#f6f7f7;border-bottom:1px solid #dcdcde;">Email</td>
                                    <td style="padding:10px 14px;font-size:12px;font-weight:bold;color:#646970;background-color:#f6f7f7;border-bottom:1px solid #dcdcde;">Role</td>
                                </tr>
                                <?php foreach ($members as $mi => $member) :
                                    if (!is_array($member)) {
                                        continue;
                                    }
                                    $row_bg = ($mi % 2 === 1) ? 'background-color:#fafafa;' : '';
                                    ?>
                                <tr>
                                    <td style="padding:10px 14px;font-size:13px;color:#1d2327;border-bottom:1px solid #f0f0f1;<?php echo $row_bg; ?>"><?php echo esc_html((string) ($member['full_name'] ?? '')); ?></td>
                                    <td style="padding:10px 14px;font-size:13px;color:#1d2327;border-bottom:1px solid #f0f0f1;<?php echo $row_bg; ?>"><?php echo esc_html((string) ($member['email'] ?? '')); ?></td>
                                    <td style="padding:10px 14px;font-size:13px;color:#1d2327;border-bottom:1px solid #f0f0f1;<?php echo $row_bg; ?>"><?php echo esc_html((string) ($member['role_label'] ?? 'Member')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php if (!empty($show_guests) && $guests !== []) : ?>
                    <tr>
                        <td style="padding:20px 28px 8px 28px;">
                            <p style="margin:0 0 10px 0;font-size:13px;font-weight:bold;letter-spacing:0.04em;text-transform:uppercase;color:#1a5f4a;">
                                Guest list
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #dcdcde;border-radius:3px;">
                                <tr>
                                    <td style="padding:10px 14px;font-size:12px;font-weight:bold;color:#646970;background-color:#f6f7f7;border-bottom:1px solid #dcdcde;">Name</td>
                                    <td style="padding:10px 14px;font-size:12px;font-weight:bold;color:#646970;background-color:#f6f7f7;border-bottom:1px solid #dcdcde;">Email</td>
                                    <td style="padding:10px 14px;font-size:12px;font-weight:bold;color:#646970;background-color:#f6f7f7;border-bottom:1px solid #dcdcde;">Registration number</td>
                                </tr>
                                <?php foreach ($guests as $gi => $guest) :
                                    if (!is_array($guest)) {
                                        continue;
                                    }
                                    $row_bg = ($gi % 2 === 1) ? 'background-color:#fafafa;' : '';
                                    ?>
                                <tr>
                                    <td style="padding:10px 14px;font-size:13px;color:#1d2327;border-bottom:1px solid #f0f0f1;<?php echo $row_bg; ?>"><?php echo esc_html((string) ($guest['full_name'] ?? '')); ?></td>
                                    <td style="padding:10px 14px;font-size:13px;color:#1d2327;border-bottom:1px solid #f0f0f1;<?php echo $row_bg; ?>"><?php echo esc_html((string) ($guest['email'] ?? '—')); ?></td>
                                    <td style="padding:10px 14px;font-size:13px;color:#1d2327;border-bottom:1px solid #f0f0f1;<?php echo $row_bg; ?>"><?php echo esc_html((string) ($guest['order_number'] ?? '')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <tr>
                        <td style="padding:28px 28px 32px 28px;">
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#646970;">
                                Bible Society of Singapore<br />
                                If you have questions about this registration, please reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
