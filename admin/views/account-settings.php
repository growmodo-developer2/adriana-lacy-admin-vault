<?php
/**
 * Operator account settings inside the admin dashboard (Figma layout).
 *
 * @package TheAdminVault
 */

defined('ABSPATH') || exit;

$settings_tab = sanitize_key($_GET['tab'] ?? 'profile');
$allowed = ['profile', 'password'];
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
?>
<div class="tav-page-header tav-account-settings-header">
    <p class="tav-breadcrumb"><?php esc_html_e('Account', 'the-admin-vault'); ?> &rsaquo; <?php echo esc_html($tabs[$settings_tab]); ?></p>
    <h1 class="tav-page-title"><?php esc_html_e('Account Settings', 'client-command-center'); ?></h1>
    <p class="tav-page-subtitle"><?php esc_html_e('Manage your account preferences and security here.', 'client-command-center'); ?></p>
</div>

<div class="tav-admin-account-wrap">
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

    <div class="tav-admin-account-content">
        <?php if ($settings_tab === 'profile') : ?>
            <h2 class="tav-admin-account-panel-title"><?php esc_html_e('Profile Information', 'client-command-center'); ?></h2>
            <p class="tav-admin-account-panel-desc"><?php esc_html_e('Manage your name, email, and avatar.', 'client-command-center'); ?></p>
            <?php
            $user = wp_get_current_user();
            if ($user->ID) :
                ?>
                <?php
                if (function_exists('ccc_render_profile_avatar_upload')) {
                    ccc_render_profile_avatar_upload();
                }
                ?>
                <div class="tav-admin-profile-summary">
                    <div class="tav-admin-profile-avatar"><?php echo get_avatar($user->ID, 72, '', '', ['class' => 'ccc-avatar-img']); ?></div>
                    <div>
                        <strong><?php echo esc_html($user->display_name); ?></strong>
                        <span class="tav-admin-profile-handle">@<?php echo esc_html($user->user_login); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <h2 class="tav-admin-account-panel-title"><?php esc_html_e('Password Settings', 'client-command-center'); ?></h2>
            <p class="tav-admin-account-panel-desc"><?php esc_html_e('Update your password and manage security settings.', 'client-command-center'); ?></p>
        <?php endif; ?>

        <?php if (function_exists('UM') && is_object(UM()->account())) : ?>
            <div class="um um-account tav-admin-um-account">
                <div class="um-form">
                    <form method="post" action="">
                        <?php
                        $args = ['tab' => $active_um_tab];
                        do_action('um_account_page_hidden_fields', $args);
                        ?>
                        <div class="um-account-main" data-current_tab="<?php echo esc_attr($active_um_tab); ?>">
                            <?php
                            foreach (UM()->account()->tabs as $id => $info) :
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
                                    UM()->account()->render_account_tab($id, $info, $args);
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="um-clear"></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
