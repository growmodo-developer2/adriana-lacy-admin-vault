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

    if (sanitize_key($_GET['view'] ?? '') === 'account-settings' && function_exists('UM')) {
        add_action('admin_enqueue_scripts', static function (): void {
            wp_enqueue_script('um_account');
            wp_enqueue_style('um_account');
            wp_enqueue_style('um_default_css');
        });
    }

    // Handle Fulfillment Form Submission (Assign to Project)
    if (isset($_GET['view']) && $_GET['view'] === 'fulfill' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tav_fulfill_nonce']) && wp_verify_nonce($_POST['tav_fulfill_nonce'], 'tav_fulfill_action')) {
        $debug_log = ABSPATH . 'tav_debug.log';
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Fulfillment POST reached for ID " . (isset($_GET['request_id']) ? $_GET['request_id'] : 'NONE') . "\n", FILE_APPEND);
        }
        
        $req_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
        
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Fulfillment POST processing started\n", FILE_APPEND);
        }
        
        $selected_storytellers = isset($_POST['storytellers']) ? array_map('intval', (array) $_POST['storytellers']) : [];
        $selected_storytellers = array_values(array_filter(array_unique($selected_storytellers)));
        
        if ($req_id > 0) {
            // Guard: only fulfill requests that have been paid.
            $current_status = get_post_meta($req_id, 'status', true);
            $fulfillable_statuses = ['in_vetting', 'matching', 'paid', 'ready_review'];
            if (!in_array($current_status, $fulfillable_statuses, true)) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) {
                    file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Blocked fulfillment — request #{$req_id} status is '{$current_status}' (not paid)\n", FILE_APPEND);
                }
                wp_safe_redirect(admin_url('admin.php?page=tav-dashboard&view=requests&error=not_paid'));
                exit;
            }

            $limits = tav_get_fulfillment_selection_limits($req_id);
            $selection_count = count($selected_storytellers);

            if ($selection_count < $limits['min'] || $selection_count > $limits['max']) {
                wp_safe_redirect(admin_url('admin.php?page=tav-dashboard&view=fulfill&request_id=' . $req_id . '&error=selection_count'));
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
                    $body_tmpl = get_option('tav_email_fulfill_body', "Hi {{client_name}},\n\nWe have found some great storytellers for your project {{project_name}}.\n\nLog in to view them here: {{platform_url}}\n\nBest,\nThe Team");
                    
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
                        '{{client_name}}'      => $client_user->display_name,
                        '{{project_name}}'     => get_the_title($req_id),
                        '{{request_id}}'       => (string) $req_id,
                        '{{storyteller_list}}' => $st_list_text,
                        '{{platform_url}}'     => $review_url,
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
                    
                    if ( defined('WP_DEBUG') && WP_DEBUG ) {
                        file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Catch-all filter priority: " . ($filter_pri ?: "NOT FOUND") . "\n", FILE_APPEND);
                        file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Calling wp_mail with parameters: " . json_encode($mail_data) . "\n", FILE_APPEND);
                    }
                    
                    $sent = wp_mail($to, $subject, $body);
                    if ( defined('WP_DEBUG') && WP_DEBUG ) {
                        file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "wp_mail result: " . ($sent ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);
                    }
                    
                    // Log for debugging (simple error log)
                    if (!$sent) {
                        error_log("TAV Email Alert: Failed to send fulfillment email to {$to} for request #{$req_id}");
                    }
                } else {
                    if ( defined('WP_DEBUG') && WP_DEBUG ) {
                        file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "No client user found for request #$req_id\n", FILE_APPEND);
                    }
                }
            } else {
                if ( defined('WP_DEBUG') && WP_DEBUG ) {
                    file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "No storytellers selected\n", FILE_APPEND);
                }
            }
            
            // Redirect back to requests list
            $redirect_url = admin_url('admin.php?page=tav-dashboard&view=requests&notified=1&status=ready_review');
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Redirecting to $redirect_url\n", FILE_APPEND);
            }
            wp_redirect($redirect_url);
            exit;
        } else {
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                file_put_contents($debug_log, date('[Y-m-d H:i:s] ') . "Invalid Request ID: $req_id\n", FILE_APPEND);
            }
        }
    }

    // Enqueue dashboard CSS.
    add_action('admin_enqueue_scripts', function () {
        // Use the file's modification time as the cache-busting version so any
        // edit to the stylesheet is picked up immediately (filemtime() runs at
        // runtime and is not frozen by OPcache like a constant would be).
        $css_path = TAV_PLUGIN_DIR . 'assets/css/tav-dashboard.css';
        $css_ver  = file_exists($css_path) ? (string) filemtime($css_path) : TAV_VERSION;

        wp_enqueue_style(
            'tav-dashboard',
            TAV_PLUGIN_URL . 'assets/css/tav-dashboard.css',
            [],
            $css_ver
        );

        // Inter font from Google Fonts.
        wp_enqueue_style(
            'tav-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
            [],
            null
        );

        if (sanitize_key($_GET['view'] ?? '') === 'account-settings' && defined('CCC_PLUGIN_URL') && defined('CCC_VERSION')) {
            wp_enqueue_style(
                'ccc-dashboard-admin-account',
                CCC_PLUGIN_URL . 'assets/css/dashboard.css',
                ['tav-dashboard'],
                CCC_VERSION
            );

            if (sanitize_key($_GET['tab'] ?? 'profile') === 'profile') {
                wp_enqueue_script(
                    'ccc-account-settings',
                    CCC_PLUGIN_URL . 'assets/js/account-settings.js',
                    [],
                    CCC_VERSION,
                    true
                );

                wp_localize_script('ccc-account-settings', 'cccAccountSettings', [
                    'ajaxurl'     => admin_url('admin-ajax.php'),
                    'avatarNonce' => wp_create_nonce('ccc_upload_avatar'),
                    'uploading'   => __('Uploading photo…', 'client-command-center'),
                    'success'     => __('Photo updated.', 'client-command-center'),
                    'error'       => __('Could not update photo. Please try again.', 'client-command-center'),
                ]);
            }
        }

        // Enqueue WordPress media uploader for storyteller form
        wp_enqueue_media();

        wp_enqueue_script(
            'chartjs',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );

        $js_path = TAV_PLUGIN_DIR . 'assets/js/tav-dashboard.js';
        $js_ver  = file_exists($js_path) ? (string) filemtime($js_path) : TAV_VERSION;

        wp_enqueue_script(
            'tav-dashboard',
            TAV_PLUGIN_URL . 'assets/js/tav-dashboard.js',
            ['jquery', 'chartjs'],
            $js_ver,
            true
        );

        wp_localize_script('tav-dashboard', 'tavData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tav_dashboard_nonce'),
            'chart'   => tav_get_revenue_chart_data('30days'),
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
 * Min/max storyteller selection for fulfillment (Flow 3: 5–8).
 */
function tav_get_fulfillment_selection_limits(int $request_id): array
{
    $requested = (int) get_post_meta($request_id, 'storyteller_count', true);
    if ($requested <= 0 && function_exists('get_field')) {
        $requested = (int) get_field('storyteller_count', $request_id);
    }

    $min = 5;
    $max = 8;
    $target = $requested > 0 ? max($min, min($max, $requested)) : 8;

    return [
        'min'    => $min,
        'max'    => $max,
        'target' => $target,
    ];
}

/**
 * Normalize platform name to a filter slug.
 */
function tav_normalize_platform_slug(string $name): string
{
    $name = strtolower(trim($name));
    $aliases = [
        'x / twitter' => 'twitter',
        'x'           => 'twitter',
        'twitter/x'   => 'twitter',
    ];

    if (isset($aliases[$name])) {
        return $aliases[$name];
    }

    return preg_replace('/[^a-z0-9]/', '', $name);
}

/**
 * Total followers across all platforms for a storyteller.
 */
function tav_get_storyteller_total_followers(int $post_id): int
{
    $cached = (int) get_post_meta($post_id, 'tav_total_followers', true);
    if ($cached > 0) {
        return $cached;
    }

    $total = 0;
    $rows  = function_exists('get_field') ? (get_field('platforms_repeater', $post_id) ?: []) : [];
    foreach ($rows as $row) {
        $total += (int) ($row['follower_count'] ?? 0);
    }

    return $total;
}

/**
 * Whether storyteller total followers match a range filter.
 */
function tav_storyteller_matches_followers(int $post_id, string $range): bool
{
    if ($range === '') {
        return true;
    }

    $total = tav_get_storyteller_total_followers($post_id);
    $ranges = [
        'under_10k' => [0, 9999],
        '10k_50k'   => [10000, 49999],
        '50k_100k'  => [50000, 99999],
        '100k_plus' => [100000, PHP_INT_MAX],
    ];

    if (!isset($ranges[$range])) {
        return true;
    }

    [$min, $max] = $ranges[$range];
    return $total >= $min && $total <= $max;
}

/**
 * Whether storyteller has a given platform.
 */
function tav_storyteller_matches_platform(int $post_id, string $platform): bool
{
    if ($platform === '') {
        return true;
    }

    $stored = get_post_meta($post_id, 'tav_platforms', true);
    if (is_string($stored) && $stored !== '') {
        $slugs = array_filter(array_map('trim', explode(',', $stored)));
        return in_array($platform, $slugs, true);
    }

    $rows = function_exists('get_field') ? (get_field('platforms_repeater', $post_id) ?: []) : [];
    foreach ($rows as $row) {
        $slug = tav_normalize_platform_slug((string) ($row['platform_name'] ?? ''));
        if ($slug === $platform) {
            return true;
        }
    }

    return false;
}

/**
 * Search storytellers for the fulfillment picker.
 */
function tav_search_fulfillment_storytellers(array $filters): array
{
    $search_term       = $filters['s_term'] ?? '';
    $niche_filter      = $filters['s_niche'] ?? '';
    $location_filter   = $filters['s_location'] ?? '';
    $platform_filter   = $filters['s_platform'] ?? '';
    $followers_filter  = $filters['s_followers'] ?? '';
    $engagement_filter = $filters['s_engagement'] ?? '';

    $args = [
        'post_type'      => 'storyteller',
        'posts_per_page' => 100,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    if ($search_term) {
        $args['s'] = $search_term;
    }

    if ($niche_filter) {
        $args['tax_query'] = [[
            'taxonomy' => 'vs_niche',
            'field'    => 'slug',
            'terms'    => $niche_filter,
        ]];
    }

    $meta_queries = [];
    if ($location_filter) {
        $meta_queries[] = [
            'key'     => 'location',
            'value'   => $location_filter,
            'compare' => 'LIKE',
        ];
    }

    if ($platform_filter) {
        $meta_queries[] = [
            'key'     => 'tav_platforms',
            'value'   => $platform_filter,
            'compare' => 'LIKE',
        ];
    }

    if ($followers_filter) {
        $follower_ranges = [
            'under_10k' => [0, 9999],
            '10k_50k'   => [10000, 49999],
            '50k_100k'  => [50000, 99999],
            '100k_plus' => [100000, 999999999],
        ];
        if (isset($follower_ranges[$followers_filter])) {
            $meta_queries[] = [
                'key'     => 'tav_total_followers',
                'value'   => $follower_ranges[$followers_filter],
                'type'    => 'NUMERIC',
                'compare' => 'BETWEEN',
            ];
        }
    }

    if ($engagement_filter) {
        $eng_ranges = [
            'under_2' => [0, 2],
            '2_5'     => [2, 5],
            '5_10'    => [5, 10],
            '10_plus' => [10, 100],
        ];
        if (isset($eng_ranges[$engagement_filter])) {
            $meta_queries[] = [
                'key'     => 'tav_avg_engagement_rate',
                'value'   => $eng_ranges[$engagement_filter],
                'type'    => 'DECIMAL',
                'compare' => 'BETWEEN',
            ];
        }
    }

    if (!empty($meta_queries)) {
        $meta_queries['relation'] = 'AND';
        $args['meta_query'] = $meta_queries;
    }

    $storytellers = get_posts($args);

    if ($platform_filter || $followers_filter) {
        $storytellers = array_values(array_filter($storytellers, static function ($post) use ($platform_filter, $followers_filter): bool {
            if ($platform_filter && !tav_storyteller_matches_platform($post->ID, $platform_filter)) {
                return false;
            }
            if ($followers_filter && !tav_storyteller_matches_followers($post->ID, $followers_filter)) {
                return false;
            }
            return true;
        }));
    }

    return $storytellers;
}

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
 * Decode client_feedback meta (JSON or serialized array).
 *
 * @return array<int|string, string>
 */
function tav_decode_client_feedback(mixed $raw): array
{
    if (empty($raw)) {
        return [];
    }

    if (is_array($raw)) {
        return $raw;
    }

    if (!is_string($raw)) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $decoded = maybe_unserialize($raw);
    }

    return is_array($decoded) ? $decoded : [];
}

/**
 * Client satisfaction rate for the admin dashboard.
 *
 * Business rules (Verified Storytellers workflow):
 *
 * 1. Score (displayed as X.X/5.0)
 *    After admin fulfills a request, clients review storytellers on the
 *    review page and mark each one "interested" or "pass" (stored in
 *    `client_feedback` as storyteller_id => feedback).
 *    Satisfaction score = 5 × (interested reviews ÷ total reviews).
 *
 * 2. Finished badge (displayed as "X% Finished")
 *    Project completion rate among paid requests — what share of requests
 *    that moved past payment have reached the `completed` status.
 *
 * @return array{
 *   score_display: string,
 *   finished_display: string,
 *   score: float|null,
 *   finished_pct: int,
 *   interested_count: int,
 *   feedback_count: int,
 *   completed_count: int,
 *   pipeline_count: int
 * }
 */
function tav_get_satisfaction_rate(): array
{
    global $wpdb;

    $interested_count = 0;
    $feedback_count   = 0;

    $feedback_rows = $wpdb->get_results(
        "SELECT pm.meta_value
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = 'client_feedback'
           AND p.post_type = 'request'
           AND p.post_status = 'publish'",
        ARRAY_A
    );

    foreach ($feedback_rows as $row) {
        $entries = tav_decode_client_feedback($row['meta_value'] ?? '');
        foreach ($entries as $value) {
            if (!in_array($value, ['interested', 'pass'], true)) {
                continue;
            }
            $feedback_count++;
            if ($value === 'interested') {
                $interested_count++;
            }
        }
    }

    $score = null;
    if ($feedback_count > 0) {
        $score = round(5 * ($interested_count / $feedback_count), 1);
    }

    $pipeline_statuses = ['paid', 'in_vetting', 'matching', 'ready_review', 'assigned', 'completed'];
    $placeholders      = implode(',', array_fill(0, count($pipeline_statuses), '%s'));

    $pipeline_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'request'
               AND p.post_status = 'publish'
               AND pm.meta_key = 'status'
               AND pm.meta_value IN ($placeholders)",
            ...$pipeline_statuses
        )
    );

    $completed_count = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID)
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'request'
           AND p.post_status = 'publish'
           AND pm.meta_key = 'status'
           AND pm.meta_value = 'completed'"
    );

    $finished_pct = $pipeline_count > 0
        ? (int) round(($completed_count / $pipeline_count) * 100)
        : 0;

    return [
        'score'             => $score,
        'score_display'     => $score !== null ? number_format($score, 1) . '/5.0' : '—/5.0',
        'finished_pct'      => $finished_pct,
        'finished_display'  => $pipeline_count > 0
            ? sprintf(__('%d%% Finished', 'the-admin-vault'), $finished_pct)
            : __('No data', 'the-admin-vault'),
        'interested_count'  => $interested_count,
        'feedback_count'    => $feedback_count,
        'completed_count'   => $completed_count,
        'pipeline_count'    => $pipeline_count,
    ];
}

