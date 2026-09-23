(function () {
    'use strict';

    var config = window.stockExitItemsConfig || {};
    var root = document.querySelector('[data-stock-exit-items]');
    if (!root || !config.searchUrl) return;

    var body = root.querySelector('[data-items-body]');
    var search = root.querySelector('[data-concept-search]');
    var results = root.querySelector('[data-concept-results]');
    var selectedState = root.querySelector('[data-selected-state]');
    var searchState = root.querySelector('[data-search-state]');
    var selected;
    var debounce;

    function escape(value) { var element = document.createElement('div'); element.appendChild(document.createTextNode(value || '')); return element.innerHTML; }
    function count() { root.querySelector('[data-items-count]').textContent = body.querySelectorAll('[data-item-row]').length + ' conceptos'; }
    function reset() { selected = null; search.value = ''; results.classList.add('d-none'); selectedState.classList.add('d-none'); searchState.classList.remove('d-none'); }
    function choose(concept) { selected = concept; root.querySelector('[data-selected-code]').textContent = concept.code; root.querySelector('[data-selected-description]').textContent = concept.description; root.querySelector('[data-selected-quantity]').value = ''; searchState.classList.add('d-none'); selectedState.classList.remove('d-none'); }

    search.addEventListener('input', function () {
        var query = search.value.trim(); clearTimeout(debounce);
        if (query.length < 3) return results.classList.add('d-none');
        debounce = setTimeout(function () {
            fetch(config.searchUrl + '&q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json(); })
                .then(function (payload) { var concepts = Array.isArray(payload) ? payload : (payload.data || []); results.innerHTML = ''; concepts.forEach(function (concept) { var item = document.createElement('li'); item.className = 'list-group-item list-group-item-action'; item.innerHTML = '<span class="fw-semibold d-block">' + escape(concept.code) + '</span><span class="text-muted fs-12">' + escape(concept.description) + '</span>'; item.addEventListener('pointerdown', function (event) { event.preventDefault(); choose(concept); }); results.appendChild(item); }); results.classList.remove('d-none'); });
        }, 250);
    });
    root.querySelector('[data-change-concept]').addEventListener('click', reset);
    root.querySelector('[data-add-item]').addEventListener('click', function () {
        var quantity = root.querySelector('[data-selected-quantity]').value;
        if (!selected || !quantity || Number(quantity) <= 0 || body.querySelector('[data-concept-code="' + CSS.escape(selected.code) + '"]')) return;
        var empty = body.querySelector('[data-empty-row]'); if (empty) empty.remove();
        var index = Date.now(); var row = document.createElement('tr'); row.setAttribute('data-item-row', ''); row.setAttribute('data-concept-code', selected.code);
        row.innerHTML = '<td><span class="fw-semibold">' + escape(selected.code) + '</span><input type="hidden" name="items[' + index + '][concept_code]" value="' + escape(selected.code) + '"></td><td>' + escape(selected.description) + '</td><td>' + escape(selected.unit) + '</td><td><input type="number" name="items[' + index + '][quantity]" value="' + escape(quantity) + '" min="0.001" step="0.001" class="form-control form-control-sm text-end" required></td><td><button type="button" class="btn btn-soft-danger btn-sm" data-remove-item title="Eliminar concepto"><i class="ri-delete-bin-line"></i></button></td>';
        body.appendChild(row); count(); reset();
    });
    body.addEventListener('click', function (event) { var button = event.target.closest('[data-remove-item]'); if (!button) return; button.closest('[data-item-row]').remove(); if (!body.querySelector('[data-item-row]')) body.innerHTML = '<tr data-empty-row><td colspan="5" class="text-center text-muted py-3">Agregue al menos un suministro.</td></tr>'; count(); });
    document.getElementById('exitModal').addEventListener('hidden.bs.modal', function () { body.innerHTML = '<tr data-empty-row><td colspan="5" class="text-center text-muted py-3">Agregue al menos un suministro.</td></tr>'; count(); reset(); });
}());