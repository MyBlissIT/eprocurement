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
    <section class="eproc-detail-header eproc-detail-header--combined">
        <div class="eproc-detail-header-left">
            <?php echo Eprocurement_Public::status_badge( $document->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <h1 class="eproc-detail-title"><?php echo esc_html( $document->title ); ?></h1>
            <p class="eproc-detail-bid-number"><?php echo esc_html( $document->bid_number ); ?></p>
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
        </div>
    </section>

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
                                        <span class="eproc-file-label">
                                            <?php echo esc_html( $file->label ?: $file->file_name ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( Eprocurement_Public::format_file_size( (int) $file->file_size ) ); ?></td>
                                    <td>
                                        <a
                                            href="<?php echo esc_url( Eprocurement_Downloads::get_download_link( (int) $file->id, 'supporting' ) ); ?>"
                                            class="eproc-btn eproc-btn-sm eproc-btn-outline"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <?php echo esc_html__( 'Download', 'eprocurement' ); ?>
                                        </a>
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
                        <h3 class="eproc-contact-type"><?php echo esc_html__( 'SCM Contact', 'eprocurement' ); ?></h3>
                        <p class="eproc-contact-name"><?php echo esc_html( $scm_contact->name ); ?></p>
                        <?php if ( $scm_contact->department ) : ?>
                            <p class="eproc-contact-dept"><?php echo esc_html( $scm_contact->department ); ?></p>
                        <?php endif; ?>
                        <?php if ( $scm_contact->email ) : ?>
                            <p class="eproc-contact-email">
                                <a href="mailto:<?php echo esc_attr( $scm_contact->email ); ?>">
                                    <?php echo esc_html( $scm_contact->email ); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <?php if ( $scm_contact->phone ) : ?>
                            <p class="eproc-contact-phone">
                                <a href="tel:<?php echo esc_attr( $scm_contact->phone ); ?>">
                                    <?php echo esc_html( $scm_contact->phone ); ?>
                                </a>
                            </p>
                        <?php endif; ?>
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
                        <h3 class="eproc-contact-type"><?php echo esc_html__( 'Technical Contact', 'eprocurement' ); ?></h3>
                        <p class="eproc-contact-name"><?php echo esc_html( $technical_contact->name ); ?></p>
                        <?php if ( $technical_contact->department ) : ?>
                            <p class="eproc-contact-dept"><?php echo esc_html( $technical_contact->department ); ?></p>
                        <?php endif; ?>
                        <?php if ( $technical_contact->email ) : ?>
                            <p class="eproc-contact-email">
                                <a href="mailto:<?php echo esc_attr( $technical_contact->email ); ?>">
                                    <?php echo esc_html( $technical_contact->email ); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <?php if ( $technical_contact->phone ) : ?>
                            <p class="eproc-contact-phone">
                                <a href="tel:<?php echo esc_attr( $technical_contact->phone ); ?>">
                                    <?php echo esc_html( $technical_contact->phone ); ?>
                                </a>
                            </p>
                        <?php endif; ?>
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
                    <p class="eproc-muted"><?php echo esc_html__( 'No contact persons assigned.', 'eprocurement' ); ?></p>
                <?php endif; ?>
            </div>
        </section>
    </div><!-- .eproc-docs-contacts-row -->

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

            fetch( eprocFrontend.restUrl + 'query', fetchOptions
            })
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
});
</script>
