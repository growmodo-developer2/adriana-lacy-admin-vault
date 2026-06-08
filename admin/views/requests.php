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

<div class="tav-page-header tav-requests-header">
    <div class="tav-requests-header-left">
        <h1 class="tav-page-title"><?php esc_html_e('Search Requests Management', 'the-admin-vault'); ?></h1>
        <p class="tav-page-subtitle"><?php esc_html_e('Manage and track all client storyteller search requests', 'the-admin-vault'); ?></p>
    </div>
</div>

<?php
$status_filter  = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
$date_from      = sanitize_text_field($_GET['date_from'] ?? '');
$date_to        = sanitize_text_field($_GET['date_to']   ?? '');
$selected_client = (int)($_GET['client_id'] ?? 0);
$selected_niche  = sanitize_text_field($_GET['niche'] ?? '');
$search_query   = isset($_GET['req_search']) ? sanitize_text_field($_GET['req_search']) : '';
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
<!-- Search and Filters Bar -->
<div class="tav-requests-toolbar">
    <form method="GET" class="tav-requests-search-form">
        <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
        <input type="hidden" name="view" value="requests">
        <?php if ($status_filter): ?><input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
        <?php if ($selected_client): ?><input type="hidden" name="client_id" value="<?php echo esc_attr($selected_client); ?>"><?php endif; ?>
        <?php if ($selected_niche): ?><input type="hidden" name="niche" value="<?php echo esc_attr($selected_niche); ?>"><?php endif; ?>
        <div class="tav-search-input-wrap">
            <svg class="tav-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" name="req_search" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search', 'the-admin-vault'); ?>" class="tav-requests-search">
        </div>
    </form>
    
    <button type="button" class="tav-filters-toggle" id="tav-toggle-filters">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
        <?php esc_html_e('Filters', 'the-admin-vault'); ?>
    </button>
</div>

