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
    // Password visibility toggle + strength meter
    // =========================================================================

    /**
     * Wire up any [data-toggle-password="inputId"] button to toggle the
     * visibility of the target password input. Swaps the eye/eye-off SVG.
     */
    window.eprocInitPasswordToggles = function () {
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                const inputId = btn.getAttribute('data-toggle-password');
                const input = document.getElementById(inputId);
                if (!input) return;
                const eye = btn.querySelector('.eproc-icon-eye');
                const eyeOff = btn.querySelector('.eproc-icon-eye-off');
                if (input.type === 'password') {
                    input.type = 'text';
                    if (eye) eye.style.display = 'none';
                    if (eyeOff) eyeOff.style.display = 'inline-block';
                    btn.setAttribute('aria-label', 'Hide password');
                } else {
                    input.type = 'password';
                    if (eye) eye.style.display = 'inline-block';
                    if (eyeOff) eyeOff.style.display = 'none';
                    btn.setAttribute('aria-label', 'Show password');
                }
            });
        });
    };

    /**
     * Wire up a password strength meter.
     * The input gets a data-strength-target attribute pointing to the
     * meter container (.eproc-password-strength).
     */
    window.eprocInitPasswordStrength = function () {
        document.querySelectorAll('[data-strength-target]').forEach(function (input) {
            if (input.dataset.bound === '1') return;
            input.dataset.bound = '1';
            const meterId = input.getAttribute('data-strength-target');
            const meter = document.getElementById(meterId);
            if (!meter) return;

            input.addEventListener('input', function () {
                const value = input.value;
                const score = window.eprocPasswordScore ? window.eprocPasswordScore(value) : eprocDefaultScore(value);
                let label = '', cls = '';
                if (!value) { label = ''; cls = ''; }
                else if (score < 2) { label = 'Weak';   cls = 'weak'; }
                else if (score < 3) { label = 'Fair';   cls = 'fair'; }
                else if (score < 4) { label = 'Good';   cls = 'good'; }
                else                { label = 'Strong'; cls = 'strong'; }
                meter.className = 'eproc-password-strength' + (cls ? ' eproc-password-strength--' + cls : '');
                const labelEl = meter.querySelector('.eproc-password-strength-label');
                if (labelEl) labelEl.textContent = label;
            });
        });
    };

    /**
     * Simple password strength scorer (0-5).
     * Returns a numeric score: length + variety bonuses.
     */
    function eprocDefaultScore(pw) {
        if (!pw) return 0;
        let score = 0;
        if (pw.length >= 8)  score++;
        if (pw.length >= 12) score++;
        if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
        if (/\d/.test(pw)) score++;
        if (/[^a-zA-Z0-9]/.test(pw)) score++;
        return score;
    }

    // Auto-init on DOMContentLoaded.
    document.addEventListener('DOMContentLoaded', function () {
        if (window.eprocInitPasswordToggles) window.eprocInitPasswordToggles();
        if (window.eprocInitPasswordStrength) window.eprocInitPasswordStrength();
    });

    // =========================================================================
    // Confirm delete helper
    // =========================================================================

    window.eprocConfirm = function (message) {
        return window.confirm(message || eprocManage.strings.confirm_delete);
    };

    // =========================================================================
    // Notice helper (XSS-safe — uses textContent for the message payload)
    // =========================================================================

    /**
     * Render a notice message inside #eproc-message-notices (or any element).
     * The message string is set via textContent so it cannot inject HTML.
     *
     * @param {string} type    'success' | 'error' | 'warning' | 'info'
     * @param {string} message Plain-text message to display.
     * @param {string} target  CSS selector for the container (default: '#eproc-message-notices').
     */
    window.eprocShowNotice = function (type, message, target = '#eproc-message-notices') {
        const container = document.querySelector(target);
        if (!container) {
            // Fall back to toast if no container exists.
            window.eprocToast(message || 'An error occurred.', type);
            return;
        }
        container.innerHTML = '';
        const notice = document.createElement('div');
        notice.className = `eproc-notice ${type}`;
        const p = document.createElement('p');
        // textContent never interprets HTML — this is the XSS-safe way to
        // display arbitrary error messages returned from the REST API.
        p.textContent = message || 'An error occurred.';
        notice.appendChild(p);
        container.appendChild(notice);
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
