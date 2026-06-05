<?php
/**
 * Email template settings — Figma layout.
 *
 * @package TheAdminVault
 */

defined('ABSPATH') || exit;

$ccc_settings_embedded = !empty($GLOBALS['ccc_settings_embedded']);

/* ── Save handler ─────────────────────────────────────────────── */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['tav_settings_nonce'])
    && wp_verify_nonce($_POST['tav_settings_nonce'], 'tav_save_settings')
    && (current_user_can('edit_storytellers') || current_user_can('manage_options'))
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
        'tav_email_intro_subject',
        'tav_email_intro_body',
        'tav_email_enterprise_subject',
        'tav_email_enterprise_body',
        'tav_email_admin_paid_subject',
        'tav_email_admin_paid_body',
    ];
    foreach ($options as $key) {
        if (isset($_POST[$key])) {
            update_option($key, wp_kses_post(stripslashes($_POST[$key])));
        }
    }
    echo '<div class="tav-settings-notice tav-settings-notice-success"><p>'
        . esc_html__('Settings saved.', 'the-admin-vault')
        . '</p></div>';
}

/* ── Defaults (Figma copy) ────────────────────────────────────── */
$defaults = [
    'received_subject' => "We've received your Storyteller Search Request: ID: {{request_id}}",
    'received_body'    => "Hi {{client_name}},\n\nThank you for trusting VerifiedStorytellers.com with your mission.\n\nThis email confirms that we have received your request (ID: {{request_id}}). Our curation team is now actively vetting profiles to find the 5-8 best storytellers that align perfectly with your mission and brief.\n\nYou will receive a notification email within 72 hours with the curated list.\n\nIn the meantime, feel free to review your brief on the platform: {{platform_url}}/requests/{{request_id}}\n\nBest regards,\nThe Verified Storytellers Team",
    'fulfill_subject'  => 'Your Curated Storytellers are Ready to View!',
    'fulfill_body'     => "Hi {{client_name}},\n\nGreat news! The Verified Storytellers curation team has completed the matchmaking for your request ID: {{request_id}}.\n\nThe 8 hand-picked, vetted profiles are now available for your review on the platform. These storytellers have been selected based on their proven experience in relevant content that matches your specific needs.\n\nReady to see your matches? Click here: {{platform_url}}/matches/{{request_id}}\n\nWe look forward to connecting you!\nThe Verified Storytellers Team",
    'payment_subject'  => 'Your Payment Receipt from VerifiedStorytellers.com (Amount: {{total_amount}})',
    'payment_body'     => "Hi {{client_name}},\n\nThank you for your payment! We have successfully processed the upfront fee for your matchmaking request (ID: {{request_id}}).\n\nTransaction Summary:\nAmount Paid: {{total_amount}}\nPayment Method: Stripe\n\nThe full details and invoice are available in your account dashboard: {{platform_url}}/billing\n\nWe'll be in touch soon with your curated storyteller profiles.\n\nBest,\nThe Verified Storytellers Team",
    'reset_subject'    => 'Your Password Reset Request for VerifiedStorytellers.com',
    'reset_body'       => "Hello,\n\nWe received a request to reset the password for your VerifiedStorytellers.com account.\n\nPlease click the link below to set a new password: {{reset_link}}\n\nIf you did not request a password reset, you can safely ignore this email.\n\nThis link will expire in 60 minutes.\n\nRegards,\nVerifiedStorytellers.com Security Team",
    'intro_subject'    => 'Introduction Request: {{project_name}}',
    'intro_body'       => "Client {{client_name}} ({{client_email}}) has requested introductions for project: \"{{project_name}}\".\n\nSelected storytellers:\n{{storyteller_list}}\n\nPlease facilitate the warm introductions.\nAdmin link: {{admin_url}}",
    'enterprise_subject' => 'New Enterprise inquiry: {{project_name}}',
    'enterprise_body'    => "A client submitted an Enterprise search request.\n\nClient: {{client_name}} ({{client_email}})\nProject: {{project_name}}\nRequest ID: {{request_id}}\n\nAdmin: {{admin_url}}",
    'admin_paid_subject' => 'New Paid Request: {{project_name}}',
    'admin_paid_body'    => "New paid request received!\n\nProject: {{project_name}}\nClient: {{client_name}} ({{client_email}})\nPackage: {{package}}\nOrder Total: {{total_amount}}\nOrder: {{order_url}}\n\nFulfill this request:\n{{fulfill_url}}",
];

