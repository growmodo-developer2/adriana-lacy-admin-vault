<?php
defined('ABSPATH') || exit;

$total           = tav_get_total_storytellers();
$status_counts   = tav_get_status_counts();
$verified_count  = tav_get_verified_count();
$match_rate      = tav_get_match_acceptance_rate();
$revenue         = tav_get_revenue();
$active_requests = tav_get_active_requests_count();
$recent_st       = tav_get_recent_storytellers(5);
$activity_feed   = tav_get_activity_feed(10);
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
            <div class="tav-stat-icon growth"><span class="dashicons dashicons-thumbs-up"></span></div>
            <span class="tav-stat-badge <?php echo $match_rate['denominator'] > 0 ? 'positive' : 'neutral'; ?>">
                <?php if ($match_rate['denominator'] > 0): ?>
                    <?php echo esc_html($match_rate['numerator'] . ' of ' . $match_rate['denominator']); ?>
                <?php else: ?>
                    <?php esc_html_e('No data', 'the-admin-vault'); ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="tav-stat-value"><?php echo esc_html($match_rate['value']); ?></div>
        <div class="tav-stat-label"><?php esc_html_e('Match Acceptance Rate', 'the-admin-vault'); ?></div>
        <div class="tav-stat-sublabel" style="font-size:11px;color:#94a3b8;margin-top:2px;"><?php esc_html_e('Clients who accepted at least one match', 'the-admin-vault'); ?></div>
    </div>
</div>

<div class="tav-panels-grid">
    <div class="tav-panel">
        <div class="tav-panel-header"><h2 class="tav-panel-title"><?php esc_html_e('Recent Activity', 'the-admin-vault'); ?></h2></div>
        <?php if (!empty($activity_feed)):
            // Colour map — one accent per event type.
            $activity_colors = [
                'new_request'     => '#2271b1',
                'payment'         => '#16a34a',
                'fulfilled'       => '#0891b2',
                'selections'      => '#db2777',
                'new_storyteller' => '#7c3aed',
                'new_client'      => '#ea580c',
            ];
        ?>
            <ul class="tav-panel-list">
                <?php foreach ($activity_feed as $event):
                    $icon_color = $activity_colors[$event['type']] ?? '#64748b';
                ?>
                    <li>
                        <div class="tav-request-info" style="display:flex;align-items:flex-start;gap:10px;flex:1;min-width:0;">
                            <span class="dashicons <?php echo esc_attr($event['icon']); ?>"
                                  style="color:<?php echo esc_attr($icon_color); ?>;flex-shrink:0;margin-top:2px;font-size:16px;width:16px;height:16px;"></span>
                            <div>
                                <p class="tav-request-name" style="margin:0 0 2px;"><?php echo esc_html($event['label']); ?></p>
                                <p class="tav-request-org" style="margin:0;"><?php echo esc_html(tav_time_ago($event['timestamp'])); ?></p>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="tav-empty"><?php esc_html_e('No activity yet.', 'the-admin-vault'); ?></div>
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
