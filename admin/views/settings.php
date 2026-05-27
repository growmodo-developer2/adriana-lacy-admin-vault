<?php
defined('ABSPATH') || exit;

/*──────────────────────────────────────────────────────────────────────
 * Save handler
 *────────────────────────────────────────────────────────────────────*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['tav_settings_nonce'])
    && wp_verify_nonce($_POST['tav_settings_nonce'], 'tav_save_settings')
) {
    $options = [
        'tav_email_fulfill_subject',
        'tav_email_fulfill_body',
        'tav_email_payment_subject',
        'tav_email_payment_body',
        'tav_email_received_subject',
        'tav_email_received_body',
        'tav_email_reset_subject',
        'tav_email_reset_body',
        'tav_niches_list',
    ];
    foreach ($options as $key) {
        if (isset($_POST[$key])) {
            update_option($key, wp_kses_post(stripslashes($_POST[$key])));
        }
    }
    echo '<div class="notice notice-success is-dismissible"><p>'
        . esc_html__('Settings saved.', 'the-admin-vault')
        . '</p></div>';
}

/*──────────────────────────────────────────────────────────────────────
 * Load saved options (with defaults)
 *────────────────────────────────────────────────────────────────────*/
$fulfill_subject  = get_option('tav_email_fulfill_subject',  'Your storytellers are ready!');
$fulfill_body     = get_option('tav_email_fulfill_body',
    "Hi {client_name},\n\nWe have found some great storytellers for your project {project_name}:\n\n{storyteller_list}\n\nLog in to view them here: {link}\n\nBest,\nThe Team");

$payment_subject  = get_option('tav_email_payment_subject',  'Payment Confirmed — We\'re On It!');
$payment_body     = get_option('tav_email_payment_body',
    "Hi {client_name},\n\nGreat news — your payment has been confirmed!\n\nProject: {project_name}\nPackage: {package}\nAmount: {total}\nExpected Delivery: {delivery}\n\nWhat happens next:\n1. Our team is now reviewing your brief\n2. We'll source and vet storytellers that match your criteria\n3. You'll receive an email when your storytellers are ready to review\n\nTrack your request: {link}\n\nBest,\nThe Verified Storytellers Team");

$received_subject = get_option('tav_email_received_subject', 'Request Received: {project_name}');
$received_body    = get_option('tav_email_received_body',
    "Hi {client_name},\n\nWe've received your search request: \"{project_name}\".\n\nPackage: {package}\nExpected delivery: {delivery}\n\nYou can track progress on your dashboard:\n{link}\n\nBest,\nThe Verified Storytellers Team");

$reset_subject    = get_option('tav_email_reset_subject',   'Reset your password — {site_name}');
$reset_body       = get_option('tav_email_reset_body',
    "Hi {user_name},\n\nYou requested a password reset. Click the link below to set a new password:\n\n{reset_link}\n\nIf you did not request this, ignore this email.");

/*──────────────────────────────────────────────────────────────────────
 * Niches
 *────────────────────────────────────────────────────────────────────*/
$niches_raw = get_option('tav_niches_list');
if (empty($niches_raw)) {
    $defaults = [
        'climate'   => 'Climate',
        'health'    => 'Health',
        'politics'  => 'Politics',
        'tech'      => 'Tech',
        'fashion'   => 'Fashion',
        'lifestyle' => 'Lifestyle',
    ];
    $lines = [];
    foreach ($defaults as $k => $v) {
        $lines[] = "{$k} : {$v}";
    }
    $niches_display = implode("\n", $lines);
} else {
    $niches_display = $niches_raw;
}

/*──────────────────────────────────────────────────────────────────────
 * Preview substitution values (server-resolved, injected into JS)
 *────────────────────────────────────────────────────────────────────*/