$fulfill_subject  = get_option('tav_email_fulfill_subject', $defaults['fulfill_subject']);
$fulfill_body     = get_option('tav_email_fulfill_body', $defaults['fulfill_body']);
$payment_subject  = get_option('tav_email_payment_subject', $defaults['payment_subject']);
$payment_body     = get_option('tav_email_payment_body', $defaults['payment_body']);
$received_subject = get_option('tav_email_received_subject', $defaults['received_subject']);
$received_body    = get_option('tav_email_received_body', $defaults['received_body']);
$reset_subject    = get_option('tav_email_reset_subject', $defaults['reset_subject']);
$reset_body       = get_option('tav_email_reset_body', $defaults['reset_body']);
$intro_subject    = get_option('tav_email_intro_subject', $defaults['intro_subject']);
$intro_body       = get_option('tav_email_intro_body', $defaults['intro_body']);
$enterprise_subject = get_option('tav_email_enterprise_subject', $defaults['enterprise_subject']);
$enterprise_body    = get_option('tav_email_enterprise_body', $defaults['enterprise_body']);
$admin_paid_subject = get_option('tav_email_admin_paid_subject', $defaults['admin_paid_subject']);
$admin_paid_body    = get_option('tav_email_admin_paid_body', $defaults['admin_paid_body']);

$preview_dashboard = site_url('/client-dashboard/');
$preview_reset_url = site_url('/wp-login.php?action=rp');
$preview_site_name = get_bloginfo('name');

$placeholders = [
    '{{client_name}}',
    '{{client_email}}',
    '{{project_name}}',
    '{{request_id}}',
    '{{package}}',
    '{{platform_url}}',
    '{{total_amount}}',
    '{{reset_link}}',
    '{{storyteller_list}}',
    '{{admin_url}}',
    '{{order_url}}',
    '{{fulfill_url}}',
];

$active_tab = sanitize_key($_GET['email_tab'] ?? 'received');
$allowed_tabs = ['received', 'fulfill', 'payment', 'reset', 'intro', 'enterprise', 'admin_paid'];
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = 'received';
}

$tab_labels = [
    'received'    => __('Request Received', 'the-admin-vault'),
    'fulfill'     => __('Storytellers Ready', 'the-admin-vault'),
    'payment'     => __('Payment Receipt', 'the-admin-vault'),
    'reset'       => __('Password Reset', 'the-admin-vault'),
    'intro'       => __('Introduction Request', 'the-admin-vault'),
    'enterprise'  => __('Enterprise Inquiry', 'the-admin-vault'),
    'admin_paid'  => __('Admin Paid Request', 'the-admin-vault'),
];
?>

<?php if (!$ccc_settings_embedded) : ?>
<div class="tav-page-header tav-email-settings-header">
    <p class="tav-breadcrumb"><?php esc_html_e('Settings', 'the-admin-vault'); ?> &rsaquo; <?php esc_html_e('Email Templates', 'the-admin-vault'); ?></p>
    <h1 class="tav-page-title"><?php esc_html_e('Customize Automated Email Copy', 'the-admin-vault'); ?></h1>
</div>
<?php endif; ?>

