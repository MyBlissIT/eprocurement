<?php
/**
 * SCM document library partial.
 *
 * Manages the static SCM documents (BBBEE, tax clearance templates, etc.)
 * with an editable section title, upload form, and sortable document list.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$compliance    = new Eprocurement_Compliance_Docs();
$all_docs      = $compliance->get_all();
$section_title = Eprocurement_Compliance_Docs::get_section_title();
?>
<div class="eproc-wrap">
    <h1><?php esc_html_e( 'SCM Documents', 'eprocurement' ); ?></h1>

    <div id="eproc-compliance-notices"></div>

    <div class="eproc-compliance-layout">

        <!-- Left: Upload and Settings -->
        <div class="eproc-compliance-sidebar">

            <!-- Section Title -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Section Title', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <p class="eproc-text-muted eproc-text-sm">
                        <?php esc_html_e( 'This title is displayed on the public-facing SCM documents section.', 'eprocurement' ); ?>
                    </p>
                    <div class="eproc-input-group">
                        <input type="text" id="eproc-compliance-title"
                               value="<?php echo esc_attr( $section_title ); ?>"
                               placeholder="<?php esc_attr_e( 'SCM Documents', 'eprocurement' ); ?>">
                        <button type="button" id="eproc-save-title" class="eproc-btn"><?php esc_html_e( 'Save', 'eprocurement' ); ?></button>
                    </div>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Upload Document', 'eprocurement' ); ?></h2>
                </div>
                <div class="eproc-card-body">
                    <form id="eproc-compliance-upload-form" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'eproc_admin_nonce', 'eproc_compliance_nonce' ); ?>

                        <div class="eproc-form-group">
                            <label for="eproc-comp-label"><?php esc_html_e( 'Label', 'eprocurement' ); ?></label>
                            <input type="text" id="eproc-comp-label" name="label" required
                                   placeholder="<?php esc_attr_e( 'e.g. BBBEE Certificate Template', 'eprocurement' ); ?>">
                        </div>

                        <div class="eproc-form-group">
                            <label for="eproc-comp-description"><?php esc_html_e( 'Description', 'eprocurement' ); ?></label>
                            <textarea id="eproc-comp-description" name="description" rows="3"
                                      placeholder="<?php esc_attr_e( 'Brief description of this document...', 'eprocurement' ); ?>"></textarea>
                        </div>

                        <div class="eproc-form-group">
                            <label for="eproc-comp-file"><?php esc_html_e( 'File', 'eprocurement' ); ?></label>
                            <input type="file" id="eproc-comp-file" name="file" required
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                            <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP (max 50MB)', 'eprocurement' ); ?></p>
                        </div>

                        <!-- Upload Progress -->
                        <div id="eproc-comp-progress" class="eproc-upload-progress">
                            <div class="eproc-progress-track">
                                <div id="eproc-comp-progress-bar" class="eproc-progress-fill"></div>
                            </div>
                            <p id="eproc-comp-progress-text" class="eproc-upload-status-text"></p>
                        </div>

                        <button type="submit" class="eproc-btn eproc-btn-primary" id="eproc-comp-upload-btn">
                            <?php esc_html_e( 'Upload Document', 'eprocurement' ); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Document List -->
        <div class="eproc-compliance-main">
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2>
                        <?php esc_html_e( 'Document Library', 'eprocurement' ); ?>
                        <span class="eproc-result-count">(<?php echo esc_html( count( $all_docs ) ); ?>)</span>
                    </h2>
                </div>
                <div class="eproc-card-body eproc-card-body--flush">
                    <?php if ( ! empty( $all_docs ) ) : ?>
                        <table class="wp-list-table widefat" id="eproc-compliance-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="eproc-col-action"><?php esc_html_e( '#', 'eprocurement' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Label', 'eprocurement' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'File Name', 'eprocurement' ); ?></th>
                                    <th scope="col" class="eproc-col-narrow"><?php esc_html_e( 'Size', 'eprocurement' ); ?></th>
                                    <th scope="col" class="eproc-col-narrow"><?php esc_html_e( 'Uploaded', 'eprocurement' ); ?></th>
                                    <th scope="col" class="eproc-col-action"><?php esc_html_e( 'Actions', 'eprocurement' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="eproc-compliance-list">
                                <?php foreach ( $all_docs as $index => $doc ) : ?>
                                    <tr data-id="<?php echo esc_attr( $doc->id ); ?>">
                                        <td>
                                            <span class="eproc-sort-handle" title="<?php esc_attr_e( 'Drag to reorder', 'eprocurement' ); ?>">&#9776;</span>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html( $doc->label ?: $doc->file_name ); ?></strong>
                                            <?php if ( $doc->description ) : ?>
                                                <br><span class="eproc-text-muted eproc-text-sm"><?php echo esc_html( $doc->description ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( $doc->file_name ); ?></td>
                                        <td><?php echo esc_html( size_format( $doc->file_size ) ); ?></td>
                                        <td><?php echo esc_html( wp_date( 'j M Y', strtotime( $doc->created_at ) ) ); ?></td>
                                        <td>
                                            <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-danger eproc-delete-compliance" data-id="<?php echo esc_attr( $doc->id ); ?>" title="<?php esc_attr_e( 'Delete', 'eprocurement' ); ?>">
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="eproc-empty-state">
                            <p><?php esc_html_e( 'No SCM documents uploaded yet.', 'eprocurement' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {

    // Save section title
    $('#eproc-save-title').on('click', function() {
        var title = $('#eproc-compliance-title').val().trim();
        if (!title) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text(eprocAdmin.strings.saving);

        $.post(eprocAdmin.ajaxUrl, {
            action:                   'eproc_save_settings',
            nonce:                    eprocAdmin.nonce,
            compliance_section_title: title,
            cloud_provider:           '',
            closed_bid_retention_days: '',
            frontend_page_slug:       ''
        }, function(response) {
            if (response.success) {
                $('#eproc-compliance-notices').html(
                    '<div class="eproc-notice success"><p><?php echo esc_js( __( 'Section title updated.', 'eprocurement' ) ); ?></p></div>'
                );
            } else {
                $('#eproc-compliance-notices').html(
                    '<div class="eproc-notice error"><p>' + (response.data.message || eprocAdmin.strings.error) + '</p></div>'
                );
            }
        }).always(function() {
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save', 'eprocurement' ) ); ?>');
        });
    });

    // Upload SCM document
    $('#eproc-compliance-upload-form').on('submit', function(e) {
        e.preventDefault();

        var $btn      = $('#eproc-comp-upload-btn');
        var $progress = $('#eproc-comp-progress');
        var $bar      = $('#eproc-comp-progress-bar');
        var $text     = $('#eproc-comp-progress-text');
        var fileInput = document.getElementById('eproc-comp-file');

        if (!fileInput.files.length) return;

        var formData = new FormData();
        formData.append('action', 'eproc_upload_compliance_doc');
        formData.append('nonce', eprocAdmin.nonce);
        formData.append('file', fileInput.files[0]);
        formData.append('label', $('#eproc-comp-label').val());
        formData.append('description', $('#eproc-comp-description').val());

        $btn.prop('disabled', true);
        $progress.show();
        $text.text(eprocAdmin.strings.uploading);

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
                if (response.success) {
                    $text.text(eprocAdmin.strings.upload_success);
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    $text.text(response.data.message || eprocAdmin.strings.error);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $text.text(eprocAdmin.strings.error);
                $btn.prop('disabled', false);
            }
        });
    });

    // Delete SCM document
    $(document).on('click', '.eproc-delete-compliance', function() {
        var $btn = $(this);
        var id   = $btn.data('id');
        var $row = $btn.closest('tr');

        if (!confirm(eprocAdmin.strings.confirm_delete)) return;

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_delete_compliance_doc',
            nonce:  eprocAdmin.nonce,
            id:     id
        }, function(response) {
            if (response.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(response.data.message || eprocAdmin.strings.error);
            }
        }).fail(function() {
            alert(eprocAdmin.strings.error);
        });
    });

    // Sortable rows (if jQuery UI Sortable is available)
    if ($.fn.sortable) {
        $('#eproc-compliance-list').sortable({
            handle: '.eproc-sort-handle',
            axis:   'y',
            cursor: 'grabbing',
            update: function() {
                var order = [];
                $('#eproc-compliance-list tr').each(function() {
                    order.push($(this).data('id'));
                });

                $.post(eprocAdmin.ajaxUrl, {
                    action: 'eproc_save_settings',
                    nonce:  eprocAdmin.nonce,
                    compliance_order: order
                });
            }
        });
    }
});
</script>
