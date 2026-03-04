<?php
/**
 * Frontend Admin — Contact Persons Directory partial.
 *
 * Displays a table of contact persons with search bar, department filter,
 * and add/edit modal. All CRUD operations go through the REST API
 * (eprocurement/v1/admin/contacts) via the eprocAPI helper.
 *
 * Adapted from admin/partials/contact-persons.php for the frontend
 * manage panel at /tenders/manage/contacts/.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug        = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base = home_url( "/{$slug}/manage" );

$contacts_handler = new Eprocurement_Contact_Persons();
$all_contacts     = $contacts_handler->get_all();

// Departments list (managed option)
$departments_json = get_option( 'eprocurement_departments', '[]' );
$departments      = json_decode( $departments_json, true );
if ( ! is_array( $departments ) ) {
    $departments = [];
}

// Merge in any existing departments from contact records
$existing_depts = array_filter( array_unique( array_column( array_map( function( $c ) { return (array) $c; }, $all_contacts ), 'department' ) ) );
$departments    = array_values( array_unique( array_merge( $departments, $existing_depts ) ) );
sort( $departments );

// Can the current user add departments?
$can_add_departments = is_super_admin() || current_user_can( 'eproc_manage_contacts' );
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1><?php esc_html_e( 'Contact Persons Directory', 'eprocurement' ); ?></h1>
        <button type="button" class="eproc-btn eproc-btn-primary" id="eproc-add-contact">
            + <?php esc_html_e( 'Add Contact Person', 'eprocurement' ); ?>
        </button>
    </div>

    <div id="eproc-contact-notices"></div>

    <!-- Search & Filter Bar -->
    <div class="eproc-filter-bar">
        <div class="eproc-flex-row">
            <input type="search" id="eproc-contact-search" class="eproc-input" placeholder="<?php esc_attr_e( 'Search by name, email, phone, department...', 'eprocurement' ); ?>">
            <select id="eproc-contact-dept-filter" class="eproc-select">
                <option value=""><?php esc_html_e( 'All Departments', 'eprocurement' ); ?></option>
                <?php foreach ( $departments as $dept ) : ?>
                    <option value="<?php echo esc_attr( $dept ); ?>"><?php echo esc_html( $dept ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Contacts Table -->
    <div class="eproc-card" style="padding:0;">
        <table class="eproc-table" id="eproc-contacts-table">
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
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $contact->email ); ?></td>
                            <td><?php echo esc_html( $contact->department ?: '&mdash;' ); ?></td>
                            <td>
                                <?php if ( $linked_user ) : ?>
                                    <span class="eproc-badge verified"><?php esc_html_e( 'Linked', 'eprocurement' ); ?></span>
                                    <?php echo esc_html( $linked_user->display_name ); ?>
                                <?php else : ?>
                                    <span class="eproc-badge unverified"><?php esc_html_e( 'Not linked', 'eprocurement' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="eproc-btn eproc-btn-sm eproc-edit-contact" title="<?php esc_attr_e( 'Edit', 'eprocurement' ); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-danger eproc-delete-contact" data-id="<?php echo esc_attr( $contact->id ); ?>" title="<?php esc_attr_e( 'Delete', 'eprocurement' ); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
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
<div id="eproc-contact-modal" style="display:none;">
    <div class="eproc-modal-overlay active">
        <div class="eproc-modal">
            <div class="eproc-modal-header">
                <h3 id="eproc-modal-title"><?php esc_html_e( 'Add Contact Person', 'eprocurement' ); ?></h3>
                <button type="button" class="eproc-modal-close">&times;</button>
            </div>
            <form id="eproc-contact-form">
                <input type="hidden" id="eproc-contact-id" name="id" value="0">

                <div class="eproc-modal-body">
                    <div class="eproc-form-group">
                        <label for="eproc-contact-name"><?php esc_html_e( 'Full Name', 'eprocurement' ); ?> *</label>
                        <input type="text" id="eproc-contact-name" name="name" class="eproc-input" required>
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-contact-type"><?php esc_html_e( 'Type', 'eprocurement' ); ?></label>
                        <select id="eproc-contact-type" name="type" class="eproc-select">
                            <option value="scm"><?php esc_html_e( 'SCM', 'eprocurement' ); ?></option>
                            <option value="technical"><?php esc_html_e( 'Technical', 'eprocurement' ); ?></option>
                        </select>
                    </div>
                    <div class="eproc-grid-2">
                        <div class="eproc-form-group">
                            <label for="eproc-contact-phone"><?php esc_html_e( 'Phone', 'eprocurement' ); ?></label>
                            <input type="tel" id="eproc-contact-phone" name="phone" class="eproc-input">
                        </div>
                        <div class="eproc-form-group">
                            <label for="eproc-contact-email"><?php esc_html_e( 'Email', 'eprocurement' ); ?> *</label>
                            <input type="email" id="eproc-contact-email" name="email" class="eproc-input" required>
                        </div>
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-contact-department"><?php esc_html_e( 'Department', 'eprocurement' ); ?></label>
                        <select id="eproc-contact-department" name="department" class="eproc-select">
                            <option value=""><?php esc_html_e( '-- Select Department --', 'eprocurement' ); ?></option>
                            <?php foreach ( $departments as $dept ) : ?>
                                <option value="<?php echo esc_attr( $dept ); ?>"><?php echo esc_html( $dept ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( $can_add_departments ) : ?>
                            <div class="eproc-add-department-row" style="margin-top:6px;display:flex;gap:6px;align-items:center;">
                                <input type="text" id="eproc-new-department" class="eproc-input" placeholder="<?php esc_attr_e( 'New department name', 'eprocurement' ); ?>" style="flex:1;">
                                <button type="button" class="eproc-btn eproc-btn-sm" id="eproc-add-department-btn"><?php esc_html_e( 'Add', 'eprocurement' ); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="eproc-form-hint">
                        <?php esc_html_e( 'A WordPress user account will be created automatically when adding a new contact.', 'eprocurement' ); ?>
                    </p>
                </div>

                <div class="eproc-modal-footer">
                    <button type="button" class="eproc-btn eproc-modal-close"><?php esc_html_e( 'Cancel', 'eprocurement' ); ?></button>
                    <button type="submit" class="eproc-btn eproc-btn-primary" id="eproc-save-contact-btn"><?php esc_html_e( 'Save Contact', 'eprocurement' ); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    if (typeof eprocAPI === 'undefined') return;

    var modal      = document.getElementById('eproc-contact-modal');
    var form       = document.getElementById('eproc-contact-form');
    var modalTitle = document.getElementById('eproc-modal-title');
    var contactId  = document.getElementById('eproc-contact-id');
    var saveBtn    = document.getElementById('eproc-save-contact-btn');
    var searchBox  = document.getElementById('eproc-contact-search');
    var deptFilter = document.getElementById('eproc-contact-dept-filter');

    var saveBtnText = '<?php echo esc_js( __( 'Save Contact', 'eprocurement' ) ); ?>';

    // =========================================================================
    // Modal open / close
    // =========================================================================

    function openModal() { modal.style.display = ''; }
    function closeModal() { modal.style.display = 'none'; }

    // Add Contact
    document.getElementById('eproc-add-contact').addEventListener('click', function() {
        modalTitle.textContent = '<?php echo esc_js( __( 'Add Contact Person', 'eprocurement' ) ); ?>';
        contactId.value = '0';
        form.reset();
        openModal();
    });

    // Edit Contact — delegated
    document.getElementById('eproc-contacts-list').addEventListener('click', function(e) {
        var btn = e.target.closest('.eproc-edit-contact');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var row = btn.closest('tr');
        modalTitle.textContent = '<?php echo esc_js( __( 'Edit Contact Person', 'eprocurement' ) ); ?>';
        contactId.value = row.dataset.id;
        document.getElementById('eproc-contact-name').value       = row.dataset.name || '';
        document.getElementById('eproc-contact-type').value       = row.dataset.type || 'scm';
        document.getElementById('eproc-contact-phone').value      = row.dataset.phone || '';
        document.getElementById('eproc-contact-email').value      = row.dataset.email || '';
        document.getElementById('eproc-contact-department').value = row.dataset.department || '';
        openModal();
    });

    // Close modal — X button, Cancel button, overlay click
    modal.addEventListener('click', function(e) {
        if (e.target.classList.contains('eproc-modal-close') || e.target.classList.contains('eproc-modal-overlay')) {
            e.preventDefault();
            closeModal();
        }
    });

    // Close modal — ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            closeModal();
        }
    });

    // =========================================================================
    // Save contact via REST API
    // =========================================================================

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var id = parseInt(contactId.value, 10) || 0;

        var data = {
            id:         id,
            name:       document.getElementById('eproc-contact-name').value.trim(),
            type:       document.getElementById('eproc-contact-type').value,
            phone:      document.getElementById('eproc-contact-phone').value.trim(),
            email:      document.getElementById('eproc-contact-email').value.trim(),
            department: document.getElementById('eproc-contact-department').value,
        };

        if (!data.name || !data.email) {
            eprocToast('<?php echo esc_js( __( 'Name and email are required.', 'eprocurement' ) ); ?>', 'error');
            return;
        }

        eprocSetLoading(saveBtn, true);

        eprocAPI.post('admin/contacts', data)
            .then(function() {
                eprocToast('<?php echo esc_js( __( 'Contact saved successfully.', 'eprocurement' ) ); ?>');
                closeModal();
                // Reload to reflect changes
                location.reload();
            })
            .catch(function(err) {
                eprocToast(err.message || '<?php echo esc_js( __( 'Failed to save contact.', 'eprocurement' ) ); ?>', 'error');
                eprocSetLoading(saveBtn, false);
            });
    });

    // =========================================================================
    // Delete contact via REST API
    // =========================================================================

    document.getElementById('eproc-contacts-list').addEventListener('click', function(e) {
        var btn = e.target.closest('.eproc-delete-contact');
        if (!btn) return;
        e.preventDefault();

        var id  = btn.dataset.id;
        var row = btn.closest('tr');

        if (!eprocConfirm('<?php echo esc_js( __( 'Are you sure you want to delete this contact person?', 'eprocurement' ) ); ?>')) {
            return;
        }

        eprocAPI.del('admin/contacts/' + id)
            .then(function() {
                eprocToast('<?php echo esc_js( __( 'Contact deleted.', 'eprocurement' ) ); ?>');
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function() { row.remove(); }, 300);
            })
            .catch(function(err) {
                eprocToast(err.message || '<?php echo esc_js( __( 'Failed to delete contact.', 'eprocurement' ) ); ?>', 'error');
            });
    });

    // =========================================================================
    // Search & Department filter
    // =========================================================================

    function filterContacts() {
        var search = (searchBox.value || '').toLowerCase();
        var dept   = (deptFilter.value || '').toLowerCase();
        var rows   = document.querySelectorAll('#eproc-contacts-list tr:not(.eproc-no-items)');

        rows.forEach(function(row) {
            var name     = (row.dataset.name || '').toLowerCase();
            var email    = (row.dataset.email || '').toLowerCase();
            var phone    = (row.dataset.phone || '').toLowerCase();
            var rowDept  = (row.dataset.department || '').toLowerCase();

            var matchesSearch = !search || name.indexOf(search) > -1 || email.indexOf(search) > -1 || phone.indexOf(search) > -1 || rowDept.indexOf(search) > -1;
            var matchesDept   = !dept || rowDept === dept;

            row.style.display = (matchesSearch && matchesDept) ? '' : 'none';
        });
    }

    searchBox.addEventListener('input', filterContacts);
    deptFilter.addEventListener('change', filterContacts);

    // =========================================================================
    // Add new department via REST API
    // =========================================================================

    var addDeptBtn = document.getElementById('eproc-add-department-btn');
    if (addDeptBtn) {
        addDeptBtn.addEventListener('click', function() {
            var input   = document.getElementById('eproc-new-department');
            var newDept = (input.value || '').trim();
            if (!newDept) return;

            var deptSelect  = document.getElementById('eproc-contact-department');
            var filterSelect = document.getElementById('eproc-contact-dept-filter');

            // Check if already exists in the dropdown
            var exists = false;
            Array.from(deptSelect.options).forEach(function(opt) {
                if (opt.value.toLowerCase() === newDept.toLowerCase()) {
                    exists = true;
                }
            });

            if (!exists) {
                // Add to modal department dropdown
                var opt1 = document.createElement('option');
                opt1.value = newDept;
                opt1.textContent = newDept;
                deptSelect.appendChild(opt1);

                // Add to page filter dropdown
                var opt2 = document.createElement('option');
                opt2.value = newDept;
                opt2.textContent = newDept;
                filterSelect.appendChild(opt2);
            }

            // Select the new department
            deptSelect.value = newDept;
            input.value = '';

            // Persist to server via REST API
            eprocAPI.post('admin/departments', { department: newDept })
                .then(function() {
                    eprocToast('<?php echo esc_js( __( 'Department added.', 'eprocurement' ) ); ?>');
                })
                .catch(function(err) {
                    eprocToast(err.message || '<?php echo esc_js( __( 'Failed to add department.', 'eprocurement' ) ); ?>', 'error');
                });
        });
    }

})();
</script>
