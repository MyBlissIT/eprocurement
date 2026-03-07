<?php
/**
 * Contact Persons management partial.
 *
 * Displays a table of contact persons with add/edit modal.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$contacts_handler = new Eprocurement_Contact_Persons();
$all_contacts     = $contacts_handler->get_all();

// Departments list (managed option)
$departments_json = get_option( 'eprocurement_departments', '[]' );
$departments      = json_decode( $departments_json, true );
if ( ! is_array( $departments ) ) {
    $departments = [];
}

// Unique departments from existing contacts (in case some were added as free text before)
$existing_depts = array_filter( array_unique( array_column( array_map( function( $c ) { return (array) $c; }, $all_contacts ), 'department' ) ) );
$departments = array_values( array_unique( array_merge( $departments, $existing_depts ) ) );
sort( $departments );

// Staff users for the linking dropdown
$staff_users = get_users( [
    'role__in' => [
        'administrator',
        'editor',
        'eprocurement_scm_manager',
        'eprocurement_scm_official',
        'eprocurement_unit_manager',
    ],
    'orderby'  => 'display_name',
    'order'    => 'ASC',
] );
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1><?php esc_html_e( 'Contact Persons Directory', 'eprocurement' ); ?></h1>
        <button type="button" class="button-primary" id="eproc-add-contact">
            + <?php esc_html_e( 'Add Contact Person', 'eprocurement' ); ?>
        </button>
    </div>

    <div id="eproc-contact-notices"></div>

    <!-- Search & Filter Bar -->
    <div class="eproc-filter-bar">
        <div class="eproc-flex-row">
            <input type="search" id="eproc-contact-search" placeholder="<?php esc_attr_e( 'Search by name, email, phone, department...', 'eprocurement' ); ?>">
            <select id="eproc-contact-dept-filter">
                <option value=""><?php esc_html_e( 'All Departments', 'eprocurement' ); ?></option>
                <?php foreach ( $departments as $dept ) : ?>
                    <option value="<?php echo esc_attr( $dept ); ?>"><?php echo esc_html( $dept ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Contacts Table -->
    <div class="eproc-card" style="padding:0;">
        <table class="wp-list-table widefat" id="eproc-contacts-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Name', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Type', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Phone', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Email', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Department', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'WP User', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Actions', 'eprocurement' ); ?></th>
                </tr>
            </thead>
            <tbody id="eproc-contacts-list">
                <?php if ( ! empty( $all_contacts ) ) : ?>
                    <?php foreach ( $all_contacts as $contact ) : ?>
                        <?php $linked_user = $contact->user_id ? get_userdata( (int) $contact->user_id ) : null; ?>
                        <tr data-id="<?php echo esc_attr( $contact->id ); ?>"
                            data-name="<?php echo esc_attr( $contact->name ); ?>"
                            data-type="<?php echo esc_attr( $contact->type ); ?>"
                            data-phone="<?php echo esc_attr( $contact->phone ); ?>"
                            data-email="<?php echo esc_attr( $contact->email ); ?>"
                            data-department="<?php echo esc_attr( $contact->department ); ?>"
                            data-user-id="<?php echo esc_attr( $contact->user_id ); ?>">
                            <td><strong><?php echo esc_html( $contact->name ); ?></strong></td>
                            <td>
                                <span class="eproc-badge <?php echo esc_attr( $contact->type ); ?>">
                                    <?php echo esc_html( strtoupper( $contact->type ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( $contact->phone ) : ?>
                                    <a href="tel:<?php echo esc_attr( $contact->phone ); ?>"><?php echo esc_html( $contact->phone ); ?></a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $contact->email ); ?></td>
                            <td><?php echo esc_html( $contact->department ?: '—' ); ?></td>
                            <td>
                                <?php if ( $linked_user ) : ?>
                                    <span class="eproc-badge verified"><?php esc_html_e( 'Linked', 'eprocurement' ); ?></span>
                                    <?php echo esc_html( $linked_user->display_name ); ?>
                                <?php else : ?>
                                    <span class="eproc-badge unverified"><?php esc_html_e( 'Not linked', 'eprocurement' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small eproc-edit-contact" title="<?php esc_attr_e( 'Edit', 'eprocurement' ); ?>">
                                    <span class="dashicons dashicons-edit" style="font-size:14px;line-height:1.8;"></span>
                                </button>
                                <button type="button" class="button button-small eproc-btn-danger eproc-delete-contact" data-id="<?php echo esc_attr( $contact->id ); ?>" title="<?php esc_attr_e( 'Delete', 'eprocurement' ); ?>">
                                    <span class="dashicons dashicons-trash" style="font-size:14px;line-height:1.8;"></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="eproc-no-items">
                        <td colspan="7">
                            <div class="eproc-empty-state">
                                <p><?php esc_html_e( 'No contact persons found. Click "Add Contact Person" to create one.', 'eprocurement' ); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Contact Modal -->
<div id="eproc-contact-modal" style="display:none;" role="dialog" aria-labelledby="eproc-modal-title" aria-modal="true">
    <div class="eproc-modal-overlay active">
        <div class="eproc-modal">
            <div class="eproc-modal-header">
                <h3 id="eproc-modal-title"><?php esc_html_e( 'Add Contact Person', 'eprocurement' ); ?></h3>
                <button type="button" class="eproc-modal-close">&times;</button>
            </div>
            <form id="eproc-contact-form">
                <?php wp_nonce_field( 'eproc_admin_nonce', 'eproc_contact_nonce' ); ?>
                <input type="hidden" id="eproc-contact-id" name="id" value="0">

                <div class="eproc-modal-body">
                    <div class="eproc-form-group">
                        <label for="eproc-contact-name"><?php esc_html_e( 'Full Name', 'eprocurement' ); ?> *</label>
                        <input type="text" id="eproc-contact-name" name="name" required>
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-contact-type"><?php esc_html_e( 'Type', 'eprocurement' ); ?></label>
                        <select id="eproc-contact-type" name="type">
                            <option value="scm"><?php esc_html_e( 'SCM', 'eprocurement' ); ?></option>
                            <option value="technical"><?php esc_html_e( 'Technical', 'eprocurement' ); ?></option>
                        </select>
                    </div>
                    <div class="eproc-grid-2">
                        <div class="eproc-form-group">
                            <label for="eproc-contact-phone"><?php esc_html_e( 'Phone', 'eprocurement' ); ?></label>
                            <input type="tel" id="eproc-contact-phone" name="phone">
                        </div>
                        <div class="eproc-form-group">
                            <label for="eproc-contact-email"><?php esc_html_e( 'Email', 'eprocurement' ); ?> *</label>
                            <input type="email" id="eproc-contact-email" name="email" required>
                        </div>
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-contact-department"><?php esc_html_e( 'Department', 'eprocurement' ); ?></label>
                        <select id="eproc-contact-department" name="department">
                            <option value=""><?php esc_html_e( '-- Select Department --', 'eprocurement' ); ?></option>
                            <?php foreach ( $departments as $dept ) : ?>
                                <option value="<?php echo esc_attr( $dept ); ?>"><?php echo esc_html( $dept ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( is_super_admin() || current_user_can( 'eproc_manage_contacts' ) ) : ?>
                            <div class="eproc-add-department-row" style="margin-top:6px;">
                                <input type="text" id="eproc-new-department" placeholder="<?php esc_attr_e( 'New department name', 'eprocurement' ); ?>" style="display:inline-block;width:auto;">
                                <button type="button" class="button button-small" id="eproc-add-department-btn"><?php esc_html_e( 'Add', 'eprocurement' ); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-contact-user"><?php esc_html_e( 'Linked WP User', 'eprocurement' ); ?></label>
                        <select id="eproc-contact-user" name="user_id">
                            <option value=""><?php esc_html_e( '-- None --', 'eprocurement' ); ?></option>
                            <?php foreach ( $staff_users as $user ) : ?>
                                <option value="<?php echo esc_attr( $user->ID ); ?>">
                                    <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="description"><?php esc_html_e( 'Link to a WP user so they can reply to queries.', 'eprocurement' ); ?></span>
                    </div>
                </div>

                <div class="eproc-modal-footer">
                    <button type="button" class="button eproc-modal-close"><?php esc_html_e( 'Cancel', 'eprocurement' ); ?></button>
                    <button type="submit" class="button-primary" id="eproc-save-contact-btn"><?php esc_html_e( 'Save Contact', 'eprocurement' ); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var $modal = $('#eproc-contact-modal');

    // Open modal — Add
    $('#eproc-add-contact').on('click', function() {
        $('#eproc-modal-title').text('<?php echo esc_js( __( 'Add Contact Person', 'eprocurement' ) ); ?>');
        $('#eproc-contact-id').val(0);
        $('#eproc-contact-form')[0].reset();
        $modal.show();
    });

    // Open modal — Edit
    $(document).on('click', '.eproc-edit-contact', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $row = $(this).closest('tr');
        $('#eproc-modal-title').text('<?php echo esc_js( __( 'Edit Contact Person', 'eprocurement' ) ); ?>');
        $('#eproc-contact-id').val($row.data('id'));
        $('#eproc-contact-name').val($row.data('name'));
        $('#eproc-contact-type').val($row.data('type'));
        $('#eproc-contact-phone').val($row.data('phone'));
        $('#eproc-contact-email').val($row.data('email'));
        $('#eproc-contact-department').val($row.data('department'));
        $('#eproc-contact-user').val($row.data('user-id') || '');
        $modal.show();
    });

    // Close modal — all close triggers
    function closeModal() { $modal.hide(); }
    $modal.on('click', '.eproc-modal-close', function(e) {
        e.preventDefault();
        closeModal();
    });
    $modal.on('click', '.eproc-modal-overlay', function(e) {
        if (e.target === this) { closeModal(); }
    });

    // ESC key closes modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') { closeModal(); }
    });

    // Save contact
    $('#eproc-contact-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#eproc-save-contact-btn');
        $btn.prop('disabled', true).text(eprocAdmin.strings.saving);

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_save_contact', nonce: eprocAdmin.nonce,
            id: $('#eproc-contact-id').val(), name: $('#eproc-contact-name').val(),
            type: $('#eproc-contact-type').val(), phone: $('#eproc-contact-phone').val(),
            email: $('#eproc-contact-email').val(), department: $('#eproc-contact-department').val(),
            user_id: $('#eproc-contact-user').val()
        }, function(r) {
            if (r.success) { location.reload(); }
            else { alert(r.data.message || eprocAdmin.strings.error); $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Contact', 'eprocurement' ) ); ?>'); }
        }).fail(function() { alert(eprocAdmin.strings.error); $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Contact', 'eprocurement' ) ); ?>'); });
    });

    // Delete contact
    $(document).on('click', '.eproc-delete-contact', function(e) {
        e.preventDefault();
        var id = $(this).data('id'), $row = $(this).closest('tr');
        if (!confirm(eprocAdmin.strings.confirm_delete)) return;
        $.post(eprocAdmin.ajaxUrl, { action: 'eproc_delete_contact', nonce: eprocAdmin.nonce, id: id }, function(r) {
            if (r.success) { $row.fadeOut(300, function() { $(this).remove(); }); }
            else { alert(r.data.message || eprocAdmin.strings.error); }
        }).fail(function() { alert(eprocAdmin.strings.error); });
    });

    // Search contacts
    $('#eproc-contact-search').on('input', function() {
        var q = $(this).val().toLowerCase();
        var dept = $('#eproc-contact-dept-filter').val().toLowerCase();
        filterContacts(q, dept);
    });

    // Department filter
    $('#eproc-contact-dept-filter').on('change', function() {
        var q = $('#eproc-contact-search').val().toLowerCase();
        var dept = $(this).val().toLowerCase();
        filterContacts(q, dept);
    });

    function filterContacts(search, dept) {
        $('#eproc-contacts-list tr').not('.eproc-no-items').each(function() {
            var $row = $(this);
            var name = ($row.data('name') || '').toString().toLowerCase();
            var email = ($row.data('email') || '').toString().toLowerCase();
            var phone = ($row.data('phone') || '').toString().toLowerCase();
            var rowDept = ($row.data('department') || '').toString().toLowerCase();

            var matchesSearch = !search || name.indexOf(search) > -1 || email.indexOf(search) > -1 || phone.indexOf(search) > -1 || rowDept.indexOf(search) > -1;
            var matchesDept = !dept || rowDept === dept;

            $row.toggle(matchesSearch && matchesDept);
        });
    }

    // Add new department
    $('#eproc-add-department-btn').on('click', function() {
        var newDept = $('#eproc-new-department').val().trim();
        if (!newDept) return;

        // Add to select if not already present
        var exists = false;
        $('#eproc-contact-department option').each(function() {
            if ($(this).val().toLowerCase() === newDept.toLowerCase()) { exists = true; }
        });
        if (!exists) {
            $('<option>').val(newDept).text(newDept).appendTo('#eproc-contact-department');
            // Also add to the page filter
            $('<option>').val(newDept).text(newDept).appendTo('#eproc-contact-dept-filter');
        }
        $('#eproc-contact-department').val(newDept);
        $('#eproc-new-department').val('');

        // Persist to server
        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_add_department',
            nonce: eprocAdmin.nonce,
            department: newDept
        });
    });
});
</script>
