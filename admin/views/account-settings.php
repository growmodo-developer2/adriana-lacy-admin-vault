<?php
/**
 * Operator account settings inside the admin dashboard.
 *
 * @package TheAdminVault
 */

defined('ABSPATH') || exit;

$settings_tab = sanitize_key($_GET['tab'] ?? 'profile');
$allowed      = ['profile', 'password'];
if (!in_array($settings_tab, $allowed, true)) {
    $settings_tab = 'profile';
}

$um_tab_map    = ['profile' => 'general', 'password' => 'password'];
$active_um_tab = $um_tab_map[$settings_tab] ?? 'general';

$tabs = [
    'profile'  => __('My Profile', 'client-command-center'),
    'password' => __('Password', 'client-command-center'),
];

$base_url = tav_get_dashboard_view_url('account-settings');
$um_args  = tav_prepare_um_account_tab($active_um_tab);
$um_tabs  = tav_get_um_account_tabs();

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['tav_native_account_nonce'])
    && wp_verify_nonce($_POST['tav_native_account_nonce'], 'tav_native_account_save')
) {
    $user_id = get_current_user_id();
    if ($user_id && $settings_tab === 'profile') {
        $update = ['ID' => $user_id];

        if (isset($_POST['first_name'])) {
            $update['first_name'] = sanitize_text_field(wp_unslash($_POST['first_name']));
        }
        if (isset($_POST['last_name'])) {
            $update['last_name'] = sanitize_text_field(wp_unslash($_POST['last_name']));
        }
        if (isset($_POST['display_name'])) {
            $update['display_name'] = sanitize_text_field(wp_unslash($_POST['display_name']));
        }
        if (isset($_POST['user_email'])) {
            $email = sanitize_email(wp_unslash($_POST['user_email']));
            if ($email && !email_exists($email) || email_exists($email) === $user_id) {
                $update['user_email'] = $email;
            }
        }

        $result = wp_update_user($update);
        if (!is_wp_error($result)) {
            echo '<div class="tav-settings-notice tav-settings-notice-success"><p>'
                . esc_html__('Profile updated.', 'the-admin-vault')
                . '</p></div>';
        }
    }

    if ($user_id && $settings_tab === 'password') {
        $current = (string) ($_POST['current_user_password'] ?? '');
        $new     = (string) ($_POST['user_password'] ?? '');
        $confirm = (string) ($_POST['confirm_user_password'] ?? '');
        $user    = get_userdata($user_id);

        if ($user && wp_check_password($current, $user->user_pass, $user_id)) {
            if ($new !== '' && $new === $confirm) {
                wp_set_password($new, $user_id);
                wp_set_auth_cookie($user_id);
                echo '<div class="tav-settings-notice tav-settings-notice-success"><p>'
                    . esc_html__('Password updated.', 'the-admin-vault')
                    . '</p></div>';
            } else {
                echo '<div class="tav-settings-notice tav-settings-notice-error"><p>'
                    . esc_html__('New passwords do not match.', 'the-admin-vault')
                    . '</p></div>';
            }
        } else {
            echo '<div class="tav-settings-notice tav-settings-notice-error"><p>'
                . esc_html__('Current password is incorrect.', 'the-admin-vault')
                . '</p></div>';
        }
    }
}
?>
<div class="tav-page-header tav-account-settings-header">
    <p class="tav-breadcrumb"><?php esc_html_e('Account', 'the-admin-vault'); ?> &rsaquo; <?php echo esc_html($tabs[$settings_tab]); ?></p>
    <h1 class="tav-page-title"><?php esc_html_e('Account Settings', 'client-command-center'); ?></h1>
    <p class="tav-page-subtitle"><?php esc_html_e('Manage your account preferences and security here.', 'client-command-center'); ?></p>
</div>

