/**
 * eProcurement Frontend JavaScript
 *
 * Handles public-facing interactions: listing filters, query form modal,
 * registration, login redirect, dashboard tabs, REST API calls.
 *
 * @package Eprocurement
 */

(function ($) {
    'use strict';

    const frontend = window.eprocFrontend || {};

    // ──── REST API Helper ────
    function apiRequest(endpoint, method, data) {
        const options = {
            method: method || 'GET',
            headers: {
                'X-WP-Nonce': frontend.nonce,
            },
        };

        if (data && method !== 'GET') {
            if (data instanceof FormData) {
                options.body = data;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        }

        let url = frontend.restUrl + endpoint;
        if (data && method === 'GET') {
            const params = new URLSearchParams(data);
            url += '?' + params.toString();
        }

        return fetch(url, options).then(function (response) {
            return response.json();
        });
    }

    // ──── Button Loading State Helpers ────
    function btnLoading($btn, text) {
        $btn.data('original-text', $btn.text());
        $btn.prop('disabled', true).addClass('eproc-btn-loading').text(text);
    }

    function btnReset($btn, text) {
        $btn.prop('disabled', false).removeClass('eproc-btn-loading').text(text || $btn.data('original-text'));
    }

    // ──── Show Notice ────
    function showNotice(message, type) {
        type = type || 'blue';
        const $notice = $('<div class="eproc-info-box"></div>').addClass(type).text(message);
        $('.eproc-wrap').prepend($notice);
        setTimeout(function () {
            $notice.fadeOut(300, function () { $(this).remove(); });
        }, 5000);
    }

    // ──── Mobile Nav Toggle (legacy) ────
    $(document).on('click', '.eproc-nav-toggle', function () {
        $(this).siblings('.eproc-nav-links').toggleClass('open');
    });

    // ──── Mobile Nav Toggle (current navbar) ────
    $(document).on('click', '.eproc-navbar-toggle', function () {
        $(this).closest('.eproc-navbar-inner').find('.eproc-navbar-links').toggleClass('open');
        $(this).closest('.eproc-navbar-inner').find('.eproc-navbar-actions').toggleClass('open');
    });

    // ──── Load More (Tender Listing) ────
    $(document).on('click', '.eproc-load-more', function () {
        const $btn = $(this);
        const page = parseInt($btn.data('page') || 1) + 1;
        const totalPages = parseInt($btn.data('total-pages') || 1);
        const status = $('[name="eproc_status"]').val() || '';
        const search = $('[name="eproc_search"]').val() || '';

        if (page > totalPages) {
            $btn.prop('disabled', true).text('No more tenders');
            return;
        }

        btnLoading($btn, 'Loading...');

        apiRequest('documents', 'GET', {
            page: page,
            per_page: 12,
            status: status,
            search: search,
        }).then(function (data) {
            if (data.items && data.items.length > 0) {
                const $grid = $('.eproc-card-grid');
                const slug = frontend.slug || 'tenders';

                data.items.forEach(function (doc) {
                    const statusColors = {
                        'draft': '#888', 'open': '#8b1a2b',
                        'closed': '#e74c3c', 'cancelled': '#95a5a6', 'archived': '#7f8c8d'
                    };
                    const color = statusColors[doc.status] || '#888';
                    const closingDate = doc.closing_date
                        ? new Date(doc.closing_date).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' })
                        : 'TBC';

                    const card = '<div class="eproc-bid-card">' +
                        '<span class="eproc-status-badge" style="background:' + color + '">' + doc.status.toUpperCase() + '</span>' +
                        '<h4>' + escHtml(doc.bid_number) + ' — ' + escHtml(doc.title) + '</h4>' +
                        '<p>' + escHtml((doc.description || '').substring(0, 120)) + '...</p>' +
                        '<div class="eproc-bid-card-footer">' +
                        'Closing: ' + closingDate +
                        (doc.contacts.scm ? ' • SCM: ' + escHtml(doc.contacts.scm) : '') +
                        ' • ' + doc.doc_count + ' docs' +
                        '</div>' +
                        '<a href="/' + slug + '/bid/' + doc.id + '/" class="eproc-btn blue" style="margin-top:8px">View Details</a>' +
                        '</div>';

                    $grid.append(card);
                });

                $btn.data('page', page);

                if (page >= data.pages) {
                    btnReset($btn, 'All tenders loaded');
                    $btn.prop('disabled', true);
                } else {
                    btnReset($btn, 'Load More');
                }
            } else {
                btnReset($btn, 'No more tenders');
                $btn.prop('disabled', true);
            }
        }).catch(function () {
            btnReset($btn, 'Load More');
            showNotice(frontend.strings?.error || 'An error occurred.', 'red');
        });
    });

    // ──── Query Modal ────
    $(document).on('click', '.eproc-send-query', function (e) {
        e.preventDefault();

        if (!frontend.loggedIn) {
            showNotice(frontend.strings?.login_required || 'Please register or log in to send queries.', 'yellow');
            return;
        }

        const contactId = $(this).data('contact-id') || '';
        const visibility = $(this).data('visibility') || 'private';

        const $modal = $('#eproc-query-modal');
        if ($modal.length) {
            $modal.find('[name="contact_id"]').val(contactId);
            if (visibility === 'public') {
                $modal.find('input[name="visibility"][value="public"]').prop('checked', true);
            } else {
                $modal.find('input[name="visibility"][value="private"]').prop('checked', true);
            }
            $modal.addClass('active');
        }
    });

    $(document).on('click', '.eproc-modal-close', function (e) {
        e.preventDefault();
        $(this).closest('.eproc-modal-overlay').removeClass('active');
    });

    $(document).on('click', '.eproc-modal-overlay', function (e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    // ──── Submit Query ────
    $(document).on('click', '#eproc-submit-query', function (e) {
        e.preventDefault();

        const $modal = $(this).closest('.eproc-modal');
        const $btn = $(this);
        const notifyCheckbox = $modal.find('[name="notify_replies"]')[0];
        const notifyValue = notifyCheckbox && notifyCheckbox.checked ? 1 : 0;
        const data = {
            document_id: $modal.find('[name="document_id"]').val(),
            contact_id: $modal.find('[name="contact_id"]').val(),
            message: $modal.find('[name="message"]').val(),
            visibility: $modal.find('[name="visibility"]:checked').val() || 'private',
            notify_replies: notifyValue,
        };

        if (!data.message.trim()) {
            showNotice('Please enter your message.', 'yellow');
            return;
        }

        btnLoading($btn, frontend.strings?.sending || 'Sending...');

        // Check if there's a file attachment
        const fileInput = $modal.find('[name="attachment"]')[0];
        if (fileInput && fileInput.files.length > 0) {
            const formData = new FormData();
            formData.append('document_id', data.document_id);
            formData.append('contact_id', data.contact_id);
            formData.append('message', data.message);
            formData.append('visibility', data.visibility);
            formData.append('notify_replies', notifyValue);
            formData.append('attachment', fileInput.files[0]);

            apiRequest('query', 'POST', formData).then(handleQueryResponse).catch(handleQueryError);
        } else {
            apiRequest('query', 'POST', data).then(handleQueryResponse).catch(handleQueryError);
        }

        function handleQueryResponse(resp) {
            if (resp.success) {
                showNotice(frontend.strings?.sent || 'Query submitted!', 'green');
                $modal.closest('.eproc-modal-overlay').removeClass('active');
                $modal.find('[name="message"]').val('');
            } else {
                showNotice(resp.error || frontend.strings?.error || 'Failed to submit query.', 'red');
            }
            btnReset($btn, 'Send Query');
        }

        function handleQueryError() {
            showNotice(frontend.strings?.error || 'An error occurred.', 'red');
            btnReset($btn, 'Send Query');
        }
    });

    // ──── Registration Form ────
    $(document).on('submit', '#eproc-register-form', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');

        // Validate
        const password = $form.find('[name="password"]').val();
        const confirm = $form.find('[name="password_confirm"]').val();

        if (password.length < 8) {
            showNotice('Password must be at least 8 characters.', 'red');
            return;
        }

        if (password !== confirm) {
            showNotice('Passwords do not match.', 'red');
            return;
        }

        btnLoading($btn, frontend.strings?.registering || 'Creating your account...');

        const data = {
            first_name: $form.find('[name="first_name"]').val(),
            last_name: $form.find('[name="last_name"]').val(),
            company_name: $form.find('[name="company_name"]').val(),
            company_reg: $form.find('[name="company_reg"]').val(),
            phone: $form.find('[name="phone"]').val(),
            email: $form.find('[name="email"]').val(),
            password: password,
        };

        apiRequest('register', 'POST', data).then(function (resp) {
            if (resp.success) {
                showNotice(frontend.strings?.registered || 'Registration successful! Check your email.', 'green');
                $form[0].reset();
                setTimeout(function () {
                    const slug = frontend.slug || 'tenders';
                    window.location.href = '/' + slug + '/login/?registered=1';
                }, 3000);
            } else {
                showNotice(resp.error || 'Registration failed.', 'red');
            }
            btnReset($btn, 'Create Account & Send Verification Email');
        }).catch(function () {
            showNotice(frontend.strings?.error || 'An error occurred.', 'red');
            btnReset($btn, 'Create Account & Send Verification Email');
        });
    });

    // ──── Dashboard Tabs ────
    $(document).on('click', '.eproc-tab', function (e) {
        e.preventDefault();
        const tab = $(this).data('tab');

        $('.eproc-tab').removeClass('active');
        $(this).addClass('active');

        $('.eproc-tab-content').removeClass('active');
        $('#eproc-tab-' + tab).addClass('active');

        // Update URL without reload
        if (window.history.pushState) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }
    });

    // ──── Dashboard Thread View ────
    $(document).on('click', '.eproc-view-thread', function (e) {
        e.preventDefault();
        const threadId = $(this).data('thread-id');
        const $detail = $('#eproc-thread-detail');

        $detail.html('<p style="text-align:center;color:#888;padding:20px">Loading...</p>');

        apiRequest('threads/' + threadId, 'GET').then(function (data) {
            if (data.error) {
                $detail.html('<p class="eproc-info-box red">' + escHtml(data.error) + '</p>');
                return;
            }

            let html = '<h4 style="color:#1a1a5e;margin-bottom:12px">' + escHtml(data.thread.subject) + '</h4>';
            html += '<span class="eproc-visibility-badge ' + escHtml(data.thread.visibility) + '">' +
                escHtml(data.thread.visibility).toUpperCase() + '</span>';

            data.messages.forEach(function (msg) {
                const cls = msg.is_staff ? 'staff' : 'bidder';
                html += '<div class="eproc-message-bubble ' + cls + '">' +
                    '<div class="eproc-message-who">' + escHtml(msg.sender_name) +
                    (msg.is_staff ? ' (Staff)' : '') +
                    ' • ' + escHtml(msg.created_at) + '</div>' +
                    escHtml(msg.message) + '</div>';

                if (msg.attachments && msg.attachments.length > 0) {
                    msg.attachments.forEach(function (att) {
                        html += '<div style="font-size:11px;margin-bottom:8px">' +
                            '<a href="' + escHtml(att.download_url) + '" class="eproc-btn blue" style="padding:3px 8px;font-size:10px">' +
                            '📎 ' + escHtml(att.file_name) + '</a></div>';
                    });
                }
            });

            // Reply form
            if (data.thread.status === 'open') {
                html += '<div style="margin-top:16px;padding-top:12px;border-top:1px solid #eee">' +
                    '<textarea class="eproc-form-textarea" id="eproc-reply-text" placeholder="Type your reply..."></textarea>' +
                    '<button class="eproc-btn blue" id="eproc-dashboard-reply" data-thread-id="' + threadId +
                    '" style="margin-top:8px">Send Reply</button></div>';
            }

            $detail.html(html);
        }).catch(function () {
            $detail.html('<p class="eproc-info-box red">Failed to load thread.</p>');
        });
    });

    // ──── Dashboard Reply ────
    $(document).on('click', '#eproc-dashboard-reply', function (e) {
        e.preventDefault();
        const threadId = $(this).data('thread-id');
        const message = $('#eproc-reply-text').val();

        if (!message.trim()) return;

        const $btn = $(this);
        btnLoading($btn, 'Sending...');

        apiRequest('reply', 'POST', {
            thread_id: threadId,
            message: message,
        }).then(function (resp) {
            if (resp.success) {
                showNotice('Reply sent!', 'green');
                // Re-load thread
                $('[data-thread-id="' + threadId + '"].eproc-view-thread').trigger('click');
            } else {
                showNotice(resp.error || 'Failed to send reply.', 'red');
            }
            btnReset($btn, 'Send Reply');
        }).catch(function () {
            showNotice('An error occurred.', 'red');
            btnReset($btn, 'Send Reply');
        });
    });

    // ──── HTML Escape Helper ────
    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ──── Init: set active tab from URL ────
    $(function () {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) {
            $('.eproc-tab[data-tab="' + tab + '"]').trigger('click');
        }
    });

})(jQuery);
