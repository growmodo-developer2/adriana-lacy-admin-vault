<?php
/**
 * The Admin Vault — Dashboard Page
 *
 * Renders the custom admin dashboard with real data and dynamically loads views.
 *
 * @package TheAdminVault
 */

defined('ABSPATH') || exit;

/*--------------------------------------------------------------
 * Register the Dashboard admin page & enqueue assets
 *------------------------------------------------------------*/
add_action('admin_menu', 'tav_register_dashboard_page');

function tav_register_dashboard_page(): void
{
    $hook = add_menu_page(
        __('Storytellers Dashboard', 'the-admin-vault'),
        __('Admin Vault', 'the-admin-vault'),
        'edit_storytellers',
        'tav-dashboard',
        'tav_render_dashboard',
        'dashicons-shield-alt',
        3
    );

    // Ensure Dashboard is the first item in the submenu
    add_submenu_page(
        'tav-dashboard',
        __('Dashboard', 'the-admin-vault'),
        __('Dashboard', 'the-admin-vault'),
        'edit_storytellers',
        'tav-dashboard',
        'tav_render_dashboard'
    );

    add_action("load-{$hook}", 'tav_dashboard_on_load');
}

function tav_dashboard_on_load(): void
{
    // Handle ACF form submission
    if (function_exists('acf_form_head')) {
        acf_form_head();
    }

    // Handle Fulfillment Form Submission (Save & Notify)
    if (isset($_GET['view']) && $_GET['view'] === 'fulfill' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tav_fulfill_nonce']) && wp_verify_nonce($_POST['tav_fulfill_nonce'], 'tav_fulfill_action')) {
        $debug_log = ABSPATH . 'tav_debug.log';
        file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Fulfillment POST reached for ID " . (isset($_GET['request_id']) ? $_GET['request_id'] : 'NONE') . "\n", FILE_APPEND);
        
        $req_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
        
        file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Fulfillment POST processing started\n", FILE_APPEND);
        
        $selected_storytellers = isset($_POST['storytellers']) ? array_map('intval', $_POST['storytellers']) : [];
        
        if ($req_id > 0) {
            // Guard: only fulfill requests that have been paid.
            $current_status = get_post_meta($req_id, 'status', true);
            $fulfillable_statuses = ['in_vetting', 'matching', 'paid', 'ready_review'];
            if (!in_array($current_status, $fulfillable_statuses, true)) {
                file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Blocked fulfillment — request #{$req_id} status is '{$current_status}' (not paid)\n", FILE_APPEND);
                wp_safe_redirect(admin_url('admin.php?page=tav-dashboard&view=requests&error=not_paid'));
                exit;
            }

            // Single authoritative meta key — 'storytellers' is what the CCC reads.
            update_post_meta($req_id, 'storytellers', $selected_storytellers);

            if (!empty($selected_storytellers)) {
                update_post_meta($req_id, 'status', 'ready_review');
                if (function_exists('update_field')) {
                    update_field('status', 'ready_review', $req_id);
                }

                // Ensure the post is published so CCC queries can find it.
                wp_update_post([
                    'ID'          => $req_id,
                    'post_status' => 'publish',
                ]);

                // Email Notification Logic
                $client_id = get_post_field('post_author', $req_id);
                $client_user = get_userdata($client_id);
                
                if ($client_user) {
                    $to = $client_user->user_email;
                    $subject_tmpl = get_option('tav_email_fulfill_subject', 'Your storytellers are ready!');
                    $body_tmpl = get_option('tav_email_fulfill_body', "Hi {client_name},\n\nWe have found some great storytellers for your project {project_name}.\n\nLog in to view them here: {link}\n\nBest,\nThe Team");
                    
                    $st_list_text = "";
                    foreach ($selected_storytellers as $st_id) {
                        $st_post = get_post($st_id);
                        if ($st_post) {
                            $bio = get_field('bio', $st_id);
                            $st_list_text .= "• " . $st_post->post_title . ($bio ? ": " . wp_trim_words($bio, 20) : "") . "\n";
                        }
                    }

                    $review_page = get_page_by_path('review-storytellers');
                    $review_url  = $review_page
                        ? add_query_arg('request_id', $req_id, get_permalink($review_page))
                        : add_query_arg('request_id', $req_id, site_url('/client-dashboard/'));

                    $replacements = [
                        '{client_name}'      => $client_user->display_name,
                        '{project_name}'     => get_the_title($req_id),
                        '{storyteller_list}' => $st_list_text,
                        '{link}'             => $review_url,
                    ];
                    
                    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_tmpl);
                    $body = str_replace(array_keys($replacements), array_values($replacements), $body_tmpl);

                    $mail_data = [
                        'to' => $to,
                        'subject' => $subject,
                        'body' => $body,
                        'replacements' => $replacements
                    ];
                    
                    // Check if catch-all filter is actually hooked
                    $filter_pri = has_filter('wp_mail', 'SISANU_Emails_Catch_All::wp_mail_catch_all');
                    if (!$filter_pri) {
                        // try different syntax
                        $filter_pri = has_filter('wp_mail', ['SISANU_Emails_Catch_All', 'wp_mail_catch_all']);
                    }
                    
                    file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Catch-all filter priority: " . ($filter_pri ?: "NOT FOUND") . "\n", FILE_APPEND);
                    file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Calling wp_mail with parameters: " . json_encode($mail_data) . "\n", FILE_APPEND);
                    
                    $sent = wp_mail($to, $subject, $body);
                    file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "wp_mail result: " . ($sent ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);
                    
                    // Log for debugging (simple error log)
                    if (!$sent) {
                        error_log("TAV Email Alert: Failed to send fulfillment email to {$to} for request #{$req_id}");
                    }
                } else {
                    file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "No client user found for request #$req_id\n", FILE_APPEND);
                }
            } else {
                file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "No storytellers selected\n", FILE_APPEND);
            }
            
            // Redirect back to requests list
            $redirect_url = admin_url('admin.php?page=tav-dashboard&view=requests&notified=1');
            file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Redirecting to $redirect_url\n", FILE_APPEND);
            wp_redirect($redirect_url);
            exit;
        } else {
            file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Invalid Request ID: $req_id\n", FILE_APPEND);
        }
    }

    // Enqueue dashboard CSS.
    add_action('admin_enqueue_scripts', function () {
        wp_enqueue_style(
            'tav-dashboard',
            TAV_PLUGIN_URL . 'assets/css/tav-dashboard.css',
            [],
            TAV_VERSION
        );

        // Inter font from Google Fonts.
        wp_enqueue_style(
            'tav-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
            [],
            null
        );

        wp_enqueue_script(
            'tav-dashboard',
            TAV_PLUGIN_URL . 'assets/js/tav-dashboard.js',
            ['jquery'],
            TAV_VERSION,
            true
        );

        wp_localize_script('tav-dashboard', 'tavData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tav_dashboard_nonce'),
        ]);
    });

    // Add body class for full-width.
    add_filter('admin_body_class', function (string $classes): string {
        return $classes . ' tav-page';
    });
}

