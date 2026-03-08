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
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1>
            <a href="<?php echo esc_url( $back_url ); ?>" class="eproc-back-link">&larr;</a>
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
            <!-- Bid Submissions -->
            <div class="eproc-card" id="eproc-submissions-card">
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
        </div>

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
                            <input type="checkbox" name="allow_late_submissions" id="allow_late_submissions" value="1"
                                <?php checked( $bid && ! empty( $bid->allow_late_submissions ) ); ?>
                                style="width:auto;margin:0;" />
                            <span><?php esc_html_e( 'Allow Late Submissions', 'eprocurement' ); ?></span>
                        </label>
                        <span class="eproc-form-hint"><?php esc_html_e( 'Bidders can submit after the closing date (marked as late).', 'eprocurement' ); ?></span>
                    </div>
                    <div class="eproc-form-group">
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
            var lateCheck = document.getElementById('allow_late_submissions');
            var briefCheck = document.getElementById('briefing_compulsory');
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

            window.eprocAjax('eproc_change_status', {
                id:     bidId,
                status: 'open'
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

            window.eprocAjax('eproc_change_status', {
                id:     bidId,
                status: newStatus
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
});
</script>