/**
 * @deprecated Use tav_get_satisfaction_rate(). Kept for backward compatibility.
 */
function tav_get_match_acceptance_rate(): array
{
    $satisfaction = tav_get_satisfaction_rate();

    return [
        'value'       => $satisfaction['score_display'],
        'numerator'   => $satisfaction['interested_count'],
        'denominator' => $satisfaction['feedback_count'],
    ];
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
 * Revenue grouped by day/month/hour for the selected period.
 *
 * @return array{labels:string[],received:float[],pending:float[],total:float}
 */
function tav_get_revenue_chart_data(string $period = '30days'): array
{
    $empty = [
        'labels'   => [],
        'received' => [],
        'pending'  => [],
        'total'    => 0.0,
    ];

    if (!class_exists('WooCommerce')) {
        return $empty;
    }

    $received_by_key = [];
    $pending_by_key  = [];
    $since           = null;
    $key_format      = 'Y-m-d';
    $label_format    = 'M j';

    if ($period === '24hours') {
        $since        = strtotime('-24 hours');
        $key_format   = 'Y-m-d H';
        $label_format = 'g A';
    } elseif ($period === '7days') {
        $since = strtotime('-6 days midnight');
    } elseif ($period === '30days') {
        $since = strtotime('-29 days midnight');
    }

    $order_args = [
        'type'   => 'shop_order',
        'limit'  => -1,
        'return' => 'objects',
    ];
    if ($since) {
        $order_args['date_created'] = '>' . $since;
    }

    foreach (wc_get_orders(array_merge($order_args, ['status' => ['completed', 'processing']])) as $order) {
        $created = $order->get_date_created();
        if (!$created) {
            continue;
        }
        $key = $period === 'alltime'
            ? gmdate('Y-m', $created->getTimestamp())
            : gmdate($key_format, $created->getTimestamp());
        $received_by_key[$key] = ($received_by_key[$key] ?? 0.0) + (float) $order->get_total();
    }

    foreach (wc_get_orders(array_merge($order_args, ['status' => ['pending', 'on-hold']])) as $order) {
        $created = $order->get_date_created();
        if (!$created) {
            continue;
        }
        $key = $period === 'alltime'
            ? gmdate('Y-m', $created->getTimestamp())
            : gmdate($key_format, $created->getTimestamp());
        $pending_by_key[$key] = ($pending_by_key[$key] ?? 0.0) + (float) $order->get_total();
    }

    $labels   = [];
    $received = [];
    $pending  = [];

    if ($period === 'alltime') {
        $start = new DateTime('first day of -11 months');
        $end   = new DateTime('first day of next month');
        $step  = new DateInterval('P1M');
        for ($cursor = clone $start; $cursor < $end; $cursor->add($step)) {
            $key       = $cursor->format('Y-m');
            $labels[]  = $cursor->format('M');
            $received[] = round($received_by_key[$key] ?? 0.0, 2);
            $pending[]  = round($pending_by_key[$key] ?? 0.0, 2);
        }
    } elseif ($period === '24hours') {
        for ($i = 23; $i >= 0; $i--) {
            $ts        = strtotime("-{$i} hours");
            $key       = gmdate('Y-m-d H', $ts);
            $labels[]  = gmdate($label_format, $ts);
            $received[] = round($received_by_key[$key] ?? 0.0, 2);
            $pending[]  = round($pending_by_key[$key] ?? 0.0, 2);
        }
    } elseif ($period === '7days') {
        for ($i = 6; $i >= 0; $i--) {
            $ts        = strtotime("-{$i} days");
            $key       = gmdate('Y-m-d', $ts);
            $labels[]  = gmdate($label_format, $ts);
            $received[] = round($received_by_key[$key] ?? 0.0, 2);
            $pending[]  = round($pending_by_key[$key] ?? 0.0, 2);
        }
    } else {
        for ($i = 29; $i >= 0; $i--) {
            $ts        = strtotime("-{$i} days");
            $key       = gmdate('Y-m-d', $ts);
            $labels[]  = gmdate($label_format, $ts);
            $received[] = round($received_by_key[$key] ?? 0.0, 2);
            $pending[]  = round($pending_by_key[$key] ?? 0.0, 2);
        }
    }

    return [
        'labels'   => $labels,
        'received' => $received,
        'pending'  => $pending,
        'total'    => round(array_sum($received), 2),
    ];
}

/**
 * Human-readable request title for dashboard lists.
 */
function tav_get_request_title(int $request_id, string $fallback = ''): string
{
    $title = trim($fallback !== '' ? $fallback : get_the_title($request_id));
    if ($title !== '' && !in_array($title, ['Auto Draft', '(no title)'], true)) {
        return $title;
    }

    if (function_exists('get_field')) {
        $goal = get_field('campaign_goal', $request_id);
        if (is_string($goal) && $goal !== '') {
            return $goal;
        }
    }

    return sprintf(__('Request #%d', 'the-admin-vault'), $request_id);
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
 * Pending fulfillment requests — paid/in_vetting/matching, oldest first.
 */
function tav_get_pending_fulfillment_requests(int $limit = 5): array
{
    $posts = get_posts([
        'post_type'      => 'request',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'meta_query'     => [[
            'key'     => 'status',
            'value'   => ['paid', 'in_vetting', 'matching'],
            'compare' => 'IN',
        ]],
    ]);

    $out = [];
    foreach ($posts as $p) {
        $out[] = [
            'id'       => $p->ID,
            'title'    => tav_get_request_title($p->ID, $p->post_title),
            'status'   => get_post_meta($p->ID, 'status', true),
            'due_date' => get_post_meta($p->ID, 'due_date', true),
            'client'   => get_the_author_meta('display_name', (int) $p->post_author) ?: __('Unknown', 'the-admin-vault'),
        ];
    }
    return $out;
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
 * Human-readable time-ago string from a Unix timestamp.
 * e.g. "just now", "4 minutes ago", "2 hours ago", "3 days ago".
 */
function tav_time_ago(int $timestamp): string
{
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return __('just now', 'the-admin-vault');
    }
    if ($diff < 3600) {
        $n = (int) round($diff / 60);
        return sprintf(_n('%d minute ago', '%d minutes ago', $n, 'the-admin-vault'), $n);
    }
    if ($diff < 86400) {
        $n = (int) round($diff / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $n, 'the-admin-vault'), $n);
    }
    if ($diff < 2592000) { // < 30 days
        $n = (int) round($diff / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $n, 'the-admin-vault'), $n);
    }
    if ($diff < 31536000) { // < 365 days
        $n = (int) round($diff / 2592000);
        return sprintf(_n('%d month ago', '%d months ago', $n, 'the-admin-vault'), $n);
    }
    $n = (int) round($diff / 31536000);
    return sprintf(_n('%d year ago', '%d years ago', $n, 'the-admin-vault'), $n);
}