/*--------------------------------------------------------------
 * Data helpers
 *------------------------------------------------------------*/

/**
 * Get storyteller counts grouped by campaign status.
 */
function tav_get_status_counts(): array
{
    global $wpdb;

    $results = $wpdb->get_results(
        "SELECT pm.meta_value as status, COUNT(p.ID) as count
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'storyteller'
           AND p.post_status = 'publish'
           AND pm.meta_key = 'campaign_status'
         GROUP BY pm.meta_value"
    );

    $counts = [
        'prospect' => 0,
        'active' => 0,
        'paused' => 0,
        'completed' => 0,
        'declined' => 0,
    ];

    foreach ($results as $row) {
        if (isset($counts[$row->status])) {
            $counts[$row->status] = (int)$row->count;
        }
    }

    return $counts;
}

/**
 * Get count of verified storytellers (score >= 70).
 */
function tav_get_verified_count(): int
{
    global $wpdb;

    $count = $wpdb->get_var(
        "SELECT COUNT(p.ID)
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'storyteller'
           AND p.post_status = 'publish'
           AND pm.meta_key = 'authenticity_score'
           AND CAST(pm.meta_value AS UNSIGNED) >= 70"
    );

    return (int)$count;
}

/**
 * Total published storytellers.
 */
function tav_get_total_storytellers(): int
{
    $counts = wp_count_posts('storyteller');
    return (int)($counts->publish ?? 0);
}

