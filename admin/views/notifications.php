<?php
defined('ABSPATH') || exit;

/*──────────────────────────────────────────────────────────────────────
 * Admin Notifications View
 * 
 * Displays recent activity and notifications for administrators.
 *────────────────────────────────────────────────────────────────────*/

$activity_feed = tav_get_activity_feed(30);
$pending_requests_count = tav_get_active_requests_count();
?>

<div class="tav-page-header">
    <h1 class="tav-page-title"><?php esc_html_e('Notifications', 'the-admin-vault'); ?></h1>
    <p class="tav-page-subtitle"><?php esc_html_e('Recent activity and alerts requiring your attention', 'the-admin-vault'); ?></p>
</div>

<!-- Quick Stats -->
<div class="tav-notifications-stats">
    <div class="tav-notif-stat-card">
        <div class="tav-notif-stat-icon tav-notif-stat-pending">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </div>
        <div class="tav-notif-stat-content">
            <span class="tav-notif-stat-number"><?php echo esc_html($pending_requests_count); ?></span>
            <span class="tav-notif-stat-label"><?php esc_html_e('Pending Requests', 'the-admin-vault'); ?></span>
        </div>
        <?php if ($pending_requests_count > 0): ?>
            <a href="<?php echo esc_url(tav_get_dashboard_view_url('requests')); ?>" class="tav-notif-stat-action">
                <?php esc_html_e('View All', 'the-admin-vault'); ?> →
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="tav-panel">
    <div class="tav-panel-header">
        <h2 class="tav-panel-title"><?php esc_html_e('Recent Activity', 'the-admin-vault'); ?></h2>
    </div>

    <div class="tav-notifications-list">
        <?php if (empty($activity_feed)): ?>
            <div class="tav-notif-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <p><?php esc_html_e('No recent activity to show.', 'the-admin-vault'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($activity_feed as $event): ?>
                <div class="tav-notif-item" data-type="<?php echo esc_attr($event['type']); ?>">
                    <div class="tav-notif-icon">
                        <span class="dashicons <?php echo esc_attr($event['icon']); ?>"></span>
                    </div>
                    <div class="tav-notif-content">
                        <p class="tav-notif-text"><?php echo esc_html($event['label']); ?></p>
                        <span class="tav-notif-time"><?php echo esc_html(tav_time_ago($event['timestamp'])); ?></span>
                    </div>
                    <?php
                    $action_url = '';
                    switch ($event['type']) {
                        case 'new_request':
                        case 'payment':
                        case 'fulfilled':
                        case 'selections':
                            $action_url = tav_get_dashboard_view_url('requests');
                            break;
                        case 'new_storyteller':
                            $action_url = tav_get_dashboard_view_url('storytellers');
                            break;
                        case 'new_client':
                            $action_url = tav_get_dashboard_view_url('clients');
                            break;
                    }
                    if ($action_url):
                    ?>
                        <a href="<?php echo esc_url($action_url); ?>" class="tav-notif-action">
                            <?php esc_html_e('View', 'the-admin-vault'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Notifications Stats */
.tav-notifications-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.tav-notif-stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px 24px;
}

.tav-notif-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tav-notif-stat-pending {
    background: #fef3c7;
    color: #d97706;
}

.tav-notif-stat-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.tav-notif-stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.tav-notif-stat-label {
    font-size: 13px;
    color: #64748b;
}

.tav-notif-stat-action {
    font-size: 13px;
    font-weight: 500;
    color: #3b82f6;
    text-decoration: none;
    white-space: nowrap;
}

.tav-notif-stat-action:hover {
    color: #2563eb;
    text-decoration: underline;
}

/* Notifications List */
.tav-notifications-list {
    padding: 0;
}

.tav-notif-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s;
}

.tav-notif-item:hover {
    background: #f8fafc;
}

.tav-notif-item:last-child {
    border-bottom: none;
}

.tav-notif-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tav-notif-item[data-type="new_request"] .tav-notif-icon {
    background: #dbeafe;
    color: #2563eb;
}

.tav-notif-item[data-type="payment"] .tav-notif-icon {
    background: #d1fae5;
    color: #059669;
}

.tav-notif-item[data-type="fulfilled"] .tav-notif-icon {
    background: #fef3c7;
    color: #d97706;
}

.tav-notif-item[data-type="selections"] .tav-notif-icon {
    background: #fce7f3;
    color: #db2777;
}

.tav-notif-item[data-type="new_storyteller"] .tav-notif-icon {
    background: #e0e7ff;
    color: #4f46e5;
}

.tav-notif-item[data-type="new_client"] .tav-notif-icon {
    background: #f3e8ff;
    color: #9333ea;
}

.tav-notif-icon .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.tav-notif-content {
    flex: 1;
    min-width: 0;
}

.tav-notif-text {
    margin: 0 0 4px;
    font-size: 14px;
    color: #1e293b;
    line-height: 1.4;
}

.tav-notif-time {
    font-size: 12px;
    color: #94a3b8;
}

.tav-notif-action {
    font-size: 13px;
    font-weight: 500;
    color: #3b82f6;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 6px;
    transition: background .15s;
    white-space: nowrap;
}

.tav-notif-action:hover {
    background: #eff6ff;
    color: #2563eb;
}

/* Empty State */
.tav-notif-empty {
    text-align: center;
    padding: 60px 24px;
    color: #94a3b8;
}

.tav-notif-empty svg {
    margin-bottom: 16px;
    opacity: .5;
}

.tav-notif-empty p {
    margin: 0;
    font-size: 15px;
}
</style>
