<?php
defined('ABSPATH') || exit;


$req_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
$request = get_post($req_id);

if (!$request || $request->post_type !== 'request') {
    echo '<div class="notice notice-error"><p>' . __('Invalid Request ID.', 'the-admin-vault') . '</p></div>';
    return;
}


// Get current selection
$assigned_ids = get_post_meta($req_id, 'storytellers', true);
if (empty($assigned_ids)) {
    $assigned_ids = get_post_meta($req_id, 'assigned_storytellers', true);
}
if (empty($assigned_ids)) {
    $assigned_ids = get_post_meta($req_id, 'assigned_storyteller', true);
}
$assigned_ids = is_array($assigned_ids) ? array_map('intval', $assigned_ids) : [];

$selection_limits = tav_get_fulfillment_selection_limits($req_id);

// Client Brief Data
$client_id = $request->post_author;
$client_user = get_userdata($client_id);
$client_name = $client_user ? $client_user->display_name : 'Unknown Client';
$project_name = $request->post_title;
$brief = $request->post_content;
$tier = get_field('package_tier', $req_id);
$req_count = get_post_meta($req_id, 'storyteller_count', true);
$additional_info = get_post_meta($req_id, 'additional_information', true);
$campaign_goal = get_field('campaign_goal', $req_id);
$req_location  = get_field('location', $req_id);
$req_timeline  = get_field('timeline', $req_id);
$req_addons    = get_field('addons', $req_id);

$addon_labels = [
    'rush'     => __('Rush Delivery', 'the-admin-vault'),
    'extra'    => __('Extra Matches', 'the-admin-vault'),
    'strategy' => __('Strategy Call', 'the-admin-vault'),
];
$addon_display = '';
if (is_array($req_addons) && !empty($req_addons)) {
    $addon_display = implode(', ', array_map(fn($a) => $addon_labels[$a] ?? ucfirst((string)$a), $req_addons));
} elseif (is_string($req_addons) && $req_addons !== '') {
    $addon_display = $addon_labels[$req_addons] ?? ucfirst($req_addons);
}

// Niche: Fetch from taxonomy
$request_niches = wp_get_post_terms($req_id, 'vs_niche', ['fields' => 'names']);
if (empty($request_niches)) {
    // Fallback to meta if no taxonomy terms
    $niche_val = get_field('niche', $req_id) ?: get_field('story_type', $req_id);
    if (is_array($niche_val)) {
        $niche_req = array_map(function($n) { return is_object($n) ? $n->name : $n; }, $niche_val);
    } elseif (is_object($niche_val)) {
        $niche_req = $niche_val->name;
    } else {
        $niche_req = $niche_val;
    }
} else {
    $niche_req = $request_niches;
}


// Storyteller search — filters by name, niche, location, platform, followers, engagement.
$storytellers = tav_search_fulfillment_storytellers([
    's_term'       => isset($_GET['s_term']) ? sanitize_text_field($_GET['s_term']) : '',
    's_niche'      => isset($_GET['s_niche']) ? sanitize_text_field($_GET['s_niche']) : '',
    's_location'   => isset($_GET['s_location']) ? sanitize_text_field($_GET['s_location']) : '',
    's_platform'   => isset($_GET['s_platform']) ? sanitize_text_field($_GET['s_platform']) : '',
    's_followers'  => isset($_GET['s_followers']) ? sanitize_text_field($_GET['s_followers']) : '',
    's_engagement' => isset($_GET['s_engagement']) ? sanitize_text_field($_GET['s_engagement']) : '',
]);

$search_term       = isset($_GET['s_term']) ? sanitize_text_field($_GET['s_term']) : '';
$niche_filter      = isset($_GET['s_niche']) ? sanitize_text_field($_GET['s_niche']) : '';
$location_filter   = isset($_GET['s_location']) ? sanitize_text_field($_GET['s_location']) : '';
$platform_filter   = isset($_GET['s_platform']) ? sanitize_text_field($_GET['s_platform']) : '';
$followers_filter  = isset($_GET['s_followers']) ? sanitize_text_field($_GET['s_followers']) : '';
$engagement_filter = isset($_GET['s_engagement']) ? sanitize_text_field($_GET['s_engagement']) : '';