/**
 * Average authenticity score.
 */
function tav_get_avg_authenticity(): int
{
    global $wpdb;

    $avg = $wpdb->get_var(
        "SELECT AVG(CAST(pm.meta_value AS UNSIGNED))
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = 'authenticity_score'
           AND p.post_type = 'storyteller'
           AND p.post_status = 'publish'"
    );

    return $avg ? (int)round((float)$avg) : 0;
}

/**
 * Total revenue from WooCommerce orders.
 * Returns [month => float, all_time => float].
 */
function tav_get_revenue(): array
{
    $result = ['month' => 0.0, 'all_time' => 0.0];

    if (!class_exists('WooCommerce')) {
        return $result;
    }

    $all_orders = wc_get_orders([
        'status' => ['completed', 'processing'],
        'limit'  => -1,
        'return' => 'ids',
    ]);

    foreach ($all_orders as $oid) {
        $order = wc_get_order($oid);
        if (!$order) continue;
        $result['all_time'] += (float) $order->get_total();
    }

    $month_orders = wc_get_orders([
        'status'     => ['completed', 'processing'],
        'date_after' => gmdate('Y-m-01'),
        'limit'      => -1,
        'return'     => 'ids',
    ]);

    foreach ($month_orders as $oid) {
        $order = wc_get_order($oid);
        if (!$order) continue;
        $result['month'] += (float) $order->get_total();
    }

    return $result;
}

/**
 * Count of active requests (statuses that represent work in progress).
 */
function tav_get_active_requests_count(): int
{
    global $wpdb;

    $count = $wpdb->get_var(
        "SELECT COUNT(p.ID)
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'request'
           AND pm.meta_key = 'status'
           AND pm.meta_value IN ('in_vetting', 'matching', 'ready_review', 'paid')"
    );

    return (int) $count;
}

/**
 * Recent storytellers (latest N).
 */
