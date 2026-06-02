<?php
/**
 * Front-end Admin Portal — operator dashboard at /admin-dashboard/
 *
 * @package TheAdminVault
 */

defined('ABSPATH') || exit;

function tav_is_operator(): bool
{
    if (!is_user_logged_in()) {
        return false;
    }

    return current_user_can('edit_storytellers') || current_user_can('manage_options');
}

function tav_get_admin_portal_url(): string
{
    $page = get_page_by_path('admin-dashboard');
    if ($page) {
        return get_permalink($page);
    }

    return home_url('/admin-dashboard/');
}

function tav_is_admin_portal_page(): bool
{
    if (is_singular('page')) {
        $post = get_queried_object();
        if ($post instanceof WP_Post && $post->post_name === 'admin-dashboard') {
            return true;
        }
    }

    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $home_path = trim((string) parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path !== '' && str_starts_with($path, $home_path . '/')) {
        $path = substr($path, strlen($home_path) + 1);
    } elseif ($home_path !== '' && $path === $home_path) {
        $path = '';
    }

    return $path === 'admin-dashboard';
}

function tav_enqueue_admin_portal_assets(): void
{
    wp_enqueue_style('dashicons');

    wp_enqueue_style(
        'tav-dashboard',
        TAV_PLUGIN_URL . 'assets/css/tav-dashboard.css',
        ['dashicons'],
        TAV_VERSION
    );

    wp_enqueue_style(
        'tav-admin-portal',
        TAV_PLUGIN_URL . 'assets/css/tav-admin-portal.css',
        ['tav-dashboard'],
        TAV_VERSION
    );

    wp_enqueue_style(
        'tav-google-fonts-portal',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        [],
        null
    );

    wp_enqueue_script(
        'chartjs',
        'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js',
        [],
        '4.4.0',
        true
    );

    wp_enqueue_script(
        'tav-dashboard',
        TAV_PLUGIN_URL . 'assets/js/tav-dashboard.js',
        ['jquery', 'chartjs'],
        TAV_VERSION,
        true
    );

    wp_localize_script('tav-dashboard', 'tavData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('tav_dashboard_nonce'),
        'chart'   => tav_get_revenue_chart_data('30days'),
    ]);

    wp_add_inline_script(
        'tav-dashboard',
        'var ajaxurl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';',
        'before'
    );
}

