<?php
defined('ABSPATH') || exit;

$current_user    = wp_get_current_user();
$display_name    = $current_user->display_name ?: $current_user->user_login;
$total           = tav_get_total_storytellers();
$verified_count  = tav_get_verified_count();
$satisfaction      = tav_get_satisfaction_rate();
$revenue         = tav_get_revenue();
$active_requests = tav_get_active_requests_count();
$recent_st       = tav_get_recent_storytellers(5);
$activity_feed   = tav_get_activity_feed(12);
$chart_data      = tav_get_revenue_chart_data('30days');
$pending_fulfillment = tav_get_pending_fulfillment_requests(5);

$requests_url = tav_get_dashboard_view_url('requests');
$storytellers_url = tav_get_dashboard_view_url('storytellers');
?>

<div class="tav-dash-topbar">
    <div class="tav-page-header">
        <h1 class="tav-page-title"><?php echo esc_html(sprintf(__('Welcome, %s', 'the-admin-vault'), $display_name)); ?></h1>
        <p class="tav-page-subtitle"><?php esc_html_e('Your current sales summary and activity.', 'the-admin-vault'); ?></p>
    </div>
</div>

<div class="tav-dashboard-layout">
    <div class="tav-dashboard-main">
        <div class="tav-stats-grid tav-stats-grid--compact">
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
                    <div class="tav-stat-icon verified"><span class="dashicons dashicons-groups"></span></div>
                    <span class="tav-stat-badge positive"><?php echo esc_html($verified_count); ?> <?php esc_html_e('verified', 'the-admin-vault'); ?></span>
                </div>
                <div class="tav-stat-value"><?php echo esc_html(number_format($total)); ?></div>
                <div class="tav-stat-label"><?php esc_html_e('Vetted Storytellers', 'the-admin-vault'); ?></div>
            </div>

            <div class="tav-stat-card">
                <div class="tav-stat-top">
                    <div class="tav-stat-icon satisfaction"><span class="dashicons dashicons-star-filled"></span></div>
                    <span class="tav-stat-badge <?php echo $satisfaction['feedback_count'] > 0 ? 'positive' : 'neutral'; ?>">
                        <?php echo esc_html($satisfaction['finished_display']); ?>
                    </span>
                </div>
                <div class="tav-stat-value"><?php echo esc_html($satisfaction['score_display']); ?></div>
                <div class="tav-stat-label"><?php esc_html_e('Satisfaction Rate', 'the-admin-vault'); ?></div>
                <?php if ($satisfaction['feedback_count'] > 0): ?>
                    <div class="tav-stat-sublabel">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: interested count, 2: total review count */
                            __('%1$d of %2$d storyteller reviews marked interested', 'the-admin-vault'),
                            $satisfaction['interested_count'],
                            $satisfaction['feedback_count']
                        ));
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tav-panel tav-revenue-chart-panel">
            <div class="tav-chart-header">
                <div>
                    <h3 class="tav-chart-title"><?php esc_html_e('Total Revenue', 'the-admin-vault'); ?></h3>
                    <div class="tav-chart-summary">
                        <span class="tav-chart-total" id="tav-chart-total">$<?php echo esc_html(number_format($chart_data['total'], 2)); ?></span>
                        <span class="tav-chart-sub"><?php echo esc_html(sprintf(__('$%s all time', 'the-admin-vault'), number_format($revenue['all_time']))); ?></span>
                    </div>
                </div>
                <div class="tav-chart-filters">
                    <button type="button" class="tav-chart-btn" data-period="alltime"><?php esc_html_e('All Time', 'the-admin-vault'); ?></button>
                    <button type="button" class="tav-chart-btn tav-chart-active" data-period="30days"><?php esc_html_e('30 days', 'the-admin-vault'); ?></button>
                    <button type="button" class="tav-chart-btn" data-period="7days"><?php esc_html_e('7 days', 'the-admin-vault'); ?></button>
                    <button type="button" class="tav-chart-btn" data-period="24hours"><?php esc_html_e('24 hours', 'the-admin-vault'); ?></button>
                </div>
            </div>
            <div class="tav-chart-canvas-wrap">
                <canvas id="tav-revenue-chart" aria-label="<?php esc_attr_e('Revenue chart', 'the-admin-vault'); ?>"></canvas>
            </div>
            <div class="tav-chart-legend">
                <span class="tav-legend-item tav-legend-received"><?php esc_html_e('Received', 'the-admin-vault'); ?></span>
                <span class="tav-legend-item tav-legend-pending"><?php esc_html_e('Pending', 'the-admin-vault'); ?></span>
            </div>
        </div>

        <div class="tav-panel tav-fulfillment-center ">
            <div class="tav-fulfillment-header">
                <div class="tav-fulfillment-heading">
                    <h3><?php esc_html_e('Request Fulfillment Center', 'the-admin-vault'); ?></h3>
                    <p class="tav-fulfillment-subtitle"><?php esc_html_e('Quickly access and start matchmaking for time-sensitive client requests.', 'the-admin-vault'); ?></p>
                </div>
                <a href="<?php echo esc_url($requests_url); ?>" class="tav-panel-link"><?php esc_html_e('View all', 'the-admin-vault'); ?></a>
            </div>
            <?php if (empty($pending_fulfillment)): ?>
                <p class="tav-empty-state"><?php esc_html_e('No requests pending fulfillment.', 'the-admin-vault'); ?></p>
            <?php else: ?>
                <?php
                $tav_fulfillment_status_pills = [
                    'paid'       => ['label' => __('Paid', 'the-admin-vault'),        'fg' => '#0369a1', 'bg' => '#e0f2fe'],
                    'in_vetting' => ['label' => __('In Vetting', 'the-admin-vault'),  'fg' => '#92400e', 'bg' => '#fef3c7'],
                    'matching'   => ['label' => __('Matching', 'the-admin-vault'),    'fg' => '#3730a3', 'bg' => '#e0e7ff'],
                ];
                ?>
                <ul class="tav-fulfillment-list">
                    <?php foreach ($pending_fulfillment as $item):
                        $pill = $tav_fulfillment_status_pills[$item['status']] ?? [
                            'label' => ucwords(str_replace('_', ' ', (string) $item['status'])),
                            'fg'    => '#475569',
                            'bg'    => '#f1f5f9',
                        ];
                    ?>
                        <li class="tav-fulfillment-item">
                            <div class="tav-fulfillment-info">
                                <p class="tav-fulfillment-title"><?php echo esc_html($item['title']); ?></p>
                                <p class="tav-fulfillment-meta">
                                    <span><?php echo esc_html($item['client']); ?></span>
                                    <?php if (!empty($item['due_date'])): ?>
                                        <span class="tav-fulfillment-due">· <?php echo esc_html(sprintf(__('Due: %s', 'the-admin-vault'), date_i18n('M j', strtotime($item['due_date'])))); ?></span>
                                    <?php endif; ?>
                                    <span class="tav-pill tav-fulfillment-pill" style="--pill-fg:<?php echo esc_attr($pill['fg']); ?>;--pill-bg:<?php echo esc_attr($pill['bg']); ?>;"><?php echo esc_html($pill['label']); ?></span>
                                </p>
                            </div>
                            <a href="<?php echo esc_url(tav_get_dashboard_view_url('fulfill', ['request_id' => (int) $item['id']])); ?>" class="tav-fulfillment-view-btn">
                                <?php esc_html_e('View', 'the-admin-vault'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <aside class="tav-dashboard-rail">
        <div class="tav-panel tav-activity-panel">
            <div class="tav-panel-header tav-panel-header--row">
                <h2 class="tav-panel-title"><?php esc_html_e('Recent activity', 'the-admin-vault'); ?></h2>
            </div>
            <?php if (!empty($activity_feed)):
                $activity_colors = [
                    'new_request'     => '#2271b1',
                    'payment'         => '#16a34a',
                    'fulfilled'       => '#0891b2',
                    'selections'      => '#db2777',
                    'new_storyteller' => '#7c3aed',
                    'new_client'      => '#ea580c',
                ];
                ?>
                <ul class="tav-activity-list">
                    <?php foreach ($activity_feed as $event):
                        $icon_color = $activity_colors[$event['type']] ?? '#64748b';
                        ?>
                        <li class="tav-activity-item">
                            <span class="tav-activity-dot" style="background:<?php echo esc_attr($icon_color); ?>"></span>
                            <div class="tav-activity-body">
                                <p class="tav-activity-label"><?php echo esc_html($event['label']); ?></p>
                                <p class="tav-activity-time"><?php echo esc_html(tav_time_ago($event['timestamp'])); ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tav-empty"><?php esc_html_e('No activity yet.', 'the-admin-vault'); ?></div>
            <?php endif; ?>
        </div>

        <div class="tav-panel tav-storytellers-panel">
            <div class="tav-panel-header">
                <h2 class="tav-panel-title"><?php esc_html_e('Recently Added Storytellers', 'the-admin-vault'); ?></h2>
            </div>
            <?php if (!empty($recent_st)): ?>
                <ul class="tav-panel-list tav-panel-list--compact">
                    <?php foreach ($recent_st as $post):
                        $metrics = 0;
                        if (function_exists('have_rows') && have_rows('platforms_repeater', $post->ID)) {
                            while (have_rows('platforms_repeater', $post->ID)) {
                                the_row();
                                $metrics += (int) get_sub_field('follower_count');
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
                                <p class="tav-st-name">
                                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a>
                                </p>
                                <p class="tav-st-location"><?php echo esc_html(function_exists('get_field') ? (get_field('private_contact', $post->ID) ?: __('No contact', 'the-admin-vault')) : __('No contact', 'the-admin-vault')); ?></p>
                            </div>
                            <span class="tav-st-metrics"><?php echo esc_html(tav_format_metric($metrics)); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tav-empty"><?php esc_html_e('No storytellers yet.', 'the-admin-vault'); ?></div>
            <?php endif; ?>
            <div class="tav-panel-footer">
                <a href="<?php echo esc_url($storytellers_url); ?>"><?php esc_html_e('View All Storytellers', 'the-admin-vault'); ?></a>
            </div>
        </div>
    </aside>
</div>

<script type="application/json" id="tav-chart-initial-data"><?php echo wp_json_encode($chart_data); ?></script>
