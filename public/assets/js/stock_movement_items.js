(function () {
    'use strict';

    var config = window.stockPurchaseItemsConfig || {};
    var searchUrl = config.searchUrl || '';
    var container = document.querySelector('[data-stock-items="purchase"]');
    if (!container || !searchUrl) return;

    var body = container.querySelector('[data-items-body]');
    var searchState = container.querySelector('[data-search-state]');
    var selectedState = container.querySelector('[data-selected-state]');
    var searchInput = container.querySelector('[data-concept-search]');
    var results = container.querySelector('[data-concept-results]');
    var count = container.querySelector('[data-items-count]');
    var error = container.querySelector('[data-items-error]');
    var selected = null;
    var debounceTimer;
    var requestNumber = 0;

    function escHtml(value) {
        var element = document.createElement('div');
        element.appendChild(document.createTextNode(value == null ? '' : String(value)));
        return element.innerHTML;
    }

    function itemRows() {
        return body.querySelectorAll('[data-item-row]');
    }

    function updateCount() {
        var total = itemRows().length;
        count.textContent = total + (total === 1 ? ' concepto' : ' conceptos');
    }

    function showError(message) {
        error.textContent = message;
        error.classList.remove('d-none');
    }

    function resetSelector() {
        selected = null;
        searchInput.value = '';
        results.innerHTML = '';
        results.classList.add('d-none');
        error.classList.add('d-none');
        selectedState.classList.add('d-none');
        searchState.classList.remove('d-none');
        searchInput.focus();
    }

    function selectConcept(concept) {
        selected = concept;
        container.querySelector('[data-selected-code]').textContent = concept.code;
        container.querySelector('[data-selected-description]').textContent = concept.description;
        container.querySelector('[data-selected-quantity]').value = '';
        container.querySelector('[data-origin-certificate]').value = '';
        container.querySelector('[data-safety-certificate]').value = '';
        results.classList.add('d-none');
        error.classList.add('d-none');
        searchState.classList.add('d-none');
        selectedState.classList.remove('d-none');
        container.querySelector('[data-selected-quantity]').focus();
    }

    function renderResults(concepts, query) {
        results.innerHTML = '';
        if (!concepts.length) {
            results.innerHTML = '<li class="list-group-item text-muted fs-13">Sin resultados para ' + escHtml(query) + '.</li>';
        } else {
            concepts.forEach(function (concept) {
                var option = document.createElement('li');
                option.className = 'list-group-item list-group-item-action';
                option.innerHTML = '<span class="fw-semibold d-block">' + escHtml(concept.code) + '</span><span class="text-muted fs-12">' + escHtml(concept.description) + '</span>';
                option.addEventListener('pointerdown', function (event) {
                    event.preventDefault();
                    selectConcept(concept);
                });
                results.appendChild(option);
            });
        }
        results.classList.remove('d-none');
    }

    function searchConcepts() {
        var query = searchInput.value.trim();
        clearTimeout(debounceTimer);
        if (query.length < 3) {
            results.classList.add('d-none');
            return;
        }
        debounceTimer = setTimeout(function () {
            var currentRequest = ++requestNumber;
            fetch(searchUrl + '&q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                .then(function (payload) {
                    if (currentRequest !== requestNumber) return;
                    renderResults(Array.isArray(payload) ? payload : (payload.data || []), query);
                })
                .catch(function () {
                    if (currentRequest === requestNumber) showError('No fue posible buscar conceptos.');
                });
        }, 250);
    }

    function addItem() {
        var quantity = container.querySelector('[data-selected-quantity]').value;
        if (!selected || !quantity || Number(quantity) <= 0) {
            showError('Capture una cantidad mayor a cero.');
            return;
        }
        if (body.querySelector('[data-concept-code="' + CSS.escape(selected.code) + '"]')) {
            showError('Este concepto ya fue agregado al movimiento.');
            return;
        }

        var emptyRow = body.querySelector('[data-empty-row]');
        if (emptyRow) emptyRow.remove();
        var index = Date.now();
        var row = document.createElement('tr');
        row.setAttribute('data-item-row', '');
        row.setAttribute('data-concept-code', selected.code);
        row.innerHTML = '<td><span class="fw-semibold">' + escHtml(selected.code) + '</span><input type="hidden" name="items[' + index + '][concept_code]" value="' + escHtml(selected.code) + '"></td>'
            + '<td>' + escHtml(selected.description) + '</td>'
            + '<td>' + escHtml(selected.unit || '') + '</td>'
            + '<td><input type="number" name="items[' + index + '][quantity]" value="' + escHtml(quantity) + '" min="0.001" step="0.001" class="form-control form-control-sm text-end" required></td>'
            + '<td><input type="file" name="items[' + index + '][origin_certificate]" accept="application/pdf" class="form-control form-control-sm mb-1" title="Certificado de origen"><input type="file" name="items[' + index + '][safety_certificate]" accept="application/pdf" class="form-control form-control-sm" title="Certificado de seguridad"></td>'
            + '<td><button type="button" class="btn btn-soft-danger btn-sm" data-remove-item title="Eliminar concepto"><i class="ri-delete-bin-line"></i></button></td>';
        row.querySelector('input[name$="[origin_certificate]"]').files = container.querySelector('[data-origin-certificate]').files;
        row.querySelector('input[name$="[safety_certificate]"]').files = container.querySelector('[data-safety-certificate]').files;
        body.appendChild(row);
        updateCount();
        resetSelector();
    }

    searchInput.addEventListener('input', searchConcepts);
    container.querySelector('[data-add-item]').addEventListener('click', addItem);
    container.querySelector('[data-change-concept]').addEventListener('click', resetSelector);
    body.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-item]');
        if (!button) return;
        button.closest('[data-item-row]').remove();
        if (!itemRows().length) {
            body.innerHTML = '<tr data-empty-row><td colspan="6" class="text-center text-muted py-3">Agregue al menos un suministro.</td></tr>';
        }
        updateCount();
    });

    document.getElementById('entryModal').addEventListener('hidden.bs.modal', function () {
        body.innerHTML = '<tr data-empty-row><td colspan="6" class="text-center text-muted py-3">Agregue al menos un suministro.</td></tr>';
        updateCount();
        resetSelector();
    });
}());