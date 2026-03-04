<?php
/**
 * Frontend Admin — Registered Bidders.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug         = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base  = home_url( "/{$slug}/manage" );

$bidder_handler = new Eprocurement_Bidder();

$filter_verified = isset( $_GET['verified'] ) ? sanitize_text_field( $_GET['verified'] ) : '';
$search          = sanitize_text_field( $_GET['s'] ?? '' );
$paged           = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page        = 20;

$args = [
    'per_page' => $per_page,
    'page'     => $paged,
];

if ( $filter_verified !== '' ) {
    $args['verified'] = absint( $filter_verified );
}

if ( $search ) {
    $args['search'] = $search;
}

$result      = $bidder_handler->get_all_bidders( $args );
$items       = $result['items'];
$total       = $result['total'];
$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

$total_all        = Eprocurement_Database::count( 'bidder_profiles' );
$total_verified   = Eprocurement_Database::count( 'bidder_profiles', [ 'verified' => 1 ] );
$total_unverified = $total_all - $total_verified;

$base_url = $manage_base . '/bidders/';
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1>
            <?php esc_html_e( 'Registered Bidders', 'eprocurement' ); ?>
            <span class="eproc-result-count">(<?php echo esc_html( $total_all ); ?>)</span>
        </h1>
        <button type="button" class="eproc-btn eproc-btn-sm" id="eproc-export-bidders">
            <?php esc_html_e( 'Export CSV', 'eprocurement' ); ?>
        </button>
    </div>

    <!-- Filter Tabs -->
    <div class="eproc-tabs">
        <a href="<?php echo esc_url( $base_url ); ?>" class="eproc-tab <?php echo $filter_verified === '' ? 'active' : ''; ?>">
            <?php esc_html_e( 'All', 'eprocurement' ); ?>
            <span class="count"><?php echo esc_html( $total_all ); ?></span>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'verified', '1', $base_url ) ); ?>" class="eproc-tab <?php echo $filter_verified === '1' ? 'active' : ''; ?>">
            <?php esc_html_e( 'Verified', 'eprocurement' ); ?>
            <span class="count"><?php echo esc_html( $total_verified ); ?></span>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'verified', '0', $base_url ) ); ?>" class="eproc-tab <?php echo $filter_verified === '0' ? 'active' : ''; ?>">
            <?php esc_html_e( 'Unverified', 'eprocurement' ); ?>
            <span class="count"><?php echo esc_html( $total_unverified ); ?></span>
        </a>
    </div>

    <!-- Bidders Table -->
    <div class="eproc-card" style="padding:0;">
        <table class="eproc-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Company', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Contact Name', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Email', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Phone', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Status', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Registered', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Downloads', 'eprocurement' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Queries', 'eprocurement' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $items ) ) : ?>
                    <?php foreach ( $items as $bidder ) : ?>
                        <?php
                        $download_count = Eprocurement_Database::count( 'downloads', [ 'user_id' => $bidder->user_id ] );
                        $query_count    = Eprocurement_Database::count( 'threads', [ 'bidder_id' => $bidder->user_id ] );
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $bidder->company_name ); ?></strong>
                                <?php if ( $bidder->company_reg ) : ?>
                                    <br><span class="eproc-text-muted"><?php echo esc_html( $bidder->company_reg ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $bidder->display_name ); ?></td>
                            <td>
                                <a href="mailto:<?php echo esc_attr( $bidder->user_email ); ?>">
                                    <?php echo esc_html( $bidder->user_email ); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( $bidder->phone ?: '—' ); ?></td>
                            <td>
                                <?php if ( (int) $bidder->verified ) : ?>
                                    <span class="eproc-badge verified"><?php esc_html_e( 'Verified', 'eprocurement' ); ?></span>
                                <?php else : ?>
                                    <span class="eproc-badge unverified"><?php esc_html_e( 'Unverified', 'eprocurement' ); ?></span>
                                    <button type="button" class="eproc-btn eproc-btn-sm eproc-resend-verify" data-user-id="<?php echo esc_attr( $bidder->user_id ); ?>">
                                        <?php esc_html_e( 'Resend', 'eprocurement' ); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( wp_date( 'j M Y', strtotime( $bidder->user_registered ) ) ); ?></td>
                            <td style="text-align:center;"><?php echo esc_html( $download_count ); ?></td>
                            <td style="text-align:center;"><?php echo esc_html( $query_count ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8">
                            <div class="eproc-empty-state">
                                <p><?php esc_html_e( 'No bidders found.', 'eprocurement' ); ?></p>
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
        if ( $filter_verified !== '' ) { $page_args['verified'] = $filter_verified; }
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
document.addEventListener('DOMContentLoaded', function() {
    // Export CSV
    document.getElementById('eproc-export-bidders').addEventListener('click', async function() {
        try {
            var data = await eprocAPI.get('admin/bidders/export');
            eprocExportCSV(data.data, 'bidders-export.csv');
        } catch (e) {
            eprocToast(e.message, 'error');
        }
    });

    // Resend verification
    document.querySelectorAll('.eproc-resend-verify').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            var userId = this.dataset.userId;
            this.disabled = true;
            this.textContent = '<?php echo esc_js( __( 'Sending...', 'eprocurement' ) ); ?>';
            try {
                await eprocAPI.post('admin/bidders/' + userId + '/resend', {});
                this.textContent = '<?php echo esc_js( __( 'Sent!', 'eprocurement' ) ); ?>';
                this.style.color = '#8b1a2b';
            } catch (e) {
                eprocToast(e.message, 'error');
                this.disabled = false;
                this.textContent = '<?php echo esc_js( __( 'Resend', 'eprocurement' ) ); ?>';
            }
        });
    });
});
</script>
