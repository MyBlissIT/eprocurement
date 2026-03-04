<?php
/**
 * Bid Document list partial.
 *
 * Displays all bids in a filterable, paginated table.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$documents = new Eprocurement_Documents();
$contacts  = new Eprocurement_Contact_Persons();

// Category (set by parent renderer or default to 'bid')
$eproc_category    = $eproc_category ?? 'bid';
$current_page_slug = sanitize_text_field( $_GET['page'] ?? 'eprocurement-bids' );

$category_labels = [
    'bid'               => __( 'Bid Documents', 'eprocurement' ),
    'briefing_register' => __( 'Briefing Register', 'eprocurement' ),
    'closing_register'  => __( 'Closing Register', 'eprocurement' ),
    'appointments'      => __( 'Appointments', 'eprocurement' ),
];

// Filters
$current_status = sanitize_text_field( $_GET['status'] ?? '' );
$search         = sanitize_text_field( $_GET['s'] ?? '' );
$paged          = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page       = 20;

$result = $documents->list( [
    'status'              => $current_status,
    'search'              => $search,
    'category'            => $eproc_category,
    'per_page'            => $per_page,
    'page'                => $paged,
    'include_all_statuses' => true,
    'include_all_categories' => false,
    'orderby'             => 'created_at',
    'order'               => 'DESC',
] );

$items       = $result['items'];
$total       = $result['total'];
$total_pages = $result['pages'];

$statuses = [ 'draft', 'open', 'closed', 'cancelled', 'archived' ];
$base_url = admin_url( 'admin.php?page=' . $current_page_slug );
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1><?php echo esc_html( $category_labels[ $eproc_category ] ?? __( 'Bid Documents', 'eprocurement' ) ); ?></h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $current_page_slug . '&action=new' ) ); ?>" class="button-primary">
            + <?php esc_html_e( 'Add New', 'eprocurement' ); ?>
        </a>
    </div>

    <?php $is_regular_bid = ( $eproc_category === 'bid' ); ?>

    <!-- Filter Bar -->
    <div class="eproc-filter-bar">
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="eproc-flex-row">
            <input type="hidden" name="page" value="<?php echo esc_attr( $current_page_slug ); ?>">
            <?php if ( $is_regular_bid ) : ?>
                <select name="status">
                    <option value=""><?php esc_html_e( 'All Statuses', 'eprocurement' ); ?></option>
                    <?php foreach ( $statuses as $status ) : ?>
                        <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $current_status, $status ); ?>>
                            <?php echo esc_html( ucfirst( $status ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'eprocurement' ); ?>">
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'eprocurement' ); ?></button>
            <?php if ( $current_status || $search ) : ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'eprocurement' ); ?></a>
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
        <table class="wp-list-table widefat">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Bid Number', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Title', 'eprocurement' ); ?></th>
                    <?php if ( $is_regular_bid ) : ?>
                        <th scope="col"><?php esc_html_e( 'Status', 'eprocurement' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'SCM Contact', 'eprocurement' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Closing Date', 'eprocurement' ); ?></th>
                    <?php endif; ?>
                    <th scope="col"><?php esc_html_e( 'Created', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Actions', 'eprocurement' ); ?></th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if ( ! empty( $items ) ) : ?>
                    <?php foreach ( $items as $bid ) : ?>
                        <?php
                        $scm_contact = $is_regular_bid && $bid->scm_contact_id ? $contacts->get( (int) $bid->scm_contact_id ) : null;
                        $edit_url    = admin_url( 'admin.php?page=' . $current_page_slug . '&action=edit&id=' . absint( $bid->id ) );
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
                            <?php if ( $is_regular_bid ) : ?>
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
                            <?php endif; ?>
                            <td><?php echo esc_html( wp_date( 'j M Y', strtotime( $bid->created_at ) ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'Edit', 'eprocurement' ); ?>">
                                    <span class="dashicons dashicons-edit" style="font-size:14px;line-height:1.8;"></span>
                                </a>
                                <?php if ( current_user_can( 'eproc_delete_bids' ) ) : ?>
                                    <button type="button" class="button button-small eproc-btn-danger eproc-delete-bid" data-id="<?php echo esc_attr( $bid->id ); ?>" title="<?php esc_attr_e( 'Delete', 'eprocurement' ); ?>">
                                        <span class="dashicons dashicons-trash" style="font-size:14px;line-height:1.8;"></span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="<?php echo $is_regular_bid ? '7' : '4'; ?>">
                            <div class="eproc-empty-state">
                                <p><?php esc_html_e( 'No entries found.', 'eprocurement' ); ?></p>
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
        if ( $current_status ) { $page_args['status'] = $current_status; }
        if ( $search ) { $page_args['s'] = $search; }
        ?>
        <div class="eproc-pagination">
            <?php if ( $paged > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => 1 ] ), $base_url ) ); ?>">&laquo;</a>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $paged - 1 ] ), $base_url ) ); ?>">&lsaquo;</a>
            <?php endif; ?>

            <?php
            $start = max( 1, $paged - 2 );
            $end   = min( $total_pages, $paged + 2 );
            for ( $i = $start; $i <= $end; $i++ ) :
            ?>
                <?php if ( $i === $paged ) : ?>
                    <span class="current"><?php echo esc_html( $i ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $i ] ), $base_url ) ); ?>"><?php echo esc_html( $i ); ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ( $paged < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $paged + 1 ] ), $base_url ) ); ?>">&rsaquo;</a>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $page_args, [ 'paged' => $total_pages ] ), $base_url ) ); ?>">&raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    $('.eproc-delete-bid').on('click', function() {
        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        if ( ! confirm(eprocAdmin.strings.confirm_delete) ) {
            return;
        }

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_delete_bid',
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
});
</script>
