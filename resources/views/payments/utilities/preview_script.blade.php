{{-- Vista previa de montos (payments.preview) para los modales de autorizar, pagar y corregir. Se incluye una sola vez por página. --}}
@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-amount-preview-form').forEach(function (form) {
        var box = form.querySelector('.js-preview-box');
        var input = form.querySelector('.js-preview-amount');
        var submit = form.querySelector('.js-preview-submit');
        var confirmWrapper = form.querySelector('.js-preview-confirm');
        var confirmInput = confirmWrapper ? confirmWrapper.querySelector('input[type=checkbox]') : null;
        var modal = form.closest('.modal');
        var timer = null;
        var sequence = 0;

        if (!box || !submit) {
            return;
        }

        function setState(enabled, needsConfirm) {
            submit.disabled = !enabled;
            if (confirmWrapper) {
                confirmWrapper.classList.toggle('d-none', !needsConfirm);
                confirmInput.required = needsConfirm;
                if (!needsConfirm) {
                    confirmInput.checked = false;
                }
            }
        }

        function addAlert(type, lines) {
            var alert = document.createElement('div');
            alert.className = 'alert alert-' + type + ' fs-12 py-2 mb-2';
            if (lines.length === 1) {
                alert.textContent = lines[0];
            } else {
                var list = document.createElement('ul');
                list.className = 'mb-0 ps-3';
                lines.forEach(function (line) {
                    var item = document.createElement('li');
                    item.textContent = line;
                    list.appendChild(item);
                });
                alert.appendChild(list);
            }
            box.appendChild(alert);
        }

        function render(data) {
            box.innerHTML = '';

            if (!data.ok) {
                addAlert('danger', [data.error || 'No se pudo calcular la vista previa.']);
                setState(false, false);
                return;
            }

            var movements = data.movements || [];
            var warnings = data.warnings || [];
            var hasExcess = box.dataset.accion === 'pagar' && !box.dataset.correccion
                && movements.some(function (text) { return /Abona|Saldo a favor/.test(text); });

            if (movements.length) {
                var intro = document.createElement('div');
                intro.className = 'fs-12 fw-semibold mb-1';
                intro.textContent = 'Al confirmar, el sistema hará:';
                box.appendChild(intro);
                addAlert(hasExcess ? 'warning' : 'info', movements);
            }
            if (warnings.length) {
                addAlert('warning', warnings);
            }

            setState(true, warnings.length > 0 || hasExcess);
        }

        function load() {
            var amount = input ? input.value : box.dataset.amount;

            if (!amount || parseFloat(amount) <= 0) {
                box.innerHTML = '';
                setState(false, false);
                return;
            }

            var params = new URLSearchParams({ accion: box.dataset.accion, monto: amount });
            if (box.dataset.correccion) { params.set('correccion', box.dataset.correccion); }
            if (box.dataset.origin) { params.set('origin', box.dataset.origin); }

            var current = ++sequence;
            box.innerHTML = '<div class="text-muted fs-12">Calculando…</div>';
            setState(false, false);

            fetch(box.dataset.url + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (response) { return response.json(); })
                .then(function (data) { if (current === sequence) { render(data); } })
                .catch(function () {
                    if (current === sequence) {
                        render({ ok: false, error: 'No se pudo calcular la vista previa. Intenta de nuevo.' });
                    }
                });
        }

        if (input) {
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(load, 350);
            });
        }

        if (modal) {
            modal.addEventListener('shown.bs.modal', load);
        } else {
            load();
        }

        if (form.dataset.confirm) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        }
    });
});
</script>
@endpush
@endonce
