<?php
/**
 * Plugin Name:  The Admin Vault
 * Plugin URI:   https://github.com/oyic/the-admin-vault
 * Description:  A private, admin-only vault for managing Storyteller profiles — social handles, verified metrics, private contact info, and authenticity scores. Built on ACF Pro.
 * Version:      1.4.7
 * Author:       OYIC
 * Author URI:   https://oyic.com
 * License:      GPL-2.0-or-later
 * Text Domain:  the-admin-vault
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

/*--------------------------------------------------------------
 * Constants
 *------------------------------------------------------------*/
define('TAV_VERSION', '1.4.7');
define('TAV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TAV_PLUGIN_URL', plugin_dir_url(__FILE__));

/*--------------------------------------------------------------
 * Load Admin Dashboard
 *------------------------------------------------------------*/
require_once TAV_PLUGIN_DIR . 'frontend/admin-portal.php';
require_once TAV_PLUGIN_DIR . 'admin/dashboard.php';

/*--------------------------------------------------------------
 * 1. Register Custom Post Type — Storytellers
 *
 *  • Hidden from the front-end (public = false, publicly_queryable = false,
 *    exclude_from_search = true, has_archive = false).
 *  • show_ui = true so it appears in WP Admin.
 *  • Capability type mapped to 'storyteller' with map_meta_cap so we
 *    can restrict access exclusively to Administrators.
 *------------------------------------------------------------*/
add_action('init', 'tav_register_storytellers_cpt');

