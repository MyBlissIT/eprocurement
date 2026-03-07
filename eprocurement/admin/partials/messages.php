<?php
/**
 * Admin messaging inbox partial.
 *
 * Displays a two-pane layout: thread list (left) and conversation view (right).
 * Includes search, filter tabs (All / Unread / Public), and "Reply as" label.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
    $active_thread = $messaging->get_thread( $active_thread_id, get_current_user_id() );
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
$open_total    = 0;
foreach ( $threads as $t ) {
    if ( $t->visibility === 'public' ) {
        $public_total++;
    }
    if ( $t->status === 'open' ) {
        $open_total++;
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
                <button type="button" class="eproc-thread-tab" data-filter="open">
                    <?php esc_html_e( 'Open', 'eprocurement' ); ?>
                    <span class="count"><?php echo esc_html( $open_total ); ?></span>
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
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-messages&thread_id=' . absint( $thread->id ) ) ); ?>"
                           class="eproc-thread-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $unread ? 'has-unread' : ''; ?>"
                           data-visibility="<?php echo esc_attr( $thread->visibility ); ?>"
                           data-status="<?php echo esc_attr( $thread->status ); ?>"
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
                                <?php $frontend_slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' ); ?>
                                <a href="#" class="eproc-bid-preview-link" data-bid-url="<?php echo esc_url( Eprocurement_Public::bid_url( absint( $thread_document->id ) ) ); ?>" title="<?php esc_attr_e( 'View bid details', 'eprocurement' ); ?>">
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

                            // Show notification preference indicator
                            if ( $thread_bidder ) {
                                global $wpdb;
                                $bp_tbl = Eprocurement_Database::table( 'bidder_profiles' );
                                $bidder_notify = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT notify_replies FROM {$bp_tbl} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                    (int) $thread_bidder->ID
                                ) );
                                if ( $bidder_notify !== null && (int) $bidder_notify === 1 ) {
                                    echo ' <span class="eproc-notify-indicator eproc-notify-on" title="' . esc_attr__( 'Email notifications enabled', 'eprocurement' ) . '" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:#1a7a3f;margin-left:6px;">&#9993; ' . esc_html__( 'Notifications on', 'eprocurement' ) . '</span>';
                                } elseif ( $bidder_notify !== null && (int) $bidder_notify === 0 ) {
                                    echo ' <span class="eproc-notify-indicator eproc-notify-off" title="' . esc_attr__( 'Email notifications disabled', 'eprocurement' ) . '" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:#95a5a6;margin-left:6px;">&#9993; ' . esc_html__( 'Notifications off', 'eprocurement' ) . '</span>';
                                }
                            }
                            ?>
                        </span>
                        <?php if ( $active_thread->status === 'open' && current_user_can( 'eproc_reply_threads' ) ) : ?>
                            <?php if ( $active_thread->visibility === 'private' ) : ?>
                                <button type="button" class="eproc-btn eproc-btn-sm eproc-make-public-btn" data-thread-id="<?php echo esc_attr( $active_thread_id ); ?>">
                                    <?php esc_html_e( 'Make Public', 'eprocurement' ); ?>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="eproc-btn eproc-btn-success eproc-btn-sm eproc-resolve-thread" data-thread-id="<?php echo esc_attr( $active_thread_id ); ?>">
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
                            <?php wp_nonce_field( 'eproc_admin_nonce', 'eproc_reply_nonce' ); ?>
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

<!-- Bid Preview Modal -->
<div id="eproc-bid-preview-modal" style="display:none;" role="dialog" aria-labelledby="eproc-bid-preview-title" aria-modal="true">
    <div class="eproc-modal-overlay active">
        <div class="eproc-modal eproc-modal-lg">
            <div class="eproc-modal-header">
                <h3 id="eproc-bid-preview-title"><?php esc_html_e( 'Bid Details', 'eprocurement' ); ?></h3>
                <div class="eproc-modal-header-actions">
                    <a href="#" id="eproc-bid-preview-newtab" target="_blank" class="eproc-btn eproc-btn-sm" title="<?php esc_attr_e( 'Open in new tab', 'eprocurement' ); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        <?php esc_html_e( 'New Tab', 'eprocurement' ); ?>
                    </a>
                    <button type="button" class="eproc-modal-close">&times;</button>
                </div>
            </div>
            <div class="eproc-modal-body eproc-bid-preview-body">
                <div class="eproc-bid-preview-loading">
                    <p><?php esc_html_e( 'Loading bid details...', 'eprocurement' ); ?></p>
                </div>
                <iframe id="eproc-bid-preview-iframe" src="" frameborder="0" style="width:100%;height:100%;border:none;display:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Make Public Reason Modal -->
<div id="eproc-make-public-modal" class="eproc-modal-overlay" style="display:none;" role="dialog" aria-labelledby="eproc-make-public-title" aria-modal="true">
    <div class="eproc-modal" style="max-width:440px;">
        <div class="eproc-modal-header">
            <h3 id="eproc-make-public-title"><?php esc_html_e( 'Make Thread Public', 'eprocurement' ); ?></h3>
            <button type="button" class="eproc-close-modal">&times;</button>
        </div>
        <div class="eproc-modal-body">
            <p class="eproc-text-muted" style="margin:0 0 12px"><?php esc_html_e( 'This will change the thread from private to public. All bidders will be able to see it. The original author will be notified by email.', 'eprocurement' ); ?></p>
            <div class="eproc-form-group">
                <label for="eproc-visibility-reason"><?php esc_html_e( 'Reason (sent to the bidder)', 'eprocurement' ); ?></label>
                <textarea id="eproc-visibility-reason" rows="3" class="eproc-input" placeholder="<?php esc_attr_e( 'e.g. This query is relevant to all bidders and has been made public for transparency.', 'eprocurement' ); ?>"></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
                <button type="button" class="eproc-btn eproc-btn-sm eproc-close-modal"><?php esc_html_e( 'Cancel', 'eprocurement' ); ?></button>
                <button type="button" class="eproc-btn eproc-btn-primary eproc-btn-sm" id="eproc-confirm-make-public"><?php esc_html_e( 'Confirm & Notify', 'eprocurement' ); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    // Scroll messages to bottom
    var $area = $('#eproc-messages-area');
    if ($area.length) {
        $area.scrollTop($area[0].scrollHeight);
    }

    // Search threads
    $('#eproc-thread-search-input').on('input', function() {
        var query = $(this).val().toLowerCase();
        $('.eproc-thread-item').each(function() {
            var sender  = $(this).data('sender') || '';
            var subject = $(this).data('subject') || '';
            var matches = !query || sender.indexOf(query) > -1 || subject.indexOf(query) > -1;
            $(this).toggle(matches);
        });
    });

    // Filter tabs
    $('.eproc-thread-tab').on('click', function() {
        var filter = $(this).data('filter');
        $('.eproc-thread-tab').removeClass('active');
        $(this).addClass('active');

        $('.eproc-thread-item').each(function() {
            var show = true;
            if (filter === 'open') {
                show = $(this).data('status') === 'open';
            } else if (filter === 'unread') {
                show = $(this).hasClass('has-unread');
            } else if (filter === 'public') {
                show = $(this).data('visibility') === 'public';
            }
            $(this).toggle(show);
        });
    });

    // Bid preview modal
    var $bidModal = $('#eproc-bid-preview-modal');
    var $iframe   = $('#eproc-bid-preview-iframe');

    $('.eproc-bid-preview-link').on('click', function(e) {
        e.preventDefault();
        var bidUrl = $(this).data('bid-url');
        var bidText = $(this).text().trim();

        $('#eproc-bid-preview-title').text(bidText);
        $('#eproc-bid-preview-newtab').attr('href', bidUrl);
        $('.eproc-bid-preview-loading').show();
        $iframe.hide();

        $iframe.attr('src', bidUrl);
        $iframe.on('load', function() {
            $('.eproc-bid-preview-loading').hide();
            $iframe.show();
        });

        $bidModal.show();
    });

    function closeBidModal() {
        $bidModal.hide();
        $iframe.attr('src', '');
    }

    $bidModal.on('click', '.eproc-modal-close', function(e) {
        e.preventDefault();
        closeBidModal();
    });

    $bidModal.on('click', '.eproc-modal-overlay', function(e) {
        if (e.target === this) closeBidModal();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $bidModal.is(':visible')) closeBidModal();
    });

    // Mark thread as resolved
    $('.eproc-resolve-thread').on('click', function() {
        var $btn = $(this);
        var threadId = $btn.data('thread-id');

        if (!confirm('<?php echo esc_js( __( 'Mark this thread as resolved?', 'eprocurement' ) ); ?>')) return;

        $btn.prop('disabled', true);

        $.post(eprocAdmin.ajaxUrl, {
            action:    'eproc_resolve_thread',
            nonce:     eprocAdmin.nonce,
            thread_id: threadId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                $('#eproc-message-notices').html(
                    '<div class="eproc-notice error"><p>' + (response.data.message || eprocAdmin.strings.error) + '</p></div>'
                );
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            $btn.prop('disabled', false);
        });
    });

    // Make Public modal
    var makePublicThreadId = null;
    var $publicModal = $('#eproc-make-public-modal');

    $('.eproc-make-public-btn').on('click', function() {
        makePublicThreadId = $(this).data('thread-id');
        $('#eproc-visibility-reason').val('');
        $publicModal.show();
    });

    $publicModal.on('click', '.eproc-close-modal', function() {
        $publicModal.hide();
    });

    $publicModal.on('click', function(e) {
        if (e.target === this) $publicModal.hide();
    });

    $('#eproc-confirm-make-public').on('click', function() {
        var $btn = $(this);
        var reason = $('#eproc-visibility-reason').val().trim();

        $btn.prop('disabled', true).text(eprocAdmin.strings.saving);

        $.post(eprocAdmin.ajaxUrl, {
            action:     'eproc_change_thread_visibility',
            nonce:      eprocAdmin.nonce,
            thread_id:  makePublicThreadId,
            visibility: 'public',
            reason:     reason
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : eprocAdmin.strings.error;
                $('#eproc-message-notices').html('<div class="eproc-notice error"><p>' + msg + '</p></div>');
                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Confirm & Notify', 'eprocurement' ) ); ?>');
                $publicModal.hide();
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Confirm & Notify', 'eprocurement' ) ); ?>');
        });
    });

    // Show attachment filename
    $('#eproc-reply-attachment').on('change', function() {
        var name = this.files.length ? this.files[0].name : '';
        $('#eproc-attachment-name').text(name);
    });

    // Send reply (with optional attachment)
    $('#eproc-reply-form').on('submit', function(e) {
        e.preventDefault();

        var $btn     = $('#eproc-send-reply');
        var message  = $('#eproc-reply-message').val().trim();
        var threadId = $('input[name="thread_id"]').val();

        if (!message) return;

        $btn.prop('disabled', true).text(eprocAdmin.strings.saving);

        var formData = new FormData();
        formData.append('action', 'eproc_reply_message');
        formData.append('nonce', eprocAdmin.nonce);
        formData.append('thread_id', threadId);
        formData.append('message', message);

        var fileInput = $('#eproc-reply-attachment')[0];
        if (fileInput && fileInput.files.length > 0) {
            formData.append('attachment', fileInput.files[0]);
        }

        $.ajax({
            url: eprocAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    $('#eproc-message-notices').html(
                        '<div class="eproc-notice error"><p>' + (response.data.message || eprocAdmin.strings.error) + '</p></div>'
                    );
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Reply', 'eprocurement' ) ); ?>');
                }
            },
            error: function() {
                $('#eproc-message-notices').html(
                    '<div class="eproc-notice error"><p>' + eprocAdmin.strings.error + '</p></div>'
                );
                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Reply', 'eprocurement' ) ); ?>');
            }
        });
    });
});
</script>
