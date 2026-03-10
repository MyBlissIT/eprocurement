<?php
/**
 * Frontend Manage — Online Bids partial.
 *
 * Lists all bids that accept online submissions with submission
 * counts and links to view individual bidders/submissions.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug         = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base  = home_url( "/{$slug}/manage" );
$submissions  = new Eprocurement_Bid_Submissions();
$page         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page     = 25;

$filter_status = sanitize_text_field( $_GET['status'] ?? '' );

$result      = $submissions->get_bids_with_submissions( [
    'status'   => $filter_status,
    'per_page' => $per_page,
    'page'     => $page,
] );
$items       = $result['items'] ?? [];
$total       = $result['total'] ?? 0;
$total_pages = $per_page > 0 ? ceil( $total / $per_page ) : 1;

$base_url = $manage_base . '/online-bids/';
?>
<div class="eproc-wrap">
    <div class="eproc-page-header">
        <h1><?php esc_html_e( 'Online Bids', 'eprocurement' ); ?></h1>
    </div>

    <!-- Filter Bar -->
    <div class="eproc-filter-bar">
        <form method="get" action="<?php echo esc_url( $base_url ); ?>" class="eproc-flex-row">
            <select name="status" onchange="this.form.submit()">
                <option value=""><?php esc_html_e( 'All Statuses', 'eprocurement' ); ?></option>
                <option value="open" <?php selected( $filter_status, 'open' ); ?>><?php esc_html_e( 'Open', 'eprocurement' ); ?></option>
                <option value="closed" <?php selected( $filter_status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'eprocurement' ); ?></option>
                <option value="draft" <?php selected( $filter_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'eprocurement' ); ?></option>
                <option value="archived" <?php selected( $filter_status, 'archived' ); ?>><?php esc_html_e( 'Archived', 'eprocurement' ); ?></option>
            </select>
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'eprocurement' ); ?></button>
            <?php if ( $filter_status ) : ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'eprocurement' ); ?></a>
            <?php endif; ?>
            <span class="eproc-result-count">
                <?php
                printf(
                    esc_html( _n( '%s bid', '%s bids', $total, 'eprocurement' ) ),
                    esc_html( number_format_i18n( $total ) )
                );
                ?>
            </span>
        </form>
    </div>

    <!-- Online Bids Table -->
    <div class="eproc-card" style="padding:0;">
        <?php if ( ! empty( $items ) ) : ?>
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th style="width:15%;"><?php esc_html_e( 'Bid Number', 'eprocurement' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'eprocurement' ); ?></th>
                        <th style="width:12%;"><?php esc_html_e( 'Status', 'eprocurement' ); ?></th>
                        <th style="width:15%;"><?php esc_html_e( 'Closing Date', 'eprocurement' ); ?></th>
                        <th style="width:12%;text-align:center;"><?php esc_html_e( 'Submissions', 'eprocurement' ); ?></th>
                        <th style="width:12%;text-align:center;"><?php esc_html_e( 'Bidders', 'eprocurement' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $bid ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( $manage_base . '/bids/?action=edit&id=' . $bid->id ); ?>">
                                    <?php echo esc_html( $bid->bid_number ); ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $manage_base . '/bids/?action=edit&id=' . $bid->id ); ?>">
                                    <?php echo esc_html( $bid->title ); ?>
                                </a>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'open'      => '#16a34a',
                                    'closed'    => '#8b1a2b',
                                    'draft'     => '#6b7280',
                                    'archived'  => '#9333ea',
                                    'cancelled' => '#dc2626',
                                ];
                                $color = $status_colors[ $bid->status ] ?? '#6b7280';
                                ?>
                                <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600;color:#fff;background:<?php echo esc_attr( $color ); ?>;">
                                    <?php echo esc_html( ucfirst( $bid->status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if ( $bid->closing_date ) {
                                    $closing_ts = strtotime( $bid->closing_date );
                                    $is_past    = $closing_ts < time();
                                    echo '<span' . ( $is_past ? ' style="color:#8b1a2b;font-weight:600;"' : '' ) . '>';
                                    echo esc_html( wp_date( 'j M Y, H:i', $closing_ts ) );
                                    echo '</span>';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ( (int) $bid->submission_count > 0 ) : ?>
                                    <span style="display:inline-block;background:#8b1a2b;color:#fff;font-size:12px;font-weight:600;padding:2px 10px;border-radius:10px;">
                                        <?php echo esc_html( number_format_i18n( $bid->submission_count ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <span style="color:#9ca3af;">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ( (int) $bid->bidder_count > 0 ) : ?>
                                    <a href="<?php echo esc_url( $manage_base . '/bids/?action=edit&id=' . $bid->id . '#submissions' ); ?>"
                                       style="display:inline-block;background:#1a1a5e;color:#fff;font-size:12px;font-weight:600;padding:2px 10px;border-radius:10px;text-decoration:none;"
                                       title="<?php esc_attr_e( 'View bidders who submitted', 'eprocurement' ); ?>">
                                        <?php echo esc_html( number_format_i18n( $bid->bidder_count ) ); ?>
                                    </a>
                                <?php else : ?>
                                    <span style="color:#9ca3af;">0</span>
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
                        $url = $base_url;
                        $url_args = [];
                        if ( $i > 1 ) { $url_args['paged'] = $i; }
                        if ( $filter_status ) { $url_args['status'] = $filter_status; }
                        if ( ! empty( $url_args ) ) {
                            $url = add_query_arg( $url_args, $url );
                        }
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
                <p><?php esc_html_e( 'No bids with online submissions enabled.', 'eprocurement' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
