<?php
/**
 * Add/Edit bid document partial.
 *
 * Two-column layout: Left = Bid Information form, Right = Status/Contacts/Dates.
 * Bid Documents section below on edit.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$documents = new Eprocurement_Documents();
$contacts  = new Eprocurement_Contact_Persons();

$bid_id         = absint( $_GET['id'] ?? 0 );
$bid            = $bid_id ? $documents->get( $bid_id ) : null;
$is_edit        = (bool) $bid;
$eproc_category = $eproc_category ?? ( $bid ? ( $bid->category ?? 'bid' ) : 'bid' );
$is_regular_bid = ( $eproc_category === 'bid' );
$current_page_slug = sanitize_text_field( $_GET['page'] ?? 'eprocurement-bids' );

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
$all_contacts      = $contacts->get_all();

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
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-bids' ) ); ?>" class="eproc-back-link">&larr;</a>
            <?php echo esc_html( $page_title ); ?>
            <?php if ( $is_edit && $is_regular_bid ) : ?>
                <span class="eproc-status-badge eproc-status-<?php echo esc_attr( $current_status ); ?>">
                    <?php echo esc_html( ucfirst( $current_status ) ); ?>
                </span>
            <?php endif; ?>
        </h1>
    </div>

    <div id="eproc-bid-notices"></div>

    <form id="eproc-bid-form" method="post" class="eproc-bid-layout">
        <?php wp_nonce_field( 'eproc_admin_nonce', 'eproc_nonce' ); ?>
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
                               placeholder="<?php esc_attr_e( 'e.g. BID/2026/001', 'eprocurement' ); ?>">
                    </div>
                    <div class="eproc-form-group">
                        <label for="title"><?php esc_html_e( 'Title', 'eprocurement' ); ?> <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required
                               value="<?php echo esc_attr( $bid ? $bid->title : '' ); ?>"
                               placeholder="<?php esc_attr_e( 'Bid title', 'eprocurement' ); ?>">
                    </div>
                    <div class="eproc-form-group">
                        <label for="description"><?php esc_html_e( 'Description', 'eprocurement' ); ?></label>
                        <p class="eproc-text-muted eproc-text-sm" style="margin:0 0 8px;">
                            <?php esc_html_e( 'Type a description below OR click "Upload Screenshot" to insert an image of the description from the bid document. The image will display inline — no click required.', 'eprocurement' ); ?>
                        </p>
                        <button type="button" class="eproc-btn eproc-btn-sm" id="eproc-upload-desc-screenshot" style="margin-bottom:8px;">
                            &#128247; <?php esc_html_e( 'Upload Screenshot', 'eprocurement' ); ?>
                        </button>
                        <?php
                        wp_editor(
                            $bid ? $bid->description : '',
                            'description',
                            [
                                'textarea_name' => 'description',
                                'media_buttons' => true,
                                'textarea_rows' => 8,
                                'teeny'         => false,
                                'quicktags'     => true,
                            ]
                        );
                        ?>
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
                        <div class="eproc-upload-icon">&#128228;</div>
                        <p class="eproc-upload-text">
                            <?php esc_html_e( 'Drag and drop files here, or click to select files', 'eprocurement' ); ?>
                        </p>
                        <p class="eproc-upload-hint">
                            <?php esc_html_e( 'Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP (max 50MB)', 'eprocurement' ); ?>
                        </p>
                        <input type="file" id="eproc-file-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                    </div>

                    <!-- Upload Progress -->
                    <div id="eproc-upload-progress" class="eproc-upload-progress">
                        <div class="eproc-progress-track">
                            <div id="eproc-progress-bar" class="eproc-progress-fill"></div>
                        </div>
                        <p id="eproc-upload-status" class="eproc-upload-status-text"></p>
                    </div>

                    <!-- Pending files queue (new bids only) -->
                    <input type="hidden" id="eproc-pending-doc-ids" name="pending_doc_ids" value="">

                    <!-- File List -->
                    <table class="wp-list-table widefat" id="eproc-supporting-docs-table" <?php echo empty( $supporting_docs ) ? 'style="display:none;"' : ''; ?>>
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
                                        <?php if ( $doc->label ) : ?>
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
        </div>

        <!-- Right Column: Status, Contacts, Dates -->
        <div class="eproc-bid-sidebar">

            <!-- Status & Actions -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php echo $is_regular_bid ? esc_html__( 'Status & Actions', 'eprocurement' ) : esc_html__( 'Actions', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <?php if ( $is_regular_bid && $is_edit && ! empty( $allowed_next ) && current_user_can( 'eproc_publish_bids' ) ) : ?>
                        <div class="eproc-form-group">
                            <label for="eproc-change-status"><?php esc_html_e( 'Change Status', 'eprocurement' ); ?></label>
                            <div class="eproc-input-group">
                                <select id="eproc-change-status">
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

                    <?php if ( $is_regular_bid ) : ?>
                        <button type="submit" class="eproc-btn eproc-btn-primary eproc-btn-block" id="eproc-save-bid">
                            <?php echo $is_edit ? esc_html__( 'Update Bid', 'eprocurement' ) : esc_html__( 'Save Draft', 'eprocurement' ); ?>
                        </button>

                        <?php if ( ( ! $is_edit || $current_status === 'draft' ) && current_user_can( 'eproc_publish_bids' ) ) : ?>
                            <button type="button" class="eproc-btn eproc-btn-success eproc-btn-block eproc-mt-sm" id="eproc-open-bid">
                                <?php echo $is_edit ? esc_html__( 'Open Bid', 'eprocurement' ) : esc_html__( 'Save & Open Bid', 'eprocurement' ); ?>
                            </button>
                        <?php endif; ?>
                    <?php else : ?>
                        <!-- Non-bid categories: simple save only, no status workflow -->
                        <button type="submit" class="eproc-btn eproc-btn-primary eproc-btn-block" id="eproc-save-bid">
                            <?php echo $is_edit ? esc_html__( 'Update', 'eprocurement' ) : esc_html__( 'Save', 'eprocurement' ); ?>
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
                        <select id="scm_contact_id" name="scm_contact_id">
                            <option value=""><?php esc_html_e( '-- None --', 'eprocurement' ); ?></option>
                            <?php foreach ( $scm_contacts as $c ) : ?>
                                <option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $bid ? (int) $bid->scm_contact_id : 0, (int) $c->id ); ?>>
                                    <?php echo esc_html( $c->name ); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php
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
                        <select id="technical_contact_id" name="technical_contact_id">
                            <option value=""><?php esc_html_e( '-- None --', 'eprocurement' ); ?></option>
                            <?php foreach ( $tech_contacts as $c ) : ?>
                                <option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $bid ? (int) $bid->technical_contact_id : 0, (int) $c->id ); ?>>
                                    <?php echo esc_html( $c->name ); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php
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
                        <input type="datetime-local" id="opening_date" name="opening_date"
                               value="<?php echo esc_attr( $bid && $bid->opening_date ? date( 'Y-m-d\TH:i', strtotime( $bid->opening_date ) ) : '' ); ?>">
                    </div>
                    <div class="eproc-form-group">
                        <label for="briefing_date"><?php esc_html_e( 'Briefing Date', 'eprocurement' ); ?></label>
                        <input type="datetime-local" id="briefing_date" name="briefing_date"
                               value="<?php echo esc_attr( $bid && $bid->briefing_date ? date( 'Y-m-d\TH:i', strtotime( $bid->briefing_date ) ) : '' ); ?>">
                    </div>
                    <div class="eproc-form-group">
                        <label for="closing_date"><?php esc_html_e( 'Closing Date', 'eprocurement' ); ?></label>
                        <input type="datetime-local" id="closing_date" name="closing_date"
                               value="<?php echo esc_attr( $bid && $bid->closing_date ? date( 'Y-m-d\TH:i', strtotime( $bid->closing_date ) ) : '' ); ?>">
                    </div>
                </div>
            </div>
            <?php endif; // $is_regular_bid ?>
        </div>
    </form>
</div>

<script>
jQuery(function($) {
    var bidId = <?php echo wp_json_encode( $bid_id ); ?>;
    var pendingDocIds = [];
    var editPageBase = '<?php echo esc_url( admin_url( 'admin.php?page=' . $current_page_slug . '&action=edit&id=' ) ); ?>';

    // Toast helper — reuse the global showNotice if available, otherwise inline
    function bidToast(message, type) {
        if (typeof window.eprocShowNotice === 'function') {
            window.eprocShowNotice(message, type);
            return;
        }
        $('.eproc-toast-notification').remove();
        var icons = { success: '&#10003;', error: '&#10007;', info: '&#9432;' };
        var icon = icons[type] || icons.info;
        var $t = $('<div class="eproc-toast-notification ' + (type || 'success') + '">' +
            '<span class="eproc-toast-icon">' + icon + '</span>' +
            '<span class="eproc-toast-message">' + message + '</span>' +
            '<button type="button" class="eproc-toast-close">&times;</button></div>');
        $('body').append($t);
        setTimeout(function() { $t.addClass('show'); }, 10);
        $t.find('.eproc-toast-close').on('click', function() {
            $t.removeClass('show'); setTimeout(function() { $t.remove(); }, 300);
        });
        setTimeout(function() {
            $t.removeClass('show'); setTimeout(function() { $t.remove(); }, 300);
        }, 5000);
    }

    // Upload Screenshot button — opens WP Media uploader, inserts image into editor
    $('#eproc-upload-desc-screenshot').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: '<?php echo esc_js( __( 'Select or Upload Screenshot', 'eprocurement' ) ); ?>',
            button: { text: '<?php echo esc_js( __( 'Insert Screenshot', 'eprocurement' ) ); ?>' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var imgTag = '<img src="' + attachment.url + '" alt="Bid Description Screenshot" style="max-width:100%;height:auto;" />';
            // Insert into TinyMCE or textarea
            if (typeof tinymce !== 'undefined' && tinymce.get('description') && !tinymce.get('description').isHidden()) {
                tinymce.get('description').execCommand('mceInsertContent', false, imgTag);
            } else {
                var $ta = $('#description');
                $ta.val($ta.val() + imgTag);
            }
        });
        frame.open();
    });

    // Date validation: opening <= briefing <= closing
    function validateBidDates() {
        var category = $('input[name="category"]').val();
        if (category !== 'bid') return true;

        var opening  = $('#opening_date').val();
        var briefing = $('#briefing_date').val();
        var closing  = $('#closing_date').val();

        if (opening && closing && opening > closing) {
            bidToast('<?php echo esc_js( __( 'Opening date cannot be after closing date.', 'eprocurement' ) ); ?>', 'error');
            return false;
        }
        if (briefing && closing && briefing > closing) {
            bidToast('<?php echo esc_js( __( 'Briefing date cannot be after closing date.', 'eprocurement' ) ); ?>', 'error');
            return false;
        }
        if (opening && briefing && opening > briefing) {
            bidToast('<?php echo esc_js( __( 'Opening date cannot be after briefing date.', 'eprocurement' ) ); ?>', 'error');
            return false;
        }
        return true;
    }

    // Save bid via AJAX
    $('#eproc-bid-form').on('submit', function(e) {
        e.preventDefault();
        if (!validateBidDates()) return;
        var $btn = $('#eproc-save-bid');
        $btn.prop('disabled', true).text(eprocAdmin.strings.saving);

        if (typeof tinymce !== 'undefined' && tinymce.get('description')) {
            tinymce.get('description').save();
        }

        var formData = {
            action:               'eproc_save_bid',
            nonce:                eprocAdmin.nonce,
            id:                   $('input[name="id"]').val(),
            bid_number:           $('#bid_number').val(),
            title:                $('#title').val(),
            description:          $('#description').val(),
            category:             $('input[name="category"]').val()
        };

        // Only include dates and contacts for regular bids
        if (formData.category === 'bid') {
            formData.scm_contact_id       = $('#scm_contact_id').val();
            formData.technical_contact_id = $('#technical_contact_id').val();
            formData.opening_date         = $('#opening_date').val();
            formData.briefing_date        = $('#briefing_date').val();
            formData.closing_date         = $('#closing_date').val();
        }

        // Include pending doc IDs for new bids
        if (pendingDocIds.length > 0) {
            formData.pending_doc_ids = pendingDocIds.join(',');
        }

        $.post(eprocAdmin.ajaxUrl, formData, function(response) {
            if (response && response.success) {
                bidToast(response.data && response.data.message ? response.data.message : eprocAdmin.strings.saved, 'success');
                if (!bidId && response.data && response.data.id) {
                    window.location.href = editPageBase + response.data.id;
                }
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : eprocAdmin.strings.error;
                bidToast(msg, 'error');
            }
        }).fail(function() {
            bidToast(eprocAdmin.strings.error, 'error');
        }).always(function() {
            <?php if ( $is_regular_bid ) : ?>
            $btn.prop('disabled', false).text('<?php echo $is_edit ? esc_js( __( 'Update Bid', 'eprocurement' ) ) : esc_js( __( 'Save Draft', 'eprocurement' ) ); ?>');
            <?php else : ?>
            $btn.prop('disabled', false).text('<?php echo $is_edit ? esc_js( __( 'Update', 'eprocurement' ) ) : esc_js( __( 'Save', 'eprocurement' ) ); ?>');
            <?php endif; ?>
        });
    });

    // Open Bid button — save then transition to open
    $('#eproc-open-bid').on('click', function() {
        if (!validateBidDates()) return;

        if (!confirm('<?php echo esc_js( __( 'Save and open this bid? It will become publicly visible.', 'eprocurement' ) ); ?>')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'eprocurement' ) ); ?>');

        if (bidId) {
            // Existing bid — just change status
            $.post(eprocAdmin.ajaxUrl, {
                action: 'eproc_change_status',
                nonce:  eprocAdmin.nonce,
                id:     bidId,
                status: 'open'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || eprocAdmin.strings.error);
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Open Bid', 'eprocurement' ) ); ?>');
                }
            });
        } else {
            // New bid — save first, then change status to open
            if (typeof tinymce !== 'undefined' && tinymce.get('description')) {
                tinymce.get('description').save();
            }

            var formData = {
                action:     'eproc_save_bid',
                nonce:      eprocAdmin.nonce,
                id:         0,
                bid_number: $('#bid_number').val(),
                title:      $('#title').val(),
                description: $('#description').val(),
                category:   $('input[name="category"]').val()
            };

            if (formData.category === 'bid') {
                formData.scm_contact_id       = $('#scm_contact_id').val();
                formData.technical_contact_id = $('#technical_contact_id').val();
                formData.opening_date         = $('#opening_date').val();
                formData.briefing_date        = $('#briefing_date').val();
                formData.closing_date         = $('#closing_date').val();
            }

            if (pendingDocIds.length > 0) {
                formData.pending_doc_ids = pendingDocIds.join(',');
            }

            $.post(eprocAdmin.ajaxUrl, formData, function(response) {
                if (response.success && response.data.id) {
                    // Now change status to open
                    $.post(eprocAdmin.ajaxUrl, {
                        action: 'eproc_change_status',
                        nonce:  eprocAdmin.nonce,
                        id:     response.data.id,
                        status: 'open'
                    }, function(statusResponse) {
                        if (statusResponse.success) {
                            window.location.href = editPageBase + response.data.id;
                        } else {
                            // Saved but status change failed — redirect to edit page anyway
                            window.location.href = editPageBase + response.data.id;
                        }
                    });
                } else {
                    bidToast(response.data.message || eprocAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save & Open Bid', 'eprocurement' ) ); ?>');
                }
            }).fail(function() {
                bidToast(eprocAdmin.strings.error, 'error');
                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save & Open Bid', 'eprocurement' ) ); ?>');
            });
        }
    });

    // Delete bid button
    $('#eproc-delete-bid-btn').on('click', function() {
        if (!confirm(eprocAdmin.strings.confirm_delete)) return;

        var deleteId = $(this).data('id');
        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_delete_bid',
            nonce:  eprocAdmin.nonce,
            id:     deleteId
        }, function(response) {
            if (response.success) {
                window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=' . $current_page_slug ) ); ?>';
            } else {
                alert(response.data.message || eprocAdmin.strings.error);
            }
        });
    });

    // Change status
    $('#eproc-apply-status').on('click', function() {
        var newStatus = $('#eproc-change-status').val();
        if (!newStatus) return;

        if (!confirm('<?php echo esc_js( __( 'Are you sure you want to change the status?', 'eprocurement' ) ); ?>')) {
            return;
        }

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_change_status',
            nonce:  eprocAdmin.nonce,
            id:     bidId,
            status: newStatus
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || eprocAdmin.strings.error);
            }
        });
    });

    // File upload via drag and drop
    var $uploadArea = $('#eproc-upload-area');
    var $fileInput  = $('#eproc-file-input');

    $uploadArea.on('click', function(e) {
        // Avoid infinite loop: file input is inside upload area, so its click would bubble back.
        if ( e.target === $fileInput[0] ) return;
        $fileInput[0].click();
    });

    $uploadArea.on('dragover dragenter', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $uploadArea.on('dragleave drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    $uploadArea.on('drop', function(e) {
        var files = e.originalEvent.dataTransfer.files;
        if (files.length) {
            uploadFiles(files);
        }
    });

    $fileInput.on('change', function() {
        if (this.files.length) {
            uploadFiles(this.files);
            this.value = '';
        }
    });

    function uploadFiles(files) {
        var $progress = $('#eproc-upload-progress');
        var $bar      = $('#eproc-progress-bar');
        var $status   = $('#eproc-upload-status');

        for (var i = 0; i < files.length; i++) {
            (function(file) {
                var formData = new FormData();
                formData.append('action', 'eproc_upload_supporting_doc');
                formData.append('nonce', eprocAdmin.nonce);
                formData.append('document_id', bidId || 0);
                formData.append('file', file);

                $progress.show();
                $status.text(eprocAdmin.strings.uploading + ' ' + file.name);

                $.ajax({
                    url:         eprocAdmin.ajaxUrl,
                    type:        'POST',
                    data:        formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                var pct = Math.round((e.loaded / e.total) * 100);
                                $bar.css('width', pct + '%');
                            }
                        });
                        return xhr;
                    },
                    success: function(response) {
                        if (response && response.success) {
                            bidToast(eprocAdmin.strings.upload_success, 'success');
                            $status.text(eprocAdmin.strings.upload_success);
                            var $table = $('#eproc-supporting-docs-table');
                            $table.show();
                            var size = file.size < 1024 ? file.size + ' B' :
                                       file.size < 1048576 ? Math.round(file.size / 1024) + ' KB' :
                                       (file.size / 1048576).toFixed(1) + ' MB';
                            var docId = response.data ? response.data.id : 0;
                            var row = '<tr data-id="' + docId + '">' +
                                '<td>' + $('<span>').text(file.name).html() + '</td>' +
                                '<td>' + size + '</td>' +
                                '<td><?php echo esc_js( wp_date( 'j M Y' ) ); ?></td>' +
                                '<td><button type="button" class="eproc-btn eproc-btn-sm eproc-btn-danger eproc-remove-doc" data-id="' + docId + '">&times;</button></td>' +
                                '</tr>';
                            $('#eproc-supporting-docs-list').append(row);

                            // Track pending doc IDs for new bids
                            if (!bidId && docId) {
                                pendingDocIds.push(docId);
                                $('#eproc-pending-doc-ids').val(pendingDocIds.join(','));
                            }
                        } else {
                            var errMsg = (response && response.data && response.data.message) ? response.data.message : eprocAdmin.strings.error;
                            bidToast(errMsg, 'error');
                            $status.text(errMsg);
                        }
                    },
                    error: function() {
                        bidToast(eprocAdmin.strings.error, 'error');
                        $status.text(eprocAdmin.strings.error);
                    },
                    complete: function() {
                        setTimeout(function() {
                            $progress.fadeOut(300, function() {
                                $bar.css('width', '0%');
                            });
                        }, 2000);
                    }
                });
            })(files[i]);
        }
    }

    // Remove bid document
    $(document).on('click', '.eproc-remove-doc', function() {
        var $btn = $(this);
        var docId = $btn.data('id');
        var $row = $btn.closest('tr');

        if (!confirm(eprocAdmin.strings.confirm_delete)) return;

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_remove_supporting_doc',
            nonce:  eprocAdmin.nonce,
            id:     docId
        }, function(response) {
            if (response.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    if ($('#eproc-supporting-docs-list tr').length === 0) {
                        $('#eproc-supporting-docs-table').hide();
                    }
                });
                // Remove from pending list if applicable
                pendingDocIds = pendingDocIds.filter(function(id) { return id != docId; });
                $('#eproc-pending-doc-ids').val(pendingDocIds.join(','));
            } else {
                alert(response.data.message || eprocAdmin.strings.error);
            }
        });
    });
});
</script>
