<?php
/**
 * Frontend Admin — Add/Edit bid document partial.
 *
 * Two-column layout: Left = Bid Information form + Bid Documents,
 * Right = Status/Contacts/Dates.
 *
 * Adapted from admin/partials/bid-edit.php for the frontend manage panel.
 * Uses eprocAjax() / eprocAPI for data operations instead of jQuery $.post.
 * All links use home_url() with the manage base path.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$documents       = new Eprocurement_Documents();
$contacts        = new Eprocurement_Contact_Persons();
$bid_submissions = new Eprocurement_Bid_Submissions();

// URL bases
$slug         = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base  = home_url( "/{$slug}/manage" );

$bid_id         = absint( $_GET['id'] ?? 0 );
$bid            = $bid_id ? $documents->get( $bid_id ) : null;
$is_edit        = (bool) $bid;
$eproc_category = $eproc_category ?? ( $bid ? ( $bid->category ?? 'bid' ) : 'bid' );
$is_regular_bid = ( $eproc_category === 'bid' );

$category_labels = [
    'bid'               => __( 'Bid', 'eprocurement' ),
    'briefing_register' => __( 'Briefing Register', 'eprocurement' ),
    'closing_register'  => __( 'Closing Register', 'eprocurement' ),
    'appointments'      => __( 'Appointment', 'eprocurement' ),
];

// Status transitions
$status_transitions = [
    'draft'     => [ 'open', 'cancelled' ],
    'open'      => [ 'closed', 'cancelled' ],
    'closed'    => [ 'archived' ],
    'cancelled' => [],
    'archived'  => [],
];

$current_status    = $bid ? $bid->status : 'draft';
$allowed_next      = $status_transitions[ $current_status ] ?? [];
$supporting_docs   = $bid ? $documents->get_supporting_docs( $bid_id ) : [];
$scm_contacts      = $contacts->get_all( 'scm' );
$tech_contacts     = $contacts->get_all( 'technical' );

// Build back URL for the list page (preserving category context)
$back_url = $manage_base . '/bids/';
if ( $eproc_category !== 'bid' ) {
    $back_url = add_query_arg( 'category', $eproc_category, $back_url );
}

// Build edit page base URL for redirect after creation
$edit_page_base = $manage_base . '/bids/?action=edit&id=';
if ( $eproc_category !== 'bid' ) {
    $edit_page_base = $manage_base . '/bids/?action=edit&category=' . urlencode( $eproc_category ) . '&id=';
}

$page_title = $is_edit
    ? sprintf(
        /* translators: %s: bid number */
        __( 'Edit Bid: %s', 'eprocurement' ),
        $bid->bid_number
    )
    : __( 'Add New Bid', 'eprocurement' );

// Build breadcrumbs (manage/frontend version).
$breadcrumb_items = [
    [
        'label' => __( 'Dashboard', 'eprocurement' ),
        'url'   => $manage_base . '/dashboard/',
    ],
    [
        'label' => $is_regular_bid ? __( 'All Bids', 'eprocurement' ) : ( $category_labels[ $eproc_category ] ?? __( 'Bids', 'eprocurement' ) ),
        'url'   => $back_url,
    ],
];

