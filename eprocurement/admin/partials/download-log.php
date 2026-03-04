<?php
/**
 * Admin Download Log partial.
 *
 * Displays a searchable, filterable log of all file downloads
 * for audit purposes.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$downloads = new Eprocurement_Downloads();
$page      = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page  = 25;

$filter_bid  = absint( $_GET['bid_id'] ?? 0 );
$filter_from = sanitize_text_field( $_GET['date_from'] ?? '' );
$filter_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
$search      = sanitize_text_field( $_GET['s'] ?? '' );

$args = [
    'per_page'  => $per_page,
    'page'      => $page,
    'date_from' => $filter_from,
    'date_to'   => $filter_to,
    'search'    => $search,
];

$result      = $downloads->get_log( $filter_bid, $args );
$items       = $result['items'] ?? [];
$total       = $result['total'] ?? 0;
$total_pages = $per_page > 0 ? ceil( $total / $per_page ) : 1;

// Get all documents for filter dropdown
$documents_obj = new Eprocurement_Documents();
$all_bids      = $documents_obj->list( [ 'per_page' => 100, 'include_all_statuses' => true ] );

// Collect unique file names and user names for autocomplete
$autocomplete_items = [];
foreach ( $items as $dl ) {
    $sup = ! empty( $dl->supporting_doc_id ) ? Eprocurement_Database::get_by_id( 'supporting_docs', (int) $dl->supporting_doc_id ) : null;
    if ( $sup && $sup->file_name ) {
        $autocomplete_items[] = $sup->file_name;
    }
    if ( ! empty( $dl->display_name ) ) {
        $autocomplete_items[] = $dl->display_name;
    }
}
$autocomplete_items = array_values( array_unique( $autocomplete_items ) );
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1><?php esc_html_e( 'Download Log', 'eprocurement' ); ?></h1>
        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=eproc_export_downloads' ), 'eproc_admin_nonce', 'nonce' ) ); ?>" class="button">
            <span class="dashicons dashicons-download" style="font-size:16px;vertical-align:text-bottom;margin-right:4px;"></span>
            <?php esc_html_e( 'Export CSV', 'eprocurement' ); ?>
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="eproc-filter-bar">
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="eproc-flex-row" autocomplete="off">
            <input type="hidden" name="page" value="eprocurement-downloads">
            <select name="bid_id">
                <option value=""><?php esc_html_e( 'All Bids', 'eprocurement' ); ?></option>
                <?php if ( ! empty( $all_bids['items'] ) ) : ?>
                    <?php foreach ( $all_bids['items'] as $bid ) : ?>
                        <option value="<?php echo absint( $bid->id ); ?>" <?php selected( $filter_bid, $bid->id ); ?>>
                            <?php echo esc_html( $bid->bid_number . ' — ' . $bid->title ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <input type="date" name="date_from" value="<?php echo esc_attr( $filter_from ); ?>" placeholder="<?php esc_attr_e( 'From', 'eprocurement' ); ?>" title="<?php esc_attr_e( 'From date', 'eprocurement' ); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr( $filter_to ); ?>" placeholder="<?php esc_attr_e( 'To', 'eprocurement' ); ?>" title="<?php esc_attr_e( 'To date', 'eprocurement' ); ?>">
            <div class="eproc-search-autocomplete" style="position:relative;">
                <input type="search" name="s" id="eproc-dl-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search file or user...', 'eprocurement' ); ?>" list="eproc-dl-suggestions" autocomplete="off">
                <datalist id="eproc-dl-suggestions">
                    <?php foreach ( $autocomplete_items as $suggestion ) : ?>
                        <option value="<?php echo esc_attr( $suggestion ); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'eprocurement' ); ?></button>
            <?php if ( $filter_bid || $filter_from || $filter_to || $search ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-downloads' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'eprocurement' ); ?></a>
            <?php endif; ?>
            <span class="eproc-result-count">
                <?php
                printf(
                    esc_html( _n( '%s record', '%s records', $total, 'eprocurement' ) ),
                    esc_html( number_format_i18n( $total ) )
                );
                ?>
            </span>
        </form>
    </div>

    <!-- Downloads Table -->
    <div class="eproc-card" style="padding:0;">
        <?php if ( ! empty( $items ) ) : ?>
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th style="width:18%;"><?php esc_html_e( 'Date', 'eprocurement' ); ?></th>
                        <th><?php esc_html_e( 'File', 'eprocurement' ); ?></th>
                        <th style="width:15%;"><?php esc_html_e( 'Bid', 'eprocurement' ); ?></th>
                        <th style="width:20%;"><?php esc_html_e( 'User / IP', 'eprocurement' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $dl ) : ?>
                        <?php
                        $doc       = $dl->document_id ? Eprocurement_Database::get_by_id( 'documents', (int) $dl->document_id ) : null;
                        $sup_doc   = ! empty( $dl->supporting_doc_id ) ? Eprocurement_Database::get_by_id( 'supporting_docs', (int) $dl->supporting_doc_id ) : null;
                        $file_name = $sup_doc->file_name ?? '—';
                        ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'j M Y, H:i', strtotime( $dl->downloaded_at ) ) ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $file_name ); ?></strong>
                            </td>
                            <td>
                                <?php if ( $doc ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-bids&action=edit&id=' . $doc->id ) ); ?>">
                                        <?php echo esc_html( $doc->bid_number ); ?>
                                    </a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $dl->display_name ) ) : ?>
                                    <?php echo esc_html( $dl->display_name ); ?>
                                <?php else : ?>
                                    <?php echo esc_html( $dl->ip_address ?: __( 'Anonymous', 'eprocurement' ) ); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="eproc-pagination">
                    <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                        <?php
                        $url_args = [
                            'page'   => 'eprocurement-downloads',
                            'paged'  => $i,
                        ];
                        if ( $filter_bid ) { $url_args['bid_id'] = $filter_bid; }
                        if ( $filter_from ) { $url_args['date_from'] = $filter_from; }
                        if ( $filter_to ) { $url_args['date_to'] = $filter_to; }
                        if ( $search ) { $url_args['s'] = $search; }
                        $url = add_query_arg( $url_args, admin_url( 'admin.php' ) );
                        ?>
                        <?php if ( $i === $page ) : ?>
                            <span class="current"><?php echo esc_html( $i ); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $i ); ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <div class="eproc-empty-state">
                <p><?php esc_html_e( 'No downloads recorded yet.', 'eprocurement' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