function tav_get_recent_storytellers(int $count = 5): array
{
    return get_posts([
        'post_type' => 'storyteller',
        'post_status' => 'publish',
        'posts_per_page' => $count,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

/**
 * Recent requests (latest N).
 */
function tav_get_recent_requests(int $count = 5): array
{
    return get_posts([
        'post_type' => 'request',
        'post_status' => 'any',
        'posts_per_page' => $count,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

/**
 * Recent storytellers with a specific campaign status.
 */
function tav_get_recent_by_status(int $count = 5): array
{
    return get_posts([
        'post_type' => 'storyteller',
        'post_status' => 'publish',
        'posts_per_page' => $count,
        'orderby' => 'modified',
        'order' => 'DESC',
        'meta_key' => 'campaign_status',
    ]);
}

/**
 * Format large numbers to human-readable strings (e.g. 125000 → 125K).
 */
function tav_format_metric(int $num): string
{
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return round($num / 1000) . 'K';
    }
    return (string)$num;
}

/**
 * Get initials from a name.
 */
function tav_get_initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr(end($parts), 0, 1));
    }
    return $initials;
}



/*--------------------------------------------------------------
 * AJAX: Fetch Storyteller Details for Modal
 *------------------------------------------------------------*/
add_action('wp_ajax_tav_get_storyteller_details', 'tav_ajax_get_storyteller_details');

function tav_ajax_get_storyteller_details(): void
{
    check_ajax_referer('tav_dashboard_nonce', 'nonce');

    $st_id = isset($_GET['st_id']) ? (int)$_GET['st_id'] : 0;
    if (!$st_id || get_post_type($st_id) !== 'storyteller') {
        wp_send_json_error(['message' => __('Invalid Storyteller ID.', 'the-admin-vault')]);
    }

    $st_post = get_post($st_id);
    
    // Bio & Location
    $bio = get_field('bio', $st_id);
    $location = get_field('location', $st_id);
    
    // Platforms
    $platforms = [];
    if (have_rows('platforms_repeater', $st_id)) {
        while (have_rows('platforms_repeater', $st_id)) {
            the_row();
            $platforms[] = [
                'name' => get_sub_field('platform_name'),
                'handle' => get_sub_field('handle'),
                'followers' => get_sub_field('follower_count'),
                'url' => get_sub_field('profile_url'),
            ];
        }
    }

    // Sample Work
    $samples = [];
    if (have_rows('sample_work', $st_id)) {
        while (have_rows('sample_work', $st_id)) {
            the_row();
            $samples[] = [
                'title' => get_sub_field('content_title'),
                'platform' => get_sub_field('platform'),
                'views' => get_sub_field('view_count'),
                'url' => get_sub_field('url'),
            ];
        }
    }

    wp_send_json_success([
        'title' => $st_post->post_title,
        'bio' => $bio ?: __('No bio provided.', 'the-admin-vault'),
        'location' => $location ?: __('Unknown', 'the-admin-vault'),
        'platforms' => $platforms,
        'samples' => $samples,
        'thumbnail' => get_the_post_thumbnail_url($st_id, 'medium'),
    ]);
}

/*--------------------------------------------------------------
 * Render
 *------------------------------------------------------------*/
function tav_render_dashboard(): void
{
    // Determine view.
    $view = $_GET['view'] ?? 'dashboard';
    $current_page_slug = 'tav-dashboard';

    // Sidebar active state logic.
    $is_dashboard = 'dashboard' === $view;
    $is_storytellers = 'storytellers' === $view;
    $is_clients = 'clients' === $view;
    $is_requests = 'requests' === $view;
    $is_add_teller = 'add-teller' === $view;
    $is_edit_teller = 'edit-teller' === $view;
    $is_fulfill = 'fulfill' === $view;
    $is_settings = 'settings' === $view;

    ?>
    <div class="tav-dashboard-wrap">
        <main class="tav-main">

            <!-- ═══ SIDEBAR ═══════════════════════════════════ -->
            <aside class="tav-sidebar">
                <div class="tav-sidebar-brand">
                    <div class="tav-sidebar-logo">VA</div>
                    <div class="tav-sidebar-brand-text">
                        <span class="tav-sidebar-brand-title"><?php esc_html_e('Admin Vault', 'the-admin-vault'); ?></span>
                        <span class="tav-sidebar-brand-sub"><?php esc_html_e('Storytellers', 'the-admin-vault'); ?></span>
                    </div>
                </div>

                <div class="tav-sidebar-toggle">
                    <button id="tav-sidebar-collapse" class="tav-btn-icon" title="Toggle Sidebar">
                        <span class="dashicons dashicons-menu"></span>
                    </button>
                </div>

                <ul class="tav-sidebar-nav">
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=dashboard')); ?>"
                           class="<?php echo $is_dashboard ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-chart-area"></span>
                            <span><?php esc_html_e('Dashboard', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=storytellers')); ?>"
                           class="<?php echo $is_storytellers || $is_add_teller || $is_edit_teller ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-groups"></span>
                            <span><?php esc_html_e('Storytellers', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=clients')); ?>"
                           class="<?php echo $is_clients ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-businessperson"></span>
                            <span><?php esc_html_e('Clients', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=requests')); ?>"
                           class="<?php echo $is_requests || $is_fulfill ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                            <span><?php esc_html_e('Requests', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=settings')); ?>"
                           class="<?php echo $is_settings ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <span><?php esc_html_e('Settings', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                </ul>
            </aside>

            <!-- ═══ CONTENT ═══════════════════════════════════ -->
            <div class="tav-content">

                <?php
                if ($is_dashboard) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/dashboard.php';
                } elseif ($is_storytellers || $is_add_teller || $is_edit_teller) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/storytellers.php';
                } elseif ($is_clients) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/clients.php';
                } elseif ($is_requests) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/requests.php';
                } elseif ($is_fulfill) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/fulfillment.php';
                } elseif ($is_settings) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/settings.php';
                } else {
                    // Default or 404
                    require_once TAV_PLUGIN_DIR . 'admin/views/dashboard.php';
                }
                ?>

            </div><!-- .tav-content -->
        </main>
    </div>
    <?php
}