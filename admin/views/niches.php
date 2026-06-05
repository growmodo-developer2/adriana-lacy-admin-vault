<?php
/**
 * Niche management — separate platform settings view.
 *
 * @package TheAdminVault
 */

defined('ABSPATH') || exit;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['tav_niches_nonce'])
    && wp_verify_nonce($_POST['tav_niches_nonce'], 'tav_save_niches')
    && (current_user_can('edit_storytellers') || current_user_can('manage_options'))
) {
    if (isset($_POST['tav_niches_list'])) {
        update_option('tav_niches_list', wp_kses_post(stripslashes($_POST['tav_niches_list'])));
    }
    echo '<div class="tav-settings-notice tav-settings-notice-success"><p>'
        . esc_html__('Niches saved.', 'the-admin-vault')
        . '</p></div>';
}

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
?>
<div class="tav-page-header">
    <p class="tav-breadcrumb"><?php esc_html_e('Settings', 'the-admin-vault'); ?> &rsaquo; <?php esc_html_e('Niche Management', 'the-admin-vault'); ?></p>
    <h1 class="tav-page-title"><?php esc_html_e('Niche Management', 'the-admin-vault'); ?></h1>
    <p class="tav-page-subtitle"><?php esc_html_e('Manage the list of niches available for storytellers.', 'the-admin-vault'); ?></p>
</div>

<div class="tav-email-settings-card">
    <form method="POST" class="tav-email-settings-form">
        <?php wp_nonce_field('tav_save_niches', 'tav_niches_nonce'); ?>

        <div class="tav-email-field">
            <label class="tav-email-field-label" for="tav_niches_list"><?php esc_html_e('Niches', 'the-admin-vault'); ?></label>
            <p class="tav-email-field-hint"><?php esc_html_e('Enter one niche per line. Format: "slug : Label" or just "Label".', 'the-admin-vault'); ?></p>
            <textarea name="tav_niches_list" id="tav_niches_list" rows="10" class="tav-email-textarea"><?php echo esc_textarea($niches_display); ?></textarea>
        </div>

        <div class="tav-email-settings-footer">
            <a href="<?php echo esc_url(tav_get_dashboard_view_url('settings')); ?>" class="tav-btn tav-btn-outline">
                <?php esc_html_e('Email Templates', 'the-admin-vault'); ?>
            </a>
            <a href="<?php echo esc_url(tav_get_dashboard_view_url('pricing')); ?>" class="tav-btn tav-btn-outline">
                <?php esc_html_e('Package Pricing', 'the-admin-vault'); ?>
            </a>
            <button type="submit" class="tav-btn tav-btn-primary"><?php esc_html_e('Save Changes', 'the-admin-vault'); ?></button>
        </div>
    </form>
</div>
