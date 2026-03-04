<?php
/**
 * Admin Dashboard partial.
 *
 * Displays stat cards, recent bids table, recent queries,
 * and status breakdown. No inline styles — all CSS in admin-shell.css.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$documents  = new Eprocurement_Documents();
$messaging  = new Eprocurement_Messaging();
$counts     = $documents->get_status_counts();
$user_id    = get_current_user_id();

$total_bidders  = Eprocurement_Database::count( 'bidder_profiles' );
$unread_count   = $messaging->get_unread_count( $user_id );

// Total bids
$total_bids  = array_sum( $counts );

// Open bids
$open_bids   = $counts['open'] ?? 0;
$closed_bids = $counts['closed'] ?? 0;

// Downloads today
$downloads_today = Eprocurement_Downloads::get_downloads_today();

// Most downloaded document (open bids only)
$most_downloaded_row   = Eprocurement_Downloads::get_most_downloaded_document();
$most_downloaded_title = $most_downloaded_row ? $most_downloaded_row->title : __( 'N/A', 'eprocurement' );
$most_downloaded_count = $most_downloaded_row ? (int) $most_downloaded_row->dl_count : 0;

// Recent bids
$recent_bids = $documents->list( [
    'per_page'              => 10,
    'page'                  => 1,
    'include_all_statuses'  => true,
] );

// Recent threads (queries)
$recent_threads = $messaging->get_admin_inbox( [
    'per_page' => 5,
    'page'     => 1,
] );
?>
<div class="eproc-wrap">
    <h1><?php esc_html_e( 'Dashboard', 'eprocurement' ); ?></h1>

    <!-- Stat Cards -->
    <div id="eproc-dashboard-stats">
        <div>
            <h3><?php esc_html_e( 'Total Bids', 'eprocurement' ); ?></h3>
            <p><?php echo esc_html( $total_bids ); ?></p>
        </div>
        <div>
            <h3><?php esc_html_e( 'Open Bids', 'eprocurement' ); ?></h3>
            <p><?php echo esc_html( $open_bids ); ?></p>
        </div>
        <div>
            <h3><?php esc_html_e( 'Closed', 'eprocurement' ); ?></h3>
            <p><?php echo esc_html( $closed_bids ); ?></p>
        </div>
        <div>
            <h3><?php esc_html_e( 'Total Bidders', 'eprocurement' ); ?></h3>
            <p><?php echo esc_html( $total_bidders ); ?></p>
        </div>
        <div>
            <h3><?php esc_html_e( 'Unread Queries', 'eprocurement' ); ?></h3>
            <p><?php echo esc_html( $unread_count ); ?></p>
        </div>
        <div>
            <h3><?php esc_html_e( 'Downloads Today', 'eprocurement' ); ?></h3>
            <p><?php echo esc_html( $downloads_today ); ?></p>
        </div>
        <div>
            <h3><?php esc_html_e( 'Most Downloaded', 'eprocurement' ); ?></h3>
            <p title="<?php echo esc_attr( $most_downloaded_title ); ?>">
                <?php
                if ( $most_downloaded_row ) {
                    echo esc_html( wp_trim_words( $most_downloaded_title, 5, '...' ) );
                    echo ' <small>(' . esc_html( $most_downloaded_count ) . ')</small>';
                } else {
                    esc_html_e( 'N/A', 'eprocurement' );
                }
                ?>
            </p>
        </div>
    </div>

    <!-- Main Content: Two Columns -->
    <div class="eproc-dashboard-grid">

        <!-- Left Column: Recent Bids -->
        <div class="eproc-dashboard-main">
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Recent Bids', 'eprocurement' ); ?></h2>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-bids' ) ); ?>" class="eproc-card-link">
                        <?php esc_html_e( 'View All', 'eprocurement' ); ?> &rarr;
                    </a>
                </div>
                <div class="eproc-card-body eproc-card-body--flush">
                    <?php if ( ! empty( $recent_bids['items'] ) ) : ?>
                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e( 'Bid No.', 'eprocurement' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Title', 'eprocurement' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Status', 'eprocurement' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Closing Date', 'eprocurement' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_bids['items'] as $bid ) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-bids&action=edit&id=' . absint( $bid->id ) ) ); ?>">
                                                <?php echo esc_html( $bid->bid_number ); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html( $bid->title ); ?></td>
                                        <td>
                                            <span class="eproc-status-badge eproc-status-<?php echo esc_attr( $bid->status ); ?>">
                                                <?php echo esc_html( ucfirst( $bid->status ) ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            if ( $bid->closing_date ) {
                                                echo esc_html( wp_date( 'j M Y, H:i', strtotime( $bid->closing_date ) ) );
                                            } else {
                                                esc_html_e( 'TBC', 'eprocurement' );
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="eproc-empty-state">
                            <p><?php esc_html_e( 'No bids found.', 'eprocurement' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Queries + Status -->
        <div class="eproc-dashboard-aside">

            <!-- Recent Queries -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Recent Queries', 'eprocurement' ); ?></h2>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-messages' ) ); ?>" class="eproc-card-link">
                        <?php esc_html_e( 'View All', 'eprocurement' ); ?> &rarr;
                    </a>
                </div>
                <div class="eproc-card-body eproc-card-body--flush">
                    <?php if ( ! empty( $recent_threads['items'] ) ) : ?>
                        <ul class="eproc-query-list">
                            <?php foreach ( $recent_threads['items'] as $thread ) : ?>
                                <?php
                                $sender = get_userdata( (int) $thread->bidder_id );
                                $doc    = Eprocurement_Database::get_by_id( 'documents', (int) $thread->document_id );
                                ?>
                                <li>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-messages&thread_id=' . absint( $thread->id ) ) ); ?>">
                                        <strong><?php echo esc_html( $sender ? $sender->display_name : __( 'Unknown', 'eprocurement' ) ); ?></strong>
                                        <span class="eproc-query-meta">
                                            <?php echo esc_html( $doc ? $doc->bid_number : '' ); ?>
                                            &mdash;
                                            <span class="eproc-visibility-badge eproc-visibility-<?php echo esc_attr( $thread->visibility ); ?>">
                                                <?php echo esc_html( strtoupper( $thread->visibility ) ); ?>
                                            </span>
                                        </span>
                                        <span class="eproc-query-time">
                                            <?php echo esc_html( wp_date( 'j M Y, H:i', strtotime( $thread->updated_at ) ) ); ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <div class="eproc-empty-state">
                            <p><?php esc_html_e( 'No queries yet.', 'eprocurement' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
