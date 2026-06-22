<?php
/**
 * SCM Documents Library.
 *
 * A static document library for standard SCM documents
 * (e.g., BBBEE forms, tax clearance templates) that apply to all bids.
 * Managed separately from bid-specific bid documents.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Compliance_Docs {

    /**
     * Add an SCM document.
     *
     * @param array $data Document data (file already uploaded to cloud).
     * @return int|false Insert ID or false.
     */
    public function add( array $data ): int|false {
        return Eprocurement_Database::insert( 'compliance_docs', [
            'file_name'      => sanitize_file_name( $data['file_name'] ?? '' ),
            'file_size'      => absint( $data['file_size'] ?? 0 ),
            'file_type'      => sanitize_text_field( $data['file_type'] ?? '' ),
            'cloud_provider' => sanitize_text_field( $data['cloud_provider'] ?? '' ),
            'cloud_key'      => sanitize_text_field( $data['cloud_key'] ?? '' ),
            'cloud_url'      => esc_url_raw( $data['cloud_url'] ?? '' ),
            'label'          => sanitize_text_field( $data['label'] ?? '' ),
            'description'    => sanitize_textarea_field( $data['description'] ?? '' ),
            'sort_order'     => absint( $data['sort_order'] ?? 0 ),
            'uploaded_by'    => get_current_user_id(),
            'created_at'     => current_time( 'mysql' ),
        ] );
    }

    /**
     * Update an SCM document's metadata.
     */
    public function update( int $id, array $data ): int|false {
        $update = [];

        if ( isset( $data['label'] ) ) {
            $update['label'] = sanitize_text_field( $data['label'] );
        }
        if ( isset( $data['description'] ) ) {
            $update['description'] = sanitize_textarea_field( $data['description'] );
        }
        if ( isset( $data['sort_order'] ) ) {
            $update['sort_order'] = absint( $data['sort_order'] );
        }

        if ( empty( $update ) ) {
            return 0;
        }

        return Eprocurement_Database::update( 'compliance_docs', $update, [ 'id' => $id ] );
    }

    /**
     * Get an SCM document by ID.
     */
    public function get( int $id ): ?object {
        return Eprocurement_Database::get_by_id( 'compliance_docs', $id );
    }

    /**
     * Get all SCM documents ordered by sort_order.
     *
     * @return array Array of SCM doc objects.
     */
    public function get_all(): array {
        return Eprocurement_Database::get_rows(
            'compliance_docs',
            [],
            'sort_order',
            'ASC'
        );
    }

    /**
     * Delete an SCM document.
     *
     * @param int $id Document ID.
     * @return bool True on success.
     */
    public function delete( int $id ): bool {
        $doc = $this->get( $id );
        if ( ! $doc ) {
            return false;
        }

        // Attempt to delete from cloud storage
        try {
            $storage = Eprocurement_Storage_Interface::get_active_provider();
            if ( $storage && $doc->cloud_key ) {
                $storage->delete( $doc->cloud_key );
            }
        } catch ( \Exception $e ) {
            // Log error but continue with database deletion
            error_log( 'eProcurement: Failed to delete cloud file: ' . $e->getMessage() );
        }

        $result = Eprocurement_Database::delete( 'compliance_docs', [ 'id' => $id ] );
        return $result !== false;
    }

    /**
     * Reorder SCM documents.
     *
     * @param array $order Array of doc IDs in desired order.
     */
    public function reorder( array $order ): void {
        foreach ( $order as $position => $id ) {
            Eprocurement_Database::update(
                'compliance_docs',
                [ 'sort_order' => (int) $position ],
                [ 'id' => absint( $id ) ]
            );
        }
    }

    /**
     * Get the section title (admin-customisable).
     */
    public static function get_section_title(): string {
        return get_option( 'eprocurement_compliance_section_title', __( 'SCM Documents', 'eprocurement' ) );
    }

    /**
     * Update the section title.
     */
    public static function set_section_title( string $title ): void {
        update_option( 'eprocurement_compliance_section_title', sanitize_text_field( $title ) );
    }

    /**
     * Get total count.
     */
    public function count(): int {
        return Eprocurement_Database::count( 'compliance_docs' );
    }
}
