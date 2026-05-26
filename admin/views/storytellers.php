<?php
defined('ABSPATH') || exit;

// Helper function to render status pill (if not already available globally or included)
if (!function_exists('tav_render_status_pill_view')) {
    function tav_render_status_pill_view(?string $status): string {
        // Implementation usage relies on tav_render_status_pill in main file or we can duplicate if needed,
        // but since this is an include, main functions should be available.
        return function_exists('tav_render_status_pill') ? tav_render_status_pill($status) : $status;
    }
}

if ($is_storytellers):
?>
    <!-- ── Storytellers Boutique Table View ────────── -->
    <div class="tav-page-header">
        <h1 class="tav-page-title"><?php esc_html_e('Verified Storytellers', 'the-admin-vault'); ?></h1>
        <div class="tav-header-actions">
            <p class="tav-page-subtitle"><?php esc_html_e('Manage your exclusive talent list', 'the-admin-vault'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=add-teller')); ?>" class="tav-btn-primary">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_html_e('Add Storyteller', 'the-admin-vault'); ?>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="tav-filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
        <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
        <input type="hidden" name="view" value="storytellers">
        
        <?php
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $loc_query = isset($_GET['loc']) ? sanitize_text_field($_GET['loc']) : '';
        ?>
        
        <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search by name...', 'the-admin-vault'); ?>" class="tav-search-input" style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc; min-width: 200px;">
        
        <input type="text" name="loc" value="<?php echo esc_attr($loc_query); ?>" placeholder="<?php esc_attr_e('Filter by location...', 'the-admin-vault'); ?>" style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc;">

        <?php
        $niches = tav_get_niches();
        $niche_query = isset($_GET['niche']) ? sanitize_text_field($_GET['niche']) : '';
        ?>
        <select name="niche" style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc;">
            <option value=""><?php esc_html_e('All Niches', 'the-admin-vault'); ?></option>
            <?php foreach ($niches as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($niche_query, $slug); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button button-secondary"><?php esc_html_e('Filter', 'the-admin-vault'); ?></button>
        
        <?php if ($search_query || $loc_query || $niche_query): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=storytellers')); ?>" class="button"><?php esc_html_e('Clear', 'the-admin-vault'); ?></a>
        <?php endif; ?>
    </form>

    <div class="tav-boutique-table-wrap">
        <table class="tav-boutique-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('NAME', 'the-admin-vault'); ?></th>
                    <th><?php esc_html_e('LOCATION', 'the-admin-vault'); ?></th>
                    <th><?php esc_html_e('NICHE', 'the-admin-vault'); ?></th>
                    <th><?php esc_html_e('PLATFORMS', 'the-admin-vault'); ?></th>
                    <th><?php esc_html_e('FOLLOWERS', 'the-admin-vault'); ?></th>
                    <th><?php esc_html_e('ENGAGEMENT', 'the-admin-vault'); ?></th>
                    <th><?php esc_html_e('STATUS', 'the-admin-vault'); ?></th>
                    <th class="actions"><?php esc_html_e('ACTIONS', 'the-admin-vault'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $paged = max(1, (int)($_GET['paged'] ?? 1));
                $posts_per_page = 20;
                
                $args = [
                    'post_type' => 'storyteller',
                    'post_status' => 'publish',
                    'posts_per_page' => $posts_per_page,
                    'paged' => $paged,
                    'orderby' => 'title',
                    'order' => 'ASC',
                ];

                if ($search_query) {
                    $args['s'] = $search_query;
                }
                
                if ($loc_query) {
                    $args['meta_query'][] = [
                        'key' => 'location',
                        'value' => $loc_query,
                        'compare' => 'LIKE'
                    ];
                }

                if ($niche_query) {
                    $args['tax_query'] = [
                        [
                            'taxonomy' => 'vs_niche',
                            'field'    => 'slug',
                            'terms'    => $niche_query,
                        ]
                    ];
                }

                $st_query = new WP_Query($args);

                $all_st = $st_query->posts;
                $total_pages = $st_query->max_num_pages;

                if (!empty($all_st)):
                    foreach ($all_st as $post):
                        $loc = get_field('location', $post->ID) ?: '—';
                        
                        // Aggregate data from repeater
                        $niche_list = [];
                        $plat_list = [];
                        $followers = 0;
                        $total_eng = 0;
                        $plat_count = 0;

                        if (have_rows('platforms_repeater', $post->ID)) {
                            while (have_rows('platforms_repeater', $post->ID)) {
                                the_row();
                                $p_name = get_sub_field('platform_name');
                                $p_name_label = ucfirst($p_name); // Simplified label
                                $plat_list[] = $p_name_label;
                                
                                $followers += (int)get_sub_field('follower_count');
                                
                                $eng = (float)get_sub_field('engagement_rate');
                                if ($eng > 0) {
                                    $total_eng += $eng;
                                    $plat_count++;
                                }
                            }
                        }

                        $niche_terms = wp_get_post_terms($post->ID, 'vs_niche', ['fields' => 'names']);
                        $niche = !empty($niche_terms) ? implode(', ', $niche_terms) : '—';
                        
                        $plat_str = !empty($plat_list) ? implode(', ', array_unique($plat_list)) : '—';
                        $engagement = $plat_count > 0 ? round($total_eng / $plat_count, 1) : 0;

                        $status = get_field('campaign_status', $post->ID) ?: 'prospect';
                        $initials = tav_get_initials($post->post_title);
                        $thumb_id = get_post_thumbnail_id($post->ID);

                        ?>
                        <tr>
                            <td>
                                <div class="tav-cell-name">
                                    <div class="tav-boutique-avatar">
                                        <?php if ($thumb_id):
                                            echo wp_get_attachment_image($thumb_id, [40, 40]);
                                        else:
                                            echo esc_html($initials);
                                        endif; ?>
                                    </div>
                                    <span class="tav-name-text"><?php echo esc_html($post->post_title); ?></span>
                                </div>
                            </td>
                            <td><span class="tav-cell-secondary"><?php echo esc_html($loc); ?></span></td>
                            <td><span class="tav-cell-secondary"><?php echo esc_html($niche); ?></span></td>
                            <td><span class="tav-cell-secondary"><?php echo esc_html($plat_str); ?></span></td>
                            <td><span class="tav-cell-primary"><?php echo esc_html(tav_format_metric((int)$followers)); ?></span></td>
                            <td><span class="tav-cell-secondary"><?php echo esc_html($engagement ? $engagement . '%' : '—'); ?></span></td>
                            <td><?php echo tav_render_status_pill($status); ?></td>
                            <td class="actions">
                                <div class="tav-actions-wrap">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=edit-teller&post_id=' . $post->ID)); ?>" class="tav-btn-icon" title="Edit">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <a href="<?php echo esc_url(get_delete_post_link($post->ID)); ?>" class="tav-btn-icon delete" title="Delete">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php
                    endforeach;
                else: ?>
                    <tr>
                        <td colspan="8" class="tav-empty"><?php esc_html_e('No storytellers found.', 'the-admin-vault'); ?></td>
                    </tr>
                <?php
                endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="tav-pagination">
            <?php
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo; Prev', 'the-admin-vault'),
                'next_text' => __('Next &raquo;', 'the-admin-vault'),
                'total' => $total_pages,
                'current' => $paged,
            ]);
            ?>
        </div>
    <?php endif; ?>

<?php elseif ($is_add_teller || $is_edit_teller):
    $post_id = $is_edit_teller ? (int)$_GET['post_id'] : 'new_post';
    $title = $is_edit_teller ? __('Edit Storyteller', 'the-admin-vault') : __('Add New Storyteller', 'the-admin-vault');
    ?>
    <div class="tav-page-header">
        <h1 class="tav-page-title"><?php echo esc_html($title); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=storytellers')); ?>" class="tav-btn-secondary">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e('Back to List', 'the-admin-vault'); ?>
        </a>
    </div>

    <div class="tav-form-panel">
        <?php
        acf_form([
            'post_id' => $post_id,
            'new_post' => [
                'post_type' => 'storyteller',
                'post_status' => 'publish',
            ],
            'field_groups' => ['group_tav_storyteller'],
            'post_title' => true,
            'post_content' => true,
            'submit_value' => $is_edit_teller ? __('Update Storyteller', 'the-admin-vault') : __('Create Storyteller', 'the-admin-vault'),
            'return' => admin_url('admin.php?page=' . $current_page_slug . '&view=storytellers'),
        ]);
        ?>
    </div>
<?php endif; ?>
        