function tav_register_storytellers_cpt(): void
{

    $labels = [
        'name' => __('Storytellers', 'the-admin-vault'),
        'singular_name' => __('Storyteller', 'the-admin-vault'),
        'add_new' => __('Add New Storyteller', 'the-admin-vault'),
        'add_new_item' => __('Add New Storyteller', 'the-admin-vault'),
        'edit_item' => __('Edit Storyteller', 'the-admin-vault'),
        'new_item' => __('New Storyteller', 'the-admin-vault'),
        'view_item' => __('View Storyteller', 'the-admin-vault'),
        'search_items' => __('Search Storytellers', 'the-admin-vault'),
        'not_found' => __('No Storytellers found.', 'the-admin-vault'),
        'not_found_in_trash' => __('No Storytellers found in Trash.', 'the-admin-vault'),
        'all_items' => __('All Storytellers', 'the-admin-vault'),
        'menu_name' => __('Storytellers', 'the-admin-vault'),
    ];

    $args = [
        'labels' => $labels,
        'description' => __('Private storyteller profiles — admin eyes only.', 'the-admin-vault'),

        /* ── Front-end visibility: NONE ────────────────── */
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,

        /* ── Admin visibility ──────────────────────────── */
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_rest' => false, // Disables the block editor (Gutenberg) to use the classic interface.
        'menu_position' => 25,
        'menu_icon' => 'dashicons-id-alt',

        /* ── Capabilities — locked to 'storyteller' type ─ */
        'capability_type' => 'storyteller',
        'map_meta_cap' => true,

        /* ── Features ──────────────────────────────────── */
        'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
        'rewrite' => false,
        'query_var' => false,
    ];

    register_post_type('storyteller', $args);

    /* ── Register Taxonomy: Storyteller Tags ────────── */
    register_taxonomy('storyteller_tag', ['storyteller'], [
        'labels' => [
            'name' => __('Tags', 'the-admin-vault'),
            'singular_name' => __('Tag', 'the-admin-vault'),
            'menu_name' => __('Tags', 'the-admin-vault'),
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_quick_edit' => true,
        'show_in_rest' => true,
    ]);

    /* ── Register Taxonomy: Niches ───────────────────── */
    register_taxonomy('vs_niche', ['storyteller', 'request', 'vs_request'], [
        'labels' => [
            'name' => __('Niches', 'the-admin-vault'),
            'singular_name' => __('Niche', 'the-admin-vault'),
            'menu_name' => __('Niches', 'the-admin-vault'),
            'all_items' => __('All Niches', 'the-admin-vault'),
            'edit_item' => __('Edit Niche', 'the-admin-vault'),
            'view_item' => __('View Niche', 'the-admin-vault'),
            'update_item' => __('Update Niche', 'the-admin-vault'),
            'add_new_item' => __('Add New Niche', 'the-admin-vault'),
            'new_item_name' => __('New Niche Name', 'the-admin-vault'),
            'search_items' => __('Search Niches', 'the-admin-vault'),
            'popular_items' => __('Popular Niches', 'the-admin-vault'),
            'separate_items_with_commas' => __('Separate niches with commas', 'the-admin-vault'),
            'add_or_remove_items' => __('Add or remove niches', 'the-admin-vault'),
            'choose_from_most_used' => __('Choose from the most used niches', 'the-admin-vault'),
            'not_found' => __('No niches found.', 'the-admin-vault'),
        ],
        'hierarchical' => true, // Better for management like categories
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_quick_edit' => true,
        'show_in_rest' => true,
    ]);
}

/*--------------------------------------------------------------
 * 2. Grant Storyteller Capabilities to Administrators Only
 *
 *  Runs once on plugin activation and adds the full set of
 *  primitive caps for the 'storyteller' capability type to the
 *  'administrator' role.
 *------------------------------------------------------------*/
register_activation_hook(__FILE__, 'tav_add_caps');

function tav_add_caps(): void
{

    $role = get_role('administrator');

    if (!$role) {
        return;
    }

    $caps = [
        'edit_storyteller',
        'read_storyteller',
        'delete_storyteller',
        'edit_storytellers',
        'edit_others_storytellers',
        'publish_storytellers',
        'read_private_storytellers',
        'delete_storytellers',
        'delete_private_storytellers',
        'delete_published_storytellers',
        'delete_others_storytellers',
        'edit_private_storytellers',
        'edit_published_storytellers',
        'create_storytellers',
    ];

    foreach ($caps as $cap) {
        $role->add_cap($cap);
    }
}

/*--------------------------------------------------------------
 * 3. Remove Storyteller Capabilities on Deactivation (clean-up)
 *------------------------------------------------------------*/
register_deactivation_hook(__FILE__, 'tav_remove_caps');

function tav_remove_caps(): void
{

    $role = get_role('administrator');

    if (!$role) {
        return;
    }

    $caps = [
        'edit_storyteller',
        'read_storyteller',
        'delete_storyteller',
        'edit_storytellers',
        'edit_others_storytellers',
        'publish_storytellers',
        'read_private_storytellers',
        'delete_storytellers',
        'delete_private_storytellers',
        'delete_published_storytellers',
        'delete_others_storytellers',
        'edit_private_storytellers',
        'edit_published_storytellers',
        'create_storytellers',
    ];

    foreach ($caps as $cap) {
        $role->remove_cap($cap);
    }
}

/*--------------------------------------------------------------
 * 4. Register ACF Field Group (programmatic — no JSON import)
 *
 *  Requires Advanced Custom Fields PRO to be active.
 *  Fields:
 *    ┌─────────────────────────┬────────────────────────┐
 *    │ Social Handles          │ Group (sub-fields)     │
 *    │  ├ Instagram            │ Text                   │
 *    │  ├ TikTok               │ Text                   │
 *    │  ├ YouTube              │ Text                   │
 *    │  ├ X / Twitter          │ Text                   │
 *    │  └ Facebook             │ Text                   │
 *    │ Verified Metrics        │ Number                 │
 *    │ Private Contact (Email) │ Email                  │
 *    │ Authenticity Score      │ Range (1 – 100)        │
 *    │ Campaign Status         │ Select                 │
 *    └─────────────────────────┴────────────────────────┘
 *------------------------------------------------------------*/
add_action('acf/include_fields', 'tav_register_acf_fields');

function tav_register_acf_fields(): void
{

    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key' => 'group_tav_storyteller',
        'title' => __('Storyteller Vault Data', 'the-admin-vault'),
        'fields' => [
            /* ── Profile Image (Image) ────────────────── */
            [
                'key' => 'field_tav_profile_image',
                'label' => __('Profile Image', 'the-admin-vault'),
                'name' => 'profile_image',
                'type' => 'image',
                'instructions' => __('Upload a profile image for the storyteller.', 'the-admin-vault'),
                'return_format' => 'id',
                'preview_size' => 'thumbnail',
                'library' => 'all',
                'wrapper' => ['width' => '50'],
            ],



            /* ── Bio (Textarea) ───────────────────────── */
            [
                'key' => 'field_tav_bio',
                'label' => __('Bio', 'the-admin-vault'),
                'name' => 'bio',
                'type' => 'textarea',
                'instructions' => __('Short biography for the storyteller.', 'the-admin-vault'),
                'rows' => 4,
                'wrapper' => ['width' => '100'],
            ],

            /* ── Platforms (Repeater) ─────────────────── */
            [
                'key' => 'field_tav_platforms_repeater',
                'label' => __('Platforms', 'the-admin-vault'),
                'name' => 'platforms_repeater',
                'type' => 'repeater',
                'instructions' => __('Add platforms and metrics.', 'the-admin-vault'),
                'button_label' => __('Add Platform', 'the-admin-vault'),
                'layout' => 'block', // Using block layout for more fields
                'wrapper' => ['width' => '100'],
                'sub_fields' => [
                    [
                        'key' => 'field_tav_plat_name',
                        'label' => __('Platform Name', 'the-admin-vault'),
                        'name' => 'platform_name',
                        'type' => 'select',
                        'choices' => [
                            'instagram' => 'Instagram',
                            'tiktok' => 'TikTok',
                            'youtube' => 'YouTube',
                            'twitter' => 'X / Twitter',
                            'facebook' => 'Facebook',
                            'linkedin' => 'LinkedIn',
                            'website' => 'Website',
                            'other' => 'Other',
                        ],
                        'wrapper' => ['width' => '25'],
                    ],
                    [
                        'key' => 'field_tav_plat_handle',
                        'label' => __('Handle', 'the-admin-vault'),
                        'name' => 'handle',
                        'type' => 'text',
                        'placeholder' => '@username',
                        'wrapper' => ['width' => '25'],
                    ],
                    [
                        'key' => 'field_tav_plat_followers',
                        'label' => __('Follower Count', 'the-admin-vault'),
                        'name' => 'follower_count',
                        'type' => 'number',
                        'wrapper' => ['width' => '25'],
                    ],
                    [
                        'key' => 'field_tav_plat_engagement',
                        'label' => __('Engagement Rate (%)', 'the-admin-vault'),
                        'name' => 'engagement_rate',
                        'type' => 'number',
                        'step' => '0.01',
                        'wrapper' => ['width' => '25'],
                    ],
                    [
                        'key' => 'field_tav_plat_url',
                        'label' => __('Profile URL', 'the-admin-vault'),
                        'name' => 'profile_url',
                        'type' => 'url',
                        'wrapper' => ['width' => '50'],
                    ],
                ],
            ],

            /* ── Niches (Taxonomy) ─────────────────── */
            [
                'key' => 'field_tav_storyteller_niche',
                'label' => __('Niches', 'the-admin-vault'),
                'name' => 'niche',
                'type' => 'taxonomy',
                'taxonomy' => 'vs_niche',
                'field_type' => 'multi_select',
                'load_save_terms' => 1,
                'return_format' => 'id',
                'multiple' => 1,
                'add_term' => 0,
                'load_terms' => 1,
                'save_terms' => 1,
                'wrapper' => ['width' => '50'],
            ],



            /* ── Private Contact (Email) ──────────────── */
            [
                'key' => 'field_tav_private_contact',
                'label' => __('Private Contact', 'the-admin-vault'),
                'name' => 'private_contact',
                'type' => 'email',
                'instructions' => __('Private email address — never exposed on the front-end.', 'the-admin-vault'),
                'placeholder' => 'name@example.com',
                'wrapper' => ['width' => '33'],
            ],

            /* ── Authenticity Score (1 – 100) ─────────── */
            [
                'key' => 'field_tav_authenticity_score',
                'label' => __('Authenticity Score', 'the-admin-vault'),
                'name' => 'authenticity_score',
                'type' => 'range',
                'instructions' => __('Internal authenticity rating from 1 (lowest) to 100 (highest).', 'the-admin-vault'),
                'min' => 1,
                'max' => 100,
                'step' => 1,
                'default_value' => 50,
                'prepend' => '🛡️',
                'append' => '/ 100',
                'wrapper' => ['width' => '34'],
            ],

            /* ── Campaign Status (Select) ─────────────── */
            [
                'key' => 'field_tav_campaign_status',
                'label' => __('Campaign Status', 'the-admin-vault'),
                'name' => 'campaign_status',
                'type' => 'select',
                'instructions' => __('Current campaign engagement status for this storyteller.', 'the-admin-vault'),
                'choices' => [
                    'prospect' => __('Prospect', 'the-admin-vault'),
                    'active' => __('Active', 'the-admin-vault'),
                    'paused' => __('Paused', 'the-admin-vault'),
                    'completed' => __('Completed', 'the-admin-vault'),
                    'declined' => __('Declined', 'the-admin-vault'),
                    'verified' => __('Verified', 'the-admin-vault'),
                    'pending' => __('Pending', 'the-admin-vault'),
                ],
                'default_value' => 'prospect',
                'return_format' => 'value',
                'allow_null' => false,
                'ui' => true,
                'wrapper' => ['width' => '33'],
            ],
            [
                'key' => 'field_tav_location',
                'label' => __('Location', 'the-admin-vault'),
                'name' => 'location',
                'type' => 'text',
                'placeholder' => __('e.g. Berlin, Germany', 'the-admin-vault'),
                'wrapper' => ['width' => '50'],
            ],
            /* ── Sample Work (Repeater) ───────────────── */
            [
                'key' => 'field_tav_sample_work',
                'label' => __('Sample Work', 'the-admin-vault'),
                'name' => 'sample_work',
                'type' => 'repeater',
                'button_label' => __('Add Sample', 'the-admin-vault'),
                'wrapper' => ['width' => '100'],
                'sub_fields' => [
                    [
                        'key' => 'field_tav_sw_title',
                        'label' => __('Content Title', 'the-admin-vault'),
                        'name' => 'content_title',
                        'type' => 'text',
                        'wrapper' => ['width' => '30'],
                    ],
                    [
                        'key' => 'field_tav_sw_platform',
                        'label' => __('Platform', 'the-admin-vault'),
                        'name' => 'platform',
                        'type' => 'select',
                        'choices' => [
                            'instagram' => 'Instagram',
                            'tiktok' => 'TikTok',
                            'youtube' => 'YouTube',
                            'twitter' => 'X / Twitter',
                            'facebook' => 'Facebook',
                            'other' => 'Other',
                        ],
                        'wrapper' => ['width' => '20'],
                    ],
                    [
                        'key' => 'field_tav_sw_views',
                        'label' => __('View Count', 'the-admin-vault'),
                        'name' => 'view_count',
                        'type' => 'number',
                        'wrapper' => ['width' => '20'],
                    ],
                    [
                        'key' => 'field_tav_sw_url',
                        'label' => __('URL', 'the-admin-vault'),
                        'name' => 'url',
                        'type' => 'url',
                        'wrapper' => ['width' => '30'],
                    ],
                ],
            ],

            /* ── Verification Notes (Wysiwyg) ─────────── */
            [
                'key' => 'field_tav_verif_notes',
                'label' => __('Verification Notes', 'the-admin-vault'),
                'name' => 'verification_notes',
                'type' => 'wysiwyg',
                'instructions' => __('Internal notes regarding verification.', 'the-admin-vault'),
                'wrapper' => ['width' => '100'],
            ],

            /* ── Organization Tags (Text) ─────────────── */
            [
                'key'          => 'field_tav_org_tags',
                'name'         => 'organization_tags',
                'label'        => __('Organization Tags', 'the-admin-vault'),
                'type'         => 'text',
                'instructions' => __('Comma-separated tags for internal organization (e.g. "climate-focus, high-priority")', 'the-admin-vault'),
                'placeholder'  => __('e.g. climate-focus, high-priority', 'the-admin-vault'),
                'parent'       => 'group_tav_storyteller',
                'wrapper'      => ['width' => '50'],
            ],

            /* ── Date Added (Date Picker) ─────────────── */
            [
                'key'            => 'field_tav_date_added',
                'name'           => 'date_added',
                'label'          => __('Date Added', 'the-admin-vault'),
                'type'           => 'date_picker',
                'display_format' => 'F j, Y',
                'return_format'  => 'Y-m-d',
                'first_day'      => 1,
                'parent'         => 'group_tav_storyteller',
                'wrapper'        => ['width' => '25'],
            ],

            /* ── Mark as Verified (True/False) ────────── */
            [
                'key'           => 'field_tav_is_verified',
                'name'          => 'is_verified',
                'label'         => __('Mark as verified (vetting complete)', 'the-admin-vault'),
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'parent'        => 'group_tav_storyteller',
                'wrapper'       => ['width' => '25'],
            ],
        ],

        /* ── Location rule: only on Storyteller CPT ──── */
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'storyteller',
                ],
            ],
        ],

        /* ── Group settings ──────────────────────────── */
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
    ]);
}