<div class="tav-admin-account-wrap ccc-admin-account-settings">
    <aside class="tav-admin-account-sidebar" aria-label="<?php esc_attr_e('Account settings navigation', 'client-command-center'); ?>">
        <nav class="tav-admin-account-nav">
            <?php foreach ($tabs as $slug => $label) : ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $slug, $base_url)); ?>"
                   class="tav-admin-account-tab <?php echo $settings_tab === $slug ? 'is-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <a href="<?php echo esc_url(wp_logout_url(tav_get_admin_portal_url())); ?>" class="tav-admin-account-signout">
            <?php esc_html_e('Sign Out', 'client-command-center'); ?>
        </a>
    </aside>

    <div class="tav-admin-account-content ccc-account-content">
        <?php if ($settings_tab === 'profile') : ?>
            <?php
            if (function_exists('ccc_render_settings_section_head')) {
                ccc_render_settings_section_head(
                    __('Profile Information', 'client-command-center'),
                    __('Manage your name, email, and avatar.', 'client-command-center')
                );
            } else {
                ?>
                <h2 class="tav-admin-account-panel-title"><?php esc_html_e('Profile Information', 'client-command-center'); ?></h2>
                <p class="tav-admin-account-panel-desc"><?php esc_html_e('Manage your name, email, and avatar.', 'client-command-center'); ?></p>
                <?php
            }

            if (function_exists('ccc_render_profile_avatar_upload')) {
                ccc_render_profile_avatar_upload();
            }

            if (function_exists('ccc_render_profile_summary_row')) {
                ccc_render_profile_summary_row();
            }
            ?>
        <?php else : ?>
            <?php
            if (function_exists('ccc_render_settings_section_head')) {
                ccc_render_settings_section_head(
                    __('Password Settings', 'client-command-center'),
                    __('Update your password and manage security settings.', 'client-command-center')
                );
            } else {
                ?>
                <h2 class="tav-admin-account-panel-title"><?php esc_html_e('Password Settings', 'client-command-center'); ?></h2>
                <p class="tav-admin-account-panel-desc"><?php esc_html_e('Update your password and manage security settings.', 'client-command-center'); ?></p>
                <?php
            }
            ?>
        <?php endif; ?>

        <?php if (!empty($um_tabs)) : ?>
            <div class="um um-account tav-admin-um-account">
                <div class="um-form">
                    <form method="post" action="">
                        <?php do_action('um_account_page_hidden_fields', $um_args); ?>
                        <div class="um-account-main" data-current_tab="<?php echo esc_attr($active_um_tab); ?>">
                            <?php
                            foreach ($um_tabs as $id => $info) {
                                if ($id !== $active_um_tab) {
                                    continue;
                                }

                                $tab_enabled = UM()->options()->get('account_tab_' . $id);
                                if (!isset($info['custom']) && empty($tab_enabled) && 'general' !== $id) {
                                    continue;
                                }
                                ?>
                                <div class="um-account-tab um-account-tab-<?php echo esc_attr($id); ?>" data-tab="<?php echo esc_attr($id); ?>">
                                    <?php
                                    $info['with_header'] = false;
                                    UM()->account()->render_account_tab($id, $info, $um_args);
                                    ?>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <div class="um-clear"></div>
                    </form>
                </div>
            </div>
        <?php else :
            $user = wp_get_current_user();
            ?>
            <form method="post" class="tav-native-account-form">
                <?php wp_nonce_field('tav_native_account_save', 'tav_native_account_nonce'); ?>

                <?php if ($settings_tab === 'profile') : ?>
                    <div class="tav-native-field">
                        <label for="tav-first-name"><?php esc_html_e('First Name', 'the-admin-vault'); ?></label>
                        <input type="text" id="tav-first-name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>">
                    </div>
                    <div class="tav-native-field">
                        <label for="tav-last-name"><?php esc_html_e('Last Name', 'the-admin-vault'); ?></label>
                        <input type="text" id="tav-last-name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>">
                    </div>
                    <div class="tav-native-field">
                        <label for="tav-display-name"><?php esc_html_e('Display Name', 'the-admin-vault'); ?></label>
                        <input type="text" id="tav-display-name" name="display_name" value="<?php echo esc_attr($user->display_name); ?>">
                    </div>
                    <div class="tav-native-field">
                        <label for="tav-user-email"><?php esc_html_e('Email Address', 'the-admin-vault'); ?></label>
                        <input type="email" id="tav-user-email" name="user_email" value="<?php echo esc_attr($user->user_email); ?>">
                    </div>
                <?php else : ?>
                    <div class="tav-native-field">
                        <label for="tav-current-password"><?php esc_html_e('Current Password', 'the-admin-vault'); ?></label>
                        <input type="password" id="tav-current-password" name="current_user_password" autocomplete="current-password">
                    </div>
                    <div class="tav-native-field">
                        <label for="tav-new-password"><?php esc_html_e('New Password', 'the-admin-vault'); ?></label>
                        <input type="password" id="tav-new-password" name="user_password" autocomplete="new-password">
                    </div>
                    <div class="tav-native-field">
                        <label for="tav-confirm-password"><?php esc_html_e('Confirm New Password', 'the-admin-vault'); ?></label>
                        <input type="password" id="tav-confirm-password" name="confirm_user_password" autocomplete="new-password">
                    </div>
                <?php endif; ?>

                <div class="tav-native-form-actions">
                    <button type="submit" class="tav-btn tav-btn-primary">
                        <?php echo $settings_tab === 'password'
                            ? esc_html__('Update Password', 'the-admin-vault')
                            : esc_html__('Save Changes', 'the-admin-vault'); ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var editBtn = document.getElementById('ccc-profile-edit-btn');
    if (!editBtn) return;
    editBtn.addEventListener('click', function () {
        var first = document.querySelector('.tav-admin-account-content input[type="text"], .tav-admin-account-content input[type="email"]');
        if (first) {
            first.focus();
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();
</script>
