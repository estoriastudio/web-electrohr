/**
 * ============================================================================
 *  material_request.js — Detalle de Solicitud de Material (SOLMAT)
 * ============================================================================
 *
 *  Controla la interacción del cuadro "Conceptos" en la vista
 *  `material_requests/show.blade.php`:
 *
 *    1. Buscador de conceptos con paginación ("Cargar más").
 *    2. Alta de un concepto vía AJAX, capturando la cantidad por cada obra.
 *    3. Popover de "Desglose por obra" sobre el valor de la cantidad.
 *    4. Edición en línea del desglose por obra (fila colapsable por ítem).
 *    5. Eliminación de un concepto vía AJAX.
 *
 *  Configuración de entrada (definida en el Blade, justo antes de cargar
 *  este archivo):
 *
 *    window.solmatConfig = {
 *        storeUrl:     '.../material-requests/{id}/items',   // POST alta, base para DELETE/PATCH
 *        searchUrl:    '.../concepts/search?type=materiales&paginated=1&per_page=50',
 *        allowedWorks: [{ id: '1', name: 'Obra A' }, ...],   // obras ligadas a la SOLMAT
 *    };
 *
 *  El token CSRF se lee de <meta name="csrf-token">.
 *
 *  Notas de diseño:
 *    - El popover y los manejadores de la tabla se inicializan SIEMPRE, incluso
 *      para usuarios sin permiso de edición (que solo ven el valor y su desglose).
 *    - El panel de alta y sus manejadores solo se conectan si el panel existe
 *      en el DOM (usuarios con rol admin|Solmat).
 * ============================================================================
 */
