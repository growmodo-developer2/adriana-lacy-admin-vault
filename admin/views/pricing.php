<?php
/**
 * Package pricing + contact FAQ settings.
 *
 * @package TheAdminVault
 */

defined('ABSPATH') || exit;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['tav_pricing_nonce'])
    && wp_verify_nonce($_POST['tav_pricing_nonce'], 'tav_save_pricing')
    && (current_user_can('edit_storytellers') || current_user_can('manage_options'))
    && function_exists('ccc_save_pricing_config')
) {
    ccc_save_pricing_config(wp_unslash($_POST['ccc_pricing'] ?? []));
    echo '<div class="tav-settings-notice tav-settings-notice-success"><p>'
        . esc_html__('Pricing and FAQ settings saved. WooCommerce product prices were synced.', 'the-admin-vault')
        . '</p></div>';
}

if (!function_exists('ccc_get_pricing') || !function_exists('ccc_get_contact_faq_items')) {
    echo '<div class="tav-settings-notice"><p>' . esc_html__('Client Command Center must be active to manage pricing.', 'the-admin-vault') . '</p></div>';
    return;
}

$pricing    = ccc_get_pricing();
$faq_items  = ccc_get_contact_faq_items();
$faq_items[] = ['question' => '', 'answer' => ''];
?>
<div class="tav-page-header">
    <p class="tav-breadcrumb"><?php esc_html_e('Settings', 'the-admin-vault'); ?> &rsaquo; <?php esc_html_e('Pricing', 'the-admin-vault'); ?></p>
    <h1 class="tav-page-title"><?php esc_html_e('Package Pricing', 'the-admin-vault'); ?></h1>
    <p class="tav-page-subtitle"><?php esc_html_e('Edit tier prices and copy shown on the public pricing page and new request form. Changes sync to WooCommerce products automatically.', 'the-admin-vault'); ?></p>
</div>

