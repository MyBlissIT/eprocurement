<?php
/**
 * Frontend Admin Dashboard partial.
 *
 * Displays stat cards, recent bids table, and recent queries.
 * Adapted from admin/partials/dashboard.php for frontend manage area.
 * All links use home_url() instead of admin_url().
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

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

// Most downloaded documents (top 4)
$most_downloaded_rows = Eprocurement_Downloads::get_most_downloaded_documents( 4 );

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
    <div class="eproc-page-header">
        <h1><?php esc_html_e( 'Dashboard', 'eprocurement' ); ?></h1>
    </div>

    <!-- Stat Cards -->
    <div id="eproc-dashboard-stats" class="eproc-stats">
        <div class="eproc-stat-card">
            <div class="eproc-stat-icon eproc-stat-icon--blue">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/></svg>
            </div>
            <div class="eproc-stat-body">
                <div class="eproc-stat-number"><?php echo esc_html( $total_bids ); ?></div>
                <div class="eproc-stat-label"><?php esc_html_e( 'Total Bids', 'eprocurement' ); ?></div>
            </div>
        </div>
        <div class="eproc-stat-card green">
            <div class="eproc-stat-icon eproc-stat-icon--green">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707 9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            </div>
            <div class="eproc-stat-body">
                <div class="eproc-stat-number"><?php echo esc_html( $open_bids ); ?></div>
                <div class="eproc-stat-label"><?php esc_html_e( 'Open Bids', 'eprocurement' ); ?></div>
            </div>
        </div>
        <div class="eproc-stat-card red">
            <div class="eproc-stat-icon eproc-stat-icon--red">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707 6.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            </div>
            <div class="eproc-stat-body">
                <div class="eproc-stat-number"><?php echo esc_html( $closed_bids ); ?></div>
                <div class="eproc-stat-label"><?php esc_html_e( 'Closed', 'eprocurement' ); ?></div>
            </div>
        </div>
        <div class="eproc-stat-card purple">
            <div class="eproc-stat-icon eproc-stat-icon--purple">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
            </div>
            <div class="eproc-stat-body">
                <div class="eproc-stat-number"><?php echo esc_html( $total_bidders ); ?></div>
                <div class="eproc-stat-label"><?php esc_html_e( 'Bidders', 'eprocurement' ); ?></div>
            </div>
        </div>
        <div class="eproc-stat-card orange">
            <div class="eproc-stat-icon eproc-stat-icon--orange">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/></svg>
            </div>
            <div class="eproc-stat-body">
                <div class="eproc-stat-number"><?php echo esc_html( $unread_count ); ?></div>
                <div class="eproc-stat-label"><?php esc_html_e( 'Unread Queries', 'eprocurement' ); ?></div>
            </div>
        </div>
        <div class="eproc-stat-card">
            <div class="eproc-stat-icon eproc-stat-icon--teal">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </div>
            <div class="eproc-stat-body">
                <div class="eproc-stat-number"><?php echo esc_html( $downloads_today ); ?></div>
                <div class="eproc-stat-label"><?php esc_html_e( 'Downloads Today', 'eprocurement' ); ?></div>
            </div>
        </div>
        <div class="eproc-stat-card-wide">
            <h3><?php esc_html_e( 'Most Downloaded', 'eprocurement' ); ?></h3>
            <?php if ( ! empty( $most_downloaded_rows ) ) : ?>
                <ul class="eproc-most-downloaded-list">
                    <?php foreach ( $most_downloaded_rows as $dl_row ) : ?>
                        <li title="<?php echo esc_attr( $dl_row->title ); ?>">
                            <?php echo esc_html( wp_trim_words( $dl_row->title, 5, '...' ) ); ?>
                            <span>(<?php echo esc_html( (int) $dl_row->dl_count ); ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e( 'N/A', 'eprocurement' ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content: Two Columns -->
    <div class="eproc-dashboard-grid">

        <!-- Left Column: Recent Bids -->
        <div class="eproc-dashboard-main">
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Recent Bids', 'eprocurement' ); ?></h2>
                    <a href="<?php echo esc_url( home_url( "/{$slug}/manage/bids/" ) ); ?>" class="eproc-card-link">
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
                                            <a href="<?php echo esc_url( home_url( "/{$slug}/manage/bids/?action=edit&id=" . absint( $bid->id ) ) ); ?>">
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

        <!-- Right Column: Recent Queries -->
        <div class="eproc-dashboard-aside">

            <!-- Recent Queries -->
            <div class="eproc-card">
                <div class="eproc-card-header">
                    <h2><?php esc_html_e( 'Recent Queries', 'eprocurement' ); ?></h2>
                    <a href="<?php echo esc_url( home_url( "/{$slug}/manage/messages/" ) ); ?>" class="eproc-card-link">
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
                                    <a href="<?php echo esc_url( home_url( "/{$slug}/manage/messages/?thread_id=" . absint( $thread->id ) ) ); ?>">
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

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- Recent Activity Feed                                          -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="eproc-card" style="margin-top:24px;">
        <div class="eproc-card-header">
            <h2>
                <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18" style="vertical-align:-3px;margin-right:4px;color:var(--eproc-primary);">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
                <?php esc_html_e( 'Recent Activity', 'eprocurement' ); ?>
            </h2>
        </div>
        <div class="eproc-card-body">
            <?php
            $activity = Eprocurement_Activity_Log::get_recent( 10 );
            if ( ! empty( $activity ) ) :
            ?>
                <div class="eproc-activity-feed">
                    <?php foreach ( $activity as $entry ) : ?>
                        <div class="eproc-activity-item">
                            <div class="eproc-activity-icon eproc-activity-icon--<?php echo esc_attr( $entry['type'] ); ?>">
                                <?php echo Eprocurement_Activity_Log::get_icon( $entry['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                            <div class="eproc-activity-body">
                                <p class="eproc-activity-text"><?php echo wp_kses_post( $entry['message'] ); ?></p>
                                <p class="eproc-activity-meta">
                                    <strong><?php echo esc_html( $entry['user_name'] ); ?></strong>
                                    · <?php echo esc_html( eprocurement_relative_time( $entry['timestamp'] ) ); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="eproc-activity-empty">
                    <p class="eproc-activity-empty-text"><?php esc_html_e( 'No recent activity. Activity will appear here as bids are created, queries submitted, and tenders awarded.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
