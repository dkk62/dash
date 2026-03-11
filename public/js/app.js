// Work Progress System - Client JS

document.addEventListener('DOMContentLoaded', function () {
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
            var fileInput = form.querySelector('.file-input');
            fileInput.click();
        });
    });

    document.querySelectorAll('.file-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (this.files.length > 0) {
                var form = this.closest('.upload-form');
                if (form.classList.contains('is-uploading')) {
                    return;
                }
                showProgress(form, this.files.length);
                uploadViaAjax(form, this);
            }
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
