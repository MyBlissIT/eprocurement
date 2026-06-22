<?php
/**
 * Frontend Admin — SCM Documents.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug        = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base = home_url( "/{$slug}/manage" );
$compliance  = new Eprocurement_Compliance_Docs();
$all_docs    = $compliance->get_all();
$section_title = Eprocurement_Compliance_Docs::get_section_title();
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1><?php echo esc_html( $section_title ); ?></h1>
        <?php if ( current_user_can( 'eproc_manage_compliance' ) ) : ?>
            <button type="button" class="eproc-btn eproc-btn-primary" id="eproc-add-scm-doc-btn">
                <?php esc_html_e( 'Upload Document', 'eprocurement' ); ?>
            </button>
        <?php endif; ?>
    </div>

    <!-- Upload Form (hidden by default) -->
    <div class="eproc-card" id="eproc-scm-upload-form" style="display:none;">
        <div class="eproc-card-header">
            <h2><?php esc_html_e( 'Upload SCM Document', 'eprocurement' ); ?></h2>
        </div>
        <div class="eproc-card-body">
            <form id="eproc-scm-doc-form">
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Label', 'eprocurement' ); ?></label>
                        <input type="text" name="label" class="eproc-input" required />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'File', 'eprocurement' ); ?></label>
                        <input type="file" name="file" class="eproc-input" required />
                    </div>
                </div>
                <div class="eproc-form-group">
                    <label class="eproc-label"><?php esc_html_e( 'Description', 'eprocurement' ); ?></label>
                    <textarea name="description" class="eproc-input" rows="3"></textarea>
                </div>
                <div class="eproc-form-actions">
                    <button type="submit" class="eproc-btn eproc-btn-primary"><?php esc_html_e( 'Upload', 'eprocurement' ); ?></button>
                    <button type="button" class="eproc-btn" id="eproc-cancel-scm-upload"><?php esc_html_e( 'Cancel', 'eprocurement' ); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Documents Table -->
    <div class="eproc-card" style="padding:0;">
        <?php if ( ! empty( $all_docs ) ) : ?>
            <table class="eproc-table" id="eproc-scm-docs-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Label', 'eprocurement' ); ?></th>
                        <th><?php esc_html_e( 'File', 'eprocurement' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'eprocurement' ); ?></th>
                        <th style="width:120px;"><?php esc_html_e( 'Actions', 'eprocurement' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $all_docs as $doc ) : ?>
                        <tr data-id="<?php echo absint( $doc->id ); ?>">
                            <td><strong><?php echo esc_html( $doc->label ); ?></strong></td>
                            <td><?php echo esc_html( $doc->file_name ); ?></td>
                            <td><?php echo esc_html( $doc->description ?? '' ); ?></td>
                            <td>
                                <button type="button" class="eproc-btn eproc-btn-danger eproc-btn-sm eproc-delete-scm-doc" data-id="<?php echo absint( $doc->id ); ?>">
                                    <?php esc_html_e( 'Delete', 'eprocurement' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="eproc-empty-state">
                <p><?php esc_html_e( 'No documents uploaded yet.', 'eprocurement' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var uploadForm = document.getElementById('eproc-scm-upload-form');
    var addBtn = document.getElementById('eproc-add-scm-doc-btn');
    var cancelBtn = document.getElementById('eproc-cancel-scm-upload');

    addBtn.addEventListener('click', function() {
        uploadForm.style.display = 'block';
        addBtn.style.display = 'none';
    });

    cancelBtn.addEventListener('click', function() {
        uploadForm.style.display = 'none';
        addBtn.style.display = '';
    });

    // Upload form
    document.getElementById('eproc-scm-doc-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        var form = this;
        var fd = new FormData(form);
        var btn = form.querySelector('button[type="submit"]');
        eprocSetLoading(btn, true);

        try {
            await eprocAPI.upload('admin/scm-docs', fd);
            eprocToast('<?php echo esc_js( __( 'Document uploaded.', 'eprocurement' ) ); ?>');
            location.reload();
        } catch (err) {
            eprocToast(err.message, 'error');
        } finally {
            eprocSetLoading(btn, false);
        }
    });

    // Delete
    document.querySelectorAll('.eproc-delete-scm-doc').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!eprocConfirm()) return;
            var id = this.dataset.id;
            try {
                await eprocAPI.del('admin/scm-docs/' + id);
                eprocToast('<?php echo esc_js( __( 'Document deleted.', 'eprocurement' ) ); ?>');
                this.closest('tr').remove();
            } catch (err) {
                eprocToast(err.message, 'error');
            }
        });
    });
});
</script>