/**
 * Rename "Title" to "Name" in acf_form for storytellers.
 */
add_filter('acf/prepare_field/name=_post_title', 'tav_rename_title_to_name');

function tav_rename_title_to_name($field)
{
    if (get_post_type() === 'storyteller' || (isset($_GET['view']) && in_array($_GET['view'], ['add-teller', 'edit-teller']))) {
        $field['label'] = __('Name', 'the-admin-vault');
    }
    return $field;
}

/*--------------------------------------------------------------
 * 5. Admin Columns - surface key data on the list table
 *
 *  Shows Authenticity Score, Campaign Status, Verified Metrics,
 *  and Private Contact directly in the Storytellers admin list
 *  for quick overview without opening each post.
 *------------------------------------------------------------*/
add_filter('manage_storyteller_posts_columns', 'tav_storyteller_columns');

function tav_storyteller_columns(array $columns): array
{

    // Insert custom columns after the title.
    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ('title' === $key) {
            $new_columns['authenticity_score'] = __('Authenticity', 'the-admin-vault');
            $new_columns['campaign_status'] = __('Campaign Status', 'the-admin-vault');
            $new_columns['verified_metrics'] = __('Metrics', 'the-admin-vault');
            $new_columns['private_contact'] = __('Contact', 'the-admin-vault');
        }
    }

    return $new_columns;
}

