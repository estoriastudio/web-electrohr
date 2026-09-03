(function () {
    'use strict';

    var config = window.workerGroupAttendanceConfig || {};
    var feedback = document.getElementById('workerGroupAttendanceFeedback');
    var absenceModalElement = document.getElementById('reportAbsenceModal');
    var absenceForm = document.getElementById('reportAbsenceForm');
    var absenceWorkerName = document.getElementById('reportAbsenceWorkerName');
    var absenceModal = absenceModalElement && window.bootstrap ? new bootstrap.Modal(absenceModalElement) : null;
    var selectedAbsenceRow = null;
    var incentiveModalElement = document.getElementById('registerAttendanceIncentiveModal');
    var incentiveForm = document.getElementById('registerAttendanceIncentiveForm');
    var incentiveWorkerName = document.getElementById('registerAttendanceIncentiveWorkerName');
    var incentiveModal = incentiveModalElement && window.bootstrap ? new bootstrap.Modal(incentiveModalElement) : null;
    var selectedIncentiveRow = null;

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
        row.querySelectorAll('.js-mark-attendance, .js-report-absence, .js-add-incentive').forEach(function (button) {
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

    function updateIncentiveFields() {
        if (!incentiveForm) {
            return;
        }

        var category = incentiveForm.querySelector('[name="category"]');
        var rateField = incentiveForm.querySelector('[data-incentive-rate-field]');
        var rateType = incentiveForm.querySelector('[name="rate_type"]');
        var overtimeFields = incentiveForm.querySelectorAll('[data-overtime-field]');
        var detailFields = incentiveForm.querySelectorAll('[data-incentive-details-field]');
        var hasCategory = category.value !== '';
        var isOvertime = category.value === 'overtime';
        var allowedTypes = category.value === 'day_off_exchange' ? ['A', 'B', 'C'] : ['A', 'B', 'C', 'D'];

        rateField.classList.toggle('d-none', !hasCategory || isOvertime);
        rateType.disabled = !hasCategory || isOvertime;
        overtimeFields.forEach(function (field) { field.classList.toggle('d-none', !isOvertime); });
        detailFields.forEach(function (field) { field.classList.toggle('d-none', !hasCategory); });

        Array.prototype.forEach.call(rateType.options, function (option) {
            option.hidden = !allowedTypes.includes(option.value);
        });

        if (!allowedTypes.includes(rateType.value)) {
            rateType.value = allowedTypes[0];
        }
    }

    function renderIncentive(row, incentive) {
        var container = row.querySelector('.js-worker-incentives');

        if (!container) {
            return;
        }

        var emptyState = container.querySelector('.js-no-incentives');
        if (emptyState) {
            emptyState.remove();
        }

        var badge = document.createElement('span');
        badge.className = 'badge bg-info-subtle text-info';
        badge.textContent = incentive.label + ' ' + incentive.detail;
        badge.title = incentive.label;
        container.appendChild(badge);
    }

    document.querySelectorAll('.js-add-incentive').forEach(function (button) {
        button.addEventListener('click', function () {
            selectedIncentiveRow = button.closest('.worker-group-attendance-row');

            if (!selectedIncentiveRow || !incentiveModal || !incentiveForm) {
                return;
            }

            incentiveForm.reset();
            incentiveWorkerName.textContent = selectedIncentiveRow.dataset.workerName;
            updateIncentiveFields();
            incentiveModal.show();
        });
    });

    if (incentiveForm) {
        incentiveForm.querySelector('[name="category"]').addEventListener('change', updateIncentiveFields);

        incentiveForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!selectedIncentiveRow || !config.incentiveUrlBase) {
                return;
            }

            clearError();
            setRowLoading(selectedIncentiveRow, true);

            fetch(config.incentiveUrlBase + '/' + encodeURIComponent(selectedIncentiveRow.dataset.workerId) + '/incentives', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(incentiveForm)
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'No fue posible registrar el incentivo.');
                    }

                    return data;
                });
            })
            .then(function (data) {
                renderIncentive(selectedIncentiveRow, data.incentive);
                incentiveModal.hide();
            })
            .catch(function (error) {
                showError(error.message);
            })
            .finally(function () {
                setRowLoading(selectedIncentiveRow, false);
            });
        });
    }
}());