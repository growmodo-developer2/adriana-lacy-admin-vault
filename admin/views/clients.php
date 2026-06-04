<?php
defined('ABSPATH') || exit;

// Filter Bar
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

?>

<div class="tav-page-header">
    <h1 class="tav-page-title"><?php esc_html_e('Clients', 'the-admin-vault'); ?></h1>
    <div class="tav-header-actions">
        <p class="tav-page-subtitle"><?php esc_html_e('Manage your client organizations', 'the-admin-vault'); ?></p>
    </div>
</div>

<!-- Search & Filter Toolbar -->
<div class="tav-clients-toolbar">
    <form method="GET" class="tav-search-form">
        <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
        <input type="hidden" name="view" value="clients">
        <div class="tav-search-input-wrap">
            <svg class="tav-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search', 'the-admin-vault'); ?>" class="tav-search-input">
        </div>
    </form>
    
    <button type="button" class="tav-filters-btn" id="tav-toggle-client-filters">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
        <?php esc_html_e('Filters', 'the-admin-vault'); ?>
    </button>
</div>

<div class="tav-boutique-table-wrap">
    <table class="tav-boutique-table tav-clients-table">
        <thead>
            <tr>
                <th class="tav-col-sortable"><?php esc_html_e('Company Name', 'the-admin-vault'); ?> <span class="tav-sort-icon">↕</span></th>
                <th class="tav-col-sortable"><?php esc_html_e('Contact', 'the-admin-vault'); ?> <span class="tav-sort-icon">↕</span></th>
                <th class="tav-col-sortable"><?php esc_html_e('Email', 'the-admin-vault'); ?> <span class="tav-sort-icon">↕</span></th>
                <th class="tav-col-sortable"><?php esc_html_e('Requests', 'the-admin-vault'); ?> <span class="tav-sort-icon">↕</span></th>
                <th class="tav-col-sortable"><?php esc_html_e('Total Spent', 'the-admin-vault'); ?> <span class="tav-sort-icon">↕</span></th>
                <th class="tav-col-sortable"><?php esc_html_e('Join Date', 'the-admin-vault'); ?> <span class="tav-sort-icon">↕</span></th>
                <th class="tav-col-sortable"><?php esc_html_e('Status', 'the-admin-vault'); ?> <span class="tav-sort-icon">↕</span></th>
                <th class="tav-col-actions"><?php esc_html_e('Actions', 'the-admin-vault'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $paged = max(1, (int)($_GET['paged'] ?? 1));
            $number = 10;
            $offset = ($paged - 1) * $number;

            // Allow multiple possible client role slugs
            $client_role_slugs = apply_filters('tav_client_role_slugs', ['um_client', 'client']);

            $args = [
                'role__in' => (array) $client_role_slugs,
                'number'   => $number,
                'offset'   => $offset,
                'orderby'  => 'registered',
                'order'    => 'DESC',
                'search'   => $search_query ? '*' . $search_query . '*' : '',
            ];
            
            $user_query = new WP_User_Query($args);
            $clients = $user_query->get_results();
            $total_users = $user_query->get_total();
            $total_pages = ceil($total_users / $number);

            if (!empty($clients)):
                foreach ($clients as $client):
                    $client_id = $client->ID;
                    $name = $client->display_name;
                    $email = $client->user_email;
                    $registered = date('M j, Y', strtotime($client->user_registered));
                    
                    // Organization / Company Name
                    $org = get_user_meta($client_id, 'organization_name', true) ?: get_user_meta($client_id, 'company_name', true) ?: '—'; 
                    
                    // Request Count
                    $request_count = count_user_posts($client_id, 'request');
                    
                    // Total Spent
                    $total_spent = 0;
                    if (class_exists('WooCommerce')) {
                        $client_orders = wc_get_orders([
                            'customer_id' => $client_id,
                            'status'      => ['completed', 'processing'],
                            'limit'       => -1,
                            'return'      => 'ids',
                        ]);
                        foreach ($client_orders as $coid) {
                            $co = wc_get_order($coid);
                            if ($co) $total_spent += (float) $co->get_total();
                        }
                    }
                    
                    // Status
                    $client_status  = get_user_meta($client_id, 'ccc_client_status', true) ?: 'active';
                    $status_labels  = ['active' => 'Active', 'cancelled' => 'Cancelled', 'suspended' => 'Suspended', 'vip' => 'VIP'];
                    $status_classes = ['active' => 'tav-status-active', 'cancelled' => 'tav-status-cancelled', 'suspended' => 'tav-status-suspended', 'vip' => 'tav-status-vip'];
                    $status_label   = $status_labels[$client_status] ?? ucfirst($client_status);
                    $status_class   = $status_classes[$client_status] ?? 'tav-status-default';
            ?>
                    <tr>
                        <td>
                            <span class="tav-cell-primary"><?php echo esc_html($org !== '—' ? $org : $name); ?></span>
                        </td>
                        <td>
                            <span class="tav-cell-secondary"><?php echo esc_html($name); ?></span>
                        </td>
                        <td>
                            <span class="tav-cell-secondary tav-cell-email-col"><?php echo esc_html($email); ?></span>
                        </td>
                        <td>
                            <span class="tav-cell-secondary"><?php echo esc_html($request_count); ?></span>
                        </td>
                        <td>
                            <span class="tav-cell-primary tav-cell-amount">
                                <?php echo $total_spent > 0 ? '$' . esc_html(number_format($total_spent, 2)) : '—'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="tav-cell-secondary"><?php echo esc_html($registered); ?></span>
                        </td>
                        <td>
                            <span class="tav-status-pill <?php echo esc_attr($status_class); ?>">
                                <span class="tav-status-dot"></span>
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>
                        <td class="tav-col-actions">
                            <button type="button"
                                    class="tav-btn tav-btn-primary tav-view-client"
                                    data-client-id="<?php echo esc_attr($client_id); ?>">
                                <?php esc_html_e('View Detail', 'the-admin-vault'); ?>
                            </button>
                        </td>
                    </tr>
            <?php
                endforeach;
            else:
            ?>
                <tr>
                    <td colspan="8" class="tav-empty"><?php esc_html_e('No clients found.', 'the-admin-vault'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="tav-pagination-wrap">
    <span class="tav-pagination-info">
        <?php printf(esc_html__('Page %1$d of %2$d', 'the-admin-vault'), $paged, max(1, $total_pages)); ?>
    </span>
    <div class="tav-pagination-buttons">
        <?php if ($paged > 1): ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>" class="tav-btn tav-btn-outline">
                <?php esc_html_e('Previous', 'the-admin-vault'); ?>
            </a>
        <?php else: ?>
            <button type="button" class="tav-btn tav-btn-outline" disabled>
                <?php esc_html_e('Previous', 'the-admin-vault'); ?>
            </button>
        <?php endif; ?>
        
        <?php if ($paged < $total_pages): ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>" class="tav-btn tav-btn-primary">
                <?php esc_html_e('Next', 'the-admin-vault'); ?>
            </a>
        <?php else: ?>
            <button type="button" class="tav-btn tav-btn-primary" disabled>
                <?php esc_html_e('Next', 'the-admin-vault'); ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- ── Client Detail Modal ──────────────────────────────────────── -->
<div id="tav-client-modal" class="tav-modal" role="dialog" aria-modal="true"
     aria-label="<?php esc_attr_e('Client detail', 'the-admin-vault'); ?>">

    <div class="tav-modal-overlay"></div>

    <div class="tav-modal-container">

        <div class="tav-modal-header">
            <h2 class="tav-modal-title">
                <?php esc_html_e('Client Detail', 'the-admin-vault'); ?>
            </h2>
            <button type="button" class="tav-modal-close tav-btn-icon"
                    aria-label="<?php esc_attr_e('Close', 'the-admin-vault'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <div class="tav-modal-body" id="tav-client-modal-content">
            <div class="tav-modal-loader">
                <span class="tav-spinner"></span>
                <p><?php esc_html_e('Loading…', 'the-admin-vault'); ?></p>
            </div>
        </div>

    </div><!-- /.tav-modal-container -->
</div><!-- /#tav-client-modal -->
