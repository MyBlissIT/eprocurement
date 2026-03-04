<?php
/**
 * Frontend Admin — User Management (Super Admin only).
 *
 * Allows Super Admin to create, edit, and delete eProcurement staff users
 * (SCM Manager, SCM Official, Unit Manager) without accessing wp-admin.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug        = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base = home_url( "/{$slug}/manage" );

// Fetch staff users
$eproc_roles = [
    'eprocurement_scm_manager',
    'eprocurement_scm_official',
    'eprocurement_unit_manager',
];

$role_labels = [
    'eprocurement_scm_manager'  => __( 'SCM Manager', 'eprocurement' ),
    'eprocurement_scm_official' => __( 'SCM Official', 'eprocurement' ),
    'eprocurement_unit_manager' => __( 'Unit Manager', 'eprocurement' ),
];

$search = sanitize_text_field( $_GET['s'] ?? '' );
$args   = [
    'role__in'   => $eproc_roles,
    'number'     => 50,
    'orderby'    => 'display_name',
    'order'      => 'ASC',
];

if ( $search ) {
    $args['search']         = '*' . $search . '*';
    $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
}

$query = new WP_User_Query( $args );
$users = $query->get_results();
$total = $query->get_total();
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1>
            <?php esc_html_e( 'User Management', 'eprocurement' ); ?>
            <span class="eproc-result-count">(<?php echo esc_html( $total ); ?>)</span>
        </h1>
        <button type="button" class="eproc-btn eproc-btn-primary" id="eproc-add-user-btn">
            <?php esc_html_e( 'Add User', 'eprocurement' ); ?>
        </button>
    </div>

    <!-- Search Bar -->
    <div class="eproc-filter-bar">
        <form method="get" action="<?php echo esc_url( $manage_base . '/users/' ); ?>" style="display:flex;gap:12px;align-items:center;">
            <input type="text" name="s" class="eproc-input" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search users...', 'eprocurement' ); ?>" />
            <button type="submit" class="eproc-btn eproc-btn-sm"><?php esc_html_e( 'Search', 'eprocurement' ); ?></button>
        </form>
    </div>

    <!-- Add/Edit User Form (hidden by default) -->
    <div class="eproc-card" id="eproc-user-form-card" style="display:none;">
        <div class="eproc-card-header">
            <h2 id="eproc-user-form-title"><?php esc_html_e( 'Add New User', 'eprocurement' ); ?></h2>
        </div>
        <div class="eproc-card-body">
            <form id="eproc-user-form">
                <input type="hidden" name="user_id" value="0" />
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Display Name', 'eprocurement' ); ?></label>
                        <input type="text" name="display_name" class="eproc-input" required />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Email', 'eprocurement' ); ?></label>
                        <input type="email" name="email" class="eproc-input" required />
                    </div>
                </div>
                <div class="eproc-form-group">
                    <label class="eproc-label"><?php esc_html_e( 'Role', 'eprocurement' ); ?></label>
                    <select name="role" class="eproc-select" required>
                        <?php foreach ( $role_labels as $role_key => $role_label ) : ?>
                            <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="eproc-text-muted"><?php esc_html_e( 'A password reset email will be sent to the user.', 'eprocurement' ); ?></p>
                <div class="eproc-form-actions">
                    <button type="submit" class="eproc-btn eproc-btn-primary"><?php esc_html_e( 'Save', 'eprocurement' ); ?></button>
                    <button type="button" class="eproc-btn" id="eproc-cancel-user"><?php esc_html_e( 'Cancel', 'eprocurement' ); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="eproc-card" style="padding:0;">
        <table class="eproc-table" id="eproc-users-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'eprocurement' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'eprocurement' ); ?></th>
                    <th><?php esc_html_e( 'Role', 'eprocurement' ); ?></th>
                    <th><?php esc_html_e( 'Registered', 'eprocurement' ); ?></th>
                    <th style="width:120px;"><?php esc_html_e( 'Actions', 'eprocurement' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $users ) ) : ?>
                    <?php foreach ( $users as $user ) :
                        $user_role = array_values( array_intersect( $user->roles, $eproc_roles ) )[0] ?? '';
                    ?>
                        <tr data-id="<?php echo absint( $user->ID ); ?>" data-name="<?php echo esc_attr( $user->display_name ); ?>" data-email="<?php echo esc_attr( $user->user_email ); ?>" data-role="<?php echo esc_attr( $user_role ); ?>">
                            <td><strong><?php echo esc_html( $user->display_name ); ?></strong></td>
                            <td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
                            <td><?php echo esc_html( $role_labels[ $user_role ] ?? $user_role ); ?></td>
                            <td><?php echo esc_html( wp_date( 'j M Y', strtotime( $user->user_registered ) ) ); ?></td>
                            <td>
                                <button type="button" class="eproc-btn eproc-btn-sm eproc-edit-user"><?php esc_html_e( 'Edit', 'eprocurement' ); ?></button>
                                <button type="button" class="eproc-btn eproc-btn-danger eproc-btn-sm eproc-delete-user"><?php esc_html_e( 'Delete', 'eprocurement' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">
                            <div class="eproc-empty-state">
                                <p><?php esc_html_e( 'No staff users found.', 'eprocurement' ); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var formCard  = document.getElementById('eproc-user-form-card');
    var form      = document.getElementById('eproc-user-form');
    var addBtn    = document.getElementById('eproc-add-user-btn');
    var cancelBtn = document.getElementById('eproc-cancel-user');
    var title     = document.getElementById('eproc-user-form-title');

    addBtn.addEventListener('click', function() {
        form.reset();
        form.user_id.value = '0';
        form.email.readOnly = false;
        title.textContent = '<?php echo esc_js( __( 'Add New User', 'eprocurement' ) ); ?>';
        formCard.style.display = 'block';
        addBtn.style.display = 'none';
    });

    cancelBtn.addEventListener('click', function() {
        formCard.style.display = 'none';
        addBtn.style.display = '';
    });

    // Edit user — pre-fill form
    document.querySelectorAll('.eproc-edit-user').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = this.closest('tr');
            form.user_id.value      = row.dataset.id;
            form.display_name.value = row.dataset.name;
            form.email.value        = row.dataset.email;
            form.email.readOnly     = true;
            form.role.value         = row.dataset.role;
            title.textContent       = '<?php echo esc_js( __( 'Edit User', 'eprocurement' ) ); ?>';
            formCard.style.display  = 'block';
            addBtn.style.display    = 'none';
        });
    });

    // Save user
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        var userId = parseInt(form.user_id.value);
        var btn    = form.querySelector('button[type="submit"]');
        eprocSetLoading(btn, true);

        try {
            if (userId > 0) {
                await eprocAPI.patch('admin/users/' + userId, {
                    display_name: form.display_name.value,
                    role: form.role.value,
                });
            } else {
                await eprocAPI.post('admin/users', {
                    display_name: form.display_name.value,
                    email: form.email.value,
                    role: form.role.value,
                });
            }
            eprocToast('<?php echo esc_js( __( 'User saved.', 'eprocurement' ) ); ?>');
            location.reload();
        } catch (err) {
            eprocToast(err.message, 'error');
        } finally {
            eprocSetLoading(btn, false);
        }
    });

    // Delete user
    document.querySelectorAll('.eproc-delete-user').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!eprocConfirm('<?php echo esc_js( __( 'Are you sure you want to delete this user?', 'eprocurement' ) ); ?>')) return;
            var row = this.closest('tr');
            var userId = row.dataset.id;
            try {
                await eprocAPI.del('admin/users/' + userId);
                eprocToast('<?php echo esc_js( __( 'User deleted.', 'eprocurement' ) ); ?>');
                row.remove();
            } catch (err) {
                eprocToast(err.message, 'error');
            }
        });
    });
});
</script>
