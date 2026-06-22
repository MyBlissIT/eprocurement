<?php
/**
 * Bid detail page.
 *
 * Displays full bid/tender information including dates, description,
 * bid documents, contact persons, and public Q&A threads.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug        = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$nav_items   = Eprocurement_Public::get_nav_items();
$bid_id      = isset( $_GET['bid'] ) ? absint( $_GET['bid'] ) : 0;

$documents_model = new Eprocurement_Documents();
$contacts_model  = new Eprocurement_Contact_Persons();
$messaging_model = new Eprocurement_Messaging();

$document = $bid_id ? $documents_model->get( $bid_id ) : null;

// Only show open or closed bids publicly (draft, archived, cancelled are hidden)
if ( ! $document || in_array( $document->status, [ 'draft', 'archived' ], true ) ) : ?>
<div class="eproc-wrap">
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
        </div>
    </nav>
    <div class="eproc-empty-state">
        <h2><?php echo esc_html__( 'Tender Not Found', 'eprocurement' ); ?></h2>
        <p><?php echo esc_html__( 'The requested tender could not be found or is no longer available.', 'eprocurement' ); ?></p>
        <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-btn eproc-btn-primary">
            <?php echo esc_html__( 'Back to Tenders', 'eprocurement' ); ?>
        </a>
    </div>
</div>
<?php
    return;
endif;

// Fetch related data
$doc_contacts    = $contacts_model->get_for_document( $bid_id );
$supporting_docs = $documents_model->get_supporting_docs( $bid_id );
$public_threads  = $messaging_model->get_threads_for_document( $bid_id, 'public' );

$scm_contact       = $doc_contacts['scm'] ?? null;
$technical_contact  = $doc_contacts['technical'] ?? null;

$current_user    = wp_get_current_user();
$is_logged_in    = is_user_logged_in();
$is_bidder       = $is_logged_in && Eprocurement_Roles::is_bidder();
$is_open_bid     = ( $document->status === 'open' );
$bidder_verified = false;
if ( $is_bidder ) {
    $bidder_model    = new Eprocurement_Bidder();
    $bidder_verified = $bidder_model->is_verified( $current_user->ID );
}

// Bid submission data
$bid_submissions   = new Eprocurement_Bid_Submissions();
$existing_sub      = ( $is_bidder && $bidder_verified ) ? $bid_submissions->get_active_submission( $bid_id, $current_user->ID ) : null;
$can_submit_result = ( $is_bidder && $bidder_verified ) ? $bid_submissions->can_submit( $bid_id, $current_user->ID ) : null;
$can_cancel_result = $existing_sub ? $bid_submissions->can_cancel( $bid_id ) : null;
$is_briefing_req   = $bid_submissions->is_briefing_compulsory( $bid_id );
?>
<div class="eproc-wrap">

    <!-- Navigation Bar -->
    <nav class="eproc-navbar">
        <div class="eproc-navbar-inner">
            <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-navbar-brand">
                <?php echo esc_html__( 'eProcurement Portal', 'eprocurement' ); ?>
            </a>
            <div class="eproc-navbar-links">
                <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-nav-link">
                    &larr; <?php echo esc_html__( 'All Tenders', 'eprocurement' ); ?>
                </a>
                <?php foreach ( $nav_items as $nav_item ) : ?>
                    <a href="<?php echo esc_url( $nav_item['url'] ); ?>" class="eproc-nav-link">
                        <?php echo esc_html( $nav_item['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="eproc-navbar-actions">
                <?php if ( $is_logged_in ) : ?>
                    <span class="eproc-nav-user">
                        <?php echo esc_html( $current_user->display_name ); ?>
                    </span>
                <?php else : ?>
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

    <!-- Bid Header + Key Dates (side by side) -->
    <section class="eproc-detail-header eproc-detail-header--combined eproc-detail-header--<?php echo esc_attr( $document->status ); ?>">
        <div class="eproc-detail-header-left">
            <?php echo Eprocurement_Public::status_badge( $document->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <h1 class="eproc-detail-title"><?php echo esc_html( $document->title ); ?></h1>
            <p class="eproc-detail-bid-number">
                <?php echo esc_html( $document->bid_number ); ?>
                <button type="button" class="eproc-copy-btn" data-copy-bid-number="<?php echo esc_attr( $document->bid_number ); ?>" aria-label="<?php esc_attr_e( 'Copy bid number', 'eprocurement' ); ?>" title="<?php esc_attr_e( 'Copy bid number', 'eprocurement' ); ?>">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path d="M8 2a1 1 0 000 2h2a1 1 0 100-2H8z"/><path d="M3 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6h-4.586l1.293-1.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L10.414 13H15v3a2 2 0 01-2 2H5a2 2 0 01-2-2V5zM15 11h2a1 1 0 110 2h-2v-2z"/></svg>
                </button>
                <button type="button" class="eproc-print-btn" data-action="print-tender" aria-label="<?php esc_attr_e( 'Print tender', 'eprocurement' ); ?>" title="<?php esc_attr_e( 'Print this tender', 'eprocurement' ); ?>">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"/></svg>
                </button>
            </p>
        </div>
        <div class="eproc-detail-header-dates">
            <div class="eproc-date-card eproc-date-card--compact">
                <span class="eproc-date-label"><?php echo esc_html__( 'Opening', 'eprocurement' ); ?></span>
                <span class="eproc-date-value">
                    <?php
                    echo $document->opening_date
                        ? esc_html( date_i18n( 'j M Y, H:i', strtotime( $document->opening_date ) ) )
                        : esc_html__( 'TBC', 'eprocurement' );
                    ?>
                </span>
            </div>
            <div class="eproc-date-card eproc-date-card--compact">
                <span class="eproc-date-label"><?php echo esc_html__( 'Briefing', 'eprocurement' ); ?></span>
                <span class="eproc-date-value">
                    <?php
                    echo $document->briefing_date
                        ? esc_html( date_i18n( 'j M Y, H:i', strtotime( $document->briefing_date ) ) )
                        : esc_html__( 'TBC', 'eprocurement' );
                    ?>
                </span>
            </div>
            <div class="eproc-date-card eproc-date-card--compact">
                <span class="eproc-date-label"><?php echo esc_html__( 'Closing', 'eprocurement' ); ?></span>
                <span class="eproc-date-value">
                    <?php
                    echo $document->closing_date
                        ? esc_html( date_i18n( 'j M Y, H:i', strtotime( $document->closing_date ) ) )
                        : esc_html__( 'TBC', 'eprocurement' );
                    ?>
                </span>
            </div>
            <?php if ( ! empty( $document->qa_deadline ) && $document->qa_deadline !== '0000-00-00 00:00:00' ) : ?>
            <div class="eproc-date-card eproc-date-card--compact">
                <span class="eproc-date-label"><?php echo esc_html__( 'Q&A Deadline', 'eprocurement' ); ?></span>
                <span class="eproc-date-value">
                    <?php echo esc_html( date_i18n( 'j M Y, H:i', strtotime( $document->qa_deadline ) ) ); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <?php
        // Live countdown timer — only shown for OPEN bids with a future closing date.
        // High perceived value: creates urgency and helps bidders plan submissions.
        if ( $document->status === 'open' && $document->closing_date && $document->closing_date !== '0000-00-00 00:00:00' ) :
            $closing_ts = strtotime( $document->closing_date );
            $now_ts     = current_time( 'timestamp' );
            $seconds_left = $closing_ts - $now_ts;
            if ( $seconds_left > 0 ) :
                ?>
                <div class="eproc-countdown-timer" data-closing-timestamp="<?php echo esc_attr( $closing_ts ); ?>" role="timer" aria-live="polite" aria-label="<?php esc_attr_e( 'Time remaining until bid closing', 'eprocurement' ); ?>">
                    <div class="eproc-countdown-icon">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 102 0V6zm-1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="eproc-countdown-body">
                        <span class="eproc-countdown-label"><?php esc_html_e( 'Time remaining', 'eprocurement' ); ?></span>
                        <div class="eproc-countdown-digits">
                            <div class="eproc-countdown-unit">
                                <span class="eproc-countdown-value" data-unit="days">00</span>
                                <span class="eproc-countdown-unit-label"><?php esc_html_e( 'days', 'eprocurement' ); ?></span>
                            </div>
                            <span class="eproc-countdown-sep">:</span>
                            <div class="eproc-countdown-unit">
                                <span class="eproc-countdown-value" data-unit="hours">00</span>
                                <span class="eproc-countdown-unit-label"><?php esc_html_e( 'hours', 'eprocurement' ); ?></span>
                            </div>
                            <span class="eproc-countdown-sep">:</span>
                            <div class="eproc-countdown-unit">
                                <span class="eproc-countdown-value" data-unit="minutes">00</span>
                                <span class="eproc-countdown-unit-label"><?php esc_html_e( 'min', 'eprocurement' ); ?></span>
                            </div>
                            <span class="eproc-countdown-sep">:</span>
                            <div class="eproc-countdown-unit">
                                <span class="eproc-countdown-value" data-unit="seconds">00</span>
                                <span class="eproc-countdown-unit-label"><?php esc_html_e( 'sec', 'eprocurement' ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            endif;
        endif;
        ?>
    </section>

    <?php
    // Show award result if the tender has been awarded.
    $award_info = $documents->get_award( $bid_id );
    if ( $award_info ) :
    ?>
    <!-- Award Result -->
    <section class="eproc-detail-section eproc-award-result-section">
        <div class="eproc-award-result-banner">
            <div class="eproc-award-result-icon">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zm7-10a1 1 0 01.945.671L14.118 6H17a1 1 0 110 2h-.018l-.382 1.428a1 1 0 01-1.94-.514L14.732 8h-2.99l-.276.829a1 1 0 11-1.94-.514l2-6A1 1 0 0112 2zm1.382 6l-.667-2-.667 2h1.334zM9 14a1 1 0 011-1h6a1 1 0 110 2h-6a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
            </div>
            <div class="eproc-award-result-body">
                <h2 class="eproc-award-result-title"><?php esc_html_e( 'Tender Awarded', 'eprocurement' ); ?></h2>
                <p class="eproc-award-result-winner">
                    <strong><?php esc_html_e( 'Awarded to:', 'eprocurement' ); ?></strong>
                    <?php echo esc_html( $award_info->company_name ?: $award_info->display_name ); ?>
                </p>
                <?php if ( $award_info->award_amount !== null && $award_info->award_amount > 0 ) : ?>
                    <p class="eproc-award-result-amount">
                        <strong><?php esc_html_e( 'Contract value:', 'eprocurement' ); ?></strong>
                        <?php echo esc_html( number_format_i18n( $award_info->award_amount, 2 ) ); ?>
                    </p>
                <?php endif; ?>
                <p class="eproc-award-result-date">
                    <strong><?php esc_html_e( 'Award date:', 'eprocurement' ); ?></strong>
                    <?php echo esc_html( wp_date( 'j F Y', strtotime( $award_info->award_date ) ) ); ?>
                </p>
                <?php if ( $award_info->award_notes ) : ?>
                    <p class="eproc-award-result-notes"><?php echo esc_html( $award_info->award_notes ); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Description -->
    <section class="eproc-detail-section eproc-description-section">
        <h2 class="eproc-section-title"><?php echo esc_html__( 'Description', 'eprocurement' ); ?></h2>
        <div class="eproc-description-content">
            <?php echo wp_kses_post( $document->description ); ?>
        </div>
    </section>

    <!-- Bid Documents + Contact Persons (side by side) -->
    <div class="eproc-detail-row eproc-docs-contacts-row">
        <section class="eproc-detail-section eproc-documents-section">
            <h2 class="eproc-section-title"><?php echo esc_html__( 'Bid Documents', 'eprocurement' ); ?></h2>
            <?php if ( empty( $supporting_docs ) ) : ?>
                <p class="eproc-muted"><?php echo esc_html__( 'No bid documents have been uploaded for this tender.', 'eprocurement' ); ?></p>
            <?php else : ?>
                <div class="eproc-table-responsive">
                    <table class="eproc-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( '#', 'eprocurement' ); ?></th>
                                <th><?php echo esc_html__( 'Document', 'eprocurement' ); ?></th>
                                <th><?php echo esc_html__( 'Size', 'eprocurement' ); ?></th>
                                <th><?php echo esc_html__( 'Download', 'eprocurement' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $supporting_docs as $index => $file ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $index + 1 ); ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <?php echo eprocurement_file_icon( $file->file_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <span class="eproc-file-label">
                                                <?php echo esc_html( $file->label ?: $file->file_name ); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html( Eprocurement_Public::format_file_size( (int) $file->file_size ) ); ?></td>
                                    <td>
                                        <a
                                            href="<?php echo esc_url( Eprocurement_Downloads::get_download_link( (int) $file->id, 'supporting' ) ); ?>"
                                            class="eproc-btn eproc-btn-sm eproc-btn-outline"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="margin-right:4px;vertical-align:-2px;"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/></svg>
                                            <?php echo esc_html__( 'Download', 'eprocurement' ); ?>
                                        </a>
                                        <?php
                                        $file_ext = strtolower( pathinfo( $file->file_name, PATHINFO_EXTENSION ) );
                                        if ( $file_ext === 'pdf' ) :
                                            $preview_url = Eprocurement_Downloads::get_download_link( (int) $file->id, 'supporting' );
                                        ?>
                                        <button type="button" class="eproc-btn eproc-btn-sm eproc-btn-outline eproc-preview-btn" data-preview-url="<?php echo esc_attr( $preview_url ); ?>" data-preview-name="<?php echo esc_attr( $file->label ?: $file->file_name ); ?>">
                                            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="margin-right:4px;vertical-align:-2px;"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                                            <?php echo esc_html__( 'Preview', 'eprocurement' ); ?>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="eproc-detail-section eproc-contacts-section">
            <h2 class="eproc-section-title"><?php echo esc_html__( 'Contact Persons', 'eprocurement' ); ?></h2>
            <div class="eproc-contacts-stack">
                <?php if ( $scm_contact ) : ?>
                    <div class="eproc-contact-card">
                        <div class="eproc-contact-card-header">
                            <?php echo eprocurement_avatar( (int) $scm_contact->user_id, $scm_contact->name, 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div>
                                <span class="eproc-contact-type"><?php echo esc_html__( 'SCM Contact', 'eprocurement' ); ?></span>
                                <h3 class="eproc-contact-name"><?php echo esc_html( $scm_contact->name ); ?></h3>
                                <?php if ( $scm_contact->department ) : ?>
                                    <p class="eproc-contact-dept"><?php echo esc_html( $scm_contact->department ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="eproc-contact-details">
                            <?php if ( $scm_contact->email ) : ?>
                                <p class="eproc-contact-email">
                                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                                    <a href="mailto:<?php echo esc_attr( $scm_contact->email ); ?>">
                                        <?php echo esc_html( $scm_contact->email ); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            <?php if ( $scm_contact->phone ) : ?>
                                <p class="eproc-contact-phone">
                                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>
                                    <a href="tel:<?php echo esc_attr( $scm_contact->phone ); ?>">
                                        <?php echo esc_html( $scm_contact->phone ); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if ( $is_open_bid ) : ?>
                        <button
                            type="button"
                            class="eproc-btn eproc-btn-primary eproc-btn-sm eproc-query-btn"
                            data-contact-id="<?php echo esc_attr( (int) $scm_contact->id ); ?>"
                            data-contact-name="<?php echo esc_attr( $scm_contact->name ); ?>"
                            data-contact-type="scm"
                            data-visibility="choose"
                        >
                            <?php echo esc_html__( 'Send Query', 'eprocurement' ); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $technical_contact ) : ?>
                    <div class="eproc-contact-card">
                        <div class="eproc-contact-card-header">
                            <?php echo eprocurement_avatar( (int) $technical_contact->user_id, $technical_contact->name, 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div>
                                <span class="eproc-contact-type"><?php echo esc_html__( 'Technical Contact', 'eprocurement' ); ?></span>
                                <h3 class="eproc-contact-name"><?php echo esc_html( $technical_contact->name ); ?></h3>
                                <?php if ( $technical_contact->department ) : ?>
                                    <p class="eproc-contact-dept"><?php echo esc_html( $technical_contact->department ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="eproc-contact-details">
                            <?php if ( $technical_contact->email ) : ?>
                                <p class="eproc-contact-email">
                                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                                    <a href="mailto:<?php echo esc_attr( $technical_contact->email ); ?>">
                                        <?php echo esc_html( $technical_contact->email ); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            <?php if ( $technical_contact->phone ) : ?>
                                <p class="eproc-contact-phone">
                                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>
                                    <a href="tel:<?php echo esc_attr( $technical_contact->phone ); ?>">
                                        <?php echo esc_html( $technical_contact->phone ); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if ( $is_open_bid ) : ?>
                        <button
                            type="button"
                            class="eproc-btn eproc-btn-primary eproc-btn-sm eproc-query-btn"
                            data-contact-id="<?php echo esc_attr( (int) $technical_contact->id ); ?>"
                            data-contact-name="<?php echo esc_attr( $technical_contact->name ); ?>"
                            data-contact-type="technical"
                            data-visibility="choose"
                        >
                            <?php echo esc_html__( 'Send Query', 'eprocurement' ); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! $scm_contact && ! $technical_contact ) : ?>
                    <div class="eproc-empty-state">
                        <p class="eproc-empty-state-text"><?php echo esc_html__( 'No contact persons assigned to this tender yet.', 'eprocurement' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div><!-- .eproc-docs-contacts-row -->

    <!-- ═══════════════════════════════════ -->
    <!-- Submit Your Bid Section             -->
    <!-- ═══════════════════════════════════ -->
    <?php if ( $document->category === 'bid' && ! empty( $document->accept_online_submissions ) ) : // Only for bids with online submissions enabled ?>
    <section class="eproc-detail-section eproc-submission-section">
        <h2 class="eproc-section-title"><?php echo esc_html__( 'Submit Your Bid', 'eprocurement' ); ?></h2>

        <?php if ( ! $is_logged_in ) : ?>
            <!-- Not logged in -->
            <div class="eproc-submission-notice eproc-submission-notice--info">
                <p><?php echo esc_html__( 'You must be logged in to submit a bid.', 'eprocurement' ); ?></p>
                <a href="<?php echo esc_url( home_url( "/{$slug}/login/?redirect_to=" . rawurlencode( $_SERVER['REQUEST_URI'] ?? '' ) ) ); ?>" class="eproc-btn eproc-btn-primary eproc-btn-sm">
                    <?php echo esc_html__( 'Login', 'eprocurement' ); ?>
                </a>
                <a href="<?php echo esc_url( home_url( "/{$slug}/register/" ) ); ?>" class="eproc-btn eproc-btn-outline eproc-btn-sm">
                    <?php echo esc_html__( 'Register', 'eprocurement' ); ?>
                </a>
            </div>

        <?php elseif ( ! $is_bidder ) : ?>
            <!-- Logged in but not a bidder role -->
            <div class="eproc-submission-notice eproc-submission-notice--warning">
                <p><?php echo esc_html__( 'Only registered bidders can submit bids.', 'eprocurement' ); ?></p>
            </div>

        <?php elseif ( ! $bidder_verified ) : ?>
            <!-- Not verified -->
            <div class="eproc-submission-notice eproc-submission-notice--warning">
                <p><?php echo esc_html__( 'Please verify your email address before submitting a bid.', 'eprocurement' ); ?></p>
            </div>

        <?php elseif ( $document->status === 'closed' && empty( $document->allow_late_submissions ) ) : ?>
            <!-- Closed, no late submissions -->
            <div class="eproc-submission-notice eproc-submission-notice--closed">
                <p><?php echo esc_html__( 'Submissions for this tender are closed.', 'eprocurement' ); ?></p>
                <?php if ( $existing_sub ) : ?>
                    <p class="eproc-text-muted"><?php echo esc_html__( 'You submitted a bid before the closing date.', 'eprocurement' ); ?></p>
                <?php endif; ?>
            </div>

        <?php elseif ( $is_briefing_req && ! $bid_submissions->get_attendee_by_email( $bid_id, $current_user->user_email ) ) : ?>
            <!-- Briefing compulsory, bidder not on attendee list -->
            <div class="eproc-submission-notice eproc-submission-notice--warning">
                <p>
                    <strong><?php echo esc_html__( 'Compulsory Briefing Attendance Required', 'eprocurement' ); ?></strong><br>
                    <?php echo esc_html__( 'This tender requires attendance at the briefing session before you can submit a bid. If you attended the briefing, please contact the SCM contact to be added to the attendee list.', 'eprocurement' ); ?>
                </p>
            </div>

        <?php elseif ( $existing_sub ) : ?>
            <!-- Already submitted -->
            <div class="eproc-submission-card">
                <div class="eproc-submission-card-header">
                    <span class="eproc-submission-icon">&#10003;</span>
                    <h3><?php echo esc_html__( 'Bid Submitted', 'eprocurement' ); ?></h3>
                </div>
                <div class="eproc-submission-card-body">
                    <div class="eproc-submission-file-info">
                        <span class="eproc-submission-file-name"><?php echo esc_html( $existing_sub->file_name ); ?></span>
                        <span class="eproc-submission-file-size"><?php echo esc_html( Eprocurement_Public::format_file_size( (int) $existing_sub->file_size ) ); ?></span>
                    </div>
                    <div class="eproc-submission-meta">
                        <span class="eproc-submission-date">
                            <?php
                            printf(
                                /* translators: %s: submission date */
                                esc_html__( 'Submitted: %s', 'eprocurement' ),
                                esc_html( date_i18n( 'j M Y, H:i', strtotime( $existing_sub->submitted_at ) ) )
                            );
                            ?>
                        </span>
                        <?php if ( (int) $existing_sub->is_late ) : ?>
                            <span class="eproc-badge-late"><?php echo esc_html__( 'Late Submission', 'eprocurement' ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( $can_cancel_result === true ) : ?>
                    <div class="eproc-submission-card-actions">
                        <button type="button" class="eproc-btn eproc-btn-outline eproc-btn-sm" id="eproc-cancel-submission" data-id="<?php echo esc_attr( (int) $existing_sub->id ); ?>">
                            <?php echo esc_html__( 'Cancel & Resubmit', 'eprocurement' ); ?>
                        </button>
                        <small class="eproc-text-muted"><?php echo esc_html__( 'Your current submission will be removed so you can upload a new one.', 'eprocurement' ); ?></small>
                    </div>
                <?php endif; ?>
            </div>

        <?php else : ?>
            <!-- Can submit — show upload zone -->
            <?php
            $is_closing_passed = $document->closing_date && strtotime( $document->closing_date ) < time();
            ?>
            <?php if ( $is_closing_passed && ! empty( $document->allow_late_submissions ) ) : ?>
                <div class="eproc-submission-notice eproc-submission-notice--warning" style="margin-bottom:16px;">
                    <p><?php echo esc_html__( 'The closing date has passed. Your submission will be marked as a late submission.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>

            <?php
            // Check submission mode — per-document mode shows multiple file inputs.
            $submission_mode = $document->submission_mode ?? 'single';
            $is_per_document = ( $submission_mode === 'per_document' );

            if ( $is_per_document ) :
                // Fetch required document fields.
                $req_model = new Eprocurement_Submission_Requirements();
                $requirements = $req_model->get_requirements( $bid_id );
            ?>

            <!-- Per-Document Upload -->
            <div id="eproc-per-document-upload">
                <p class="eproc-form-hint" style="margin-bottom:16px;">
                    <?php echo esc_html__( 'Upload each required document below. Files marked as Required must be included before you can submit.', 'eprocurement' ); ?>
                </p>
                <?php if ( empty( $requirements ) ) : ?>
                    <div class="eproc-notice warning">
                        <p><?php echo esc_html__( 'No document requirements have been defined for this tender. Please contact the SCM team.', 'eprocurement' ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="eproc-per-doc-fields">
                        <?php foreach ( $requirements as $i => $req ) : ?>
                            <div class="eproc-per-doc-field" data-field-key="<?php echo esc_attr( $req->field_key ); ?>" data-required="<?php echo esc_attr( (int) $req->is_required ); ?>">
                                <div class="eproc-per-doc-label">
                                    <strong><?php echo esc_html( $req->field_label ); ?></strong>
                                    <?php if ( (int) $req->is_required ) : ?>
                                        <span class="eproc-required">*</span>
                                    <?php else : ?>
                                        <span class="eproc-optional">(<?php echo esc_html__( 'optional', 'eprocurement' ); ?>)</span>
                                    <?php endif; ?>
                                    <?php if ( $req->description ) : ?>
                                        <br><span class="eproc-text-muted" style="font-size:12px;"><?php echo esc_html( $req->description ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="eproc-per-doc-input">
                                    <input type="file" class="eproc-per-doc-file-input" data-field-key="<?php echo esc_attr( $req->field_key ); ?>" data-field-label="<?php echo esc_attr( $req->field_label ); ?>" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" />
                                    <span class="eproc-per-doc-status"></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="eproc-btn eproc-btn-primary eproc-btn-lg eproc-btn-block" id="eproc-submit-per-doc-btn" style="margin-top:20px;">
                        <?php echo esc_html__( 'Submit All Documents', 'eprocurement' ); ?>
                    </button>
                    <div id="eproc-per-doc-progress" style="display:none;margin-top:16px;">
                        <div class="eproc-progress-track">
                            <div id="eproc-per-doc-progress-bar" class="eproc-progress-fill" style="width:0%;"></div>
                        </div>
                        <p id="eproc-per-doc-status" class="eproc-upload-status-text"></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php else : ?>
            <!-- Single File Upload -->
            <div class="eproc-upload-zone eproc-submission-upload" id="eproc-submission-upload-zone">
                <div class="eproc-upload-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.5;">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                </div>
                <p class="eproc-upload-text">
                    <?php echo esc_html__( 'Drag and drop your bid document here, or click to select a file', 'eprocurement' ); ?>
                </p>
                <p class="eproc-upload-hint">
                    <?php echo esc_html__( 'Allowed: PDF, XLS, XLSX, CSV — max 10MB', 'eprocurement' ); ?>
                </p>
                <input type="file" id="eproc-submission-file-input" accept=".pdf,.xls,.xlsx,.csv" style="display:none;" />
            </div>
            <?php endif; // end per-document vs single ?>

            <!-- Upload Progress -->
            <div id="eproc-submission-progress" class="eproc-upload-progress" style="display:none;">
                <div class="eproc-progress-track">
                    <div id="eproc-submission-progress-bar" class="eproc-progress-fill" style="width:0%;"></div>
                </div>
                <p id="eproc-submission-status" class="eproc-upload-status-text"></p>
            </div>

            <div class="eproc-form-feedback" id="eproc-submission-feedback" style="display:none;"></div>
        <?php endif; ?>
    </section>
    <?php endif; // category === 'bid' ?>

        <?php if ( $is_open_bid || ! empty( $public_threads ) ) : // Hide entire Q&A section on closed bids with no threads ?>
        <section class="eproc-detail-section eproc-qa-section">
            <h2 class="eproc-section-title"><?php echo esc_html__( 'Public Questions & Answers', 'eprocurement' ); ?></h2>

        <?php if ( empty( $public_threads ) ) : ?>
            <p class="eproc-muted"><?php echo esc_html__( 'No public questions have been asked yet.', 'eprocurement' ); ?></p>
        <?php else :
            $thread_count  = count( $public_threads );
            $show_limit    = 3;
            $has_more      = $thread_count > $show_limit;
            $thread_index  = 0;
        ?>
            <div class="eproc-qa-list">
                <?php foreach ( $public_threads as $thread ) :
                    $messages = $messaging_model->get_messages( (int) $thread->id );
                    $thread_index++;
                    $hidden_class = ( $has_more && $thread_index > $show_limit ) ? ' eproc-qa-thread--hidden' : '';
                ?>
                    <div class="eproc-qa-thread<?php echo esc_attr( $hidden_class ); ?>">
                        <?php foreach ( $messages as $msg ) :
                            $sender   = get_userdata( (int) $msg->sender_id );
                            $is_staff = Eprocurement_Roles::is_staff( (int) $msg->sender_id );
                        ?>
                            <div class="eproc-qa-message <?php echo $is_staff ? 'eproc-qa-answer' : 'eproc-qa-question'; ?>">
                                <div class="eproc-qa-message-header">
                                    <span class="eproc-qa-sender">
                                        <?php echo esc_html( $sender ? $sender->display_name : __( 'Unknown', 'eprocurement' ) ); ?>
                                    </span>
                                    <?php if ( $is_staff ) : ?>
                                        <span class="eproc-qa-badge eproc-qa-badge--staff"><?php echo esc_html__( 'Official Response', 'eprocurement' ); ?></span>
                                    <?php else : ?>
                                        <span class="eproc-qa-badge eproc-qa-badge--bidder"><?php echo esc_html__( 'Question', 'eprocurement' ); ?></span>
                                    <?php endif; ?>
                                    <span class="eproc-qa-date">
                                        <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $msg->created_at ) ) ); ?>
                                    </span>
                                </div>
                                <div class="eproc-qa-message-body">
                                    <?php echo wp_kses_post( $msg->message ); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $has_more ) : ?>
                <button type="button" class="eproc-btn eproc-btn-outline eproc-qa-show-more" id="eproc-qa-toggle">
                    <?php
                    printf(
                        /* translators: %d: number of remaining threads */
                        esc_html__( 'Show %d more', 'eprocurement' ),
                        $thread_count - $show_limit
                    );
                    ?>
                </button>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ( $is_open_bid ) : ?>
        <!-- Query Action Buttons (open bids only) -->
        <div class="eproc-qa-actions">
            <?php
            // Use SCM contact if available, otherwise fall back to technical contact
            $query_contact = $scm_contact ?: $technical_contact;
            if ( $query_contact ) : ?>
                <button
                    type="button"
                    class="eproc-btn eproc-btn-outline eproc-query-btn"
                    data-contact-id="<?php echo esc_attr( (int) $query_contact->id ); ?>"
                    data-contact-name="<?php echo esc_attr( $query_contact->name ); ?>"
                    data-visibility="public"
                >
                    <?php echo esc_html__( 'Ask a Public Question', 'eprocurement' ); ?>
                </button>
                <button
                    type="button"
                    class="eproc-btn eproc-btn-outline eproc-query-btn"
                    data-contact-id="<?php echo esc_attr( (int) $query_contact->id ); ?>"
                    data-contact-name="<?php echo esc_attr( $query_contact->name ); ?>"
                    data-visibility="private"
                >
                    <?php echo esc_html__( 'Send Private Query', 'eprocurement' ); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </section>
        <?php endif; // end: show Q&A section only if open bid or has threads ?>

    <?php if ( $is_open_bid ) : // Query modal only for open bids ?>
    <!-- Query Modal -->
    <div class="eproc-modal" id="eproc-query-modal" style="display:none;">
        <div class="eproc-modal-overlay" data-dismiss="modal"></div>
        <div class="eproc-modal-dialog">
            <div class="eproc-modal-header">
                <h3 class="eproc-modal-title"><?php echo esc_html__( 'Submit a Query', 'eprocurement' ); ?></h3>
                <button type="button" class="eproc-modal-close" data-dismiss="modal" aria-label="<?php echo esc_attr__( 'Close', 'eprocurement' ); ?>">&times;</button>
            </div>
            <div class="eproc-modal-body">
                <p class="eproc-modal-info">
                    <?php
                    printf(
                        /* translators: 1: bid number, 2: contact name */
                        esc_html__( 'Regarding: %1$s — To: %2$s', 'eprocurement' ),
                        '<strong id="eproc-query-bid-ref">' . esc_html( $document->bid_number ) . '</strong>',
                        '<strong id="eproc-query-contact-name"></strong>'
                    );
                    ?>
                </p>
                <form id="eproc-query-form">
                    <input type="hidden" name="document_id" value="<?php echo esc_attr( $bid_id ); ?>" />
                    <input type="hidden" name="contact_id" id="eproc-query-contact-id" value="" />
                    <div class="eproc-form-group eproc-visibility-chooser" id="eproc-visibility-chooser">
                        <label class="eproc-label"><?php echo esc_html__( 'Query Visibility', 'eprocurement' ); ?></label>
                        <div class="eproc-visibility-options">
                            <label class="eproc-visibility-option">
                                <input type="radio" name="visibility" value="public" />
                                <span class="eproc-visibility-option-inner eproc-vis-public">
                                    <strong><?php echo esc_html__( 'Public', 'eprocurement' ); ?></strong>
                                    <small><?php echo esc_html__( 'Visible to all bidders', 'eprocurement' ); ?></small>
                                </span>
                            </label>
                            <label class="eproc-visibility-option">
                                <input type="radio" name="visibility" value="private" />
                                <span class="eproc-visibility-option-inner eproc-vis-private">
                                    <strong><?php echo esc_html__( 'Private', 'eprocurement' ); ?></strong>
                                    <small><?php echo esc_html__( 'Only you and the contact', 'eprocurement' ); ?></small>
                                </span>
                            </label>
                        </div>
                    </div>
                    <p class="eproc-modal-visibility" id="eproc-query-visibility-label"></p>
                    <div class="eproc-form-group">
                        <label for="eproc-query-message" class="eproc-label">
                            <?php echo esc_html__( 'Your Message', 'eprocurement' ); ?>
                        </label>
                        <textarea
                            id="eproc-query-message"
                            name="message"
                            class="eproc-textarea"
                            rows="5"
                            required
                            placeholder="<?php echo esc_attr__( 'Type your query here...', 'eprocurement' ); ?>"
                        ></textarea>
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php echo esc_html__( 'Attachment (optional)', 'eprocurement' ); ?></label>
                        <div class="eproc-query-attachment-row">
                            <input type="file" id="eproc-query-attachment" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="font-size:12px;" />
                            <small class="eproc-text-muted"><?php echo esc_html__( 'PDF, DOC, JPG, PNG — max 5MB', 'eprocurement' ); ?></small>
                        </div>
                    </div>
                    <?php
                    // Get current bidder's notification preference (default: enabled)
                    $bidder_notify_replies = 1;
                    if ( $is_bidder ) {
                        $bidder_profile = $bidder_model->get_profile( $current_user->ID );
                        if ( $bidder_profile && isset( $bidder_profile->notify_replies ) ) {
                            $bidder_notify_replies = (int) $bidder_profile->notify_replies;
                        }
                    }
                    ?>
                    <div class="eproc-form-group">
                        <label class="eproc-checkbox-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input
                                type="checkbox"
                                name="notify_replies"
                                id="eproc-query-notify-replies"
                                value="1"
                                <?php checked( $bidder_notify_replies, 1 ); ?>
                                style="width:auto;margin:0;"
                            />
                            <span><?php echo esc_html__( 'Email me when staff replies to this query', 'eprocurement' ); ?></span>
                        </label>
                        <small class="eproc-text-muted" style="margin-left:26px;">
                            <?php echo esc_html__( 'This sets your notification preference for all queries.', 'eprocurement' ); ?>
                        </small>
                    </div>
                    <div class="eproc-form-actions">
                        <button type="button" class="eproc-btn eproc-btn-outline" data-dismiss="modal">
                            <?php echo esc_html__( 'Cancel', 'eprocurement' ); ?>
                        </button>
                        <button type="submit" class="eproc-btn eproc-btn-primary" id="eproc-query-submit">
                            <?php echo esc_html__( 'Submit Query', 'eprocurement' ); ?>
                        </button>
                    </div>
                    <div class="eproc-form-feedback" id="eproc-query-feedback" style="display:none;"></div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; // end: query modal for open bids only ?>

