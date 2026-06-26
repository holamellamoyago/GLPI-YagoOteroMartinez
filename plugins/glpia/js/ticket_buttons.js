/**
 * glpIA — AI-powered text improvement for GLPI ticket forms.
 *
 * Injects an "glpIA mejorar" button under every textarea on ticket pages
 * (description, followups, tasks, solutions). Clicking the button opens
 * a Bootstrap 5 modal that calls DeepSeek to improve the text using
 * the full ticket conversation as context.
 *
 * Handles dynamically-loaded forms (timeline Ajax) via MutationObserver.
 *
 * @since 1.0.0
 */
(function () {
    'use strict';

    // ── Constants ─────────────────────────────────────────────
    var BUTTON_CLASS  = 'glpia-btn';
    var BUTTON_DONE   = 'data-glpia-done';
    var MODAL_ID      = 'glpia-modal';
    var ENDPOINT      = '/plugins/glpia/ajax/improve_text.php';

    // ── Detect ticket ID from URL or DOM ──────────────────────

    function getTicketId() {
        // From URL: /front/ticket.form.php?id=42
        var match = window.location.search.match(/[?&]id=(\d+)/);
        if (match) return parseInt(match[1], 10);

        // From hidden input
        var input = document.querySelector('input[name="id"], input[name="tickets_id"]');
        if (input) return parseInt(input.value, 10);

        return 0;
    }

    // ── Detect item type from a textarea's surrounding form ───

    function getItemType(textarea) {
        var form = textarea.closest('form');
        if (!form) return '';

        // Check form action URLs or hidden inputs
        var action = (form.getAttribute('action') || '').toLowerCase();

        if (action.indexOf('solution') > -1)          return 'ITILSolution';
        if (action.indexOf('task') > -1)              return 'TicketTask';
        if (action.indexOf('followup') > -1)          return 'ITILFollowup';

        // Fallback: look for specific inputs
        if (form.querySelector('[name="solution"]'))  return 'ITILSolution';
        if (form.querySelector('[name="task"]'))      return 'TicketTask';
        if (form.querySelector('[name="followup"]'))  return 'ITILFollowup';

        return 'Ticket';
    }

    // ── Should we add a button to this textarea? ──────────────

    function shouldAddButton(textarea) {
        // Already has our button
        if (textarea.hasAttribute(BUTTON_DONE)) return false;

        // Search/filter fields
        if (textarea.closest('.search-form, .filter-panel, [role="search"]')) return false;

        // For visible textareas (non-TinyMCE): check visibility
        if (textarea.style.display !== 'none') {
            if (!textarea.offsetParent) return false;
        }
        // For TinyMCE-hidden textareas: check if editor exists
        else {
            if (typeof tinymce === 'undefined' || !textarea.id) return false;
            var editor = tinymce.get(textarea.id);
            if (!editor) return false;
        }

        return true;
    }

    // ── Add button under a textarea or TinyMCE editor ─────────

    function addButton(textarea) {
        if (!shouldAddButton(textarea)) return;

        textarea.setAttribute(BUTTON_DONE, '1');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary ' + BUTTON_CLASS + ' mt-1';
        btn.innerHTML = '<i class="ti ti-robot"></i> glpIA mejorar';

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Get current text (handle TinyMCE)
            var currentText = getTextareaContent(textarea);
            var ticketId    = getTicketId();
            var itemtype    = getItemType(textarea);

            if (!ticketId) {
                alert('No se pudo detectar el ID del ticket.');
                return;
            }

            if (!currentText.trim()) {
                alert('Escribe algo en el campo antes de mejorar.');
                return;
            }

            showModal(textarea, currentText, ticketId, itemtype);
        });

        // Place button: after TinyMCE editor container, or after textarea
        if (typeof tinymce !== 'undefined' && textarea.id) {
            var editor = tinymce.get(textarea.id);
            if (editor) {
                var editorContainer = editor.getContainer();
                if (editorContainer) {
                    editorContainer.insertAdjacentElement('afterend', btn);
                    return;
                }
            }
        }
        textarea.insertAdjacentElement('afterend', btn);
    }

    // ── Get textarea content (handles TinyMCE) ────────────────

    function getTextareaContent(textarea) {
        // Check if TinyMCE is active on this textarea
        if (typeof tinymce !== 'undefined' && textarea.id) {
            var editor = tinymce.get(textarea.id);
            if (editor) {
                return editor.getContent({ format: 'text' });
            }
        }
        return textarea.value || '';
    }

    // ── Set textarea content (handles TinyMCE) ────────────────

    function setTextareaContent(textarea, newText) {
        if (typeof tinymce !== 'undefined' && textarea.id) {
            var editor = tinymce.get(textarea.id);
            if (editor) {
                editor.setContent(newText);
                return;
            }
        }
        textarea.value = newText;
        // Trigger input event so GLPI's form validation picks it up
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // ── Show the modal ────────────────────────────────────────

    function showModal(textarea, originalText, ticketId, itemtype) {
        // Remove any existing modal
        var oldModal = document.getElementById(MODAL_ID);
        if (oldModal) oldModal.remove();

        // Build modal HTML
        var modalHTML =
            '<div class="modal fade" id="' + MODAL_ID + '" tabindex="-1" aria-hidden="true">' +
            '  <div class="modal-dialog modal-lg">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header">' +
            '        <h5 class="modal-title">' +
            '          <i class="ti ti-robot me-2 glpia-modal-icon"></i>glpIA — Mejorar texto' +
            '        </h5>' +
            '        <button type="button" class="btn-close" data-bs-dismiss="modal"' +
            '                aria-label="Cerrar"></button>' +
            '      </div>' +
            '      <div class="modal-body">' +
            '        <div class="mb-3">' +
            '          <label class="form-label fw-bold">Texto original:</label>' +
            '          <div class="glpia-original">' + escapeHtml(originalText) + '</div>' +
            '        </div>' +
            '        <div id="glpia-action-area" class="text-center mb-3">' +
            '          <button type="button" id="glpia-improve-btn"' +
            '                  class="btn btn-primary">' +
            '            <i class="ti ti-sparkles me-1"></i> Mejorar con IA' +
            '          </button>' +
            '        </div>' +
            '        <div id="glpia-loading" class="glpia-spinner-wrap" style="display:none">' +
            '          <span class="glpia-spinner"></span>' +
            '          <span>Mejorando el texto con IA...</span>' +
            '        </div>' +
            '        <div id="glpia-result-area" style="display:none">' +
            '          <label class="form-label fw-bold">Resultado:</label>' +
            '          <div id="glpia-result-text" class="glpia-result"></div>' +
            '          <div id="glpia-token-info" class="text-muted small mt-1"></div>' +
            '        </div>' +
            '        <div id="glpia-error" class="alert alert-danger" style="display:none"></div>' +
            '      </div>' +
            '      <div class="modal-footer">' +
            '        <button type="button" id="glpia-use-btn"' +
            '                class="btn btn-success" style="display:none">' +
            '          <i class="ti ti-check me-1"></i> Usar este texto' +
            '        </button>' +
            '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' +
            '          Cerrar' +
            '        </button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        var modalEl  = document.getElementById(MODAL_ID);
        var bsModal  = new bootstrap.Modal(modalEl);

        // ── Event handlers ────────────────────────────────────

        document.getElementById('glpia-improve-btn').addEventListener('click', function () {
            callDeepSeek(textarea, originalText, ticketId, itemtype);
        });

        document.getElementById('glpia-use-btn').addEventListener('click', function () {
            var resultText = document.getElementById('glpia-result-text').textContent;
            setTextareaContent(textarea, resultText);
            bsModal.hide();
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            modalEl.remove();
        });

        bsModal.show();
    }

    // ── Call DeepSeek API via our PHP endpoint ────────────────

    function callDeepSeek(textarea, originalText, ticketId, itemtype) {
        var improveBtn = document.getElementById('glpia-improve-btn');
        var loadingDiv = document.getElementById('glpia-loading');
        var resultArea = document.getElementById('glpia-result-area');
        var errorDiv    = document.getElementById('glpia-error');
        var useBtn      = document.getElementById('glpia-use-btn');
        var tokenInfo   = document.getElementById('glpia-token-info');

        // UI: show loading, hide previous results
        improveBtn.style.display = 'none';
        loadingDiv.style.display = 'flex';
        resultArea.style.display = 'none';
        errorDiv.style.display   = 'none';
        useBtn.style.display     = 'none';

        // Build form data
        var formData = new URLSearchParams();
        formData.append('ticket_id', ticketId);
        formData.append('text', originalText);
        formData.append('itemtype', itemtype);

        // Include CSRF token from page meta
        var csrfMeta = document.querySelector('meta[property=\"glpi:csrf_token\"]');
        if (csrfMeta) {
            formData.append('_glpi_csrf_token', csrfMeta.getAttribute('content'));
        }

        fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ' ' + response.statusText);
            }
            return response.json();
        })
        .then(function (data) {
            loadingDiv.style.display = 'none';

            if (data.success) {
                document.getElementById('glpia-result-text').textContent = data.improved_text;
                resultArea.style.display = 'block';
                useBtn.style.display = 'inline-block';

                if (data.token_usage) {
                    tokenInfo.textContent = 'Tokens: ' +
                        data.token_usage.total_tokens +
                        ' (prompt: ' + data.token_usage.prompt_tokens +
                        ', completion: ' + data.token_usage.completion_tokens + ')';
                }
            } else {
                errorDiv.textContent = 'Error: ' + (data.error || 'Respuesta inesperada');
                errorDiv.style.display = 'block';
                improveBtn.style.display = 'inline-block';
            }
        })
        .catch(function (err) {
            loadingDiv.style.display = 'none';
            errorDiv.textContent = 'Error de conexión: ' + err.message;
            errorDiv.style.display = 'block';
            improveBtn.style.display = 'inline-block';
        });
    }

    // ── Scan page for textareas AND TinyMCE editors ───────────

    function scanTextareas(root) {
        root = root || document;

        // 1. Scan visible textareas (non-TinyMCE)
        var textareas = root.querySelectorAll('textarea');
        for (var i = 0; i < textareas.length; i++) {
            addButton(textareas[i]);
        }

        // 2. Scan TinyMCE editor instances (the main description, etc.)
        if (typeof tinymce !== 'undefined' && tinymce.get) {
            // Get all editor instances by scanning for tox-tinymce containers
            var containers = root.querySelectorAll('.tox-tinymce');
            for (var j = 0; j < containers.length; j++) {
                addButtonForEditorContainer(containers[j]);
            }
        }
    }

    /**
     * Add button below a TinyMCE editor container (tox-tinymce).
     * Finds the associated hidden textarea to use as reference for get/set content.
     */
    function addButtonForEditorContainer(container) {
        // Already done?
        if (container.hasAttribute(BUTTON_DONE)) return;
        container.setAttribute(BUTTON_DONE, '1');

        // Find the associated textarea (usually a sibling before the editor)
        var textarea = container.parentElement.querySelector('textarea');
        if (!textarea) {
            // Try finding it elsewhere
            textarea = document.getElementById(container.id ? container.id.replace('_ifr', '') : '');
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary ' + BUTTON_CLASS + ' mt-1';
        btn.innerHTML = '<i class="ti ti-robot"></i> glpIA mejorar';

        var refTextarea = textarea; // capture for closure

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var currentText = getTextareaContent(refTextarea);
            var ticketId    = getTicketId();
            var itemtype    = getItemType(refTextarea || container);

            if (!ticketId) {
                alert('No se pudo detectar el ID del ticket.');
                return;
            }

            if (!currentText.trim()) {
                alert('Escribe algo en el campo antes de mejorar.');
                return;
            }

            showModal(refTextarea || container, currentText, ticketId, itemtype);
        });

        container.insertAdjacentElement('afterend', btn);
    }

    // ── Initial scan, retrying for TinyMCE ──────────────────

    function initScan() {
        scanTextareas();

        // TinyMCE may load after our script — retry until editors appear
        var retries = 0;
        var maxRetries = 20;
        var interval = setInterval(function () {
            retries++;
            var containers = document.querySelectorAll('.tox-tinymce');
            if (containers.length > 0 || retries >= maxRetries) {
                clearInterval(interval);
                scanTextareas();
            }
        }, 300);
    }

    // ── Escape HTML for safe rendering ────────────────────────

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ── Initialization ────────────────────────────────────────

    // Scan on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initScan(); });
    } else {
        initScan();
    }

    // Re-scan for dynamically loaded timeline forms
    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var addedNodes = mutations[i].addedNodes;
                for (var j = 0; j < addedNodes.length; j++) {
                    var node = addedNodes[j];
                    if (node.nodeType === 1) {
                        // Check if the added node itself is a textarea
                        if (node.tagName === 'TEXTAREA') {
                            addButton(node);
                        }
                        // Check if the added node itself is a TinyMCE container
                        if (node.classList && node.classList.contains('tox-tinymce')) {
                            addButtonForEditorContainer(node);
                        }
                        // Check for textareas and TinyMCE inside the added node
                        if (node.querySelectorAll) {
                            scanTextareas(node);
                        }
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

})();
