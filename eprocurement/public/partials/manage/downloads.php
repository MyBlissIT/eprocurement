<?php
/**
 * Frontend Admin — Download Log.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug        = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base = home_url( "/{$slug}/manage" );

$downloads = new Eprocurement_Downloads();
$page      = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page  = 25;

$filter_bid = absint( $_GET['bid_id'] ?? 0 );
$args       = [
    'per_page' => $per_page,
    'page'     => $page,
];

$result      = $downloads->get_log( $filter_bid, $args );
$items       = $result['items'] ?? [];
$total       = $result['total'] ?? 0;
$total_pages = $per_page > 0 ? ceil( $total / $per_page ) : 1;

$documents_obj = new Eprocurement_Documents();
$all_bids      = $documents_obj->list( [ 'per_page' => 100, 'include_all_statuses' => true ] );

$base_url = $manage_base . '/downloads/';
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1><?php esc_html_e( 'Download Log', 'eprocurement' ); ?></h1>
        <button type="button" class="eproc-btn eproc-btn-sm" id="eproc-export-downloads">
            <?php esc_html_e( 'Export CSV', 'eprocurement' ); ?>
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="eproc-filter-bar">
        <form method="get" action="<?php echo esc_url( $base_url ); ?>" style="display:flex;gap:12px;align-items:center;">
            <select name="bid_id" class="eproc-select">
                <option value=""><?php esc_html_e( 'All Bids', 'eprocurement' ); ?></option>
                <?php if ( ! empty( $all_bids['items'] ) ) : ?>
                    <?php foreach ( $all_bids['items'] as $bid ) : ?>
                        <option value="<?php echo absint( $bid->id ); ?>" <?php selected( $filter_bid, $bid->id ); ?>>
                            <?php echo esc_html( $bid->bid_number . ' — ' . $bid->title ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button type="submit" class="eproc-btn eproc-btn-sm"><?php esc_html_e( 'Filter', 'eprocurement' ); ?></button>
        </form>
    </div>

    <!-- Downloads Table -->
    <div class="eproc-card" style="padding:0;">
        <?php if ( ! empty( $items ) ) : ?>
            <table class="eproc-table">
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
                            <td><strong><?php echo esc_html( $file_name ); ?></strong></td>
                            <td>
                                <?php if ( $doc ) : ?>
                                    <a href="<?php echo esc_url( $manage_base . '/bids/?action=edit&id=' . $doc->id ); ?>">
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
                        $url = add_query_arg( [
                            'paged'  => $i,
                            'bid_id' => $filter_bid ?: null,
                        ], $base_url );
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('eproc-export-downloads').addEventListener('click', async function() {
        try {
            var params = '<?php echo $filter_bid ? "?document_id={$filter_bid}" : ""; ?>';
            var data = await eprocAPI.get('admin/downloads/export' + params);
            eprocExportCSV(data.data, 'downloads-export.csv');
        } catch (e) {
            eprocToast(e.message, 'error');
        }
    });
});
</script>
