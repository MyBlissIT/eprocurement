<?php
/**
 * Demo data seeder.
 *
 * Seeds the database with sample users, contacts, bids, threads, and
 * messages for demonstration purposes. All demo data is tagged so it
 * can be cleanly removed later.
 *
 * @package Eprocurement
 * @since   2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Demo_Data {

    /** Meta key used to tag demo users for clean removal. */
    private const DEMO_META_KEY = '_eproc_demo_data';

    /** Default password for all demo users. */
    private const DEMO_PASSWORD = 'demo123';

    /**
     * Check if demo data has been seeded.
     */
    public static function is_seeded(): bool {
        return (bool) get_option( 'eprocurement_demo_data_seeded' );
    }

    /**
     * Seed all demo data.
     *
     * @return array{success: bool, message: string, summary: array}
     */
    public static function seed(): array {
        if ( self::is_seeded() ) {
            return [
                'success' => false,
                'message' => __( 'Demo data already exists. Remove it first before re-seeding.', 'eprocurement' ),
                'summary' => [],
            ];
        }

        global $wpdb;
        $prefix  = $wpdb->prefix . EPROC_TABLE_PREFIX;
        $summary = [];

        // ── 1. Create demo users ───────────────────────────────────
        $users = self::create_demo_users();
        $summary['users'] = count( $users );

        // ── 2. Create contact persons ──────────────────────────────
        $contacts = self::create_demo_contacts( $users );
        $summary['contacts'] = count( $contacts );

        // ── 3. Create sample bids ──────────────────────────────────
        $bids = self::create_demo_bids( $users, $contacts );
        $summary['bids'] = count( $bids );

        // ── 4. Create Q&A threads and messages ─────────────────────
        $threads = self::create_demo_threads( $users, $contacts, $bids );
        $summary['threads'] = $threads;

        // ── 5. Enable notification settings ────────────────────────
        update_option( 'eprocurement_notification_settings', wp_json_encode( [
            'new_bid_notify_bidders'  => true,
            'query_notify_contact'    => true,
            'reply_notify_bidder'     => true,
            'status_change_notify'    => true,
        ] ) );

        // ── 6. Enable all bid categories ───────────────────────────
        update_option( 'eprocurement_category_briefing_register', '1' );
        update_option( 'eprocurement_category_closing_register', '1' );
        update_option( 'eprocurement_category_appointments', '1' );

        // Mark as seeded
        update_option( 'eprocurement_demo_data_seeded', true );

        return [
            'success' => true,
            'message' => sprintf(
                __( 'Demo data seeded: %d users, %d contacts, %d bids, %d Q&A threads.', 'eprocurement' ),
                $summary['users'],
                $summary['contacts'],
                $summary['bids'],
                $summary['threads']
            ),
            'summary' => $summary,
        ];
    }

    /**
     * Remove all demo data.
     *
     * @return array{success: bool, message: string}
     */
    public static function remove(): array {
        if ( ! self::is_seeded() ) {
            return [
                'success' => false,
                'message' => __( 'No demo data found to remove.', 'eprocurement' ),
            ];
        }

        global $wpdb;
        $prefix = $wpdb->prefix . EPROC_TABLE_PREFIX;

        // Get all demo user IDs
        $demo_user_ids = get_users( [
            'meta_key'   => self::DEMO_META_KEY,
            'meta_value' => '1',
            'fields'     => 'ID',
        ] );

        if ( ! empty( $demo_user_ids ) ) {
            $id_placeholders = implode( ',', array_fill( 0, count( $demo_user_ids ), '%d' ) );

            // Delete threads and messages created by demo users
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $thread_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$prefix}threads WHERE bidder_id IN ({$id_placeholders})",
                    ...$demo_user_ids
                )
            );

            if ( ! empty( $thread_ids ) ) {
                $thread_placeholders = implode( ',', array_fill( 0, count( $thread_ids ), '%d' ) );

                // Delete message attachments
                $message_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT id FROM {$prefix}messages WHERE thread_id IN ({$thread_placeholders})",
                        ...$thread_ids
                    )
                );

                if ( ! empty( $message_ids ) ) {
                    $msg_placeholders = implode( ',', array_fill( 0, count( $message_ids ), '%d' ) );
                    $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$prefix}message_attachments WHERE message_id IN ({$msg_placeholders})",
                            ...$message_ids
                        )
                    );
                }

                // Delete messages
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$prefix}messages WHERE thread_id IN ({$thread_placeholders})",
                        ...$thread_ids
                    )
                );

                // Delete threads
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$prefix}threads WHERE id IN ({$thread_placeholders})",
                        ...$thread_ids
                    )
                );
            }

            // Delete bidder profiles
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$prefix}bidder_profiles WHERE user_id IN ({$id_placeholders})",
                    ...$demo_user_ids
                )
            );

            // Delete downloads by demo users
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$prefix}downloads WHERE user_id IN ({$id_placeholders})",
                    ...$demo_user_ids
                )
            );

            // Delete bids created by demo users
            $bid_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$prefix}documents WHERE created_by IN ({$id_placeholders})",
                    ...$demo_user_ids
                )
            );

            if ( ! empty( $bid_ids ) ) {
                $bid_placeholders = implode( ',', array_fill( 0, count( $bid_ids ), '%d' ) );

                // Delete supporting docs for demo bids
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$prefix}supporting_docs WHERE document_id IN ({$bid_placeholders})",
                        ...$bid_ids
                    )
                );

                // Delete bids
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$prefix}documents WHERE id IN ({$bid_placeholders})",
                        ...$bid_ids
                    )
                );
            }

            // Delete demo contact persons
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$prefix}contact_persons WHERE user_id IN ({$id_placeholders})",
                    ...$demo_user_ids
                )
            );
            // phpcs:enable

            // Delete the WP users themselves
            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ( $demo_user_ids as $uid ) {
                wp_delete_user( (int) $uid );
            }
        }

        // Also remove any contact persons tagged as demo (without user_id)
        $wpdb->query( "DELETE FROM {$prefix}contact_persons WHERE department = '__demo__'" ); // phpcs:ignore

        delete_option( 'eprocurement_demo_data_seeded' );

        return [
            'success' => true,
            'message' => __( 'All demo data has been removed.', 'eprocurement' ),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────

    /**
     * Create the 4 demo users (one per role).
     *
     * @return array<string, int> Map of role key → user ID.
     */
    private static function create_demo_users(): array {
        $users_data = [
            'scm_manager' => [
                'user_login'   => 'demo-scm-manager',
                'user_email'   => 'scm-manager@demo.test',
                'display_name' => 'Thandi Mabaso',
                'first_name'   => 'Thandi',
                'last_name'    => 'Mabaso',
                'role'         => 'eprocurement_scm_manager',
            ],
            'scm_official' => [
                'user_login'   => 'demo-scm-official',
                'user_email'   => 'scm-official@demo.test',
                'display_name' => 'Sipho Dlamini',
                'first_name'   => 'Sipho',
                'last_name'    => 'Dlamini',
                'role'         => 'eprocurement_scm_official',
            ],
            'unit_manager' => [
                'user_login'   => 'demo-unit-manager',
                'user_email'   => 'unit-manager@demo.test',
                'display_name' => 'Lerato Mokoena',
                'first_name'   => 'Lerato',
                'last_name'    => 'Mokoena',
                'role'         => 'eprocurement_unit_manager',
            ],
            'bidder' => [
                'user_login'   => 'demo-bidder',
                'user_email'   => 'bidder@demo.test',
                'display_name' => 'James van Wyk',
                'first_name'   => 'James',
                'last_name'    => 'van Wyk',
                'role'         => 'eprocurement_subscriber',
            ],
        ];

        $created = [];

        foreach ( $users_data as $key => $data ) {
            // Check if user already exists
            $existing = get_user_by( 'login', $data['user_login'] );
            if ( $existing ) {
                $created[ $key ] = $existing->ID;
                update_user_meta( $existing->ID, self::DEMO_META_KEY, '1' );
                continue;
            }

            $user_id = wp_insert_user( [
                'user_login'   => $data['user_login'],
                'user_pass'    => self::DEMO_PASSWORD,
                'user_email'   => $data['user_email'],
                'display_name' => $data['display_name'],
                'first_name'   => $data['first_name'],
                'last_name'    => $data['last_name'],
                'role'         => $data['role'],
            ] );

            if ( ! is_wp_error( $user_id ) ) {
                update_user_meta( $user_id, self::DEMO_META_KEY, '1' );
                $created[ $key ] = $user_id;
            }
        }

        // Create bidder profile for the demo bidder
        if ( isset( $created['bidder'] ) ) {
            global $wpdb;
            $bp_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'bidder_profiles';

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$bp_table} WHERE user_id = %d",
                    $created['bidder']
                )
            );

            if ( ! $exists ) {
                $wpdb->insert( $bp_table, [
                    'user_id'        => $created['bidder'],
                    'company_name'   => 'Van Wyk & Associates',
                    'company_reg'    => '2024/123456/07',
                    'phone'          => '+27 82 555 0101',
                    'verified'       => 1,
                    'notify_replies' => 1,
                ] );
            }
        }

        return $created;
    }

    /**
     * Create demo contact persons.
     *
     * @param array<string, int> $users Map of role key → user ID.
     * @return array<string, int> Map of contact key → contact ID.
     */
    private static function create_demo_contacts( array $users ): array {
        global $wpdb;
        $table    = $wpdb->prefix . EPROC_TABLE_PREFIX . 'contact_persons';
        $contacts = [];

        $contacts_data = [
            'scm_primary' => [
                'user_id'    => $users['scm_manager'] ?? null,
                'type'       => 'scm',
                'name'       => 'Thandi Mabaso',
                'phone'      => '+27 12 555 0001',
                'email'      => 'scm@demo.test',
                'department' => 'Supply Chain Management',
            ],
            'scm_finance' => [
                'user_id'    => $users['scm_official'] ?? null,
                'type'       => 'scm',
                'name'       => 'Sipho Dlamini',
                'phone'      => '+27 12 555 0002',
                'email'      => 'finance@demo.test',
                'department' => 'Finance',
            ],
            'technical' => [
                'user_id'    => null,
                'type'       => 'technical',
                'name'       => 'Sarah van der Merwe',
                'phone'      => '+27 12 555 0003',
                'email'      => 'tech@demo.test',
                'department' => 'Information Technology',
            ],
        ];

        foreach ( $contacts_data as $key => $data ) {
            $wpdb->insert( $table, $data );
            $contacts[ $key ] = $wpdb->insert_id;
        }

        return $contacts;
    }

    /**
     * Create demo bids.
     *
     * @param array<string, int> $users    Map of role key → user ID.
     * @param array<string, int> $contacts Map of contact key → contact ID.
     * @return array<string, int> Map of bid key → document ID.
     */
    private static function create_demo_bids( array $users, array $contacts ): array {
        global $wpdb;
        $table      = $wpdb->prefix . EPROC_TABLE_PREFIX . 'documents';
        $created_by = $users['scm_manager'] ?? get_current_user_id();
        $bids       = [];

        $now  = current_time( 'mysql' );
        $future_30  = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
        $future_60  = gmdate( 'Y-m-d H:i:s', strtotime( '+60 days' ) );
        $future_14  = gmdate( 'Y-m-d H:i:s', strtotime( '+14 days' ) );
        $past_30    = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
        $past_7     = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

        $bids_data = [
            'office_furniture' => [
                'bid_number'           => 'BID/2026/001',
                'title'                => 'Supply and Delivery of Office Furniture',
                'description'          => "The entity invites proposals from suitably qualified service providers for the supply and delivery of office furniture to various municipal offices.\n\nScope includes ergonomic desk chairs, adjustable standing desks, conference tables, and filing cabinets. Delivery to three (3) locations within the municipal area.\n\nPreference will be given to B-BBEE Level 1–3 contributors.",
                'status'               => 'open',
                'category'             => 'bid',
                'scm_contact_id'       => $contacts['scm_primary'] ?? null,
                'technical_contact_id' => $contacts['technical'] ?? null,
                'opening_date'         => $now,
                'briefing_date'        => $future_14,
                'closing_date'         => $future_30,
                'created_by'           => $created_by,
            ],
            'it_infrastructure' => [
                'bid_number'           => 'BID/2026/002',
                'title'                => 'IT Infrastructure Upgrade Phase 2',
                'description'          => "Request for proposals for the upgrade of the entity's IT infrastructure including server hardware, network switches, firewall appliances, and UPS systems.\n\nThe project includes installation, configuration, data migration, and 12-month on-site support. Current infrastructure assessment documents available upon request.\n\nMinimum requirements: MICT SETA accredited, ISO 27001 certified.",
                'status'               => 'open',
                'category'             => 'bid',
                'scm_contact_id'       => $contacts['scm_finance'] ?? null,
                'technical_contact_id' => $contacts['technical'] ?? null,
                'opening_date'         => $now,
                'briefing_date'        => null,
                'closing_date'         => $future_60,
                'created_by'           => $created_by,
            ],
            'cleaning_services' => [
                'bid_number'           => 'BID/2026/003',
                'title'                => 'Cleaning Services for Municipal Buildings',
                'description'          => 'Invitation to tender for comprehensive cleaning services across all municipal buildings for a period of 36 months. Service to include daily office cleaning, window washing, carpet shampooing, and grounds maintenance.',
                'status'               => 'draft',
                'category'             => 'bid',
                'scm_contact_id'       => $contacts['scm_primary'] ?? null,
                'technical_contact_id' => null,
                'opening_date'         => null,
                'briefing_date'        => null,
                'closing_date'         => null,
                'created_by'           => $created_by,
            ],
            'security_services' => [
                'bid_number'           => 'BID/2026/004',
                'title'                => 'Security Services Tender 2026/2027',
                'description'          => 'Tender for the provision of security guard services at municipal premises for the 2026/2027 financial year. PSIRA registration mandatory. Armed and unarmed response required.',
                'status'               => 'draft',
                'category'             => 'bid',
                'scm_contact_id'       => $contacts['scm_finance'] ?? null,
                'technical_contact_id' => null,
                'opening_date'         => null,
                'briefing_date'        => null,
                'closing_date'         => null,
                'created_by'           => $created_by,
            ],
            'stationery' => [
                'bid_number'           => 'BID/2025/089',
                'title'                => 'Stationery and Office Supplies',
                'description'          => 'Supply of stationery and general office supplies for a period of 12 months. Items include printing paper, toner cartridges, pens, folders, and general consumables.',
                'status'               => 'closed',
                'category'             => 'bid',
                'scm_contact_id'       => $contacts['scm_primary'] ?? null,
                'technical_contact_id' => null,
                'opening_date'         => $past_30,
                'briefing_date'        => null,
                'closing_date'         => $past_7,
                'created_by'           => $created_by,
            ],
            'fleet_management' => [
                'bid_number'           => 'BID/2025/090',
                'title'                => 'Fleet Management Services',
                'description'          => 'This tender for fleet management services has been cancelled due to revised budget allocations. A new tender will be issued in the next financial quarter.',
                'status'               => 'cancelled',
                'category'             => 'bid',
                'scm_contact_id'       => $contacts['scm_finance'] ?? null,
                'technical_contact_id' => $contacts['technical'] ?? null,
                'opening_date'         => $past_30,
                'briefing_date'        => null,
                'closing_date'         => $past_7,
                'created_by'           => $created_by,
            ],
        ];

        foreach ( $bids_data as $key => $data ) {
            $wpdb->insert( $table, $data );
            $bids[ $key ] = $wpdb->insert_id;
        }

        return $bids;
    }

    /**
     * Create demo Q&A threads and messages.
     *
     * @param array<string, int> $users    Map of role key → user ID.
     * @param array<string, int> $contacts Map of contact key → contact ID.
     * @param array<string, int> $bids     Map of bid key → document ID.
     * @return int Number of threads created.
     */
    private static function create_demo_threads( array $users, array $contacts, array $bids ): int {
        global $wpdb;
        $threads_table  = $wpdb->prefix . EPROC_TABLE_PREFIX . 'threads';
        $messages_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'messages';

        $bidder_id  = $users['bidder'] ?? 0;
        $manager_id = $users['scm_manager'] ?? 0;

        if ( ! $bidder_id || ! $manager_id ) {
            return 0;
        }

        $count = 0;

        // ── Thread 1: Delivery timeline query on Office Furniture bid ───
        if ( isset( $bids['office_furniture'] ) && isset( $contacts['scm_primary'] ) ) {
            $wpdb->insert( $threads_table, [
                'document_id' => $bids['office_furniture'],
                'bidder_id'   => $bidder_id,
                'contact_id'  => $contacts['scm_primary'],
                'subject'     => 'Delivery timeline and phased delivery options',
                'visibility'  => 'private',
                'status'      => 'open',
            ] );
            $thread_id = $wpdb->insert_id;

            // Bidder's initial message
            $wpdb->insert( $messages_table, [
                'thread_id' => $thread_id,
                'sender_id' => $bidder_id,
                'message'   => "Good day,\n\nWith reference to BID/2026/001, could you please clarify the expected delivery timeline? Specifically:\n\n1. Is phased delivery acceptable (e.g., desks in week 1, chairs in week 2)?\n2. What are the three delivery locations?\n3. Is there a penalty clause for late delivery?\n\nThank you for your assistance.\n\nKind regards,\nJames van Wyk\nVan Wyk & Associates",
                'is_read'   => 1,
            ] );

            // Staff reply
            $wpdb->insert( $messages_table, [
                'thread_id' => $thread_id,
                'sender_id' => $manager_id,
                'message'   => "Dear Mr van Wyk,\n\nThank you for your query.\n\n1. Yes, phased delivery is acceptable provided all items are delivered within 30 calendar days of the purchase order date.\n2. The three locations are: Main Office (CBD), Regional Office (Summerstrand), and Satellite Office (Uitenhage).\n3. Penalty details are outlined in section 4.3 of the Terms and Conditions document.\n\nPlease let us know if you have further questions.\n\nRegards,\nThandi Mabaso\nSCM Manager",
                'is_read'   => 0,
            ] );

            $count++;
        }

        // ── Thread 2: Technical specs on IT Infrastructure bid ──────────
        if ( isset( $bids['it_infrastructure'] ) && isset( $contacts['scm_finance'] ) ) {
            $wpdb->insert( $threads_table, [
                'document_id' => $bids['it_infrastructure'],
                'bidder_id'   => $bidder_id,
                'contact_id'  => $contacts['scm_finance'],
                'subject'     => 'Minimum server specifications clarification',
                'visibility'  => 'public',
                'status'      => 'open',
            ] );
            $thread_id = $wpdb->insert_id;

            // Bidder's initial message
            $wpdb->insert( $messages_table, [
                'thread_id' => $thread_id,
                'sender_id' => $bidder_id,
                'message'   => "Good day,\n\nRegarding BID/2026/002, the bid document mentions server hardware upgrade but does not specify minimum hardware specifications.\n\nCould you please provide:\n- Minimum CPU cores / RAM per server\n- Storage type requirement (SSD/NVMe)\n- Virtualisation platform preference (VMware, Hyper-V, etc.)\n- Number of servers to be upgraded\n\nThis will help us provide an accurate costing.\n\nRegards,\nJames van Wyk",
                'is_read'   => 1,
            ] );

            $count++;
        }

        return $count;
    }
}
