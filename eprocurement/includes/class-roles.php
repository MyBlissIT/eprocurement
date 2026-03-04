<?php
/**
 * Custom roles and capabilities for the eProcurement plugin.
 *
 * Roles:
 * - eprocurement_scm_manager   : Full plugin control
 * - eprocurement_scm_official  : Bid management, no settings
 * - eprocurement_unit_manager  : Query inbox and reply only
 * - eprocurement_subscriber    : Frontend bidder (registered via portal)
 *
 * WordPress Admin and Editor also receive publish/close capabilities.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Roles {

    /**
     * All custom capabilities defined by the plugin.
     */
    public const CAPABILITIES = [
        'eproc_manage_settings',
        'eproc_create_bids',
        'eproc_edit_bids',
        'eproc_publish_bids',
        'eproc_close_bids',
        'eproc_delete_bids',
        'eproc_upload_documents',
        'eproc_manage_contacts',
        'eproc_view_threads',
        'eproc_reply_threads',
        'eproc_view_bidders',
        'eproc_view_downloads',
        'eproc_manage_compliance',
        'eproc_view_dashboard',
        'eproc_send_queries',
    ];

    /**
     * Role definitions with capability mappings.
     */
    private static function get_role_definitions(): array {
        return [
            'eprocurement_scm_manager' => [
                'label' => 'SCM Manager',
                'caps'  => [
                    // WordPress core
                    'read'                 => true,
                    'upload_files'         => true,
                    // Plugin capabilities (settings excluded — Super Admin only per BE-17)
                    'eproc_create_bids'       => true,
                    'eproc_edit_bids'         => true,
                    'eproc_publish_bids'      => true,
                    'eproc_close_bids'        => true,
                    'eproc_delete_bids'       => true,
                    'eproc_upload_documents'  => true,
                    'eproc_manage_contacts'   => true,
                    'eproc_view_threads'      => true,
                    'eproc_reply_threads'     => true,
                    'eproc_view_bidders'      => true,
                    'eproc_view_downloads'    => true,
                    'eproc_manage_compliance' => true,
                    'eproc_view_dashboard'    => true,
                ],
            ],
            'eprocurement_scm_official' => [
                'label' => 'SCM Official',
                'caps'  => [
                    'read'                 => true,
                    'upload_files'         => true,
                    'eproc_create_bids'       => true,
                    'eproc_edit_bids'         => true,
                    'eproc_publish_bids'      => true,
                    'eproc_close_bids'        => true,
                    'eproc_upload_documents'  => true,
                    'eproc_manage_contacts'   => true,
                    'eproc_view_threads'      => true,
                    'eproc_reply_threads'     => true,
                    'eproc_view_downloads'    => true,
                    'eproc_manage_compliance' => true,
                    'eproc_view_dashboard'    => true,
                ],
            ],
            'eprocurement_unit_manager' => [
                'label' => 'Unit Manager',
                'caps'  => [
                    'read'                 => true,
                    'eproc_view_threads'   => true,
                    'eproc_reply_threads'  => true,
                    'eproc_view_downloads' => true,
                    'eproc_view_dashboard' => true,
                ],
            ],
            'eprocurement_subscriber' => [
                'label' => 'eProcurement Bidder',
                'caps'  => [
                    'read'              => true,
                    'eproc_send_queries'   => true,
                ],
            ],
        ];
    }

    /**
     * Create all custom roles and add capabilities to WP Admin/Editor.
     */
    public static function create_roles(): void {
        $definitions = self::get_role_definitions();

        foreach ( $definitions as $role_slug => $role_data ) {
            // Remove if exists (ensures clean update)
            remove_role( $role_slug );
            add_role( $role_slug, $role_data['label'], $role_data['caps'] );
        }

        // Add plugin capabilities to WordPress Administrator
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::CAPABILITIES as $cap ) {
                $admin->add_cap( $cap );
            }
        }

        // Add publish/close capabilities to WordPress Editor
        $editor = get_role( 'editor' );
        if ( $editor ) {
            $editor_caps = [
                'eproc_create_bids',
                'eproc_edit_bids',
                'eproc_publish_bids',
                'eproc_close_bids',
                'eproc_upload_documents',
                'eproc_manage_contacts',
                'eproc_view_threads',
                'eproc_reply_threads',
                'eproc_view_downloads',
                'eproc_manage_compliance',
                'eproc_view_dashboard',
            ];
            foreach ( $editor_caps as $cap ) {
                $editor->add_cap( $cap );
            }
        }
    }

    /**
     * Check if current user has a specific eProcurement capability.
     */
    public static function current_user_can( string $capability ): bool {
        return current_user_can( $capability );
    }

    /**
     * Check if a user is a bidder (subscriber role).
     */
    public static function is_bidder( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }
        return in_array( 'eprocurement_subscriber', (array) $user->roles, true );
    }

    /**
     * Check if a user is any staff role (SCM Manager, SCM Official, Unit Manager, Admin, Editor).
     */
    public static function is_staff( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }
        $staff_roles = [
            'administrator',
            'editor',
            'eprocurement_scm_manager',
            'eprocurement_scm_official',
            'eprocurement_unit_manager',
        ];
        return ! empty( array_intersect( $staff_roles, (array) $user->roles ) );
    }
}