add_action('manage_storyteller_posts_custom_column', 'tav_storyteller_column_content', 10, 2);

function tav_storyteller_column_content(string $column, int $post_id): void
{

    switch ($column) {

        case 'authenticity_score':
            $score = (int)get_field('authenticity_score', $post_id);
            $color = $score >= 70 ? '#00a32a' : ($score >= 40 ? '#dba617' : '#d63638');
            $bg = $score >= 70 ? '#edfaef' : ($score >= 40 ? '#fef8e8' : '#fce8e9');

            // Visual mini-bar + numeric label.
            printf(
                '<div class="tav-score-wrap">' .
                '<div class="tav-score-bar" style="--tav-pct:%1$d%%;--tav-color:%2$s;--tav-bg:%3$s;"></div>' .
                '<span class="tav-score-label" style="color:%2$s;">%1$d</span>' .
                '</div>',
                $score,
                esc_attr($color),
                esc_attr($bg)
            );
            break;

        case 'campaign_status':
            $status = get_field('campaign_status', $post_id);
            echo tav_render_status_pill($status);
            break;

        case 'verified_metrics':
            // Sum followers from platforms repeater
            $total = 0;
            if (have_rows('platforms_repeater', $post_id)) {
                while (have_rows('platforms_repeater', $post_id)) {
                    the_row();
                    $total += (int)get_sub_field('follower_count');
                }
            }
            echo esc_html($total > 0 ? number_format($total) : '—');
            break;

        case 'private_contact':
            $email = get_field('private_contact', $post_id);
            echo $email ? sprintf('<a href="mailto:%1$s">%1$s</a>', esc_attr($email)) : '—';
            break;
    }
}

/**
 * Render a colour-coded status pill.
 */
function tav_render_status_pill(?string $status): string
{
    $map = [
        'prospect' => ['label' => __('Prospect', 'the-admin-vault'), 'color' => '#2271b1', 'bg' => '#e7f3fe'],
        'active' => ['label' => __('Active', 'the-admin-vault'), 'color' => '#00a32a', 'bg' => '#edfaef'],
        'paused' => ['label' => __('Paused', 'the-admin-vault'), 'color' => '#996800', 'bg' => '#fef8e8'],
        'completed' => ['label' => __('Completed', 'the-admin-vault'), 'color' => '#6c6c6c', 'bg' => '#f0f0f0'],
        'declined' => ['label' => __('Declined', 'the-admin-vault'), 'color' => '#d63638', 'bg' => '#fce8e9'],
    ];

    if (!$status || !isset($map[$status])) {
        return '<span class="tav-pill" style="--pill-fg:#787c82;--pill-bg:#f0f0f0;">—</span>';
    }

    $s = $map[$status];

    return sprintf(
        '<span class="tav-pill" style="--pill-fg:%s;--pill-bg:%s;">%s</span>',
        esc_attr($s['color']),
        esc_attr($s['bg']),
        esc_html($s['label'])
    );
}

