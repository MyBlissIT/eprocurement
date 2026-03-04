/**
 * eProcurement Admin JavaScript
 *
 * Handles admin-side interactions: AJAX form submissions,
 * file uploads, modals, and dynamic UI.
 *
 * @package Eprocurement
 */

(function ($) {
    'use strict';

    const admin = eprocAdmin;

    // ──── AJAX Helper ────
    function ajaxPost(action, data, successCallback, errorCallback) {
        data.action = action;
        data.nonce = admin.nonce;

        $.post(admin.ajaxUrl, data, function (response) {
            if (response && response.success) {
                if (successCallback) successCallback(response.data || {});
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : admin.strings.error;
                if (errorCallback) {
                    errorCallback(msg);
                } else {
                    showNotice(msg, 'error');
                }
            }
        }).fail(function () {
            showNotice(admin.strings.error, 'error');
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

    // ──── Toast Notification Display ────
    function showNotice(message, type) {
        type = type || 'success';
        // Remove existing toasts
        $('.eproc-toast-notification').remove();

        const iconMap = {
            success: '&#10003;',
            error: '&#10007;',
            info: '&#9432;'
        };
        const icon = iconMap[type] || iconMap.info;

        const $toast = $(
            '<div class="eproc-toast-notification ' + type + '">' +
            '<span class="eproc-toast-icon">' + icon + '</span>' +
            '<span class="eproc-toast-message">' + message + '</span>' +
            '<button type="button" class="eproc-toast-close">&times;</button>' +
            '</div>'
        );

        $('body').append($toast);

        // Animate in
        setTimeout(function () { $toast.addClass('show'); }, 10);

        // Close on click
        $toast.find('.eproc-toast-close').on('click', function () {
            $toast.removeClass('show');
            setTimeout(function () { $toast.remove(); }, 300);
        });

        // Auto dismiss
        setTimeout(function () {
            $toast.removeClass('show');
            setTimeout(function () { $toast.remove(); }, 300);
        }, 5000);
    }

    // Expose globally so inline scripts (bid-edit.php, etc.) can reuse it
    window.eprocShowNotice = showNotice;

    // ──── Bid Save ────
    // NOTE: Bid save is handled by inline JS in bid-edit.php (handles categories, pending docs, etc.)
    // Do NOT add a global #eproc-save-bid handler here to avoid duplicate AJAX calls.

    // ──── Bid Delete ────
    $(document).on('click', '.eproc-delete-bid', function (e) {
        e.preventDefault();
        if (!confirm(admin.strings.confirm_delete)) return;

        const id = $(this).data('id');
        ajaxPost('eproc_delete_bid', { id: id }, function () {
            showNotice('Bid deleted.', 'success');
            $('tr[data-id="' + id + '"]').fadeOut(300, function () { $(this).remove(); });
        });
    });

    // ──── Status Change ────
    $(document).on('click', '#eproc-change-status', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        const status = $('[name="new_status"]').val();

        ajaxPost('eproc_change_status', { id: id, status: status }, function (resp) {
            showNotice(resp.message, 'success');
            setTimeout(function () { location.reload(); }, 1000);
        });
    });

    // ──── Contact Person Save (Modal) ────
    $(document).on('click', '#eproc-save-contact', function (e) {
        e.preventDefault();
        const $form = $(this).closest('.eproc-modal');
        const data = {
            id: $form.find('[name="contact_id"]').val(),
            user_id: $form.find('[name="user_id"]').val(),
            type: $form.find('[name="type"]').val(),
            name: $form.find('[name="name"]').val(),
            phone: $form.find('[name="phone"]').val(),
            email: $form.find('[name="email"]').val(),
            department: $form.find('[name="department"]').val(),
        };

        ajaxPost('eproc_save_contact', data, function () {
            showNotice(admin.strings.saved, 'success');
            setTimeout(function () { location.reload(); }, 800);
        });
    });

    // ──── Contact Person Delete ────
    $(document).on('click', '.eproc-delete-contact', function (e) {
        e.preventDefault();
        if (!confirm(admin.strings.confirm_delete)) return;

        const id = $(this).data('id');
        ajaxPost('eproc_delete_contact', { id: id }, function () {
            showNotice('Contact deleted.', 'success');
            $('tr[data-id="' + id + '"]').fadeOut(300, function () { $(this).remove(); });
        });
    });

    // ──── Message Reply ────
    $(document).on('click', '#eproc-send-reply', function (e) {
        e.preventDefault();
        const threadId = $(this).data('thread-id');
        const $textarea = $(this).siblings('textarea, .eproc-reply-textarea');
        const message = $textarea.val();

        if (!message.trim()) return;

        const $btn = $(this);
        btnLoading($btn, 'Sending...');

        ajaxPost('eproc_reply_message', { thread_id: threadId, message: message }, function () {
            showNotice('Reply sent.', 'success');
            $textarea.val('');
            btnReset($btn);
            setTimeout(function () { location.reload(); }, 800);
        }, function (msg) {
            showNotice(msg, 'error');
            btnReset($btn);
        });
    });

    // ──── File Upload (Bid Documents) ────
    $(document).on('change', '.eproc-file-input', function () {
        const file = this.files[0];
        if (!file) return;

        const $area = $(this).closest('.eproc-upload-area');
        const documentId = $area.data('document-id');
        const label = $area.siblings('[name="file_label"]').val() || '';
        const $progress = $area.find('.eproc-upload-progress');
        const $bar = $progress.find('.eproc-upload-progress-bar');

        $progress.show();
        $bar.css('width', '10%');

        const formData = new FormData();
        formData.append('action', 'eproc_upload_supporting_doc');
        formData.append('nonce', admin.nonce);
        formData.append('file', file);
        formData.append('document_id', documentId);
        formData.append('label', label);

        $.ajax({
            url: admin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        const pct = (e.loaded / e.total) * 100;
                        $bar.css('width', pct + '%');
                    }
                });
                return xhr;
            },
            success: function (response) {
                if (response.success) {
                    showNotice(admin.strings.upload_success, 'success');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    showNotice(response.data?.message || admin.strings.error, 'error');
                }
                $progress.hide();
                $bar.css('width', '0');
            },
            error: function () {
                showNotice(admin.strings.error, 'error');
                $progress.hide();
                $bar.css('width', '0');
            }
        });
    });

    // ──── Remove Bid Doc ────
    $(document).on('click', '.eproc-remove-file', function (e) {
        e.preventDefault();
        if (!confirm(admin.strings.confirm_delete)) return;

        const id = $(this).data('id');
        ajaxPost('eproc_remove_supporting_doc', { id: id }, function () {
            showNotice('File removed.', 'success');
            $('[data-file-id="' + id + '"]').fadeOut(300, function () { $(this).remove(); });
        });
    });

    // ──── SCM Document Upload ────
    $(document).on('submit', '#eproc-compliance-upload-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        const formData = new FormData(this);
        formData.append('action', 'eproc_upload_compliance_doc');
        formData.append('nonce', admin.nonce);

        const $btn = $form.find('button[type="submit"]');
        btnLoading($btn, admin.strings.uploading);

        $.ajax({
            url: admin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    showNotice(admin.strings.upload_success, 'success');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    showNotice(response.data?.message || admin.strings.error, 'error');
                }
                btnReset($btn, 'Upload');
            },
            error: function () {
                showNotice(admin.strings.error, 'error');
                btnReset($btn, 'Upload');
            }
        });
    });

    // ──── Delete SCM Document ────
    $(document).on('click', '.eproc-delete-compliance', function (e) {
        e.preventDefault();
        if (!confirm(admin.strings.confirm_delete)) return;

        const id = $(this).data('id');
        ajaxPost('eproc_delete_compliance_doc', { id: id }, function () {
            showNotice('Document deleted.', 'success');
            $('tr[data-id="' + id + '"]').fadeOut(300, function () { $(this).remove(); });
        });
    });

    // Settings save is handled by the inline script in settings.php
    // (which includes all fields: categories, bid_heading, cloud credentials, etc.)

    // ──── Test Storage Connection ────
    $(document).on('click', '#eproc-test-storage', function (e) {
        e.preventDefault();
        const $btn = $(this);
        btnLoading($btn, admin.strings.testing);

        ajaxPost('eproc_test_storage', {}, function (resp) {
            showNotice(admin.strings.connected, 'success');
            btnReset($btn, 'Test Connection');
        }, function () {
            showNotice(admin.strings.connection_fail, 'error');
            btnReset($btn, 'Test Connection');
        });
    });

    // ──── Provider Section Toggle ────
    $(document).on('change', '[name="cloud_provider"]', function () {
        const provider = $(this).val();
        $('.eproc-provider-section').removeClass('active');
        if (provider) {
            $('#eproc-provider-' + provider).addClass('active');
        }
    });

    // ──── Modal Open/Close ────
    $(document).on('click', '.eproc-open-modal', function (e) {
        e.preventDefault();
        const target = $(this).data('modal');
        $('#' + target).addClass('active');
    });

    $(document).on('click', '.eproc-close-modal', function (e) {
        e.preventDefault();
        $(this).closest('.eproc-modal-overlay').removeClass('active');
    });

    $(document).on('click', '.eproc-modal-overlay', function (e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    // ──── Drag & Drop Upload ────
    $(document).on('dragover', '.eproc-upload-area', function (e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $(document).on('dragleave', '.eproc-upload-area', function () {
        $(this).removeClass('dragover');
    });

    $(document).on('drop', '.eproc-upload-area', function (e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length) {
            const $input = $(this).find('.eproc-file-input');
            $input[0].files = files;
            $input.trigger('change');
        }
    });

    // ──── Thread Click (Messages Page) ────
    $(document).on('click', '.eproc-thread-item', function () {
        const threadId = $(this).data('thread-id');
        if (threadId) {
            window.location.href = admin.ajaxUrl.replace('admin-ajax.php', '') +
                'admin.php?page=eprocurement-messages&thread_id=' + threadId;
        }
    });

    // ──── Edit Contact (populate modal) ────
    $(document).on('click', '.eproc-edit-contact', function (e) {
        e.preventDefault();
        const $row = $(this).closest('tr');
        const modal = '#eproc-contact-modal';

        $(modal).find('[name="contact_id"]').val($row.data('id'));
        $(modal).find('[name="name"]').val($row.data('name'));
        $(modal).find('[name="type"]').val($row.data('type'));
        $(modal).find('[name="phone"]').val($row.data('phone'));
        $(modal).find('[name="email"]').val($row.data('email'));
        $(modal).find('[name="department"]').val($row.data('department'));
        $(modal).find('[name="user_id"]').val($row.data('user-id'));

        $(modal).closest('.eproc-modal-overlay').addClass('active');
    });

    // Init: trigger provider section display if one is already selected
    $(function () {
        $('[name="cloud_provider"]').trigger('change');
    });

})(jQuery);
