<?php
/**
 * Frontend Admin Layout Wrapper — Staff sidebar navigation.
 *
 * Renders the same eProcurement admin shell as wp-admin but on the
 * frontend at /tenders/manage/. Uses home_url() links instead of admin_url().
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug         = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base  = home_url( "/{$slug}/manage" );
$messaging    = new Eprocurement_Messaging();
$unread_count = $messaging->get_unread_count( get_current_user_id() );
$current_user = wp_get_current_user();

// Build navigation items
$nav_items = [
    [
        'page'  => '',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        'label' => __( 'Dashboard', 'eprocurement' ),
    ],
    [
        'page'  => 'bids',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'label' => __( 'All Bids', 'eprocurement' ),
        'cap'   => 'eproc_view_dashboard',
    ],
    [
        'page'   => 'bids',
        'action' => 'new',
        'icon'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        'label'  => __( 'Add New Bid', 'eprocurement' ),
        'cap'    => 'eproc_create_bids',
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
        $cat_path = str_replace( '_', '-', $cat_key );
        $nav_items[] = [
            'page'     => 'bids',
            'category' => $cat_key,
            'icon'     => $cat_data['icon'],
            'label'    => $cat_data['label'],
            'cap'      => 'eproc_create_bids',
        ];
    }
}

$nav_items[] = [ 'separator' => true ];

if ( current_user_can( 'eproc_manage_contacts' ) ) {
    $nav_items[] = [
        'page'  => 'contacts',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'label' => __( 'Contact Persons', 'eprocurement' ),
    ];
}

$nav_items[] = [
    'page'  => 'messages',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'label' => __( 'Messages', 'eprocurement' ),
    'badge' => $unread_count,
    'cap'   => 'eproc_view_threads',
];

if ( current_user_can( 'eproc_view_bidders' ) ) {
    $nav_items[] = [
        'page'  => 'bidders',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'label' => __( 'Bidders', 'eprocurement' ),
    ];
}

$nav_items[] = [ 'separator' => true ];

$nav_items[] = [
    'page'  => 'downloads',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
    'label' => __( 'Download Log', 'eprocurement' ),
    'cap'   => 'eproc_view_downloads',
];

$nav_items[] = [
    'page'  => 'scm-docs',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'label' => Eprocurement_Compliance_Docs::get_section_title(),
    'cap'   => 'eproc_manage_compliance',
];

// Super Admin only items
if ( is_super_admin() ) {
    $nav_items[] = [ 'separator' => true ];
    $nav_items[] = [
        'page'  => 'users',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
        'label' => __( 'User Management', 'eprocurement' ),
    ];
    $nav_items[] = [
        'page'  => 'settings',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'label' => __( 'Settings', 'eprocurement' ),
    ];
}

// Build logout URL
$logout_url = wp_logout_url( home_url( "/{$slug}/" ) );
?>
<div class="eproc-wrap eproc-frontend-manage">
<div class="eproc-admin-shell">
    <!-- Sidebar Navigation -->
    <nav class="eproc-admin-sidebar">
        <div class="eproc-sidebar-brand">
            <a href="<?php echo esc_url( $manage_base . '/' ); ?>" style="color:inherit;text-decoration:none;">
                <?php esc_html_e( 'EPROCUREMENT', 'eprocurement' ); ?>
            </a>
        </div>

        <ul class="eproc-sidebar-nav">
            <?php foreach ( $nav_items as $item ) : ?>
                <?php if ( ! empty( $item['separator'] ) ) : ?>
                    <li class="eproc-nav-separator"></li>
                <?php else :
                    // Check capability
                    if ( ! empty( $item['cap'] ) && ! current_user_can( $item['cap'] ) ) {
                        continue;
                    }

                    // Build URL
                    $href = $manage_base . '/' . ( $item['page'] ? $item['page'] . '/' : '' );
                    if ( ! empty( $item['action'] ) ) {
                        $href = add_query_arg( 'action', $item['action'], $href );
                    }
                    if ( ! empty( $item['category'] ) ) {
                        $href = add_query_arg( 'category', $item['category'], $href );
                    }

                    // Determine active state
                    $is_active = false;
                    if ( ! empty( $item['action'] ) ) {
                        $is_active = ( $active_page === $item['page'] && ( $_GET['action'] ?? '' ) === $item['action'] );
                    } elseif ( ! empty( $item['category'] ) ) {
                        $is_active = ( $active_page === 'bids' && ( $_GET['category'] ?? '' ) === $item['category'] );
                    } elseif ( $item['page'] === 'bids' && empty( $item['action'] ) && empty( $item['category'] ) ) {
                        $is_active = ( $active_page === 'bids' && empty( $_GET['action'] ) && empty( $_GET['category'] ) );
                    } else {
                        $is_active = ( $active_page === $item['page'] );
                    }

                    $active_class = $is_active ? ' active' : '';
                ?>
                    <li>
                        <a href="<?php echo esc_url( $href ); ?>" class="eproc-nav-item<?php echo esc_attr( $active_class ); ?>">
                            <span class="eproc-nav-icon"><?php echo $item['icon']; // SVGs are safe ?></span>
                            <span class="eproc-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
                            <?php if ( ! empty( $item['badge'] ) && $item['badge'] > 0 ) : ?>
                                <span class="eproc-nav-badge"><?php echo esc_html( $item['badge'] ); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Separator before user actions -->
            <li class="eproc-nav-separator"></li>

            <!-- Back to Portal -->
            <li>
                <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-nav-item">
                    <span class="eproc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></span>
                    <span class="eproc-nav-label"><?php esc_html_e( 'Back to Portal', 'eprocurement' ); ?></span>
                </a>
            </li>

            <!-- Logout -->
            <li>
                <a href="<?php echo esc_url( $logout_url ); ?>" class="eproc-nav-item">
                    <span class="eproc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                    <span class="eproc-nav-label"><?php esc_html_e( 'Logout', 'eprocurement' ); ?></span>
                </a>
            </li>
        </ul>

        <!-- User info at bottom -->
        <div class="eproc-sidebar-user">
            <span class="eproc-sidebar-user-name"><?php echo esc_html( $current_user->display_name ); ?></span>
            <span class="eproc-sidebar-user-role"><?php echo esc_html( ucwords( str_replace( [ 'eprocurement_', '_' ], [ '', ' ' ], $current_user->roles[0] ?? '' ) ) ); ?></span>
        </div>
    </nav>

    <!-- Content Area -->
    <main class="eproc-admin-content">