/*--------------------------------------------------------------
 * 6. Make columns sortable
 *------------------------------------------------------------*/
add_filter('manage_edit-storyteller_sortable_columns', 'tav_storyteller_sortable_columns');

function tav_storyteller_sortable_columns(array $columns): array
{
    $columns['authenticity_score'] = 'authenticity_score';
    $columns['verified_metrics'] = 'verified_metrics';
    $columns['campaign_status'] = 'campaign_status';
    return $columns;
}

/*--------------------------------------------------------------
 * 6b. Admin Columns for Requests
 *------------------------------------------------------------*/
add_filter('manage_request_posts_columns', 'tav_request_columns');

function tav_request_columns(array $columns): array
{
    $new_columns = [];
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if ('title' === $key) {
            $new_columns['client'] = __('Client', 'the-admin-vault');
        }
    }
    return $new_columns;
}

add_action('manage_request_posts_custom_column', 'tav_request_column_content', 10, 2);

function tav_request_column_content(string $column, int $post_id): void
{
    if ('client' === $column) {
        $author_id = get_post_field('post_author', $post_id);
        $user = get_userdata($author_id);
        if ($user) {
            echo esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')';
        } else {
            echo '—';
        }
    }
}

/*--------------------------------------------------------------
 * 7. Filter Dropdowns - Authenticity Score Range & Campaign Status
 *
 *  Adds two dropdown <select> filters above the list table so
 *  admins can narrow results at a glance.
 *------------------------------------------------------------*/
add_action('restrict_manage_posts', 'tav_admin_filter_dropdowns', 10, 2);

function tav_admin_filter_dropdowns(string $post_type, string $which): void
{
    if ('storyteller' !== $post_type) {
        return;
    }

    // - Authenticity Score Range dropdown --------------------
    $current_score = $_GET['tav_score_range'] ?? '';
    $score_ranges = [
        '' => __('All Scores', 'the-admin-vault'),
        '80-100' => __('80 – 100 (Excellent)', 'the-admin-vault'),
        '60-79' => __('60 – 79 (Good)', 'the-admin-vault'),
        '40-59' => __('40 – 59 (Average)', 'the-admin-vault'),
        '20-39' => __('20 – 39 (Low)', 'the-admin-vault'),
        '1-19' => __('1 – 19 (Critical)', 'the-admin-vault'),
    ];

    echo '<select name="tav_score_range" id="tav_score_range">';
    foreach ($score_ranges as $value => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($value),
            selected($current_score, $value, false),
            esc_html($label)
        );
    }
    echo '</select>';

    // - Campaign Status dropdown ----------------------------
    $current_status = $_GET['tav_campaign_status'] ?? '';
    $statuses = [
        '' => __('All Statuses', 'the-admin-vault'),
        'prospect' => __('Prospect', 'the-admin-vault'),
        'active' => __('Active', 'the-admin-vault'),
        'paused' => __('Paused', 'the-admin-vault'),
        'completed' => __('Completed', 'the-admin-vault'),
        'declined' => __('Declined', 'the-admin-vault'),
    ];

    echo '<select name="tav_campaign_status" id="tav_campaign_status">';
    foreach ($statuses as $value => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($value),
            selected($current_status, $value, false),
            esc_html($label)
        );
    }
    echo '</select>';
}

/*--------------------------------------------------------------
 * 8. Apply Filters & Column Sorting via pre_get_posts
 *------------------------------------------------------------*/
add_action('pre_get_posts', 'tav_storyteller_query_mods');

function tav_storyteller_query_mods(\WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ('storyteller' !== $query->get('post_type')) {
        return;
    }

    $meta_query = (array)$query->get('meta_query');

    // - Handle Authenticity Score range filter --------------
    $score_range = $_GET['tav_score_range'] ?? '';
    if ($score_range && preg_match('/^(\d+)-(\d+)$/', $score_range, $m)) {
        $meta_query[] = [
            'key' => 'authenticity_score',
            'value' => [(int)$m[1], (int)$m[2]],
            'type' => 'NUMERIC',
            'compare' => 'BETWEEN',
        ];
    }

    // - Handle Campaign Status filter ----------------------
    $campaign_status = $_GET['tav_campaign_status'] ?? '';
    if ($campaign_status) {
        $meta_query[] = [
            'key' => 'campaign_status',
            'value' => sanitize_text_field($campaign_status),
            'compare' => '=',
        ];
    }

    if (!empty($meta_query)) {
        // Fix for removed verified_metrics meta key:
        // We cannot easily filter by calculated meta value in a simple query without custom SQL or saving total as a separate meta.
        // For now, if sorting by verified_metrics, we might need a workaround or save total_followers on save_post.
        $query->set('meta_query', $meta_query);
    }

    // - Handle column sorting ------------------------------
    $orderby = $query->get('orderby');

    if ('authenticity_score' === $orderby) {
        $query->set('meta_key', 'authenticity_score');
        $query->set('orderby', 'meta_value_num');
    }

    if ('verified_metrics' === $orderby) {
        // Sorting by repeater sum is complex. Ideally we hook into save_post to update a 'total_followers' meta.
        // Disabling sort for now to avoid errors, or map to a new meta if implemented.
        // $query->set('meta_key', 'verified_metrics');
        // $query->set('orderby', 'meta_value_num');
    }

    if ('campaign_status' === $orderby) {
        $query->set('meta_key', 'campaign_status');
        $query->set('orderby', 'meta_value');
    }
}