$preview_admin_email = get_option('admin_email');
$preview_site_name   = get_bloginfo('name');
$preview_dashboard   = site_url('/client-dashboard/');
$preview_reset_url   = site_url('/wp-login.php?action=rp');
?>
<style>
/* ── Tab chrome ─────────────────────────────────────────────────── */
.tav-tabs-wrap      { margin-top: 24px; }
.tav-tab-list       { display: flex; border-bottom: 2px solid #e2e8f0; margin: 0; padding: 0; gap: 4px; list-style: none; flex-wrap: wrap; }
.tav-tab-btn        { padding: 10px 22px; background: #f8fafc; border: 1px solid #e2e8f0; border-bottom: none; border-radius: 6px 6px 0 0; font-size: 14px; cursor: pointer; color: #64748b; font-weight: 500; transition: background .12s, color .12s; white-space: nowrap; }
.tav-tab-btn:hover  { background: #f1f5f9; color: #1e293b; }
.tav-tab-btn.tav-active { background: #fff; color: #1e293b; font-weight: 600; border-color: #e2e8f0; border-bottom-color: #fff; position: relative; bottom: -2px; z-index: 1; }
.tav-tab-panel      { display: none; border: 1px solid #e2e8f0; border-top: none; padding: 28px 24px 24px; background: #fff; border-radius: 0 0 6px 6px; }
.tav-tab-panel.tav-active { display: block; }

/* ── Placeholder info box ───────────────────────────────────────── */
.tav-ph-box         { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 14px 18px; margin-top: 22px; }
.tav-ph-box h4      { margin: 0 0 10px; font-size: 13px; color: #0369a1; }
.tav-ph-box table   { font-size: 13px; border-collapse: collapse; width: 100%; }
.tav-ph-box td      { padding: 3px 0; vertical-align: top; }
.tav-ph-box td:first-child { padding-right: 16px; white-space: nowrap; font-weight: 600; color: #0c4a6e; }
.tav-ph-box td:last-child  { color: #334155; }

/* ── Preview button ─────────────────────────────────────────────── */
.tav-preview-wrap   { margin-top: 16px; }

/* ── Preview modal ──────────────────────────────────────────────── */
#tav-preview-modal  { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 100000; align-items: center; justify-content: center; }
#tav-preview-modal.tav-modal-open { display: flex; }
#tav-preview-inner  { background: #fff; border-radius: 10px; padding: 32px; width: 92%; max-width: 680px; max-height: 86vh; overflow-y: auto; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
#tav-preview-close  { position: absolute; top: 14px; right: 18px; background: none; border: none; font-size: 22px; line-height: 1; cursor: pointer; color: #94a3b8; }
#tav-preview-close:hover { color: #1e293b; }
.tav-preview-title  { margin: 0 0 22px; font-size: 17px; color: #1e293b; }
.tav-preview-meta   { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 14px; }
.tav-preview-meta td { padding: 5px 0; vertical-align: top; }
.tav-preview-meta td:first-child { width: 68px; font-weight: 600; color: #64748b; padding-right: 12px; }
.tav-preview-hr     { border: none; border-top: 2px solid #e2e8f0; margin: 0 0 18px; }
#tav-preview-body   { font-size: 14px; line-height: 1.75; color: #334155; }
</style>

<div class="tav-page-header">
    <h1 class="tav-page-title"><?php esc_html_e('Settings', 'the-admin-vault'); ?></h1>
    <p class="tav-page-subtitle"><?php esc_html_e('Configure application settings and email templates', 'the-admin-vault'); ?></p>
</div>

<div class="tav-panel">
    <div class="tav-panel-header">
        <h2 class="tav-panel-title"><?php esc_html_e('Settings', 'the-admin-vault'); ?></h2>
    </div>

    <div class="tav-form-panel" style="padding: 20px;">
        <form method="POST">
            <?php wp_nonce_field('tav_save_settings', 'tav_settings_nonce'); ?>

            <!-- ── Niche Management ─────────────────────────────── -->
            <h3 style="margin-top:0;"><?php esc_html_e('Niche Management', 'the-admin-vault'); ?></h3>
            <p class="description" style="margin-bottom:15px;"><?php esc_html_e('Manage the list of niches available for storytellers.', 'the-admin-vault'); ?></p>

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

            <hr style="margin: 28px 0;">

            <!-- ── Email Templates (tabbed) ──────────────────────── -->
            <h3 style="margin: 0 0 6px;"><?php esc_html_e('Email Templates', 'the-admin-vault'); ?></h3>
            <p class="description"><?php esc_html_e('Customise the transactional emails sent to clients. Placeholders are replaced automatically when each email is dispatched.', 'the-admin-vault'); ?></p>

            <div class="tav-tabs-wrap">

                <!-- Tab nav -->
                <ul class="tav-tab-list" role="tablist">
                    <li><button type="button" class="tav-tab-btn tav-active" data-tab="tav-tab-received" role="tab" aria-selected="true"><?php esc_html_e('Request Received', 'the-admin-vault'); ?></button></li>
                    <li><button type="button" class="tav-tab-btn" data-tab="tav-tab-fulfill"  role="tab" aria-selected="false"><?php esc_html_e('Storytellers Ready', 'the-admin-vault'); ?></button></li>
                    <li><button type="button" class="tav-tab-btn" data-tab="tav-tab-payment"  role="tab" aria-selected="false"><?php esc_html_e('Payment Receipt', 'the-admin-vault'); ?></button></li>
                    <li><button type="button" class="tav-tab-btn" data-tab="tav-tab-reset"    role="tab" aria-selected="false"><?php esc_html_e('Password Reset', 'the-admin-vault'); ?></button></li>
                </ul>

                <!-- ╔══════════════════════════════════════════════ -->
                <!-- ║ TAB 1 — Request Received                      -->
                <!-- ╚══════════════════════════════════════════════ -->
                <div id="tav-tab-received" class="tav-tab-panel tav-active" role="tabpanel">
                    <p class="description" style="margin-bottom:16px;"><?php esc_html_e('Sent to the client after payment is confirmed, acknowledging their request has been received.', 'the-admin-vault'); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="tav_email_received_subject"><?php esc_html_e('Subject', 'the-admin-vault'); ?></label></th>
                            <td><input name="tav_email_received_subject" type="text" id="tav_email_received_subject" value="<?php echo esc_attr($received_subject); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tav_email_received_body"><?php esc_html_e('Body', 'the-admin-vault'); ?></label></th>
                            <td><textarea name="tav_email_received_body" id="tav_email_received_body" rows="9" cols="50" class="large-text code"><?php echo esc_textarea($received_body); ?></textarea></td>
                        </tr>
                    </table>

                    <div class="tav-preview-wrap">
                        <button type="button" class="button button-secondary tav-preview-btn"
                            data-subject="tav_email_received_subject"
                            data-body="tav_email_received_body">
                            <?php esc_html_e('Preview Template', 'the-admin-vault'); ?>
                        </button>
                    </div>

                    <div class="tav-ph-box">
                        <h4><?php esc_html_e('Available Placeholders', 'the-admin-vault'); ?></h4>
                        <table>
                            <tr><td><code>{client_name}</code></td><td><?php esc_html_e("Client's display name", 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{project_name}</code></td><td><?php esc_html_e('Request / project title', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{package}</code></td><td><?php esc_html_e('Package tier (e.g. Custom Search)', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{delivery}</code></td><td><?php esc_html_e('Expected delivery timeframe', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{link}</code></td><td><?php esc_html_e('Link to the client dashboard', 'the-admin-vault'); ?></td></tr>
                        </table>
                    </div>
                </div><!-- /tab-received -->

                <!-- ╔══════════════════════════════════════════════ -->
                <!-- ║ TAB 2 — Storytellers Ready (Fulfillment)      -->
                <!-- ╚══════════════════════════════════════════════ -->
                <div id="tav-tab-fulfill" class="tav-tab-panel" role="tabpanel">
                    <p class="description" style="margin-bottom:16px;"><?php esc_html_e('Sent to the client when storytellers have been selected for their request.', 'the-admin-vault'); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="tav_email_fulfill_subject"><?php esc_html_e('Subject', 'the-admin-vault'); ?></label></th>
                            <td><input name="tav_email_fulfill_subject" type="text" id="tav_email_fulfill_subject" value="<?php echo esc_attr($fulfill_subject); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tav_email_fulfill_body"><?php esc_html_e('Body', 'the-admin-vault'); ?></label></th>
                            <td><textarea name="tav_email_fulfill_body" id="tav_email_fulfill_body" rows="9" cols="50" class="large-text code"><?php echo esc_textarea($fulfill_body); ?></textarea></td>
                        </tr>
                    </table>

                    <div class="tav-preview-wrap">
                        <button type="button" class="button button-secondary tav-preview-btn"
                            data-subject="tav_email_fulfill_subject"
                            data-body="tav_email_fulfill_body">
                            <?php esc_html_e('Preview Template', 'the-admin-vault'); ?>
                        </button>
                    </div>

                    <div class="tav-ph-box">
                        <h4><?php esc_html_e('Available Placeholders', 'the-admin-vault'); ?></h4>
                        <table>
                            <tr><td><code>{client_name}</code></td><td><?php esc_html_e("Client's display name", 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{project_name}</code></td><td><?php esc_html_e('Request / project title', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{storyteller_list}</code></td><td><?php esc_html_e('Names of assigned storytellers', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{link}</code></td><td><?php esc_html_e('Link to the review page', 'the-admin-vault'); ?></td></tr>
                        </table>
                    </div>
                </div><!-- /tab-fulfill -->

                <!-- ╔══════════════════════════════════════════════ -->
                <!-- ║ TAB 3 — Payment Receipt                       -->
                <!-- ╚══════════════════════════════════════════════ -->
                <div id="tav-tab-payment" class="tav-tab-panel" role="tabpanel">
                    <p class="description" style="margin-bottom:16px;"><?php esc_html_e('Sent to the client immediately after a successful payment, confirming the charge.', 'the-admin-vault'); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="tav_email_payment_subject"><?php esc_html_e('Subject', 'the-admin-vault'); ?></label></th>
                            <td><input name="tav_email_payment_subject" type="text" id="tav_email_payment_subject" value="<?php echo esc_attr($payment_subject); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tav_email_payment_body"><?php esc_html_e('Body', 'the-admin-vault'); ?></label></th>
                            <td><textarea name="tav_email_payment_body" id="tav_email_payment_body" rows="9" cols="50" class="large-text code"><?php echo esc_textarea($payment_body); ?></textarea></td>
                        </tr>
                    </table>

                    <div class="tav-preview-wrap">
                        <button type="button" class="button button-secondary tav-preview-btn"
                            data-subject="tav_email_payment_subject"
                            data-body="tav_email_payment_body">
                            <?php esc_html_e('Preview Template', 'the-admin-vault'); ?>
                        </button>
                    </div>

                    <div class="tav-ph-box">
                        <h4><?php esc_html_e('Available Placeholders', 'the-admin-vault'); ?></h4>
                        <table>
                            <tr><td><code>{client_name}</code></td><td><?php esc_html_e("Client's display name", 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{project_name}</code></td><td><?php esc_html_e('Request / project title', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{package}</code></td><td><?php esc_html_e('Package tier (e.g. Custom Search)', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{total}</code></td><td><?php esc_html_e('Formatted order total (e.g. $600.00)', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{delivery}</code></td><td><?php esc_html_e('Expected delivery timeframe', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{link}</code></td><td><?php esc_html_e('Link to the client dashboard', 'the-admin-vault'); ?></td></tr>
                        </table>
                    </div>
                </div><!-- /tab-payment -->

                <!-- ╔══════════════════════════════════════════════ -->
                <!-- ║ TAB 4 — Password Reset                        -->
                <!-- ╚══════════════════════════════════════════════ -->
                <div id="tav-tab-reset" class="tav-tab-panel" role="tabpanel">
                    <p class="description" style="margin-bottom:16px;"><?php esc_html_e('Sent when a user requests a password reset link.', 'the-admin-vault'); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="tav_email_reset_subject"><?php esc_html_e('Subject', 'the-admin-vault'); ?></label></th>
                            <td><input name="tav_email_reset_subject" type="text" id="tav_email_reset_subject" value="<?php echo esc_attr($reset_subject); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tav_email_reset_body"><?php esc_html_e('Body', 'the-admin-vault'); ?></label></th>
                            <td><textarea name="tav_email_reset_body" id="tav_email_reset_body" rows="9" cols="50" class="large-text code"><?php echo esc_textarea($reset_body); ?></textarea></td>
                        </tr>
                    </table>

                    <div class="tav-preview-wrap">
                        <button type="button" class="button button-secondary tav-preview-btn"
                            data-subject="tav_email_reset_subject"
                            data-body="tav_email_reset_body">
                            <?php esc_html_e('Preview Template', 'the-admin-vault'); ?>
                        </button>
                    </div>

                    <div class="tav-ph-box">
                        <h4><?php esc_html_e('Available Placeholders', 'the-admin-vault'); ?></h4>
                        <table>
                            <tr><td><code>{user_name}</code></td><td><?php esc_html_e("Recipient's display name", 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{reset_link}</code></td><td><?php esc_html_e('One-time password reset URL', 'the-admin-vault'); ?></td></tr>
                            <tr><td><code>{site_name}</code></td><td><?php esc_html_e('Site name from General Settings', 'the-admin-vault'); ?></td></tr>
                        </table>
                    </div>
                </div><!-- /tab-reset -->

            </div><!-- /.tav-tabs-wrap -->

            <p class="submit" style="margin-top: 24px;">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                    value="<?php esc_attr_e('Save Changes', 'the-admin-vault'); ?>">
            </p>

        </form><!-- /form -->
    </div><!-- /.tav-form-panel -->
</div><!-- /.tav-panel -->

<!-- ── Preview Modal (outside the form, display-only) ────────────── -->
<div id="tav-preview-modal" aria-modal="true" aria-label="<?php esc_attr_e('Email preview', 'the-admin-vault'); ?>" role="dialog">
    <div id="tav-preview-inner">
        <button type="button" id="tav-preview-close" aria-label="<?php esc_attr_e('Close preview', 'the-admin-vault'); ?>">&#x2715;</button>
        <h2 class="tav-preview-title"><?php esc_html_e('Email Preview', 'the-admin-vault'); ?></h2>

        <table class="tav-preview-meta">
            <tr>
                <td><?php esc_html_e('From:', 'the-admin-vault'); ?></td>
                <td><?php echo esc_html($preview_admin_email); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('To:', 'the-admin-vault'); ?></td>
                <td>sample@example.com</td>
            </tr>
            <tr style="border-top:1px solid #e2e8f0;">
                <td><?php esc_html_e('Subject:', 'the-admin-vault'); ?></td>
                <td id="tav-preview-subject" style="font-weight:500;"></td>
            </tr>
        </table>

        <hr class="tav-preview-hr">
        <div id="tav-preview-body"></div>

        <p style="margin:20px 0 0; font-size:12px; color:#94a3b8; font-style:italic;">
            <?php esc_html_e('Sample data shown — no email is sent when previewing.', 'the-admin-vault'); ?>
        </p>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── Sample substitution data ──────────────────────────────────
       Values are server-resolved where they depend on site config,
       then hard-coded sample data for the remainder.              */
    var PREVIEW_DATA = {
        '{client_name}':      'Jane Smith',
        '{user_name}':        'Jane Smith',
        '{project_name}':     'Sample Campaign',
        '{package}':          'Custom Search',
        '{total}':            '$600',
        '{delivery}':         'June 3, 2026',
        '{link}':             <?php echo wp_json_encode($preview_dashboard); ?>,
        '{reset_link}':       <?php echo wp_json_encode($preview_reset_url); ?>,
        '{site_name}':        <?php echo wp_json_encode($preview_site_name); ?>,
        '{storyteller_list}': 'Sarah Miller, David Chen, Elena Kovac',
    };

    /* ── Placeholder substitution ─────────────────────────────── */
    function applySubstitutions(text) {
        Object.keys(PREVIEW_DATA).forEach(function (placeholder) {
            // split/join is faster than RegExp and avoids escaping issues.
            text = text.split(placeholder).join(PREVIEW_DATA[placeholder]);
        });
        return text;
    }

    /* ── Modal helpers ────────────────────────────────────────── */
    var modal   = document.getElementById('tav-preview-modal');
    var closeBtn = document.getElementById('tav-preview-close');

    function openPreview(subjectId, bodyId) {
        var rawSubject = document.getElementById(subjectId).value;
        var rawBody    = document.getElementById(bodyId).value;

        var renderedSubject = applySubstitutions(rawSubject);
        var renderedBody    = applySubstitutions(rawBody);

        document.getElementById('tav-preview-subject').textContent = renderedSubject;

        // Preserve line breaks; escape HTML in the body first to prevent XSS.
        var bodyEl = document.getElementById('tav-preview-body');
        bodyEl.textContent = renderedBody;           // sets text safely
        bodyEl.innerHTML   = bodyEl.innerHTML        // then convert \n → <br>
            .replace(/\n/g, '<br>');

        modal.classList.add('tav-modal-open');
        closeBtn.focus();
    }

    function closePreview() {
        modal.classList.remove('tav-modal-open');
    }

    closeBtn.addEventListener('click', closePreview);

    // Click on the dark overlay (not the inner card) also closes.
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closePreview();
    });

    // Escape key closes.
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.keyCode === 27) && modal.classList.contains('tav-modal-open')) {
            closePreview();
        }
    });

    /* ── Preview buttons ──────────────────────────────────────── */
    document.querySelectorAll('.tav-preview-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPreview(btn.dataset.subject, btn.dataset.body);
        });
    });

    /* ── Tab switching ────────────────────────────────────────── */
    var tabBtns   = document.querySelectorAll('.tav-tab-btn');
    var tabPanels = document.querySelectorAll('.tav-tab-panel');

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            // Deactivate all.
            tabBtns.forEach(function (b) {
                b.classList.remove('tav-active');
                b.setAttribute('aria-selected', 'false');
            });
            tabPanels.forEach(function (p) {
                p.classList.remove('tav-active');
            });

            // Activate the clicked tab.
            btn.classList.add('tav-active');
            btn.setAttribute('aria-selected', 'true');
            document.getElementById(btn.dataset.tab).classList.add('tav-active');
        });
    });
}());
</script>