if ( $is_edit ) {
    $breadcrumb_items[] = [
        'label' => $bid->bid_number,
        'url'   => '',
    ];
} else {
    $breadcrumb_items[] = [
        'label' => __( 'Add New', 'eprocurement' ),
        'url'   => '',
    ];
}
?>
<div class="eproc-wrap">
    <?php echo eprocurement_breadcrumbs( $breadcrumb_items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div class="eproc-page-header">
        <h1>
            <?php echo esc_html( $page_title ); ?>
            <?php if ( $is_edit ) : ?>
                <span class="eproc-status-badge eproc-status-<?php echo esc_attr( $current_status ); ?>">
                    <?php echo esc_html( ucfirst( $current_status ) ); ?>
                </span>
            <?php endif; ?>
        </h1>
    </div>

    <div id="eproc-bid-notices"></div>

    <form id="eproc-bid-form" method="post" class="eproc-bid-layout">
        <input type="hidden" name="id" value="<?php echo esc_attr( $bid_id ); ?>">
        <input type="hidden" name="category" value="<?php echo esc_attr( $eproc_category ); ?>">

        <!-- Left Column: Bid Details -->
        <div class="eproc-bid-main">
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Bid Details', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <div class="eproc-form-group">
                        <label for="bid_number"><?php esc_html_e( 'Bid Number', 'eprocurement' ); ?> <span class="required">*</span></label>
                        <input type="text" id="bid_number" name="bid_number" required
                               value="<?php echo esc_attr( $bid ? $bid->bid_number : '' ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. BID/2026/001', 'eprocurement' ); ?>"
                               class="eproc-input">
                    </div>
                    <div class="eproc-form-group">
                        <label for="title"><?php esc_html_e( 'Title', 'eprocurement' ); ?> <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required
                               value="<?php echo esc_attr( $bid ? $bid->title : '' ); ?>"
                               placeholder="<?php esc_attr_e( 'Bid title', 'eprocurement' ); ?>"
                               class="eproc-input">
                    </div>
                    <div class="eproc-form-group">
                        <label for="description"><?php esc_html_e( 'Description', 'eprocurement' ); ?></label>
                        <textarea id="description" name="description" rows="12" class="eproc-input" placeholder="<?php esc_attr_e( 'Enter bid description...', 'eprocurement' ); ?>"><?php echo esc_textarea( $bid ? $bid->description : '' ); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Bid Documents -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Bid Documents', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <!-- Upload Area -->
                    <div id="eproc-upload-area" class="eproc-upload-zone">
                        <div class="eproc-upload-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.5;">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                        </div>
                        <p class="eproc-upload-text">
                            <?php esc_html_e( 'Drag and drop files here, or click to select files', 'eprocurement' ); ?>
                        </p>
                        <p class="eproc-upload-hint">
                            <?php esc_html_e( 'Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP (max 50MB)', 'eprocurement' ); ?>
                        </p>
                        <input type="file" id="eproc-file-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" style="display:none;">
                    </div>

                    <!-- Upload Progress -->
                    <div id="eproc-upload-progress" class="eproc-upload-progress" style="display:none;">
                        <div class="eproc-progress-track">
                            <div id="eproc-progress-bar" class="eproc-progress-fill" style="width:0%;"></div>
                        </div>
                        <p id="eproc-upload-status" class="eproc-upload-status-text"></p>
                    </div>

                    <!-- Pending files queue (new bids only) -->
                    <input type="hidden" id="eproc-pending-doc-ids" name="pending_doc_ids" value="">

                    <!-- File List -->
                    <table class="eproc-table" id="eproc-supporting-docs-table" <?php echo empty( $supporting_docs ) ? 'style="display:none;"' : ''; ?>>
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'File Name', 'eprocurement' ); ?></th>
                                <th scope="col" class="eproc-col-narrow"><?php esc_html_e( 'Size', 'eprocurement' ); ?></th>
                                <th scope="col" class="eproc-col-narrow"><?php esc_html_e( 'Uploaded', 'eprocurement' ); ?></th>
                                <th scope="col" class="eproc-col-action"><?php esc_html_e( 'Remove', 'eprocurement' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="eproc-supporting-docs-list">
                            <?php foreach ( $supporting_docs as $doc ) : ?>
                                <tr data-id="<?php echo esc_attr( $doc->id ); ?>">
                                    <td>
                                        <?php echo esc_html( $doc->file_name ); ?>
                                        <?php if ( ! empty( $doc->label ) ) : ?>
                                            <span class="eproc-text-muted"> &mdash; <?php echo esc_html( $doc->label ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( size_format( $doc->file_size ) ); ?></td>
                                    <td><?php echo esc_html( wp_date( 'j M Y', strtotime( $doc->created_at ) ) ); ?></td>
                                    <td>
                                        <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-danger eproc-remove-doc" data-id="<?php echo esc_attr( $doc->id ); ?>" title="<?php esc_attr_e( 'Remove', 'eprocurement' ); ?>">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ( $is_edit && $is_regular_bid ) : ?>
            <!-- Bid Submissions (hidden when Accept Online Submissions is off) -->
            <div class="eproc-card" id="eproc-submissions-card" style="<?php echo ( $bid && ! empty( $bid->accept_online_submissions ) ) ? '' : 'display:none;'; ?>">
                <div class="eproc-card-header">
                    <h2>
                        <?php esc_html_e( 'Bid Submissions', 'eprocurement' ); ?>
                        <span class="eproc-tab-count" id="eproc-submission-count">0</span>
                    </h2>
                    <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-outline" id="eproc-download-submissions-zip" style="display:none;" title="<?php esc_attr_e( 'Download All as ZIP', 'eprocurement' ); ?>">
                        <?php esc_html_e( 'Download ZIP', 'eprocurement' ); ?>
                    </button>
                </div>
                <div class="eproc-card-body">
                    <div id="eproc-submissions-loading" class="eproc-text-center">
                        <p class="eproc-muted"><?php esc_html_e( 'Loading submissions...', 'eprocurement' ); ?></p>
                    </div>
                    <div id="eproc-submissions-empty" style="display:none;">
                        <p class="eproc-muted"><?php esc_html_e( 'No bid submissions have been received yet.', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-table-responsive" id="eproc-submissions-table-wrap" style="display:none;">
                        <table class="eproc-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Company', 'eprocurement' ); ?></th>
                                    <th><?php esc_html_e( 'File', 'eprocurement' ); ?></th>
                                    <th><?php esc_html_e( 'Submitted', 'eprocurement' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'eprocurement' ); ?></th>
                                    <th class="eproc-col-action"><?php esc_html_e( 'Download', 'eprocurement' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="eproc-submissions-list"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // ─── Evaluation Card ───────────────────────────────────────────
            // Shown only when the bid is closed (evaluation happens post-closing).
            // Includes: criteria management, scoring inputs, comparison view,
            // and award form. All driven by REST endpoints.
            if ( $is_edit && $current_status === 'closed' ) :
                $evaluation_model = new Eprocurement_Evaluation();
                $criteria = $evaluation_model->get_criteria( $bid_id );
                $award    = $documents->get_award( $bid_id );
            ?>
            <!-- Evaluation Criteria Card -->
            <div class="eproc-card eproc-evaluation-card" id="eproc-evaluation-card">
                <div class="eproc-card-header">
                    <h2>
                        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18" style="vertical-align:-3px;margin-right:4px;color:var(--eproc-primary);"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e( 'Evaluation Matrix', 'eprocurement' ); ?>
                    </h2>
                    <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-outline" id="eproc-comparison-btn">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="margin-right:4px;vertical-align:-2px;"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-3.5L5 18V4z"/></svg>
                        <?php esc_html_e( 'Compare & Award', 'eprocurement' ); ?>
                    </button>
                </div>
                <div class="eproc-card-body">
                    <?php if ( $award ) : ?>
                        <div class="eproc-award-banner">
                            <div class="eproc-award-banner-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zm7-10a1 1 0 01.945.671L14.118 6H17a1 1 0 110 2h-.018l-.382 1.428a1 1 0 01-1.94-.514L14.732 8h-2.99l-.276.829a1 1 0 11-1.94-.514l2-6A1 1 0 0112 2zm1.382 6l-.667-2-.667 2h1.334zM9 14a1 1 0 011-1h6a1 1 0 110 2h-6a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                            </div>
                            <div class="eproc-award-banner-body">
                                <strong><?php esc_html_e( 'Awarded to:', 'eprocurement' ); ?></strong>
                                <?php echo esc_html( $award->company_name ?: $award->display_name ); ?>
                                <?php if ( $award->award_amount ) : ?>
                                    · <?php echo esc_html( number_format_i18n( $award->award_amount, 2 ) ); ?>
                                <?php endif; ?>
                                <span class="eproc-award-banner-date"><?php echo esc_html( wp_date( 'j M Y', strtotime( $award->award_date ) ) ); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="eproc-form-hint" style="margin-bottom:16px;">
                        <?php esc_html_e( 'Define scoring criteria with weights. Evaluators score each submission independently; the system computes weighted totals automatically.', 'eprocurement' ); ?>
                    </p>

                    <!-- Criteria list -->
                    <div id="eproc-criteria-list">
                        <?php if ( empty( $criteria ) ) : ?>
                            <div class="eproc-empty-state" style="padding:32px 16px;">
                                <p class="eproc-empty-state-text"><?php esc_html_e( 'No evaluation criteria defined yet. Add criteria below to start scoring submissions.', 'eprocurement' ); ?></p>
                            </div>
                        <?php else : ?>
                            <table class="eproc-table eproc-criteria-table">
                                <thead>
                                    <tr>
                                        <th style="width:30px;">#</th>
                                        <th><?php esc_html_e( 'Criterion', 'eprocurement' ); ?></th>
                                        <th style="width:80px;"><?php esc_html_e( 'Weight', 'eprocurement' ); ?></th>
                                        <th style="width:80px;"><?php esc_html_e( 'Max', 'eprocurement' ); ?></th>
                                        <th style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $criteria as $i => $c ) : ?>
                                        <tr data-criterion-id="<?php echo esc_attr( $c->id ); ?>">
                                            <td class="eproc-text-muted"><?php echo esc_html( $i + 1 ); ?></td>
                                            <td>
                                                <strong><?php echo esc_html( $c->name ); ?></strong>
                                                <?php if ( $c->description ) : ?>
                                                    <br><span class="eproc-text-muted" style="font-size:12px;"><?php echo esc_html( $c->description ); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="eproc-weight-pill"><?php echo esc_html( number_format_i18n( (float) $c->weight, 1 ) ); ?></span></td>
                                            <td><?php echo esc_html( $c->max_score ); ?></td>
                                            <td>
                                                <button type="button" class="eproc-btn-icon eproc-delete-criterion" data-criterion-id="<?php echo esc_attr( $c->id ); ?>" title="<?php esc_attr_e( 'Delete criterion', 'eprocurement' ); ?>" aria-label="<?php esc_attr_e( 'Delete criterion', 'eprocurement' ); ?>">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Add new criterion form -->
                    <div class="eproc-criteria-add" id="eproc-criteria-add">
                        <div class="eproc-form-row eproc-form-row--2col">
                            <input type="text" id="eproc-new-criterion-name" class="eproc-input" placeholder="<?php esc_attr_e( 'Criterion name (e.g. Technical Approach)', 'eprocurement' ); ?>" />
                            <input type="text" id="eproc-new-criterion-desc" class="eproc-input" placeholder="<?php esc_attr_e( 'Description (optional)', 'eprocurement' ); ?>" />
                        </div>
                        <div class="eproc-form-row eproc-form-row--2col">
                            <div class="eproc-form-group" style="margin-bottom:0;">
                                <label class="eproc-form-label" for="eproc-new-criterion-weight"><?php esc_html_e( 'Weight', 'eprocurement' ); ?></label>
                                <input type="number" id="eproc-new-criterion-weight" class="eproc-input" value="1" min="0.1" step="0.1" style="width:100px;" />
                            </div>
                            <div class="eproc-form-group" style="margin-bottom:0;">
                                <label class="eproc-form-label" for="eproc-new-criterion-max"><?php esc_html_e( 'Max Score', 'eprocurement' ); ?></label>
                                <input type="number" id="eproc-new-criterion-max" class="eproc-input" value="10" min="1" max="100" style="width:100px;" />
                            </div>
                        </div>
                        <button type="button" class="eproc-btn eproc-btn-primary eproc-btn-sm" id="eproc-add-criterion-btn">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="margin-right:4px;vertical-align:-2px;"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                            <?php esc_html_e( 'Add Criterion', 'eprocurement' ); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; // end evaluation card ?>
        </div>

        <!-- ════════════════════════════════════════════════════════════ -->
        <!-- Comparison & Award Modal                                       -->
        <!-- ════════════════════════════════════════════════════════════ -->
        <?php if ( $is_edit && $current_status === 'closed' ) : ?>
        <div class="eproc-modal eproc-modal-lg" id="eproc-comparison-modal" style="display:none;" aria-hidden="true">
            <div class="eproc-modal-backdrop" data-close-modal="eproc-comparison-modal"></div>
            <div class="eproc-modal-content">
                <div class="eproc-modal-header">
                    <h2>
                        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18" style="vertical-align:-3px;margin-right:4px;"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-3.5L5 18V4z"/></svg>
                        <?php esc_html_e( 'Ranked Comparison & Award', 'eprocurement' ); ?>
                    </h2>
                    <button type="button" class="eproc-modal-close" data-close-modal="eproc-comparison-modal" aria-label="<?php esc_attr_e( 'Close', 'eprocurement' ); ?>">&times;</button>
                </div>
                <div class="eproc-modal-body" id="eproc-comparison-body">
                    <div class="eproc-text-center" style="padding:40px 0;">
                        <div class="eproc-skeleton eproc-skeleton-card" style="width:100%;max-width:500px;margin:0 auto 12px;"></div>
                        <div class="eproc-skeleton eproc-skeleton-line" style="width:80%;max-width:400px;margin:0 auto;"></div>
                        <p class="eproc-muted" style="margin-top:16px;"><?php esc_html_e( 'Loading comparison...', 'eprocurement' ); ?></p>
                    </div>
                </div>
                <div class="eproc-modal-footer">
                    <button type="button" class="eproc-btn eproc-btn-outline" data-close-modal="eproc-comparison-modal"><?php esc_html_e( 'Close', 'eprocurement' ); ?></button>
                </div>
            </div>
        </div>

        <!-- Award Form Modal -->
        <div class="eproc-modal" id="eproc-award-modal" style="display:none;" aria-hidden="true">
            <div class="eproc-modal-backdrop" data-close-modal="eproc-award-modal"></div>
            <div class="eproc-modal-content">
                <div class="eproc-modal-header">
                    <h2><?php esc_html_e( 'Award Tender', 'eprocurement' ); ?></h2>
                    <button type="button" class="eproc-modal-close" data-close-modal="eproc-award-modal" aria-label="<?php esc_attr_e( 'Close', 'eprocurement' ); ?>">&times;</button>
                </div>
                <div class="eproc-modal-body">
                    <div class="eproc-notice warning" style="margin-bottom:16px;">
                        <p><strong><?php esc_html_e( 'You are about to award this tender.', 'eprocurement' ); ?></strong> <?php esc_html_e( 'An email notification will be sent to the winning bidder and all other bidders who submitted.', 'eprocurement' ); ?></p>
                    </div>
                    <form id="eproc-award-form">
                        <input type="hidden" id="eproc-award-bid-id" value="<?php echo esc_attr( $bid_id ); ?>" />
                        <div class="eproc-form-group">
                            <label class="eproc-form-label" for="eproc-award-winner"><?php esc_html_e( 'Award to', 'eprocurement' ); ?> <span class="eproc-required">*</span></label>
                            <select id="eproc-award-winner" class="eproc-input eproc-select" required>
                                <option value=""><?php esc_html_e( 'Select a bidder...', 'eprocurement' ); ?></option>
                            </select>
                        </div>
                        <div class="eproc-form-group">
                            <label class="eproc-form-label" for="eproc-award-amount"><?php esc_html_e( 'Contract Value', 'eprocurement' ); ?></label>
                            <input type="number" id="eproc-award-amount" class="eproc-input" min="0" step="0.01" placeholder="<?php esc_attr_e( 'e.g. 1250000.00', 'eprocurement' ); ?>" />
                            <span class="eproc-form-hint"><?php esc_html_e( 'Optional. Leave blank if not disclosed.', 'eprocurement' ); ?></span>
                        </div>
                        <div class="eproc-form-group">
                            <label class="eproc-form-label" for="eproc-award-notes"><?php esc_html_e( 'Award Notes', 'eprocurement' ); ?></label>
                            <textarea id="eproc-award-notes" class="eproc-input" rows="3" placeholder="<?php esc_attr_e( 'Public notes about the award (shown to the winner).', 'eprocurement' ); ?>"></textarea>
                        </div>
                    </form>
                </div>
                <div class="eproc-modal-footer">
                    <button type="button" class="eproc-btn eproc-btn-outline" data-close-modal="eproc-award-modal"><?php esc_html_e( 'Cancel', 'eprocurement' ); ?></button>
                    <button type="button" class="eproc-btn eproc-btn-primary" id="eproc-confirm-award">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="margin-right:4px;vertical-align:-2px;"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zm7-10a1 1 0 01.945.671L14.118 6H17a1 1 0 110 2h-.018l-.382 1.428a1 1 0 01-1.94-.514L14.732 8h-2.99l-.276.829a1 1 0 11-1.94-.514l2-6A1 1 0 0112 2zm1.382 6l-.667-2-.667 2h1.334zM9 14a1 1 0 011-1h6a1 1 0 110 2h-6a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e( 'Confirm Award', 'eprocurement' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; // end award modal ?>


        <!-- Right Column: Status, Contacts, Dates -->
        <div class="eproc-bid-sidebar">

            <!-- Status & Actions -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Status & Actions', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <?php if ( $is_edit && ! empty( $allowed_next ) && current_user_can( 'eproc_publish_bids' ) ) : ?>
                        <div class="eproc-form-group">
                            <label for="eproc-change-status"><?php esc_html_e( 'Change Status', 'eprocurement' ); ?></label>
                            <div class="eproc-input-group">
                                <select id="eproc-change-status" class="eproc-select">
                                    <option value=""><?php esc_html_e( '-- Select --', 'eprocurement' ); ?></option>
                                    <?php foreach ( $allowed_next as $next_status ) : ?>
                                        <option value="<?php echo esc_attr( $next_status ); ?>">
                                            <?php echo esc_html( ucfirst( $next_status ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="eproc-apply-status" class="eproc-btn"><?php esc_html_e( 'Apply', 'eprocurement' ); ?></button>
                            </div>
                        </div>
                        <hr class="eproc-divider">
                    <?php endif; ?>

                    <button type="submit" class="eproc-btn eproc-btn-success eproc-btn-block" id="eproc-save-bid">
                        <?php echo $is_edit ? esc_html__( 'Update Bid', 'eprocurement' ) : esc_html__( 'Save Draft', 'eprocurement' ); ?>
                    </button>

                    <?php if ( $current_status === 'draft' && $is_edit && current_user_can( 'eproc_publish_bids' ) ) : ?>
                        <button type="button" class="eproc-btn eproc-btn-primary eproc-btn-block eproc-mt-sm" id="eproc-open-bid">
                            <?php esc_html_e( 'Open Bid', 'eprocurement' ); ?>
                        </button>
                    <?php endif; ?>

                    <?php if ( $is_edit && current_user_can( 'eproc_delete_bids' ) ) : ?>
                        <button type="button" class="eproc-btn eproc-btn-danger eproc-btn-block eproc-mt-sm" id="eproc-delete-bid-btn" data-id="<?php echo esc_attr( $bid_id ); ?>">
                            <?php esc_html_e( 'Delete', 'eprocurement' ); ?>
                        </button>
                    <?php endif; ?>

                    <?php // Created/Updated system timestamps hidden — only user-set Key Dates are shown ?>
                </div>
            </div>

            <?php if ( $is_regular_bid ) : ?>
            <!-- Contact Persons -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Contact Persons', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <div class="eproc-form-group">
                        <label for="scm_contact_id"><?php esc_html_e( 'SCM Contact', 'eprocurement' ); ?></label>
                        <select id="scm_contact_id" name="scm_contact_id" class="eproc-select">
                            <option value=""><?php esc_html_e( '-- None --', 'eprocurement' ); ?></option>
                            <?php foreach ( $scm_contacts as $c ) : ?>
                                <option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $bid ? (int) $bid->scm_contact_id : 0, (int) $c->id ); ?>>
                                    <?php echo esc_html( $c->name ); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php
                            // Show currently assigned contact even if it's not in the SCM list
                            if ( $bid && $bid->scm_contact_id ) {
                                $found = false;
                                foreach ( $scm_contacts as $c ) {
                                    if ( (int) $c->id === (int) $bid->scm_contact_id ) {
                                        $found = true;
                                        break;
                                    }
                                }
                                if ( ! $found ) {
                                    $assigned = $contacts->get( (int) $bid->scm_contact_id );
                                    if ( $assigned ) {
                                        printf(
                                            '<option value="%s" selected>%s</option>',
                                            esc_attr( $assigned->id ),
                                            esc_html( $assigned->name . ' (' . $assigned->type . ')' )
                                        );
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="eproc-form-group">
                        <label for="technical_contact_id"><?php esc_html_e( 'Technical Contact', 'eprocurement' ); ?></label>
                        <select id="technical_contact_id" name="technical_contact_id" class="eproc-select">
                            <option value=""><?php esc_html_e( '-- None --', 'eprocurement' ); ?></option>
                            <?php foreach ( $tech_contacts as $c ) : ?>
                                <option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $bid ? (int) $bid->technical_contact_id : 0, (int) $c->id ); ?>>
                                    <?php echo esc_html( $c->name ); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php
                            // Show currently assigned contact even if it's not in the Technical list
                            if ( $bid && $bid->technical_contact_id ) {
                                $found = false;
                                foreach ( $tech_contacts as $c ) {
                                    if ( (int) $c->id === (int) $bid->technical_contact_id ) {
                                        $found = true;
                                        break;
                                    }
                                }
                                if ( ! $found ) {
                                    $assigned = $contacts->get( (int) $bid->technical_contact_id );
                                    if ( $assigned ) {
                                        printf(
                                            '<option value="%s" selected>%s</option>',
                                            esc_attr( $assigned->id ),
                                            esc_html( $assigned->name . ' (' . $assigned->type . ')' )
                                        );
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Key Dates -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Key Dates', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <div class="eproc-form-group">
                        <label for="opening_date"><?php esc_html_e( 'Opening Date', 'eprocurement' ); ?></label>
                        <input type="datetime-local" id="opening_date" name="opening_date" class="eproc-input"
                               value="<?php echo esc_attr( $bid && $bid->opening_date ? date( 'Y-m-d\TH:i', strtotime( $bid->opening_date ) ) : '' ); ?>">
                    </div>
                    <div class="eproc-form-group">
                        <label for="briefing_date"><?php esc_html_e( 'Briefing Date', 'eprocurement' ); ?></label>
                        <input type="datetime-local" id="briefing_date" name="briefing_date" class="eproc-input"
                               value="<?php echo esc_attr( $bid && $bid->briefing_date ? date( 'Y-m-d\TH:i', strtotime( $bid->briefing_date ) ) : '' ); ?>">
                    </div>
                    <div class="eproc-form-group">
                        <label for="closing_date"><?php esc_html_e( 'Closing Date', 'eprocurement' ); ?></label>
                        <input type="datetime-local" id="closing_date" name="closing_date" class="eproc-input"
                               value="<?php echo esc_attr( $bid && $bid->closing_date ? date( 'Y-m-d\TH:i', strtotime( $bid->closing_date ) ) : '' ); ?>">
                    </div>
                    <div class="eproc-form-group">
                        <label for="qa_deadline" class="eproc-form-label"><?php esc_html_e( 'Q&A Deadline', 'eprocurement' ); ?></label>
                        <input type="datetime-local" id="qa_deadline" name="qa_deadline" class="eproc-input"
                               value="<?php echo esc_attr( $bid && ! empty( $bid->qa_deadline ) ? date( 'Y-m-d\TH:i', strtotime( $bid->qa_deadline ) ) : '' ); ?>">
                        <span class="eproc-form-hint"><?php esc_html_e( 'Deadline for bidders to submit queries. Must be before the closing date.', 'eprocurement' ); ?></span>
                    </div>
                </div>
            </div>
            <!-- Submission Settings -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Submission Settings', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <div class="eproc-form-group">
                        <label class="eproc-checkbox-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal;">
                            <input type="checkbox" name="accept_online_submissions" id="accept_online_submissions" value="1"
                                <?php checked( $bid && ! empty( $bid->accept_online_submissions ) ); ?>
                                style="width:auto;margin:0;" />
                            <span><?php esc_html_e( 'Accept Online Submissions', 'eprocurement' ); ?></span>
                        </label>
                        <span class="eproc-form-hint"><?php esc_html_e( 'Allow bidders to upload bid documents through the portal. When disabled, bids must be submitted outside the system.', 'eprocurement' ); ?></span>
                    </div>
                    <div class="eproc-form-group" id="eproc-late-submissions-group" style="<?php echo ( $bid && ! empty( $bid->accept_online_submissions ) ) ? '' : 'display:none;'; ?>">
                        <label class="eproc-checkbox-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal;">
                            <input type="checkbox" name="allow_late_submissions" id="allow_late_submissions" value="1"
                                <?php checked( $bid && ! empty( $bid->allow_late_submissions ) ); ?>
                                style="width:auto;margin:0;" />
                            <span><?php esc_html_e( 'Allow Late Submissions', 'eprocurement' ); ?></span>
                        </label>
                        <span class="eproc-form-hint"><?php esc_html_e( 'Bidders can submit after the closing date (marked as late).', 'eprocurement' ); ?></span>
                    </div>
                    <div class="eproc-form-group" id="eproc-briefing-group" style="<?php echo ( $bid && ! empty( $bid->accept_online_submissions ) ) ? '' : 'display:none;'; ?>">
                        <label class="eproc-checkbox-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal;">
                            <input type="checkbox" name="briefing_compulsory" id="briefing_compulsory" value="1"
                                <?php checked( $bid && ! empty( $bid->briefing_compulsory ) ); ?>
                                style="width:auto;margin:0;" />
                            <span><?php esc_html_e( 'Compulsory Briefing Attendance', 'eprocurement' ); ?></span>
                        </label>
                        <span class="eproc-form-hint"><?php esc_html_e( 'Only bidders on the attendees list can submit.', 'eprocurement' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Briefing Attendees (shown via JS when compulsory is checked) -->
            <div class="eproc-card" id="eproc-attendees-card" style="<?php echo ( $bid && ! empty( $bid->briefing_compulsory ) ) ? '' : 'display:none;'; ?>">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Briefing Attendees', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <?php if ( $is_edit ) : ?>
                    <div class="eproc-form-row eproc-form-row--2col" style="margin-bottom:12px;">
                        <div class="eproc-form-group" style="margin-bottom:0;">
                            <input type="email" id="eproc-attendee-email" class="eproc-input" placeholder="<?php esc_attr_e( 'Email address', 'eprocurement' ); ?>" />
                        </div>
                        <div class="eproc-form-group" style="margin-bottom:0;">
                            <input type="text" id="eproc-attendee-company" class="eproc-input" placeholder="<?php esc_attr_e( 'Company name', 'eprocurement' ); ?>" />
                        </div>
                    </div>
                    <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-primary" id="eproc-add-attendee" style="margin-bottom:12px;">
                        <?php esc_html_e( 'Add Attendee', 'eprocurement' ); ?>
                    </button>
                    <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-outline" id="eproc-send-invites" style="margin-bottom:12px;display:none;">
                        <?php esc_html_e( 'Send Invite Emails', 'eprocurement' ); ?>
                    </button>

                    <div id="eproc-attendees-loading" class="eproc-text-center">
                        <p class="eproc-muted"><?php esc_html_e( 'Loading...', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-table-responsive" id="eproc-attendees-table-wrap" style="display:none;">
                        <table class="eproc-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Email', 'eprocurement' ); ?></th>
                                    <th><?php esc_html_e( 'Company', 'eprocurement' ); ?></th>
                                    <th class="eproc-col-action"><?php esc_html_e( 'Remove', 'eprocurement' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="eproc-attendees-list"></tbody>
                        </table>
                    </div>
                    <?php else : ?>
                        <p class="eproc-muted"><?php esc_html_e( 'Save the bid first, then manage attendees.', 'eprocurement' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; // $is_regular_bid ?>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    if (typeof eprocManage === 'undefined') {
        return;
    }

    var bidId         = <?php echo wp_json_encode( $bid_id ); ?>;
    var pendingDocIds = [];
    var editPageBase  = <?php echo wp_json_encode( $edit_page_base ); ?>;
    var backUrl       = <?php echo wp_json_encode( $back_url ); ?>;
    var isEdit        = <?php echo wp_json_encode( $is_edit ); ?>;
    var category      = <?php echo wp_json_encode( $eproc_category ); ?>;
    var saveBtnLabel  = <?php echo wp_json_encode( $is_edit ? __( 'Update Bid', 'eprocurement' ) : __( 'Save Draft', 'eprocurement' ) ); ?>;

    var form        = document.getElementById('eproc-bid-form');
    var saveBtn     = document.getElementById('eproc-save-bid');
    var noticeArea  = document.getElementById('eproc-bid-notices');

    // =========================================================================
    // Helper: show notice
    // =========================================================================

    function showNotice(message, type) {
        type = type || 'success';
        noticeArea.innerHTML = '<div class="eproc-notice ' + type + '"><p>' + escHtml(message) + '</p></div>';
        noticeArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // =========================================================================
    // Save bid via AJAX
    // =========================================================================

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var btn = saveBtn;
        window.eprocSetLoading(btn, true);

        var formData = {
            action:     'eproc_save_bid',
            nonce:      eprocManage.ajaxNonce,
            id:         document.querySelector('input[name="id"]').value,
            bid_number: document.getElementById('bid_number').value,
            title:      document.getElementById('title').value,
            description: document.getElementById('description').value,
            category:   document.querySelector('input[name="category"]').value
        };

        // Only include dates and contacts for regular bids
        if (formData.category === 'bid') {
            var scmSelect = document.getElementById('scm_contact_id');
            var techSelect = document.getElementById('technical_contact_id');
            if (scmSelect)  formData.scm_contact_id       = scmSelect.value;
            if (techSelect) formData.technical_contact_id = techSelect.value;

            var openingDate = document.getElementById('opening_date');
            var briefingDate = document.getElementById('briefing_date');
            var closingDate = document.getElementById('closing_date');
            if (openingDate)  formData.opening_date  = openingDate.value;
            if (briefingDate) formData.briefing_date  = briefingDate.value;
            if (closingDate)  formData.closing_date   = closingDate.value;

            // Submission settings
            var onlineCheck = document.getElementById('accept_online_submissions');
            var lateCheck = document.getElementById('allow_late_submissions');
            var briefCheck = document.getElementById('briefing_compulsory');
            if (onlineCheck) formData.accept_online_submissions = onlineCheck.checked ? 1 : 0;
            if (lateCheck)  formData.allow_late_submissions = lateCheck.checked ? 1 : 0;
            if (briefCheck) formData.briefing_compulsory    = briefCheck.checked ? 1 : 0;
        }

        // Include pending doc IDs for new bids
        if (pendingDocIds.length > 0) {
            formData.pending_doc_ids = pendingDocIds.join(',');
        }

        window.eprocAjax('eproc_save_bid', formData)
            .then(function(response) {
                if (response.success) {
                    if (window.eprocToast) {
                        window.eprocToast(response.data.message || eprocManage.strings.saved, 'success');
                    }
                    showNotice(response.data.message || eprocManage.strings.saved, 'success');
                    // Redirect to edit page if this was a new bid
                    if (!bidId && response.data.id) {
                        window.location.href = editPageBase + response.data.id;
                    }
                } else {
                    var msg = (response.data && response.data.message) || eprocManage.strings.error;
                    showNotice(msg, 'error');
                    if (window.eprocToast) {
                        window.eprocToast(msg, 'error');
                    }
                }
            })
            .catch(function() {
                showNotice(eprocManage.strings.error, 'error');
                if (window.eprocToast) {
                    window.eprocToast(eprocManage.strings.error, 'error');
                }
            })
            .finally(function() {
                window.eprocSetLoading(btn, false);
                btn.textContent = saveBtnLabel;
            });
    });

    // =========================================================================
    // Open Bid button -- save then transition to open
    // =========================================================================

    var openBidBtn = document.getElementById('eproc-open-bid');
    if (openBidBtn) {
        openBidBtn.addEventListener('click', function() {
            if (!window.eprocConfirm(<?php echo wp_json_encode( __( 'Save and open this bid? It will become publicly visible.', 'eprocurement' ) ); ?>)) {
                return;
            }

            window.eprocSetLoading(openBidBtn, true);

            // Save form first, then change status
            var saveData = {
                action:     'eproc_save_bid',
                nonce:      eprocManage.ajaxNonce,
                id:         bidId,
                bid_number: document.getElementById('bid_number').value,
                title:      document.getElementById('title').value,
                description: document.getElementById('description').value,
                category:   document.querySelector('input[name="category"]').value
            };

            if (saveData.category === 'bid') {
                var scmSelect  = document.getElementById('scm_contact_id');
                var techSelect = document.getElementById('technical_contact_id');
                if (scmSelect)  saveData.scm_contact_id       = scmSelect.value;
                if (techSelect) saveData.technical_contact_id = techSelect.value;

                var openingDate  = document.getElementById('opening_date');
                var briefingDate = document.getElementById('briefing_date');
                var closingDate  = document.getElementById('closing_date');
                if (openingDate)  saveData.opening_date  = openingDate.value;
                if (briefingDate) saveData.briefing_date  = briefingDate.value;
                if (closingDate)  saveData.closing_date   = closingDate.value;

                var onlineEl = document.getElementById('accept_online_submissions');
                var lateEl   = document.getElementById('allow_late_submissions');
                var briefEl  = document.getElementById('briefing_compulsory');
                if (onlineEl) saveData.accept_online_submissions = onlineEl.checked ? 1 : 0;
                if (lateEl)   saveData.allow_late_submissions    = lateEl.checked ? 1 : 0;
                if (briefEl)  saveData.briefing_compulsory       = briefEl.checked ? 1 : 0;
            }

            window.eprocAjax('eproc_save_bid', saveData)
            .then(function() {
                // Now change status
                return window.eprocAjax('eproc_change_status', {
                    id:     bidId,
                    status: 'open'
                });
            })
            .then(function(response) {
                if (response.success) {
                    window.eprocToast(<?php echo wp_json_encode( __( 'Bid opened successfully.', 'eprocurement' ) ); ?>, 'success');
                    location.reload();
                } else {
                    var msg = (response.data && response.data.message) || eprocManage.strings.error;
                    alert(msg);
                    window.eprocSetLoading(openBidBtn, false);
                }
            })
            .catch(function() {
                alert(eprocManage.strings.error);
                window.eprocSetLoading(openBidBtn, false);
            });
        });
    }

    // =========================================================================
    // Delete bid button
    // =========================================================================

    var deleteBidBtn = document.getElementById('eproc-delete-bid-btn');
    if (deleteBidBtn) {
        deleteBidBtn.addEventListener('click', function() {
            if (!window.eprocConfirm(eprocManage.strings.confirm_delete)) {
                return;
            }

            var deleteId = this.getAttribute('data-id');
            window.eprocSetLoading(deleteBidBtn, true);

            window.eprocAjax('eproc_delete_bid', {
                id: deleteId
            })
            .then(function(response) {
                if (response.success) {
                    window.eprocToast(<?php echo wp_json_encode( __( 'Bid deleted.', 'eprocurement' ) ); ?>, 'success');
                    window.location.href = backUrl;
                } else {
                    var msg = (response.data && response.data.message) || eprocManage.strings.error;
                    alert(msg);
                    window.eprocSetLoading(deleteBidBtn, false);
                }
            })
            .catch(function() {
                alert(eprocManage.strings.error);
                window.eprocSetLoading(deleteBidBtn, false);
            });
        });
    }

    // =========================================================================
    // Change status
    // =========================================================================

    var applyStatusBtn = document.getElementById('eproc-apply-status');
    if (applyStatusBtn) {
        applyStatusBtn.addEventListener('click', function() {
            var statusSelect = document.getElementById('eproc-change-status');
            var newStatus    = statusSelect ? statusSelect.value : '';
            if (!newStatus) return;

            if (!window.eprocConfirm(<?php echo wp_json_encode( __( 'Are you sure you want to change the status?', 'eprocurement' ) ); ?>)) {
                return;
            }

            window.eprocSetLoading(applyStatusBtn, true);

            // Save form first, then change status
            var saveData = {
                action:     'eproc_save_bid',
                nonce:      eprocManage.ajaxNonce,
                id:         bidId,
                bid_number: document.getElementById('bid_number').value,
                title:      document.getElementById('title').value,
                description: document.getElementById('description').value,
                category:   document.querySelector('input[name="category"]').value
            };

            if (saveData.category === 'bid') {
                var scmSelect  = document.getElementById('scm_contact_id');
                var techSelect = document.getElementById('technical_contact_id');
                if (scmSelect)  saveData.scm_contact_id       = scmSelect.value;
                if (techSelect) saveData.technical_contact_id = techSelect.value;

                var openingDate  = document.getElementById('opening_date');
                var briefingDate = document.getElementById('briefing_date');
                var closingDate  = document.getElementById('closing_date');
                if (openingDate)  saveData.opening_date  = openingDate.value;
                if (briefingDate) saveData.briefing_date  = briefingDate.value;
                if (closingDate)  saveData.closing_date   = closingDate.value;

                var onlineEl = document.getElementById('accept_online_submissions');
                var lateEl   = document.getElementById('allow_late_submissions');
                var briefEl  = document.getElementById('briefing_compulsory');
                if (onlineEl) saveData.accept_online_submissions = onlineEl.checked ? 1 : 0;
                if (lateEl)   saveData.allow_late_submissions    = lateEl.checked ? 1 : 0;
                if (briefEl)  saveData.briefing_compulsory       = briefEl.checked ? 1 : 0;
            }

            window.eprocAjax('eproc_save_bid', saveData)
            .then(function() {
                // Then change status
                return window.eprocAjax('eproc_change_status', {
                    id:     bidId,
                    status: newStatus
                });
            })
            .then(function(response) {
                if (response.success) {
                    window.eprocToast(<?php echo wp_json_encode( __( 'Status changed.', 'eprocurement' ) ); ?>, 'success');
                    location.reload();
                } else {
                    var msg = (response.data && response.data.message) || eprocManage.strings.error;
                    alert(msg);
                    window.eprocSetLoading(applyStatusBtn, false);
                }
            })
            .catch(function() {
                alert(eprocManage.strings.error);
                window.eprocSetLoading(applyStatusBtn, false);
            });
        });
    }

    // =========================================================================
    // File upload: drag and drop + click
    // =========================================================================

    var uploadArea = document.getElementById('eproc-upload-area');
    var fileInput  = document.getElementById('eproc-file-input');

    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', function(e) {
            // Prevent infinite loop: clicking on the file input would bubble back
            if (e.target === fileInput) return;
            fileInput.click();
        });

        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        uploadArea.addEventListener('dragenter', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                uploadFiles(e.dataTransfer.files);
            }
        });

        fileInput.addEventListener('change', function() {
            if (this.files.length) {
                uploadFiles(this.files);
                this.value = '';
            }
        });
    }

    function uploadFiles(files) {
        var progressContainer = document.getElementById('eproc-upload-progress');
        var progressBar       = document.getElementById('eproc-progress-bar');
        var statusText        = document.getElementById('eproc-upload-status');

        for (var i = 0; i < files.length; i++) {
            (function(file) {
                var formData = new FormData();
                formData.append('action', 'eproc_upload_supporting_doc');
                formData.append('nonce', eprocManage.ajaxNonce);
                formData.append('document_id', bidId || 0);
                formData.append('file', file);

                progressContainer.style.display = 'block';
                statusText.textContent = eprocManage.strings.uploading + ' ' + file.name;

                var xhr = new XMLHttpRequest();
                xhr.open('POST', eprocManage.ajaxUrl, true);
                xhr.withCredentials = true;

                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var pct = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = pct + '%';
                    }
                });

                xhr.onload = function() {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            statusText.textContent = eprocManage.strings.upload_success;

                            var table = document.getElementById('eproc-supporting-docs-table');
                            var tbody = document.getElementById('eproc-supporting-docs-list');
                            table.style.display = '';

                            // Format file size
                            var size = file.size < 1024 ? file.size + ' B' :
                                       file.size < 1048576 ? Math.round(file.size / 1024) + ' KB' :
                                       (file.size / 1048576).toFixed(1) + ' MB';

                            // Build date string
                            var now = new Date();
                            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                            var dateStr = now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();

                            var row = document.createElement('tr');
                            row.setAttribute('data-id', response.data.id);
                            row.innerHTML =
                                '<td>' + escHtml(file.name) + '</td>' +
                                '<td>' + size + '</td>' +
                                '<td>' + dateStr + '</td>' +
                                '<td><button type="button" class="eproc-btn eproc-btn-sm eproc-btn-danger eproc-remove-doc" data-id="' + response.data.id + '">&times;</button></td>';

                            tbody.appendChild(row);

                            // Track pending doc IDs for new bids
                            if (!bidId) {
                                pendingDocIds.push(response.data.id);
                                document.getElementById('eproc-pending-doc-ids').value = pendingDocIds.join(',');
                            }

                            if (window.eprocToast) {
                                window.eprocToast(file.name + ' ' + eprocManage.strings.upload_success, 'success');
                            }
                        } else {
                            var errMsg = (response.data && response.data.message) || eprocManage.strings.error;
                            statusText.textContent = errMsg;
                            if (window.eprocToast) {
                                window.eprocToast(errMsg, 'error');
                            }
                        }
                    } catch (err) {
                        statusText.textContent = eprocManage.strings.error;
                    }
                };

                xhr.onerror = function() {
                    statusText.textContent = eprocManage.strings.error;
                };

                xhr.onloadend = function() {
                    setTimeout(function() {
                        progressContainer.style.display = 'none';
                        progressBar.style.width = '0%';
                    }, 2000);
                };

                xhr.send(formData);
            })(files[i]);
        }
    }

    // =========================================================================
    // Remove bid document (delegated)
    // =========================================================================

    document.addEventListener('click', function(e) {
        var removeBtn = e.target.closest('.eproc-remove-doc');
        if (!removeBtn) return;

        e.preventDefault();

        if (!window.eprocConfirm(eprocManage.strings.confirm_delete)) {
            return;
        }

        var docId = removeBtn.getAttribute('data-id');
        var row   = removeBtn.closest('tr');

        removeBtn.disabled = true;

        window.eprocAjax('eproc_remove_supporting_doc', {
            id: docId
        })
        .then(function(response) {
            if (response.success) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function() {
                    row.remove();
                    var tbody = document.getElementById('eproc-supporting-docs-list');
                    if (tbody && tbody.children.length === 0) {
                        document.getElementById('eproc-supporting-docs-table').style.display = 'none';
                    }
                }, 300);

                // Remove from pending list if applicable
                pendingDocIds = pendingDocIds.filter(function(id) {
                    return String(id) !== String(docId);
                });
                document.getElementById('eproc-pending-doc-ids').value = pendingDocIds.join(',');

                if (window.eprocToast) {
                    window.eprocToast(<?php echo wp_json_encode( __( 'Document removed.', 'eprocurement' ) ); ?>, 'success');
                }
            } else {
                var msg = (response.data && response.data.message) || eprocManage.strings.error;
                alert(msg);
                removeBtn.disabled = false;
            }
        })
        .catch(function() {
            alert(eprocManage.strings.error);
            removeBtn.disabled = false;
        });
    });

    // =========================================================================
    // Accept Online Submissions toggle — show/hide sub-settings
    // =========================================================================

    var onlineCheck       = document.getElementById('accept_online_submissions');
    var lateGroup         = document.getElementById('eproc-late-submissions-group');
    var briefingGroup     = document.getElementById('eproc-briefing-group');
    var submissionsCard   = document.getElementById('eproc-submissions-card');

    if (onlineCheck) {
        onlineCheck.addEventListener('change', function() {
            var show = this.checked ? '' : 'none';
            if (lateGroup)      lateGroup.style.display      = show;
            if (briefingGroup)  briefingGroup.style.display   = show;
            if (submissionsCard) submissionsCard.style.display = show;
        });
    }

    // =========================================================================
    // Compulsory Briefing toggle — show/hide attendees card
    // =========================================================================

    var briefingCheck  = document.getElementById('briefing_compulsory');
    var attendeesCard  = document.getElementById('eproc-attendees-card');

    if (briefingCheck && attendeesCard) {
        briefingCheck.addEventListener('change', function() {
            attendeesCard.style.display = this.checked ? '' : 'none';
            if (this.checked && bidId) {
                loadAttendees();
            }
        });
    }

    // =========================================================================
    // Load submissions (edit mode, regular bids only)
    // =========================================================================

    if (isEdit && category === 'bid') {
        loadSubmissions();
        if (briefingCheck && briefingCheck.checked) {
            loadAttendees();
        }
    }

    function loadSubmissions() {
        var countEl    = document.getElementById('eproc-submission-count');
        var loadingEl  = document.getElementById('eproc-submissions-loading');
        var emptyEl    = document.getElementById('eproc-submissions-empty');
        var tableWrap  = document.getElementById('eproc-submissions-table-wrap');
        var tbody      = document.getElementById('eproc-submissions-list');
        var zipBtn     = document.getElementById('eproc-download-submissions-zip');
        if (!loadingEl) return;

        window.eprocAPI.get('admin/bids/' + bidId + '/submissions')
            .then(function(data) {
                loadingEl.style.display = 'none';
                if (!data.items || data.items.length === 0) {
                    emptyEl.style.display = '';
                    countEl.textContent = '0';
                    return;
                }

                countEl.textContent = data.items.length;
                zipBtn.style.display = '';
                tableWrap.style.display = '';
                tbody.innerHTML = '';

                data.items.forEach(function(sub) {
                    var row = document.createElement('tr');
                    var lateHtml = sub.is_late == 1 ? ' <span class="eproc-badge-late">Late</span>' : '';
                    var backdateHtml = '';
                    if (sub.backdated_by) {
                        backdateHtml = ' <span class="eproc-badge-late" title="' + escHtml('Original: ' + (sub.original_submitted_at || '')) + '">Backdated</span>';
                    }

                    var timestampCell = '<td>' + escHtml(sub.submitted_at) + lateHtml + backdateHtml;

                    // Super Admin backdate: make timestamp clickable
                    if (eprocManage.isSuperAdmin) {
                        timestampCell = '<td class="eproc-backdate-cell" data-sub-id="' + sub.id + '">' +
                            '<span class="eproc-backdate-trigger" title="' + escHtml(<?php echo wp_json_encode( __( 'Click to backdate', 'eprocurement' ) ); ?>) + '">' +
                            escHtml(sub.submitted_at) + '</span>' +
                            lateHtml + backdateHtml;
                    }
                    timestampCell += '</td>';

                    row.innerHTML =
                        '<td>' + escHtml(sub.company_name || sub.display_name || 'Unknown') + '</td>' +
                        '<td>' + escHtml(sub.file_name) + '</td>' +
                        timestampCell +
                        '<td>' + escHtml(sub.status) + '</td>' +
                        '<td><a href="' + eprocManage.restUrl + 'admin/submissions/' + sub.id + '/download" class="eproc-btn eproc-btn-sm eproc-btn-outline" target="_blank">Download</a></td>';

                    tbody.appendChild(row);
                });
            })
            .catch(function() {
                loadingEl.innerHTML = '<p class="eproc-text-muted">' + eprocManage.strings.error + '</p>';
            });
    }

    // Download ZIP
    var zipBtn = document.getElementById('eproc-download-submissions-zip');
    if (zipBtn) {
        zipBtn.addEventListener('click', function() {
            window.open(eprocManage.restUrl + 'admin/bids/' + bidId + '/submissions/download?_wpnonce=' + eprocManage.nonce, '_blank');
        });
    }

    // =========================================================================
    // Backdate UI (Super Admin only) — delegated click
    // =========================================================================

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('.eproc-backdate-trigger');
        if (!trigger || !eprocManage.isSuperAdmin) return;

        var cell  = trigger.closest('.eproc-backdate-cell');
        var subId = cell.getAttribute('data-sub-id');

        // Replace text with datetime input
        var currentDt = trigger.textContent.trim();
        var inputVal  = currentDt.replace(' ', 'T').replace(/(\d{2})\/(\d{2})\/(\d{4})/, '$3-$2-$1');
        // Try to parse into datetime-local format
        var d = new Date(currentDt);
        if (!isNaN(d.getTime())) {
            inputVal = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + 'T' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
        }

        cell.innerHTML =
            '<input type="datetime-local" class="eproc-input eproc-backdate-input" value="' + inputVal + '" style="width:180px;font-size:12px;" />' +
            '<div style="margin-top:6px;display:flex;gap:4px;">' +
                '<button type="button" class="eproc-btn eproc-btn-sm eproc-btn-primary eproc-backdate-visible" data-sub-id="' + subId + '">' + escHtml(<?php echo wp_json_encode( __( 'Show', 'eprocurement' ) ); ?>) + '</button>' +
                '<button type="button" class="eproc-btn eproc-btn-sm eproc-btn-outline eproc-backdate-hidden" data-sub-id="' + subId + '">' + escHtml(<?php echo wp_json_encode( __( 'Hide', 'eprocurement' ) ); ?>) + '</button>' +
                '<button type="button" class="eproc-btn eproc-btn-sm eproc-backdate-cancel">' + escHtml(<?php echo wp_json_encode( __( 'Cancel', 'eprocurement' ) ); ?>) + '</button>' +
            '</div>';
    });

    // Backdate: visible or hidden
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.eproc-backdate-visible, .eproc-backdate-hidden');
        if (!btn) return;

        var visible = btn.classList.contains('eproc-backdate-visible');
        var subId   = btn.getAttribute('data-sub-id');
        var cell    = btn.closest('.eproc-backdate-cell');
        var input   = cell.querySelector('.eproc-backdate-input');
        var newDt   = input ? input.value : '';

        if (!newDt) {
            alert(<?php echo wp_json_encode( __( 'Please select a date and time.', 'eprocurement' ) ); ?>);
            return;
        }

        btn.disabled = true;
        var confirmMsg = visible
            ? <?php echo wp_json_encode( __( 'Backdate with visible indicator? Staff will see "Backdated" badge.', 'eprocurement' ) ); ?>
            : <?php echo wp_json_encode( __( 'Backdate and hide? No one will see this was changed.', 'eprocurement' ) ); ?>;

        if (!window.eprocConfirm(confirmMsg)) {
            btn.disabled = false;
            return;
        }

        window.eprocAPI.patch('admin/submissions/' + subId + '/backdate', {
            submitted_at: newDt.replace('T', ' ') + ':00',
            visible: visible
        })
        .then(function(data) {
            if (data.success) {
                if (window.eprocToast) window.eprocToast(<?php echo wp_json_encode( __( 'Timestamp updated.', 'eprocurement' ) ); ?>, 'success');
                loadSubmissions(); // Refresh table
            } else {
                alert(data.message || eprocManage.strings.error);
            }
        })
        .catch(function() {
            alert(eprocManage.strings.error);
        })
        .finally(function() {
            btn.disabled = false;
        });
    });

    // Backdate: cancel
    document.addEventListener('click', function(e) {
        if (e.target.closest('.eproc-backdate-cancel')) {
            loadSubmissions(); // Just reload
        }
    });

    // =========================================================================
    // Briefing Attendees
    // =========================================================================

    function loadAttendees() {
        var loadingEl = document.getElementById('eproc-attendees-loading');
        var tableWrap = document.getElementById('eproc-attendees-table-wrap');
        var tbody     = document.getElementById('eproc-attendees-list');
        var inviteBtn = document.getElementById('eproc-send-invites');
        if (!loadingEl || !bidId) return;

        loadingEl.style.display = '';
        tableWrap.style.display = 'none';

        window.eprocAPI.get('admin/bids/' + bidId + '/attendees')
            .then(function(data) {
                loadingEl.style.display = 'none';
                if (!data.attendees || data.attendees.length === 0) {
                    tableWrap.style.display = 'none';
                    if (inviteBtn) inviteBtn.style.display = 'none';
                    return;
                }

                tableWrap.style.display = '';
                if (inviteBtn) inviteBtn.style.display = '';
                tbody.innerHTML = '';

                data.attendees.forEach(function(att) {
                    var row = document.createElement('tr');
                    row.setAttribute('data-id', att.id);
                    row.innerHTML =
                        '<td>' + escHtml(att.bidder_email) + '</td>' +
                        '<td>' + escHtml(att.company_name || '') + '</td>' +
                        '<td><button type="button" class="eproc-btn eproc-btn-sm eproc-btn-danger eproc-remove-attendee" data-id="' + att.id + '">&times;</button></td>';
                    tbody.appendChild(row);
                });
            })
            .catch(function() {
                loadingEl.innerHTML = '<p class="eproc-text-muted">' + eprocManage.strings.error + '</p>';
            });
    }

    // Add attendee
    var addAttendeeBtn = document.getElementById('eproc-add-attendee');
    if (addAttendeeBtn) {
        addAttendeeBtn.addEventListener('click', function() {
            var emailEl   = document.getElementById('eproc-attendee-email');
            var companyEl = document.getElementById('eproc-attendee-company');
            var email   = emailEl.value.trim();
            var company = companyEl.value.trim();

            if (!email) {
                alert(<?php echo wp_json_encode( __( 'Please enter an email address.', 'eprocurement' ) ); ?>);
                return;
            }

            window.eprocSetLoading(addAttendeeBtn, true);

            window.eprocAPI.post('admin/bids/' + bidId + '/attendees', {
                email: email,
                company_name: company
            })
            .then(function(data) {
                if (data.id) {
                    emailEl.value = '';
                    companyEl.value = '';
                    if (window.eprocToast) window.eprocToast(<?php echo wp_json_encode( __( 'Attendee added.', 'eprocurement' ) ); ?>, 'success');
                    loadAttendees();
                } else {
                    alert(data.message || eprocManage.strings.error);
                }
            })
            .catch(function() { alert(eprocManage.strings.error); })
            .finally(function() { window.eprocSetLoading(addAttendeeBtn, false); });
        });
    }

    // Remove attendee (delegated)
    document.addEventListener('click', function(e) {
        var removeBtn = e.target.closest('.eproc-remove-attendee');
        if (!removeBtn) return;

        if (!window.eprocConfirm(eprocManage.strings.confirm_delete)) return;

        var attId = removeBtn.getAttribute('data-id');
        removeBtn.disabled = true;

        window.eprocAPI.del('admin/attendees/' + attId)
            .then(function(data) {
                if (data.success) {
                    if (window.eprocToast) window.eprocToast(<?php echo wp_json_encode( __( 'Attendee removed.', 'eprocurement' ) ); ?>, 'success');
                    loadAttendees();
                } else {
                    alert(data.message || eprocManage.strings.error);
                    removeBtn.disabled = false;
                }
            })
            .catch(function() {
                alert(eprocManage.strings.error);
                removeBtn.disabled = false;
            });
    });

    // Send invite emails
    var sendInvitesBtn = document.getElementById('eproc-send-invites');
    if (sendInvitesBtn) {
        sendInvitesBtn.addEventListener('click', function() {
            if (!window.eprocConfirm(<?php echo wp_json_encode( __( 'Send briefing invite emails to all attendees?', 'eprocurement' ) ); ?>)) return;

            window.eprocSetLoading(sendInvitesBtn, true);

            window.eprocAPI.post('admin/bids/' + bidId + '/attendees/invite', {})
                .then(function(data) {
                    if (data.success) {
                        if (window.eprocToast) window.eprocToast(data.message || <?php echo wp_json_encode( __( 'Invites sent.', 'eprocurement' ) ); ?>, 'success');
                    } else {
                        alert(data.message || eprocManage.strings.error);
                    }
                })
                .catch(function() { alert(eprocManage.strings.error); })
                .finally(function() { window.eprocSetLoading(sendInvitesBtn, false); });
        });
    }

    // ════════════════════════════════════════════════════════════
    // EVALUATION MATRIX — criteria CRUD + comparison + award
    // ════════════════════════════════════════════════════════════
    var bidId = <?php echo wp_json_encode( $bid_id ); ?>;
    var evaluationCard = document.getElementById('eproc-evaluation-card');

    if (evaluationCard && bidId) {
        // ── Add criterion ──
        var addCritBtn = document.getElementById('eproc-add-criterion-btn');
        if (addCritBtn) {
            addCritBtn.addEventListener('click', function() {
                var name   = document.getElementById('eproc-new-criterion-name').value.trim();
                var desc   = document.getElementById('eproc-new-criterion-desc').value.trim();
                var weight = parseFloat(document.getElementById('eproc-new-criterion-weight').value) || 1;
                var max    = parseInt(document.getElementById('eproc-new-criterion-max').value, 10) || 10;

                if (!name) {
                    eprocToast('<?php echo esc_js( __( 'Please enter a criterion name.', 'eprocurement' ) ); ?>', 'error');
                    return;
                }

                window.eprocSetLoading(addCritBtn, true);
                eprocAPI.post('admin/bids/' + bidId + '/criteria', {
                    name: name, description: desc, weight: weight, max_score: max
                })
                .then(function() {
                    eprocToast('<?php echo esc_js( __( 'Criterion added.', 'eprocurement' ) ); ?>', 'success');
                    location.reload();
                })
                .catch(function(err) {
                    eprocToast(err.message || '<?php echo esc_js( __( 'Failed to add criterion.', 'eprocurement' ) ); ?>', 'error');
                })
                .finally(function() { window.eprocSetLoading(addCritBtn, false); });
            });
        }

        // ── Delete criterion ──
        document.querySelectorAll('.eproc-delete-criterion').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var critId = btn.getAttribute('data-criterion-id');
                if (!confirm('<?php echo esc_js( __( 'Delete this criterion? All scores recorded for it will also be deleted.', 'eprocurement' ) ); ?>')) return;

                eprocAPI.del('admin/criteria/' + critId)
                .then(function() {
                    eprocToast('<?php echo esc_js( __( 'Criterion deleted.', 'eprocurement' ) ); ?>', 'success');
                    var row = btn.closest('tr');
                    if (row) row.remove();
                })
                .catch(function(err) {
                    eprocToast(err.message || '<?php echo esc_js( __( 'Failed to delete criterion.', 'eprocurement' ) ); ?>', 'error');
                });
            });
        });

        // ── Comparison modal ──
        var compBtn = document.getElementById('eproc-comparison-btn');
        var compBody = document.getElementById('eproc-comparison-body');

        function openModal(id) {
            var m = document.getElementById(id);
            if (m) { m.style.display = 'flex'; m.setAttribute('aria-hidden', 'false'); }
        }
        function closeModal(id) {
            var m = document.getElementById(id);
            if (m) { m.style.display = 'none'; m.setAttribute('aria-hidden', 'true'); }
        }

        document.querySelectorAll('[data-close-modal]').forEach(function(el) {
            el.addEventListener('click', function() {
                closeModal(el.getAttribute('data-close-modal'));
            });
        });

        if (compBtn) {
            compBtn.addEventListener('click', function() {
                openModal('eproc-comparison-modal');
                eprocAPI.get('admin/bids/' + bidId + '/comparison')
                .then(function(data) {
                    renderComparison(data);
                })
                .catch(function(err) {
                    compBody.innerHTML = '<div class="eproc-notice error"><p>' + escHtml(err.message || 'Failed to load comparison.') + '</p></div>';
                });
            });
        }

        function renderComparison(data) {
            var criteria = data.criteria || [];
            var ranked = data.ranked || [];
            var award = data.award;

            if (ranked.length === 0) {
                compBody.innerHTML = '<div class="eproc-empty-state">' +
                    '<p class="eproc-empty-state-title"><?php echo esc_js( __( 'No submissions yet', 'eprocurement' ) ); ?></p>' +
                    '<p class="eproc-empty-state-text"><?php echo esc_js( __( 'Submissions will appear here once bidders submit their bids.', 'eprocurement' ) ); ?></p>' +
                    '</div>';
                return;
            }

            compBody.innerHTML = renderRankedTable(ranked, criteria, award);
        }

        function renderRankedTable(ranked, criteria, award) {
            var hasScores = ranked.some(function(r) { return r.criteria_scored > 0; });
            var html = '<div class="eproc-table-responsive"><table class="eproc-table eproc-comparison-table">' +
                '<thead><tr>' +
                '<th style="width:40px;">#</th>' +
                '<th><?php echo esc_js( __( 'Bidder', 'eprocurement' ) ); ?></th>' +
                '<th><?php echo esc_js( __( 'Company', 'eprocurement' ) ); ?></th>' +
                '<th style="width:100px;"><?php echo esc_js( __( 'Score', 'eprocurement' ) ); ?></th>' +
                '<th style="width:80px;"><?php echo esc_js( __( 'Late?', 'eprocurement' ) ); ?></th>' +
                '<th style="width:120px;"></th>' +
                '</tr></thead><tbody>';

            ranked.forEach(function(r) {
                var isWinner = award && award.user_id === r.submission.user_id;
                var medal = r.rank === 1 ? '🥇' : r.rank === 2 ? '🥈' : r.rank === 3 ? '🥉' : '';
                var scoreDisplay = hasScores
                    ? '<strong style="font-size:16px;color:' + (r.rank === 1 ? '#15803d' : '#1e293b') + ';">' + r.score_total.toFixed(1) + '</strong><span class="eproc-text-muted" style="font-size:11px;">/100</span>'
                    : '<span class="eproc-text-muted">—</span>';

                html += '<tr' + (isWinner ? ' class="eproc-winner-row"' : '') + '>' +
                    '<td class="eproc-rank-cell">' + medal + ' ' + r.rank + '</td>' +
                    '<td>' + escHtml(r.bidder_name) + '</td>' +
                    '<td>' + escHtml(r.company_name || '—') + '</td>' +
                    '<td class="eproc-score-cell">' + scoreDisplay + '</td>' +
                    '<td>' + (r.is_late ? '<span class="eproc-badge eproc-badge-unverified"><?php echo esc_js( __( 'Late', 'eprocurement' ) ); ?></span>' : '—') + '</td>' +
                    '<td>';

                if (isWinner) {
                    html += '<?php echo esc_js( __( 'Awarded', 'eprocurement' ) ); ?> 🏆';
                } else if (!award) {
                    html += '<button type="button" class="eproc-btn eproc-btn-sm eproc-btn-primary eproc-award-btn" data-user-id="' + r.submission.user_id + '" data-bidder-name="' + escAttr(r.bidder_name) + '" data-company="' + escAttr(r.company_name || '') + '">' +
                        '<?php echo esc_js( __( 'Award', 'eprocurement' ) ); ?></button>';
                }

                html += '</td></tr>';
            });

            html += '</tbody></table></div>';

            if (!hasScores && criteria.length > 0) {
                html += '<p class="eproc-form-hint" style="margin-top:12px;"><?php echo esc_js( __( 'Scores will appear once evaluators start rating submissions.', 'eprocurement' ) ); ?></p>';
            }

            return html;
        }

        // ── Award modal ──
        document.addEventListener('click', function(e) {
            var awardBtn = e.target.closest('.eproc-award-btn');
            if (!awardBtn) return;

            var userId = awardBtn.getAttribute('data-user-id');
            var bidderName = awardBtn.getAttribute('data-bidder-name');
            var company = awardBtn.getAttribute('data-company');

            var winnerSelect = document.getElementById('eproc-award-winner');
            winnerSelect.innerHTML = '<option value="' + userId + '" selected>' + escHtml(company ? company + ' (' + bidderName + ')' : bidderName) + '</option>';

            closeModal('eproc-comparison-modal');
            openModal('eproc-award-modal');
        });

        // Confirm award.
        var confirmAwardBtn = document.getElementById('eproc-confirm-award');
        if (confirmAwardBtn) {
            confirmAwardBtn.addEventListener('click', function() {
                var winnerId = parseInt(document.getElementById('eproc-award-winner').value, 10);
                var amount   = parseFloat(document.getElementById('eproc-award-amount').value) || 0;
                var notes    = document.getElementById('eproc-award-notes').value.trim();

                if (!winnerId) {
                    eprocToast('<?php echo esc_js( __( 'Please select a bidder to award.', 'eprocurement' ) ); ?>', 'error');
                    return;
                }

                if (!confirm('<?php echo esc_js( __( 'Are you sure? This will send email notifications to all bidders who submitted.', 'eprocurement' ) ); ?>')) return;

                window.eprocSetLoading(confirmAwardBtn, true);
                eprocAPI.post('admin/bids/' + bidId + '/award', {
                    winner_user_id: winnerId,
                    award_amount: amount,
                    award_notes: notes
                })
                .then(function() {
                    eprocToast('<?php echo esc_js( __( 'Tender awarded. Notifications sent.', 'eprocurement' ) ); ?>', 'success');
                    closeModal('eproc-award-modal');
                    setTimeout(function() { location.reload(); }, 1500);
                })
                .catch(function(err) {
                    eprocToast(err.message || '<?php echo esc_js( __( 'Failed to award tender.', 'eprocurement' ) ); ?>', 'error');
                })
                .finally(function() { window.eprocSetLoading(confirmAwardBtn, false); });
            });
        }

        function escHtml(s) {
            var d = document.createElement('div');
            d.textContent = s || '';
            return d.innerHTML;
        }
        function escAttr(s) {
            return String(s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
    }
});
</script>
