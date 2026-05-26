<?php
defined('ABSPATH') || exit;

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tav_settings_nonce']) && wp_verify_nonce($_POST['tav_settings_nonce'], 'tav_save_settings')) {
    
    $settings = [
        'tav_email_fulfill_subject',
        'tav_email_fulfill_body',
        'tav_email_payment_subject',
        'tav_email_payment_body',
        'tav_email_received_subject',
        'tav_email_received_body',
        'tav_niches_list',
    ];

    foreach ($settings as $key) {
        if (isset($_POST[$key])) {
            update_option($key, wp_kses_post(stripslashes($_POST[$key])));
        }
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'the-admin-vault') . '</p></div>';
}

$fulfill_subject  = get_option('tav_email_fulfill_subject',  'Your storytellers are ready!');
$fulfill_body     = get_option('tav_email_fulfill_body',     "Hi {client_name},\n\nWe have found some great storytellers for your project {project_name}:\n\n{storyteller_list}\n\nLog in to view them here: {link}\n\nBest,\nThe Team");
$payment_subject  = get_option('tav_email_payment_subject',  'Payment Confirmed — We\'re On It!');
$payment_body     = get_option('tav_email_payment_body',     "Hi {client_name},\n\nGreat news — your payment has been confirmed!\n\nProject: {project_name}\nPackage: {package}\nAmount: {total}\nExpected Delivery: {delivery}\n\nWhat happens next:\n1. Our team is now reviewing your brief\n2. We'll source and vet storytellers that match your criteria\n3. You'll receive an email when your storytellers are ready to review\n\nTrack your request: {link}\n\nBest,\nThe Verified Storytellers Team");
$received_subject = get_option('tav_email_received_subject', 'Request Received: {project_name}');
$received_body    = get_option('tav_email_received_body',    "Hi {client_name},\n\nWe've received your search request: \"{project_name}\".\n\nPackage: {package}\nExpected delivery: {delivery}\n\nYou can track progress on your dashboard:\n{link}\n\nBest,\nThe Verified Storytellers Team");

// Prepare Niches for display
$niches_raw = get_option('tav_niches_list');
if (empty($niches_raw)) {
    // defaults
    $defaults = [
        'climate' => 'Climate',
        'health' => 'Health',
        'politics' => 'Politics',
        'tech' => 'Tech',
        'fashion' => 'Fashion',
        'lifestyle' => 'Lifestyle',
    ];
    $lines = [];
    foreach ($defaults as $k => $v) {
        $lines[] = "$k : $v";
    }
    $niches_display = implode("\n", $lines);
} else {
    $niches_display = $niches_raw;
}

?>

<div class="tav-page-header">
    <h1 class="tav-page-title"><?php esc_html_e('Settings', 'the-admin-vault'); ?></h1>
    <p class="tav-page-subtitle"><?php esc_html_e('Configure application settings and email templates', 'the-admin-vault'); ?></p>
</div>