/*--------------------------------------------------------------
 * 9. Inline CSS for column pills & score bar
 *
 *  Scoped to the Storyteller list screen only.
 *------------------------------------------------------------*/
add_action('admin_head', 'tav_admin_column_styles');

function tav_admin_column_styles(): void
{
    $screen = get_current_screen();

    if (!$screen || 'edit-storyteller' !== $screen->id) {
        return;
    }

    echo '<style id="tav-column-styles">
    .tav-pill {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.5;
        white-space: nowrap;
        color: var(--pill-fg);
        background: var(--pill-bg);
    }
    .tav-score-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 100px;
    }
    .tav-score-bar {
        flex: 1;
        height: 8px;
        border-radius: 4px;
        background: var(--tav-bg);
        position: relative;
        overflow: hidden;
    }
    .tav-score-bar::after {
        content: "";
        position: absolute;
        inset: 0;
        width: var(--tav-pct);
        background: var(--tav-color);
        border-radius: 4px;
        transition: width .3s ease;
    }
    .tav-score-label {
        font-weight: 600;
        font-size: 13px;
        font-variant-numeric: tabular-nums;
        min-width: 24px;
    }
    .column-authenticity_score,
    .column-campaign_status,
    .column-verified_metrics,
    .column-private_contact { white-space: nowrap; }
    .column-title { width: 25%; }
    .column-authenticity_score { width: 12%; }
    .column-campaign_status    { width: 14%; }
    .column-verified_metrics   { width: 10%; }
    .column-private_contact    { width: 18%; }
    </style>';
}

/**
 * Sync ACF Profile Image field with WP Featured Image
 */
add_action('acf/update_value/name=profile_image', 'tav_sync_profile_image', 10, 3);
function tav_sync_profile_image($value, $post_id, $field)
{
    if (is_numeric($post_id)) {
        update_post_meta($post_id, '_thumbnail_id', $value);
    }
    return $value;
}

/*--------------------------------------------------------------
 * 12. Helper Functions & Niche Management
 *------------------------------------------------------------*/

/**
 * Get configured niches from settings
 * Returns array like ['slug' => 'Label']
 */
if (!function_exists('tav_get_niches')) {
    function tav_get_niches(): array {
        $terms = get_terms([
            'taxonomy' => 'vs_niche',
            'hide_empty' => false,
        ]);

        $niches = [];

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $niches[$term->slug] = $term->name;
            }
        }

        // Fallback/Legacy logic if no terms exist yet
        if (empty($niches)) {
            $raw = get_option('tav_niches_list');
            
            if (empty($raw)) {
                return [
                    'climate' => 'Climate',
                    'health' => 'Health',
                    'politics' => 'Politics',
                    'tech' => 'Tech',
                    'fashion' => 'Fashion',
                    'lifestyle' => 'Lifestyle',
                ];
            }

            $lines = preg_split("/\r\n|\n|\r/", $raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (strpos($line, ':') !== false) {
                    list($slug, $label) = array_map('trim', explode(':', $line, 2));
                    $niches[$slug] = $label;
                } else {
                    $slug = sanitize_title($line);
                    $niches[$slug] = $line;
                }
            }
        }
        
        return $niches;
    }
}

/**
 * Dynamically populate ACF niche choices
 */
add_filter('acf/load_field/name=niche_tags', 'tav_load_niche_choices');
add_filter('acf/load_field/name=niche', 'tav_load_niche_choices');

function tav_load_niche_choices($field) {
    // If the taxonomy terms aren't keys, but we want the slug as key and name as value
    $field['choices'] = tav_get_niches();
    return $field;
}

/*--------------------------------------------------------------
 * 13. Persist aggregate engagement rate on storyteller save
 *
 * Fires after ACF saves all fields (priority 20, after ACF's
 * own save at priority 10). Reads the platforms_repeater rows,
 * averages every engagement_rate value that is numeric and > 0,
 * and writes the result to the flat meta key
 * tav_avg_engagement_rate (2 decimal places).
 *
 * This flat key is then used for the fulfillment filter query
 * instead of the LIKE wildcard on platforms_repeater_%_engagement_rate.
 *------------------------------------------------------------*/
add_action('acf/save_post', 'tav_persist_avg_engagement_rate', 20);

