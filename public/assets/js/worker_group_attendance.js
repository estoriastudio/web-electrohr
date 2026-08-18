(function () {
    'use strict';

    var config = window.workerGroupAttendanceConfig || {};
    var feedback = document.getElementById('workerGroupAttendanceFeedback');

    if (!config.markUrlBase || !config.date) {
        return;
    }

    function showError(message) {
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.classList.remove('d-none');
    }

    function clearError() {
        if (!feedback) {
            return;
        }

        feedback.textContent = '';
        feedback.classList.add('d-none');
    }

    function renderStatus(row, attended) {
        var status = row.querySelector('.js-attendance-status');
        var buttons = row.querySelectorAll('.js-mark-attendance');

        if (status) {
            status.textContent = attended ? 'Show' : 'No show';
            status.className = 'js-attendance-status badge ' + (attended ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger');
        }

        buttons.forEach(function (button) {
            var isSelected = Number(button.dataset.attended) === Number(attended);
            button.className = 'btn btn-sm js-mark-attendance ' + (button.dataset.attended === '1'
                ? (isSelected ? 'btn-success' : 'btn-outline-success')
                : (isSelected ? 'btn-danger' : 'btn-outline-danger'));
        });
    }

    document.querySelectorAll('.js-mark-attendance').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = button.closest('.worker-group-attendance-row');
            var workerId = row ? row.dataset.workerId : null;

            if (!workerId) {
                return;
            }

            clearError();
            row.querySelectorAll('.js-mark-attendance').forEach(function (rowButton) {
                rowButton.disabled = true;
            });

            var payload = new URLSearchParams({
                date: config.date,
                attended: button.dataset.attended
            });

            fetch(config.markUrlBase + '/' + encodeURIComponent(workerId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString()
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'No fue posible registrar la asistencia.');
                    }

                    return data;
                });
            })
            .then(function (data) {
                renderStatus(row, data.attendance.attended);
            })
            .catch(function (error) {
                showError(error.message);
            })
            .finally(function () {
                row.querySelectorAll('.js-mark-attendance').forEach(function (rowButton) {
                    rowButton.disabled = false;
                });
            });
        });
    });
}());