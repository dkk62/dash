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
            if (xhr.status >= 200 && xhr.status < 300) {
                updateProgress(form, 100);
                setTimeout(function () {
                    window.location.reload();
                }, 1500);
                return;
            }

            var responseMessage = 'Upload failed. Please try again.';
            var response = xhr.response;
            if (response && typeof response === 'object' && response.message) {
                responseMessage = response.message;
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
                    var list = existingFiles.map(function (name, i) {
                        return (i + 1) + '. ' + name;
                    }).join('\n');
                    var msg = 'The following existing file(s) will be replaced:\n\n'
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

    // ---- Stage Note Modal ----
    var noteModal = document.getElementById('noteModal');
    if (noteModal) {
        var noteModalText     = document.getElementById('noteModalText');
        var noteModalReadonly = document.getElementById('noteModalReadonly');
        var noteModalMeta     = document.getElementById('noteModalMeta');
        var noteCharCount     = document.getElementById('noteCharCount');
        var noteSaveBtn       = document.getElementById('noteSaveBtn');
        var _notePeriodId     = '';
        var _noteStage        = '';
        var _noteAccountId    = '0';
        var _noteTriggerBtn   = null;

        var stageLabels = { stage1: 'Stage 1', stage2: 'Stage 2', stage3: 'Stage 3', stage4: 'Stage 4' };

        noteModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            _noteTriggerBtn  = btn;
            _notePeriodId    = btn.getAttribute('data-period-id');
            _noteStage       = btn.getAttribute('data-stage');
            _noteAccountId   = btn.getAttribute('data-account-id') || '0';
            var canEdit      = btn.getAttribute('data-can-edit') === '1';
            var noteText     = btn.getAttribute('data-note') || '';
            var periodLabel  = btn.getAttribute('data-period-label') || ('Period #' + _notePeriodId);

            noteModalMeta.textContent = (stageLabels[_noteStage] || _noteStage) + '  \u00b7  ' + periodLabel;

            if (canEdit) {
                noteModalText.value = noteText;
                noteModalText.style.display = '';
                noteModalReadonly.style.display = 'none';
                noteSaveBtn.style.display = '';
                noteCharCount.textContent = noteText.length + ' / 1000';
                setTimeout(function () { noteModalText.focus(); }, 300);
            } else {
                noteModalReadonly.textContent = noteText || '(no note saved)';
                noteModalText.style.display = 'none';
                noteModalReadonly.style.display = '';
                noteSaveBtn.style.display = 'none';
                noteCharCount.textContent = '';
            }
        });

        noteModalText.addEventListener('input', function () {
            noteCharCount.textContent = this.value.length + ' / 1000';
        });

        noteSaveBtn.addEventListener('click', function () {
            var note = noteModalText.value.trim();
            noteSaveBtn.disabled = true;

            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('period_id', _notePeriodId);
            formData.append('stage', _noteStage);
            formData.append('account_id', _noteAccountId);
            formData.append('note', note);

            var xhr = new XMLHttpRequest();
            var saveUrl = window.location.href.split('?')[0] + '?action=save_note';
            xhr.open('POST', saveUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.responseType = 'json';

            xhr.addEventListener('load', function () {
                noteSaveBtn.disabled = false;
                if (xhr.status >= 200 && xhr.status < 300 && xhr.response && xhr.response.success) {
                    if (_noteTriggerBtn) {
                        _noteTriggerBtn.setAttribute('data-note', note);
                        if (note !== '') {
                            _noteTriggerBtn.classList.add('note-has-content');
                            _noteTriggerBtn.title = 'View/Edit Note';
                        } else {
                            _noteTriggerBtn.classList.remove('note-has-content');
                            _noteTriggerBtn.title = 'Add Note';
                        }
                    }
                    bootstrap.Modal.getInstance(noteModal).hide();
                } else {
                    var msg = (xhr.response && xhr.response.message) ? xhr.response.message : 'Failed to save note.';
                    alert(msg);
                }
            });

            xhr.addEventListener('error', function () {
                noteSaveBtn.disabled = false;
                alert('Network error. Please try again.');
            });

            xhr.send(formData);
        });
    }
});