function tav_persist_avg_engagement_rate($post_id): void
{
    // $post_id can be an integer or a string like 'options'; skip non-posts.
    if (!is_numeric($post_id)) {
        return;
    }

    $post_id = (int) $post_id;

    if (get_post_type($post_id) !== 'storyteller') {
        return;
    }

    $rates = [];
    $rows  = get_field('platforms_repeater', $post_id); // array of rows after ACF save

    if (!empty($rows) && is_array($rows)) {
        foreach ($rows as $row) {
            $rate = isset($row['engagement_rate']) ? (float) $row['engagement_rate'] : 0.0;
            if ($rate > 0) {
                $rates[] = $rate;
            }
        }
    }

    $avg = !empty($rates) ? round(array_sum($rates) / count($rates), 2) : 0.0;

    update_post_meta($post_id, 'tav_avg_engagement_rate', $avg);

    $total_followers = 0;
    $platform_slugs  = [];
    if (!empty($rows) && is_array($rows)) {
        foreach ($rows as $row) {
            $total_followers += (int) ($row['follower_count'] ?? 0);
            $slug = tav_normalize_platform_slug((string) ($row['platform_name'] ?? ''));
            if ($slug !== '') {
                $platform_slugs[] = $slug;
            }
        }
    }

    update_post_meta($post_id, 'tav_total_followers', $total_followers);
    update_post_meta($post_id, 'tav_platforms', implode(',', array_unique($platform_slugs)));

    // Sync the is_verified toggle to campaign_status: checking the box
    // promotes the storyteller to 'verified' without forcing the admin
    // to also change the select. campaign_status remains the source of
    // truth — this just makes the checkbox a convenient shortcut.
    $is_verified = get_field('is_verified', $post_id);
    if ($is_verified) {
        update_field('campaign_status', 'verified', $post_id);
    }
}

/*--------------------------------------------------------------
 * 14. One-time backfill: populate tav_avg_engagement_rate on
 *     all existing storyteller posts.
 *
 * Guarded by the option tav_engagement_backfill_done so it
 * only runs once — on the first admin page load after this
 * version of the plugin is deployed.
 *------------------------------------------------------------*/
add_action('admin_init', 'tav_backfill_engagement_rates');

