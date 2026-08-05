// Popover "Desglose por obra" en columna Solicitada
(function () {
	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(String(str)));
		return div.innerHTML;
	}

	function buildBreakdownContent(breakdown) {
		if (!Array.isArray(breakdown) || breakdown.length === 0) {
			return '<span class="text-muted fs-13">Sin desglose por obra.</span>';
		}

		var rows = breakdown.map(function (row) {
			var name = escHtml(row.work_name || row.work_id || 'Obra');
			var nameAttr = name.replace(/"/g, '&quot;');
			var qty = escHtml(String(row.quantity || '0.00'));

			return '<tr>'
				+ '<td><div class="text-truncate solcom-breakdown-name" title="' + nameAttr + '">' + name + '</div></td>'
				+ '<td class="text-end fw-semibold ps-3">' + qty + '</td>'
				+ '</tr>';
		}).join('');

		return '<table class="table table-sm table-borderless align-middle mb-0 solcom-breakdown-table">'
			+ '<thead><tr class="text-muted"><th class="fw-semibold">Obra</th><th class="fw-semibold text-end">Cant.</th></tr></thead>'
			+ '<tbody>' + rows + '</tbody>'
			+ '</table>';
	}

	function initQtyPopovers(scope) {
		if (!window.bootstrap || !window.bootstrap.Popover) return;

		var root = scope || document;
		root.querySelectorAll('.js-solcom-qty-popover').forEach(function (trigger) {
			if (trigger._solcomPopover) return;

			var breakdown = [];
			try {
				breakdown = JSON.parse(trigger.getAttribute('data-breakdown') || '[]');
			} catch (error) {
				breakdown = [];
			}

			trigger._solcomPopover = new bootstrap.Popover(trigger, {
				trigger: 'focus',
				placement: 'top',
				html: true,
				sanitize: false,
				content: buildBreakdownContent(breakdown),
				customClass: 'solcom-breakdown-popover'
			});
		});
	}

	initQtyPopovers(document);
}());

// Contador de caracteres en modal solicitar cambios
(function () {
	var textarea = document.querySelector('#modalRequestChanges textarea[name="change_text"]');
	var counter = document.getElementById('changeTextCount');
	if (textarea && counter) {
		textarea.addEventListener('input', function () {
			counter.textContent = this.value.length;
		});
	}
}());

