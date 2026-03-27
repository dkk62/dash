// Work Progress System - Client JS

document.addEventListener('DOMContentLoaded', function () {

    // ---- Delete Confirmation Modal ----
    var _deleteForm = null;
    var confirmModal = document.getElementById('confirmDeleteModal');
    if (confirmModal) {
        var bsConfirmModal = new bootstrap.Modal(confirmModal);
        var confirmBtn = document.getElementById('confirmDeleteBtn');

        document.querySelectorAll('form.confirm-delete').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                _deleteForm = form;
                var title   = form.getAttribute('data-confirm-title') || 'Confirm Deletion';
                var message = form.getAttribute('data-confirm-message') || 'Are you sure you want to delete this item?';
                var warning = form.getAttribute('data-confirm-warning') || 'This action cannot be undone.';

                document.getElementById('confirmDeleteLabel').innerHTML =
                    '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + title;
                document.getElementById('confirmDeleteMessage').innerHTML = message;
                document.getElementById('confirmDeleteWarning').textContent = warning;
                bsConfirmModal.show();
            });
        });

        confirmBtn.addEventListener('click', function () {
            if (_deleteForm) {
                bsConfirmModal.hide();
                // Submit natively, bypassing the confirm handler
                _deleteForm.classList.remove('confirm-delete');
                _deleteForm.submit();
            }
        });

        confirmModal.addEventListener('hidden.bs.modal', function () {
            _deleteForm = null;
        });
    }

    function getProgressParts(form) {
        return {
            container: form.querySelector('.upload-progress'),
            bar: form.querySelector('.upload-progress-bar'),
            percent: form.querySelector('.upload-progress-percent'),
            track: form.querySelector('.upload-progress-track')
        };
    }

    function showProgress(form, fileCount) {
        form.classList.add('is-uploading');

        var button = form.querySelector('.upload-btn');
        if (button) {
            button.disabled = true;
            button.title = fileCount > 1 ? ('Uploading ' + fileCount + ' files...') : 'Uploading 1 file...';
        }

        var parts = getProgressParts(form);
        if (parts.container) {
            parts.container.hidden = false;
            parts.container.style.display = 'block';
        }
        updateProgress(form, 0);
    }

    function updateProgress(form, percentValue) {
        var safePercent = Math.max(0, Math.min(100, Math.round(percentValue)));
        var parts = getProgressParts(form);

        if (parts.bar) {
            parts.bar.style.width = safePercent + '%';
            parts.bar.textContent = safePercent + '%';
        }
        if (parts.percent) {
            parts.percent.textContent = safePercent + '%';
        }
        if (parts.track) {
            parts.track.setAttribute('aria-valuenow', String(safePercent));
        }
    }

    function resetUploadingState(form) {
        form.classList.remove('is-uploading');
        var button = form.querySelector('.upload-btn');
        if (button) {
            button.disabled = false;
            button.title = 'Upload';
        }
    }

    function hideProgress(form) {
        var parts = getProgressParts(form);
        if (parts.container) {
            parts.container.hidden = true;
            parts.container.style.display = 'none';
        }
        updateProgress(form, 0);
    }

    function uploadViaAjax(form, fileInput) {
        var xhr = new XMLHttpRequest();
        var data = new FormData(form);

        xhr.open('POST', form.getAttribute('action'), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.responseType = 'json';

        xhr.upload.addEventListener('progress', function (evt) {
            if (!evt.lengthComputable) {
                return;
            }
            var percent = (evt.loaded / evt.total) * 100;
            updateProgress(form, percent);
        });

        xhr.upload.addEventListener('loadstart', function () {
            updateProgress(form, 1);
        });

        xhr.addEventListener('load', function () {
            var response = xhr.response;
            var isJsonSuccess = response && typeof response === 'object' && response.success === true;

            if (xhr.status >= 200 && xhr.status < 300 && isJsonSuccess) {
                updateProgress(form, 100);
                var parts = getProgressParts(form);
                if (parts.percent && response.message) {
                    parts.percent.textContent = response.message;
                }
                setTimeout(function () {
                    window.location.reload();
                }, 1500);
                return;
            }

            var responseMessage = 'Upload failed. Please try again.';
            if (response && typeof response === 'object' && response.message) {
                responseMessage = response.message;
            } else if (xhr.status >= 200 && xhr.status < 300 && !isJsonSuccess) {
                responseMessage = 'Upload failed: the server returned an unexpected response (status ' + xhr.status + '). Please contact the administrator.';
            }
            alert(responseMessage);
            resetUploadingState(form);
            hideProgress(form);
            fileInput.value = '';
        });

        xhr.addEventListener('error', function () {
            alert('Network error during upload. Please try again.');
            resetUploadingState(form);
            hideProgress(form);
            fileInput.value = '';
        });

        xhr.send(data);
    }

    // Upload buttons: click triggers hidden multi-file input, then auto-submit
    document.querySelectorAll('.upload-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = this.closest('.upload-form');
            if (form.classList.contains('is-uploading')) {
                return;
            }
            var clientName = form.getAttribute('data-client-name');
            if (clientName && !confirm('Upload files to "' + clientName + '"?')) {
                return;
            }
            var fileInput = form.querySelector('.file-input');
            fileInput.click();
        });
    });

    function checkExistingFiles(form, callback) {
        var periodId  = form.querySelector('input[name="period_id"]').value;
        var stage     = form.querySelector('input[name="stage"]').value;
        var accountInput = form.querySelector('input[name="account_id"]');
        var accountId = accountInput ? accountInput.value : '';

        var url = form.getAttribute('action').split('?')[0]
            + '?action=check_existing_files&period_id=' + encodeURIComponent(periodId)
            + '&stage=' + encodeURIComponent(stage);
        if (accountId) {
            url += '&account_id=' + encodeURIComponent(accountId);
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.responseType = 'json';
        xhr.addEventListener('load', function () {
            var files = (xhr.response && xhr.response.files) ? xhr.response.files : [];
            callback(files);
        });
        xhr.addEventListener('error', function () {
            callback([]);
        });
        xhr.send();
    }

    document.querySelectorAll('.file-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (this.files.length === 0) {
                return;
            }
            var fileInput = this;
            var form = this.closest('.upload-form');
            if (form.classList.contains('is-uploading')) {
                return;
            }

            checkExistingFiles(form, function (existingFiles) {
                if (existingFiles.length > 0) {
                    var clientName = form.getAttribute('data-client-name') || 'Unknown Client';
                    var list = existingFiles.map(function (name, i) {
                        return (i + 1) + '. ' + name;
                    }).join('\n');
                    var msg = 'Client: ' + clientName + '\n\n'
                        + 'The following existing file(s) will be replaced:\n\n'
                        + list + '\n\nProceed with upload?';
                    if (!confirm(msg)) {
                        fileInput.value = '';
                        return;
                    }
                }
                showProgress(form, fileInput.files.length);
                uploadViaAjax(form, fileInput);
            });
        });
    });

    // ---- Download LED update via AJAX ----
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    function findLed(periodId, stage, accountId) {
        var selector = '[data-led][data-period-id="' + periodId + '"][data-stage="' + stage + '"]';
        if (accountId) {
            selector += '[data-account-id="' + accountId + '"]';
        }
        return document.querySelector(selector);
    }

    function applyLedStatus(led, status) {
        led.className = led.className.replace(/\bled-\w+/g, '').trim();
        led.classList.add('led-' + status);
        led.title = status.charAt(0).toUpperCase() + status.slice(1);
    }

    document.querySelectorAll('[data-download-trigger]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            var href      = this.getAttribute('href');
            var periodId  = this.getAttribute('data-period-id');
            var stage     = this.getAttribute('data-stage');
            var accountId = this.getAttribute('data-account-id') || '';

            // Trigger the actual file download
            window.location.href = href;

            // AJAX call to update LED status in DB and DOM
            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('period_id', periodId);
            formData.append('stage', stage);
            if (accountId) {
                formData.append('account_id', accountId);
            }

            var xhr = new XMLHttpRequest();
            var markUrl = href.split('?')[0] + '?action=mark_downloaded';
            xhr.open('POST', markUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.responseType = 'json';
            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 300 && xhr.response && xhr.response.success) {
                    var led = findLed(periodId, stage, accountId || null);
                    if (led) {
                        applyLedStatus(led, xhr.response.status);
                    }
                }
            });
            xhr.send(formData);
        });
    });

    // ---- Stage Files Modal (LED click) ----
    var stageFilesModal = document.getElementById('stageFilesModal');
    if (stageFilesModal) {
        var bsStageFilesModal = new bootstrap.Modal(stageFilesModal);
        var stageLabelsFiles = { stage1: 'Stage 1', stage2: 'Stage 2', stage3: 'Stage 3', stage4: 'Stage 4' };

        function formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            var units = ['B', 'KB', 'MB', 'GB'];
            var i = 0;
            var size = bytes;
            while (size >= 1024 && i < units.length - 1) {
                size /= 1024;
                i++;
            }
            return (i === 0 ? size : size.toFixed(1)) + ' ' + units[i];
        }

        document.addEventListener('click', function (e) {
            var led = e.target.closest('[data-led-clickable]');
            if (!led) return;

            var periodId    = led.getAttribute('data-period-id');
            var stage       = led.getAttribute('data-stage');
            var accountId   = led.getAttribute('data-account-id') || '';
            var clientName  = led.getAttribute('data-client-name') || '';
            var periodLabel = led.getAttribute('data-period-label') || '';
            var accountName = led.getAttribute('data-account-name') || '';

            var metaEl = document.getElementById('stageFilesMeta');
            var line1 = clientName;
            var line2 = (stageLabelsFiles[stage] || stage) + '  \u00b7  ' + periodLabel;
            if (stage === 'stage1' && accountName) {
                line2 += '  \u00b7  ' + accountName;
            }
            metaEl.innerHTML = '<strong>' + escapeHtml(line1) + '</strong><br>' + escapeHtml(line2);
            document.getElementById('stageFilesLoading').style.display = '';
            document.getElementById('stageFilesEmpty').style.display = 'none';
            document.getElementById('stageFilesTableWrap').style.display = 'none';
            document.getElementById('stageFilesBody').innerHTML = '';
            bsStageFilesModal.show();

            var url = window.location.href.split('?')[0]
                + '?action=stage_files&period_id=' + encodeURIComponent(periodId)
                + '&stage=' + encodeURIComponent(stage);
            if (accountId) {
                url += '&account_id=' + encodeURIComponent(accountId);
            }

            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.responseType = 'json';
            xhr.addEventListener('load', function () {
                document.getElementById('stageFilesLoading').style.display = 'none';
                var files = (xhr.response && xhr.response.files) ? xhr.response.files : [];
                if (files.length === 0) {
                    document.getElementById('stageFilesEmpty').style.display = '';
                    return;
                }
                var tbody = document.getElementById('stageFilesBody');
                var html = '';
                files.forEach(function (f, i) {
                    var uploadDate = f.uploaded_at ? new Date(f.uploaded_at.replace(/-/g, '/')).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' }) : '-';
                    html += '<tr>'
                        + '<td>' + (i + 1) + '</td>'
                        + '<td class="text-break">' + escapeHtml(f.original_filename) + '</td>'
                        + '<td class="text-nowrap">' + uploadDate + '</td>'
                        + '<td>' + escapeHtml(f.uploaded_by_name) + '</td>'
                        + '</tr>';
                });
                tbody.innerHTML = html;
                document.getElementById('stageFilesTableWrap').style.display = '';
            });
            xhr.addEventListener('error', function () {
                document.getElementById('stageFilesLoading').style.display = 'none';
                document.getElementById('stageFilesEmpty').textContent = 'Failed to load files.';
                document.getElementById('stageFilesEmpty').style.display = '';
            });
            xhr.send();
        });

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    }

    // ---- Stage Note Modal (Chat) ----
    var noteModal = document.getElementById('noteModal');
    if (noteModal) {
        var noteChatArea    = document.getElementById('noteChatArea');
        var noteChatEmpty   = document.getElementById('noteChatEmpty');
        var noteModalMeta   = document.getElementById('noteModalMeta');
        var noteMessageInput = document.getElementById('noteMessageInput');
        var noteSendBtn     = document.getElementById('noteSendBtn');
        var _notePeriodId   = '';
        var _noteStage      = '';
        var _noteAccountId  = '0';
        var _noteTriggerBtn = null;
        var _noteEntries    = [];

        var stageLabels = { stage1: 'Stage 1', stage2: 'Stage 2', stage3: 'Stage 3', stage4: 'Stage 4' };

        function noteEscapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }

        function renderChatEntries(entries) {
            if (!entries || entries.length === 0) {
                noteChatArea.innerHTML = '';
                noteChatArea.style.display = 'none';
                noteChatEmpty.style.display = '';
                return;
            }
            noteChatEmpty.style.display = 'none';
            noteChatArea.style.display = '';
            var html = '';
            entries.forEach(function (e) {
                var timeStr = e.at || '';
                if (timeStr) {
                    var d = new Date(timeStr.replace(/-/g, '/'));
                    if (!isNaN(d.getTime())) {
                        timeStr = d.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' })
                                + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    }
                }
                html += '<div class="note-chat-entry">'
                    + '<div class="note-chat-header">'
                    + '<span class="note-chat-author">' + noteEscapeHtml(e.by || 'Unknown') + '</span>'
                    + (timeStr ? '<span class="note-chat-time">' + noteEscapeHtml(timeStr) + '</span>' : '')
                    + '</div>'
                    + '<div class="note-chat-msg">' + noteEscapeHtml(e.msg || '') + '</div>'
                    + '</div>';
            });
            noteChatArea.innerHTML = html;
            noteChatArea.scrollTop = noteChatArea.scrollHeight;
        }

        function parseNoteEntries(raw) {
            if (!raw || raw.trim() === '') return [];
            try {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) return parsed;
            } catch (ex) {}
            // Legacy plain text
            return [{ by: 'System', at: '', msg: raw }];
        }

        noteModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            _noteTriggerBtn  = btn;
            _notePeriodId    = btn.getAttribute('data-period-id');
            _noteStage       = btn.getAttribute('data-stage');
            _noteAccountId   = btn.getAttribute('data-account-id') || '0';
            var rawNote      = btn.getAttribute('data-note') || '';
            var periodLabel  = btn.getAttribute('data-period-label') || ('Period #' + _notePeriodId);

            noteModalMeta.textContent = (stageLabels[_noteStage] || _noteStage) + '  \u00b7  ' + periodLabel;

            _noteEntries = parseNoteEntries(rawNote);
            renderChatEntries(_noteEntries);

            noteMessageInput.value = '';
            noteMessageInput.style.height = 'auto';
            setTimeout(function () { noteMessageInput.focus(); }, 300);
        });

        function sendNote() {
            var msg = noteMessageInput.value.trim();
            if (msg === '') return;

            noteSendBtn.disabled = true;
            noteMessageInput.disabled = true;

            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('period_id', _notePeriodId);
            formData.append('stage', _noteStage);
            formData.append('account_id', _noteAccountId);
            formData.append('message', msg);

            var xhr = new XMLHttpRequest();
            var saveUrl = window.location.href.split('?')[0] + '?action=save_note';
            xhr.open('POST', saveUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.responseType = 'json';

            xhr.addEventListener('load', function () {
                noteSendBtn.disabled = false;
                noteMessageInput.disabled = false;
                if (xhr.status >= 200 && xhr.status < 300 && xhr.response && xhr.response.success) {
                    var entry = xhr.response.entry;
                    _noteEntries.push(entry);
                    renderChatEntries(_noteEntries);
                    noteMessageInput.value = '';
                    noteMessageInput.style.height = 'auto';
                    noteMessageInput.focus();

                    // Update the trigger button's data-note with new JSON
                    if (_noteTriggerBtn) {
                        _noteTriggerBtn.setAttribute('data-note', JSON.stringify(_noteEntries));
                        _noteTriggerBtn.classList.add('note-has-content');
                        _noteTriggerBtn.title = 'View Notes';
                    }
                } else {
                    var errMsg = (xhr.response && xhr.response.message) ? xhr.response.message : 'Failed to send note.';
                    alert(errMsg);
                }
            });

            xhr.addEventListener('error', function () {
                noteSendBtn.disabled = false;
                noteMessageInput.disabled = false;
                alert('Network error. Please try again.');
            });

            xhr.send(formData);
        }

        noteSendBtn.addEventListener('click', sendNote);

        noteMessageInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    }
});
