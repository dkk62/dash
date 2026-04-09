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

    // Extensions that can be previewed in the browser
    var previewableExts = ['pdf','jpg','jpeg','png','gif','webp','svg','bmp','txt','csv','log','xml','json','htm','html','xlsx','xls','docx','doc','rtf'];

    // Extensions that can only be downloaded (no in-browser preview)
    var downloadOnlyExts = ['doc','rtf'];

    function isDownloadOnly(filename) {
        var ext = (filename || '').split('.').pop().toLowerCase();
        return downloadOnlyExts.indexOf(ext) !== -1;
    }

    function isPreviewable(filename) {
        var ext = (filename || '').split('.').pop().toLowerCase();
        return previewableExts.indexOf(ext) !== -1;
    }

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
                    var viewBtn = '';
                    if (isPreviewable(f.original_filename) && f.file_id) {
                        var previewUrl = window.location.href.split('?')[0] + '?action=preview_file&file_id=' + encodeURIComponent(f.file_id);
                        if (isDownloadOnly(f.original_filename)) {
                            viewBtn = '<a href="' + escapeHtml(previewUrl) + '" download class="btn btn-outline-secondary btn-sm py-0 px-1" style="font-size:0.7rem;" title="Download"><i class="bi bi-download"></i></a>';
                        } else {
                            viewBtn = '<button type="button" class="btn btn-outline-primary btn-sm py-0 px-1" style="font-size:0.7rem;" data-preview-url="' + escapeHtml(previewUrl) + '" data-preview-name="' + escapeHtml(f.original_filename) + '"><i class="bi bi-eye"></i></button>';
                        }
                    }
                    html += '<tr>'
                        + '<td>' + (i + 1) + '</td>'
                        + '<td class="text-break">' + escapeHtml(f.original_filename) + '</td>'
                        + '<td class="text-nowrap">' + uploadDate + '</td>'
                        + '<td>' + escapeHtml(f.uploaded_by_name) + '</td>'
                        + '<td class="text-center">' + viewBtn + '</td>'
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

    // ---- File Preview Modal ----
    var previewModal = document.getElementById('filePreviewModal');
    if (previewModal) {
            var bsPreviewModal = new bootstrap.Modal(previewModal);
            var previewTitle = document.getElementById('filePreviewTitle');
            var previewBody = document.getElementById('filePreviewBody');
            var previewLoading = document.getElementById('filePreviewLoading');

            // Click handler for view buttons inside stage files table
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-preview-url]');
                if (!btn) return;
                e.preventDefault();

                var url = btn.getAttribute('data-preview-url');
                var name = btn.getAttribute('data-preview-name');
                var ext = (name || '').split('.').pop().toLowerCase();

                previewTitle.textContent = name;
                previewBody.innerHTML = '';
                previewLoading.style.display = '';

                bsPreviewModal.show();

                if (ext === 'pdf') {
                    var iframe = document.createElement('iframe');
                    iframe.src = url;
                    iframe.style.cssText = 'width:100%;flex:1;border:none;';
                    iframe.onload = function () { previewLoading.style.display = 'none'; };
                    previewBody.appendChild(iframe);
                } else if (['jpg','jpeg','png','gif','webp','svg','bmp'].indexOf(ext) !== -1) {
                    var img = document.createElement('img');
                    img.src = url;
                    img.alt = name;
                    img.style.cssText = 'max-width:100%;max-height:calc(100vh - 70px);display:block;margin:0 auto;object-fit:contain;';
                    img.onload = function () { previewLoading.style.display = 'none'; };
                    img.onerror = function () {
                        previewLoading.style.display = 'none';
                        previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to load image.</div>';
                    };
                    previewBody.appendChild(img);
                } else if (['docx'].indexOf(ext) !== -1) {
                    // Word DOCX files via Mammoth.js
                    var xhr4 = new XMLHttpRequest();
                    xhr4.open('GET', url, true);
                    xhr4.responseType = 'arraybuffer';
                    xhr4.onload = function () {
                        previewLoading.style.display = 'none';
                        if (xhr4.status >= 200 && xhr4.status < 300) {
                            mammoth.convertToHtml({ arrayBuffer: xhr4.response })
                                .then(function (result) {
                                    var container = document.createElement('div');
                                    container.style.cssText = 'flex:1;overflow:auto;padding:1rem;font-size:0.9rem;line-height:1.6;';
                                    container.innerHTML = result.value;
                                    // Style images inside the rendered HTML
                                    container.querySelectorAll('img').forEach(function (img) {
                                        img.style.maxWidth = '100%';
                                        img.style.height = 'auto';
                                    });
                                    // Style tables inside the rendered HTML
                                    container.querySelectorAll('table').forEach(function (tbl) {
                                        tbl.className = 'table table-sm table-bordered';
                                    });
                                    previewBody.appendChild(container);
                                })
                                .catch(function () {
                                    previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to parse Word document.</div>';
                                });
                        } else {
                            previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to load file.</div>';
                        }
                    };
                    xhr4.onerror = function () {
                        previewLoading.style.display = 'none';
                        previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to load file.</div>';
                    };
                    xhr4.send();
                } else if (['xlsx','xls'].indexOf(ext) !== -1) {
                    // Excel files via SheetJS
                    var xhr3 = new XMLHttpRequest();
                    xhr3.open('GET', url, true);
                    xhr3.responseType = 'arraybuffer';
                    xhr3.onload = function () {
                        previewLoading.style.display = 'none';
                        if (xhr3.status >= 200 && xhr3.status < 300) {
                            try {
                                var data = new Uint8Array(xhr3.response);
                                var workbook = XLSX.read(data, { type: 'array' });
                                var container = document.createElement('div');
                                container.style.cssText = 'flex:1;overflow:auto;';
                                // Render tabs if multiple sheets
                                if (workbook.SheetNames.length > 1) {
                                    var tabs = document.createElement('ul');
                                    tabs.className = 'nav nav-tabs mb-2';
                                    tabs.style.fontSize = '0.8rem';
                                    workbook.SheetNames.forEach(function (sn, si) {
                                        var li = document.createElement('li');
                                        li.className = 'nav-item';
                                        var a = document.createElement('a');
                                        a.className = 'nav-link py-1 px-2' + (si === 0 ? ' active' : '');
                                        a.href = '#';
                                        a.textContent = sn;
                                        a.setAttribute('data-sheet-index', si);
                                        a.addEventListener('click', function (ev) {
                                            ev.preventDefault();
                                            tabs.querySelectorAll('.nav-link').forEach(function (t) { t.classList.remove('active'); });
                                            this.classList.add('active');
                                            var idx = parseInt(this.getAttribute('data-sheet-index'));
                                            var sheets = container.querySelectorAll('.xlsx-sheet');
                                            sheets.forEach(function (s, j) { s.style.display = j === idx ? '' : 'none'; });
                                        });
                                        li.appendChild(a);
                                        tabs.appendChild(li);
                                    });
                                    container.appendChild(tabs);
                                }
                                workbook.SheetNames.forEach(function (sn, si) {
                                    var sheet = workbook.Sheets[sn];
                                    var htmlStr = XLSX.utils.sheet_to_html(sheet, { editable: false });
                                    var wrap = document.createElement('div');
                                    wrap.className = 'xlsx-sheet';
                                    wrap.style.display = si === 0 ? '' : 'none';
                                    wrap.innerHTML = htmlStr;
                                    var tbl = wrap.querySelector('table');
                                    if (tbl) {
                                        tbl.className = 'table table-sm table-bordered mb-0';
                                        tbl.style.fontSize = '0.8rem';
                                    }
                                    container.appendChild(wrap);
                                });
                                previewBody.appendChild(container);
                            } catch (err) {
                                previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to parse Excel file.</div>';
                            }
                        } else {
                            previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to load file.</div>';
                        }
                    };
                    xhr3.onerror = function () {
                        previewLoading.style.display = 'none';
                        previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to load file.</div>';
                    };
                    xhr3.send();
                } else {
                    // Text-based files
                    var xhr2 = new XMLHttpRequest();
                    xhr2.open('GET', url, true);
                    xhr2.responseType = 'text';
                    xhr2.onload = function () {
                        previewLoading.style.display = 'none';
                        if (xhr2.status >= 200 && xhr2.status < 300) {
                            var pre = document.createElement('pre');
                            pre.style.cssText = 'flex:1;overflow:auto;white-space:pre-wrap;word-break:break-word;font-size:0.85rem;margin:0;';
                            pre.textContent = xhr2.responseText;
                            previewBody.appendChild(pre);
                        } else {
                            previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to load file.</div>';
                        }
                    };
                    xhr2.onerror = function () {
                        previewLoading.style.display = 'none';
                        previewBody.innerHTML = '<div class="text-danger text-center py-3">Failed to load file.</div>';
                    };
                    xhr2.send();
                }
            });

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

    // ---- Document Upload (Documents page) ----
    document.querySelectorAll('.doc-upload-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = this.closest('.doc-upload-form');
            if (form.classList.contains('is-uploading')) return;
            var clientName = form.getAttribute('data-client-name');
            if (clientName && !confirm('Upload documents to "' + clientName + '"?')) return;
            var fileInput = form.querySelector('.doc-file-input');
            fileInput.click();
        });
    });

    document.querySelectorAll('.doc-file-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (this.files.length === 0) return;
            var fileInput = this;
            var form = this.closest('.doc-upload-form');
            if (form.classList.contains('is-uploading')) return;
            showProgress(form, fileInput.files.length);
            docUploadViaAjax(form, fileInput);
        });
    });

    function docUploadViaAjax(form, fileInput) {
        var xhr = new XMLHttpRequest();
        var data = new FormData(form);
        xhr.open('POST', form.getAttribute('action'), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.responseType = 'json';

        xhr.upload.addEventListener('progress', function (evt) {
            if (evt.lengthComputable) updateProgress(form, (evt.loaded / evt.total) * 100);
        });
        xhr.upload.addEventListener('loadstart', function () { updateProgress(form, 1); });

        xhr.addEventListener('load', function () {
            var response = xhr.response;
            if (xhr.status >= 200 && xhr.status < 300 && response && response.success) {
                updateProgress(form, 100);
                var parts = getProgressParts(form);
                if (parts.percent && response.message) parts.percent.textContent = response.message;
                setTimeout(function () { window.location.reload(); }, 1500);
                return;
            }
            var msg = (response && response.message) ? response.message : 'Upload failed. Please try again.';
            alert(msg);
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

    // ---- Document LED click (view files) ----
    var docFilesModal = document.getElementById('docFilesModal');
    if (docFilesModal) {
        var bsDocFilesModal = new bootstrap.Modal(docFilesModal);

        function docEscapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }

        document.addEventListener('click', function (e) {
            var led = e.target.closest('[data-doc-led-clickable]');
            if (!led) return;

            var clientId    = led.getAttribute('data-client-id');
            var clientName  = led.getAttribute('data-client-name') || '';

            document.getElementById('docViewMeta').innerHTML =
                '<strong>' + docEscapeHtml(clientName) + '</strong>';
            document.getElementById('docViewLoading').style.display = '';
            document.getElementById('docViewEmpty').style.display = 'none';
            document.getElementById('docViewTableWrap').style.display = 'none';
            document.getElementById('docViewBody').innerHTML = '';
            bsDocFilesModal.show();

            var url = window.location.href.split('?')[0]
                + '?action=doc_files&client_id=' + encodeURIComponent(clientId);

            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.responseType = 'json';
            xhr.addEventListener('load', function () {
                document.getElementById('docViewLoading').style.display = 'none';
                var files = (xhr.response && xhr.response.files) ? xhr.response.files : [];
                if (files.length === 0) {
                    document.getElementById('docViewEmpty').style.display = '';
                    return;
                }
                var tbody = document.getElementById('docViewBody');
                var html = '';
                files.forEach(function (f, i) {
                    var dt = f.uploaded_at ? new Date(f.uploaded_at.replace(/-/g, '/')) : null;
                    var dateStr = dt ? dt.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' })
                        + ' ' + dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-';
                    var viewBtn = '';
                    if (isPreviewable(f.original_filename) && f.id) {
                        var previewUrl = window.location.href.split('?')[0] + '?action=doc_preview&doc_id=' + encodeURIComponent(f.id);
                        if (isDownloadOnly(f.original_filename)) {
                            viewBtn = '<a href="' + docEscapeHtml(previewUrl) + '" download class="btn btn-outline-secondary btn-sm py-0 px-1" style="font-size:0.7rem;" title="Download"><i class="bi bi-download"></i></a>';
                        } else {
                            viewBtn = '<button type="button" class="btn btn-outline-primary btn-sm py-0 px-1" style="font-size:0.7rem;" data-preview-url="' + docEscapeHtml(previewUrl) + '" data-preview-name="' + docEscapeHtml(f.original_filename) + '"><i class="bi bi-eye"></i></button>';
                        }
                    }
                    html += '<tr>'
                        + '<td>' + (i + 1) + '</td>'
                        + '<td class="text-break">' + docEscapeHtml(f.original_filename) + '</td>'
                        + '<td class="text-nowrap">' + dateStr + '</td>'
                        + '<td>' + docEscapeHtml(f.uploaded_by_name) + '</td>'
                        + '<td class="text-center">' + viewBtn + '</td>'
                        + '</tr>';
                });
                tbody.innerHTML = html;
                document.getElementById('docViewTableWrap').style.display = '';
            });
            xhr.addEventListener('error', function () {
                document.getElementById('docViewLoading').style.display = 'none';
                document.getElementById('docViewEmpty').textContent = 'Failed to load documents.';
                document.getElementById('docViewEmpty').style.display = '';
            });
            xhr.send();
        });
    }

    // ---- Document Download Modal ----
    var docDownloadModal = document.getElementById('docDownloadModal');
    if (docDownloadModal) {
        var bsDocDownloadModal = new bootstrap.Modal(docDownloadModal);
        var _docDlClientId = '';

        function docDlEscapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }

        document.querySelectorAll('.doc-download-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var clientId    = this.getAttribute('data-client-id');
                var clientName  = this.getAttribute('data-client-name') || '';
                _docDlClientId = clientId;

                document.getElementById('docDlMeta').innerHTML =
                    '<strong>' + docDlEscapeHtml(clientName) + '</strong>';
                document.getElementById('docDlLoading').style.display = '';
                document.getElementById('docDlEmpty').style.display = 'none';
                document.getElementById('docDlTableWrap').style.display = 'none';
                document.getElementById('docDlBody').innerHTML = '';
                document.getElementById('docDownloadSelectedBtn').disabled = true;
                var selectAllCb = document.getElementById('docSelectAll');
                if (selectAllCb) selectAllCb.checked = true;
                bsDocDownloadModal.show();

                var url = window.location.href.split('?')[0]
                    + '?action=doc_files&client_id=' + encodeURIComponent(clientId);

                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.responseType = 'json';
                xhr.addEventListener('load', function () {
                    document.getElementById('docDlLoading').style.display = 'none';
                    var files = (xhr.response && xhr.response.files) ? xhr.response.files : [];
                    if (files.length === 0) {
                        document.getElementById('docDlEmpty').style.display = '';
                        return;
                    }
                    var tbody = document.getElementById('docDlBody');
                    var html = '';
                    files.forEach(function (f, i) {
                        var dt = f.uploaded_at ? new Date(f.uploaded_at.replace(/-/g, '/')) : null;
                        var dateStr = dt ? dt.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' })
                            + ' ' + dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-';
                        html += '<tr>'
                            + '<td class="text-center"><input type="checkbox" class="doc-dl-check" value="' + f.id + '" checked></td>'
                            + '<td>' + (i + 1) + '</td>'
                            + '<td class="text-break">' + docDlEscapeHtml(f.original_filename) + '</td>'
                            + '<td class="text-nowrap">' + dateStr + '</td>'
                            + '<td>' + docDlEscapeHtml(f.uploaded_by_name) + '</td>'
                            + '</tr>';
                    });
                    tbody.innerHTML = html;
                    document.getElementById('docDlTableWrap').style.display = '';
                    document.getElementById('docDownloadSelectedBtn').disabled = false;
                    syncDocSelectAll();
                });
                xhr.addEventListener('error', function () {
                    document.getElementById('docDlLoading').style.display = 'none';
                    document.getElementById('docDlEmpty').textContent = 'Failed to load documents.';
                    document.getElementById('docDlEmpty').style.display = '';
                });
                xhr.send();
            });
        });

        // Select All checkbox
        var docSelectAll = document.getElementById('docSelectAll');
        if (docSelectAll) {
            docSelectAll.addEventListener('change', function () {
                var checks = document.querySelectorAll('.doc-dl-check');
                checks.forEach(function (c) { c.checked = docSelectAll.checked; });
                syncDocDlBtn();
            });
        }

        document.getElementById('docDlBody').addEventListener('change', function () {
            syncDocSelectAll();
            syncDocDlBtn();
        });

        function syncDocSelectAll() {
            var all = document.querySelectorAll('.doc-dl-check');
            var checked = document.querySelectorAll('.doc-dl-check:checked');
            var sa = document.getElementById('docSelectAll');
            if (sa) {
                sa.checked = all.length > 0 && checked.length === all.length;
                sa.indeterminate = checked.length > 0 && checked.length < all.length;
            }
            syncDocDlBtn();
        }

        function syncDocDlBtn() {
            var any = document.querySelectorAll('.doc-dl-check:checked').length > 0;
            document.getElementById('docDownloadSelectedBtn').disabled = !any;
        }

        // Download selected
        document.getElementById('docDownloadSelectedBtn').addEventListener('click', function () {
            var checked = document.querySelectorAll('.doc-dl-check:checked');
            if (checked.length === 0) return;

            var fileIds = [];
            checked.forEach(function (c) { fileIds.push(c.value); });

            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Preparing...';

            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('client_id', _docDlClientId);
            fileIds.forEach(function (id) { formData.append('file_ids[]', id); });

            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href.split('?')[0] + '?action=doc_download', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.responseType = 'json';
            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 300 && xhr.response && xhr.response.success) {
                    window.location.href = window.location.href.split('?')[0]
                        + '?action=doc_download_stream&token=' + encodeURIComponent(xhr.response.token);
                    setTimeout(function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-download"></i> Download Selected';
                    }, 2000);
                } else {
                    var msg = (xhr.response && xhr.response.message) ? xhr.response.message : 'Download failed.';
                    alert(msg);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-download"></i> Download Selected';
                }
            });
            xhr.addEventListener('error', function () {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-download"></i> Download Selected';
            });
            xhr.send(formData);
        });
    }
});