<div class="tav-panel">
    <div class="tav-panel-header"><h2 class="tav-panel-title"><?php esc_html_e('Email Templates', 'the-admin-vault'); ?></h2></div>
    
    <div class="tav-form-panel" style="padding: 20px;">
        <form method="POST">
            <?php wp_nonce_field('tav_save_settings', 'tav_settings_nonce'); ?>
            
            <h3 style="margin-top: 0;"><?php esc_html_e('Niche Management', 'the-admin-vault'); ?></h3>
            <p class="description" style="margin-bottom: 15px;"><?php esc_html_e('Manage the list of niches available for storytellers.', 'the-admin-vault'); ?></p>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tav_niches_list"><?php esc_html_e('Niches', 'the-admin-vault'); ?></label></th>
                    <td>
                        <textarea name="tav_niches_list" id="tav_niches_list" rows="8" cols="50" class="large-text code"><?php echo esc_textarea($niches_display); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Enter one niche per line. Format: "slug : Label" or just "Label".', 'the-admin-vault'); ?><br>
                            <?php esc_html_e('Example: "climate : Climate Action"', 'the-admin-vault'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <hr>

            <h3 style="margin-top: 20px;"><?php esc_html_e('Fulfillment Notification', 'the-admin-vault'); ?></h3>
            <p class="description" style="margin-bottom: 15px;"><?php esc_html_e('Sent to client when storytellers are assigned to their request.', 'the-admin-vault'); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tav_email_fulfill_subject"><?php esc_html_e('Subject Line', 'the-admin-vault'); ?></label></th>
                    <td>
                        <input name="tav_email_fulfill_subject" type="text" id="tav_email_fulfill_subject" value="<?php echo esc_attr($fulfill_subject); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tav_email_fulfill_body"><?php esc_html_e('Email Body', 'the-admin-vault'); ?></label></th>
                    <td>
                        <textarea name="tav_email_fulfill_body" id="tav_email_fulfill_body" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($fulfill_body); ?></textarea>
                    </td>
                </tr>
            </table>

            <hr style="margin:30px 0;">

            <h3><?php esc_html_e('Payment Confirmed', 'the-admin-vault'); ?></h3>
            <p class="description" style="margin-bottom:15px;"><?php esc_html_e('Sent to client immediately after successful Stripe payment.', 'the-admin-vault'); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tav_email_payment_subject"><?php esc_html_e('Subject', 'the-admin-vault'); ?></label></th>
                    <td><input name="tav_email_payment_subject" type="text" id="tav_email_payment_subject" value="<?php echo esc_attr($payment_subject); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tav_email_payment_body"><?php esc_html_e('Body', 'the-admin-vault'); ?></label></th>
                    <td><textarea name="tav_email_payment_body" id="tav_email_payment_body" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($payment_body); ?></textarea></td>
                </tr>
            </table>

            <hr style="margin:30px 0;">

            <h3><?php esc_html_e('Request Received', 'the-admin-vault'); ?></h3>
            <p class="description" style="margin-bottom:15px;"><?php esc_html_e('Sent to client when a new request is submitted (optional — currently disabled pre-payment).', 'the-admin-vault'); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tav_email_received_subject"><?php esc_html_e('Subject', 'the-admin-vault'); ?></label></th>
                    <td><input name="tav_email_received_subject" type="text" id="tav_email_received_subject" value="<?php echo esc_attr($received_subject); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tav_email_received_body"><?php esc_html_e('Body', 'the-admin-vault'); ?></label></th>
                    <td><textarea name="tav_email_received_body" id="tav_email_received_body" rows="8" cols="50" class="large-text code"><?php echo esc_textarea($received_body); ?></textarea></td>
                </tr>
            </table>

            <hr style="margin:30px 0;">

            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px 20px;">
                <h4 style="margin:0 0 8px; font-size:14px; color:#0369a1;"><?php esc_html_e('Available Email Placeholders', 'the-admin-vault'); ?></h4>
                <table style="font-size:13px; border-collapse:collapse; width:100%;">
                    <tr><td style="padding:4px 12px 4px 0; font-weight:600; white-space:nowrap; color:#0c4a6e;"><code>{client_name}</code></td><td style="padding:4px 0; color:#334155;"><?php esc_html_e("The client's display name", 'the-admin-vault'); ?></td></tr>
                    <tr><td style="padding:4px 12px 4px 0; font-weight:600; white-space:nowrap; color:#0c4a6e;"><code>{project_name}</code></td><td style="padding:4px 0; color:#334155;"><?php esc_html_e("The request / project title", 'the-admin-vault'); ?></td></tr>
                    <tr><td style="padding:4px 12px 4px 0; font-weight:600; white-space:nowrap; color:#0c4a6e;"><code>{package}</code></td><td style="padding:4px 0; color:#334155;"><?php esc_html_e("Package tier name (e.g. Custom Search)", 'the-admin-vault'); ?></td></tr>
                    <tr><td style="padding:4px 12px 4px 0; font-weight:600; white-space:nowrap; color:#0c4a6e;"><code>{total}</code></td><td style="padding:4px 0; color:#334155;"><?php esc_html_e("Formatted order total (e.g. $600.00)", 'the-admin-vault'); ?></td></tr>
                    <tr><td style="padding:4px 12px 4px 0; font-weight:600; white-space:nowrap; color:#0c4a6e;"><code>{delivery}</code></td><td style="padding:4px 0; color:#334155;"><?php esc_html_e("Delivery timeframe (e.g. 5 business days)", 'the-admin-vault'); ?></td></tr>
                    <tr><td style="padding:4px 12px 4px 0; font-weight:600; white-space:nowrap; color:#0c4a6e;"><code>{storyteller_list}</code></td><td style="padding:4px 0; color:#334155;"><?php esc_html_e("Bulleted list of storyteller names (fulfillment email only)", 'the-admin-vault'); ?></td></tr>
                    <tr><td style="padding:4px 12px 4px 0; font-weight:600; white-space:nowrap; color:#0c4a6e;"><code>{link}</code></td><td style="padding:4px 0; color:#334155;"><?php esc_html_e("Link to dashboard or review page", 'the-admin-vault'); ?></td></tr>
                </table>
                <p style="margin:8px 0 0; font-size:12px; color:#64748b;"><?php esc_html_e('All placeholders work in both subject and body. Password reset emails are handled by WordPress/Ultimate Member.', 'the-admin-vault'); ?></p>
            </div>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'the-admin-vault'); ?>">
            </p>
        </form>
    </div>
</div>
