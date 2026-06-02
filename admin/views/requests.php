<?php
defined('ABSPATH') || exit;

// Handle "Mark Complete" status transition — uses $wpdb to bypass ACF filters.
if (!empty($_GET['tav_set_complete'])) {
    $comp_id = (int) $_GET['tav_set_complete'];
    if ($comp_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'tav_complete_' . $comp_id)) {
        $cur = get_post_meta($comp_id, 'status', true);
        if (in_array($cur, ['assigned', 'ready_review'], true)) {
            global $wpdb;
            $wpdb->update($wpdb->postmeta, ['meta_value' => 'completed'], ['post_id' => $comp_id, 'meta_key' => 'status']);
            clean_post_cache($comp_id);
            wp_cache_delete($comp_id, 'post_meta');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Request marked as Completed.', 'the-admin-vault') . '</p></div>';
        }
    }
}

// Start Matching: paid/in_vetting → matching (client sees "Sourcing Storytellers").
if (!empty($_GET['tav_start_matching'])) {
    $match_id = (int) $_GET['tav_start_matching'];
    if ($match_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'tav_match_' . $match_id)) {
        $cur = get_post_meta($match_id, 'status', true);
        if (in_array($cur, ['paid', 'in_vetting'], true)) {
            update_post_meta($match_id, 'status', 'matching');
            if (function_exists('update_field')) {
                update_field('status', 'matching', $match_id);
            }
            clean_post_cache($match_id);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Request moved to Matching — client will see sourcing in progress.', 'the-admin-vault') . '</p></div>';
        }
    }
}

// Handle bulk actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tav_bulk_action']) && !empty($_POST['tav_bulk_ids']) && isset($_POST['tav_bulk_nonce']) && wp_verify_nonce($_POST['tav_bulk_nonce'], 'tav_bulk_requests')) {
    $bulk_action = sanitize_text_field($_POST['tav_bulk_action']);
    $bulk_ids    = array_map('intval', (array) $_POST['tav_bulk_ids']);
    $bulk_count  = 0;

    $allowed_bulk = [
        'archive'  => 'archived',
        'complete' => 'completed',
    ];

    if (isset($allowed_bulk[$bulk_action])) {
        global $wpdb;
        $new_status = $allowed_bulk[$bulk_action];
        foreach ($bulk_ids as $bid) {
            if ($bid && get_post_type($bid) === 'request') {
                $wpdb->update($wpdb->postmeta, ['meta_value' => $new_status], ['post_id' => $bid, 'meta_key' => 'status']);
                clean_post_cache($bid);
                wp_cache_delete($bid, 'post_meta');
                $bulk_count++;
            }
        }
        if ($bulk_count) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('%d request(s) updated to %s.', 'the-admin-vault'), $bulk_count, ucfirst($new_status))) . '</p></div>';
        }
    }
}

