<?php
/**
 * Bidder dashboard (logged-in view).
 *
 * Provides tabbed interface for:
 * - My Queries: thread list with conversation view and reply form
 * - My Downloads: audit log of files downloaded by the bidder
 * - My Profile: editable company information
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug      = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$nav_items = Eprocurement_Public::get_nav_items();

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;

// Fetch data for all tabs
$messaging_model = new Eprocurement_Messaging();
$bidder_model    = new Eprocurement_Bidder();
$downloads_model = new Eprocurement_Downloads();

$threads = $messaging_model->get_threads_for_bidder( $user_id );
$profile = $bidder_model->get_profile( $user_id );

// Get download log for this user
global $wpdb;
$downloads_table = Eprocurement_Database::table( 'downloads' );
$supporting_table = Eprocurement_Database::table( 'supporting_docs' );
$documents_table = Eprocurement_Database::table( 'documents' );

$user_downloads = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT dl.*, sd.file_name, sd.file_size, sd.label AS file_label, d.bid_number, d.title AS bid_title
         FROM {$downloads_table} dl
         LEFT JOIN {$supporting_table} sd ON dl.supporting_doc_id = sd.id
         LEFT JOIN {$documents_table} d ON dl.document_id = d.id
         WHERE dl.user_id = %d
         ORDER BY dl.downloaded_at DESC
         LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $user_id
    )
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$logout_url = wp_nonce_url(
    add_query_arg( 'eproc_logout', '1', home_url( "/{$slug}/" ) ),
    'eproc_logout'
);

// Active tab
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'queries';
if ( ! in_array( $active_tab, [ 'queries', 'downloads', 'profile' ], true ) ) {
    $active_tab = 'queries';
}

// Profile update feedback
$profile_updated = isset( $_GET['profile_updated'] ) && $_GET['profile_updated'] === '1';
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
                <span class="eproc-nav-user"><?php echo esc_html( $current_user->display_name ); ?></span>
                <a href="<?php echo esc_url( $logout_url ); ?>" class="eproc-btn eproc-btn-outline">
                    <?php echo esc_html__( 'Logout', 'eprocurement' ); ?>
                </a>
            </div>
            <button class="eproc-navbar-toggle" aria-label="<?php echo esc_attr__( 'Toggle navigation', 'eprocurement' ); ?>">
                <span class="eproc-navbar-toggle-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <section class="eproc-dashboard-header">
        <h1 class="eproc-dashboard-title"><?php echo esc_html__( 'My Dashboard', 'eprocurement' ); ?></h1>
    </section>

    <!-- Tabs Navigation -->
    <div class="eproc-tabs">
        <div class="eproc-tabs-nav">
            <a
                href="<?php echo esc_url( add_query_arg( 'tab', 'queries', home_url( "/{$slug}/my-account/" ) ) ); ?>"
                class="eproc-tab-link <?php echo $active_tab === 'queries' ? 'eproc-tab-link--active' : ''; ?>"
                data-tab="queries"
            >
                <?php echo esc_html__( 'My Queries', 'eprocurement' ); ?>
                <?php if ( ! empty( $threads ) ) : ?>
                    <span class="eproc-tab-count"><?php echo esc_html( count( $threads ) ); ?></span>
                <?php endif; ?>
            </a>
            <a
                href="<?php echo esc_url( add_query_arg( 'tab', 'downloads', home_url( "/{$slug}/my-account/" ) ) ); ?>"
                class="eproc-tab-link <?php echo $active_tab === 'downloads' ? 'eproc-tab-link--active' : ''; ?>"
                data-tab="downloads"
            >
                <?php echo esc_html__( 'My Downloads', 'eprocurement' ); ?>
            </a>
            <a
                href="<?php echo esc_url( add_query_arg( 'tab', 'profile', home_url( "/{$slug}/my-account/" ) ) ); ?>"
                class="eproc-tab-link <?php echo $active_tab === 'profile' ? 'eproc-tab-link--active' : ''; ?>"
                data-tab="profile"
            >
                <?php echo esc_html__( 'My Profile', 'eprocurement' ); ?>
            </a>
        </div>

        <!-- ======================== -->
        <!-- TAB: My Queries          -->
        <!-- ======================== -->
        <div class="eproc-tab-panel <?php echo $active_tab === 'queries' ? 'eproc-tab-panel--active' : ''; ?>" id="eproc-tab-queries">

            <!-- Thread List -->
            <div class="eproc-thread-list" id="eproc-thread-list">
                <?php if ( empty( $threads ) ) : ?>
                    <div class="eproc-empty-state">
                        <p><?php echo esc_html__( 'You have not submitted any queries yet.', 'eprocurement' ); ?></p>
                        <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-btn eproc-btn-primary">
                            <?php echo esc_html__( 'Browse Tenders', 'eprocurement' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <?php foreach ( $threads as $thread ) :
                        $doc     = Eprocurement_Database::get_by_id( 'documents', (int) $thread->document_id );
                        $contact = Eprocurement_Database::get_by_id( 'contact_persons', (int) $thread->contact_id );
                        $msgs    = $messaging_model->get_messages( (int) $thread->id );
                        $unread  = 0;
                        foreach ( $msgs as $msg ) {
                            if ( ! $msg->is_read && (int) $msg->sender_id !== $user_id ) {
                                $unread++;
                            }
                        }
                    ?>
                        <div class="eproc-thread-item" data-thread-id="<?php echo esc_attr( (int) $thread->id ); ?>">
                            <div class="eproc-thread-item-header">
                                <span class="eproc-thread-bid-ref">
                                    <?php echo esc_html( $doc ? $doc->bid_number : __( 'Unknown', 'eprocurement' ) ); ?>
                                </span>
                                <span class="eproc-visibility-badge eproc-visibility-badge--<?php echo esc_attr( $thread->visibility ); ?>">
                                    <?php echo esc_html( ucfirst( $thread->visibility ) ); ?>
                                </span>
                                <?php if ( $unread > 0 ) : ?>
                                    <span class="eproc-unread-badge"><?php echo esc_html( $unread ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="eproc-thread-item-body">
                                <p class="eproc-thread-subject"><?php echo esc_html( $thread->subject ); ?></p>
                                <p class="eproc-thread-meta">
                                    <?php
                                    printf(
                                        /* translators: 1: contact name, 2: message count */
                                        esc_html__( 'Contact: %1$s | %2$d messages', 'eprocurement' ),
                                        esc_html( $contact ? $contact->name : __( 'Unknown', 'eprocurement' ) ),
                                        count( $msgs )
                                    );
                                    ?>
                                </p>
                            </div>
                            <div class="eproc-thread-item-action">
                                <button
                                    type="button"
                                    class="eproc-btn eproc-btn-outline eproc-btn-sm eproc-view-thread-btn"
                                    data-thread-id="<?php echo esc_attr( (int) $thread->id ); ?>"
                                >
                                    <?php echo esc_html__( 'View', 'eprocurement' ); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Thread Detail (loaded dynamically) -->
            <div class="eproc-thread-detail" id="eproc-thread-detail" style="display:none;">
                <button type="button" class="eproc-btn eproc-btn-outline eproc-btn-sm" id="eproc-back-to-threads">
                    &larr; <?php echo esc_html__( 'Back to Queries', 'eprocurement' ); ?>
                </button>
                <div class="eproc-thread-detail-header" id="eproc-thread-detail-header"></div>
                <div class="eproc-conversation" id="eproc-conversation"></div>

                <!-- Reply Form -->
                <form class="eproc-reply-form" id="eproc-reply-form">
                    <input type="hidden" name="thread_id" id="eproc-reply-thread-id" value="" />
                    <div class="eproc-form-group">
                        <label for="eproc-reply-message" class="eproc-label">
                            <?php echo esc_html__( 'Your Reply', 'eprocurement' ); ?>
                        </label>
                        <textarea
                            id="eproc-reply-message"
                            name="message"
                            class="eproc-textarea"
                            rows="4"
                            required
                            placeholder="<?php echo esc_attr__( 'Type your reply...', 'eprocurement' ); ?>"
                        ></textarea>
                    </div>
                    <div class="eproc-form-actions">
                        <button type="submit" class="eproc-btn eproc-btn-primary" id="eproc-reply-submit">
                            <?php echo esc_html__( 'Send Reply', 'eprocurement' ); ?>
                        </button>
                    </div>
                    <div class="eproc-form-feedback" id="eproc-reply-feedback" style="display:none;"></div>
                </form>
            </div>

        </div>

        <!-- ======================== -->
        <!-- TAB: My Downloads        -->
        <!-- ======================== -->
        <div class="eproc-tab-panel <?php echo $active_tab === 'downloads' ? 'eproc-tab-panel--active' : ''; ?>" id="eproc-tab-downloads">
            <?php if ( empty( $user_downloads ) ) : ?>
                <div class="eproc-empty-state">
                    <p><?php echo esc_html__( 'You have not downloaded any documents yet.', 'eprocurement' ); ?></p>
                </div>
            <?php else : ?>
                <div class="eproc-table-responsive">
                    <table class="eproc-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Date', 'eprocurement' ); ?></th>
                                <th><?php echo esc_html__( 'Bid Reference', 'eprocurement' ); ?></th>
                                <th><?php echo esc_html__( 'Document', 'eprocurement' ); ?></th>
                                <th><?php echo esc_html__( 'Size', 'eprocurement' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $user_downloads as $dl ) : ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dl->downloaded_at ) ) ); ?>
                                    </td>
                                    <td>
                                        <?php if ( $dl->bid_number ) : ?>
                                            <a href="<?php echo esc_url( Eprocurement_Public::bid_url( (int) $dl->document_id ) ); ?>">
                                                <?php echo esc_html( $dl->bid_number ); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="eproc-muted"><?php echo esc_html__( 'N/A', 'eprocurement' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $dl->file_label ?: ( $dl->file_name ?: __( 'Unknown file', 'eprocurement' ) ) ); ?>
                                    </td>
                                    <td>
                                        <?php echo $dl->file_size ? esc_html( Eprocurement_Public::format_file_size( (int) $dl->file_size ) ) : '—'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================== -->
        <!-- TAB: My Profile          -->
        <!-- ======================== -->
        <div class="eproc-tab-panel <?php echo $active_tab === 'profile' ? 'eproc-tab-panel--active' : ''; ?>" id="eproc-tab-profile">

            <?php if ( $profile_updated ) : ?>
                <div class="eproc-info-box eproc-info-box--success">
                    <p><?php echo esc_html__( 'Profile updated successfully.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="eproc-form-feedback" id="eproc-profile-feedback" style="display:none;"></div>

            <form id="eproc-profile-form" class="eproc-form">
                <div class="eproc-form-row eproc-form-row--2col">
                    <div class="eproc-form-group">
                        <label for="eproc-profile-first-name" class="eproc-label">
                            <?php echo esc_html__( 'First Name', 'eprocurement' ); ?>
                        </label>
                        <input
                            type="text"
                            id="eproc-profile-first-name"
                            class="eproc-input"
                            value="<?php echo esc_attr( $current_user->first_name ); ?>"
                            disabled
                        />
                        <span class="eproc-form-hint"><?php echo esc_html__( 'Contact support to change your name.', 'eprocurement' ); ?></span>
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-profile-last-name" class="eproc-label">
                            <?php echo esc_html__( 'Last Name', 'eprocurement' ); ?>
                        </label>
                        <input
                            type="text"
                            id="eproc-profile-last-name"
                            class="eproc-input"
                            value="<?php echo esc_attr( $current_user->last_name ); ?>"
                            disabled
                        />
                    </div>
                </div>

                <div class="eproc-form-group">
                    <label for="eproc-profile-email" class="eproc-label">
                        <?php echo esc_html__( 'Email Address', 'eprocurement' ); ?>
                    </label>
                    <input
                        type="email"
                        id="eproc-profile-email"
                        class="eproc-input"
                        value="<?php echo esc_attr( $current_user->user_email ); ?>"
                        disabled
                    />
                </div>

                <div class="eproc-form-row eproc-form-row--2col">
                    <div class="eproc-form-group">
                        <label for="eproc-profile-company" class="eproc-label">
                            <?php echo esc_html__( 'Company Name', 'eprocurement' ); ?>
                        </label>
                        <input
                            type="text"
                            id="eproc-profile-company"
                            name="company_name"
                            class="eproc-input"
                            value="<?php echo esc_attr( $profile ? $profile->company_name : '' ); ?>"
                            required
                        />
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-profile-company-reg" class="eproc-label">
                            <?php echo esc_html__( 'Company Reg Number', 'eprocurement' ); ?>
                        </label>
                        <input
                            type="text"
                            id="eproc-profile-company-reg"
                            name="company_reg"
                            class="eproc-input"
                            value="<?php echo esc_attr( $profile ? $profile->company_reg : '' ); ?>"
                        />
                    </div>
                </div>

                <div class="eproc-form-group">
                    <label for="eproc-profile-phone" class="eproc-label">
                        <?php echo esc_html__( 'Phone Number', 'eprocurement' ); ?>
                    </label>
                    <input
                        type="tel"
                        id="eproc-profile-phone"
                        name="phone"
                        class="eproc-input"
                        value="<?php echo esc_attr( $profile ? $profile->phone : '' ); ?>"
                    />
                </div>

                <div class="eproc-form-group">
                    <label class="eproc-label"><?php echo esc_html__( 'Email Notifications', 'eprocurement' ); ?></label>
                    <label class="eproc-checkbox-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal;">
                        <input
                            type="checkbox"
                            name="notify_replies"
                            id="eproc-profile-notify-replies"
                            value="1"
                            <?php checked( $profile && isset( $profile->notify_replies ) ? (int) $profile->notify_replies : 1, 1 ); ?>
                            style="width:auto;margin:0;"
                        />
                        <span><?php echo esc_html__( 'Receive email notifications when staff replies to my queries', 'eprocurement' ); ?></span>
                    </label>
                </div>

                <div class="eproc-form-group">
                    <label class="eproc-label"><?php echo esc_html__( 'Verification Status', 'eprocurement' ); ?></label>
                    <?php if ( $profile && (int) $profile->verified === 1 ) : ?>
                        <span class="eproc-verification-status eproc-verification-status--verified">
                            <?php echo esc_html__( 'Verified', 'eprocurement' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="eproc-verification-status eproc-verification-status--pending">
                            <?php echo esc_html__( 'Not Verified', 'eprocurement' ); ?>
                        </span>
                        <span class="eproc-form-hint">
                            <?php echo esc_html__( 'Check your email for the verification link. You must verify before submitting queries.', 'eprocurement' ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="eproc-form-actions">
                    <button type="submit" class="eproc-btn eproc-btn-primary" id="eproc-profile-submit">
                        <?php echo esc_html__( 'Update Profile', 'eprocurement' ); ?>
                    </button>
                </div>
            </form>
        </div>

    </div><!-- .eproc-tabs -->

</div><!-- .eproc-wrap -->

<script>
(function() {
    var slug = eprocFrontend.slug || 'tenders';

    // =====================
    // Tab Switching (client-side enhancement for faster UX)
    // =====================
    var tabLinks  = document.querySelectorAll('.eproc-tab-link');
    var tabPanels = document.querySelectorAll('.eproc-tab-panel');

    tabLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var tab = link.getAttribute('data-tab');

            tabLinks.forEach(function(l) { l.classList.remove('eproc-tab-link--active'); });
            tabPanels.forEach(function(p) { p.classList.remove('eproc-tab-panel--active'); });

            link.classList.add('eproc-tab-link--active');
            var panel = document.getElementById('eproc-tab-' + tab);
            if ( panel ) panel.classList.add('eproc-tab-panel--active');

            // Update URL without reload
            if ( window.history && window.history.replaceState ) {
                var url = new URL(window.location);
                url.searchParams.set('tab', tab);
                window.history.replaceState(null, '', url.toString());
            }
        });
    });

    // =====================
    // Thread Detail View
    // =====================
    var threadList    = document.getElementById('eproc-thread-list');
    var threadDetail  = document.getElementById('eproc-thread-detail');
    var backBtn       = document.getElementById('eproc-back-to-threads');
    var detailHeader  = document.getElementById('eproc-thread-detail-header');
    var conversation  = document.getElementById('eproc-conversation');
    var replyForm     = document.getElementById('eproc-reply-form');
    var replyThreadId = document.getElementById('eproc-reply-thread-id');
    var replyFeedback = document.getElementById('eproc-reply-feedback');
    var replySubmit   = document.getElementById('eproc-reply-submit');

    // View thread button handlers
    document.querySelectorAll('.eproc-view-thread-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            loadThread( parseInt(btn.getAttribute('data-thread-id'), 10) );
        });
    });

    // Back button
    if ( backBtn ) {
        backBtn.addEventListener('click', function() {
            threadDetail.style.display = 'none';
            threadList.style.display = '';
        });
    }

    function loadThread(threadId) {
        fetch( eprocFrontend.restUrl + 'threads/' + threadId, {
            headers: { 'X-WP-Nonce': eprocFrontend.nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if ( data.error ) {
                alert(data.error);
                return;
            }

            // Show detail, hide list
            threadList.style.display = 'none';
            threadDetail.style.display = '';

            // Render header
            detailHeader.innerHTML =
                '<h3>' + escHtml(data.thread.subject) + '</h3>' +
                '<span class="eproc-visibility-badge eproc-visibility-badge--' + escHtml(data.thread.visibility) + '">' +
                    escHtml(data.thread.visibility.charAt(0).toUpperCase() + data.thread.visibility.slice(1)) +
                '</span>';

            // Render messages
            conversation.innerHTML = '';
            data.messages.forEach(function(msg) {
                var bubbleClass = msg.is_staff ? 'eproc-bubble--staff' : 'eproc-bubble--bidder';
                var bubble = document.createElement('div');
                bubble.className = 'eproc-bubble ' + bubbleClass;
                bubble.innerHTML =
                    '<div class="eproc-bubble-header">' +
                        '<span class="eproc-bubble-sender">' + escHtml(msg.sender_name) + '</span>' +
                        (msg.is_staff ? ' <span class="eproc-qa-badge eproc-qa-badge--staff">Staff</span>' : '') +
                        '<span class="eproc-bubble-date">' + escHtml(msg.created_at) + '</span>' +
                    '</div>' +
                    '<div class="eproc-bubble-body">' + escHtml(msg.message) + '</div>';

                // Attachments
                if ( msg.attachments && msg.attachments.length ) {
                    var attHtml = '<div class="eproc-bubble-attachments">';
                    msg.attachments.forEach(function(att) {
                        attHtml += '<a href="' + escAttr(att.download_url) + '" class="eproc-attachment-link" target="_blank" rel="noopener noreferrer">' +
                            escHtml(att.file_name) + '</a> ';
                    });
                    attHtml += '</div>';
                    bubble.innerHTML += attHtml;
                }

                conversation.appendChild(bubble);
            });

            // Set up reply form
            replyThreadId.value = threadId;
            replyFeedback.style.display = 'none';
        })
        .catch(function() {
            alert(eprocFrontend.strings.error);
        });
    }

    // Reply submission
    if ( replyForm ) {
        replyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var message = document.getElementById('eproc-reply-message').value.trim();
            if ( ! message ) return;

            replySubmit.disabled = true;
            replySubmit.textContent = eprocFrontend.strings.sending;
            replyFeedback.style.display = 'none';

            fetch( eprocFrontend.restUrl + 'reply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': eprocFrontend.nonce
                },
                body: JSON.stringify({
                    thread_id: parseInt(replyThreadId.value, 10),
                    message: message
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if ( data.success ) {
                    replyFeedback.className = 'eproc-form-feedback eproc-feedback-success';
                    replyFeedback.textContent = '<?php echo esc_js( __( 'Reply sent successfully.', 'eprocurement' ) ); ?>';
                    replyFeedback.style.display = 'block';
                    document.getElementById('eproc-reply-message').value = '';
                    // Reload thread to show new message
                    loadThread( parseInt(replyThreadId.value, 10) );
                } else {
                    replyFeedback.className = 'eproc-form-feedback eproc-feedback-error';
                    replyFeedback.textContent = data.error || eprocFrontend.strings.error;
                    replyFeedback.style.display = 'block';
                }
                replySubmit.disabled = false;
                replySubmit.textContent = '<?php echo esc_js( __( 'Send Reply', 'eprocurement' ) ); ?>';
            })
            .catch(function() {
                replyFeedback.className = 'eproc-form-feedback eproc-feedback-error';
                replyFeedback.textContent = eprocFrontend.strings.error;
                replyFeedback.style.display = 'block';
                replySubmit.disabled = false;
                replySubmit.textContent = '<?php echo esc_js( __( 'Send Reply', 'eprocurement' ) ); ?>';
            });
        });
    }

    // =====================
    // Profile Update
    // =====================
    var profileForm     = document.getElementById('eproc-profile-form');
    var profileFeedback = document.getElementById('eproc-profile-feedback');
    var profileSubmit   = document.getElementById('eproc-profile-submit');

    if ( profileForm ) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            profileSubmit.disabled = true;
            profileFeedback.style.display = 'none';

            var notifyCheckbox = profileForm.querySelector('[name="notify_replies"]');
            var formData = {
                company_name:   profileForm.querySelector('[name="company_name"]').value.trim(),
                company_reg:    profileForm.querySelector('[name="company_reg"]').value.trim(),
                phone:          profileForm.querySelector('[name="phone"]').value.trim(),
                notify_replies: notifyCheckbox && notifyCheckbox.checked ? 1 : 0
            };

            fetch( eprocFrontend.restUrl + 'profile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': eprocFrontend.nonce
                },
                body: JSON.stringify(formData)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if ( data.success ) {
                    profileFeedback.className = 'eproc-form-feedback eproc-feedback-success';
                    profileFeedback.textContent = '<?php echo esc_js( __( 'Profile updated successfully.', 'eprocurement' ) ); ?>';
                } else {
                    profileFeedback.className = 'eproc-form-feedback eproc-feedback-error';
                    profileFeedback.textContent = data.error || eprocFrontend.strings.error;
                }
                profileFeedback.style.display = 'block';
                profileSubmit.disabled = false;
            })
            .catch(function() {
                profileFeedback.className = 'eproc-form-feedback eproc-feedback-error';
                profileFeedback.textContent = eprocFrontend.strings.error;
                profileFeedback.style.display = 'block';
                profileSubmit.disabled = false;
            });
        });
    }

    // =====================
    // Helpers
    // =====================
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