</div><!-- .eproc-wrap -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ──── Countdown timer ────
    var countdownEl = document.querySelector('.eproc-countdown-timer');
    if (countdownEl) {
        var closingTs = parseInt(countdownEl.getAttribute('data-closing-timestamp'), 10);

        function pad(n) { return n < 10 ? '0' + n : '' + n; }

        function updateCountdown() {
            var now = Math.floor(Date.now() / 1000);
            var diff = closingTs - now;

            if (diff <= 0) {
                // Bid has closed — reload to show updated status.
                window.location.reload();
                return;
            }

            var days    = Math.floor(diff / 86400);
            var hours   = Math.floor((diff % 86400) / 3600);
            var minutes = Math.floor((diff % 3600) / 60);
            var seconds = diff % 60;

            var daysEl    = countdownEl.querySelector('[data-unit="days"]');
            var hoursEl   = countdownEl.querySelector('[data-unit="hours"]');
            var minutesEl = countdownEl.querySelector('[data-unit="minutes"]');
            var secondsEl = countdownEl.querySelector('[data-unit="seconds"]');

            if (daysEl)    daysEl.textContent    = pad(days);
            if (hoursEl)   hoursEl.textContent   = pad(hours);
            if (minutesEl) minutesEl.textContent = pad(minutes);
            if (secondsEl) secondsEl.textContent = pad(seconds);

            // Add urgency class when < 24h remaining.
            if (diff < 86400) {
                countdownEl.classList.add('eproc-countdown-timer--urgent');
            }
            if (diff < 3600) {
                countdownEl.classList.add('eproc-countdown-timer--critical');
            }
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    // ──── Copy-to-clipboard on bid number ────
    var copyBidBtn = document.querySelector('[data-copy-bid-number]');
    if (copyBidBtn) {
        copyBidBtn.addEventListener('click', function() {
            var bidNumber = copyBidBtn.getAttribute('data-copy-bid-number');
            if (navigator.clipboard && bidNumber) {
                navigator.clipboard.writeText(bidNumber).then(function() {
                    var original = copyBidBtn.innerHTML;
                    copyBidBtn.innerHTML = '<svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                    setTimeout(function() { copyBidBtn.innerHTML = original; }, 1500);
                });
            }
        });
    }

    // ──── Print button ────
    var printBtn = document.querySelector('[data-action="print-tender"]');
    if (printBtn) {
        printBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
    }

    // ──── Document preview modal ────
    document.addEventListener('click', function(e) {
        var previewBtn = e.target.closest('.eproc-preview-btn');
        if (!previewBtn) return;

        var url = previewBtn.getAttribute('data-preview-url');
        var name = previewBtn.getAttribute('data-preview-name');

        // Create or reuse the preview modal.
        var modal = document.getElementById('eproc-preview-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'eproc-preview-modal';
            modal.className = 'eproc-modal eproc-modal-lg';
            modal.innerHTML =
                '<div class="eproc-modal-backdrop" onclick="this.parentElement.style.display=\'none\'"></div>' +
                '<div class="eproc-modal-content" style="max-width:900px;height:85vh;">' +
                    '<div class="eproc-modal-header">' +
                        '<h2 id="eproc-preview-title"></h2>' +
                        '<button type="button" class="eproc-modal-close" onclick="document.getElementById(\'eproc-preview-modal\').style.display=\'none\'" aria-label="Close">&times;</button>' +
                    '</div>' +
                    '<div class="eproc-modal-body" style="padding:0;flex:1;overflow:hidden;">' +
                        '<iframe id="eproc-preview-iframe" style="width:100%;height:100%;border:none;" allowfullscreen></iframe>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(modal);
        }

        document.getElementById('eproc-preview-title').textContent = name;
        document.getElementById('eproc-preview-iframe').src = url;
        modal.style.display = 'flex';
    });

    // ──── Original modal logic ────
    var modal          = document.getElementById('eproc-query-modal');
    var contactIdField    = document.getElementById('eproc-query-contact-id');
    var contactName       = document.getElementById('eproc-query-contact-name');
    var visibilityLabel   = document.getElementById('eproc-query-visibility-label');
    var visibilityChooser = document.getElementById('eproc-visibility-chooser');
    var queryForm         = document.getElementById('eproc-query-form');
    var feedback          = document.getElementById('eproc-query-feedback');
    var submitBtn         = document.getElementById('eproc-query-submit');
    var slug              = (typeof eprocFrontend !== 'undefined' && eprocFrontend.slug) ? eprocFrontend.slug : 'tenders';

    if ( ! modal ) return;

    // Update visibility label when radio changes
    var visRadios = queryForm ? queryForm.querySelectorAll('input[name="visibility"]') : [];
    visRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'public') {
                visibilityLabel.textContent = '<?php echo esc_js( __( 'This question and its answer will be publicly visible to all bidders.', 'eprocurement' ) ); ?>';
                visibilityLabel.className = 'eproc-modal-visibility eproc-visibility-public';
            } else {
                visibilityLabel.textContent = '<?php echo esc_js( __( 'This query is private and only visible to you and the contact person.', 'eprocurement' ) ); ?>';
                visibilityLabel.className = 'eproc-modal-visibility eproc-visibility-private';
            }
            visibilityLabel.style.display = 'block';
        });
    });

    // Open modal on query button click — use event delegation for reliability
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.eproc-query-btn');
        if ( ! btn ) return;

        // Check login status
        if ( ! eprocFrontend.loggedIn ) {
            window.location.href = '/' + slug + '/login/?redirect_to=' + encodeURIComponent(window.location.href);
            return;
        }

        feedback.style.display = 'none';
        queryForm.reset();

        contactIdField.value = btn.getAttribute('data-contact-id') || '';
        contactName.textContent = btn.getAttribute('data-contact-name') || '';

        var visibility = btn.getAttribute('data-visibility') || 'choose';

        // Always show the visibility chooser so bidder can select
        visibilityChooser.style.display = '';

        if ( visibility === 'public' || visibility === 'private' ) {
            // Pre-select the radio but still allow changing
            var radio = queryForm.querySelector('input[name="visibility"][value="' + visibility + '"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        } else {
            // "choose" mode — no pre-selection, hide label until chosen
            visibilityLabel.textContent = '';
            visibilityLabel.style.display = 'none';
        }

        modal.style.display = 'flex';
    });

    // Dismiss modal
    document.querySelectorAll('[data-dismiss="modal"]').forEach(function(el) {
        el.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    });

    // Submit query
    if ( queryForm ) {
        queryForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var message = document.getElementById('eproc-query-message').value.trim();
            if ( ! message ) return;

            submitBtn.disabled = true;
            submitBtn.textContent = eprocFrontend.strings.sending;
            feedback.style.display = 'none';

            var selectedVis = queryForm.querySelector('input[name="visibility"]:checked');
            if ( ! selectedVis ) {
                visibilityLabel.textContent = '<?php echo esc_js( __( 'Please select whether this query is Public or Private.', 'eprocurement' ) ); ?>';
                visibilityLabel.className = 'eproc-modal-visibility eproc-visibility-warning';
                visibilityLabel.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = '<?php echo esc_js( __( 'Submit Query', 'eprocurement' ) ); ?>';
                return;
            }

            // Use FormData if attachment is present, otherwise JSON
            var attachInput = document.getElementById('eproc-query-attachment');
            var hasAttachment = attachInput && attachInput.files && attachInput.files.length > 0;
            var notifyCheckbox = document.getElementById('eproc-query-notify-replies');
            var notifyValue = notifyCheckbox && notifyCheckbox.checked ? 1 : 0;
            var fetchOptions = { method: 'POST', headers: { 'X-WP-Nonce': eprocFrontend.nonce } };

            if ( hasAttachment ) {
                var formData = new FormData();
                formData.append('document_id', contactIdField.form.querySelector('[name="document_id"]').value);
                formData.append('contact_id', contactIdField.value);
                formData.append('visibility', selectedVis.value);
                formData.append('message', message);
                formData.append('notify_replies', notifyValue);
                formData.append('attachment', attachInput.files[0]);
                fetchOptions.body = formData;
            } else {
                fetchOptions.headers['Content-Type'] = 'application/json';
                fetchOptions.body = JSON.stringify({
                    document_id: parseInt(contactIdField.form.querySelector('[name="document_id"]').value, 10),
                    contact_id: parseInt(contactIdField.value, 10),
                    visibility: selectedVis.value,
                    message: message,
                    notify_replies: notifyValue
                });
            }

            fetch( eprocFrontend.restUrl + 'query', fetchOptions )
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if ( data.success ) {
                    feedback.className = 'eproc-form-feedback eproc-feedback-success';
                    feedback.textContent = eprocFrontend.strings.sent;
                    feedback.style.display = 'block';
                    queryForm.reset();
                    setTimeout(function() { modal.style.display = 'none'; }, 2000);
                } else {
                    feedback.className = 'eproc-form-feedback eproc-feedback-error';
                    feedback.textContent = data.error || data.message || eprocFrontend.strings.error;
                    feedback.style.display = 'block';
                }
                submitBtn.disabled = false;
                submitBtn.textContent = '<?php echo esc_js( __( 'Submit Query', 'eprocurement' ) ); ?>';
            })
            .catch(function() {
                feedback.className = 'eproc-form-feedback eproc-feedback-error';
                feedback.textContent = eprocFrontend.strings.error;
                feedback.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = '<?php echo esc_js( __( 'Submit Query', 'eprocurement' ) ); ?>';
            });
        });
    }

    // Q&A "Show more" toggle
    var qaToggle = document.getElementById('eproc-qa-toggle');
    if (qaToggle) {
        qaToggle.addEventListener('click', function() {
            var hiddenThreads = document.querySelectorAll('.eproc-qa-thread--hidden');
            hiddenThreads.forEach(function(el) {
                el.classList.remove('eproc-qa-thread--hidden');
            });
            qaToggle.style.display = 'none';
        });
    }

    // =====================
    // Bid Submission Upload
    // =====================
    var subUploadZone = document.getElementById('eproc-submission-upload-zone');
    var subFileInput  = document.getElementById('eproc-submission-file-input');

    if (subUploadZone && subFileInput) {
        subUploadZone.addEventListener('click', function(e) {
            if (e.target === subFileInput) return;
            subFileInput.click();
        });

        subUploadZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        subUploadZone.addEventListener('dragenter', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        subUploadZone.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });

        subUploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                uploadSubmission(e.dataTransfer.files[0]);
            }
        });

        subFileInput.addEventListener('change', function() {
            if (this.files.length) {
                uploadSubmission(this.files[0]);
                this.value = '';
            }
        });
    }

    function uploadSubmission(file) {
        var progressEl  = document.getElementById('eproc-submission-progress');
        var progressBar = document.getElementById('eproc-submission-progress-bar');
        var statusText  = document.getElementById('eproc-submission-status');
        var feedbackEl  = document.getElementById('eproc-submission-feedback');

        feedbackEl.style.display = 'none';
        progressEl.style.display = 'block';
        statusText.textContent   = '<?php echo esc_js( __( 'Uploading...', 'eprocurement' ) ); ?> ' + file.name;

        var formData = new FormData();
        formData.append('document_id', <?php echo wp_json_encode( $bid_id ); ?>);
        formData.append('file', file);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', eprocFrontend.restUrl + 'submissions', true);
        xhr.setRequestHeader('X-WP-Nonce', eprocFrontend.nonce);

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = pct + '%';
            }
        });

        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                if (xhr.status >= 200 && xhr.status < 300 && data.id) {
                    feedbackEl.className = 'eproc-form-feedback eproc-feedback-success';
                    feedbackEl.textContent = '<?php echo esc_js( __( 'Bid submitted successfully! Refreshing...', 'eprocurement' ) ); ?>';
                    feedbackEl.style.display = 'block';
                    progressEl.style.display = 'none';
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    feedbackEl.className = 'eproc-form-feedback eproc-feedback-error';
                    feedbackEl.textContent = data.message || '<?php echo esc_js( __( 'Upload failed. Please try again.', 'eprocurement' ) ); ?>';
                    feedbackEl.style.display = 'block';
                    progressEl.style.display = 'none';
                }
            } catch (err) {
                feedbackEl.className = 'eproc-form-feedback eproc-feedback-error';
                feedbackEl.textContent = '<?php echo esc_js( __( 'An error occurred.', 'eprocurement' ) ); ?>';
                feedbackEl.style.display = 'block';
                progressEl.style.display = 'none';
            }
        };

        xhr.onerror = function() {
            feedbackEl.className = 'eproc-form-feedback eproc-feedback-error';
            feedbackEl.textContent = '<?php echo esc_js( __( 'Network error. Please try again.', 'eprocurement' ) ); ?>';
            feedbackEl.style.display = 'block';
            progressEl.style.display = 'none';
        };

        xhr.send(formData);
    }

    // Cancel submission
    var cancelBtn = document.getElementById('eproc-cancel-submission');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            if (!confirm('<?php echo esc_js( __( 'Are you sure you want to cancel your submission? Your uploaded file will be removed.', 'eprocurement' ) ); ?>')) {
                return;
            }
            var subId = this.getAttribute('data-id');
            cancelBtn.disabled = true;
            cancelBtn.textContent = '<?php echo esc_js( __( 'Cancelling...', 'eprocurement' ) ); ?>';

            fetch(eprocFrontend.restUrl + 'submissions/' + subId, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': eprocFrontend.nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || '<?php echo esc_js( __( 'Failed to cancel. Please try again.', 'eprocurement' ) ); ?>');
                    cancelBtn.disabled = false;
                    cancelBtn.textContent = '<?php echo esc_js( __( 'Cancel & Resubmit', 'eprocurement' ) ); ?>';
                }
            })
            .catch(function() {
                alert('<?php echo esc_js( __( 'Network error.', 'eprocurement' ) ); ?>');
                cancelBtn.disabled = false;
                cancelBtn.textContent = '<?php echo esc_js( __( 'Cancel & Resubmit', 'eprocurement' ) ); ?>';
            });
        });
    }

    // ──── Per-document submission ────
    var perDocSubmitBtn = document.getElementById('eproc-submit-per-doc-btn');
    if (perDocSubmitBtn) {
        perDocSubmitBtn.addEventListener('click', function() {
            var inputs = document.querySelectorAll('.eproc-per-doc-file-input');
            var hasFiles = false;
            var missingRequired = [];

            inputs.forEach(function(input) {
                if (input.files.length > 0) {
                    hasFiles = true;
                    var status = input.parentElement.querySelector('.eproc-per-doc-status');
                    if (status) status.textContent = '';
                } else {
                    var field = input.closest('.eproc-per-doc-field');
                    var isRequired = field.getAttribute('data-required') === '1';
                    var label = input.getAttribute('data-field-label');
                    if (isRequired) {
                        missingRequired.push(label);
                        var status = input.parentElement.querySelector('.eproc-per-doc-status');
                        if (status) status.innerHTML = '<span style="color:#dc2626;font-size:12px;"><?php echo esc_js( __( 'Required', 'eprocurement' ) ); ?></span>';
                    }
                }
            });

            if (missingRequired.length > 0) {
                alert('<?php echo esc_js( __( 'Please upload all required documents:', 'eprocurement' ) ); ?>\n\n' + missingRequired.join('\n'));
                return;
            }

            if (!hasFiles) {
                alert('<?php echo esc_js( __( 'Please select at least one file to upload.', 'eprocurement' ) ); ?>');
                return;
            }

            // Build FormData with all selected files.
            var formData = new FormData();
            formData.append('document_id', <?php echo esc_js( $bid_id ); ?>);
            formData.append('submission_mode', 'per_document');

            inputs.forEach(function(input) {
                if (input.files.length > 0) {
                    var fieldKey = input.getAttribute('data-field-key');
                    var fieldLabel = input.getAttribute('data-field-label');
                    var safeName = sanitize_file_name(fieldLabel);
                    formData.append('files[' + fieldKey + ']', input.files[0], safeName + '_' + fieldKey);
                }
            });

            // Progress UI.
            var progressEl = document.getElementById('eproc-per-doc-progress');
            var progressBar = document.getElementById('eproc-per-doc-progress-bar');
            var statusEl = document.getElementById('eproc-per-doc-status');
            var feedbackEl = document.getElementById('eproc-submission-feedback');

            if (feedbackEl) feedbackEl.style.display = 'none';
            if (progressEl) progressEl.style.display = 'block';
            if (progressBar) progressBar.style.width = '0%';
            if (statusEl) statusEl.textContent = '<?php echo esc_js( __( 'Uploading documents...', 'eprocurement' ) ); ?>';
            perDocSubmitBtn.disabled = true;
            perDocSubmitBtn.textContent = '<?php echo esc_js( __( 'Uploading...', 'eprocurement' ) ); ?>';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', eprocFrontend.restUrl + 'submissions');
            xhr.setRequestHeader('X-WP-Nonce', eprocFrontend.nonce);

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var pct = Math.round((e.loaded / e.total) * 100);
                    if (progressBar) progressBar.style.width = pct + '%';
                    if (statusEl) statusEl.textContent = '<?php echo esc_js( __( 'Uploading...', 'eprocurement' ) ); ?> ' + pct + '%';
                }
            };

            xhr.onload = function() {
                var data = JSON.parse(xhr.responseText || '{}');
                if (xhr.status === 200 || xhr.status === 201) {
                    if (feedbackEl) {
                        feedbackEl.className = 'eproc-form-feedback eproc-feedback-success';
                        feedbackEl.textContent = '<?php echo esc_js( __( 'Bid submitted successfully! Refreshing...', 'eprocurement' ) ); ?>';
                        feedbackEl.style.display = 'block';
                    }
                    if (progressEl) progressEl.style.display = 'none';
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    if (feedbackEl) {
                        feedbackEl.className = 'eproc-form-feedback eproc-feedback-error';
                        feedbackEl.textContent = data.error || data.message || '<?php echo esc_js( __( 'Upload failed. Please try again.', 'eprocurement' ) ); ?>';
                        feedbackEl.style.display = 'block';
                    }
                    if (progressEl) progressEl.style.display = 'none';
                    perDocSubmitBtn.disabled = false;
                    perDocSubmitBtn.textContent = '<?php echo esc_js( __( 'Submit All Documents', 'eprocurement' ) ); ?>';
                }
            };

            xhr.onerror = function() {
                if (feedbackEl) {
                    feedbackEl.className = 'eproc-form-feedback eproc-feedback-error';
                    feedbackEl.textContent = '<?php echo esc_js( __( 'Network error. Please try again.', 'eprocurement' ) ); ?>';
                    feedbackEl.style.display = 'block';
                }
                if (progressEl) progressEl.style.display = 'none';
                perDocSubmitBtn.disabled = false;
                perDocSubmitBtn.textContent = '<?php echo esc_js( __( 'Submit All Documents', 'eprocurement' ) ); ?>';
            };

            xhr.send(formData);

            function sanitize_file_name(name) {
                return name.replace(/[^a-zA-Z0-9_\-]/g, '_').substring(0, 50);
            }
        });
    }
});
</script>