if (isset($_GET['notified']) && $_GET['notified'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Storytellers assigned. Status updated to Ready to Review and the client has been notified by email.', 'the-admin-vault') . '</p></div>';
}
if (isset($_GET['error']) && $_GET['error'] === 'not_paid') {
    echo '<div class="notice notice-error is-dismissible"><p>' . __('Cannot fulfill: this request has not been paid yet.', 'the-admin-vault') . '</p></div>';
}
?>

<div class="tav-page-header">
    <h1 class="tav-page-title"><?php esc_html_e('Search Requests', 'the-admin-vault'); ?></h1>
    <div class="tav-header-actions">
        <p class="tav-page-subtitle"><?php esc_html_e('Manage client search requests', 'the-admin-vault'); ?></p>
    </div>
</div>

<?php
$status_filter  = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
$date_from      = sanitize_text_field($_GET['date_from'] ?? '');
$date_to        = sanitize_text_field($_GET['date_to']   ?? '');
$selected_client = (int)($_GET['client_id'] ?? 0);
$selected_niche  = sanitize_text_field($_GET['niche'] ?? '');
$filter_statuses = [
    ''                => __('All Statuses', 'the-admin-vault'),
    'pending_payment' => __('Payment Pending', 'the-admin-vault'),
    'in_vetting'      => __('In Vetting', 'the-admin-vault'),
    'matching'        => __('Matching', 'the-admin-vault'),
    'ready_review'    => __('Ready to Review', 'the-admin-vault'),
    'assigned'        => __('Assigned', 'the-admin-vault'),
    'completed'       => __('Completed', 'the-admin-vault'),
    'archived'        => __('Archived', 'the-admin-vault'),
];
?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
    <form method="GET" style="display:flex; gap:8px; align-items:center; margin:0; flex-wrap:wrap;">
        <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
        <input type="hidden" name="view" value="requests">
        <select name="status_filter" style="padding:6px 10px; border-radius:4px; border:1px solid #ccc;">
            <?php foreach ($filter_statuses as $val => $label): ?>
                <option value="<?php echo esc_attr($val); ?>" <?php selected($status_filter, $val); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        $client_users = get_users([
            'role__in'   => ['um_client', 'client'],
            'orderby'    => 'display_name',
            'order'      => 'ASC',
            'fields'     => ['ID', 'display_name'],
        ]);
        ?>
        <select name="client_id" style="padding:6px 10px; border-radius:4px; border:1px solid #ccc; min-width:140px;">
            <option value=""><?php esc_html_e('All Clients', 'the-admin-vault'); ?></option>
            <?php foreach ($client_users as $u): ?>
                <option value="<?php echo (int)$u->ID; ?>" <?php selected($selected_client, $u->ID); ?>>
                    <?php echo esc_html($u->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php $niches = tav_get_niches(); ?>
        <select name="niche" style="padding:6px 10px; border-radius:4px; border:1px solid #ccc; min-width:120px;">
            <option value=""><?php esc_html_e('All Niches', 'the-admin-vault'); ?></option>
            <?php foreach ($niches as $niche_slug => $niche_name): ?>
                <option value="<?php echo esc_attr($niche_slug); ?>" <?php selected($selected_niche, $niche_slug); ?>>
                    <?php echo esc_html($niche_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="date_from" style="font-size:13px;"><?php esc_html_e('From', 'the-admin-vault'); ?></label>
        <input type="date" id="date_from" name="date_from"
            value="<?php echo esc_attr($date_from); ?>"
            style="padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:13px;">
        <label for="date_to" style="font-size:13px;"><?php esc_html_e('To', 'the-admin-vault'); ?></label>
        <input type="date" id="date_to" name="date_to"
            value="<?php echo esc_attr($date_to); ?>"
            style="padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:13px;">
        <button type="submit" class="button button-secondary"><?php esc_html_e('Filter', 'the-admin-vault'); ?></button>
        <?php if ($status_filter || $selected_client || $selected_niche || $date_from || $date_to): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=requests')); ?>" class="button"><?php esc_html_e('Clear', 'the-admin-vault'); ?></a>
        <?php endif; ?>
    </form>

    <div style="display:flex; gap:8px; align-items:center;" id="tav-bulk-bar">
        <select name="tav_bulk_action" form="tav-bulk-form" style="padding:6px 10px; border-radius:4px; border:1px solid #ccc;">
            <option value=""><?php esc_html_e('Bulk Actions', 'the-admin-vault'); ?></option>
            <option value="complete"><?php esc_html_e('Mark Complete', 'the-admin-vault'); ?></option>
            <option value="archive"><?php esc_html_e('Archive', 'the-admin-vault'); ?></option>
        </select>
        <button type="submit" form="tav-bulk-form" class="button button-secondary"><?php esc_html_e('Apply', 'the-admin-vault'); ?></button>
    </div>
</div>

<form method="POST" id="tav-bulk-form">
<?php wp_nonce_field('tav_bulk_requests', 'tav_bulk_nonce'); ?>

<div class="tav-boutique-table-wrap">
    <table class="tav-boutique-table">
        <thead>
            <tr>
                <th style="width:30px;"><input type="checkbox" id="tav-select-all"></th>
                <th><?php esc_html_e('PROJECT / CLIENT', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('STATUS', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('NICHE', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('DATES', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('SELECTION', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('TIER', 'the-admin-vault'); ?></th>
                <th class="actions"><span class="dashicons dashicons-ellipsis" title="<?php esc_attr_e('Actions', 'the-admin-vault'); ?>"></span></th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;
            $paged = max(1, (int)($_GET['paged'] ?? 1));
            $request_cpt = 'request'; 
            $client_filter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
            
            $query_args = [
                'post_type' => $request_cpt,
                'post_status' => 'any',
                'posts_per_page' => 20,
                'paged' => $paged,
                'orderby' => 'date',
                'order' => 'DESC',
            ];

            // Client filter (from dropdown; also accepts legacy URL param from clients view)
            $active_client = $selected_client ?: $client_filter;
            if ($active_client) {
                $query_args['author'] = $active_client;
            }

            // Status filter
            if (!empty($status_filter)) {
                $query_args['meta_query'][] = [
                    'key'     => 'status',
                    'value'   => $status_filter,
                    'compare' => '=',
                ];
            }

            // Niche filter
            if ($selected_niche) {
                $query_args['tax_query'][] = [
                    'taxonomy' => 'vs_niche',
                    'field'    => 'slug',
                    'terms'    => $selected_niche,
                ];
            }

            // Date range filter
            if ($date_from || $date_to) {
                $date_query = ['inclusive' => true];
                if ($date_from) $date_query['after']  = $date_from . ' 00:00:00';
                if ($date_to)   $date_query['before'] = $date_to   . ' 23:59:59';
                $query_args['date_query'] = [$date_query];
            }


            // Custom sorting: Paid requests first, then by date
            $sort_by_paid = function($orderby) use ($wpdb) {
                // We need to join postmeta to access 'status'
                // WP_Query will join postmeta if we use a meta query or meta_key,
                // but since we removed the meta query, we should ensure the join or use a simpler approach.
                // However, we can use a meta query just for the join without filtering.
                return "CASE WHEN {$wpdb->postmeta}.meta_value = 'paid' THEN 0 ELSE 1 END ASC, {$wpdb->posts}.post_date DESC";
            };

            $query_args['meta_query'] = [
                'relation' => 'AND',
                [
                    'key' => 'status',
                    'compare' => 'EXISTS', // Just to ensure the join
                ]
            ];

            add_filter('posts_orderby', $sort_by_paid);
            $req_query = new WP_Query($query_args);
            remove_filter('posts_orderby', $sort_by_paid);

            if ($req_query->have_posts()):
                while ($req_query->have_posts()): $req_query->the_post();
                    $req_id = get_the_ID();
                    
                    // Client: Post Author
                    $author_id = get_the_author_meta('ID');
                    $client_name = get_the_author_meta('display_name') ?: 'Unknown'; // Or use 'user_nicename'

                    // Status: 'status' meta (ACF)
                    $status = get_field('status') ?: 'pending';
                    
                    // Dates
                    $date_submitted = get_the_date('M j, Y');
                    $due_date = ''; // Not in meta yet, verified.
                    
                    // Tier
                    $tier = get_field('package_tier');

                    // Niche: Taxonomy
                    $req_niches = wp_get_post_terms($req_id, 'vs_niche', ['fields' => 'names']);
                    $niche_display = !empty($req_niches) ? implode(', ', $req_niches) : '—';
                    
                    // Custom Logic for auto assigned status
                    $client_selected = get_field('client_selected_storytellers', $req_id) ?: get_post_meta($req_id, 'client_selected_storytellers', true);
                    if (!empty($client_selected)) {
                        $status = 'assigned';
                    }

                    $status_labels = [
                        'pending_payment' => __('Payment Pending', 'the-admin-vault'),
                        'pending'         => __('Pending',         'the-admin-vault'),
                        'paid'            => __('Paid',            'the-admin-vault'),
                        'in_vetting'      => __('In Vetting',      'the-admin-vault'),
                        'matching'        => __('Matching',        'the-admin-vault'),
                        'ready_review'    => __('Ready to Review', 'the-admin-vault'),
                        'assigned'        => __('Assigned',        'the-admin-vault'),
                        'completed'       => __('Completed',       'the-admin-vault'),
                        'archived'        => __('Archived',        'the-admin-vault'),
                        'enterprise_inquiry' => __('Enterprise Inquiry', 'the-admin-vault'),
                    ];
                    $status_label = $status_labels[$status] ?? ucwords(str_replace('_', ' ', $status));

                    // Selection column display
                    $selection_display = '—';
                    if (!empty($client_selected)) {
                        $selected_arr = is_array($client_selected) ? $client_selected : [$client_selected];
                        $names = [];
                        foreach ($selected_arr as $st) {
                            if (is_object($st)) {
                                $names[] = $st->post_title;
                            } else {
                                $names[] = get_the_title($st);
                            }
                        }
                        $selection_display = implode(', ', $names);
                    } else {
                        $admin_assigned = get_field('storytellers', $req_id) ?: get_post_meta($req_id, 'storytellers', true) ?: (get_post_meta($req_id, 'assigned_storytellers', true) ?: []);
                        $count = is_array($admin_assigned) ? count($admin_assigned) : 0;
                        $selection_display = $count ? $count : '—';
                    }

                    // Dynamic row class for status colour-coding.
                    $status_class_map = [
                        'pending_payment' => 'status-pending-payment',
                        'paid'            => 'status-paid',
                        'in_vetting'      => 'status-in-vetting',
                        'matching'        => 'status-matching',
                        'ready_review'    => 'status-ready-review',
                        'assigned'        => 'status-assigned',
                        'completed'       => 'status-completed',
                        'archived'        => 'status-archived',
                    ];
                    $status_class = $status_class_map[$status] ?? 'status-default';
            ?>
                    <tr class="<?php echo esc_attr($status_class); ?>">
                        <td><input type="checkbox" name="tav_bulk_ids[]" value="<?php echo esc_attr($req_id); ?>"></td>
                        <td>
                            <div class="tav-cell-name">
                                <span class="tav-name-text"><?php echo esc_html(get_the_title()); ?></span>
                                <span class="tav-cell-secondary" style="display:block; font-size:12px;"><?php echo esc_html($client_name); ?></span>
                            </div>
                        </td>
                        <td><span class="tav-pill"><?php echo esc_html($status_label); ?></span></td>
                        <td><span class="tav-cell-secondary"><?php echo esc_html($niche_display); ?></span></td>
                        <td>
                            <?php
                                $due_date = get_post_meta($req_id, 'due_date', true);
                                $due_display = !empty($due_date) ? date_i18n('M j, Y', strtotime($due_date)) : '—';
                            ?>
                            <div style="font-size:13px;">
                                <div><strong>Submitted:</strong> <?php echo esc_html($date_submitted); ?></div>
                                <div><strong>Due:</strong> <?php echo esc_html($due_display); ?></div>
                            </div>
                        </td>
                        <td><span class="tav-cell-primary" style="font-size: 13px; line-height: 1.4; display: block; max-width: 200px;"><?php echo $selection_display; ?></span></td>
                        <td><span class="tav-cell-secondary"><?php echo esc_html($tier ? ucfirst($tier) : '—'); ?></span></td>
                        <td class="actions">
                            <div class="tav-actions-wrap">
                                <?php if (in_array($status, ['paid', 'in_vetting'], true)): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(
                                        admin_url('admin.php?page=' . $current_page_slug . '&view=requests&tav_start_matching=' . $req_id),
                                        'tav_match_' . $req_id
                                    )); ?>"
                                       class="tav-btn-icon"
                                       title="<?php esc_attr_e('Start Matching', 'the-admin-vault'); ?>"
                                       aria-label="<?php esc_attr_e('Start Matching', 'the-admin-vault'); ?>">
                                        <span class="dashicons dashicons-search"></span>
                                    </a>
                                <?php endif; ?>
                                <?php if (in_array($status, ['paid', 'in_vetting', 'matching', 'ready_review'], true)): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=fulfill&request_id=' . $req_id)); ?>" 
                                       class="tav-btn-icon-primary" 
                                       title="<?php esc_attr_e('Fulfill Request', 'the-admin-vault'); ?>"
                                       aria-label="<?php esc_attr_e('Fulfill Request', 'the-admin-vault'); ?>">
                                        <span class="dashicons dashicons-share-alt2"></span>
                                    </a>
                                <?php endif; ?>
                                <?php if (in_array($status, ['pending_payment', 'in_vetting', 'matching', 'ready_review'], true)): ?>
                                    <button type="button"
                                            class="tav-btn-icon tav-open-request-modal"
                                            data-modal="tav-brief-modal-<?php echo esc_attr($req_id); ?>"
                                            title="<?php esc_attr_e('View Brief', 'the-admin-vault'); ?>"
                                            aria-label="<?php esc_attr_e('View Brief', 'the-admin-vault'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                <?php endif; ?>
                                <?php if ($status === 'assigned'): ?>
                                    <button type="button"
                                            class="tav-btn tav-btn-primary tav-open-request-modal"
                                            data-modal="tav-req-modal-<?php echo esc_attr($req_id); ?>"
                                            style="white-space:nowrap; border:none; cursor:pointer; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600;">
                                        <?php esc_html_e('View Match', 'the-admin-vault'); ?>
                                    </button>
                                    <button type="button"
                                            class="tav-btn-icon tav-open-request-modal"
                                            data-modal="tav-req-modal-<?php echo esc_attr($req_id); ?>"
                                            title="<?php esc_attr_e('View Brief', 'the-admin-vault'); ?>"
                                            aria-label="<?php esc_attr_e('View Brief', 'the-admin-vault'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=' . $current_page_slug . '&view=requests&tav_set_complete=' . $req_id), 'tav_complete_' . $req_id)); ?>"
                                       class="tav-btn-icon"
                                       title="<?php esc_attr_e('Mark Complete', 'the-admin-vault'); ?>"
                                       aria-label="<?php esc_attr_e('Mark Complete', 'the-admin-vault'); ?>"
                                       onclick="return confirm('<?php esc_attr_e('Mark this request as completed?', 'the-admin-vault'); ?>');">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($status === 'completed'): ?>
                                    <button type="button"
                                            class="tav-btn-icon tav-open-request-modal"
                                            data-modal="tav-brief-modal-<?php echo esc_attr($req_id); ?>"
                                            title="<?php esc_attr_e('View Details', 'the-admin-vault'); ?>"
                                            aria-label="<?php esc_attr_e('View Details', 'the-admin-vault'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <span><?php esc_html_e('View Details', 'the-admin-vault'); ?></span>
                                    </button>
                                <?php endif; ?>
                                <?php if ($status === 'archived'): ?>
                                    <span class="tav-cell-secondary"><?php esc_html_e('Archived', 'the-admin-vault'); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
            <?php
                endwhile;
                wp_reset_postdata();
            else:
            ?>
                <tr>
                    <td colspan="8" class="tav-empty"><?php esc_html_e('No requests found.', 'the-admin-vault'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($req_query->max_num_pages > 1): ?>
    <div class="tav-pagination">
        <?php
        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'total' => $req_query->max_num_pages,
            'current' => $paged,
        ]);
        ?>
    </div>
<?php endif; ?>
</form><!-- /tav-bulk-form -->

<?php
/* ─────────────────────────────────────────────────────────────
 * Pre-rendered "View Brief" modals for NON-assigned requests
 * ───────────────────────────────────────────────────────────── */
if ($req_query->have_posts()) :
    $req_query->rewind_posts();
    while ($req_query->have_posts()) : $req_query->the_post();
        $brief_req_id = get_the_ID();

        $brief_status = get_post_meta($brief_req_id, 'status', true);
        $brief_client_selected = get_field('client_selected_storytellers', $brief_req_id) ?: get_post_meta($brief_req_id, 'client_selected_storytellers', true);
        // Completed rows always get a brief modal so admins can review what was delivered.
        // For other allowed statuses, skip rows where the client has already picked
        // (those open the "assigned" details modal instead).
        if ($brief_status !== 'completed' && !empty($brief_client_selected)) continue;
        if (!in_array($brief_status, ['pending_payment', 'in_vetting', 'matching', 'ready_review', 'paid', 'completed'], true)) continue;

        $b_author_id    = (int) get_post_field('post_author', $brief_req_id);
        $b_client_name  = get_the_author_meta('display_name', $b_author_id) ?: 'Unknown';
        $b_client_email = get_the_author_meta('user_email', $b_author_id);
        $b_date         = get_the_date('F j, Y');
        $b_tier         = get_field('package_tier', $brief_req_id);
        $b_goal         = get_field('campaign_goal', $brief_req_id);
        $b_location     = get_field('location', $brief_req_id);
        $b_timeline     = get_field('timeline', $brief_req_id);
        $b_audience     = get_field('audience_size', $brief_req_id);
        $b_addons       = get_field('addons', $brief_req_id);
        $b_special      = get_field('special_requirements', $brief_req_id);
        $b_niches       = wp_get_post_terms($brief_req_id, 'vs_niche', ['fields' => 'names']);
        $b_niche_str    = !empty($b_niches) ? implode(', ', $b_niches) : '';

        $b_tier_labels = [
            'quick' => 'Quick Match — $400', 'custom' => 'Custom Search — $600',
            'premium' => 'Premium Search — $900', 'retainer' => 'Monthly Retainer — $1,800/mo',
            'enterprise' => 'Enterprise — Custom Pricing',
        ];
        $b_tier_label = $b_tier_labels[$b_tier] ?? ucfirst($b_tier ?: '—');

        $b_addon_labels = ['rush' => 'Rush Delivery (+$200)', 'extra' => 'Extra Matches (+$150)', 'strategy' => 'Strategy Call (+$100)'];
        $b_addon_str = '';
        if (is_array($b_addons) && !empty($b_addons)) {
            $b_addon_str = implode(', ', array_map(fn($a) => $b_addon_labels[$a] ?? ucfirst($a), $b_addons));
        }

        $b_status_labels = [
            'pending_payment' => 'Payment Pending', 'paid' => 'Paid', 'in_vetting' => 'In Vetting',
            'matching' => 'Matching', 'ready_review' => 'Ready to Review', 'completed' => 'Completed',
        ];
        $b_status_label = $b_status_labels[$brief_status] ?? ucwords(str_replace('_', ' ', $brief_status));
        ?>

        <div id="tav-brief-modal-<?php echo esc_attr($brief_req_id); ?>" class="tav-modal" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(get_the_title()); ?>">
            <div class="tav-modal-overlay tav-close-request-modal"></div>
            <div class="tav-modal-container tav-req-modal-container">
                <div class="tav-modal-header">
                    <div>
                        <h2><?php echo esc_html(get_the_title() ?: 'Request #' . $brief_req_id); ?></h2>
                        <p class="tav-req-modal-sub">
                            <span class="tav-pill" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.5px;"><?php echo esc_html($b_status_label); ?></span>
                            &nbsp;·&nbsp; <?php echo esc_html($b_date); ?>
                        </p>
                    </div>
                    <button type="button" class="tav-modal-close tav-close-request-modal" aria-label="Close">&times;</button>
                </div>
                <div class="tav-modal-body">
                    <div class="tav-req-section">
                        <h3 class="tav-req-section-title">Client Brief</h3>
                        <div class="tav-req-brief-grid">
                            <div class="tav-req-brief-item">
                                <span class="tav-req-brief-label">Client</span>
                                <span class="tav-req-brief-value"><?php echo esc_html($b_client_name); ?>
                                    <?php if ($b_client_email): ?><a href="mailto:<?php echo esc_attr($b_client_email); ?>" class="tav-req-email-link"><?php echo esc_html($b_client_email); ?></a><?php endif; ?>
                                </span>
                            </div>
                            <?php if ($b_tier): ?>
                            <div class="tav-req-brief-item"><span class="tav-req-brief-label">Package</span><span class="tav-req-brief-value"><?php echo esc_html($b_tier_label); ?></span></div>
                            <?php endif; ?>
                            <?php if ($b_location): ?>
                            <div class="tav-req-brief-item"><span class="tav-req-brief-label">Location</span><span class="tav-req-brief-value"><?php echo esc_html($b_location); ?></span></div>
                            <?php endif; ?>
                            <?php if ($b_niche_str): ?>
                            <div class="tav-req-brief-item"><span class="tav-req-brief-label">Niche</span><span class="tav-req-brief-value"><?php echo esc_html($b_niche_str); ?></span></div>
                            <?php endif; ?>
                            <?php if ($b_audience): ?>
                            <div class="tav-req-brief-item"><span class="tav-req-brief-label">Audience Size</span><span class="tav-req-brief-value"><?php echo esc_html(ucfirst(str_replace('_', ' ', $b_audience))); ?></span></div>
                            <?php endif; ?>
                            <?php if ($b_timeline): ?>
                            <div class="tav-req-brief-item"><span class="tav-req-brief-label">Timeline</span><span class="tav-req-brief-value"><?php echo esc_html(str_replace('_', ' ', $b_timeline)); ?></span></div>
                            <?php endif; ?>
                            <?php if ($b_addon_str): ?>
                            <div class="tav-req-brief-item tav-req-brief-item--wide"><span class="tav-req-brief-label">Add-ons</span><span class="tav-req-brief-value"><?php echo esc_html($b_addon_str); ?></span></div>
                            <?php endif; ?>
                            <?php if ($b_goal): ?>
                            <div class="tav-req-brief-item tav-req-brief-item--wide"><span class="tav-req-brief-label">Campaign Goal</span><span class="tav-req-brief-value"><?php echo esc_html($b_goal); ?></span></div>
                            <?php endif; ?>
                            <?php if ($b_special): ?>
                            <div class="tav-req-brief-item tav-req-brief-item--wide"><span class="tav-req-brief-label">Special Requirements</span><span class="tav-req-brief-value"><?php echo esc_html($b_special); ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php endwhile;
    wp_reset_postdata();
endif;
?>

<?php
/* ─────────────────────────────────────────────────────────────
 * Pre-rendered modals for each ASSIGNED request
 * ───────────────────────────────────────────────────────────── */
if ($req_query->have_posts()) :
    $req_query->rewind_posts();
    while ($req_query->have_posts()) : $req_query->the_post();
        $modal_req_id = get_the_ID();

        // Only render modals for assigned requests
        $modal_selected  = get_field('client_selected_storytellers', $modal_req_id)
                           ?: get_post_meta($modal_req_id, 'client_selected_storytellers', true);
        if (empty($modal_selected)) {
            continue;
        }

        // Request meta
        $modal_author_id   = (int) get_post_field('post_author', $modal_req_id);
        $modal_client_name = get_the_author_meta('display_name', $modal_author_id) ?: 'Unknown';
        $modal_client_email= get_the_author_meta('user_email', $modal_author_id);
        $modal_status_lbl  = __('Assigned', 'the-admin-vault');
        $modal_date        = get_the_date('F j, Y');
        $modal_tier        = get_field('package_tier', $modal_req_id);
        $modal_goal        = get_field('campaign_goal', $modal_req_id);
        $modal_location    = get_field('location', $modal_req_id);
        $modal_timeline    = get_field('timeline', $modal_req_id);
        $modal_audience    = get_field('audience_size', $modal_req_id);
        $modal_addons      = get_field('addons', $modal_req_id);
        $modal_special     = get_field('special_requirements', $modal_req_id);
        $modal_niches      = wp_get_post_terms($modal_req_id, 'vs_niche', ['fields' => 'names']);
        $modal_niche_str   = !empty($modal_niches) ? implode(', ', $modal_niches) : '';

        // Tier label map
        $tier_labels = [
            'quick'      => 'Quick Match — $400',
            'custom'     => 'Custom Search — $600',
            'premium'    => 'Premium Search — $900',
            'retainer'   => 'Monthly Retainer — $1,800/mo',
            'enterprise' => 'Enterprise — Custom Pricing',
        ];
        $modal_tier_label = $tier_labels[$modal_tier] ?? ucfirst($modal_tier ?: '—');

        // Addon label map
        $addon_labels = [
            'rush'     => 'Rush Delivery (+$200)',
            'extra'    => 'Extra Matches (+$150)',
            'strategy' => 'Strategy Call (+$100)',
        ];
        $modal_addon_str = '';
        if (is_array($modal_addons) && !empty($modal_addons)) {
            $modal_addon_str = implode(', ', array_map(
                fn($a) => $addon_labels[$a] ?? ucfirst($a),
                $modal_addons
            ));
        }

        // Storytellers
        $modal_st_ids = is_array($modal_selected) ? $modal_selected : [$modal_selected];
        ?>

        <!-- Modal: Request #<?php echo $modal_req_id; ?> -->
        <div id="tav-req-modal-<?php echo esc_attr($modal_req_id); ?>" class="tav-modal" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(get_the_title()); ?>">
            <div class="tav-modal-overlay tav-close-request-modal"></div>
            <div class="tav-modal-container tav-req-modal-container">

                <!-- Header -->
                <div class="tav-modal-header">
                    <div>
                        <h2><?php echo esc_html(get_the_title()); ?></h2>
                        <p class="tav-req-modal-sub">
                            <span class="tav-pill" style="color:#0369a1;font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.5px;">Assigned</span>
                            &nbsp;·&nbsp; <?php echo esc_html($modal_date); ?>
                        </p>
                    </div>
                    <button type="button" class="tav-modal-close tav-close-request-modal" aria-label="Close">&times;</button>
                </div>

                <!-- Scrollable body -->
                <div class="tav-modal-body">

                    <!-- ① Request Brief -->
                    <div class="tav-req-section">
                        <h3 class="tav-req-section-title">Request Brief</h3>
                        <div class="tav-req-brief-grid">

                            <div class="tav-req-brief-item">
                                <span class="tav-req-brief-label">Client</span>
                                <span class="tav-req-brief-value">
                                    <?php echo esc_html($modal_client_name); ?>
                                    <?php if ($modal_client_email): ?>
                                        <a href="mailto:<?php echo esc_attr($modal_client_email); ?>" class="tav-req-email-link"><?php echo esc_html($modal_client_email); ?></a>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ($modal_tier): ?>
                            <div class="tav-req-brief-item">
                                <span class="tav-req-brief-label">Package</span>
                                <span class="tav-req-brief-value"><?php echo esc_html($modal_tier_label); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($modal_location): ?>
                            <div class="tav-req-brief-item">
                                <span class="tav-req-brief-label">Location</span>
                                <span class="tav-req-brief-value"><?php echo esc_html($modal_location); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($modal_niche_str): ?>
                            <div class="tav-req-brief-item">
                                <span class="tav-req-brief-label">Niche</span>
                                <span class="tav-req-brief-value"><?php echo esc_html($modal_niche_str); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($modal_audience): ?>
                            <div class="tav-req-brief-item">
                                <span class="tav-req-brief-label">Audience Size</span>
                                <span class="tav-req-brief-value"><?php echo esc_html(ucfirst(str_replace('_', ' ', $modal_audience))); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($modal_timeline): ?>
                            <div class="tav-req-brief-item">
                                <span class="tav-req-brief-label">Timeline</span>
                                <span class="tav-req-brief-value"><?php echo esc_html(str_replace('_', ' ', $modal_timeline)); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($modal_addon_str): ?>
                            <div class="tav-req-brief-item tav-req-brief-item--wide">
                                <span class="tav-req-brief-label">Add-ons</span>
                                <span class="tav-req-brief-value"><?php echo esc_html($modal_addon_str); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($modal_goal): ?>
                            <div class="tav-req-brief-item tav-req-brief-item--wide">
                                <span class="tav-req-brief-label">Campaign Goal</span>
                                <span class="tav-req-brief-value"><?php echo esc_html($modal_goal); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($modal_special): ?>
                            <div class="tav-req-brief-item tav-req-brief-item--wide">
                                <span class="tav-req-brief-label">Special Requirements</span>
                                <span class="tav-req-brief-value"><?php echo esc_html($modal_special); ?></span>
                            </div>
                            <?php endif; ?>

                        </div><!-- /.tav-req-brief-grid -->
                    </div><!-- /.tav-req-section -->

                    <!-- ② Assigned Storytellers -->
                    <div class="tav-req-section">
                        <h3 class="tav-req-section-title">
                            Client-Selected Storytellers
                            <span class="tav-req-st-count"><?php echo count($modal_st_ids); ?></span>
                        </h3>

                        <?php foreach ($modal_st_ids as $raw_st) :
                            $st_id = is_object($raw_st) ? $raw_st->ID : intval($raw_st);
                            if (!$st_id) continue;

                            $st_name     = get_the_title($st_id);
                            $st_bio      = get_field('bio', $st_id);
                            $st_location = get_field('location', $st_id);
                            $st_email    = get_field('private_contact', $st_id);
                            $st_img_id   = get_field('profile_image', $st_id);
                            $st_img_url  = $st_img_id ? wp_get_attachment_image_url($st_img_id, 'thumbnail') : '';
                            $st_initials = strtoupper(mb_substr($st_name, 0, 1));

                            $st_niches = wp_get_post_terms($st_id, 'vs_niche', ['fields' => 'names']);
                        ?>
                        <div class="tav-req-st-card">

                            <!-- Photo + headline -->
                            <div class="tav-req-st-header">
                                <?php if ($st_img_url): ?>
                                    <img src="<?php echo esc_url($st_img_url); ?>" alt="<?php echo esc_attr($st_name); ?>" class="tav-req-st-photo">
                                <?php else: ?>
                                    <div class="tav-req-st-photo tav-req-st-photo--placeholder"><?php echo esc_html($st_initials); ?></div>
                                <?php endif; ?>

                                <div class="tav-req-st-meta">
                                    <strong class="tav-req-st-name"><?php echo esc_html($st_name); ?></strong>
                                    <?php if ($st_location): ?>
                                        <span class="tav-req-st-location">
                                            <span class="dashicons dashicons-location"></span>
                                            <?php echo esc_html($st_location); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($st_email): ?>
                                        <a href="mailto:<?php echo esc_attr($st_email); ?>" class="tav-req-st-email">
                                            <span class="dashicons dashicons-email-alt"></span>
                                            <?php echo esc_html($st_email); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($st_niches)): ?>
                                        <div class="tav-req-st-niches">
                                            <?php foreach ($st_niches as $nt): ?>
                                                <span class="tav-req-st-niche-tag"><?php echo esc_html($nt); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <a href="<?php echo esc_url(get_edit_post_link($st_id)); ?>" class="tav-btn-secondary tav-req-st-edit-btn" target="_blank" title="Edit Storyteller">
                                    <span class="dashicons dashicons-edit"></span> Edit
                                </a>
                            </div><!-- /.tav-req-st-header -->

                            <?php if ($st_bio): ?>
                            <p class="tav-req-st-bio"><?php echo esc_html($st_bio); ?></p>
                            <?php endif; ?>

                            <!-- Platforms -->
                            <?php if (have_rows('platforms_repeater', $st_id)): ?>
                            <div class="tav-req-st-platforms">
                                <?php while (have_rows('platforms_repeater', $st_id)): the_row();
                                    $plat_name   = get_sub_field('platform_name');
                                    $handle      = get_sub_field('handle');
                                    $followers   = get_sub_field('follower_count');
                                    $engagement  = get_sub_field('engagement_rate');
                                    $profile_url = get_sub_field('profile_url');
                                ?>
                                <div class="tav-req-st-platform-chip">
                                    <div class="tav-req-st-platform-name"><?php echo esc_html(ucfirst($plat_name)); ?></div>
                                    <?php if ($handle): ?>
                                        <?php if ($profile_url): ?>
                                            <a href="<?php echo esc_url($profile_url); ?>" target="_blank" rel="noopener" class="tav-req-st-platform-handle">@<?php echo esc_html($handle); ?></a>
                                        <?php else: ?>
                                            <span class="tav-req-st-platform-handle">@<?php echo esc_html($handle); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="tav-req-st-platform-stats">
                                        <?php if ($followers): ?><span><?php echo esc_html(number_format($followers)); ?> followers</span><?php endif; ?>
                                        <?php if ($engagement): ?><span><?php echo esc_html($engagement); ?>% eng.</span><?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Sample Work -->
                            <?php if (have_rows('sample_work', $st_id)): ?>
                            <div class="tav-req-st-samples">
                                <p class="tav-req-st-samples-label">Sample Work</p>
                                <?php while (have_rows('sample_work', $st_id)): the_row();
                                    $sw_title    = get_sub_field('content_title');
                                    $sw_platform = get_sub_field('platform');
                                    $sw_views    = get_sub_field('view_count');
                                    $sw_url      = get_sub_field('url');
                                ?>
                                <div class="tav-st-sample-item">
                                    <div class="tav-st-sample-info">
                                        <?php if ($sw_url): ?>
                                            <a href="<?php echo esc_url($sw_url); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html($sw_title ?: 'View'); ?></strong></a>
                                        <?php else: ?>
                                            <strong><?php echo esc_html($sw_title ?: 'Untitled'); ?></strong>
                                        <?php endif; ?>
                                        <span>
                                            <?php echo esc_html(ucfirst($sw_platform)); ?>
                                            <?php if ($sw_views): ?>&nbsp;· <?php echo esc_html(number_format($sw_views)); ?> views<?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($sw_url): ?>
                                        <a href="<?php echo esc_url($sw_url); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span></a>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php endif; ?>

                        </div><!-- /.tav-req-st-card -->
                        <?php endforeach; ?>

                    </div><!-- /.tav-req-section -->
                </div><!-- /.tav-modal-body -->
            </div><!-- /.tav-modal-container -->
        </div><!-- /.tav-modal -->

    <?php endwhile;
    wp_reset_postdata();
endif; ?>

<!-- Modal JS -->
<script>
(function(){
    // Open
    document.querySelectorAll('.tav-open-request-modal').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = this.getAttribute('data-modal');
            var modal = document.getElementById(id);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        });
    });

    // Close (overlay click or × button)
    document.querySelectorAll('.tav-close-request-modal').forEach(function(el){
        el.addEventListener('click', function(){
            var modal = this.closest('.tav-modal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    });

    // Escape key
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            document.querySelectorAll('.tav-modal[style*="display: block"]').forEach(function(m){
                m.style.display = 'none';
            });
            document.body.style.overflow = '';
        }
    });

    // Select all checkbox
    var selectAll = document.getElementById('tav-select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            var boxes = document.querySelectorAll('input[name="tav_bulk_ids[]"]');
            boxes.forEach(function(cb){ cb.checked = selectAll.checked; });
        });
    }
})();
</script>