function tav_backfill_engagement_rates(): void
{
    if (get_option('tav_engagement_backfill_done')) {
        return;
    }

    $post_ids = get_posts([
        'post_type'      => 'storyteller',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($post_ids as $post_id) {
        $rates = [];
        $rows  = get_field('platforms_repeater', $post_id);

        if (!empty($rows) && is_array($rows)) {
            foreach ($rows as $row) {
                $rate = isset($row['engagement_rate']) ? (float) $row['engagement_rate'] : 0.0;
                if ($rate > 0) {
                    $rates[] = $rate;
                }
            }
        }

        $avg = !empty($rates) ? round(array_sum($rates) / count($rates), 2) : 0.0;
        update_post_meta($post_id, 'tav_avg_engagement_rate', $avg);

        $total_followers = 0;
        $platform_slugs  = [];
        if (!empty($rows) && is_array($rows)) {
            foreach ($rows as $row) {
                $total_followers += (int) ($row['follower_count'] ?? 0);
                $slug = function_exists('tav_normalize_platform_slug')
                    ? tav_normalize_platform_slug((string) ($row['platform_name'] ?? ''))
                    : strtolower((string) ($row['platform_name'] ?? ''));
                if ($slug !== '') {
                    $platform_slugs[] = $slug;
                }
            }
        }
        update_post_meta($post_id, 'tav_total_followers', $total_followers);
        update_post_meta($post_id, 'tav_platforms', implode(',', array_unique($platform_slugs)));
    }

    update_option('tav_engagement_backfill_done', true);
}

add_action('admin_init', 'tav_backfill_storyteller_filter_meta');

function tav_backfill_storyteller_filter_meta(): void
{
    if (get_option('tav_storyteller_filters_backfill_done')) {
        return;
    }

    $post_ids = get_posts([
        'post_type'      => 'storyteller',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($post_ids as $post_id) {
        $rows            = get_field('platforms_repeater', $post_id);
        $total_followers = 0;
        $platform_slugs  = [];

        if (!empty($rows) && is_array($rows)) {
            foreach ($rows as $row) {
                $total_followers += (int) ($row['follower_count'] ?? 0);
                $slug = function_exists('tav_normalize_platform_slug')
                    ? tav_normalize_platform_slug((string) ($row['platform_name'] ?? ''))
                    : strtolower((string) ($row['platform_name'] ?? ''));
                if ($slug !== '') {
                    $platform_slugs[] = $slug;
                }
            }
        }

        update_post_meta($post_id, 'tav_total_followers', $total_followers);
        update_post_meta($post_id, 'tav_platforms', implode(',', array_unique($platform_slugs)));
    }

    update_option('tav_storyteller_filters_backfill_done', true);
}

/*--------------------------------------------------------------
 * 15. Custom password reset email
 *
 *  Overrides the default WordPress reset email with the admin-
 *  editable template stored in tav_email_reset_subject /
 *  tav_email_reset_body. If either option is empty, the default
 *  WordPress behavior is preserved.
 *------------------------------------------------------------*/
add_filter('retrieve_password_title', 'tav_custom_password_reset_subject', 10, 3);
add_filter('retrieve_password_message', 'tav_custom_password_reset_email', 10, 4);

function tav_custom_password_reset_subject($title, $user_login, $user_data)
{
    $subject = get_option('tav_email_reset_subject', '');
    if (empty($subject)) {
        return $title;
    }
    $replacements = [
        '{{user_name}}'  => $user_data->display_name ?: $user_login,
        '{{site_name}}'  => get_bloginfo('name'),
        '{{reset_link}}' => '',
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $subject);
}

function tav_custom_password_reset_email($message, $key, $user_login, $user_data)
{
    $body = get_option('tav_email_reset_body', '');
    if (empty($body)) {
        return $message;
    }
    $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
    $replacements = [
        '{{user_name}}'  => $user_data->display_name ?: $user_login,
        '{{reset_link}}' => $reset_link,
        '{{site_name}}'  => get_bloginfo('name'),
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $body);
}

/*--------------------------------------------------------------
 * 16. Custom Storyteller Form Handler
 *
 *  Handles the custom storyteller add/edit form submission
 *  from the Figma-matched UI.
 *------------------------------------------------------------*/
add_action('admin_init', 'tav_handle_storyteller_form_submission');

function tav_handle_storyteller_form_submission(): void
{
    if (!isset($_POST['tav_action']) || $_POST['tav_action'] !== 'save_storyteller') {
        return;
    }

    if (!isset($_POST['tav_storyteller_nonce']) || !wp_verify_nonce($_POST['tav_storyteller_nonce'], 'tav_save_storyteller')) {
        wp_die(__('Security check failed.', 'the-admin-vault'));
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to perform this action.', 'the-admin-vault'));
    }

    $post_id = !empty($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $full_name = trim($first_name . ' ' . $last_name);

    $post_data = [
        'post_type'   => 'storyteller',
        'post_status' => 'publish',
        'post_title'  => $full_name,
    ];

    if ($post_id > 0) {
        $post_data['ID'] = $post_id;
        $post_id = wp_update_post($post_data);
    } else {
        $post_id = wp_insert_post($post_data);
    }

    if (is_wp_error($post_id) || !$post_id) {
        wp_die(__('Failed to save storyteller.', 'the-admin-vault'));
    }

    // Save ACF fields
    if (function_exists('update_field')) {
        // Bio
        update_field('bio', sanitize_textarea_field($_POST['bio'] ?? ''), $post_id);

        // Location
        update_field('location', sanitize_text_field($_POST['location'] ?? ''), $post_id);

        // Profile Image
        $profile_image = intval($_POST['profile_image'] ?? 0);
        if ($profile_image > 0) {
            update_field('profile_image', $profile_image, $post_id);
            set_post_thumbnail($post_id, $profile_image);
        } else {
            update_field('profile_image', '', $post_id);
            delete_post_thumbnail($post_id);
        }

        // Platforms
        $platforms = [];
        if (!empty($_POST['platforms']) && is_array($_POST['platforms'])) {
            foreach ($_POST['platforms'] as $platform) {
                if (!empty($platform['platform_name']) || !empty($platform['handle'])) {
                    $platforms[] = [
                        'platform_name'   => sanitize_text_field($platform['platform_name'] ?? ''),
                        'handle'          => sanitize_text_field($platform['handle'] ?? ''),
                        'follower_count'  => intval($platform['follower_count'] ?? 0),
                        'profile_url'     => esc_url_raw($platform['profile_url'] ?? ''),
                        'engagement_rate' => floatval($platform['engagement_rate'] ?? 0),
                    ];
                }
            }
        }
        update_field('platforms_repeater', $platforms, $post_id);

        // Sample Work
        $sample_works = [];
        if (!empty($_POST['sample_work']) && is_array($_POST['sample_work'])) {
            foreach ($_POST['sample_work'] as $sample) {
                if (!empty($sample['content_title']) || !empty($sample['url'])) {
                    $sample_works[] = [
                        'content_title' => sanitize_text_field($sample['content_title'] ?? ''),
                        'platform'      => sanitize_text_field($sample['platform'] ?? ''),
                        'view_count'    => intval($sample['view_count'] ?? 0),
                        'url'           => esc_url_raw($sample['url'] ?? ''),
                    ];
                }
            }
        }
        update_field('sample_work', $sample_works, $post_id);

        // Organization Tags
        update_field('organization_tags', sanitize_text_field($_POST['organization_tags'] ?? ''), $post_id);

        // Date Added
        update_field('date_added', sanitize_text_field($_POST['date_added'] ?? ''), $post_id);

        // Is Verified
        update_field('is_verified', isset($_POST['is_verified']) ? 1 : 0, $post_id);
    }

    // Save Niches (taxonomy)
    if (!empty($_POST['niche']) && is_array($_POST['niche'])) {
        $niche_ids = array_map('intval', $_POST['niche']);
        wp_set_post_terms($post_id, $niche_ids, 'vs_niche');
    } else {
        wp_set_post_terms($post_id, [], 'vs_niche');
    }

    // Clear ACF cache and trigger engagement rate calculation
    if (function_exists('acf_flush_value_cache')) {
        acf_flush_value_cache($post_id);
    }
    clean_post_cache($post_id);
    
    // Manually calculate and save engagement rate (in case ACF hook doesn't fire)
    $rates = [];
    if (!empty($platforms)) {
        foreach ($platforms as $p) {
            $rate = floatval($p['engagement_rate'] ?? 0);
            if ($rate > 0) {
                $rates[] = $rate;
            }
        }
    }
    $avg = !empty($rates) ? round(array_sum($rates) / count($rates), 2) : 0.0;
    update_post_meta($post_id, 'tav_avg_engagement_rate', $avg);
    
    // Also trigger ACF save action for any other hooks
    do_action('acf/save_post', $post_id);

    // Redirect back to storytellers list
    wp_safe_redirect(tav_get_dashboard_view_url('storytellers', ['saved' => '1']));
    exit;
}