(function () {
    'use strict';

    // ── Configuración y token CSRF ────────────────────────────────────────
    var config    = window.solmatConfig || {};
    var storeUrl  = config.storeUrl || '';
    var searchUrl = config.searchUrl || '';
    var allowedWorks = Array.isArray(config.allowedWorks) ? config.allowedWorks : [];

    var csrfMeta  = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';

    // ── Referencias base a la tabla de conceptos ──────────────────────────
    var tbody      = document.getElementById('solmat_items_tbody');
    var countBadge = document.getElementById('solmat_items_count');

    // Si no existe la tabla, no estamos en la vista correcta: abortar.
    if (!tbody) return;

    /* =========================================================================
     *  UTILIDADES GENERALES
     * ========================================================================= */

    /**
     * Escapa una cadena para insertarla de forma segura como HTML/atributo.
     * @param {*} str  Valor a escapar (se convierte a texto).
     * @returns {string} Cadena con entidades HTML seguras.
     */
    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str != null ? String(str) : ''));
        return d.innerHTML;
    }

    /**
     * Normaliza un número a texto con dos decimales.
     * @param {*} value  Valor numérico (o convertible).
     * @returns {string} p.ej. "12.50". Devuelve "0.00" si no es numérico.
     */
    function formatQty(value) {
        return Number(value || 0).toFixed(2);
    }

    /**
     * Cuenta las filas de concepto actualmente en la tabla.
     * @returns {number}
     */
    function itemCount() {
        return tbody.querySelectorAll('tr.solmat-item-row').length;
    }

    /**
     * Refresca el badge "N ítem(s)" del encabezado del cuadro.
     */
    function updateBadge() {
        if (countBadge) countBadge.textContent = itemCount() + ' ítem(s)';
    }

    /**
     * Renumera la columna "#" tras insertar o eliminar filas.
     */
    function renumber() {
        tbody.querySelectorAll('tr.solmat-item-row').forEach(function (row, i) {
            var td = row.querySelector('td:first-child');
            if (td) td.textContent = i + 1;
        });
    }

    /* =========================================================================
     *  POPOVER "DESGLOSE POR OBRA"
     * ========================================================================= */

    /**
     * Construye el HTML del contenido del popover a partir del desglose.
    * Se presenta como una tabla compacta (Obra / Cantidad / Estado). Los nombres de
     * obra largos se truncan con ellipsis y conservan el nombre completo en el
     * atributo `title` (visible al pasar el cursor).
     *
     * @param {Array<{work_id:*, work_name:string, quantity:*}>} breakdown
     * @returns {string} HTML de la tabla de desglose.
     */
    function buildBreakdownContent(breakdown) {
        if (!Array.isArray(breakdown) || breakdown.length === 0) {
            return '<span class="text-muted fs-13">Sin desglose por obra.</span>';
        }

        var rows = breakdown.map(function (row) {
            var name = escHtml(row.work_name || row.work_id);
            var nameAttr = name.replace(/"/g, '&quot;'); // seguro para el atributo title
            return '<tr>'
                + '<td><div class="text-truncate solmat-breakdown-name" title="' + nameAttr + '">' + name + '</div></td>'
                + '<td class="text-end fw-semibold ps-3">' + escHtml(String(row.quantity)) + '</td>'
                + '<td class="text-end ps-2">'
                + (row.is_committed ? '<span class="badge bg-warning-subtle text-warning">Comprometido</span>' : '')
                + '</td>'
                + '</tr>';
        }).join('');

        return '<table class="table table-sm table-borderless align-middle mb-0 solmat-breakdown-table">'
            + '<thead>'
            + '<tr class="text-muted">'
            + '<th class="fw-semibold">Obra</th>'
            + '<th class="fw-semibold text-end">Cant.</th>'
            + '<th></th>'
            + '</tr>'
            + '</thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table>';
    }

    /**
     * Inicializa los popovers de Bootstrap sobre los botones de cantidad
     * (`.js-solmat-qty-popover`). El desglose se lee del atributo
     * `data-breakdown` (JSON). Es idempotente: no re-inicializa un botón que
     * ya tenga popover (`_solmatPopover`).
     *
     * @param {Element|Document} [scope]  Ámbito de búsqueda; por defecto `document`.
     */
    function initQtyPopovers(scope) {
        var root = scope || document;
        root.querySelectorAll('.js-solmat-qty-popover').forEach(function (trigger) {
            if (trigger._solmatPopover) return;

            var breakdown = [];
            try {
                breakdown = JSON.parse(trigger.getAttribute('data-breakdown') || '[]');
            } catch (error) {
                breakdown = [];
            }

            if (window.bootstrap && window.bootstrap.Popover) {
                trigger._solmatPopover = new bootstrap.Popover(trigger, {
                    trigger: 'focus',
                    placement: 'top',
                    html: true,
                    sanitize: false,
                    content: buildBreakdownContent(breakdown),
                    customClass: 'solmat-breakdown-popover'
                });
            }
        });
    }

    /* =========================================================================
     *  EDICIÓN EN LÍNEA DEL DESGLOSE (fila colapsable por ítem)
     * ========================================================================= */

    /**
     * Muestra la fila de edición del desglose.
     * @param {string} targetSelector  Selector de la fila (p.ej. "#solmat_breakdown_edit_5").
     */
    function openEditBreakdown(targetSelector) {
        var editRow = document.querySelector(targetSelector);
        if (!editRow) return;

        editRow.classList.remove('d-none');
    }

    /**
     * Oculta la fila de edición del desglose.
     * @param {Element} editRow  La fila <tr> a ocultar.
     */
    function closeEditBreakdown(editRow) {
        if (!editRow) return;

        editRow.classList.add('d-none');
    }

    /**
     * Recalcula y pinta el total (suma de cantidades por obra) del formulario
     * de edición mientras el usuario escribe.
     * @param {HTMLFormElement} form  Formulario `.solmat-breakdown-form`.
     */
    function updateBreakdownTotal(form) {
        if (!form) return;

        var total = Array.from(form.querySelectorAll('.js-solmat-breakdown-qty')).reduce(function (acc, input) {
            var value = parseFloat(input.value);
            return acc + (isNaN(value) ? 0 : value);
        }, 0);

        var totalBadge = form.querySelector('.js-solmat-breakdown-total');
        if (totalBadge) {
            totalBadge.textContent = total.toFixed(2);
        }
    }

    /* =========================================================================
     *  INSERCIÓN DE FILAS (usado tras el alta AJAX)
     * ========================================================================= */

    /**
     * Inserta en la tabla la fila de un concepto recién creado (respuesta del
     * servidor) más, si aplica, su fila colapsable de edición de desglose.
     * Replica exactamente la estructura renderizada por el Blade para que las
     * filas nuevas y las existentes se comporten igual.
     *
     * @param {Object} item                     Ítem devuelto por el controlador.
     * @param {number} item.id
     * @param {string} item.code
     * @param {string} item.description
     * @param {string} item.unit
     * @param {number} item.quantity            Cantidad total.
     * @param {string|null} item.file_url       URL temporal de especificaciones.
     * @param {Array<{work_id:*, work_name:string, quantity:number}>} item.work_quantities
     */
    function appendRow(item) {
        var emptyRow = document.getElementById('solmat_empty_row');
        if (emptyRow) emptyRow.remove();

        var num  = itemCount() + 1;
        var qtyF = formatQty(item.quantity);
        var breakdown = Array.isArray(item.work_quantities) ? item.work_quantities : [];

        // Desglose normalizado (con cantidades a 2 decimales) para el popover.
        var breakdownJson = JSON.stringify(breakdown.map(function (row) {
            return {
                work_id: row.work_id,
                work_name: row.work_name,
                quantity: formatQty(row.quantity),
                is_committed: !!row.is_committed
            };
        }));

        // ── Fila principal del concepto ──
        var tr = document.createElement('tr');
        tr.className      = 'solmat-item-row solmat-item-new';
        tr.dataset.itemId = item.id;
        tr.innerHTML =
            '<td>' + num + '</td>'
            + '<td><span class="fw-semibold">' + escHtml(item.code) + '</span></td>'
            + '<td>' + escHtml(item.description) + '</td>'
            + '<td>' + escHtml(item.unit) + '</td>'
            + '<td class="text-end fw-semibold">'
            + '<button type="button"'
            + ' class="btn btn-link btn-sm p-0 text-decoration-none text-dark js-solmat-qty-popover"'
            + ' title="Desglose por obra">'
            + '<span class="d-inline-flex align-items-center gap-1">'
            + '<span>' + escHtml(qtyF) + '</span>'
            + '<i class="ri-information-line fs-13 text-primary"></i>'
            + '</span>'
            + '</button>'
            + '<span class="badge bg-warning-subtle text-warning ms-1 js-solmat-commitment-badge d-none" title="Incluye cantidades comprometidas"><i class="ri-lock-line"></i></span>'
            + '</td>'
            + '<td>'
            + (item.file_url
                ? '<a href="' + escHtml(item.file_url) + '" target="_blank" class="btn btn-light btn-sm" title="Descargar especificaciones"><i class="ri-file-download-line"></i></a>'
                : '<span class="text-muted fs-12">—</span>')
            + '</td>'
            + '<td>'
                + '<div class="d-flex gap-1">'
                + (breakdown.length > 0
                    ? '<button type="button" class="btn btn-soft-primary btn-sm js-solmat-open-edit" data-edit-target="#solmat_breakdown_edit_' + escHtml(item.id) + '" title="Editar cantidades"><i class="ri-edit-line"></i></button>'
                    : '')
                + '<form method="POST" action="' + storeUrl + '/' + escHtml(item.id) + '" class="solmat-delete-form">'
                + '<input type="hidden" name="_token" value="' + escHtml(csrfToken) + '">'
                + '<input type="hidden" name="_method" value="DELETE">'
                + '<button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">'
                + '<i class="ri-delete-bin-line"></i></button>'
                + '</form>'
                + '</div>'
            + '</td>';

        // El desglose se asigna con setAttribute (no como cadena inline) para
        // que el navegador escape correctamente las comillas del JSON.
        var qtyBtn = tr.querySelector('.js-solmat-qty-popover');
        if (qtyBtn) qtyBtn.setAttribute('data-breakdown', breakdownJson);

        tbody.appendChild(tr);

        // ── Fila colapsable de edición del desglose (solo si hay obras) ──
        if (breakdown.length > 0) {
            var editDetail = document.createElement('tr');
            editDetail.className = 'bg-light-subtle solmat-breakdown-row d-none';
            editDetail.id = 'solmat_breakdown_edit_' + item.id;
            editDetail.innerHTML =
                '<td colspan="7" class="py-2">'
                + '<form action="' + storeUrl + '/' + escHtml(item.id) + '" method="POST" class="solmat-breakdown-form">'
                + '<input type="hidden" name="_token" value="' + escHtml(csrfToken) + '">'
                + '<input type="hidden" name="_method" value="PATCH">'
                + '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">'
                + '<span class="text-muted fs-12 fw-semibold">Editar cantidades por obra</span>'
                + '<span class="badge bg-primary-subtle text-primary">Total: <span class="js-solmat-breakdown-total">' + escHtml(qtyF) + '</span></span>'
                + '</div>'
                + '<div class="row g-2">'
                + breakdown.map(function (row, index) {
                    return ''
                        + '<div class="col-md-6 col-lg-4">'
                        + '<label class="form-label fs-12 mb-1">' + escHtml(row.work_name || row.work_id) + '</label>'
                        + '<input type="number" name="work_quantities[' + index + '][quantity]" class="form-control js-solmat-breakdown-qty" value="' + escHtml(formatQty(row.quantity)) + '" min="0.01" step="0.01" inputmode="decimal">'
                        + '<input type="hidden" name="work_quantities[' + index + '][work_id]" value="' + escHtml(String(row.work_id)) + '">'
                        + '<input type="hidden" name="work_quantities[' + index + '][is_committed]" value="0">'
                        + '<div class="form-check mt-1">'
                        + '<input type="checkbox" class="form-check-input" id="solmat_commitment_' + escHtml(item.id) + '_' + escHtml(String(row.work_id)) + '" name="work_quantities[' + index + '][is_committed]" value="1"' + (row.is_committed ? ' checked' : '') + '>'
                        + '<label class="form-check-label fs-12" for="solmat_commitment_' + escHtml(item.id) + '_' + escHtml(String(row.work_id)) + '">Comprometido</label>'
                        + '</div>'
                        + '</div>';
                }).join('')
                + '</div>'
                + '<div class="d-flex justify-content-end gap-2 mt-3">'
                + '<button type="button" class="btn btn-light btn-sm js-solmat-cancel-edit">Cancelar</button>'
                + '<button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>'
                + '</div>'
                + '</form>'
                + '</td>';
            tbody.appendChild(editDetail);
        }

        initQtyPopovers(tr);
    }

    /* =========================================================================
     *  MANEJADORES DELEGADOS SOBRE LA TABLA (siempre activos)
     * ========================================================================= */

    // Abrir la fila de edición del desglose.
    tbody.addEventListener('click', function (e) {
        var editButton = e.target.closest('.js-solmat-open-edit');
        if (!editButton) return;

        e.preventDefault();
        openEditBreakdown(editButton.getAttribute('data-edit-target'));
    });

    // Cancelar la edición (ocultar la fila).
    tbody.addEventListener('click', function (e) {
        var cancelButton = e.target.closest('.js-solmat-cancel-edit');
        if (!cancelButton) return;

        e.preventDefault();
        closeEditBreakdown(cancelButton.closest('tr'));
    });

    // Guardar el desglose editado (PATCH vía AJAX).
    tbody.addEventListener('submit', function (e) {
        var breakdownForm = e.target.closest('.solmat-breakdown-form');
        if (!breakdownForm) return;

        e.preventDefault();

        var submitBtn = breakdownForm.querySelector('button[type=submit]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        fetch(breakdownForm.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(breakdownForm)
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(function (payload) {
            // La fila de edición está justo debajo de su fila de concepto.
            var breakdownRow = breakdownForm.closest('tr');
            var itemRow = breakdownRow ? breakdownRow.previousElementSibling : null;

            if (itemRow) {
                // Actualizar el valor mostrado y regenerar el popover con el nuevo desglose.
                var qtyButton = itemRow.querySelector('.js-solmat-qty-popover');
                if (qtyButton) {
                    qtyButton.innerHTML = '<span class="d-inline-flex align-items-center gap-1"><span>' + escHtml(formatQty(payload.quantity)) + '</span><i class="ri-information-line fs-13 text-primary"></i></span>';
                    qtyButton.setAttribute('data-breakdown', JSON.stringify(payload.work_quantities || []));

                    if (qtyButton._solmatPopover) {
                        qtyButton._solmatPopover.dispose();
                        qtyButton._solmatPopover = null;
                    }
                    initQtyPopovers(itemRow);
                }

                var commitmentBadge = itemRow.querySelector('.js-solmat-commitment-badge');
                if (commitmentBadge) {
                    var hasCommittedWork = (payload.work_quantities || []).some(function (row) {
                        return !!row.is_committed;
                    });
                    commitmentBadge.classList.toggle('d-none', !hasCommittedWork);
                }

                var totalBadge = breakdownForm.querySelector('.js-solmat-breakdown-total');
                if (totalBadge) {
                    totalBadge.textContent = formatQty(payload.quantity);
                }
            }

            closeEditBreakdown(breakdownRow);
        })
        .catch(function () {
            alert('Error al guardar los cambios. Intenta de nuevo.');
        })
        .finally(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        });
    });

    // Eliminar un concepto (DELETE vía AJAX).
    tbody.addEventListener('submit', function (e) {
        var form = e.target.closest('.solmat-delete-form');
        if (!form) return;
        e.preventDefault();

        if (!confirm('¿Eliminar este concepto?')) return;

        var btn = form.querySelector('button[type=submit]');
        if (btn) btn.disabled = true;
        var tr = form.closest('tr');

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form)
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(function () {
            // Desvanecer la fila y, tras la transición, quitarla junto con su
            // fila de desglose asociada.
            tr.style.cssText = 'transition:opacity .25s;opacity:0';
            setTimeout(function () {
                var nextRow = tr.nextElementSibling;
                if (nextRow && nextRow.classList.contains('solmat-breakdown-row')) {
                    nextRow.remove();
                }
                tr.remove();
                renumber();
                updateBadge();
                if (itemCount() === 0) {
                    var emptyTr = document.createElement('tr');
                    emptyTr.id = 'solmat_empty_row';
                    emptyTr.innerHTML =
                        '<td colspan="7" class="text-center text-muted py-3">Sin conceptos registrados.</td>';
                    tbody.appendChild(emptyTr);
                }
            }, 280);
        })
        .catch(function () {
            alert('Error al eliminar. Intenta de nuevo.');
            if (btn) btn.disabled = false;
        });
    });

    // Recalcular el total en vivo al editar cantidades del desglose.
    tbody.addEventListener('input', function (e) {
        if (!e.target.classList.contains('js-solmat-breakdown-qty')) return;
        updateBreakdownTotal(e.target.closest('.solmat-breakdown-form'));
    });

    // Inicialización de estado al cargar la vista.
    initQtyPopovers();
    tbody.querySelectorAll('.solmat-breakdown-form').forEach(function (form) {
        updateBreakdownTotal(form);
    });

    /* =========================================================================
     *  PANEL DE ALTA DE CONCEPTOS (solo si el panel existe → rol admin|Solmat)
     * ========================================================================= */

    var searchInput = document.getElementById('solmat_concept_search');
    if (!searchInput) return; // Usuario sin permiso de alta: nada más que hacer.

    // ── Referencias al DOM del panel ──
    var addPanel      = document.getElementById('solmat_add_panel');
    var stateSearch   = document.getElementById('solmat_state_search');
    var dropdown      = document.getElementById('solmat_concept_dropdown');
    var stateSelected = document.getElementById('solmat_state_selected');
    var previewCode   = document.getElementById('solmat_preview_code');
    var previewDesc   = document.getElementById('solmat_preview_desc');
    var previewUnit   = document.getElementById('solmat_preview_unit');
    var btnAdd        = document.getElementById('solmat_btn_add');
    var btnChange     = document.getElementById('solmat_btn_change');
    var addError      = document.getElementById('solmat_add_error');
    var workQuantitiesWrap = document.getElementById('solmat_works_quantities');
    var totalPreview  = document.getElementById('solmat_total_preview');

    // ── Estado del panel ──
    var selectedConcept = null;        // Concepto elegido en el buscador.
    var selectedWorkQuantities = {};   // { work_id: "cantidad" } capturado por el usuario.
    var selectedWorkCommitments = {};  // { work_id: boolean } marcado al agregar el concepto.
    var debounceTimer;                 // Timer del debounce del buscador.
    var currentQuery = '';             // Última búsqueda ejecutada.
    var currentPage = 1;               // Página actual de resultados.
    var hasMoreResults = false;        // ¿Hay más páginas por cargar?
    var isLoadingMore = false;         // Candado anti-doble-carga.
    var displayedResults = 0;          // Resultados mostrados (para el indicador).
    var totalResults = 0;              // Total de resultados según el servidor.

    /* ── Cambios de estado del panel ────────────────────────────────────── */

    /**
     * Estado A — Restablece el panel a la pantalla de búsqueda vacía.
     */
    function showSearch() {
        selectedConcept    = null;
        searchInput.value  = '';
        selectedWorkQuantities = {};
        selectedWorkCommitments = {};
        currentQuery = '';
        currentPage = 1;
        hasMoreResults = false;
        isLoadingMore = false;
        displayedResults = 0;
        totalResults = 0;
        dropdown.innerHTML = '';
        dropdown.classList.add('d-none');
        if (addError)  { addError.classList.add('d-none'); addError.textContent = ''; }
        stateSelected.classList.add('d-none');
        stateSearch.classList.remove('d-none');
        searchInput.focus();
    }

    /**
     * Estado B — Muestra el concepto seleccionado y sus campos de cantidad.
     * @param {{id:*, code:string, description:string, unit:string}} concept
     */
    function showSelected(concept) {
        selectedConcept         = concept;
        previewCode.textContent = concept.code;
        previewDesc.textContent = concept.description;
        previewUnit.textContent = concept.unit;
        if (addError) { addError.classList.add('d-none'); addError.textContent = ''; }
        stateSearch.classList.add('d-none');
        stateSelected.classList.remove('d-none');
        renderWorkQuantities();
    }

    /**
     * Suma las cantidades capturadas por obra y actualiza el badge "Total".
     * @returns {number} Total capturado.
     */
    function updateTotalPreview() {
        var total = Object.keys(selectedWorkQuantities).reduce(function (acc, workId) {
            var value = parseFloat(selectedWorkQuantities[workId]);
            return acc + (isNaN(value) ? 0 : value);
        }, 0);

        if (totalPreview) {
            totalPreview.textContent = 'Total: ' + formatQty(total);
        }

        return total;
    }

    /**
     * Pinta un campo de cantidad por cada obra ligada a la SOLMAT y engancha
     * el listener que sincroniza `selectedWorkQuantities` + el total.
     */
    function renderWorkQuantities() {
        if (!workQuantitiesWrap) return;

        var html = '';
        allowedWorks.forEach(function (work) {
            var value = selectedWorkQuantities[work.id] || '';
            var isCommitted = !!selectedWorkCommitments[work.id];
            html += ''
                + '<div class="input-group">'
                + '  <span class="input-group-text bg-light grow justify-content-start">' + escHtml(work.name) + '</span>'
                + '  <input type="number"'
                + '         class="form-control text-end js-solmat-work-qty"'
                + '         data-work-id="' + escHtml(work.id) + '"'
                + '         min="0" step="0.01" inputmode="decimal"'
                + '         placeholder="0.00" value="' + escHtml(value) + '">'
                + '  <span class="input-group-text bg-light">'
                + '    <span class="form-check mb-0">'
                + '      <input class="form-check-input js-solmat-work-commitment" type="checkbox"'
                + '             data-work-id="' + escHtml(work.id) + '"'
                + '             id="solmat_new_commitment_' + escHtml(work.id) + '"'
                + (isCommitted ? ' checked' : '') + '>'
                + '      <label class="form-check-label fs-12" for="solmat_new_commitment_' + escHtml(work.id) + '">Comprometido</label>'
                + '    </span>'
                + '  </span>'
                + '</div>';
        });

        workQuantitiesWrap.innerHTML = html || '<div class="text-muted fs-12">No hay obras vinculadas.</div>';

        workQuantitiesWrap.querySelectorAll('.js-solmat-work-qty').forEach(function (input) {
            input.addEventListener('input', function () {
                selectedWorkQuantities[this.getAttribute('data-work-id')] = this.value;
                updateTotalPreview();
            });
        });

        workQuantitiesWrap.querySelectorAll('.js-solmat-work-commitment').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                selectedWorkCommitments[this.getAttribute('data-work-id')] = this.checked;
            });
        });

        updateTotalPreview();
    }

    /* ── Buscador de conceptos ──────────────────────────────────────────── */

    /**
     * Crea el <li> de un resultado del buscador; al elegirlo, pasa al estado B.
     * @param {{id:*, code:string, description:string, unit:string}} c
     * @returns {HTMLLIElement}
     */
    function renderConceptOption(c) {
        var li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action py-3 px-3';
        li.style.cursor = 'pointer';
        li.innerHTML =
            '<div class="d-flex justify-content-between align-items-start gap-2">'
            + '<div class="grow overflow-hidden">'
            + '<span class="fw-bold d-block">' + escHtml(c.code) + '</span>'
            + '<span class="text-muted fs-13 d-block text-truncate">' + escHtml(c.description) + '</span>'
            + '</div>'
            + '<span class="badge bg-light text-dark border shrink-0 align-self-center">'
            + escHtml(c.unit) + '</span>'
            + '</div>';

        // pointerdown (no click) para no perder el foco antes de seleccionar.
        li.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            dropdown.classList.add('d-none');
            showSelected(c);
        });

        return li;
    }

    /**
     * Añade el botón "Cargar más resultados" al final del dropdown.
     */
    function renderLoadMoreButton() {
        var more = document.createElement('li');
        more.className = 'list-group-item text-center py-2';
        more.innerHTML =
            '<button type="button" id="solmat_btn_load_more" class="btn btn-link btn-sm text-decoration-none">'
            + '<i class="ri-arrow-down-s-line me-1"></i>Cargar más resultados'
            + '</button>';
        dropdown.appendChild(more);

        var loadBtn = document.getElementById('solmat_btn_load_more');
        if (!loadBtn) return;

        loadBtn.addEventListener('click', function () {
            if (isLoadingMore || !hasMoreResults) return;
            fetchConcepts(currentQuery, currentPage + 1, true);
        });
    }

    /**
     * Añade el indicador "Mostrando N de M resultado(s)" al dropdown.
     */
    function renderResultIndicator() {
        var indicator = document.createElement('li');
        indicator.className = 'list-group-item bg-light-subtle text-muted fs-12 py-2 px-3';
        indicator.id = 'solmat_results_indicator';
        indicator.innerHTML =
            '<i class="ri-filter-3-line me-1"></i>'
            + 'Mostrando <strong>' + displayedResults + '</strong> de <strong>' + totalResults + '</strong> resultado(s)';
        dropdown.appendChild(indicator);
    }

    /**
     * Consulta el endpoint de búsqueda de conceptos y pinta los resultados.
     * @param {string} q       Término de búsqueda.
     * @param {number} page    Página a solicitar (1-based).
     * @param {boolean} append `true` = agregar a la lista (paginación);
     *                         `false` = reemplazar (nueva búsqueda).
     */
    function fetchConcepts(q, page, append) {
        if (!q || q.length < 1) {
            dropdown.classList.add('d-none');
            dropdown.innerHTML = '';
            return;
        }

        isLoadingMore = true;

        fetch(searchUrl + '&q=' + encodeURIComponent(q) + '&page=' + encodeURIComponent(page), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
            var data = Array.isArray(payload) ? payload : (payload.data || []);
            var meta = payload.meta || null;

            if (!append) {
                dropdown.innerHTML = '';
            } else {
                // Quitar el botón "Cargar más" y el indicador previos antes de anexar.
                var prevMore = document.getElementById('solmat_btn_load_more');
                if (prevMore && prevMore.parentElement && prevMore.parentElement.parentElement) {
                    prevMore.parentElement.parentElement.remove();
                }
                var prevIndicator = document.getElementById('solmat_results_indicator');
                if (prevIndicator && prevIndicator.parentElement) {
                    prevIndicator.parentElement.removeChild(prevIndicator);
                }
            }

            if (!append && data.length === 0) {
                dropdown.innerHTML =
                    '<li class="list-group-item text-center text-muted py-3 fs-13">'
                    + '<i class="ri-search-line me-1"></i>Sin resultados para «' + escHtml(q) + '»</li>';
                dropdown.classList.remove('d-none');
                currentPage = 1;
                hasMoreResults = false;
                displayedResults = 0;
                totalResults = 0;
                return;
            }

            data.forEach(function (c) {
                dropdown.appendChild(renderConceptOption(c));
            });

            currentPage = page;
            hasMoreResults = meta ? !!meta.has_more : false;
            displayedResults = append ? (displayedResults + data.length) : data.length;
            totalResults = meta && typeof meta.total === 'number' ? meta.total : displayedResults;

            renderResultIndicator();

            if (hasMoreResults) {
                renderLoadMoreButton();
            }

            dropdown.classList.remove('d-none');
        })
        .catch(function () {
            if (!append) {
                dropdown.innerHTML =
                    '<li class="list-group-item text-danger py-2 px-3 fs-13">'
                    + '<i class="ri-error-warning-line me-1"></i>Error al buscar. Intenta de nuevo.</li>';
                dropdown.classList.remove('d-none');
            }
        })
        .finally(function () {
            isLoadingMore = false;
        });
    }

    /* ── Alta del concepto (POST vía AJAX) ──────────────────────────────── */

    /**
     * Valida las cantidades capturadas y envía el alta del concepto. En éxito,
     * inserta la fila con `appendRow` y vuelve al estado de búsqueda.
     */
    function doAdd() {
        if (!selectedConcept) return;

        // Solo obras con cantidad válida (> 0).
        var workRows = Object.keys(selectedWorkQuantities).map(function (workId) {
            return {
                work_id: workId,
                quantity: selectedWorkQuantities[workId],
                is_committed: !!selectedWorkCommitments[workId]
            };
        }).filter(function (row) {
            return row.quantity !== '' && !isNaN(parseFloat(row.quantity)) && parseFloat(row.quantity) > 0;
        });

        if (workRows.length === 0) {
            addError.textContent = 'Ingresa al menos una cantidad válida por obra.';
            addError.classList.remove('d-none');
            return;
        }

        if (updateTotalPreview() <= 0) {
            addError.textContent = 'La cantidad total debe ser mayor a 0.';
            addError.classList.remove('d-none');
            return;
        }
        addError.classList.add('d-none');

        btnAdd.disabled = true;
        btnAdd.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Agregando…';

        var fd = new FormData();
        fd.append('_token',      csrfToken);
        fd.append('concept_id',  selectedConcept.id);
        fd.append('code',        selectedConcept.code);
        fd.append('description', selectedConcept.description);
        fd.append('unit',        selectedConcept.unit);
        workRows.forEach(function (row, index) {
            fd.append('work_quantities[' + index + '][work_id]', row.work_id);
            fd.append('work_quantities[' + index + '][quantity]', row.quantity);
            fd.append('work_quantities[' + index + '][is_committed]', row.is_committed ? '1' : '0');
        });

        var specFileInput = document.getElementById('solmat_spec_file');
        if (specFileInput && specFileInput.files.length > 0) {
            fd.append('spec_file', specFileInput.files[0]);
        }

        fetch(storeUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(function (item) {
            appendRow(item);
            updateBadge();
            showSearch();
        })
        .catch(function () {
            addError.textContent = 'Error al agregar el concepto. Intenta de nuevo.';
            addError.classList.remove('d-none');
        })
        .finally(function () {
            btnAdd.disabled = false;
            btnAdd.innerHTML = '<i class="ri-add-line me-1"></i>Agregar a la solicitud';
        });
    }

    /* ── Enganche de eventos del panel ──────────────────────────────────── */

    // Búsqueda con debounce (300 ms).
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var q = this.value.trim();
        if (q.length < 1) {
            currentQuery = '';
            currentPage = 1;
            hasMoreResults = false;
            displayedResults = 0;
            totalResults = 0;
            dropdown.classList.add('d-none');
            dropdown.innerHTML = '';
            return;
        }
        debounceTimer = setTimeout(function () {
            currentQuery = q;
            currentPage = 1;
            hasMoreResults = false;
            displayedResults = 0;
            totalResults = 0;
            fetchConcepts(q, 1, false);
        }, 300);
    });

    // Cerrar el dropdown al tocar fuera del panel.
    document.addEventListener('pointerdown', function (e) {
        if (addPanel && !addPanel.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });

    // "Cambiar" concepto → volver a la búsqueda.
    btnChange.addEventListener('click', showSearch);

    // "Agregar a la solicitud".
    btnAdd.addEventListener('click', doAdd);

}());
