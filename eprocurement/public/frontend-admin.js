/**
 * Frontend Admin JS — shared utilities for the /tenders/manage/ panel.
 *
 * Uses fetch() with X-WP-Nonce for REST API calls instead of jQuery AJAX.
 * The eprocManage object is localized from PHP with restUrl, nonce, ajaxUrl, etc.
 */
(function () {
    'use strict';

    if (typeof eprocManage === 'undefined') {
        return;
    }

    const REST  = eprocManage.restUrl;
    const NONCE = eprocManage.nonce;

    // =========================================================================
    // REST API helper
    // =========================================================================

    window.eprocAPI = {
        /**
         * Make a REST API request.
         *
         * @param {string}  endpoint  Relative to eprocurement/v1/ (e.g. 'admin/bids')
         * @param {object}  options   fetch options (method, body, etc.)
         * @returns {Promise<object>} Parsed JSON response.
         */
        async request(endpoint, options = {}) {
            const url = REST + endpoint;
            const headers = {
                'X-WP-Nonce': NONCE,
            };

            // Don't set Content-Type for FormData (browser handles boundary)
            if (options.body && !(options.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                if (typeof options.body === 'object') {
                    options.body = JSON.stringify(options.body);
                }
            }

            const response = await fetch(url, {
                method: options.method || 'GET',
                headers: { ...headers, ...(options.headers || {}) },
                body: options.body || undefined,
                credentials: 'same-origin',
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.data?.message || 'Request failed');
            }

            return data;
        },

        get(endpoint)           { return this.request(endpoint); },
        post(endpoint, body)    { return this.request(endpoint, { method: 'POST', body }); },
        patch(endpoint, body)   { return this.request(endpoint, { method: 'PATCH', body }); },
        del(endpoint)           { return this.request(endpoint, { method: 'DELETE' }); },

        /**
         * Upload a file via FormData.
         */
        upload(endpoint, formData) {
            return this.request(endpoint, { method: 'POST', body: formData });
        },
    };

    // =========================================================================
    // Toast notifications
    // =========================================================================

    window.eprocToast = function (message, type = 'success', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `eproc-toast eproc-toast--${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };

    // =========================================================================
    // AJAX helper (for existing AJAX handlers that haven't been migrated)
    // =========================================================================

    window.eprocAjax = function (action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', eprocManage.ajaxNonce);

        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }

        return fetch(eprocManage.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        }).then(r => r.json());
    };

    // =========================================================================
    // Confirm delete helper
    // =========================================================================

    window.eprocConfirm = function (message) {
        return window.confirm(message || eprocManage.strings.confirm_delete);
    };

    // =========================================================================
    // Loading state helper
    // =========================================================================

    window.eprocSetLoading = function (element, loading) {
        if (!element) return;
        if (loading) {
            element.dataset.originalText = element.textContent;
            element.textContent = eprocManage.strings.saving;
            element.disabled = true;
            element.style.opacity = '0.7';
        } else {
            element.textContent = element.dataset.originalText || element.textContent;
            element.disabled = false;
            element.style.opacity = '1';
        }
    };

    // =========================================================================
    // CSV Export helper (downloads from REST response data)
    // =========================================================================

    window.eprocExportCSV = function (data, filename) {
        if (!data || !data.length) return;

        const headers = Object.keys(data[0]);
        const csvRows = [
            headers.join(','),
            ...data.map(row =>
                headers.map(h => {
                    const val = (row[h] ?? '').toString().replace(/"/g, '""');
                    return `"${val}"`;
                }).join(',')
            ),
        ];

        const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = filename || 'export.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    };

})();