/**
 * Unified activity feed — chronological merge of up to $limit events across:
 *   1. New requests submitted    (status: pending_payment — uses post_date)
 *   2. Payments received         (status: paid            — uses post_modified)
 *   3. Requests fulfilled        (status: ready_review    — uses post_modified)
 *   4. Client selections made    (status: assigned        — uses post_modified)
 *   5. New storytellers added    (storyteller post_date)
 *   6. New clients registered    (user user_registered)
 *
 * Each event has keys: timestamp (int), type (string), icon (string), label (string).
 */
function tav_get_activity_feed(int $limit = 10): array
{
    $events = [];

    // ── 1–4: Request-based events ─────────────────────────────────────────────
    $requests = get_posts([
        'post_type'      => 'request',
        'post_status'    => 'any',
        'posts_per_page' => $limit * 3, // over-fetch so the merged sort has enough candidates
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'     => 'status',
                'value'   => ['pending_payment', 'paid', 'ready_review', 'assigned'],
                'compare' => 'IN',
            ],
        ],
    ]);

    foreach ($requests as $post) {
        $status      = get_post_meta($post->ID, 'status', true);
        $author_id   = (int) $post->post_author;
        $client_name = $author_id
            ? (get_the_author_meta('display_name', $author_id) ?: __('Unknown client', 'the-admin-vault'))
            : __('Unknown client', 'the-admin-vault');
        $title = $post->post_title ?: __('(untitled)', 'the-admin-vault');

        switch ($status) {
            case 'pending_payment':
                $events[] = [
                    'timestamp' => strtotime($post->post_date),
                    'type'      => 'new_request',
                    'icon'      => 'dashicons-clipboard',
                    'label'     => sprintf(
                        /* translators: 1: client name, 2: request title */
                        __('%1$s submitted a new request: %2$s', 'the-admin-vault'),
                        $client_name,
                        $title
                    ),
                ];
                break;

            case 'paid':
                $events[] = [
                    'timestamp' => strtotime($post->post_modified),
                    'type'      => 'payment',
                    'icon'      => 'dashicons-money-alt',
                    'label'     => sprintf(
                        /* translators: 1: client name, 2: request title */
                        __('%1$s completed payment for %2$s', 'the-admin-vault'),
                        $client_name,
                        $title
                    ),
                ];
                break;

            case 'ready_review':
                $events[] = [
                    'timestamp' => strtotime($post->post_modified),
                    'type'      => 'fulfilled',
                    'icon'      => 'dashicons-yes-alt',
                    'label'     => sprintf(
                        /* translators: %s: request title */
                        __('Request fulfilled: %s — storytellers sent to client', 'the-admin-vault'),
                        $title
                    ),
                ];
                break;

            case 'assigned':
                $events[] = [
                    'timestamp' => strtotime($post->post_modified),
                    'type'      => 'selections',
                    'icon'      => 'dashicons-heart',
                    'label'     => sprintf(
                        /* translators: 1: client name, 2: request title */
                        __('%1$s selected storytellers for %2$s', 'the-admin-vault'),
                        $client_name,
                        $title
                    ),
                ];
                break;
        }
    }

    // ── 5: New storyteller events ──────────────────────────────────────────────
    $storytellers = get_posts([
        'post_type'      => 'storyteller',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    foreach ($storytellers as $post) {
        $events[] = [
            'timestamp' => strtotime($post->post_date),
            'type'      => 'new_storyteller',
            'icon'      => 'dashicons-id-alt',
            'label'     => sprintf(
                /* translators: %s: storyteller name */
                __('New storyteller added: %s', 'the-admin-vault'),
                $post->post_title ?: __('(untitled)', 'the-admin-vault')
            ),
        ];
    }

    // ── 6: New client registrations ───────────────────────────────────────────
    $client_role_slugs = apply_filters('tav_client_role_slugs', ['um_client', 'client']);
    $new_clients = get_users([
        'role__in' => (array) $client_role_slugs,
        'number'   => $limit,
        'orderby'  => 'registered',
        'order'    => 'DESC',
    ]);

    foreach ($new_clients as $user) {
        $events[] = [
            'timestamp' => strtotime($user->user_registered),
            'type'      => 'new_client',
            'icon'      => 'dashicons-admin-users',
            'label'     => sprintf(
                /* translators: %s: client display name */
                __('New client registered: %s', 'the-admin-vault'),
                $user->display_name ?: $user->user_login
            ),
        ];
    }

    // ── Sort newest-first, return top $limit ───────────────────────────────────
    usort($events, static function (array $a, array $b): int {
        return $b['timestamp'] <=> $a['timestamp'];
    });

    return array_slice($events, 0, $limit);
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
 * AJAX: Fetch Client Details for Modal
 *------------------------------------------------------------*/
add_action('wp_ajax_tav_get_client_details', 'tav_ajax_get_client_details');

function tav_ajax_get_client_details(): void
{
    check_ajax_referer('tav_dashboard_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions.', 'the-admin-vault')], 403);
    }

    $client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
    $user      = $client_id ? get_userdata($client_id) : false;

    if (!$user) {
        wp_send_json_error(['message' => __('Invalid client ID.', 'the-admin-vault')]);
    }

    $company = (string) get_user_meta($client_id, 'organization_name', true);
    $status  = (string) get_user_meta($client_id, 'ccc_client_status', true) ?: 'active';
    $notes   = (string) get_user_meta($client_id, 'ccc_client_notes',  true);

    // Tier label map — prefer live CCC pricing if the plugin is active.
    $tier_labels = [
        'quick'      => 'Quick Match',
        'custom'     => 'Custom Search',
        'premium'    => 'Premium Search',
        'retainer'   => 'Monthly Retainer',
        'enterprise' => 'Enterprise',
    ];
    if (function_exists('ccc_get_pricing')) {
        $p = ccc_get_pricing();
        foreach (($p['tiers'] ?? []) as $key => $tier) {
            $tier_labels[$key] = $tier['label'];
        }
    }

    // Request history (up to 20 most recent).
    $requests_raw = get_posts([
        'post_type'      => 'request',
        'post_status'    => 'any',
        'author'         => $client_id,
        'posts_per_page' => 20,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $requests = [];
    foreach ($requests_raw as $req) {
        $pkg_key   = (string) get_post_meta($req->ID, 'package_tier', true);
        $pkg_label = $tier_labels[$pkg_key] ?? ucfirst($pkg_key ?: '—');

        $order_id = (int) get_post_meta($req->ID, 'woo_order_id', true);
        $total    = '—';
        if ($order_id && class_exists('WooCommerce')) {
            $order = wc_get_order($order_id);
            if ($order) {
                $total = '$' . number_format((float) $order->get_total(), 2);
            }
        }

        $requests[] = [
            'id'      => $req->ID,
            'title'   => ($req->post_title ?: "Request #{$req->ID}"),
            'package' => $pkg_label,
            'date'    => date_i18n('M j, Y', strtotime($req->post_date)),
            'total'   => $total,
        ];
    }

    wp_send_json_success([
        'id'       => $client_id,
        'name'     => $user->display_name,
        'email'    => $user->user_email,
        'company'  => $company,
        'status'   => $status,
        'notes'    => $notes,
        'requests' => $requests,
    ]);
}

/*--------------------------------------------------------------
 * AJAX: Save Client Status + Notes
 *------------------------------------------------------------*/
add_action('wp_ajax_tav_save_client_details', 'tav_ajax_save_client_details');

function tav_ajax_save_client_details(): void
{
    check_ajax_referer('tav_dashboard_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions.', 'the-admin-vault')], 403);
    }

    $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
    if (!$client_id || !get_userdata($client_id)) {
        wp_send_json_error(['message' => __('Invalid client ID.', 'the-admin-vault')]);
    }

    $allowed_statuses = ['active', 'suspended', 'vip'];
    $status = (isset($_POST['status']) && in_array($_POST['status'], $allowed_statuses, true))
        ? $_POST['status']
        : 'active';

    $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

    update_user_meta($client_id, 'ccc_client_status', $status);
    update_user_meta($client_id, 'ccc_client_notes',  $notes);

    wp_send_json_success(['message' => __('Changes saved.', 'the-admin-vault')]);
}

