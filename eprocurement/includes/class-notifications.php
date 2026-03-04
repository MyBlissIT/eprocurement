<?php
/**
 * Email Notifications.
 *
 * Handles all wp_mail() notification emails for plugin events.
 *
 * Events:
 * - New bidder registration (verification email handled by Bidder class)
 * - New query submitted → contact person
 * - Reply posted → bidder
 * - New bid opened → all verified bidders (optional)
 * - Bid status changed → SCM Manager digest
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Notifications {

    /**
     * Register notification hooks.
     */
    public function __construct() {
        add_action( 'eprocurement_query_created', [ $this, 'notify_new_query' ], 10, 2 );
        add_action( 'eprocurement_reply_posted', [ $this, 'notify_reply' ], 10, 2 );
        add_action( 'eprocurement_status_changed', [ $this, 'notify_status_change' ], 10, 3 );
        add_action( 'eprocurement_bid_published', [ $this, 'notify_new_bid' ], 10, 1 );
        add_action( 'eprocurement_visibility_changed', [ $this, 'notify_visibility_change' ], 10, 4 );
    }

    /**
     * Get notification settings.
     */
    private function get_settings(): array {
        $settings = get_option( 'eprocurement_notification_settings', '' );
        if ( is_string( $settings ) ) {
            $settings = json_decode( $settings, true );
        }
        return is_array( $settings ) ? $settings : [];
    }

    /**
     * Check if a notification type is enabled.
     */
    private function is_enabled( string $key ): bool {
        $settings = $this->get_settings();
        return ! empty( $settings[ $key ] );
    }

    /**
     * Notify contact person about a new query.
     *
     * @param int $thread_id Thread ID.
     * @param int $message_id First message ID.
     */
    public function notify_new_query( int $thread_id, int $message_id ): void {
        if ( ! $this->is_enabled( 'query_notify_contact' ) ) {
            return;
        }

        $thread = Eprocurement_Database::get_by_id( 'threads', $thread_id );
        if ( ! $thread ) {
            return;
        }

        $contact = Eprocurement_Database::get_by_id( 'contact_persons', (int) $thread->contact_id );
        if ( ! $contact || ! $contact->email ) {
            return;
        }

        $document = Eprocurement_Database::get_by_id( 'documents', (int) $thread->document_id );
        $bidder   = get_userdata( (int) $thread->bidder_id );
        $message  = Eprocurement_Database::get_by_id( 'messages', $message_id );

        $subject = sprintf(
            /* translators: 1: Bid number, 2: Bid title */
            __( 'New Query: %1$s — %2$s', 'eprocurement' ),
            $document->bid_number ?? '',
            $document->title ?? ''
        );

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        $body = sprintf(
            /* translators: 1: Contact name, 2: Bidder name, 3: Bid number, 4: Visibility, 5: Message excerpt, 6: Manage URL */
            __(
                "Hello %1\$s,\n\n" .
                "A new query has been submitted by %2\$s regarding %3\$s.\n\n" .
                "Visibility: %4\$s\n\n" .
                "Message:\n%5\$s\n\n" .
                "Please log in to respond:\n%6\$s\n\n" .
                "Regards,\neProcurement System",
                'eprocurement'
            ),
            $contact->name,
            $bidder ? $bidder->display_name : 'Unknown',
            $document->bid_number ?? '',
            ucfirst( $thread->visibility ),
            $message ? wp_strip_all_tags( $message->message ) : '',
            home_url( "/{$slug}/manage/messages/?thread_id=" . $thread_id )
        );

        wp_mail( $contact->email, $subject, $body );
    }

    /**
     * Notify bidder about a reply to their query.
     *
     * @param int $thread_id  Thread ID.
     * @param int $message_id Reply message ID.
     */
    public function notify_reply( int $thread_id, int $message_id ): void {
        if ( ! $this->is_enabled( 'reply_notify_bidder' ) ) {
            return;
        }

        $thread = Eprocurement_Database::get_by_id( 'threads', $thread_id );
        if ( ! $thread ) {
            return;
        }

        $bidder  = get_userdata( (int) $thread->bidder_id );
        if ( ! $bidder ) {
            return;
        }

        // Check if the bidder has opted out of reply notifications
        global $wpdb;
        $bp_table = Eprocurement_Database::table( 'bidder_profiles' );
        $notify   = $wpdb->get_var( $wpdb->prepare(
            "SELECT notify_replies FROM {$bp_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            (int) $thread->bidder_id
        ) );
        if ( $notify !== null && (int) $notify === 0 ) {
            return;
        }

        $message  = Eprocurement_Database::get_by_id( 'messages', $message_id );
        $sender   = get_userdata( (int) ( $message->sender_id ?? 0 ) );
        $document = Eprocurement_Database::get_by_id( 'documents', (int) $thread->document_id );

        $subject = sprintf(
            /* translators: 1: Bid number */
            __( 'Reply to your query: %1$s', 'eprocurement' ),
            $document->bid_number ?? ''
        );

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        $body = sprintf(
            /* translators: 1: Bidder name, 2: Responder name, 3: Bid number, 4: Message excerpt, 5: Dashboard URL */
            __(
                "Hello %1\$s,\n\n" .
                "%2\$s has replied to your query about %3\$s.\n\n" .
                "Reply:\n%4\$s\n\n" .
                "View the full conversation in your dashboard:\n%5\$s\n\n" .
                "Regards,\neProcurement System",
                'eprocurement'
            ),
            $bidder->display_name,
            $sender ? $sender->display_name : 'Staff',
            $document->bid_number ?? '',
            $message ? wp_strip_all_tags( $message->message ) : '',
            home_url( "/{$slug}/my-account/" )
        );

        wp_mail( $bidder->user_email, $subject, $body );
    }

    /**
     * Notify about bid status change.
     *
     * @param int    $document_id Document ID.
     * @param string $new_status  New status.
     * @param string $old_status  Old status.
     */
    public function notify_status_change( int $document_id, string $new_status, string $old_status ): void {
        if ( ! $this->is_enabled( 'status_change_notify' ) ) {
            return;
        }

        $document = Eprocurement_Database::get_by_id( 'documents', $document_id );
        if ( ! $document ) {
            return;
        }

        // Notify SCM Managers
        $managers = get_users( [ 'role' => 'eprocurement_scm_manager' ] );

        foreach ( $managers as $manager ) {
            $subject = sprintf(
                /* translators: 1: Bid number, 2: New status */
                __( 'Bid Status Update: %1$s is now %2$s', 'eprocurement' ),
                $document->bid_number,
                strtoupper( $new_status )
            );

            $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

            $body = sprintf(
                /* translators: 1: Manager name, 2: Bid number, 3: Bid title, 4: Old status, 5: New status, 6: Manage URL */
                __(
                    "Hello %1\$s,\n\n" .
                    "The status of %2\$s — %3\$s has changed.\n\n" .
                    "Previous status: %4\$s\n" .
                    "New status: %5\$s\n\n" .
                    "View bid:\n%6\$s\n\n" .
                    "Regards,\neProcurement System",
                    'eprocurement'
                ),
                $manager->display_name,
                $document->bid_number,
                $document->title,
                strtoupper( $old_status ),
                strtoupper( $new_status ),
                home_url( "/{$slug}/manage/bids/?action=edit&id=" . $document_id )
            );

            wp_mail( $manager->user_email, $subject, $body );
        }

        // If opened → notify bidders
        if ( $new_status === 'open' ) {
            do_action( 'eprocurement_bid_published', $document_id );
        }
    }

    /**
     * Notify all verified bidders about a newly opened bid.
     *
     * @param int $document_id Document ID.
     */
    public function notify_new_bid( int $document_id ): void {
        if ( ! $this->is_enabled( 'new_bid_notify_bidders' ) ) {
            return;
        }

        $document = Eprocurement_Database::get_by_id( 'documents', $document_id );
        if ( ! $document ) {
            return;
        }

        // Get all verified bidders
        global $wpdb;
        $profiles_table = Eprocurement_Database::table( 'bidder_profiles' );

        $bidders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT bp.user_id, u.user_email, u.display_name
                 FROM {$profiles_table} bp
                 JOIN {$wpdb->users} u ON bp.user_id = u.ID
                 WHERE bp.verified = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                1
            )
        );

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        foreach ( $bidders as $bidder ) {
            $subject = sprintf(
                /* translators: 1: Bid number, 2: Bid title */
                __( 'New Tender Opened: %1$s — %2$s', 'eprocurement' ),
                $document->bid_number,
                $document->title
            );

            $body = sprintf(
                /* translators: 1: Bidder name, 2: Bid number, 3: Bid title, 4: Closing date, 5: Tender URL */
                __(
                    "Hello %1\$s,\n\n" .
                    "A new tender has been opened:\n\n" .
                    "Bid Number: %2\$s\n" .
                    "Title: %3\$s\n" .
                    "Closing Date: %4\$s\n\n" .
                    "View full details and download documents:\n%5\$s\n\n" .
                    "Regards,\neProcurement System",
                    'eprocurement'
                ),
                $bidder->display_name,
                $document->bid_number,
                $document->title,
                $document->closing_date ? wp_date( 'j F Y', strtotime( $document->closing_date ) ) : 'TBC',
                home_url( "/{$slug}/bid/" . $document->id . '/' )
            );

            wp_mail( $bidder->user_email, $subject, $body );
        }
    }

    /**
     * Notify the original bidder that their thread visibility was changed.
     *
     * @param int    $thread_id       Thread ID.
     * @param string $old_visibility  Previous visibility.
     * @param string $new_visibility  New visibility.
     * @param string $reason          Reason provided by the staff member.
     */
    public function notify_visibility_change( int $thread_id, string $old_visibility, string $new_visibility, string $reason ): void {
        $thread = Eprocurement_Database::get_by_id( 'threads', $thread_id );
        if ( ! $thread ) {
            return;
        }

        $bidder = get_userdata( (int) $thread->bidder_id );
        if ( ! $bidder ) {
            return;
        }

        $document = Eprocurement_Database::get_by_id( 'documents', (int) $thread->document_id );
        $staff    = wp_get_current_user();

        $subject = sprintf(
            /* translators: 1: Bid number */
            __( 'Your query visibility has been changed: %1$s', 'eprocurement' ),
            $document ? $document->bid_number : ''
        );

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        $body = sprintf(
            /* translators: 1: Bidder name, 2: Old visibility, 3: New visibility, 4: Staff name, 5: Bid number, 6: Reason, 7: Dashboard URL */
            __(
                "Hello %1\$s,\n\n" .
                "The visibility of your query has been changed from %2\$s to %3\$s by %4\$s.\n\n" .
                "Bid: %5\$s\n" .
                "Subject: %6\$s\n\n" .
                "Reason:\n%7\$s\n\n" .
                "You can view your queries in your dashboard:\n%8\$s\n\n" .
                "Regards,\neProcurement System",
                'eprocurement'
            ),
            $bidder->display_name,
            strtoupper( $old_visibility ),
            strtoupper( $new_visibility ),
            $staff->display_name,
            $document ? $document->bid_number : '',
            $thread->subject,
            $reason ?: __( 'No reason provided.', 'eprocurement' ),
            home_url( "/{$slug}/my-account/?tab=queries" )
        );

        wp_mail( $bidder->user_email, $subject, $body );
    }
}
