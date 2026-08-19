(function () {
    'use strict';

    var config = window.workerGroupAttendanceConfig || {};
    var feedback = document.getElementById('workerGroupAttendanceFeedback');
    var absenceModalElement = document.getElementById('reportAbsenceModal');
    var absenceForm = document.getElementById('reportAbsenceForm');
    var absenceWorkerName = document.getElementById('reportAbsenceWorkerName');
    var absenceModal = absenceModalElement && window.bootstrap ? new bootstrap.Modal(absenceModalElement) : null;
    var selectedAbsenceRow = null;

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

    function renderStatus(row, attendance) {
        var status = row.querySelector('.js-attendance-status');
        var buttons = row.querySelectorAll('.js-mark-attendance');
        var absenceButton = row.querySelector('.js-report-absence');
        var attended = Boolean(attendance.attended);

        if (status) {
            status.textContent = attendance.status_label || (attended ? 'Show' : 'No show');
            status.className = 'js-attendance-status badge ' + (attended ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger');
        }

        buttons.forEach(function (button) {
            var isSelected = Number(button.dataset.attended) === Number(attended);
            button.className = 'btn btn-sm js-mark-attendance ' + (button.dataset.attended === '1'
                ? (isSelected ? 'btn-success' : 'btn-outline-success')
                : (isSelected ? 'btn-danger' : 'btn-outline-danger'));
        });

        if (absenceButton) {
            absenceButton.className = 'btn btn-sm js-report-absence ' + (attended ? 'btn-outline-danger' : 'btn-danger');
        }
    }

    function setRowLoading(row, loading) {
        row.querySelectorAll('.js-mark-attendance, .js-report-absence').forEach(function (button) {
            button.disabled = loading;
        });
    }

    function submitAttendance(row, body, headers) {
        clearError();
        setRowLoading(row, true);

        return fetch(config.markUrlBase + '/' + encodeURIComponent(row.dataset.workerId), {
            method: 'POST',
            headers: headers,
            body: body
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
            renderStatus(row, data.attendance);
            return data;
        })
        .catch(function (error) {
            showError(error.message);
            throw error;
        })
        .finally(function () {
            setRowLoading(row, false);
        });
    }

    document.querySelectorAll('.js-mark-attendance').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = button.closest('.worker-group-attendance-row');

            if (!row) {
                return;
            }

            var payload = new URLSearchParams({
                date: config.date,
                attended: '1'
            });

            submitAttendance(row, payload.toString(), {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }).catch(function () {});
        });
    });

    document.querySelectorAll('.js-report-absence').forEach(function (button) {
        button.addEventListener('click', function () {
            selectedAbsenceRow = button.closest('.worker-group-attendance-row');

            if (!selectedAbsenceRow || !absenceModal || !absenceForm) {
                return;
            }

            absenceForm.reset();
            absenceWorkerName.textContent = selectedAbsenceRow.dataset.workerName;
            absenceModal.show();
        });
    });

    if (absenceForm) {
        absenceForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!selectedAbsenceRow) {
                return;
            }

            var payload = new FormData(absenceForm);
            payload.append('date', config.date);
            payload.append('attended', '0');

            submitAttendance(selectedAbsenceRow, payload, {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            })
            .then(function () {
                absenceModal.hide();
            })
            .catch(function () {});
        });
    }
}());