?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'selection_count'): ?>
    <div class="notice notice-error is-dismissible">
        <p><?php printf(
            esc_html__('Please select between %1$d and %2$d storytellers before assigning to the project.', 'the-admin-vault'),
            (int) $selection_limits['min'],
            (int) $selection_limits['max']
        ); ?></p>
    </div>
<?php endif; ?>

<div class="tav-page-header">
    <h1 class="tav-page-title"><?php esc_html_e('Fulfill Request', 'the-admin-vault'); ?>: <?php echo esc_html($project_name); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=requests')); ?>" class="tav-btn-secondary">
        <span class="dashicons dashicons-arrow-left-alt"></span>
        <?php esc_html_e('Back to Requests', 'the-admin-vault'); ?>
    </a>
</div>

<div class="tav-fulfillment-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    
    <!-- Left Column: Brief -->
    <div class="tav-panel" style="align-self: start;">
        <div class="tav-panel-header"><h2 class="tav-panel-title"><?php esc_html_e('Client Brief', 'the-admin-vault'); ?></h2></div>
        <div class="tav-brief-content" style="padding: 20px;">
            <div class="tav-brief-row">
                <span class="tav-brief-label"><?php esc_html_e('Client', 'the-admin-vault'); ?></span>
                <span class="tav-brief-value"><?php echo esc_html($client_name); ?></span>
            </div>
            <div class="tav-brief-row">
                <span class="tav-brief-label"><?php esc_html_e('Niche', 'the-admin-vault'); ?></span>
                <span class="tav-brief-value"><?php echo esc_html(is_array($niche_req) ? implode(', ', $niche_req) : $niche_req); ?></span>
            </div>
            <div class="tav-brief-row">
                <span class="tav-brief-label"><?php esc_html_e('Package Tier', 'the-admin-vault'); ?></span>
                <span class="tav-brief-value"><?php echo esc_html($tier ? ucfirst($tier) : '—'); ?></span>
            </div>
            <div class="tav-brief-row">
                <span class="tav-brief-label"><?php esc_html_e('Requested Count', 'the-admin-vault'); ?></span>
                <span class="tav-brief-value"><?php echo esc_html($req_count ?: '—'); ?></span>
            </div>
            <?php if ($req_location): ?>
                <div class="tav-brief-row">
                    <span class="tav-brief-label"><?php esc_html_e('Location', 'the-admin-vault'); ?></span>
                    <span class="tav-brief-value"><?php echo esc_html($req_location); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($req_timeline): ?>
                <div class="tav-brief-row">
                    <span class="tav-brief-label"><?php esc_html_e('Timeline', 'the-admin-vault'); ?></span>
                    <span class="tav-brief-value"><?php echo esc_html(ucwords(str_replace('_', ' ', $req_timeline))); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($addon_display): ?>
                <div class="tav-brief-row">
                    <span class="tav-brief-label"><?php esc_html_e('Add-ons', 'the-admin-vault'); ?></span>
                    <span class="tav-brief-value"><?php echo esc_html($addon_display); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($campaign_goal): ?>
                <div class="tav-brief-row">
                    <span class="tav-brief-label"><?php esc_html_e('Campaign Goal', 'the-admin-vault'); ?></span>
                    <span class="tav-brief-value"><?php echo wp_kses_post($campaign_goal); ?></span>
                </div>
            <?php endif; ?>
            <?php
            $due_date_raw     = get_post_meta($request->ID, 'due_date', true);
            $due_date_display = $due_date_raw
                ? date_i18n('F j, Y', strtotime($due_date_raw))
                : '—';
            ?>
            <div class="tav-brief-row">
                <span class="tav-brief-label"><?php esc_html_e('Match Deadline', 'the-admin-vault'); ?></span>
                <span class="tav-brief-value"><?php echo esc_html($due_date_display); ?></span>
            </div>
            <hr>
            <h4><?php esc_html_e('Additional Info', 'the-admin-vault'); ?></h4>
            <div class="tav-brief-text" style="font-size: 13px; color: #666; margin-bottom: 20px;">
                <?php echo wp_kses_post($additional_info ?: 'None.'); ?>
            </div>
            <hr>
            <h4><?php esc_html_e('Brief Details', 'the-admin-vault'); ?></h4>
            <div class="tav-brief-text">
                <?php echo wp_kses_post($brief ?: 'No details provided.'); ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Search & Select -->
    <div class="tav-panel">
        <div class="tav-panel-header">
            <h2 class="tav-panel-title"><?php esc_html_e('Select Storytellers', 'the-admin-vault'); ?></h2>
        </div>
        
        <!-- Search & Filter Bar -->
        <form method="GET" style="padding: 15px; border-bottom: 1px solid #eee;">
            <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
            <input type="hidden" name="view" value="fulfill">
            <input type="hidden" name="request_id" value="<?php echo esc_attr($req_id); ?>">

            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:10px;">
                <input type="text" name="s_term" value="<?php echo esc_attr($search_term); ?>" placeholder="<?php esc_attr_e('Search by name...', 'the-admin-vault'); ?>" style="flex:1; min-width:160px; padding:8px;">
                <input type="text" name="s_location" value="<?php echo esc_attr($location_filter); ?>" placeholder="<?php esc_attr_e('Location...', 'the-admin-vault'); ?>" style="width:140px; padding:8px;">

                <?php $niches = tav_get_niches(); ?>
                <select name="s_niche" style="padding:8px;">
                    <option value=""><?php esc_html_e('All Niches', 'the-admin-vault'); ?></option>
                    <?php foreach ($niches as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected($niche_filter, $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="s_platform" style="padding:8px;">
                    <option value=""><?php esc_html_e('All Platforms', 'the-admin-vault'); ?></option>
                    <?php
                    $platforms = ['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'twitter' => 'X / Twitter', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn'];
                    foreach ($platforms as $pk => $pl): ?>
                        <option value="<?php echo esc_attr($pk); ?>" <?php selected($platform_filter, $pk); ?>><?php echo esc_html($pl); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="s_followers" style="padding:8px;">
                        <option value=""><?php esc_html_e('Followers', 'the-admin-vault'); ?></option>
                        <option value="under_10k" <?php selected($followers_filter, 'under_10k'); ?>><?php esc_html_e('Under 10K', 'the-admin-vault'); ?></option>
                        <option value="10k_50k" <?php selected($followers_filter, '10k_50k'); ?>><?php esc_html_e('10K – 50K', 'the-admin-vault'); ?></option>
                        <option value="50k_100k" <?php selected($followers_filter, '50k_100k'); ?>><?php esc_html_e('50K – 100K', 'the-admin-vault'); ?></option>
                        <option value="100k_plus" <?php selected($followers_filter, '100k_plus'); ?>><?php esc_html_e('100K+', 'the-admin-vault'); ?></option>
                </select>

                <select name="s_engagement" style="padding:8px;">
                    <option value=""><?php esc_html_e('Engagement', 'the-admin-vault'); ?></option>
                    <option value="under_2" <?php selected($engagement_filter, 'under_2'); ?>><?php esc_html_e('Under 2%', 'the-admin-vault'); ?></option>
                    <option value="2_5" <?php selected($engagement_filter, '2_5'); ?>><?php esc_html_e('2% – 5%', 'the-admin-vault'); ?></option>
                    <option value="5_10" <?php selected($engagement_filter, '5_10'); ?>><?php esc_html_e('5% – 10%', 'the-admin-vault'); ?></option>
                    <option value="10_plus" <?php selected($engagement_filter, '10_plus'); ?>><?php esc_html_e('10%+', 'the-admin-vault'); ?></option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="button button-primary"><?php esc_html_e('Search', 'the-admin-vault'); ?></button>
                <?php if ($search_term || $niche_filter || $location_filter || $platform_filter || $followers_filter || $engagement_filter): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=fulfill&request_id=' . $req_id)); ?>" class="button"><?php esc_html_e('Clear Filters', 'the-admin-vault'); ?></a>
                <?php endif; ?>
            </div>
        </form>

        <form method="POST" id="tav-fulfillment-form">
            <?php wp_nonce_field('tav_fulfill_action', 'tav_fulfill_nonce'); ?>
            <div class="tav-storyteller-list" style="padding: 15px; max-height: 600px; overflow-y: auto;">
                <?php if (!empty($storytellers)): ?>
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        <?php foreach ($storytellers as $st):
                            $is_selected = in_array($st->ID, $assigned_ids);
                            $thumb = get_the_post_thumbnail_url($st->ID, 'thumbnail');
                            $avg_engagement = (float) get_post_meta($st->ID, 'tav_avg_engagement_rate', true);
                            $platforms = get_field('platforms_repeater', $st->ID) ?: [];
                            $total_followers = 0;
                            foreach ($platforms as $p) {
                                $total_followers += (int) ($p['follower_count'] ?? 0);
                            }
                            $followers_display = $total_followers > 0 ? tav_format_metric($total_followers) : '—';
                        ?>
                            <li style="display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #f0f0f0; <?php echo $is_selected ? 'background: #edfaef;' : ''; ?>">
                                <input type="checkbox" name="storytellers[]" value="<?php echo $st->ID; ?>" <?php checked($is_selected); ?> style="margin-right: 15px;">
                                <div style="width: 40px; height: 40px; background: #eee; border-radius: 50%; overflow: hidden; margin-right: 10px;">
                                    <?php if ($thumb): ?>
                                        <img src="<?php echo esc_url($thumb); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong style="display: block; font-size: 14px;"><?php echo esc_html($st->post_title); ?></strong>
                                    <span style="font-size: 12px; color: #666;"><?php echo esc_html(get_field('location', $st->ID)); ?></span>
                                </div>
                                <div style="margin-left: 20px; font-size: 13px; color: #444; min-width: 70px; text-align: right;">
                                    <?php echo esc_html($followers_display); ?>
                                </div>
                                <div style="margin-left: 12px; min-width: 70px; text-align: right;">
                                    <?php if ($avg_engagement > 0):
                                        $eng_class = $avg_engagement >= 6 ? 'tav-eng-high' : ($avg_engagement >= 3 ? 'tav-eng-mid' : 'tav-eng-low');
                                    ?>
                                        <span class="tav-engagement-badge <?php echo esc_attr($eng_class); ?>"><?php echo esc_html(number_format($avg_engagement, 1)); ?>%</span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </div>
                                <div style="margin-left: auto;">
                                    <button type="button" class="button button-small tav-view-storyteller" data-st-id="<?php echo $st->ID; ?>">
                                        <?php esc_html_e('View', 'the-admin-vault'); ?>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?php esc_html_e('No storytellers found.', 'the-admin-vault'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="tav-panel-footer" style="text-align: right; padding: 15px; background: #f9f9f9; border-top: 1px solid #eee;">
                <div class="tav-selection-counter" id="tav-selection-counter"
                     data-min="<?php echo (int) $selection_limits['min']; ?>"
                     data-max="<?php echo (int) $selection_limits['max']; ?>"
                     data-target="<?php echo (int) $selection_limits['target']; ?>">
                    <span id="tav-selected-count">0</span> <?php esc_html_e('selected', 'the-admin-vault'); ?>
                    <span class="tav-counter-target"><?php printf(
                        esc_html__('(required: %1$d–%2$d, target: %3$d)', 'the-admin-vault'),
                        (int) $selection_limits['min'],
                        (int) $selection_limits['max'],
                        (int) $selection_limits['target']
                    ); ?></span>
                </div>
                <p id="tav-selection-error" class="tav-selection-error" style="display:none;color:#b91c1c;font-size:13px;margin:0 0 10px;"></p>
                <button type="submit" class="tav-btn-primary" id="tav-save-fulfillment" disabled>
                    <span class="tav-btn-text"><?php esc_html_e('Assign to Project', 'the-admin-vault'); ?></span>
                    <span class="tav-spinner" style="display:none;"></span>
                </button>
            </div>
        </form>
        <script>
        (function(){
            var form    = document.getElementById('tav-fulfillment-form');
            var counter = document.getElementById('tav-selection-counter');
            var output  = document.getElementById('tav-selected-count');
            var errorEl = document.getElementById('tav-selection-error');
            var submit  = document.getElementById('tav-save-fulfillment');
            if (!form || !counter || !output || !submit) return;

            var min    = parseInt(counter.getAttribute('data-min'), 10) || 5;
            var max    = parseInt(counter.getAttribute('data-max'), 10) || 8;
            var target = parseInt(counter.getAttribute('data-target'), 10) || max;

            function update() {
                var n = form.querySelectorAll('input[type="checkbox"][name="storytellers[]"]:checked').length;
                output.textContent = n;
                counter.classList.toggle('tav-counter-met', n >= min && n <= max);
                submit.disabled = n < min || n > max;

                if (errorEl) {
                    if (n > max) {
                        errorEl.style.display = 'block';
                        errorEl.textContent = 'Select at most ' + max + ' storytellers.';
                    } else if (n > 0 && n < min) {
                        errorEl.style.display = 'block';
                        errorEl.textContent = 'Select at least ' + min + ' storytellers to assign.';
                    } else {
                        errorEl.style.display = 'none';
                        errorEl.textContent = '';
                    }
                }
            }

            form.addEventListener('change', function(e){
                if (!e.target || !e.target.matches('input[type="checkbox"][name="storytellers[]"]')) {
                    return;
                }
                var checked = form.querySelectorAll('input[type="checkbox"][name="storytellers[]"]:checked');
                if (e.target.checked && checked.length > max) {
                    e.target.checked = false;
                    if (errorEl) {
                        errorEl.style.display = 'block';
                        errorEl.textContent = 'You can select up to ' + max + ' storytellers.';
                    }
                }
                update();
            });

            form.addEventListener('submit', function(e){
                var n = form.querySelectorAll('input[type="checkbox"][name="storytellers[]"]:checked').length;
                if (n < min || n > max) {
                    e.preventDefault();
                    if (errorEl) {
                        errorEl.style.display = 'block';
                        errorEl.textContent = 'Please select between ' + min + ' and ' + max + ' storytellers.';
                    }
                }
            });

            update();
        })();
        </script>
    </div>
</div>

<!-- Storyteller Details Modal -->
<div id="tav-storyteller-modal" class="tav-modal" style="display:none;">
    <div class="tav-modal-overlay"></div>
    <div class="tav-modal-container">
        <div class="tav-modal-header">
            <h2 id="tav-modal-title"><?php esc_html_e('Storyteller Details', 'the-admin-vault'); ?></h2>
            <button type="button" class="tav-modal-close">&times;</button>
        </div>
        <div class="tav-modal-body" id="tav-modal-content">
            <!-- Dynamic Content -->
            <div class="tav-modal-loader">
                <span class="tav-spinner"></span>
                <p><?php esc_html_e('Loading details...', 'the-admin-vault'); ?></p>
            </div>
        </div>
    </div>
</div>
