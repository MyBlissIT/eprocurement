<?php
/**
 * Submission Requirements.
 *
 * Manages per-tender required document fields for per-document
 * submission mode. SCM defines which documents bidders must upload
 * (e.g. Tax Certificate, BBBEE Certificate, ID Copy, etc.) and
 * whether each is required or optional.
 *
 * @package Eprocurement
 * @since   2.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Submission_Requirements {

    /**
     * Preset document types that SCM can quickly add.
     */
    public const PRESETS = [
        'tax_certificate'    => 'Tax Clearance Certificate',
        'bbbee_certificate'  => 'BBBEE Certificate',
        'id_copy'            => 'ID Copy (Director/Representative)',
        'proof_of_address'   => 'Proof of Address',
        'csd_report'         => 'CSD Report',
        'bank_confirmation'  => 'Bank Confirmation Letter',
        'company_registration'=> 'Company Registration Document',
        'quotation'          => 'Quotation',
        'proposal'           => 'Technical Proposal',
        'tender_document'    => 'Completed Tender Document',
    ];

    /**
     * Get all requirements for a tender, ordered by sort_order.
     */
    public function get_requirements( int $document_id ): array {
        global $wpdb;
        $table = Eprocurement_Database::table( 'submission_requirements' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE document_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore
                $document_id
            )
        );
    }

    /**
     * Add a requirement.
     */
    public function add_requirement( array $data ): int|false {
        $sort_order = $this->next_sort_order( (int) $data['document_id'] );

        return Eprocurement_Database::insert( 'submission_requirements', [
            'document_id'         => absint( $data['document_id'] ),
            'field_key'           => sanitize_key( $data['field_key'] ?? uniqid( 'req_' ) ),
            'field_label'         => sanitize_text_field( $data['field_label'] ),
            'description'         => sanitize_textarea_field( $data['description'] ?? '' ) ?: null,
            'is_required'         => absint( $data['is_required'] ?? 1 ) ? 1 : 0,
            'sort_order'          => $sort_order,
            'accepted_extensions' => sanitize_text_field( $data['accepted_extensions'] ?? '' ) ?: null,
            'max_file_size'       => absint( $data['max_file_size'] ?? 0 ) ?: null,
            'created_at'          => current_time( 'mysql' ),
        ] );
    }

    /**
     * Delete a requirement.
     */
    public function delete_requirement( int $id ): bool {
        $result = Eprocurement_Database::delete( 'submission_requirements', [ 'id' => $id ] );
        return $result !== false;
    }

    /**
     * Get the next sort_order.
     */
    private function next_sort_order( int $document_id ): int {
        global $wpdb;
        $table = Eprocurement_Database::table( 'submission_requirements' );
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$table} WHERE document_id = %d", // phpcs:ignore
                $document_id
            )
        );
    }

    /**
     * Validate that a submission has all required documents uploaded.
     *
     * @param int   $document_id  Tender ID.
     * @param array $uploaded_keys Array of field_key values that were uploaded.
     * @return array{valid: bool, missing: array} Validation result.
     */
    public function validate_submission( int $document_id, array $uploaded_keys ): array {
        $requirements = $this->get_requirements( $document_id );
        $missing = [];

        foreach ( $requirements as $req ) {
            if ( (int) $req->is_required === 1 && ! in_array( $req->field_key, $uploaded_keys, true ) ) {
                $missing[] = $req->field_label;
            }
        }

        return [
            'valid'   => empty( $missing ),
            'missing' => $missing,
        ];
    }
}