function tav_admin_dashboard_shortcode(): string
{
    if (!is_user_logged_in()) {
        $login = wp_login_url(tav_get_admin_portal_url());

        return '<div class="tav-admin-portal-message"><p>' .
            esc_html__('Please log in to access the admin dashboard.', 'the-admin-vault') .
            '</p><p><a class="tav-btn-sm tav-btn-primary" href="' . esc_url($login) . '">' .
            esc_html__('Log in', 'the-admin-vault') . '</a></p></div>';
    }

    if (!tav_is_operator()) {
        return '<div class="tav-admin-portal-message"><p>' .
            esc_html__('You do not have permission to view this dashboard.', 'the-admin-vault') .
            '</p></div>';
    }

    tav_enqueue_admin_portal_assets();

    $current_page_slug = 'tav-dashboard';
    $portal_url        = tav_get_admin_portal_url();

    ob_start();
    ?>
    <div class="tav-dashboard-wrap tav-front-portal">
        <main class="tav-main">
            <aside class="tav-sidebar">
                <div class="tav-sidebar-brand">
                    <div class="tav-sidebar-logo">VA</div>
                    <div class="tav-sidebar-brand-text">
                        <span class="tav-sidebar-brand-title"><?php esc_html_e('Admin Vault', 'the-admin-vault'); ?></span>
                        <span class="tav-sidebar-brand-sub"><?php esc_html_e('Storytellers', 'the-admin-vault'); ?></span>
                    </div>
                </div>

                <div class="tav-sidebar-toggle">
                    <button id="tav-sidebar-collapse" class="tav-btn-icon" type="button" title="<?php esc_attr_e('Toggle Sidebar', 'the-admin-vault'); ?>">
                        <span class="dashicons dashicons-menu"></span>
                    </button>
                </div>

                <ul class="tav-sidebar-nav">
                    <li>
                        <a href="<?php echo esc_url($portal_url); ?>" class="active">
                            <span class="dashicons dashicons-chart-area"></span>
                            <span><?php esc_html_e('Dashboard', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=storytellers')); ?>">
                            <span class="dashicons dashicons-groups"></span>
                            <span><?php esc_html_e('Storytellers', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=clients')); ?>">
                            <span class="dashicons dashicons-businessperson"></span>
                            <span><?php esc_html_e('Clients', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=requests')); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                            <span><?php esc_html_e('Requests', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=settings')); ?>">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <span><?php esc_html_e('Settings', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                </ul>
            </aside>

            <div class="tav-content">
                <?php require TAV_PLUGIN_DIR . 'admin/views/dashboard.php'; ?>
            </div>
        </main>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('tav_admin_dashboard', 'tav_admin_dashboard_shortcode');

function tav_ensure_admin_dashboard_page_content(): void
{
    $page = get_page_by_path('admin-dashboard');
    if (!$page instanceof WP_Post) {
        return;
    }

    $content      = (string) $page->post_content;
    $needs_update = !has_shortcode($content, 'tav_admin_dashboard');

    if ($needs_update) {
        $content = '[tav_admin_dashboard]';
    }

    if (get_post_meta($page->ID, '_elementor_edit_mode', true)) {
        delete_post_meta($page->ID, '_elementor_edit_mode');
        delete_post_meta($page->ID, '_elementor_data');
        delete_post_meta($page->ID, '_elementor_template_type');
        delete_post_meta($page->ID, '_elementor_page_settings');
        $needs_update = true;
    }

    if ($needs_update) {
        wp_update_post([
            'ID'           => $page->ID,
            'post_content' => $content,
        ]);
        clean_post_cache($page->ID);
    }
}

function tav_admin_portal_the_content(string $content): string
{
    if (!tav_is_admin_portal_page()) {
        return $content;
    }

    if (has_shortcode($content, 'tav_admin_dashboard')) {
        return do_shortcode($content);
    }

    return tav_admin_dashboard_shortcode();
}
add_filter('the_content', 'tav_admin_portal_the_content', 1);

function tav_admin_portal_hide_page_title(bool $show): bool
{
    if (tav_is_admin_portal_page()) {
        return false;
    }

    return $show;
}
add_filter('hello_elementor_page_title', 'tav_admin_portal_hide_page_title');

function tav_admin_portal_access_guard(): void
{
    if (!tav_is_admin_portal_page()) {
        return;
    }

    if (!is_user_logged_in()) {
        auth_redirect();
    }

    if (!tav_is_operator()) {
        if (function_exists('ccc_is_client') && ccc_is_client()) {
            $client_dashboard = get_page_by_path('client-dashboard');
            wp_safe_redirect($client_dashboard ? get_permalink($client_dashboard) : home_url('/client-dashboard/'));
        } else {
            wp_safe_redirect(home_url('/'));
        }
        exit;
    }
}
add_action('template_redirect', 'tav_admin_portal_access_guard', 1);

function tav_admin_portal_render_full_page(): void
{
    if (!tav_is_admin_portal_page()) {
        return;
    }

    tav_enqueue_admin_portal_assets();

    status_header(200);
    nocache_headers();

    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('tav-admin-portal-page'); ?>>
<?php wp_body_open(); ?>
<?php echo tav_admin_dashboard_shortcode(); ?>
<?php wp_footer(); ?>
</body>
</html>
    <?php
    exit;
}
add_action('template_redirect', 'tav_admin_portal_render_full_page', 2);

function tav_maybe_create_admin_dashboard_page(): void
{
    if (get_page_by_path('admin-dashboard')) {
        tav_ensure_admin_dashboard_page_content();
        return;
    }

    $page_id = wp_insert_post([
        'post_title'   => 'Admin Dashboard',
        'post_name'    => 'admin-dashboard',
        'post_content' => '[tav_admin_dashboard]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
    ], true);

    if (!is_wp_error($page_id) && $page_id) {
        flush_rewrite_rules(false);
    }
}
add_action('admin_init', 'tav_maybe_create_admin_dashboard_page');
add_action('init', 'tav_maybe_create_admin_dashboard_page', 20);
add_action('init', 'tav_ensure_admin_dashboard_page_content', 21);

function tav_admin_portal_404_bootstrap(): void
{
    if (!is_404()) {
        return;
    }

    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $home_path = trim((string) parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path !== '' && str_starts_with($path, $home_path . '/')) {
        $path = substr($path, strlen($home_path) + 1);
    }

    if ($path !== 'admin-dashboard') {
        return;
    }

    tav_maybe_create_admin_dashboard_page();

    if (get_page_by_path('admin-dashboard')) {
        wp_safe_redirect(tav_get_admin_portal_url());
        exit;
    }
}
add_action('template_redirect', 'tav_admin_portal_404_bootstrap', 0);
