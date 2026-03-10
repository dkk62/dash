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
});