/*--------------------------------------------------------------
 * AJAX: Revenue chart data
 *------------------------------------------------------------*/
add_action('wp_ajax_tav_get_chart_data', function (): void {
    check_ajax_referer('tav_dashboard_nonce', 'nonce');
    if (!current_user_can('edit_storytellers')) {
        wp_send_json_error('Unauthorized', 403);
    }
    $allowed = ['7days', '30days', 'alltime', '24hours'];
    $period  = sanitize_text_field($_POST['period'] ?? '30days');
    if (!in_array($period, $allowed, true)) $period = '30days';
    wp_send_json_success(tav_get_revenue_chart_data($period));
});

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
    $is_niches = 'niches' === $view;
    $is_pricing = 'pricing' === $view;
    $is_account_settings = 'account-settings' === $view;
    $is_notifications = 'notifications' === $view;
    $pending_requests_count = tav_get_active_requests_count();

    ?>
    <div class="tav-dashboard-wrap<?php echo tav_is_frontend_portal_context() ? ' tav-front-portal' : ''; ?>">
        <main class="tav-main">

            <!-- ═══ SIDEBAR (Single with Collapsed/Expanded States) ═══ -->
            <aside class="tav-sidebar" id="tav-sidebar">
                <div class="tav-sidebar-brand">
                    <div class="tav-sidebar-logo" id="tav-sidebar-toggle" title="<?php esc_attr_e('Toggle Sidebar', 'the-admin-vault'); ?>">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <div class="tav-sidebar-brand-text">
                        <span class="tav-sidebar-brand-title"><?php esc_html_e('Verified', 'the-admin-vault'); ?></span>
                        <span class="tav-sidebar-brand-sub"><?php esc_html_e('Storytellers', 'the-admin-vault'); ?></span>
                    </div>
                </div>

                <div class="tav-sidebar-section-label">
                    <span><?php esc_html_e('GENERAL', 'the-admin-vault'); ?></span>
                </div>

                <ul class="tav-sidebar-nav">
                    <li>
                        <a href="<?php echo esc_url(tav_get_dashboard_view_url('dashboard')); ?>"
                           class="<?php echo $is_dashboard ? 'active' : ''; ?>" title="<?php esc_attr_e('Dashboard', 'the-admin-vault'); ?>">
                            <svg class="tav-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="9" rx="1"></rect>
                                <rect x="14" y="3" width="7" height="5" rx="1"></rect>
                                <rect x="14" y="12" width="7" height="9" rx="1"></rect>
                                <rect x="3" y="16" width="7" height="5" rx="1"></rect>
                            </svg>
                            <span class="tav-nav-text"><?php esc_html_e('Dashboard', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(tav_get_dashboard_view_url('requests')); ?>"
                           class="<?php echo $is_requests || $is_fulfill ? 'active' : ''; ?>" title="<?php esc_attr_e('Requests', 'the-admin-vault'); ?>">
                            <svg class="tav-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            <span class="tav-nav-text"><?php esc_html_e('Requests', 'the-admin-vault'); ?></span>
                            <?php if ($pending_requests_count > 0): ?>
                                <span class="tav-nav-badge"><?php echo esc_html($pending_requests_count); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(tav_get_dashboard_view_url('storytellers')); ?>"
                           class="<?php echo $is_storytellers || $is_add_teller || $is_edit_teller ? 'active' : ''; ?>" title="<?php esc_attr_e('Storytellers', 'the-admin-vault'); ?>">
                            <svg class="tav-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span class="tav-nav-text"><?php esc_html_e('Storytellers', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(tav_get_dashboard_view_url('clients')); ?>"
                           class="<?php echo $is_clients ? 'active' : ''; ?>" title="<?php esc_attr_e('Clients', 'the-admin-vault'); ?>">
                            <svg class="tav-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>
                            <span class="tav-nav-text"><?php esc_html_e('Clients', 'the-admin-vault'); ?></span>
                        </a>
                    </li>
                </ul>
                
                <!-- Bottom Section -->
                <div class="tav-sidebar-bottom-section">
                    <ul class="tav-sidebar-nav tav-sidebar-nav-bottom">
                        <li>
                            <a href="<?php echo esc_url(tav_get_dashboard_view_url('notifications')); ?>"
                               class="<?php echo $is_notifications ? 'active' : ''; ?>" title="<?php esc_attr_e('Notifications', 'the-admin-vault'); ?>">
                                <svg class="tav-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                </svg>
                                <span class="tav-nav-text"><?php esc_html_e('Notifications', 'the-admin-vault'); ?></span>
                                <?php if ($pending_requests_count > 0): ?>
                                    <span class="tav-nav-badge"><?php echo esc_html($pending_requests_count); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(tav_get_dashboard_view_url('settings')); ?>"
                               class="<?php echo $is_settings || $is_niches || $is_pricing ? 'active' : ''; ?>" title="<?php esc_attr_e('Settings', 'the-admin-vault'); ?>">
                                <svg class="tav-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                                <span class="tav-nav-text"><?php esc_html_e('Settings', 'the-admin-vault'); ?></span>
                            </a>
                        </li>
                    </ul>

                    <ul class="tav-sidebar-nav tav-sidebar-nav-profile">
                        <li>
                            <a href="<?php echo esc_url(tav_get_dashboard_view_url('account-settings', ['tab' => 'profile'])); ?>"
                               class="<?php echo $is_account_settings ? 'active' : ''; ?>"
                               title="<?php esc_attr_e('My Profile', 'client-command-center'); ?>">
                                <svg class="tav-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span class="tav-nav-text"><?php esc_html_e('My Profile', 'client-command-center'); ?></span>
                            </a>
                        </li>
                    </ul>
                    
                    <!-- User Profile Card -->
                    <?php 
                    $current_user = wp_get_current_user();
                    $user_name = $current_user->display_name;
                    $user_email = $current_user->user_email;
                    ?>
                    <div class="tav-sidebar-user-card">
                        <div class="tav-user-avatar">
                            <?php echo get_avatar(get_current_user_id(), 40); ?>
                        </div>
                        <div class="tav-user-info">
                            <span class="tav-user-name"><?php echo esc_html($user_name); ?></span>
                            <span class="tav-user-email"><?php echo esc_html($user_email); ?></span>
                        </div>
                        <button class="tav-user-dropdown-toggle" type="button" aria-expanded="false" aria-controls="tav-user-account-menu">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="tav-user-account-menu" id="tav-user-account-menu" hidden>
                            <a href="<?php echo esc_url(tav_get_dashboard_view_url('account-settings', ['tab' => 'profile'])); ?>">
                                <?php esc_html_e('My Profile', 'client-command-center'); ?>
                            </a>
                            <a href="<?php echo esc_url(tav_get_dashboard_view_url('account-settings', ['tab' => 'password'])); ?>">
                                <?php esc_html_e('Password', 'client-command-center'); ?>
                            </a>
                        </div>
                    </div>
                </div>
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
                } elseif ($is_niches) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/niches.php';
                } elseif ($is_pricing) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/pricing.php';
                } elseif ($is_account_settings) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/account-settings.php';
                } elseif ($is_notifications) {
                    require_once TAV_PLUGIN_DIR . 'admin/views/notifications.php';
                } else {
                    require_once TAV_PLUGIN_DIR . 'admin/views/dashboard.php';
                }
                ?>

            </div><!-- .tav-content -->
        </main>
    </div>
    
    <script>
    (function() {
        var toggleBtn = document.getElementById('tav-sidebar-toggle');
        var sidebar = document.getElementById('tav-sidebar');

        if (toggleBtn && sidebar) {
            var isCollapsed = localStorage.getItem('tav_sidebar_collapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }

            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('tav_sidebar_collapsed', sidebar.classList.contains('collapsed'));
            });
        }

        var userToggle = document.querySelector('.tav-user-dropdown-toggle');
        var userMenu = document.getElementById('tav-user-account-menu');
        if (userToggle && userMenu) {
            userToggle.addEventListener('click', function() {
                var open = !userMenu.hidden;
                userMenu.hidden = open;
                userToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            });
        }
    })();
    </script>
    <?php
}