<div class="tav-email-settings-card">
    <form method="POST" class="tav-email-settings-form">
        <?php wp_nonce_field('tav_save_pricing', 'tav_pricing_nonce'); ?>

        <h2 class="tav-admin-account-panel-title"><?php esc_html_e('Package Tiers', 'the-admin-vault'); ?></h2>
        <?php foreach ($pricing['tiers'] as $key => $tier) : ?>
            <div class="tav-pricing-tier-block">
                <h3><?php echo esc_html($tier['label']); ?> <code><?php echo esc_html($key); ?></code></h3>
                <div class="tav-pricing-grid">
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Label', 'the-admin-vault'); ?></label>
                        <input type="text" class="tav-email-input" name="ccc_pricing[tiers][<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($tier['label']); ?>">
                    </div>
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Price (USD)', 'the-admin-vault'); ?></label>
                        <input type="number" min="0" step="1" class="tav-email-input" name="ccc_pricing[tiers][<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr((string) (int) ($tier['price'] ?? 0)); ?>">
                    </div>
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Badge', 'the-admin-vault'); ?></label>
                        <input type="text" class="tav-email-input" name="ccc_pricing[tiers][<?php echo esc_attr($key); ?>][badge]" value="<?php echo esc_attr($tier['badge'] ?? ''); ?>">
                    </div>
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Price suffix', 'the-admin-vault'); ?></label>
                        <input type="text" class="tav-email-input" name="ccc_pricing[tiers][<?php echo esc_attr($key); ?>][price_suffix]" value="<?php echo esc_attr($tier['price_suffix'] ?? ''); ?>">
                    </div>
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Storytellers', 'the-admin-vault'); ?></label>
                        <input type="text" class="tav-email-input" name="ccc_pricing[tiers][<?php echo esc_attr($key); ?>][storytellers]" value="<?php echo esc_attr($tier['storytellers'] ?? ''); ?>">
                    </div>
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Delivery', 'the-admin-vault'); ?></label>
                        <input type="text" class="tav-email-input" name="ccc_pricing[tiers][<?php echo esc_attr($key); ?>][delivery]" value="<?php echo esc_attr($tier['delivery'] ?? ''); ?>">
                    </div>
                </div>
                <div class="tav-email-field">
                    <label class="tav-email-field-label">
                        <input type="checkbox" name="ccc_pricing[tiers][<?php echo esc_attr($key); ?>][recommended]" value="1" <?php checked(!empty($tier['recommended'])); ?>>
                        <?php esc_html_e('Mark as Most Popular', 'the-admin-vault'); ?>
                    </label>
                </div>
                <div class="tav-email-field">
                    <label class="tav-email-field-label" for="tier_features_<?php echo esc_attr($key); ?>"><?php esc_html_e('Features (one per line)', 'the-admin-vault'); ?></label>
                    <textarea class="tav-email-textarea" id="tier_features_<?php echo esc_attr($key); ?>" name="ccc_pricing[tiers][<?php echo esc_attr($key); ?>][features]" rows="5"><?php echo esc_textarea(implode("\n", $tier['features'] ?? [])); ?></textarea>
                </div>
            </div>
        <?php endforeach; ?>

        <h2 class="tav-admin-account-panel-title"><?php esc_html_e('Add-ons', 'the-admin-vault'); ?></h2>
        <div class="tav-pricing-grid">
            <?php foreach ($pricing['addons'] as $key => $addon) : ?>
                <div class="tav-pricing-tier-block">
                    <h3><?php echo esc_html($addon['label']); ?> <code><?php echo esc_html($key); ?></code></h3>
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Label', 'the-admin-vault'); ?></label>
                        <input type="text" class="tav-email-input" name="ccc_pricing[addons][<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($addon['label']); ?>">
                    </div>
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Description', 'the-admin-vault'); ?></label>
                        <input type="text" class="tav-email-input" name="ccc_pricing[addons][<?php echo esc_attr($key); ?>][desc]" value="<?php echo esc_attr($addon['desc'] ?? ''); ?>">
                    </div>
                    <div class="tav-email-field">
                        <label class="tav-email-field-label"><?php esc_html_e('Price (USD)', 'the-admin-vault'); ?></label>
                        <input type="number" min="0" step="1" class="tav-email-input" name="ccc_pricing[addons][<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr((string) (int) ($addon['price'] ?? 0)); ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 class="tav-admin-account-panel-title"><?php esc_html_e('Contact Page FAQ', 'the-admin-vault'); ?></h2>
        <p class="tav-page-subtitle"><?php esc_html_e('Shown below the contact form on /contact/. Leave a row blank to skip it.', 'the-admin-vault'); ?></p>
        <?php foreach ($faq_items as $index => $item) : ?>
            <div class="tav-pricing-tier-block">
                <div class="tav-email-field">
                    <label class="tav-email-field-label"><?php esc_html_e('Question', 'the-admin-vault'); ?></label>
                    <input type="text" class="tav-email-input" name="ccc_pricing[faq][<?php echo (int) $index; ?>][question]" value="<?php echo esc_attr($item['question'] ?? ''); ?>">
                </div>
                <div class="tav-email-field">
                    <label class="tav-email-field-label"><?php esc_html_e('Answer', 'the-admin-vault'); ?></label>
                    <textarea class="tav-email-textarea" name="ccc_pricing[faq][<?php echo (int) $index; ?>][answer]" rows="3"><?php echo esc_textarea($item['answer'] ?? ''); ?></textarea>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="tav-email-settings-footer">
            <a href="<?php echo esc_url(tav_get_dashboard_view_url('settings')); ?>" class="tav-btn tav-btn-outline">
                <?php esc_html_e('Back to Email Templates', 'the-admin-vault'); ?>
            </a>
            <button type="submit" class="tav-btn tav-btn-primary"><?php esc_html_e('Save Changes', 'the-admin-vault'); ?></button>
        </div>
    </form>
</div>

<style>
.tav-pricing-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
.tav-pricing-tier-block { border-top:1px solid #e2e8f0; padding-top:20px; margin-top:20px; }
.tav-pricing-tier-block h3 { margin:0 0 12px; font-size:16px; }
</style>
