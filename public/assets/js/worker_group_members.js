(function () {
    'use strict';

    var config = window.workerGroupMembersConfig || {};
    var tbody = document.getElementById('workerGroupMembersTbody');
    var countBadge = document.getElementById('workerGroupMembersCount');
    var searchInput = document.getElementById('workerGroupMemberSearch');
    var results = document.getElementById('workerGroupMemberResults');
    var searchState = document.getElementById('workerGroupMemberSearchState');
    var selectedState = document.getElementById('workerGroupMemberSelectedState');
    var selectedName = document.getElementById('workerGroupMemberName');
    var selectedDetails = document.getElementById('workerGroupMemberDetails');
    var joinedAtInput = document.getElementById('workerGroupMemberJoinedAt');
    var addButton = document.getElementById('workerGroupMemberAdd');
    var changeButton = document.getElementById('workerGroupMemberChange');
    var errorBox = document.getElementById('workerGroupMemberError');
    var selectedWorker = null;
    var searchTimer = null;
    var searchRequest = 0;

    if (!tbody || !searchInput || !results || !config.searchUrl || !config.storeUrl) {
        return;
    }

    function escHtml(value) {
        var node = document.createElement('div');
        node.appendChild(document.createTextNode(value == null ? '' : String(value)));

        return node.innerHTML;
    }

    function updateCount() {
        if (countBadge) {
            countBadge.textContent = tbody.querySelectorAll('.worker-group-member-row').length;
        }
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    }

    function closeResults() {
        results.innerHTML = '';
        results.classList.add('d-none');
    }

    function resetSelection() {
        selectedWorker = null;
        selectedState.classList.add('d-none');
        searchState.classList.remove('d-none');
        searchInput.value = '';
        closeResults();
        clearError();
        searchInput.focus();
    }

    function selectWorker(worker) {
        selectedWorker = worker;
        selectedName.textContent = worker.first_name + ' ' + worker.last_name;
        selectedDetails.textContent = [worker.employee_code, worker.job_title].filter(Boolean).join(' - ') || 'Sin puesto registrado';
        searchState.classList.add('d-none');
        selectedState.classList.remove('d-none');
        closeResults();
        clearError();
    }

    function renderResults(workers) {
        if (!workers.length) {
            results.innerHTML = '<li class="list-group-item text-muted fs-13">No hay trabajadores disponibles.</li>';
            results.classList.remove('d-none');
            return;
        }

        results.innerHTML = workers.map(function (worker, index) {
            var details = [worker.employee_code, worker.job_title].filter(Boolean).join(' - ') || 'Sin puesto registrado';

            return '<li class="list-group-item list-group-item-action p-0">'
                + '<button type="button" class="btn btn-link text-start text-decoration-none text-dark w-100 px-3 py-2 js-worker-group-member-result" data-index="' + index + '">'
                + '<span class="d-block fw-medium">' + escHtml(worker.first_name + ' ' + worker.last_name) + '</span>'
                + '<span class="d-block text-muted fs-12">' + escHtml(details) + '</span>'
                + '</button></li>';
        }).join('');
        results.classList.remove('d-none');

        results.querySelectorAll('.js-worker-group-member-result').forEach(function (button) {
            button.addEventListener('click', function () {
                selectWorker(workers[Number(button.dataset.index)]);
            });
        });
    }

    function searchWorkers(query) {
        var requestId = ++searchRequest;

        fetch(config.searchUrl + '?q=' + encodeURIComponent(query), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('No fue posible buscar trabajadores.');
            }

            return response.json();
        })
        .then(function (workers) {
            if (requestId === searchRequest) {
                renderResults(workers);
            }
        })
        .catch(function (error) {
            if (requestId === searchRequest) {
                showError(error.message);
                closeResults();
            }
        });
    }

    function appendMember(member) {
        var emptyRow = document.getElementById('workerGroupMembersEmpty');
        if (emptyRow) {
            emptyRow.remove();
        }

        var row = document.createElement('tr');
        row.className = 'worker-group-member-row';
        row.dataset.workerId = member.id;
        row.innerHTML = '<td><a href="' + escHtml(config.workerShowBaseUrl + '/' + member.id) + '" class="text-dark">'
            + escHtml(member.first_name + ' ' + member.last_name) + '</a></td>'
            + '<td>' + escHtml(member.job_title || '—') + '</td>'
            + '<td>' + escHtml(member.joined_at) + '</td>'
            + '<td class="text-end"><form class="d-inline-flex gap-2" method="POST" action="'
            + escHtml(config.removeUrlBase + '/' + member.id) + '">'
            + '<input type="hidden" name="_token" value="' + escHtml(config.csrfToken) + '">'
            + '<input type="hidden" name="_method" value="DELETE">'
            + '<input type="date" name="left_at" value="' + new Date().toISOString().slice(0, 10) + '" class="form-control form-control-sm" required>'
            + '<button class="btn btn-sm btn-outline-danger" title="Remover"><i class="ri-user-unfollow-line"></i></button>'
            + '</form></td>';
        tbody.appendChild(row);
        updateCount();
    }

    searchInput.addEventListener('input', function () {
        var query = searchInput.value.trim();
        clearTimeout(searchTimer);
        clearError();

        if (query.length < 2) {
            closeResults();
            return;
        }

        searchTimer = setTimeout(function () {
            searchWorkers(query);
        }, 250);
    });

    changeButton.addEventListener('click', resetSelection);

    addButton.addEventListener('click', function () {
        if (!selectedWorker || !joinedAtInput.value) {
            showError('Selecciona un trabajador e indica la fecha de ingreso.');
            return;
        }

        clearError();
        addButton.disabled = true;
        addButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Asignando...';

        var payload = new URLSearchParams({
            worker_id: selectedWorker.id,
            joined_at: joinedAtInput.value
        });

        fetch(config.storeUrl, {
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
                    throw new Error(data.message || 'No fue posible asignar al trabajador.');
                }

                return data;
            });
        })
        .then(function (data) {
            appendMember(data.member);
            resetSelection();
        })
        .catch(function (error) {
            showError(error.message);
        })
        .finally(function () {
            addButton.disabled = false;
            addButton.innerHTML = '<i class="ri-user-add-line me-1"></i>Asignar a cuadrilla';
        });
    });
}());