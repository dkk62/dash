// Work Progress System - Client JS

document.addEventListener('DOMContentLoaded', function () {
    // Upload buttons: click triggers hidden multi-file input, then auto-submit
    document.querySelectorAll('.upload-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = this.closest('.upload-form');
            var fileInput = form.querySelector('.file-input');
            fileInput.click();
        });
    });

    document.querySelectorAll('.file-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (this.files.length > 0) {
                var form = this.closest('.upload-form');
                form.submit();
            }
        });
    });
});
