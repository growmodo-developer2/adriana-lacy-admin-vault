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
    <form method="GET" class="tav-filter-bar">
        <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
        <input type="hidden" name="view" value="storytellers">
        
        <?php
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $loc_query = isset($_GET['loc']) ? sanitize_text_field($_GET['loc']) : '';
        ?>
        
        <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search by name...', 'the-admin-vault'); ?>" class="tav-search-input">
        
        <input type="text" name="loc" value="<?php echo esc_attr($loc_query); ?>" placeholder="<?php esc_attr_e('Filter by location...', 'the-admin-vault'); ?>">

        <?php
        $niches = tav_get_niches();
        $niche_query = isset($_GET['niche']) ? sanitize_text_field($_GET['niche']) : '';
        ?>
        <select name="niche">
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
                        
                        // Aggregate data from the platforms repeater.
                        // Read via get_field() (array) instead of have_rows():
                        // have_rows() can mis-read when iterating many posts in a
                        // single request, which made saved rows show 0 followers
                        // and "—" platforms. get_field() matches how the save hook
                        // reads the data, so the list always reflects saved values.
                        $plat_list = [];
                        $followers = 0;

                        $platform_rows = get_field('platforms_repeater', $post->ID);
                        if (is_array($platform_rows)) {
                            foreach ($platform_rows as $p_row) {
                                $p_name = (string) ($p_row['platform_name'] ?? '');
                                if ($p_name !== '') {
                                    $plat_list[] = ucfirst($p_name);
                                }
                                $followers += (int) ($p_row['follower_count'] ?? 0);
                            }
                        }

                        $niche_terms = wp_get_post_terms($post->ID, 'vs_niche', ['fields' => 'names']);
                        $niche = !empty($niche_terms) ? implode(', ', $niche_terms) : '—';

                        $plat_str    = !empty($plat_list) ? implode(', ', array_unique($plat_list)) : '—';
                        // Read the pre-calculated average written by tav_persist_avg_engagement_rate.
                        $engagement  = (float) get_post_meta($post->ID, 'tav_avg_engagement_rate', true);

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
    
    // Get existing data if editing
    $first_name = '';
    $last_name = '';
    $bio = '';
    $location = '';
    $engagement_rate = '';
    $org_tags = '';
    $date_added = '';
    $is_verified = false;
    $platforms = [];
    $sample_works = [];
    $profile_image_id = '';
    $selected_niches = [];
    
    if ($is_edit_teller && $post_id !== 'new_post') {
        $post = get_post($post_id);
        if ($post) {
            $full_name = $post->post_title;
            $name_parts = explode(' ', $full_name, 2);
            $first_name = $name_parts[0] ?? '';
            $last_name = $name_parts[1] ?? '';
        }
        $bio = get_field('bio', $post_id) ?: '';
        $location = get_field('location', $post_id) ?: '';
        $org_tags = get_field('organization_tags', $post_id) ?: '';
        $date_added = get_field('date_added', $post_id) ?: '';
        $is_verified = get_field('is_verified', $post_id) ? true : false;
        $profile_image_id = get_field('profile_image', $post_id) ?: '';
        
        // Get platforms
        $platforms = get_field('platforms_repeater', $post_id) ?: [];
        
        // Get sample works
        $sample_works = get_field('sample_work', $post_id) ?: [];
        
        // Get niches
        $niche_terms = wp_get_post_terms($post_id, 'vs_niche', ['fields' => 'ids']);
        $selected_niches = is_array($niche_terms) ? $niche_terms : [];
        
        // Calculate average engagement rate
        $total_engagement = 0;
        $count = 0;
        if (!empty($platforms)) {
            foreach ($platforms as $p) {
                if (!empty($p['engagement_rate'])) {
                    $total_engagement += floatval($p['engagement_rate']);
                    $count++;
                }
            }
        }
        $engagement_rate = $count > 0 ? round($total_engagement / $count, 2) : '';
    }
    
    // Get all niches for dropdown
    $all_niches = get_terms([
        'taxonomy' => 'vs_niche',
        'hide_empty' => false,
    ]);
    
    // Platform choices
    $platform_choices = [
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'twitter' => 'X / Twitter',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'website' => 'Website',
        'other' => 'Other',
    ];
    ?>
    
    <!-- Back Link -->
    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=storytellers')); ?>" class="tav-back-link">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?php esc_html_e('Back to Storytellers', 'the-admin-vault'); ?>
    </a>
    
    <h1 class="tav-form-title"><?php echo esc_html($title); ?></h1>
    
    <div class="tav-storyteller-form-panel">
        <form id="tav-storyteller-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('tav_save_storyteller', 'tav_storyteller_nonce'); ?>
            <input type="hidden" name="tav_action" value="save_storyteller">
            <input type="hidden" name="post_id" value="<?php echo esc_attr($is_edit_teller ? $post_id : ''); ?>">
            
            <!-- Name Fields -->
            <div class="tav-form-row tav-form-row--2col">
                <div class="tav-form-group">
                    <label for="first_name"><?php esc_html_e('First Name', 'the-admin-vault'); ?></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($first_name); ?>" placeholder="<?php esc_attr_e('First Name...', 'the-admin-vault'); ?>">
                </div>
                <div class="tav-form-group">
                    <label for="last_name"><?php esc_html_e('Last Name', 'the-admin-vault'); ?></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($last_name); ?>" placeholder="<?php esc_attr_e('Last Name...', 'the-admin-vault'); ?>">
                </div>
            </div>
            
            <!-- Profile Image -->
            <div class="tav-form-group">
                <label><?php esc_html_e('Profile Image', 'the-admin-vault'); ?></label>
                <div class="tav-upload-zone" id="profile-image-zone">
                    <input type="hidden" name="profile_image" id="profile_image" value="<?php echo esc_attr($profile_image_id); ?>">
                    <div class="tav-upload-preview" id="profile-image-preview" style="<?php echo $profile_image_id ? '' : 'display:none;'; ?>">
                        <?php if ($profile_image_id): ?>
                            <?php echo wp_get_attachment_image($profile_image_id, 'thumbnail'); ?>
                        <?php endif; ?>
                        <button type="button" class="tav-upload-remove" id="remove-profile-image">&times;</button>
                    </div>
                    <div class="tav-upload-placeholder" id="profile-image-placeholder" style="<?php echo $profile_image_id ? 'display:none;' : ''; ?>">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M17 8L12 3L7 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="tav-upload-text"><?php esc_html_e('Click to upload and attach files', 'the-admin-vault'); ?></span>
                        <span class="tav-upload-hint"><?php esc_html_e('PNG, JPG (max. 800×400px)', 'the-admin-vault'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Bio -->
            <div class="tav-form-group">
                <label for="bio"><?php esc_html_e('Bio', 'the-admin-vault'); ?></label>
                <textarea id="bio" name="bio" rows="4" placeholder="<?php esc_attr_e('Placeholder text here...', 'the-admin-vault'); ?>"><?php echo esc_textarea($bio); ?></textarea>
            </div>
            
            <!-- Location -->
            <div class="tav-form-group">
                <label for="location"><?php esc_html_e('Location', 'the-admin-vault'); ?></label>
                <input type="text" id="location" name="location" value="<?php echo esc_attr($location); ?>" placeholder="<?php esc_attr_e('Location (e.g., London, UK)', 'the-admin-vault'); ?>">
            </div>
            
            <!-- Platforms -->
            <div class="tav-form-group">
                <label><?php esc_html_e('Platforms', 'the-admin-vault'); ?></label>
                <div id="platforms-container">
                    <?php 
                    if (empty($platforms)) {
                        $platforms = [['platform_name' => '', 'handle' => '', 'follower_count' => '', 'profile_url' => '', 'engagement_rate' => '']];
                    }
                    foreach ($platforms as $index => $platform): 
                    ?>
                    <div class="tav-repeater-row tav-platform-row" data-index="<?php echo $index; ?>">
                        <div class="tav-form-row tav-form-row--5col">
                            <div class="tav-form-group">
                                <select name="platforms[<?php echo $index; ?>][platform_name]">
                                    <option value=""><?php esc_html_e('Select platform', 'the-admin-vault'); ?></option>
                                    <?php foreach ($platform_choices as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($platform['platform_name'] ?? '', $val); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="tav-form-group">
                                <input type="text" name="platforms[<?php echo $index; ?>][handle]" value="<?php echo esc_attr($platform['handle'] ?? ''); ?>" placeholder="<?php esc_attr_e('Handle...', 'the-admin-vault'); ?>">
                            </div>
                            <div class="tav-form-group">
                                <input type="number" name="platforms[<?php echo $index; ?>][follower_count]" value="<?php echo esc_attr($platform['follower_count'] ?? ''); ?>" placeholder="<?php esc_attr_e('Followers...', 'the-admin-vault'); ?>">
                            </div>
                            <div class="tav-form-group">
                                <input type="number" step="0.01" name="platforms[<?php echo $index; ?>][engagement_rate]" value="<?php echo esc_attr($platform['engagement_rate'] ?? ''); ?>" placeholder="<?php esc_attr_e('Eng. %...', 'the-admin-vault'); ?>">
                            </div>
                            <div class="tav-form-group">
                                <input type="url" name="platforms[<?php echo $index; ?>][profile_url]" value="<?php echo esc_attr($platform['profile_url'] ?? ''); ?>" placeholder="<?php esc_attr_e('Profile URL...', 'the-admin-vault'); ?>">
                            </div>
                        </div>
                        <?php if ($index > 0): ?>
                        <button type="button" class="tav-repeater-remove" onclick="this.closest('.tav-repeater-row').remove();">&times;</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tav-add-row-btn" id="add-platform">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 1V11M1 6H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <?php esc_html_e('Add Another Platform', 'the-admin-vault'); ?>
                </button>
            </div>
            
            <!-- Average Engagement Rate (calculated, read-only) -->
            <div class="tav-form-group">
                <label for="engagement_rate"><?php esc_html_e('Average Engagement Rate', 'the-admin-vault'); ?></label>
                <input type="text" id="engagement_rate" value="<?php echo esc_attr($engagement_rate ? $engagement_rate . '%' : '—'); ?>" placeholder="<?php esc_attr_e('Calculated from platforms', 'the-admin-vault'); ?>" readonly class="tav-input-readonly">
                <p class="tav-field-hint"><?php esc_html_e('Auto-calculated from platform engagement rates above.', 'the-admin-vault'); ?></p>
            </div>
            
            <!-- Niche -->
            <div class="tav-form-group">
                <label for="niche"><?php esc_html_e('Niche', 'the-admin-vault'); ?></label>
                <select id="niche" name="niche[]" multiple class="tav-select-multiple">
                    <option value="" disabled><?php esc_html_e('Select a niche', 'the-admin-vault'); ?></option>
                    <?php if (!is_wp_error($all_niches)): foreach ($all_niches as $niche): ?>
                        <option value="<?php echo esc_attr($niche->term_id); ?>" <?php echo in_array($niche->term_id, $selected_niches) ? 'selected' : ''; ?>>
                            <?php echo esc_html($niche->name); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            
            <!-- Sample Work -->
            <div class="tav-form-group">
                <label><?php esc_html_e('Sample Work', 'the-admin-vault'); ?></label>
                <div id="sample-work-container">
                    <?php 
                    if (empty($sample_works)) {
                        $sample_works = [['content_title' => '', 'platform' => '', 'view_count' => '', 'url' => '']];
                    }
                    foreach ($sample_works as $index => $sample): 
                    ?>
                    <div class="tav-repeater-row tav-sample-row" data-index="<?php echo $index; ?>">
                        <div class="tav-form-row tav-form-row--4col">
                            <div class="tav-form-group">
                                <input type="text" name="sample_work[<?php echo $index; ?>][content_title]" value="<?php echo esc_attr($sample['content_title'] ?? ''); ?>" placeholder="<?php esc_attr_e('Content title...', 'the-admin-vault'); ?>">
                            </div>
                            <div class="tav-form-group">
                                <select name="sample_work[<?php echo $index; ?>][platform]">
                                    <option value=""><?php esc_html_e('Platform', 'the-admin-vault'); ?></option>
                                    <?php foreach ($platform_choices as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($sample['platform'] ?? '', $val); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="tav-form-group">
                                <input type="number" name="sample_work[<?php echo $index; ?>][view_count]" value="<?php echo esc_attr($sample['view_count'] ?? ''); ?>" placeholder="<?php esc_attr_e('View count...', 'the-admin-vault'); ?>">
                            </div>
                            <div class="tav-form-group">
                                <input type="url" name="sample_work[<?php echo $index; ?>][url]" value="<?php echo esc_attr($sample['url'] ?? ''); ?>" placeholder="<?php esc_attr_e('URL...', 'the-admin-vault'); ?>">
                            </div>
                        </div>
                        <?php if ($index > 0): ?>
                        <button type="button" class="tav-repeater-remove" onclick="this.closest('.tav-repeater-row').remove();">&times;</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tav-add-row-btn" id="add-sample-work">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 1V11M1 6H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <?php esc_html_e('Add Another Work', 'the-admin-vault'); ?>
                </button>
            </div>
            
            <!-- Date Added -->
            <div class="tav-form-group">
                <label for="date_added"><?php esc_html_e('Date Added', 'the-admin-vault'); ?></label>
                <input type="date" id="date_added" name="date_added" value="<?php echo esc_attr($date_added); ?>" placeholder="<?php esc_attr_e('Date', 'the-admin-vault'); ?>">
            </div>
            
            <!-- Organization Tags -->
            <div class="tav-form-group">
                <label for="organization_tags"><?php esc_html_e('Organization Tags', 'the-admin-vault'); ?></label>
                <div class="tav-tags-input-wrap">
                    <div class="tav-tags-display" id="tags-display">
                        <?php 
                        $tags_array = array_filter(array_map('trim', explode(',', $org_tags)));
                        foreach ($tags_array as $tag): 
                        ?>
                            <span class="tav-tag-chip" data-tag="<?php echo esc_attr($tag); ?>">
                                <?php echo esc_html($tag); ?>
                                <button type="button" class="tav-tag-remove">&times;</button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" id="tags_input" placeholder="<?php esc_attr_e('Type and press Enter to add tag...', 'the-admin-vault'); ?>">
                    <input type="hidden" name="organization_tags" id="organization_tags" value="<?php echo esc_attr($org_tags); ?>">
                </div>
            </div>
            
            <!-- Mark as Verified -->
            <div class="tav-form-group tav-checkbox-group">
                <label class="tav-checkbox-label">
                    <input type="checkbox" name="is_verified" value="1" <?php checked($is_verified, true); ?>>
                    <span class="tav-checkbox-custom"></span>
                    <?php esc_html_e('Mark as verified (vetting complete)', 'the-admin-vault'); ?>
                </label>
            </div>
            
            <!-- Form Actions -->
            <div class="tav-form-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=storytellers')); ?>" class="tav-btn-cancel">
                    <?php esc_html_e('Cancel', 'the-admin-vault'); ?>
                </a>
                <button type="submit" class="tav-btn-submit">
                    <?php echo $is_edit_teller ? esc_html__('Update Storyteller', 'the-admin-vault') : esc_html__('Add Storyteller', 'the-admin-vault'); ?>
                </button>
            </div>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Platform choices template
        var platformChoices = <?php echo json_encode($platform_choices); ?>;
        
        // Add Platform Row
        var platformIndex = <?php echo count($platforms); ?>;
        $('#add-platform').on('click', function() {
            var optionsHtml = '<option value=""><?php esc_html_e('Select platform', 'the-admin-vault'); ?></option>';
            $.each(platformChoices, function(val, label) {
                optionsHtml += '<option value="' + val + '">' + label + '</option>';
            });
            
            var html = '<div class="tav-repeater-row tav-platform-row" data-index="' + platformIndex + '">' +
                '<div class="tav-form-row tav-form-row--5col">' +
                    '<div class="tav-form-group"><select name="platforms[' + platformIndex + '][platform_name]">' + optionsHtml + '</select></div>' +
                    '<div class="tav-form-group"><input type="text" name="platforms[' + platformIndex + '][handle]" placeholder="<?php esc_attr_e('Handle...', 'the-admin-vault'); ?>"></div>' +
                    '<div class="tav-form-group"><input type="number" name="platforms[' + platformIndex + '][follower_count]" placeholder="<?php esc_attr_e('Followers...', 'the-admin-vault'); ?>"></div>' +
                    '<div class="tav-form-group"><input type="number" step="0.01" name="platforms[' + platformIndex + '][engagement_rate]" placeholder="<?php esc_attr_e('Eng. %...', 'the-admin-vault'); ?>"></div>' +
                    '<div class="tav-form-group"><input type="url" name="platforms[' + platformIndex + '][profile_url]" placeholder="<?php esc_attr_e('Profile URL...', 'the-admin-vault'); ?>"></div>' +
                '</div>' +
                '<button type="button" class="tav-repeater-remove" onclick="this.closest(\'.tav-repeater-row\').remove();">&times;</button>' +
            '</div>';
            $('#platforms-container').append(html);
            platformIndex++;
        });
        
        // Add Sample Work Row
        var sampleIndex = <?php echo count($sample_works); ?>;
        $('#add-sample-work').on('click', function() {
            var optionsHtml = '<option value=""><?php esc_html_e('Platform', 'the-admin-vault'); ?></option>';
            $.each(platformChoices, function(val, label) {
                optionsHtml += '<option value="' + val + '">' + label + '</option>';
            });
            
            var html = '<div class="tav-repeater-row tav-sample-row" data-index="' + sampleIndex + '">' +
                '<div class="tav-form-row tav-form-row--4col">' +
                    '<div class="tav-form-group"><input type="text" name="sample_work[' + sampleIndex + '][content_title]" placeholder="<?php esc_attr_e('Content title...', 'the-admin-vault'); ?>"></div>' +
                    '<div class="tav-form-group"><select name="sample_work[' + sampleIndex + '][platform]">' + optionsHtml + '</select></div>' +
                    '<div class="tav-form-group"><input type="number" name="sample_work[' + sampleIndex + '][view_count]" placeholder="<?php esc_attr_e('View count...', 'the-admin-vault'); ?>"></div>' +
                    '<div class="tav-form-group"><input type="url" name="sample_work[' + sampleIndex + '][url]" placeholder="<?php esc_attr_e('URL...', 'the-admin-vault'); ?>"></div>' +
                '</div>' +
                '<button type="button" class="tav-repeater-remove" onclick="this.closest(\'.tav-repeater-row\').remove();">&times;</button>' +
            '</div>';
            $('#sample-work-container').append(html);
            sampleIndex++;
        });
        
        // Profile Image Upload
        $('#profile-image-zone').on('click', function(e) {
            if ($(e.target).hasClass('tav-upload-remove')) return;
            
            var frame = wp.media({
                title: '<?php esc_html_e('Select Profile Image', 'the-admin-vault'); ?>',
                button: { text: '<?php esc_html_e('Use this image', 'the-admin-vault'); ?>' },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#profile_image').val(attachment.id);
                $('#profile-image-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" alt=""><button type="button" class="tav-upload-remove" id="remove-profile-image">&times;</button>').show();
                $('#profile-image-placeholder').hide();
            });
            
            frame.open();
        });
        
        // Remove Profile Image
        $(document).on('click', '#remove-profile-image', function(e) {
            e.stopPropagation();
            $('#profile_image').val('');
            $('#profile-image-preview').hide().html('');
            $('#profile-image-placeholder').show();
        });
        
        // Tags Input
        function updateTagsHidden() {
            var tags = [];
            $('#tags-display .tav-tag-chip').each(function() {
                tags.push($(this).data('tag'));
            });
            $('#organization_tags').val(tags.join(', '));
        }
        
        $('#tags_input').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                var tag = $(this).val().trim();
                if (tag) {
                    var chip = '<span class="tav-tag-chip" data-tag="' + tag + '">' + tag + '<button type="button" class="tav-tag-remove">&times;</button></span>';
                    $('#tags-display').append(chip);
                    $(this).val('');
                    updateTagsHidden();
                }
            }
        });
        
        $(document).on('click', '.tav-tag-remove', function() {
            $(this).parent('.tav-tag-chip').remove();
            updateTagsHidden();
        });
    });
    </script>
<?php endif; ?>
        