(function () {
	function initTooltips(scope) {
		if (!window.bootstrap || !window.bootstrap.Tooltip) return;

		var elements = (scope || document).querySelectorAll('[data-bs-toggle="tooltip"]');
		elements.forEach(function (element) {
			if (window.bootstrap.Tooltip.getInstance(element)) return;
			new window.bootstrap.Tooltip(element);
		});
	}

	initTooltips(document);

	var csrfMeta = document.querySelector('meta[name="csrf-token"]');
	var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
	var tbody = document.getElementById('solcom_items_tbody');
	var badge = document.getElementById('solcom_items_count');

	if (!tbody) return;

	var storeUrl = tbody.dataset.storeUrl || '';
	var destroyUrlTemplate = tbody.dataset.destroyUrlTemplate || '';
	var conceptsSearchUrl = tbody.dataset.conceptsSearchUrl || '';

	// Helpers
	function escHtml(str) {
		var d = document.createElement('div');
		d.appendChild(document.createTextNode(String(str)));
		return d.innerHTML;
	}

	function itemCount() {
		return tbody.querySelectorAll('tr.solcom-item-row').length;
	}

	function parseQty(value) {
		var n = parseFloat(value);
		return isNaN(n) ? 0 : n;
	}

	function toTwoDecimals(value) {
		return parseQty(value).toFixed(2);
	}

	function updateBadge() {
		if (badge) badge.textContent = itemCount() + ' ítem(s)';
	}

	function renumber() {
		tbody.querySelectorAll('tr.solcom-item-row').forEach(function (row, i) {
			var td = row.querySelector('.solcom-row-num');
			if (td) td.textContent = i + 1;
		});
	}

	// Debounce para botones +/-
	var qtyTimers = {};
	function saveQtyDebounced(input) {
		var id = input.dataset.itemId;
		clearTimeout(qtyTimers[id]);
		qtyTimers[id] = setTimeout(function () { saveQty(input); }, 500);
	}

	// Botones +/-
	tbody.addEventListener('click', function (e) {
		var btn = e.target.closest('.solcom-qty-minus, .solcom-qty-plus');
		if (!btn) return;
		var input = btn.closest('.input-group').querySelector('.solcom-qty-input');
		if (!input || input.disabled) return;
		var val = parseQty(input.value);
		if (btn.classList.contains('solcom-qty-minus')) val = Math.max(0, val - 1);
		else val += 1;
		input.value = toTwoDecimals(val);
		saveQtyDebounced(input);
	});

	// Guardar cantidad a comprar (inline, auto-save)
	// blur usa capture porque blur no burbujea
	tbody.addEventListener('blur', function (e) {
		var input = e.target.closest('.solcom-qty-input');
		if (!input) return;
		saveQty(input);
	}, true);

	tbody.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter') return;
		var input = e.target.closest('.solcom-qty-input');
		if (!input) return;
		e.preventDefault();
		input.blur();
	});

	function saveQty(input) {
		var val = parseFloat(input.value);
		var original = parseFloat(input.dataset.original);

		if (isNaN(val) || val < 0) {
			input.value = isNaN(original) ? '' : toTwoDecimals(original);
			return;
		}
		val = parseFloat(toTwoDecimals(val));
		if (val === parseFloat(toTwoDecimals(original))) return;

		var updateUrl = storeUrl + '/' + input.dataset.itemId;

		input.disabled = true;
		input.classList.remove('is-saved', 'is-error');

		var fd = new FormData();
		fd.append('_token', csrfToken);
		fd.append('_method', 'PATCH');
		fd.append('purchase_quantity', val);

		fetch(updateUrl, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
			body: fd
		})
		.then(function (r) {
			if (!r.ok) throw new Error(r.status);
			return r.json();
		})
		.then(function (data) {
			var saved = parseQty(data.purchase_quantity);
			input.dataset.original = toTwoDecimals(saved);
			input.value = toTwoDecimals(saved);
			input.disabled = false;
			input.classList.add('is-saved');
			setTimeout(function () { input.classList.remove('is-saved'); }, 1200);
			Toastify({
				text: 'Cantidad guardada',
				duration: 2000,
				gravity: 'bottom',
				position: 'right',
				className: 'bg-success',
				stopOnFocus: false,
			}).showToast();
		})
		.catch(function () {
			input.value = toTwoDecimals(original);
			input.disabled = false;
			input.classList.add('is-error');
			setTimeout(function () { input.classList.remove('is-error'); }, 2000);
			Toastify({
				text: 'Error al guardar. Intenta de nuevo.',
				duration: 3000,
				gravity: 'bottom',
				position: 'right',
				className: 'bg-danger',
				stopOnFocus: false,
			}).showToast();
		});
	}

	// AJAX Delete
	tbody.addEventListener('submit', function (e) {
		var form = e.target.closest('.solcom-delete-form');
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
			tr.style.cssText = 'transition:opacity .25s;opacity:0';
			setTimeout(function () {
				tr.remove();
				renumber();
				updateBadge();
				if (itemCount() === 0) {
					var emptyTr = document.createElement('tr');
					emptyTr.id = 'solcom_empty_row';
					emptyTr.innerHTML =
						'<td colspan="8" class="text-center text-muted py-4">'
						+ '<i class="ri-inbox-line fs-4 d-block mb-1 opacity-50"></i>'
						+ 'Sin conceptos registrados.</td>';
					tbody.appendChild(emptyTr);
				}
			}, 280);
		})
		.catch(function () {
			alert('Error al eliminar. Intenta de nuevo.');
			if (btn) btn.disabled = false;
		});
	});

	// Panel Agregar Concepto
	var stateSearch = document.getElementById('solcom_state_search');
	var stateSelected = document.getElementById('solcom_state_selected');
	var addPanel = document.getElementById('solcom_add_panel');
	var btnToggle = document.getElementById('solcom_btn_toggle_add_concept');

	if (!stateSearch) return;

	var searchInput = document.getElementById('solcom_search_input');
	var dropdown = document.getElementById('solcom_search_dropdown');
	var purQtyInput = document.getElementById('solcom_pur_qty');
	var specFileInput = document.getElementById('solcom_spec_file');
	var btnAdd = document.getElementById('solcom_btn_add');
	var btnChange = document.getElementById('solcom_btn_change');
	var previewCode = document.getElementById('solcom_preview_code');
	var previewDesc = document.getElementById('solcom_preview_desc');
	var previewUnit = document.getElementById('solcom_preview_unit');
	var addError = document.getElementById('solcom_add_error');

	if (btnToggle && addPanel) {
		btnToggle.addEventListener('click', function () {
			addPanel.style.display = addPanel.style.display === 'none' ? '' : 'none';
		});
	}

	var currentConcept = null;
	var debounceTimer;

	function showSearch() {
		stateSelected.classList.add('d-none');
		stateSearch.classList.remove('d-none');
		searchInput.value = '';
		purQtyInput.value = '';
		if (specFileInput) specFileInput.value = '';
		currentConcept = null;
		dropdown.innerHTML = '';
		dropdown.classList.add('d-none');
	}

	function showSelected(concept) {
		currentConcept = concept;
		previewCode.textContent = concept.code;
		previewDesc.textContent = concept.description;
		previewUnit.textContent = concept.unit;
		purQtyInput.value = '';
		addError.classList.add('d-none');
		stateSearch.classList.add('d-none');
		stateSelected.classList.remove('d-none');
		purQtyInput.focus();
	}

	// Búsqueda con debounce
	searchInput.addEventListener('input', function () {
		clearTimeout(debounceTimer);
		var q = this.value.trim();
		if (q.length < 2) {
			dropdown.classList.add('d-none');
			dropdown.innerHTML = '';
			return;
		}

		debounceTimer = setTimeout(function () {
			fetch(conceptsSearchUrl + '?type=materiales&q=' + encodeURIComponent(q), {
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				dropdown.innerHTML = '';
				if (!data.length) {
					dropdown.innerHTML =
						'<li class="list-group-item list-group-item-light text-muted fs-13 py-2 px-3">Sin resultados</li>';
				} else {
					data.forEach(function (concept) {
						var li = document.createElement('li');
						li.className = 'list-group-item list-group-item-action py-2 px-3 fs-13';
						li.style.cursor = 'pointer';
						li.innerHTML =
							'<span class="fw-semibold">' + escHtml(concept.code) + '</span>'
							+ ' <span class="text-muted">- ' + escHtml(concept.description) + '</span>'
							+ ' <span class="badge bg-light text-dark border ms-1">' + escHtml(concept.unit) + '</span>';
						li.addEventListener('pointerdown', function (e) {
							e.preventDefault();
							showSelected(concept);
						});
						dropdown.appendChild(li);
					});
				}
				dropdown.classList.remove('d-none');
			});
		}, 300);
	});

	// Cerrar dropdown al tocar fuera
	document.addEventListener('pointerdown', function (e) {
		if (stateSearch && !stateSearch.contains(e.target)) {
			dropdown.classList.add('d-none');
		}
	});

	btnChange.addEventListener('click', showSearch);

	// Confirmar con Enter desde "A Comprar"
	purQtyInput.addEventListener('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			doAdd();
		}
	});

	btnAdd.addEventListener('click', doAdd);

	function doAdd() {
		if (!currentConcept) return;

		var pur = parseFloat(purQtyInput.value);

		addError.classList.add('d-none');
		if (isNaN(pur) || pur < 0) {
			purQtyInput.classList.add('is-invalid');
			setTimeout(function () { purQtyInput.classList.remove('is-invalid'); }, 1500);
			purQtyInput.focus();
			return;
		}

		btnAdd.disabled = true;

		var fd = new FormData();
		fd.append('_token', csrfToken);
		fd.append('concept_id', currentConcept.id);
		fd.append('code', currentConcept.code);
		fd.append('description', currentConcept.description);
		fd.append('unit', currentConcept.unit);
		fd.append('purchase_quantity', toTwoDecimals(pur));
		if (specFileInput && specFileInput.files && specFileInput.files.length > 0) {
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
			addError.textContent = 'Error al agregar. Intenta de nuevo.';
			addError.classList.remove('d-none');
		})
		.finally(function () {
			btnAdd.disabled = false;
		});
	}

	function appendRow(item) {
		// Quitar fila vacía si existe
		var emptyRow = document.getElementById('solcom_empty_row');
		if (emptyRow) emptyRow.remove();

		var num = itemCount() + 1;
		var destroyUrl = destroyUrlTemplate.replace('__ID__', item.id);

		var tr = document.createElement('tr');
		tr.className = 'solcom-item-row';
		tr.innerHTML =
			'<td class="solcom-row-num text-muted fs-12">' + num + '</td>'
			+ '<td><span class="fw-semibold">' + escHtml(item.code) + '</span></td>'
			+ '<td>' + escHtml(item.description) + '</td>'
			+ '<td class="text-center">'
			+   (item.file_url
				? '<a href="' + escHtml(item.file_url) + '" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Documento de Especificaciones Técnicas" aria-label="Documento de Especificaciones Técnicas"><i class="ri-file-3-line"></i><span>E.T.</span></a>'
				: '<span class="badge bg-light text-muted border d-inline-flex align-items-center gap-1 opacity-75" data-bs-toggle="tooltip" data-bs-placement="top" title="Documento de Especificaciones Técnicas"><i class="ri-file-3-line"></i><span>Sin E.T.</span></span>')
			+ '</td>'
			+ '<td>' + escHtml(item.unit) + '</td>'
			+ '<td class="text-end text-muted fs-13">' + toTwoDecimals(item.requested_quantity) + '</td>'
			+ '<td style="min-width:170px">'
			+   '<div class="input-group">'
			+     '<button type="button" class="btn btn-light border solcom-qty-minus px-3" title="Restar"><i class="ri-subtract-line"></i></button>'
			+     '<input type="number" class="form-control text-center fw-bold fs-15 solcom-qty-input"'
			+     ' value="' + toTwoDecimals(item.purchase_quantity) + '"'
			+     ' data-item-id="' + item.id + '"'
			+     ' data-original="' + toTwoDecimals(item.purchase_quantity) + '"'
			+     ' step="0.01" min="0" inputmode="numeric">'
			+     '<button type="button" class="btn btn-light border solcom-qty-plus px-3" title="Sumar"><i class="ri-add-line"></i></button>'
			+   '</div>'
			+ '</td>'
			+ '<td>'
			+   '<form action="' + escHtml(destroyUrl) + '" method="POST" class="solcom-delete-form">'
			+     '<input type="hidden" name="_token" value="' + escHtml(csrfToken) + '">'
			+     '<input type="hidden" name="_method" value="DELETE">'
			+     '<button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">'
			+       '<i class="ri-delete-bin-line"></i>'
			+     '</button>'
			+   '</form>'
			+ '</td>';

		// Flash verde al agregar
		tr.style.background = 'var(--bs-primary-bg-subtle)';
		setTimeout(function () {
			tr.style.transition = 'background 1s';
			tr.style.background = '';
		}, 50);

		tbody.appendChild(tr);
		initTooltips(tr);
	}
}());
