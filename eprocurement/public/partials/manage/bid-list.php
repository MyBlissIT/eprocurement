<?php
/**
 * Frontend Admin — Bid Document list partial.
 *
 * Displays all bids in a filterable, paginated table within the
 * frontend manage panel at /tenders/manage/bids/.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$documents = new Eprocurement_Documents();
$contacts  = new Eprocurement_Contact_Persons();

// URL bases
$slug         = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base  = home_url( "/{$slug}/manage" );
$bids_url     = $manage_base . '/bids/';

// Category (set by router in class-frontend-admin.php, defaults to 'bid')
$eproc_category = $eproc_category ?? 'bid';

$category_labels = [
    'bid'               => __( 'Bid Documents', 'eprocurement' ),
    'briefing_register' => __( 'Briefing Register', 'eprocurement' ),
    'closing_register'  => __( 'Closing Register', 'eprocurement' ),
    'appointments'      => __( 'Appointments', 'eprocurement' ),
];

// Build base URL for this category
$base_url = $bids_url;
if ( $eproc_category !== 'bid' ) {
    $base_url = add_query_arg( 'category', $eproc_category, $bids_url );
}

// Filters
$current_status = sanitize_text_field( $_GET['status'] ?? '' );
$search         = sanitize_text_field( $_GET['s'] ?? '' );
$paged          = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page       = 20;

$result = $documents->list( [
    'status'                 => $current_status,
    'search'                 => $search,
    'category'               => $eproc_category,
    'per_page'               => $per_page,
    'page'                   => $paged,
    'include_all_statuses'   => true,
    'include_all_categories' => false,
    'orderby'                => 'created_at',
    'order'                  => 'DESC',
] );

$items       = $result['items'];
$total       = $result['total'];
$total_pages = $result['pages'];

$statuses = [ 'draft', 'open', 'closed', 'cancelled', 'archived' ];
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1><?php echo esc_html( $category_labels[ $eproc_category ] ?? __( 'Bid Documents', 'eprocurement' ) ); ?></h1>
        <?php if ( current_user_can( 'eproc_create_bids' ) ) : ?>
            <a href="<?php echo esc_url( $manage_base . '/bids/?action=new' . ( $eproc_category !== 'bid' ? '&category=' . urlencode( $eproc_category ) : '' ) ); ?>" class="eproc-btn eproc-btn-primary">
                + <?php esc_html_e( 'Add New', 'eprocurement' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Bar -->
    <div class="eproc-filter-bar">
        <form method="get" action="<?php echo esc_url( $bids_url ); ?>" class="eproc-flex-row">
            <?php if ( $eproc_category !== 'bid' ) : ?>
                <input type="hidden" name="category" value="<?php echo esc_attr( $eproc_category ); ?>">
            <?php endif; ?>
            <select name="status" class="eproc-select">
                <option value=""><?php esc_html_e( 'All Statuses', 'eprocurement' ); ?></option>
                <?php foreach ( $statuses as $status ) : ?>
                    <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $current_status, $status ); ?>>
                        <?php echo esc_html( ucfirst( $status ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="s" class="eproc-input" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search bids...', 'eprocurement' ); ?>">
            <button type="submit" class="eproc-btn eproc-btn-primary"><?php esc_html_e( 'Filter', 'eprocurement' ); ?></button>
            <?php if ( $current_status || $search ) : ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="eproc-btn"><?php esc_html_e( 'Clear', 'eprocurement' ); ?></a>
            <?php endif; ?>
            <span class="eproc-result-count">
                <?php
                printf(
                    esc_html( _n( '%s item', '%s items', $total, 'eprocurement' ) ),
                    esc_html( number_format_i18n( $total ) )
                );
                ?>
            </span>
        </form>
    </div>

    <!-- Bids Table -->
    <div class="eproc-card" style="padding:0;">
        <table class="eproc-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Bid Number', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Title', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Status', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'SCM Contact', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Closing Date', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Actions', 'eprocurement' ); ?></th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if ( ! empty( $items ) ) : ?>
                    <?php foreach ( $items as $bid ) : ?>
                        <?php
                        $scm_contact = $bid->scm_contact_id ? $contacts->get( (int) $bid->scm_contact_id ) : null;
                        $edit_url    = $manage_base . '/bids/?action=edit&id=' . absint( $bid->id );
                        if ( $eproc_category !== 'bid' ) {
                            $edit_url = add_query_arg( 'category', $eproc_category, $edit_url );
                        }
                        ?>
                        <tr data-id="<?php echo esc_attr( $bid->id ); ?>">
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>">
                                    <strong><?php echo esc_html( $bid->bid_number ); ?></strong>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $bid->title ); ?></a>
                            </td>
                            <td>
                                <span class="eproc-status-badge eproc-status-<?php echo esc_attr( $bid->status ); ?>">
                                    <?php echo esc_html( ucfirst( $bid->status ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $scm_contact ? $scm_contact->name : '—' ); ?></td>
                            <td>
                                <?php
                                if ( $bid->closing_date ) {
                                    echo esc_html( wp_date( 'j M Y, H:i', strtotime( $bid->closing_date ) ) );
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="eproc-btn eproc-btn-sm" title="<?php esc_attr_e( 'Edit', 'eprocurement' ); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <?php if ( current_user_can( 'eproc_delete_bids' ) ) : ?>
                                    <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-danger eproc-delete-bid" data-id="<?php echo esc_attr( $bid->id ); ?>" title="<?php esc_attr_e( 'Delete', 'eprocurement' ); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6">
                            <div class="eproc-empty-state">
                                <p><?php esc_html_e( 'No bids found.', 'eprocurement' ); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <?php
        $page_args = [];
        if ( $eproc_category !== 'bid' ) { $page_args['category'] = $eproc_category; }
        if ( $current_status ) { $page_args['status'] = $current_status; }
        if ( $search ) { $page_args['s'] = $search; }
        ?>
        <div class="eproc-pagination">
            <?php if ( $paged > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => 1 ] ), $bids_url ) ); ?>">&laquo;</a>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $paged - 1 ] ), $bids_url ) ); ?>">&lsaquo;</a>
            <?php endif; ?>

            <?php
            $start = max( 1, $paged - 2 );
            $end   = min( $total_pages, $paged + 2 );
            for ( $i = $start; $i <= $end; $i++ ) :
            ?>
                <?php if ( $i === $paged ) : ?>
                    <span class="current"><?php echo esc_html( $i ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $i ] ), $bids_url ) ); ?>"><?php echo esc_html( $i ); ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ( $paged < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $paged + 1 ] ), $bids_url ) ); ?>">&rsaquo;</a>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $total_pages ] ), $bids_url ) ); ?>">&raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof eprocManage === 'undefined') {
        return;
    }

    document.querySelectorAll('.eproc-delete-bid').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id  = this.getAttribute('data-id');
            var row = this.closest('tr');

            if ( ! window.confirm(eprocManage.strings.confirm_delete) ) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'eproc_delete_bid');
            formData.append('nonce', eprocManage.ajaxNonce);
            formData.append('id', id);

            fetch(eprocManage.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(function() { row.remove(); }, 300);
                } else {
                    alert((data.data && data.data.message) || eprocManage.strings.error);
                }
            })
            .catch(function() {
                alert(eprocManage.strings.error);
            });
        });
    });
});
</script>
