<?php
/**
 * Public tender listing page.
 *
 * Displays the main tender/bid listing with search, filters,
 * a card grid, and Load More pagination.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug       = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$nav_items  = Eprocurement_Public::get_nav_items();
$documents  = new Eprocurement_Documents();

// Category filter (set by router in class-public.php for sub-pages)
$active_category = isset( $eproc_category ) ? $eproc_category : 'bid';

// Category display labels — main bid heading is configurable per tenant
$bid_heading = get_option( 'eprocurement_bid_heading', __( 'Tenders & Bids', 'eprocurement' ) );
$category_labels = [
    'bid'                => $bid_heading,
    'briefing_register'  => __( 'Briefing Register', 'eprocurement' ),
    'closing_register'   => __( 'Closing Register', 'eprocurement' ),
    'appointments'       => __( 'Appointments', 'eprocurement' ),
];
$page_title = $category_labels[ $active_category ] ?? $category_labels['bid'];

// Initial server-side render (page 1)
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$search_query  = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

$args = [
    'per_page' => 12,
    'page'     => 1,
    'orderby'  => 'closing_date',
    'order'    => 'DESC',
    'category' => $active_category,
];

if ( $status_filter && in_array( $status_filter, [ 'open', 'closed' ], true ) ) {
    $args['status'] = $status_filter;
}
if ( $search_query ) {
    $args['search'] = $search_query;
}

$result = $documents->list( $args );
$items  = $result['items'];
$total  = $result['total'];
$pages  = $result['pages'];

// Batch-fetch contacts and doc counts to avoid N+1 queries.
global $wpdb;
$_doc_ids     = wp_list_pluck( $items, 'id' );
$_contact_ids = [];
foreach ( $items as $_d ) {
    if ( ! empty( $_d->scm_contact_id ) ) {
        $_contact_ids[] = (int) $_d->scm_contact_id;
    }
}
$_contact_ids = array_unique( $_contact_ids );

$_contacts_map = [];
if ( ! empty( $_contact_ids ) ) {
    $_ph = implode( ',', array_fill( 0, count( $_contact_ids ), '%d' ) );
    $_ct = Eprocurement_Database::table( 'contact_persons' );
    foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM {$_ct} WHERE id IN ({$_ph})", ...$_contact_ids ) ) as $_row ) { // phpcs:ignore
        $_contacts_map[ (int) $_row->id ] = $_row->name;
    }
}

$_doc_counts = [];
if ( ! empty( $_doc_ids ) ) {
    $_ph = implode( ',', array_fill( 0, count( $_doc_ids ), '%d' ) );
    $_st = Eprocurement_Database::table( 'supporting_docs' );
    foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT document_id, COUNT(*) AS cnt FROM {$_st} WHERE document_id IN ({$_ph}) GROUP BY document_id", ...$_doc_ids ) ) as $_row ) { // phpcs:ignore
        $_doc_counts[ (int) $_row->document_id ] = (int) $_row->cnt;
    }
}
?>
<div class="eproc-wrap">

    <!-- Navigation Bar -->
    <nav class="eproc-navbar">
        <div class="eproc-navbar-inner">
            <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-navbar-brand">
                <?php echo esc_html__( 'eProcurement Portal', 'eprocurement' ); ?>
            </a>
            <div class="eproc-navbar-links">
                <?php foreach ( $nav_items as $nav_item ) : ?>
                    <a href="<?php echo esc_url( $nav_item['url'] ); ?>" class="eproc-nav-link">
                        <?php echo esc_html( $nav_item['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="eproc-navbar-actions">
                <?php if ( is_user_logged_in() && Eprocurement_Roles::is_bidder() ) : ?>
                    <a href="<?php echo esc_url( home_url( "/{$slug}/my-account/" ) ); ?>" class="eproc-btn eproc-btn-outline">
                        <?php echo esc_html__( 'My Dashboard', 'eprocurement' ); ?>
                    </a>
                <?php elseif ( ! is_user_logged_in() ) : ?>
                    <a href="<?php echo esc_url( home_url( "/{$slug}/login/" ) ); ?>" class="eproc-btn eproc-btn-outline">
                        <?php echo esc_html__( 'Login', 'eprocurement' ); ?>
                    </a>
                    <a href="<?php echo esc_url( home_url( "/{$slug}/register/" ) ); ?>" class="eproc-btn eproc-btn-primary">
                        <?php echo esc_html__( 'Register', 'eprocurement' ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <button class="eproc-navbar-toggle" aria-label="<?php echo esc_attr__( 'Toggle navigation', 'eprocurement' ); ?>">
                <span class="eproc-navbar-toggle-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <?php
    $hero_class = 'eproc-hero';
    $hero_title = $page_title;
    $hero_sub   = __( 'Browse open procurement opportunities. Download bid documents and submit your queries online.', 'eprocurement' );

    if ( $status_filter === 'open' ) {
        $hero_class .= ' eproc-hero--open';
        $hero_title  = __( 'Open Tenders & Bids', 'eprocurement' );
        $hero_sub    = __( 'Currently accepting submissions. Download bid documents and submit your queries before the closing date.', 'eprocurement' );
    } elseif ( $status_filter === 'closed' ) {
        $hero_class .= ' eproc-hero--closed';
        $hero_title  = __( 'Closed Tenders & Bids', 'eprocurement' );
        $hero_sub    = __( 'These tenders have closed and are no longer accepting submissions.', 'eprocurement' );
    }
    ?>
    <section class="<?php echo esc_attr( $hero_class ); ?>">
        <div class="eproc-hero-inner">
            <h1 class="eproc-hero-title"><?php echo esc_html( $hero_title ); ?></h1>
            <p class="eproc-hero-subtitle">
                <?php echo esc_html( $hero_sub ); ?>
            </p>
        </div>
    </section>

    <!-- Search & Filter Bar -->
    <section class="eproc-filter-bar">
        <form class="eproc-filter-form" method="get" action="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>">
            <div class="eproc-filter-row">
                <div class="eproc-search-field">
                    <input
                        type="text"
                        name="search"
                        class="eproc-input"
                        placeholder="<?php echo esc_attr__( 'Search by bid number or title...', 'eprocurement' ); ?>"
                        value="<?php echo esc_attr( $search_query ); ?>"
                    />
                </div>
                <div class="eproc-filter-field">
                    <select name="status" class="eproc-select" onchange="this.form.submit()">
                        <option value=""><?php echo esc_html__( 'All Statuses', 'eprocurement' ); ?></option>
                        <option value="open" <?php selected( $status_filter, 'open' ); ?>>
                            <?php echo esc_html__( 'Open', 'eprocurement' ); ?>
                        </option>
                        <option value="closed" <?php selected( $status_filter, 'closed' ); ?>>
                            <?php echo esc_html__( 'Closed', 'eprocurement' ); ?>
                        </option>
                    </select>
                </div>
                <div class="eproc-filter-action">
                    <button type="submit" class="eproc-btn eproc-btn-primary">
                        <?php echo esc_html__( 'Search', 'eprocurement' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- Results Count -->
    <div class="eproc-results-meta">
        <p class="eproc-results-count">
            <?php
            printf(
                /* translators: %d: total number of tenders found */
                esc_html( _n( '%d tender found', '%d tenders found', $total, 'eprocurement' ) ),
                (int) $total
            );
            ?>
        </p>
    </div>

    <!-- Bid Cards Grid -->
    <div id="eproc-tender-grid" class="eproc-card-grid eproc-card-grid--3col">
        <?php if ( empty( $items ) ) : ?>
            <div class="eproc-empty-state">
                <p><?php echo esc_html__( 'No tenders match your search criteria.', 'eprocurement' ); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ( $items as $doc ) :
                $scm_name      = ! empty( $doc->scm_contact_id ) ? ( $_contacts_map[ (int) $doc->scm_contact_id ] ?? '' ) : '';
                $doc_count      = $_doc_counts[ (int) $doc->id ] ?? 0;
                $closing_date   = $doc->closing_date ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $doc->closing_date ) ) : '';
                $short_desc     = wp_trim_words( wp_strip_all_tags( $doc->description ), 20, '...' );
            ?>
                <div class="eproc-card eproc-bid-card">
                    <div class="eproc-card-header">
                        <?php echo Eprocurement_Public::status_badge( $doc->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method returns escaped HTML ?>
                    </div>
                    <div class="eproc-card-body">
                        <p class="eproc-bid-number"><?php echo esc_html( $doc->bid_number ); ?></p>
                        <h3 class="eproc-bid-title"><?php echo esc_html( $doc->title ); ?></h3>
                        <?php if ( $short_desc ) : ?>
                            <p class="eproc-bid-excerpt"><?php echo esc_html( $short_desc ); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="eproc-card-meta">
                        <?php if ( $closing_date ) : ?>
                            <div class="eproc-meta-item">
                                <span class="eproc-meta-label"><?php echo esc_html__( 'Closing:', 'eprocurement' ); ?></span>
                                <span class="eproc-meta-value"><?php echo esc_html( $closing_date ); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ( $scm_name ) : ?>
                            <div class="eproc-meta-item">
                                <span class="eproc-meta-label"><?php echo esc_html__( 'SCM Contact:', 'eprocurement' ); ?></span>
                                <span class="eproc-meta-value"><?php echo esc_html( $scm_name ); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="eproc-meta-item">
                            <span class="eproc-meta-label"><?php echo esc_html__( 'Documents:', 'eprocurement' ); ?></span>
                            <span class="eproc-meta-value"><?php echo esc_html( $doc_count ); ?></span>
                        </div>
                    </div>
                    <div class="eproc-card-footer">
                        <a
                            href="<?php echo esc_url( Eprocurement_Public::bid_url( (int) $doc->id ) ); ?>"
                            class="eproc-btn eproc-btn-primary eproc-btn-block"
                        >
                            <?php echo esc_html__( 'View Details', 'eprocurement' ); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Load More Pagination -->
    <?php if ( $pages > 1 ) : ?>
        <div class="eproc-pagination" id="eproc-pagination">
            <button
                type="button"
                class="eproc-btn eproc-btn-outline eproc-btn-lg"
                id="eproc-load-more"
                data-page="1"
                data-pages="<?php echo esc_attr( $pages ); ?>"
                data-total="<?php echo esc_attr( $total ); ?>"
            >
                <?php echo esc_html__( 'Load More Tenders', 'eprocurement' ); ?>
            </button>
            <p class="eproc-pagination-info">
                <?php
                printf(
                    /* translators: 1: number shown, 2: total */
                    esc_html__( 'Showing %1$d of %2$d tenders', 'eprocurement' ),
                    min( 12, $total ),
                    (int) $total
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

</div><!-- .eproc-wrap -->

<script>
(function() {
    var grid       = document.getElementById('eproc-tender-grid');
    var loadMore   = document.getElementById('eproc-load-more');
    var pagination = document.getElementById('eproc-pagination');

    if ( ! loadMore ) return;

    var i18n = {
        closing:    '<?php echo esc_js( __( 'Closing:', 'eprocurement' ) ); ?>',
        scmContact: '<?php echo esc_js( __( 'SCM Contact:', 'eprocurement' ) ); ?>',
        documents:  '<?php echo esc_js( __( 'Documents:', 'eprocurement' ) ); ?>',
        viewDetail: '<?php echo esc_js( __( 'View Details', 'eprocurement' ) ); ?>',
        loadMore:   '<?php echo esc_js( __( 'Load More Tenders', 'eprocurement' ) ); ?>',
        showing:    '<?php echo esc_js( __( 'Showing %1$d of %2$d tenders', 'eprocurement' ) ); ?>'
    };

    loadMore.addEventListener('click', function() {
        var currentPage = parseInt( loadMore.getAttribute('data-page'), 10 );
        var totalPages  = parseInt( loadMore.getAttribute('data-pages'), 10 );
        var nextPage    = currentPage + 1;

        if ( nextPage > totalPages ) return;

        loadMore.disabled = true;
        loadMore.textContent = eprocFrontend.strings.sending || 'Loading...';

        var params = new URLSearchParams({
            page: nextPage,
            per_page: 12,
            category: '<?php echo esc_js( $active_category ); ?>',
            status: '<?php echo esc_js( $status_filter ); ?>',
            search: '<?php echo esc_js( $search_query ); ?>'
        });

        fetch( eprocFrontend.restUrl + 'documents?' + params.toString(), {
            headers: { 'X-WP-Nonce': eprocFrontend.nonce }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if ( data.items && data.items.length ) {
                var statusColors = {
                    'draft': '#888', 'open': '#16a34a',
                    'closed': '#8b1a2b', 'cancelled': '#95a5a6', 'archived': '#7f8c8d'
                };

                data.items.forEach(function(doc) {
                    var card = document.createElement('div');
                    card.className = 'eproc-card eproc-bid-card';

                    var badgeColor  = statusColors[doc.status] || '#888';
                    var closingDate = doc.closing_date ? new Date(doc.closing_date).toLocaleDateString() : '';
                    var scmContact  = (doc.contacts && doc.contacts.scm) ? doc.contacts.scm : '';
                    var description = doc.description ? doc.description.replace(/<[^>]*>/g, '').substring(0, 120) + '...' : '';
                    var slug        = eprocFrontend.slug || 'tenders';
                    var detailUrl   = '/' + slug + '/bid/' + parseInt(doc.id, 10) + '/';

                    card.innerHTML = '<div class="eproc-card-header">' +
                        '<span class="eproc-status-badge" style="background:' + badgeColor + '">' + escHtml(doc.status.toUpperCase()) + '</span>' +
                        '</div>' +
                        '<div class="eproc-card-body">' +
                        '<p class="eproc-bid-number">' + escHtml(doc.bid_number) + '</p>' +
                        '<h3 class="eproc-bid-title">' + escHtml(doc.title) + '</h3>' +
                        (description ? '<p class="eproc-bid-excerpt">' + escHtml(description) + '</p>' : '') +
                        '</div>' +
                        '<div class="eproc-card-meta">' +
                        (closingDate ? '<div class="eproc-meta-item"><span class="eproc-meta-label">' + escHtml(i18n.closing) + '</span> <span class="eproc-meta-value">' + escHtml(closingDate) + '</span></div>' : '') +
                        (scmContact ? '<div class="eproc-meta-item"><span class="eproc-meta-label">' + escHtml(i18n.scmContact) + '</span> <span class="eproc-meta-value">' + escHtml(scmContact) + '</span></div>' : '') +
                        '<div class="eproc-meta-item"><span class="eproc-meta-label">' + escHtml(i18n.documents) + '</span> <span class="eproc-meta-value">' + parseInt(doc.doc_count || 0, 10) + '</span></div>' +
                        '</div>' +
                        '<div class="eproc-card-footer">' +
                        '<a href="' + escAttr(detailUrl) + '" class="eproc-btn eproc-btn-primary eproc-btn-block">' + escHtml(i18n.viewDetail) + '</a>' +
                        '</div>';

                    grid.appendChild(card);
                });

                loadMore.setAttribute('data-page', nextPage);

                // Update count display
                var shown = grid.querySelectorAll('.eproc-bid-card').length;
                var total = parseInt( loadMore.getAttribute('data-total'), 10 );
                var info  = pagination.querySelector('.eproc-pagination-info');
                if ( info ) {
                    info.textContent = i18n.showing.replace('%1$d', shown).replace('%2$d', total);
                }

                if ( nextPage >= totalPages ) {
                    loadMore.style.display = 'none';
                }
            }

            loadMore.disabled = false;
            loadMore.textContent = i18n.loadMore;
        })
        .catch(function() {
            loadMore.disabled = false;
            loadMore.textContent = i18n.loadMore;
        });
    });

    function escHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text || ''));
        return div.innerHTML;
    }

    function escAttr(text) {
        return (text || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
})();
</script>
