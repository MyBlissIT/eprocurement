<?php
/**
 * Frontend Admin messaging inbox partial.
 *
 * Displays a two-pane layout: thread list (left) and conversation view (right).
 * Includes search, filter tabs (All / Unread / Public), and "Reply as" label.
 * Adapted from admin/partials/messages.php for the frontend manage panel.
 * Uses REST API (eprocAPI) instead of WP AJAX for replies and resolve actions.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug         = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$manage_base  = home_url( "/{$slug}/manage" );

$messaging  = new Eprocurement_Messaging();
$user_id    = get_current_user_id();

// Get threads
$inbox = $messaging->get_admin_inbox( [
    'per_page' => 50,
    'page'     => 1,
] );

$threads = $inbox['items'];

// Active thread
$active_thread_id = absint( $_GET['thread_id'] ?? 0 );
$active_thread    = null;
$active_messages  = [];
$thread_document  = null;
$thread_bidder    = null;

if ( $active_thread_id ) {
    $active_thread = $messaging->get_thread( $active_thread_id );
    if ( $active_thread ) {
        $active_messages = $messaging->get_messages( $active_thread_id );
        $thread_document = Eprocurement_Database::get_by_id( 'documents', (int) $active_thread->document_id );
        $thread_bidder   = get_userdata( (int) $active_thread->bidder_id );

        // Mark as read
        $messaging->mark_thread_read( $active_thread_id, $user_id );
    }
}

// Count totals for tabs
$total_threads = count( $threads );
$unread_total  = 0;
$public_total  = 0;
foreach ( $threads as $t ) {
    if ( $t->visibility === 'public' ) {
        $public_total++;
    }
    $t_messages = $messaging->get_messages( (int) $t->id );
    foreach ( $t_messages as $m ) {
        if ( (int) $m->sender_id !== $user_id && ! (int) $m->is_read ) {
            $unread_total++;
            break;
        }
    }
}

// Get the contact person for "Reply as" label
$reply_as_name = '';
if ( $active_thread && $thread_document ) {
    $contacts = new Eprocurement_Contact_Persons();
    $scm_id   = (int) $thread_document->scm_contact_id;
    if ( $scm_id ) {
        $contact = $contacts->get( $scm_id );
        if ( $contact ) {
            $reply_as_name = $contact->name;
        }
    }
}
?>
<div class="eproc-wrap">
    <h1><?php esc_html_e( 'Messages', 'eprocurement' ); ?></h1>

    <div id="eproc-message-notices"></div>

    <div class="eproc-messaging-panel">

        <!-- Thread List (Left) -->
        <div class="eproc-thread-list-panel">

            <!-- Search -->
            <div class="eproc-thread-search">
                <input type="text" id="eproc-thread-search-input" placeholder="<?php esc_attr_e( 'Search threads...', 'eprocurement' ); ?>">
            </div>

            <!-- Filter Tabs -->
            <div class="eproc-thread-tabs">
                <button type="button" class="eproc-thread-tab active" data-filter="all">
                    <?php esc_html_e( 'All', 'eprocurement' ); ?>
                    <span class="count"><?php echo esc_html( $total_threads ); ?></span>
                </button>
                <button type="button" class="eproc-thread-tab" data-filter="unread">
                    <?php esc_html_e( 'Unread', 'eprocurement' ); ?>
                    <span class="count"><?php echo esc_html( $unread_total ); ?></span>
                </button>
                <button type="button" class="eproc-thread-tab" data-filter="public">
                    <?php esc_html_e( 'Public', 'eprocurement' ); ?>
                    <span class="count"><?php echo esc_html( $public_total ); ?></span>
                </button>
            </div>

            <!-- Thread Items -->
            <div class="eproc-thread-items">
                <?php if ( ! empty( $threads ) ) : ?>
                    <?php foreach ( $threads as $thread ) : ?>
                        <?php
                        $sender   = get_userdata( (int) $thread->bidder_id );
                        $doc      = Eprocurement_Database::get_by_id( 'documents', (int) $thread->document_id );
                        $is_active = (int) $thread->id === $active_thread_id;

                        // Get unread count for this thread
                        $thread_messages = $messaging->get_messages( (int) $thread->id );
                        $unread = 0;
                        foreach ( $thread_messages as $msg ) {
                            if ( (int) $msg->sender_id !== $user_id && ! (int) $msg->is_read ) {
                                $unread++;
                            }
                        }
                        ?>
                        <a href="<?php echo esc_url( $manage_base . '/messages/?thread_id=' . absint( $thread->id ) ); ?>"
                           class="eproc-thread-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $unread ? 'has-unread' : ''; ?>"
                           data-visibility="<?php echo esc_attr( $thread->visibility ); ?>"
                           data-sender="<?php echo esc_attr( $sender ? strtolower( $sender->display_name ) : '' ); ?>"
                           data-subject="<?php echo esc_attr( strtolower( $doc ? $doc->bid_number . ' ' . $doc->title : $thread->subject ) ); ?>">
                            <div class="eproc-thread-item-main">
                                <div class="eproc-thread-item-info">
                                    <span class="eproc-thread-sender <?php echo $unread ? 'unread' : ''; ?>">
                                        <?php echo esc_html( $sender ? $sender->display_name : __( 'Unknown', 'eprocurement' ) ); ?>
                                    </span>
                                    <span class="eproc-thread-subject">
                                        <?php echo esc_html( $doc ? $doc->bid_number . ' - ' . $doc->title : $thread->subject ); ?>
                                    </span>
                                </div>
                                <div class="eproc-thread-item-meta">
                                    <span class="eproc-visibility-badge eproc-visibility-<?php echo esc_attr( $thread->visibility ); ?>">
                                        <?php echo esc_html( strtoupper( $thread->visibility ) ); ?>
                                    </span>
                                    <span class="eproc-thread-time">
                                        <?php echo esc_html( wp_date( 'j M, H:i', strtotime( $thread->updated_at ) ) ); ?>
                                    </span>
                                    <?php if ( $unread > 0 ) : ?>
                                        <span class="eproc-thread-unread-badge"><?php echo esc_html( $unread ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="eproc-empty-state">
                        <p><?php esc_html_e( 'No messages yet.', 'eprocurement' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Conversation View (Right) -->
        <div class="eproc-conversation-panel">
            <?php if ( $active_thread && $active_messages ) : ?>
                <!-- Thread Header -->
                <div class="eproc-conversation-header">
                    <div class="eproc-conversation-title">
                        <strong>
                            <?php if ( $thread_document ) : ?>
                                <a href="<?php echo esc_url( $manage_base . '/bids/?action=edit&id=' . absint( $thread_document->id ) ); ?>" target="_blank" title="<?php esc_attr_e( 'Open bid in new tab', 'eprocurement' ); ?>">
                                    <?php echo esc_html( $thread_document->bid_number . ' - ' . $thread_document->title ); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html( $active_thread->subject ); ?>
                            <?php endif; ?>
                        </strong>
                        <span class="eproc-visibility-badge eproc-visibility-<?php echo esc_attr( $active_thread->visibility ); ?>">
                            <?php echo esc_html( strtoupper( $active_thread->visibility ) ); ?>
                        </span>
                    </div>
                    <div class="eproc-conversation-actions">
                        <span class="eproc-conversation-from">
                            <?php
                            printf(
                                /* translators: %s: bidder name */
                                esc_html__( 'From: %s', 'eprocurement' ),
                                esc_html( $thread_bidder ? $thread_bidder->display_name : __( 'Unknown', 'eprocurement' ) )
                            );
                            ?>
                        </span>
                        <?php if ( $active_thread->status === 'open' && current_user_can( 'eproc_reply_threads' ) ) : ?>
                            <button type="button" class="eproc-btn eproc-btn-success eproc-btn-sm" id="eproc-resolve-thread" data-thread-id="<?php echo esc_attr( $active_thread_id ); ?>">
                                <?php esc_html_e( 'Mark Resolved', 'eprocurement' ); ?>
                            </button>
                        <?php elseif ( $active_thread->status === 'resolved' ) : ?>
                            <span class="eproc-badge verified"><?php esc_html_e( 'Resolved', 'eprocurement' ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages -->
                <div class="eproc-messages-area" id="eproc-messages-area">
                    <?php foreach ( $active_messages as $msg ) : ?>
                        <?php
                        $is_mine     = (int) $msg->sender_id === $user_id;
                        $msg_sender  = get_userdata( (int) $msg->sender_id );
                        $is_staff    = $msg->sender_id ? Eprocurement_Roles::is_staff( (int) $msg->sender_id ) : false;
                        $attachments = $messaging->get_attachments( (int) $msg->id );
                        ?>
                        <div class="eproc-message-row <?php echo $is_staff ? 'staff' : 'bidder'; ?>">
                            <div class="eproc-message-bubble <?php echo $is_staff ? 'staff' : 'bidder'; ?>">
                                <div class="eproc-message-meta">
                                    <strong class="eproc-message-sender <?php echo $is_staff ? 'staff' : ''; ?>">
                                        <?php echo esc_html( $msg_sender ? $msg_sender->display_name : __( 'Unknown', 'eprocurement' ) ); ?>
                                        <?php if ( $is_staff ) : ?>
                                            <span class="eproc-message-role">(<?php esc_html_e( 'Staff', 'eprocurement' ); ?>)</span>
                                        <?php endif; ?>
                                    </strong>
                                    <span class="eproc-message-time">
                                        <?php echo esc_html( wp_date( 'j M Y, H:i', strtotime( $msg->created_at ) ) ); ?>
                                    </span>
                                </div>
                                <div class="eproc-message-body">
                                    <?php echo wp_kses_post( wpautop( $msg->message ) ); ?>
                                </div>
                                <?php if ( ! empty( $attachments ) ) : ?>
                                    <div class="eproc-message-attachments">
                                        <?php foreach ( $attachments as $att ) : ?>
                                            <div class="eproc-attachment-item">
                                                <span class="eproc-attachment-icon">&#128206;</span>
                                                <?php echo esc_html( $att->file_name ); ?>
                                                <span class="eproc-attachment-size">(<?php echo esc_html( size_format( $att->file_size ) ); ?>)</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Form -->
                <?php if ( $active_thread->status !== 'resolved' && current_user_can( 'eproc_reply_threads' ) ) : ?>
                    <div class="eproc-reply-panel">
                        <?php if ( $reply_as_name ) : ?>
                            <div class="eproc-reply-as">
                                <?php
                                printf(
                                    /* translators: %s: contact person name */
                                    esc_html__( 'Reply as: %s', 'eprocurement' ),
                                    '<strong>' . esc_html( $reply_as_name ) . '</strong>'
                                );
                                ?>
                            </div>
                        <?php endif; ?>
                        <form id="eproc-reply-form" enctype="multipart/form-data">
                            <input type="hidden" name="thread_id" value="<?php echo esc_attr( $active_thread_id ); ?>">
                            <div class="eproc-form-group">
                                <textarea id="eproc-reply-message" name="message" rows="3" placeholder="<?php esc_attr_e( 'Type your reply...', 'eprocurement' ); ?>" required></textarea>
                            </div>
                            <div class="eproc-reply-footer">
                                <div class="eproc-reply-actions">
                                    <label class="eproc-btn eproc-btn-sm" title="<?php esc_attr_e( 'Attach file (PDF, DOC, JPG, PNG — max 5MB)', 'eprocurement' ); ?>">
                                        &#128206; <?php esc_html_e( 'Attach', 'eprocurement' ); ?>
                                        <input type="file" id="eproc-reply-attachment" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none;">
                                    </label>
                                    <span id="eproc-attachment-name" class="eproc-text-muted eproc-text-sm"></span>
                                </div>
                                <div class="eproc-reply-submit">
                                    <span class="eproc-reply-hint">
                                        <?php
                                        if ( $active_thread->visibility === 'public' ) {
                                            esc_html_e( 'This reply will be visible to all bidders.', 'eprocurement' );
                                        } else {
                                            esc_html_e( 'This is a private conversation.', 'eprocurement' );
                                        }
                                        ?>
                                    </span>
                                    <button type="submit" class="eproc-btn eproc-btn-primary" id="eproc-send-reply">
                                        <?php esc_html_e( 'Send Reply', 'eprocurement' ); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php elseif ( $active_thread->status === 'resolved' ) : ?>
                    <div class="eproc-reply-panel eproc-reply-resolved">
                        <?php esc_html_e( 'This thread has been resolved.', 'eprocurement' ); ?>
                    </div>
                <?php endif; ?>

            <?php else : ?>
                <!-- No thread selected -->
                <div class="eproc-empty-state eproc-conversation-empty">
                    <p><?php esc_html_e( 'Select a thread from the left to view the conversation.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // Scroll messages to bottom
    var messagesArea = document.getElementById('eproc-messages-area');
    if (messagesArea) {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    // Search threads
    var searchInput = document.getElementById('eproc-thread-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var query = this.value.toLowerCase();
            document.querySelectorAll('.eproc-thread-item').forEach(function (item) {
                var sender  = (item.dataset.sender || '').toLowerCase();
                var subject = (item.dataset.subject || '').toLowerCase();
                var matches = !query || sender.indexOf(query) > -1 || subject.indexOf(query) > -1;
                item.style.display = matches ? '' : 'none';
            });
        });
    }

    // Filter tabs
    document.querySelectorAll('.eproc-thread-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var filter = this.dataset.filter;

            document.querySelectorAll('.eproc-thread-tab').forEach(function (t) {
                t.classList.remove('active');
            });
            this.classList.add('active');

            document.querySelectorAll('.eproc-thread-item').forEach(function (item) {
                var show = true;
                if (filter === 'unread') {
                    show = item.classList.contains('has-unread');
                } else if (filter === 'public') {
                    show = item.dataset.visibility === 'public';
                }
                item.style.display = show ? '' : 'none';
            });
        });
    });

    // Mark thread as resolved (via REST API)
    var resolveBtn = document.getElementById('eproc-resolve-thread');
    if (resolveBtn) {
        resolveBtn.addEventListener('click', function () {
            var threadId = this.dataset.threadId;

            if (!confirm('<?php echo esc_js( __( 'Mark this thread as resolved?', 'eprocurement' ) ); ?>')) {
                return;
            }

            eprocSetLoading(resolveBtn, true);

            eprocAPI.patch('admin/threads/' + threadId + '/resolve', {})
                .then(function () {
                    location.reload();
                })
                .catch(function (err) {
                    document.getElementById('eproc-message-notices').innerHTML =
                        '<div class="eproc-notice error"><p>' + (err.message || 'An error occurred.') + '</p></div>';
                    eprocSetLoading(resolveBtn, false);
                });
        });
    }

    // Show attachment filename
    var attachmentInput = document.getElementById('eproc-reply-attachment');
    if (attachmentInput) {
        attachmentInput.addEventListener('change', function () {
            var nameSpan = document.getElementById('eproc-attachment-name');
            if (nameSpan) {
                nameSpan.textContent = this.files.length ? this.files[0].name : '';
            }
        });
    }

    // Send reply (with optional attachment) via REST API
    var replyForm = document.getElementById('eproc-reply-form');
    if (replyForm) {
        replyForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var sendBtn   = document.getElementById('eproc-send-reply');
            var textarea  = document.getElementById('eproc-reply-message');
            var message   = textarea ? textarea.value.trim() : '';
            var threadId  = replyForm.querySelector('input[name="thread_id"]').value;
            var fileInput = document.getElementById('eproc-reply-attachment');

            if (!message) return;

            eprocSetLoading(sendBtn, true);

            var formData = new FormData();
            formData.append('message', message);

            if (fileInput && fileInput.files.length > 0) {
                formData.append('attachment', fileInput.files[0]);
            }

            eprocAPI.post('admin/threads/' + threadId + '/reply', formData)
                .then(function () {
                    location.reload();
                })
                .catch(function (err) {
                    document.getElementById('eproc-message-notices').innerHTML =
                        '<div class="eproc-notice error"><p>' + (err.message || 'An error occurred.') + '</p></div>';
                    eprocSetLoading(sendBtn, false);
                });
        });
    }

})();
</script>