<!-- Collapsible Advanced Filters -->
<div class="tav-advanced-filters" id="tav-advanced-filters" style="display: none;">
    <form method="GET" class="tav-filters-form">
        <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
        <input type="hidden" name="view" value="requests">
        <?php if ($search_query): ?><input type="hidden" name="req_search" value="<?php echo esc_attr($search_query); ?>"><?php endif; ?>
        
        <div class="tav-filter-group">
            <label><?php esc_html_e('Status', 'the-admin-vault'); ?></label>
            <select name="status_filter">
                <?php foreach ($filter_statuses as $val => $label): ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($status_filter, $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="tav-filter-group">
            <label><?php esc_html_e('Client', 'the-admin-vault'); ?></label>
            <?php
            $client_users = get_users([
                'role__in'   => ['um_client', 'client'],
                'orderby'    => 'display_name',
                'order'      => 'ASC',
                'fields'     => ['ID', 'display_name'],
            ]);
            ?>
            <select name="client_id">
                <option value=""><?php esc_html_e('All Clients', 'the-admin-vault'); ?></option>
                <?php foreach ($client_users as $u): ?>
                    <option value="<?php echo (int)$u->ID; ?>" <?php selected($selected_client, $u->ID); ?>>
                        <?php echo esc_html($u->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="tav-filter-group">
            <label><?php esc_html_e('Niche', 'the-admin-vault'); ?></label>
            <?php $niches = tav_get_niches(); ?>
            <select name="niche">
                <option value=""><?php esc_html_e('All Niches', 'the-admin-vault'); ?></option>
                <?php foreach ($niches as $niche_slug => $niche_name): ?>
                    <option value="<?php echo esc_attr($niche_slug); ?>" <?php selected($selected_niche, $niche_slug); ?>>
                        <?php echo esc_html($niche_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="tav-filter-group">
            <label><?php esc_html_e('Date Range', 'the-admin-vault'); ?></label>
            <div class="tav-date-range">
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php esc_attr_e('From', 'the-admin-vault'); ?>">
                <span>-</span>
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php esc_attr_e('To', 'the-admin-vault'); ?>">
            </div>
        </div>
        
        <div class="tav-filter-actions">
            <button type="submit" class="tav-btn-filter"><?php esc_html_e('Apply Filters', 'the-admin-vault'); ?></button>
            <?php if ($status_filter || $selected_client || $selected_niche || $date_from || $date_to): ?>
                <a href="<?php echo esc_url(tav_get_dashboard_view_url('requests', $search_query ? ['req_search' => $search_query] : [])); ?>" class="tav-btn-clear"><?php esc_html_e('Clear', 'the-admin-vault'); ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<form method="POST" id="tav-bulk-form">
<?php wp_nonce_field('tav_bulk_requests', 'tav_bulk_nonce'); ?>

<div class="tav-boutique-table-wrap tav-requests-table-wrap">
    <table class="tav-boutique-table tav-requests-table">
        <thead>
            <tr>
                <th class="tav-col-project"><?php esc_html_e('Project Name', 'the-admin-vault'); ?> <span class="tav-sort-icon">&#8597;</span></th>
                <th class="tav-col-client"><?php esc_html_e('Client', 'the-admin-vault'); ?> <span class="tav-sort-icon">&#8597;</span></th>
                <th class="tav-col-status"><?php esc_html_e('Status', 'the-admin-vault'); ?> <span class="tav-sort-icon">&#8597;</span></th>
                <th class="tav-col-submitted"><?php esc_html_e('Date Submitted', 'the-admin-vault'); ?> <span class="tav-sort-icon">&#8597;</span></th>
                <th class="tav-col-due"><?php esc_html_e('Date Due', 'the-admin-vault'); ?> <span class="tav-sort-icon">&#8597;</span></th>
                <th class="tav-col-actions"><?php esc_html_e('Actions', 'the-admin-vault'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;
            $paged = max(1, (int)($_GET['paged'] ?? 1));
            $posts_per_page = 10;
            $request_cpt = 'request'; 
            $client_filter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
            
            $query_args = [
                'post_type' => $request_cpt,
                'post_status' => 'any',
                'posts_per_page' => $posts_per_page,
                'paged' => $paged,
                'orderby' => 'date',
                'order' => 'DESC',
            ];

            // Search filter
            if (!empty($search_query)) {
                $query_args['s'] = $search_query;
            }

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
                return "CASE WHEN {$wpdb->postmeta}.meta_value = 'paid' THEN 0 ELSE 1 END ASC, {$wpdb->posts}.post_date DESC";
            };

            $query_args['meta_query'] = [
                'relation' => 'AND',
                [
                    'key' => 'status',
                    'compare' => 'EXISTS',
                ]
            ];

            add_filter('posts_orderby', $sort_by_paid);
            $req_query = new WP_Query($query_args);
            remove_filter('posts_orderby', $sort_by_paid);
            
            $total_items = $req_query->found_posts;
            $total_pages = $req_query->max_num_pages;

            if ($req_query->have_posts()):
                while ($req_query->have_posts()): $req_query->the_post();
                    $req_id = get_the_ID();
                    
                    // Project Name: Try to find it from available data
                    $post_obj = get_post($req_id);
                    $project_name = $post_obj->post_title ?? '';
                    $name_not_available = false;
                    $project_name_full = ''; // Store full text for tooltip
                    
                    // If title is empty or generic, try to use campaign_goal from project_brief
                    if (empty(trim($project_name)) || $project_name === 'Auto Draft') {
                        // Try project_brief group field first (nested structure)
                        $project_brief = get_field('project_brief', $req_id);
                        $campaign_goal = '';
                        
                        if (is_array($project_brief) && !empty($project_brief['campaign_goal'])) {
                            $campaign_goal = $project_brief['campaign_goal'];
                        } else {
                            // Fallback: try direct campaign_goal field
                            $campaign_goal = get_field('campaign_goal', $req_id);
                        }
                        
                        if (!empty($campaign_goal)) {
                            $project_name_full = $campaign_goal;
                            $project_name = $campaign_goal; // CSS will handle truncation
                        } else {
                            $project_name = __('Name not available', 'the-admin-vault');
                            $name_not_available = true;
                        }
                    }
                    
                    // Client: Post Author
                    $author_id = get_the_author_meta('ID');
                    $client_name = get_the_author_meta('display_name') ?: 'Unknown';

                    // Status: 'status' meta (ACF)
                    $status = get_field('status') ?: 'pending';
                    
                    // Dates
                    $date_submitted = get_the_date('Y-m-d');
                    $due_date_raw = get_post_meta($req_id, 'due_date', true);
                    $due_date = !empty($due_date_raw) ? date('Y-m-d', strtotime($due_date_raw)) : '—';
                    
                    // Custom Logic for auto assigned status
                    $client_selected = get_field('client_selected_storytellers', $req_id) ?: get_post_meta($req_id, 'client_selected_storytellers', true);
                    if (!empty($client_selected)) {
                        $status = 'assigned';
                    }

                    // Map each status to unique label and class
                    $status_config = [
                        'pending_payment' => ['label' => 'Awaiting Payment', 'class' => 'tav-status-pending-payment'],
                        'pending'         => ['label' => 'Pending', 'class' => 'tav-status-pending'],
                        'paid'            => ['label' => 'Paid', 'class' => 'tav-status-paid'],
                        'in_vetting'      => ['label' => 'In Vetting', 'class' => 'tav-status-vetting'],
                        'matching'        => ['label' => 'Matching', 'class' => 'tav-status-matching'],
                        'ready_review'    => ['label' => 'Ready for Review', 'class' => 'tav-status-ready-review'],
                        'assigned'        => ['label' => 'Assigned', 'class' => 'tav-status-assigned'],
                        'completed'       => ['label' => 'Completed', 'class' => 'tav-status-completed'],
                        'archived'        => ['label' => 'Archived', 'class' => 'tav-status-archived'],
                    ];
                    $status_info = $status_config[$status] ?? ['label' => ucwords(str_replace('_', ' ', $status)), 'class' => 'tav-status-pending'];
            ?>
                    <tr>
                        <td class="tav-col-project">
                            <div class="tav-project-cell">
                                <span class="tav-project-indicator"></span>
                                <span class="tav-project-name <?php echo $name_not_available ? 'tav-project-name--unavailable' : ''; ?>" 
                                      <?php if (!empty($project_name_full)): ?>title="<?php echo esc_attr($project_name_full); ?>"<?php endif; ?>>
                                    <?php echo esc_html($project_name); ?>
                                </span>
                            </div>
                        </td>
                        <td class="tav-col-client"><?php echo esc_html($client_name); ?></td>
                        <td class="tav-col-status">
                            <span class="tav-status-badge <?php echo esc_attr($status_info['class']); ?>">
                                <span class="tav-status-dot"></span>
                                <?php echo esc_html($status_info['label']); ?>
                            </span>
                        </td>
                        <td class="tav-col-submitted"><?php echo esc_html($date_submitted); ?></td>
                        <td class="tav-col-due"><?php echo esc_html($due_date); ?></td>
                        <td class="tav-col-actions">
                            <div class="tav-action-buttons">
                                <?php if ($name_not_available): ?>
                                    <button type="button"
                                            class="tav-action-btn tav-action-debug tav-open-request-modal"
                                            data-modal="tav-debug-modal-<?php echo esc_attr($req_id); ?>">
                                        <?php esc_html_e('View Details', 'the-admin-vault'); ?>
                                    </button>
                                <?php endif; ?>
                                
                                <?php 
                                // Status-specific action buttons
                                switch ($status):
                                    case 'pending_payment': ?>
                                        <button type="button"
                                                class="tav-action-btn tav-action-view-brief tav-open-request-modal"
                                                data-modal="tav-brief-modal-<?php echo esc_attr($req_id); ?>">
                                            <?php esc_html_e('View Brief', 'the-admin-vault'); ?>
                                        </button>
                                    <?php break;
                                    
                                    case 'pending': ?>
                                        <button type="button"
                                                class="tav-action-btn tav-action-view-brief tav-open-request-modal"
                                                data-modal="tav-brief-modal-<?php echo esc_attr($req_id); ?>">
                                            <?php esc_html_e('View Brief', 'the-admin-vault'); ?>
                                        </button>
                                    <?php break;
                                    
                                    case 'paid': ?>
                                        <a href="<?php echo esc_url(tav_get_dashboard_view_url('fulfill', ['request_id' => $req_id])); ?>" 
                                           class="tav-action-btn tav-action-start-matching">
                                            <?php esc_html_e('Start Matching', 'the-admin-vault'); ?>
                                        </a>
                                    <?php break;
                                    
                                    case 'in_vetting': ?>
                                        <a href="<?php echo esc_url(tav_get_dashboard_view_url('fulfill', ['request_id' => $req_id])); ?>" 
                                           class="tav-action-btn tav-action-continue-vetting">
                                            <?php esc_html_e('Continue Vetting', 'the-admin-vault'); ?>
                                        </a>
                                    <?php break;
                                    
                                    case 'matching': ?>
                                        <a href="<?php echo esc_url(tav_get_dashboard_view_url('fulfill', ['request_id' => $req_id])); ?>" 
                                           class="tav-action-btn tav-action-assign">
                                            <?php esc_html_e('Assign Storytellers', 'the-admin-vault'); ?>
                                        </a>
                                    <?php break;
                                    
                                    case 'ready_review': ?>
                                        <button type="button"
                                                class="tav-action-btn tav-action-awaiting tav-open-request-modal"
                                                data-modal="tav-brief-modal-<?php echo esc_attr($req_id); ?>">
                                            <?php esc_html_e('Awaiting Client', 'the-admin-vault'); ?>
                                        </button>
                                    <?php break;
                                    
                                    case 'assigned': ?>
                                        <button type="button"
                                                class="tav-action-btn tav-action-view-match tav-open-request-modal"
                                                data-modal="tav-req-modal-<?php echo esc_attr($req_id); ?>">
                                            <?php esc_html_e('View Selection', 'the-admin-vault'); ?>
                                        </button>
                                        <a href="<?php echo esc_url(wp_nonce_url(tav_get_dashboard_view_url('requests', ['tav_set_complete' => $req_id]), 'tav_complete_' . $req_id)); ?>" 
                                           class="tav-action-btn tav-action-complete"
                                           onclick="return confirm('<?php esc_attr_e('Mark this request as completed?', 'the-admin-vault'); ?>');">
                                            <?php esc_html_e('Mark Complete', 'the-admin-vault'); ?>
                                        </a>
                                    <?php break;
                                    
                                    case 'completed': ?>
                                        <button type="button"
                                                class="tav-action-btn tav-action-view-report tav-open-request-modal"
                                                data-modal="tav-brief-modal-<?php echo esc_attr($req_id); ?>">
                                            <?php esc_html_e('View Report', 'the-admin-vault'); ?>
                                        </button>
                                    <?php break;
                                    
                                    case 'archived': ?>
                                        <button type="button"
                                                class="tav-action-btn tav-action-view-archived tav-open-request-modal"
                                                data-modal="tav-brief-modal-<?php echo esc_attr($req_id); ?>">
                                            <?php esc_html_e('View Archive', 'the-admin-vault'); ?>
                                        </button>
                                    <?php break;
                                    
                                    default: ?>
                                        <button type="button"
                                                class="tav-action-btn tav-action-view-brief tav-open-request-modal"
                                                data-modal="tav-brief-modal-<?php echo esc_attr($req_id); ?>">
                                            <?php esc_html_e('View', 'the-admin-vault'); ?>
                                        </button>
                                <?php endswitch; ?>
                            </div>
                        </td>
                    </tr>
            <?php
                endwhile;
                wp_reset_postdata();
            else:
            ?>
                <tr>
                    <td colspan="6" class="tav-empty"><?php esc_html_e('No requests found.', 'the-admin-vault'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="tav-requests-pagination">
    <span class="tav-pagination-info">
        <?php 
        printf(
            esc_html__('Page %1$d of %2$d', 'the-admin-vault'),
            $paged,
            max(1, $total_pages)
        ); 
        ?>
    </span>
    <div class="tav-pagination-buttons">
        <?php if ($paged > 1): ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>" class="tav-pagination-btn tav-pagination-prev">
                <?php esc_html_e('Previous', 'the-admin-vault'); ?>
            </a>
        <?php else: ?>
            <span class="tav-pagination-btn tav-pagination-prev disabled">
                <?php esc_html_e('Previous', 'the-admin-vault'); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($paged < $total_pages): ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>" class="tav-pagination-btn tav-pagination-next">
                <?php esc_html_e('Next', 'the-admin-vault'); ?>
            </a>
        <?php else: ?>
            <span class="tav-pagination-btn tav-pagination-next disabled">
                <?php esc_html_e('Next', 'the-admin-vault'); ?>
            </span>
        <?php endif; ?>
    </div>
</div>
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
        // Completed/archived rows always get a brief modal so admins can review what was delivered.
        // For other allowed statuses, skip rows where the client has already picked
        // (those open the "assigned" details modal instead).
        if (!in_array($brief_status, ['completed', 'archived'], true) && !empty($brief_client_selected)) continue;
        if (!in_array($brief_status, ['pending', 'pending_payment', 'in_vetting', 'matching', 'ready_review', 'paid', 'completed', 'archived'], true)) continue;

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
            'pending' => 'Pending', 'pending_payment' => 'Awaiting Payment', 'paid' => 'Paid', 
            'in_vetting' => 'In Vetting', 'matching' => 'Matching', 'ready_review' => 'Ready for Review', 
            'assigned' => 'Assigned', 'completed' => 'Completed', 'archived' => 'Archived',
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

<?php
/* ─────────────────────────────────────────────────────────────
 * Debug modals for "Name not available" requests
 * ───────────────────────────────────────────────────────────── */
if ($req_query->have_posts()) :
    $req_query->rewind_posts();
    while ($req_query->have_posts()) : $req_query->the_post();
        $debug_req_id = get_the_ID();
        $debug_post = get_post($debug_req_id);
        $debug_title = $debug_post->post_title ?? '';
        
        // Only render debug modals for requests with empty/Auto Draft titles
        if (!empty(trim($debug_title)) && $debug_title !== 'Auto Draft') {
            continue;
        }

        // Get all post data
        $debug_author_id = (int) $debug_post->post_author;
        $debug_author = get_user_by('ID', $debug_author_id);
        $debug_author_name = $debug_author ? $debug_author->display_name : 'Unknown';
        $debug_author_email = $debug_author ? $debug_author->user_email : '';

        // Get all post meta
        $debug_all_meta = get_post_meta($debug_req_id);

        // Get ACF fields if available
        $debug_acf_fields = [];
        if (function_exists('get_fields')) {
            $debug_acf_fields = get_fields($debug_req_id) ?: [];
        }

        // Get taxonomies
        $debug_taxonomies = [];
        $post_taxonomies = get_object_taxonomies($debug_post->post_type);
        foreach ($post_taxonomies as $tax) {
            $terms = wp_get_post_terms($debug_req_id, $tax, ['fields' => 'names']);
            if (!empty($terms) && !is_wp_error($terms)) {
                $debug_taxonomies[$tax] = $terms;
            }
        }
        ?>

        <!-- Debug Modal: Request #<?php echo $debug_req_id; ?> -->
        <div id="tav-debug-modal-<?php echo esc_attr($debug_req_id); ?>" class="tav-modal" role="dialog" aria-modal="true" aria-label="Debug Request #<?php echo esc_attr($debug_req_id); ?>">
            <div class="tav-modal-overlay tav-close-request-modal"></div>
            <div class="tav-modal-container tav-req-modal-container" style="max-width:900px;">

                <!-- Header -->
                <div class="tav-modal-header" style="background:#6366f1;">
                    <div>
                        <h2 style="color:#fff;">Debug: Request #<?php echo esc_html($debug_req_id); ?></h2>
                        <p class="tav-req-modal-sub" style="color:rgba(255,255,255,0.8);">
                            Raw data to diagnose why project name is not available
                        </p>
                    </div>
                    <button type="button" class="tav-modal-close tav-close-request-modal" aria-label="Close" style="color:#fff;">&times;</button>
                </div>

                <!-- Scrollable body -->
                <div class="tav-modal-body" style="max-height:70vh;overflow-y:auto;">

                    <!-- Post Object Data -->
                    <div class="tav-req-section" style="margin-bottom:24px;">
                        <h3 class="tav-req-section-title" style="background:#f1f5f9;padding:12px;border-radius:6px;margin-bottom:16px;">
                            Post Object (wp_posts)
                        </h3>
                        <table class="tav-debug-table" style="width:100%;border-collapse:collapse;font-size:13px;">
                            <tr style="background:#f8fafc;">
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;width:200px;">ID</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;"><?php echo esc_html($debug_req_id); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">post_title</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;<?php echo empty($debug_title) ? 'color:#ef4444;font-style:italic;' : ''; ?>">
                                    <?php echo empty($debug_title) ? '(empty string)' : esc_html($debug_title); ?>
                                </td>
                            </tr>
                            <tr style="background:#f8fafc;">
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">post_name (slug)</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;"><?php echo esc_html($debug_post->post_name ?: '(empty)'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">post_status</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;"><?php echo esc_html($debug_post->post_status); ?></td>
                            </tr>
                            <tr style="background:#f8fafc;">
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">post_type</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;"><?php echo esc_html($debug_post->post_type); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">post_date</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;"><?php echo esc_html($debug_post->post_date); ?></td>
                            </tr>
                            <tr style="background:#f8fafc;">
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">post_author</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;">
                                    <?php echo esc_html($debug_author_id); ?> - <?php echo esc_html($debug_author_name); ?>
                                    <?php if ($debug_author_email): ?>
                                        (<a href="mailto:<?php echo esc_attr($debug_author_email); ?>"><?php echo esc_html($debug_author_email); ?></a>)
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">post_content</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;<?php echo empty($debug_post->post_content) ? 'color:#94a3b8;font-style:italic;' : ''; ?>">
                                    <?php echo empty($debug_post->post_content) ? '(empty)' : esc_html(wp_trim_words($debug_post->post_content, 50, '...')); ?>
                                </td>
                            </tr>
                            <tr style="background:#f8fafc;">
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">post_excerpt</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;<?php echo empty($debug_post->post_excerpt) ? 'color:#94a3b8;font-style:italic;' : ''; ?>">
                                    <?php echo empty($debug_post->post_excerpt) ? '(empty)' : esc_html($debug_post->post_excerpt); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;">Edit Link</td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;">
                                    <a href="<?php echo esc_url(get_edit_post_link($debug_req_id)); ?>" target="_blank" style="color:#0369a1;">
                                        Edit in WordPress Admin &rarr;
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- ACF Fields -->
                    <?php if (!empty($debug_acf_fields)): ?>
                    <div class="tav-req-section" style="margin-bottom:24px;">
                        <h3 class="tav-req-section-title" style="background:#f1f5f9;padding:12px;border-radius:6px;margin-bottom:16px;">
                            ACF Fields (<?php echo count($debug_acf_fields); ?> fields)
                        </h3>
                        <table class="tav-debug-table" style="width:100%;border-collapse:collapse;font-size:13px;">
                            <?php 
                            $row_alt = false;
                            foreach ($debug_acf_fields as $field_name => $field_value): 
                                $row_alt = !$row_alt;
                            ?>
                            <tr<?php echo $row_alt ? ' style="background:#f8fafc;"' : ''; ?>>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;width:200px;vertical-align:top;">
                                    <?php echo esc_html($field_name); ?>
                                </td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;word-break:break-word;">
                                    <?php 
                                    if (is_array($field_value) || is_object($field_value)) {
                                        echo '<pre style="margin:0;font-size:12px;background:#f8fafc;padding:8px;border-radius:4px;overflow-x:auto;max-height:200px;">' . esc_html(print_r($field_value, true)) . '</pre>';
                                    } elseif (empty($field_value) && $field_value !== 0 && $field_value !== '0') {
                                        echo '<span style="color:#94a3b8;font-style:italic;">(empty)</span>';
                                    } else {
                                        echo esc_html($field_value);
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Taxonomies -->
                    <?php if (!empty($debug_taxonomies)): ?>
                    <div class="tav-req-section" style="margin-bottom:24px;">
                        <h3 class="tav-req-section-title" style="background:#f1f5f9;padding:12px;border-radius:6px;margin-bottom:16px;">
                            Taxonomies
                        </h3>
                        <table class="tav-debug-table" style="width:100%;border-collapse:collapse;font-size:13px;">
                            <?php 
                            $row_alt = false;
                            foreach ($debug_taxonomies as $tax_name => $tax_terms): 
                                $row_alt = !$row_alt;
                            ?>
                            <tr<?php echo $row_alt ? ' style="background:#f8fafc;"' : ''; ?>>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;width:200px;">
                                    <?php echo esc_html($tax_name); ?>
                                </td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;">
                                    <?php echo esc_html(implode(', ', $tax_terms)); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- All Post Meta -->
                    <div class="tav-req-section" style="margin-bottom:24px;">
                        <h3 class="tav-req-section-title" style="background:#f1f5f9;padding:12px;border-radius:6px;margin-bottom:16px;">
                            All Post Meta (wp_postmeta) - <?php echo count($debug_all_meta); ?> entries
                        </h3>
                        <table class="tav-debug-table" style="width:100%;border-collapse:collapse;font-size:13px;">
                            <?php 
                            $row_alt = false;
                            foreach ($debug_all_meta as $meta_key => $meta_values): 
                                // Skip internal ACF field references (start with _)
                                if (strpos($meta_key, '_') === 0 && strpos($meta_key, '_wp_') !== 0) {
                                    continue;
                                }
                                $row_alt = !$row_alt;
                            ?>
                            <tr<?php echo $row_alt ? ' style="background:#f8fafc;"' : ''; ?>>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:600;width:200px;vertical-align:top;">
                                    <?php echo esc_html($meta_key); ?>
                                </td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;word-break:break-word;">
                                    <?php 
                                    foreach ($meta_values as $mv) {
                                        $unserialized = @unserialize($mv);
                                        if ($unserialized !== false || $mv === 'b:0;') {
                                            echo '<pre style="margin:0;font-size:12px;background:#f8fafc;padding:8px;border-radius:4px;overflow-x:auto;max-height:150px;">' . esc_html(print_r($unserialized, true)) . '</pre>';
                                        } elseif (empty($mv) && $mv !== 0 && $mv !== '0') {
                                            echo '<span style="color:#94a3b8;font-style:italic;">(empty)</span>';
                                        } else {
                                            echo esc_html($mv);
                                        }
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <!-- Quick Actions -->
                    <div class="tav-req-section">
                        <h3 class="tav-req-section-title" style="background:#fef3c7;padding:12px;border-radius:6px;margin-bottom:16px;">
                            Quick Actions
                        </h3>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;">
                            <a href="<?php echo esc_url(get_edit_post_link($debug_req_id)); ?>" 
                               target="_blank" 
                               class="tav-action-btn" 
                               style="background:#0369a1;text-decoration:none;">
                                Edit Post in WP Admin
                            </a>
                            <a href="<?php echo esc_url(tav_get_dashboard_view_url('fulfill', ['request_id' => $debug_req_id])); ?>" 
                               class="tav-action-btn tav-action-fulfill"
                               style="text-decoration:none;">
                                Fulfill Request
                            </a>
                        </div>
                    </div>

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
