<?php
/**
 * Bid Evaluation Matrix.
 *
 * Manages per-tender scoring criteria and per-submission scores
 * by evaluators. Provides ranked comparison output.
 *
 * Schema:
 *   eproc_evaluation_criteria  (id, document_id, name, description, weight, max_score, sort_order, ...)
 *   eproc_evaluation_scores    (id, submission_id, criterion_id, evaluator_user_id, score, notes, ...)
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Evaluation {

    /**
     * Get all criteria for a tender, ordered by sort_order.
     *
     * @param int $document_id Tender document ID.
     * @return array Array of criterion objects.
     */
    public function get_criteria( int $document_id ): array {
        global $wpdb;
        $table = Eprocurement_Database::table( 'evaluation_criteria' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE document_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $document_id
            )
        );
    }

    /**
     * Create a new criterion.
     *
     * @param array $data Criterion data.
     * @return int|false Insert ID or false.
     */
    public function add_criterion( array $data ): int|false {
        $sort_order = $this->next_sort_order( (int) $data['document_id'] );

        return Eprocurement_Database::insert( 'evaluation_criteria', [
            'document_id' => absint( $data['document_id'] ),
            'name'        => sanitize_text_field( $data['name'] ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ) ?: null,
            'weight'      => max( 0.01, (float) $data['weight'] ),
            'max_score'   => min( 100, max( 1, absint( $data['max_score'] ?? 10 ) ) ),
            'sort_order'  => $sort_order,
            'created_by'  => get_current_user_id(),
            'created_at'  => current_time( 'mysql' ),
        ] );
    }

    /**
     * Update an existing criterion.
     */
    public function update_criterion( int $id, array $data ): int|false {
        $update = [];

        if ( isset( $data['name'] ) )        $update['name']        = sanitize_text_field( $data['name'] );
        if ( isset( $data['description'] ) ) $update['description'] = sanitize_textarea_field( $data['description'] ) ?: null;
        if ( isset( $data['weight'] ) )      $update['weight']      = max( 0.01, (float) $data['weight'] );
        if ( isset( $data['max_score'] ) )   $update['max_score']   = min( 100, max( 1, absint( $data['max_score'] ) ) );

        if ( empty( $update ) ) {
            return 0;
        }

        return Eprocurement_Database::update( 'evaluation_criteria', $update, [ 'id' => $id ] );
    }

    /**
     * Delete a criterion (and cascade-delete its scores).
     */
    public function delete_criterion( int $id ): bool {
        global $wpdb;
        $scores_table = Eprocurement_Database::table( 'evaluation_scores' );

        // Delete scores for this criterion.
        $wpdb->delete( $scores_table, [ 'criterion_id' => $id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        $result = Eprocurement_Database::delete( 'evaluation_criteria', [ 'id' => $id ] );
        return $result !== false;
    }

    /**
     * Reorder criteria based on an array of IDs in the desired order.
     */
    public function reorder_criteria( array $ordered_ids ): bool {
        global $wpdb;
        $table = Eprocurement_Database::table( 'evaluation_criteria' );

        foreach ( $ordered_ids as $index => $id ) {
            $wpdb->update(
                $table,
                [ 'sort_order' => $index ],
                [ 'id' => absint( $id ) ],
                [ '%d' ],
                [ '%d' ]
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
        return true;
    }

    /**
     * Get the next sort_order for a tender's criteria.
     */
    private function next_sort_order( int $document_id ): int {
        global $wpdb;
        $table = Eprocurement_Database::table( 'evaluation_criteria' );
        $max = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$table} WHERE document_id = %d", // phpcs:ignore
                $document_id
            )
        );
        return $max;
    }

    /**
     * Get all scores for a submission (from all evaluators).
     *
     * @param int $submission_id Submission ID.
     * @return array Array of score objects.
     */
    public function get_scores_for_submission( int $submission_id ): array {
        global $wpdb;
        $table = Eprocurement_Database::table( 'evaluation_scores' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE submission_id = %d", // phpcs:ignore
                $submission_id
            )
        );
    }

    /**
     * Get a single score (or null) for a submission × criterion × evaluator.
     */
    public function get_score( int $submission_id, int $criterion_id, int $evaluator_user_id ): ?object {
        global $wpdb;
        $table = Eprocurement_Database::table( 'evaluation_scores' );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE submission_id = %d AND criterion_id = %d AND evaluator_user_id = %d", // phpcs:ignore
                $submission_id, $criterion_id, $evaluator_user_id
            )
        );
        return $row ?: null;
    }

    /**
     * Set (insert or update) a score for a submission × criterion × evaluator.
     *
     * @param int    $submission_id Submission ID.
     * @param int    $criterion_id  Criterion ID.
     * @param float  $score         Score value (0 to max_score).
     * @param string $notes         Optional notes.
     * @return int|false Score row ID or false.
     */
    public function set_score( int $submission_id, int $criterion_id, float $score, string $notes = '' ): int|false {
        global $wpdb;
        $table   = Eprocurement_Database::table( 'evaluation_scores' );
        $user_id = get_current_user_id();

        // Validate score against criterion max.
        $criterion = Eprocurement_Database::get_by_id( 'evaluation_criteria', $criterion_id );
        if ( ! $criterion ) {
            return false;
        }
        $max_score = (float) $criterion->max_score;
        $score = max( 0, min( $max_score, $score ) );

        $existing = $this->get_score( $submission_id, $criterion_id, $user_id );

        if ( $existing ) {
            Eprocurement_Database::update(
                'evaluation_scores',
                [
                    'score' => $score,
                    'notes' => sanitize_textarea_field( $notes ) ?: null,
                ],
                [ 'id' => $existing->id ]
            );
            return (int) $existing->id;
        }

        return Eprocurement_Database::insert( 'evaluation_scores', [
            'submission_id'      => $submission_id,
            'criterion_id'       => $criterion_id,
            'evaluator_user_id'  => $user_id,
            'score'              => $score,
            'notes'              => sanitize_textarea_field( $notes ) ?: null,
            'created_at'         => current_time( 'mysql' ),
        ] );
    }

    /**
     * Compute the weighted total score for a single submission.
     *
     * Formula: SUM(score / max_score * weight) / SUM(weight) * 100
     * Returns a 0-100 normalised score so different tender criteria sets
     * can be compared. Returns null if no scores are recorded.
     *
     * @param int $submission_id Submission ID.
     * @return array{total: float, raw: float, max_raw: float, count: int, by_criterion: array}
     */
    public function compute_submission_score( int $submission_id ): array {
        global $wpdb;

        $scores_table  = Eprocurement_Database::table( 'evaluation_scores' );
        $criteria_table = Eprocurement_Database::table( 'evaluation_criteria' );

        // Join scores with criteria to compute weighted totals.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.criterion_id, c.weight, c.max_score, AVG(s.score) as avg_score
                 FROM {$scores_table} s
                 INNER JOIN {$criteria_table} c ON s.criterion_id = c.id
                 WHERE s.submission_id = %d
                 GROUP BY s.criterion_id, c.weight, c.max_score", // phpcs:ignore
                $submission_id
            )
        );

        if ( empty( $rows ) ) {
            return [
                'total'      => 0.0,
                'raw'        => 0.0,
                'max_raw'    => 0.0,
                'count'      => 0,
                'by_criterion' => [],
            ];
        }

        $sum_weighted   = 0.0;
        $sum_weights    = 0.0;
        $sum_raw        = 0.0;
        $sum_max_raw    = 0.0;
        $by_criterion   = [];

        foreach ( $rows as $row ) {
            $weight   = (float) $row->weight;
            $max      = max( 1, (float) $row->max_score );
            $avg      = (float) $row->avg_score;

            $normalised = ( $avg / $max ) * 100;  // 0-100 per criterion
            $sum_weighted += $normalised * $weight;
            $sum_weights  += $weight;
            $sum_raw      += $avg;
            $sum_max_raw  += $max;

            $by_criterion[ (int) $row->criterion_id ] = [
                'avg_score' => $avg,
                'max_score' => $max,
                'weight'    => $weight,
                'normalised' => $normalised,
            ];
        }

        $total = $sum_weights > 0 ? ( $sum_weighted / $sum_weights ) : 0.0;

        return [
            'total'        => round( $total, 2 ),
            'raw'          => round( $sum_raw, 2 ),
            'max_raw'      => round( $sum_max_raw, 2 ),
            'count'        => count( $rows ),
            'by_criterion' => $by_criterion,
        ];
    }

    /**
     * Get the ranked comparison of all submissions for a tender.
     *
     * @param int $document_id Tender document ID.
     * @return array Array of {submission, bidder, score_total, rank} sorted by score desc.
     */
    public function get_ranked_comparison( int $document_id ): array {
        $submissions_model = new Eprocurement_Bid_Submissions();
        $submissions = $submissions_model->get_submissions_for_document( $document_id );

        $ranked = [];
        foreach ( $submissions as $sub ) {
            $score_data = $this->compute_submission_score( (int) $sub->id );
            $bidder = get_userdata( (int) $sub->user_id );

            $profile = ( new Eprocurement_Bidder() )->get_profile( (int) $sub->user_id );

            $ranked[] = [
                'submission'    => $sub,
                'bidder_name'   => $bidder ? $bidder->display_name : 'Unknown',
                'company_name'  => $profile ? $profile->company_name : '',
                'bidder_email'  => $bidder ? $bidder->user_email : '',
                'score_total'   => $score_data['total'],
                'score_raw'     => $score_data['raw'],
                'score_max_raw' => $score_data['max_raw'],
                'criteria_scored' => $score_data['count'],
                'is_late'       => (bool) $sub->is_late,
                'submitted_at'  => $sub->submitted_at,
            ];
        }

        // Sort by score_total desc.
        usort( $ranked, function ( $a, $b ) {
            return $b['score_total'] <=> $a['score_total'];
        } );

        // Assign ranks (1-indexed).
        foreach ( $ranked as $index => &$row ) {
            $row['rank'] = $index + 1;
        }

        return $ranked;
    }

    /**
     * Check whether the current user has scored any submissions for a tender.
     */
    public function has_user_scored( int $document_id, int $user_id ): bool {
        global $wpdb;
        $scores_table  = Eprocurement_Database::table( 'evaluation_scores' );
        $criteria_table = Eprocurement_Database::table( 'evaluation_criteria' );

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$scores_table} s
                 INNER JOIN {$criteria_table} c ON s.criterion_id = c.id
                 WHERE c.document_id = %d AND s.evaluator_user_id = %d", // phpcs:ignore
                $document_id, $user_id
            )
        );
        return $count > 0;
    }
}
