<?php
/**
 * Contact Persons management.
 *
 * Handles CRUD for SCM and Technical contacts linked to bids.
 * Contact persons must be linked to a WordPress user account to reply to queries.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Contact_Persons {

    /**
     * Create a new contact person.
     *
     * @param array $data Contact data.
     * @return int|false Insert ID or false.
     */
    public function create( array $data ): int|false {
        return Eprocurement_Database::insert( 'contact_persons', [
            'user_id'    => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
            'type'       => in_array( $data['type'] ?? '', [ 'scm', 'technical' ], true ) ? $data['type'] : 'scm',
            'name'       => sanitize_text_field( $data['name'] ?? '' ),
            'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
            'email'      => sanitize_email( $data['email'] ?? '' ),
            'department' => sanitize_text_field( $data['department'] ?? '' ),
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    /**
     * Update a contact person.
     */
    public function update( int $id, array $data ): int|false {
        $update = [];

        if ( isset( $data['user_id'] ) ) {
            $update['user_id'] = $data['user_id'] ? absint( $data['user_id'] ) : null;
        }
        if ( isset( $data['type'] ) && in_array( $data['type'], [ 'scm', 'technical' ], true ) ) {
            $update['type'] = $data['type'];
        }
        if ( isset( $data['name'] ) ) {
            $update['name'] = sanitize_text_field( $data['name'] );
        }
        if ( isset( $data['phone'] ) ) {
            $update['phone'] = sanitize_text_field( $data['phone'] );
        }
        if ( isset( $data['email'] ) ) {
            $update['email'] = sanitize_email( $data['email'] );
        }
        if ( isset( $data['department'] ) ) {
            $update['department'] = sanitize_text_field( $data['department'] );
        }

        return Eprocurement_Database::update( 'contact_persons', $update, [ 'id' => $id ] );
    }

    /**
     * Get a contact person by ID.
     */
    public function get( int $id ): ?object {
        return Eprocurement_Database::get_by_id( 'contact_persons', $id );
    }

    /**
     * Get all contact persons with optional type filter.
     */
    public function get_all( string $type = '' ): array {
        $where = [];
        if ( $type && in_array( $type, [ 'scm', 'technical' ], true ) ) {
            $where['type'] = $type;
        }

        return Eprocurement_Database::get_rows( 'contact_persons', $where, 'name', 'ASC' );
    }

    /**
     * Get contacts assigned to a specific document.
     */
    public function get_for_document( int $document_id ): array {
        $document = Eprocurement_Database::get_by_id( 'documents', $document_id );
        if ( ! $document ) {
            return [];
        }

        $contacts = [];

        if ( $document->scm_contact_id ) {
            $scm = $this->get( (int) $document->scm_contact_id );
            if ( $scm ) {
                $contacts['scm'] = $scm;
            }
        }

        if ( $document->technical_contact_id ) {
            $tech = $this->get( (int) $document->technical_contact_id );
            if ( $tech ) {
                $contacts['technical'] = $tech;
            }
        }

        return $contacts;
    }

    /**
     * Delete a contact person.
     *
     * @param int $id Contact ID.
     * @return bool True on success.
     */
    public function delete( int $id ): bool {
        // Check if contact is assigned to any active bids
        global $wpdb;

        $table = Eprocurement_Database::table( 'documents' );
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE (scm_contact_id = %d OR technical_contact_id = %d) AND status NOT IN ('closed', 'cancelled', 'archived')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $id,
                $id
            )
        );

        if ( $count > 0 ) {
            return false; // Cannot delete a contact assigned to active bids
        }

        $result = Eprocurement_Database::delete( 'contact_persons', [ 'id' => $id ] );
        return $result !== false;
    }

    /**
     * Check if a contact person is linked to a WordPress user.
     */
    public function is_linked_to_user( int $id ): bool {
        $contact = $this->get( $id );
        return $contact && ! empty( $contact->user_id );
    }

    /**
     * Get the WordPress user associated with a contact.
     */
    public function get_linked_user( int $contact_id ): ?\WP_User {
        $contact = $this->get( $contact_id );
        if ( ! $contact || ! $contact->user_id ) {
            return null;
        }

        return get_userdata( (int) $contact->user_id ) ?: null;
    }
}