<div class="tav-email-settings-wrap<?php echo $ccc_settings_embedded ? ' tav-email-settings-embedded' : ''; ?>">
    <form method="POST" class="tav-email-settings-form" id="tav-email-settings-form">
        <?php wp_nonce_field('tav_save_settings', 'tav_settings_nonce'); ?>

        <!-- Hidden fields: all templates saved together -->
        <input type="hidden" name="tav_email_received_subject" id="tav_email_received_subject_h" value="<?php echo esc_attr($received_subject); ?>">
        <input type="hidden" name="tav_email_received_body" id="tav_email_received_body_h" value="<?php echo esc_attr($received_body); ?>">
        <input type="hidden" name="tav_email_fulfill_subject" id="tav_email_fulfill_subject_h" value="<?php echo esc_attr($fulfill_subject); ?>">
        <input type="hidden" name="tav_email_fulfill_body" id="tav_email_fulfill_body_h" value="<?php echo esc_attr($fulfill_body); ?>">
        <input type="hidden" name="tav_email_payment_subject" id="tav_email_payment_subject_h" value="<?php echo esc_attr($payment_subject); ?>">
        <input type="hidden" name="tav_email_payment_body" id="tav_email_payment_body_h" value="<?php echo esc_attr($payment_body); ?>">
        <input type="hidden" name="tav_email_reset_subject" id="tav_email_reset_subject_h" value="<?php echo esc_attr($reset_subject); ?>">
        <input type="hidden" name="tav_email_reset_body" id="tav_email_reset_body_h" value="<?php echo esc_attr($reset_body); ?>">
        <input type="hidden" name="tav_email_intro_subject" id="tav_email_intro_subject_h" value="<?php echo esc_attr($intro_subject); ?>">
        <input type="hidden" name="tav_email_intro_body" id="tav_email_intro_body_h" value="<?php echo esc_attr($intro_body); ?>">
        <input type="hidden" name="tav_email_enterprise_subject" id="tav_email_enterprise_subject_h" value="<?php echo esc_attr($enterprise_subject); ?>">
        <input type="hidden" name="tav_email_enterprise_body" id="tav_email_enterprise_body_h" value="<?php echo esc_attr($enterprise_body); ?>">
        <input type="hidden" name="tav_email_admin_paid_subject" id="tav_email_admin_paid_subject_h" value="<?php echo esc_attr($admin_paid_subject); ?>">
        <input type="hidden" name="tav_email_admin_paid_body" id="tav_email_admin_paid_body_h" value="<?php echo esc_attr($admin_paid_body); ?>">

        <!-- Pill tabs -->
        <div class="tav-email-pill-tabs" role="tablist">
            <?php foreach ($tab_labels as $slug => $label) : ?>
                <button type="button"
                        class="tav-email-pill-tab <?php echo $active_tab === $slug ? 'is-active' : ''; ?>"
                        data-tab="<?php echo esc_attr($slug); ?>"
                        role="tab"
                        aria-selected="<?php echo $active_tab === $slug ? 'true' : 'false'; ?>">
                    <?php echo esc_html($label); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="tav-email-settings-card">
            <!-- Dynamic variables -->
            <div class="tav-email-variables">
                <h3 class="tav-email-variables-title"><?php esc_html_e('Dynamic Content Variables (Click to Copy):', 'the-admin-vault'); ?></h3>
                <p class="tav-email-variables-desc"><?php esc_html_e('Use these placeholders in your Subject or Body. They will be automatically replaced with live data.', 'the-admin-vault'); ?></p>
                <div class="tav-email-variable-chips">
                    <?php foreach ($placeholders as $ph) : ?>
                        <button type="button" class="tav-email-chip" data-copy="<?php echo esc_attr($ph); ?>"><?php echo esc_html($ph); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab panels -->
            <?php
            $panels = [
                'received' => [
                    'subject_id' => 'tav_email_received_subject',
                    'body_id'    => 'tav_email_received_body',
                    'subject'    => $received_subject,
                    'body'       => $received_body,
                    'default_subject' => $defaults['received_subject'],
                    'default_body'    => $defaults['received_body'],
                    'restore'    => true,
                ],
                'fulfill' => [
                    'subject_id' => 'tav_email_fulfill_subject',
                    'body_id'    => 'tav_email_fulfill_body',
                    'subject'    => $fulfill_subject,
                    'body'       => $fulfill_body,
                    'default_subject' => $defaults['fulfill_subject'],
                    'default_body'    => $defaults['fulfill_body'],
                    'restore'    => false,
                ],
                'payment' => [
                    'subject_id' => 'tav_email_payment_subject',
                    'body_id'    => 'tav_email_payment_body',
                    'subject'    => $payment_subject,
                    'body'       => $payment_body,
                    'default_subject' => $defaults['payment_subject'],
                    'default_body'    => $defaults['payment_body'],
                    'restore'    => false,
                ],
                'reset' => [
                    'subject_id' => 'tav_email_reset_subject',
                    'body_id'    => 'tav_email_reset_body',
                    'subject'    => $reset_subject,
                    'body'       => $reset_body,
                    'default_subject' => $defaults['reset_subject'],
                    'default_body'    => $defaults['reset_body'],
                    'restore'    => false,
                ],
                'intro' => [
                    'subject_id' => 'tav_email_intro_subject',
                    'body_id'    => 'tav_email_intro_body',
                    'subject'    => $intro_subject,
                    'body'       => $intro_body,
                    'default_subject' => $defaults['intro_subject'],
                    'default_body'    => $defaults['intro_body'],
                    'restore'    => false,
                ],
                'enterprise' => [
                    'subject_id' => 'tav_email_enterprise_subject',
                    'body_id'    => 'tav_email_enterprise_body',
                    'subject'    => $enterprise_subject,
                    'body'       => $enterprise_body,
                    'default_subject' => $defaults['enterprise_subject'],
                    'default_body'    => $defaults['enterprise_body'],
                    'restore'    => false,
                ],
                'admin_paid' => [
                    'subject_id' => 'tav_email_admin_paid_subject',
                    'body_id'    => 'tav_email_admin_paid_body',
                    'subject'    => $admin_paid_subject,
                    'body'       => $admin_paid_body,
                    'default_subject' => $defaults['admin_paid_subject'],
                    'default_body'    => $defaults['admin_paid_body'],
                    'restore'    => false,
                ],
            ];

            foreach ($panels as $slug => $panel) :
                ?>
                <div class="tav-email-tab-panel <?php echo $active_tab === $slug ? 'is-active' : ''; ?>"
                     id="tav-email-panel-<?php echo esc_attr($slug); ?>"
                     data-tab="<?php echo esc_attr($slug); ?>"
                     role="tabpanel"
                     <?php echo $active_tab !== $slug ? 'hidden' : ''; ?>>

                    <div class="tav-email-field">
                        <label class="tav-email-field-label" for="<?php echo esc_attr($panel['subject_id']); ?>_v">
                            <?php esc_html_e('Subject Line', 'the-admin-vault'); ?>
                        </label>
                        <input type="text"
                               class="tav-email-input tav-email-subject-input"
                               id="<?php echo esc_attr($panel['subject_id']); ?>_v"
                               data-field="<?php echo esc_attr($panel['subject_id']); ?>"
                               data-default="<?php echo esc_attr($panel['default_subject']); ?>"
                               value="<?php echo esc_attr($panel['subject']); ?>">
                    </div>

                    <div class="tav-email-field">
                        <label class="tav-email-field-label" for="<?php echo esc_attr($panel['body_id']); ?>_v">
                            <?php esc_html_e('Email Body', 'the-admin-vault'); ?>
                        </label>
                        <p class="tav-email-field-hint"><?php esc_html_e('Supports custom text, HTML, and basic Markdown for formatting.', 'the-admin-vault'); ?></p>
                        <textarea class="tav-email-textarea tav-email-body-input"
                                  id="<?php echo esc_attr($panel['body_id']); ?>_v"
                                  data-field="<?php echo esc_attr($panel['body_id']); ?>"
                                  data-default="<?php echo esc_attr($panel['default_body']); ?>"
                                  rows="12"><?php echo esc_textarea($panel['body']); ?></textarea>
                    </div>

                    <div class="tav-email-settings-footer">
                        <?php if ($panel['restore']) : ?>
                            <button type="button" class="tav-btn tav-btn-outline tav-email-restore-btn" data-tab="<?php echo esc_attr($slug); ?>">
                                <?php esc_html_e('Restore Template', 'the-admin-vault'); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="tav-btn tav-btn-outline tav-email-preview-btn"
                                data-subject="<?php echo esc_attr($panel['subject_id']); ?>_v"
                                data-body="<?php echo esc_attr($panel['body_id']); ?>_v">
                            <?php esc_html_e('Preview Template', 'the-admin-vault'); ?>
                        </button>
                        <button type="submit" name="tav_settings_save" class="tav-btn tav-btn-primary">
                            <?php esc_html_e('Save Changes', 'the-admin-vault'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$ccc_settings_embedded && function_exists('tav_get_dashboard_view_url')) : ?>
            <p class="tav-email-settings-meta">
                <a href="<?php echo esc_url(tav_get_dashboard_view_url('niches')); ?>"><?php esc_html_e('Manage storyteller niches', 'the-admin-vault'); ?></a>
                ·
                <a href="<?php echo esc_url(tav_get_dashboard_view_url('pricing')); ?>"><?php esc_html_e('Manage package pricing', 'the-admin-vault'); ?></a>
            </p>
        <?php endif; ?>
    </form>
