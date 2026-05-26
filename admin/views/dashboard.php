<?php
defined('ABSPATH') || exit;

$total           = tav_get_total_storytellers();
$status_counts   = tav_get_status_counts();
$verified_count  = tav_get_verified_count();
$avg_score       = tav_get_avg_authenticity();
$revenue         = tav_get_revenue();
$active_requests = tav_get_active_requests_count();
$recent_st       = tav_get_recent_storytellers(5);
$recent_requests = tav_get_recent_requests(5);
?>

<!-- Page Header -->
<div class="tav-page-header">
    <h1 class="tav-page-title"><?php esc_html_e('Dashboard', 'the-admin-vault'); ?></h1>
    <p class="tav-page-subtitle"><?php esc_html_e('Overview of your platform activity', 'the-admin-vault'); ?></p>
</div>

<!-- ── Stat Cards ───────────────────────────────── -->
<div class="tav-stats-grid">
    <div class="tav-stat-card">
        <div class="tav-stat-top">
            <div class="tav-stat-icon revenue"><span class="dashicons dashicons-money-alt"></span></div>
            <span class="tav-stat-badge positive">$<?php echo esc_html(number_format($revenue['month'])); ?> <?php esc_html_e('this month', 'the-admin-vault'); ?></span>
        </div>
        <div class="tav-stat-value">$<?php echo esc_html(number_format($revenue['all_time'])); ?></div>
        <div class="tav-stat-label"><?php esc_html_e('Total Revenue', 'the-admin-vault'); ?></div>
    </div>

    <div class="tav-stat-card">
        <div class="tav-stat-top">
            <div class="tav-stat-icon requests"><span class="dashicons dashicons-clipboard"></span></div>
            <span class="tav-stat-badge positive"><?php esc_html_e('Active', 'the-admin-vault'); ?></span>
        </div>
        <div class="tav-stat-value"><?php echo esc_html($active_requests); ?></div>
        <div class="tav-stat-label"><?php esc_html_e('Active Requests', 'the-admin-vault'); ?></div>
    </div>

    <div class="tav-stat-card">
        <div class="tav-stat-top">
            <div class="tav-stat-icon verified"><span class="dashicons dashicons-id-alt"></span></div>
            <span class="tav-stat-badge positive"><?php echo esc_html($verified_count); ?> <?php esc_html_e('verified', 'the-admin-vault'); ?></span>
        </div>
        <div class="tav-stat-value"><?php echo esc_html(number_format($total)); ?></div>
        <div class="tav-stat-label"><?php esc_html_e('Total Storytellers', 'the-admin-vault'); ?></div>
    </div>

    <div class="tav-stat-card">
        <div class="tav-stat-top">
            <div class="tav-stat-icon growth"><span class="dashicons dashicons-chart-line"></span></div>
            <span class="tav-stat-badge <?php echo $avg_score >= 50 ? 'positive' : 'neutral'; ?>"><?php echo esc_html($avg_score); ?>%</span>
        </div>
        <div class="tav-stat-value"><?php echo esc_html($avg_score); ?>%</div>
        <div class="tav-stat-label"><?php esc_html_e('Avg. Authenticity Score', 'the-admin-vault'); ?></div>
    </div>
</div>

<div class="tav-panels-grid">
    <div class="tav-panel">
        <div class="tav-panel-header"><h2 class="tav-panel-title"><?php esc_html_e('Recent Requests / Activities', 'the-admin-vault'); ?></h2></div>
        <?php if (!empty($recent_requests)): ?>
            <ul class="tav-panel-list">
                <?php foreach ($recent_requests as $post):
                    $status = get_field('status', $post->ID) ?: get_post_meta($post->ID, 'status', true) ?: 'pending';

                    // Custom Logic for auto assigned status
                    $client_selected = get_field('client_selected_storytellers', $post->ID) ?: get_post_meta($post->ID, 'client_selected_storytellers', true);
                    if (!empty($client_selected)) {
                        $status = 'assigned';
                    }

                    $labels = [
                        'pending_payment'    => __('Payment Pending', 'the-admin-vault'),
                        'pending'            => __('Pending',         'the-admin-vault'),
                        'paid'               => __('Paid',            'the-admin-vault'),
                        'in_vetting'         => __('In Vetting',      'the-admin-vault'),
                        'matching'           => __('Matching',        'the-admin-vault'),
                        'ready_review'       => __('Ready to Review', 'the-admin-vault'),
                        'assigned'           => __('Assigned',        'the-admin-vault'),
                        'completed'          => __('Completed',       'the-admin-vault'),
                        'archived'           => __('Archived',        'the-admin-vault'),
                        'enterprise_inquiry' => __('Enterprise Inquiry', 'the-admin-vault'),
                    ];

                    $author_id = (int) $post->post_author;
                    $client_name = $author_id ? (get_the_author_meta('display_name', $author_id) ?: __('Unknown', 'the-admin-vault')) : __('Unknown', 'the-admin-vault');
                    $submitted = mysql2date('M j, Y', $post->post_date);
                    ?>
                    <li>
                        <div class="tav-request-info">
                            <p class="tav-request-name"><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($post->post_title); ?></a></p>
                            <p class="tav-request-org"><?php echo esc_html($client_name . ' · ' . $submitted); ?></p>
                        </div>
                        <span class="tav-status <?php echo esc_attr($status); ?>"><?php echo esc_html($labels[$status] ?? ucwords(str_replace('_', ' ', $status))); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="tav-empty"><?php esc_html_e('No requests yet.', 'the-admin-vault'); ?></div>
        <?php endif; ?>
        <div class="tav-panel-footer">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=requests')); ?>"><?php esc_html_e('View All Requests', 'the-admin-vault'); ?></a>
        </div>
    </div>

    <div class="tav-panel">
        <div class="tav-panel-header"><h2 class="tav-panel-title"><?php esc_html_e('Recently Added Storytellers', 'the-admin-vault'); ?></h2></div>
        <?php if (!empty($recent_st)): ?>
            <ul class="tav-panel-list">
                <?php foreach ($recent_st as $post):
                    $metrics = 0;
                    if (have_rows('platforms_repeater', $post->ID)) {
                        while (have_rows('platforms_repeater', $post->ID)) {
                            the_row();
                            $metrics += (int)get_sub_field('follower_count');
                        }
                    }
                    $initials = tav_get_initials($post->post_title);
                    $thumb_id = get_post_thumbnail_id($post->ID);
                    ?>
                    <li>
                        <div class="tav-st-avatar">
                            <?php if ($thumb_id):
                                echo wp_get_attachment_image($thumb_id, [40, 40]);
                            else:
                                echo esc_html($initials);
                            endif; ?>
                        </div>
                        <div class="tav-st-info">
                            <p class="tav-st-name"><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($post->post_title); ?></a></p>
                            <p class="tav-st-location"><?php echo esc_html(get_field('private_contact', $post->ID) ?: __('No contact', 'the-admin-vault')); ?></p>
                        </div>
                        <span class="tav-st-metrics"><?php echo esc_html(tav_format_metric($metrics)); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="tav-empty"><?php esc_html_e('No storytellers yet.', 'the-admin-vault'); ?></div>
        <?php endif; ?>
        <div class="tav-panel-footer">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug . '&view=storytellers')); ?>"><?php esc_html_e('View All Storytellers', 'the-admin-vault'); ?></a>
        </div>
    </div>
</div>
