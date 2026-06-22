<?php
/**
 * Bidder management partial.
 *
 * Displays all registered bidders with verification status and activity counts.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bidder_handler = new Eprocurement_Bidder();

// Filters
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

$result = $bidder_handler->get_all_bidders( $args );
$items  = $result['items'];
$total  = $result['total'];
$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

// Counts for tabs
$total_all        = Eprocurement_Database::count( 'bidder_profiles' );
$total_verified   = Eprocurement_Database::count( 'bidder_profiles', [ 'verified' => 1 ] );
$total_unverified = $total_all - $total_verified;

$base_url = admin_url( 'admin.php?page=eprocurement-bidders' );
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1>
            <?php esc_html_e( 'Registered Bidders', 'eprocurement' ); ?>
            <span class="eproc-result-count">(<?php echo esc_html( $total_all ); ?>)</span>
        </h1>
        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=eproc_export_downloads' ), 'eproc_admin_nonce', 'nonce' ) ); ?>" class="button">
            <?php esc_html_e( 'Export CSV', 'eprocurement' ); ?>
        </a>
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
        <table class="wp-list-table widefat">
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
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <?php echo eprocurement_avatar( (int) $bidder->user_id, $bidder->display_name, 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <div>
                                        <strong><?php echo esc_html( $bidder->company_name ); ?></strong>
                                        <?php if ( $bidder->company_reg ) : ?>
                                            <br><span class="eproc-text-muted"><?php echo esc_html( $bidder->company_reg ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
                                    <?php echo eprocurement_status_badge( 'verified', __( 'Verified', 'eprocurement' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php else : ?>
                                    <?php echo eprocurement_status_badge( 'unverified', __( 'Unverified', 'eprocurement' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <button type="button" class="button button-small eproc-resend-verify" data-user-id="<?php echo esc_attr( $bidder->user_id ); ?>" style="margin-left:4px;font-size:11px;">
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
                                <div class="eproc-empty-state-illustration">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <p class="eproc-empty-state-title"><?php esc_html_e( 'No bidders yet', 'eprocurement' ); ?></p>
                                <p class="eproc-empty-state-text"><?php esc_html_e( 'Bidders will appear here once they register on the portal.', 'eprocurement' ); ?></p>
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
jQuery(function($) {
    $('.eproc-resend-verify').on('click', function() {
        var $btn = $(this);
        var userId = $btn.data('user-id');
        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Sending...', 'eprocurement' ) ); ?>');

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_resend_verification',
            nonce: eprocAdmin.nonce,
            user_id: userId
        }, function(r) {
            if (r.success) {
                $btn.text('<?php echo esc_js( __( 'Sent!', 'eprocurement' ) ); ?>').css('color', '#8b1a2b');
            } else {
                alert(r.data.message || eprocAdmin.strings.error);
                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Resend', 'eprocurement' ) ); ?>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Resend', 'eprocurement' ) ); ?>');
        });
    });
});
</script>
