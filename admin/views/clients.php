<?php
defined('ABSPATH') || exit;

// Filter Bar
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

?>

<div class="tav-page-header">
    <h1 class="tav-page-title"><?php esc_html_e('Clients', 'the-admin-vault'); ?></h1>
    <div class="tav-header-actions">
        <p class="tav-page-subtitle"><?php esc_html_e('Manage your client base', 'the-admin-vault'); ?></p>
        <!-- Optional: Add Manually button if needed, linking to user-new.php -->
    </div>
</div>

<form method="GET" class="tav-filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
    <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
    <input type="hidden" name="view" value="clients">
    
    <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search clients...', 'the-admin-vault'); ?>" class="tav-search-input" style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc; min-width: 250px;">
    
    <button type="submit" class="button button-secondary"><?php esc_html_e('Search', 'the-admin-vault'); ?></button>
    
    <?php if ($search_query): ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=clients')); ?>" class="button"><?php esc_html_e('Clear', 'the-admin-vault'); ?></a>
    <?php endif; ?>
</form>

<div class="tav-boutique-table-wrap">
    <table class="tav-boutique-table">
        <thead>
            <tr>
                <th><?php esc_html_e('NAME / EMAIL', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('ORGANIZATION', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('REQUESTS', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('TOTAL SPENT', 'the-admin-vault'); ?></th>
                <th><?php esc_html_e('JOINED', 'the-admin-vault'); ?></th>
                <th class="actions"><?php esc_html_e('ACTIONS', 'the-admin-vault'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $paged = max(1, (int)($_GET['paged'] ?? 1));
            $number = 20;
            $offset = ($paged - 1) * $number;

            // Allow multiple possible client role slugs so this stays robust
            // against role key changes (e.g. "client" vs "um_client").
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
                    
                    // Meta (Placeholder or actual if known)
                    $org = get_user_meta($client_id, 'organization_name', true) ?: '—'; 
                    
                    // Request Count (Count 'request' posts via author)
                    $request_count = count_user_posts($client_id, 'request');
                    
                    $initials = tav_get_initials($name);
                    $avatar_url = get_avatar_url($client_id, ['size' => 40]);
            ?>
                    <tr>
                        <td>
                            <div class="tav-cell-name">
                                <div class="tav-boutique-avatar" style="background: #e7f3fe; color: #2271b1;">
                                    <?php if ($avatar_url): ?>
                                        <img src="<?php echo esc_url($avatar_url); ?>" alt="" style="border-radius:50%;">
                                    <?php else: ?>
                                        <?php echo esc_html($initials); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="tav-name-text"><?php echo esc_html($name); ?></span>
                                    <span class="tav-cell-secondary" style="display:block; font-size:12px;"><?php echo esc_html($email); ?></span>
                                </div>
                            </div>
                        </td>
                        <td><span class="tav-cell-secondary"><?php echo esc_html($org); ?></span></td>
                        <td>
                             <?php if ($request_count > 0): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=requests&client_id=' . $client_id)); ?>" class="tav-pill" style="--pill-fg:#2271b1;--pill-bg:#e7f3fe;">
                                    <?php echo esc_html($request_count); ?>
                                </a>
                             <?php else: ?>
                                <span class="tav-cell-secondary">0</span>
                             <?php endif; ?>
                        </td>
                        <td>
                            <?php
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
                            ?>
                            <span class="tav-cell-primary" style="font-weight:600;">
                                <?php echo $total_spent > 0 ? '$' . esc_html(number_format($total_spent)) : '—'; ?>
                            </span>
                        </td>
                        <td><span class="tav-cell-secondary"><?php echo esc_html($registered); ?></span></td>
                        <td class="actions">
                            <div class="tav-actions-wrap">
                                <a href="<?php echo esc_url(get_edit_user_link($client_id)); ?>" class="tav-btn-icon" title="Edit User">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                            </div>
                        </td>
                    </tr>
            <?php
                endforeach;
            else:
            ?>
                <tr>
                    <td colspan="6" class="tav-empty"><?php esc_html_e('No clients found.', 'the-admin-vault'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
    <div class="tav-pagination">
        <?php
        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'total' => $total_pages,
            'current' => $paged,
        ]);
        ?>
    </div>
<?php endif; ?>
