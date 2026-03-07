<?php
/**
 * Admin Layout Wrapper — Custom Sidebar Navigation.
 *
 * Outputs the eProcurement admin shell with a left sidebar
 * and a content area. Each admin partial is rendered inside
 * this shell. The sidebar provides a custom navigation menu
 * that is completely independent of the WordPress admin sidebar.
 *
 * Usage:
 *   Eprocurement_Admin::open_layout( 'dashboard' );
 *   require 'partials/dashboard.php';
 *   Eprocurement_Admin::close_layout();
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$messaging    = new Eprocurement_Messaging();
$unread_count = $messaging->get_unread_count( get_current_user_id() );

$current_page   = sanitize_text_field( $_GET['page'] ?? '' );
$current_action = sanitize_text_field( $_GET['action'] ?? '' );

$nav_items = [
    [
        'slug'  => 'eprocurement',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        'label' => __( 'Dashboard', 'eprocurement' ),
    ],
    [
        'slug'  => 'eprocurement-bids',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'label' => __( 'All Bids', 'eprocurement' ),
    ],
    [
        'slug'   => 'eprocurement-bids',
        'action' => 'new',
        'icon'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        'label'  => __( 'Add New Bid', 'eprocurement' ),
    ],
];

// Dynamically add category nav items if enabled
$category_nav = [
    'briefing_register' => [
        'label' => __( 'Briefing Register', 'eprocurement' ),
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="15" y2="16"/></svg>',
    ],
    'closing_register' => [
        'label' => __( 'Closing Register', 'eprocurement' ),
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14l2 2 4-4"/></svg>',
    ],
    'appointments' => [
        'label' => __( 'Appointments', 'eprocurement' ),
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    ],
];

foreach ( $category_nav as $cat_key => $cat_data ) {
    if ( get_option( "eprocurement_category_{$cat_key}", '0' ) === '1' ) {
        $nav_items[] = [
            'slug'  => 'eprocurement-' . $cat_key,
            'icon'  => $cat_data['icon'],
            'label' => $cat_data['label'],
        ];
    }
}

$nav_items[] = [ 'separator' => true ];
$nav_items[] = [
    'slug'  => 'eprocurement-contacts',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'label' => __( 'Contact Persons', 'eprocurement' ),
];
$nav_items[] = [
    'slug'  => 'eprocurement-messages',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'label' => __( 'Messages', 'eprocurement' ),
    'badge' => $unread_count,
];
$nav_items[] = [
    'slug'  => 'eprocurement-bidders',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'label' => __( 'Bidders', 'eprocurement' ),
];
$nav_items[] = [ 'separator' => true ];
$nav_items[] = [
    'slug'  => 'eprocurement-downloads',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
    'label' => __( 'Download Log', 'eprocurement' ),
];
$nav_items[] = [
    'slug'  => 'eprocurement-compliance',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'label' => __( 'SCM Documents', 'eprocurement' ),
];

// User Management — visible to admins/managers who can list users
if ( current_user_can( 'list_users' ) ) {
    $nav_items[] = [
        'slug'  => 'eprocurement-users',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'label' => __( 'User Management', 'eprocurement' ),
        'href'  => admin_url( 'users.php' ),
    ];
}

// Settings — Super Admin only
if ( is_super_admin() ) {
    $nav_items[] = [
        'slug'  => 'eprocurement-settings',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'label' => __( 'Settings', 'eprocurement' ),
    ];
}

// Bottom separator + utility links
$tenders_slug = get_option( 'eprocurement_page_slug', 'tenders' );
$nav_items[] = [ 'separator' => true ];

// WordPress Admin link — Super Admin only
if ( is_super_admin() ) {
    $nav_items[] = [
        'slug'  => 'eproc-wp-admin',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
        'label' => __( 'WordPress Admin', 'eprocurement' ),
        'href'  => admin_url( 'index.php' ),
    ];
}

$nav_items[] = [
    'slug'  => 'eproc-back-to-portal',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>',
    'label' => __( 'Back to Portal', 'eprocurement' ),
    'href'  => home_url( '/' . $tenders_slug . '/' ),
];
$nav_items[] = [
    'slug'  => 'eproc-logout',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    'label' => __( 'Logout', 'eprocurement' ),
    'href'  => wp_logout_url( home_url( '/' ) ),
];

$current_user = wp_get_current_user();
$logo_url     = '';
$logo_file    = WP_CONTENT_DIR . '/mu-plugins/sme-assets/mybliss-logo.png';
if ( file_exists( $logo_file ) ) {
    $logo_url = content_url( 'mu-plugins/sme-assets/mybliss-logo.png' );
}

?>
<div class="eproc-admin-shell">
    <!-- Sidebar Navigation -->
    <nav class="eproc-admin-sidebar">
        <div class="eproc-sidebar-brand">
            <?php if ( $logo_url ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="MyBliss Technologies" class="eproc-sidebar-logo">
            <?php endif; ?>
            <span class="eproc-sidebar-brand-text"><?php esc_html_e( 'eProcurement', 'eprocurement' ); ?></span>
        </div>

        <ul class="eproc-sidebar-nav">
            <?php foreach ( $nav_items as $item ) : ?>
                <?php if ( ! empty( $item['separator'] ) ) : ?>
                    <li class="eproc-nav-separator"></li>
                <?php else :
                    if ( ! empty( $item['href'] ) ) {
                        $href = $item['href'];
                    } else {
                        $href = admin_url( 'admin.php?page=' . $item['slug'] );
                        if ( ! empty( $item['action'] ) ) {
                            $href .= '&action=' . $item['action'];
                        }
                    }

                    // Determine active state
                    $is_active = false;
                    if ( ! empty( $item['action'] ) ) {
                        $is_active = ( $current_page === $item['slug'] && $current_action === $item['action'] );
                    } else {
                        // "All Bids" should be active for list view (no action or action=list)
                        if ( $item['slug'] === 'eprocurement-bids' && empty( $item['action'] ) ) {
                            $is_active = ( $current_page === 'eprocurement-bids' && $current_action !== 'new' );
                        } else {
                            $is_active = ( $current_page === $item['slug'] );
                        }
                    }

                    $active_class = $is_active ? ' active' : '';
                ?>
                    <li>
                        <a href="<?php echo esc_url( $href ); ?>" class="eproc-nav-item<?php echo esc_attr( $active_class ); ?>">
                            <span class="eproc-nav-icon"><?php echo wp_kses( $item['icon'], [ 'svg' => [ 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'class' => true, 'xmlns' => true ], 'path' => [ 'd' => true, 'fill' => true, 'stroke' => true ], 'circle' => [ 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ], 'rect' => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true, 'stroke' => true ], 'line' => [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true ], 'polyline' => [ 'points' => true, 'fill' => true, 'stroke' => true ], 'polygon' => [ 'points' => true, 'fill' => true, 'stroke' => true ], 'g' => [ 'fill' => true, 'stroke' => true, 'transform' => true ] ] ); ?></span>
                            <span class="eproc-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
                            <?php if ( ! empty( $item['badge'] ) && $item['badge'] > 0 ) : ?>
                                <span class="eproc-nav-badge"><?php echo esc_html( $item['badge'] ); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <!-- Sidebar Footer: User Info -->
        <div class="eproc-sidebar-footer">
            <div class="eproc-sidebar-user">
                <span class="eproc-sidebar-user-name"><?php echo esc_html( $current_user->display_name ); ?></span>
                <span class="eproc-sidebar-user-role"><?php
                    $roles = $current_user->roles;
                    echo esc_html( strtoupper( str_replace( '_', ' ', reset( $roles ) ) ) );
                ?></span>
            </div>
        </div>
    </nav>

    <!-- Content Area -->
    <main class="eproc-admin-content">