</div>

<!-- Preview modal -->
<div id="tav-preview-modal" class="tav-email-preview-modal" aria-modal="true" role="dialog" hidden>
    <div class="tav-email-preview-dialog">
        <button type="button" id="tav-preview-close" class="tav-email-preview-close" aria-label="<?php esc_attr_e('Close', 'the-admin-vault'); ?>">&times;</button>
        <h2 class="tav-email-preview-heading"><?php esc_html_e('Email Preview', 'the-admin-vault'); ?></h2>
        <div class="tav-email-preview-meta">
            <p><strong><?php esc_html_e('To:', 'the-admin-vault'); ?></strong> Jane Doe (Client) &lt;client@example.org&gt;</p>
            <p><strong><?php esc_html_e('From:', 'the-admin-vault'); ?></strong> Verified Storytellers &lt;noreply@verifiedstorytellers.com&gt;</p>
            <p><strong><?php esc_html_e('Subject:', 'the-admin-vault'); ?></strong> <span id="tav-preview-subject"></span></p>
        </div>
        <div id="tav-preview-body" class="tav-email-preview-body"></div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var PREVIEW_DATA = {
        '{{client_name}}': 'Jane Doe (Client)',
        '{{user_name}}': 'Jane Doe (Client)',
        '{{project_name}}': 'Sample Campaign',
        '{{request_id}}': 'XYZ-12345',
        '{{package}}': 'Custom Search',
        '{{total_amount}}': '$600.00',
        '{{delivery}}': 'June 3, 2026',
        '{{platform_url}}': <?php echo wp_json_encode($preview_dashboard); ?>,
        '{{reset_link}}': <?php echo wp_json_encode($preview_reset_url); ?>,
        '{{site_name}}': <?php echo wp_json_encode($preview_site_name); ?>,
        '{{storyteller_list}}': '  • Sarah Miller\n  • David Chen\n  • Elena Kovac',
        '{{client_email}}': 'client@example.org',
        '{{admin_url}}': <?php echo wp_json_encode(admin_url('admin.php?page=tav-dashboard&view=requests')); ?>,
        '{{order_url}}': <?php echo wp_json_encode(admin_url('post.php?post=123&action=edit')); ?>,
        '{{fulfill_url}}': <?php echo wp_json_encode(admin_url('admin.php?page=tav-dashboard&view=fulfill&request_id=123')); ?>,
    };

    var form = document.getElementById('tav-email-settings-form');
    if (!form) return;

    function syncHiddenFields() {
        form.querySelectorAll('.tav-email-subject-input, .tav-email-body-input').forEach(function (el) {
            var hidden = document.getElementById(el.dataset.field + '_h');
            if (hidden) hidden.value = el.value;
        });
    }

    function applySubstitutions(text) {
        Object.keys(PREVIEW_DATA).forEach(function (key) {
            text = text.split(key).join(PREVIEW_DATA[key]);
        });
        return text;
    }

    /* Tab switching */
    var pillTabs = form.querySelectorAll('.tav-email-pill-tab');
    var panels = form.querySelectorAll('.tav-email-tab-panel');

    pillTabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            syncHiddenFields();
            var tab = btn.dataset.tab;
            pillTabs.forEach(function (b) {
                b.classList.remove('is-active');
                b.setAttribute('aria-selected', 'false');
            });
            panels.forEach(function (p) {
                p.classList.remove('is-active');
                p.hidden = true;
            });
            btn.classList.add('is-active');
            btn.setAttribute('aria-selected', 'true');
            var panel = document.getElementById('tav-email-panel-' + tab);
            if (panel) {
                panel.classList.add('is-active');
                panel.hidden = false;
            }
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('email_tab', tab);
                window.history.replaceState({}, '', url.toString());
            }
        });
    });

    /* Copy chips */
    form.querySelectorAll('.tav-email-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var text = chip.dataset.copy;
            if (!text) return;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            }
            chip.classList.add('is-copied');
            setTimeout(function () { chip.classList.remove('is-copied'); }, 600);
        });
    });

    /* Restore template */
    form.querySelectorAll('.tav-email-restore-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panel = document.getElementById('tav-email-panel-' + btn.dataset.tab);
            if (!panel) return;
            panel.querySelectorAll('[data-default]').forEach(function (el) {
                el.value = el.dataset.default || '';
            });
            syncHiddenFields();
        });
    });

    /* Preview */
    var modal = document.getElementById('tav-preview-modal');
    var closeBtn = document.getElementById('tav-preview-close');

    function openPreview(subjectId, bodyId) {
        var subjectEl = document.getElementById(subjectId);
        var bodyEl = document.getElementById(bodyId);
        if (!subjectEl || !bodyEl || !modal) return;
        document.getElementById('tav-preview-subject').textContent = applySubstitutions(subjectEl.value);
        var bodyOut = document.getElementById('tav-preview-body');
        bodyOut.textContent = applySubstitutions(bodyEl.value);
        bodyOut.innerHTML = bodyOut.innerHTML.replace(/\n/g, '<br>');
        modal.hidden = false;
    }

    form.querySelectorAll('.tav-email-preview-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPreview(btn.dataset.subject, btn.dataset.body);
        });
    });

    if (closeBtn && modal) {
        closeBtn.addEventListener('click', function () { modal.hidden = true; });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.hidden = true;
        });
    }

    form.addEventListener('submit', syncHiddenFields);
    form.querySelectorAll('.tav-email-subject-input, .tav-email-body-input').forEach(function (el) {
        el.addEventListener('input', syncHiddenFields);
    });
}());
</script>
