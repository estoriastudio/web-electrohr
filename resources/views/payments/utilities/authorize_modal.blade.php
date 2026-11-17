{{--
    Modal "Autorizar con monto" (admin). El monto viene precargado con el 100 % del PAY y, antes de confirmar,
    la vista previa del servidor lista lo que hará el sistema.

    Variables: $payment, $currency, $returnTo (opcional), $fromDetail (bool), $checklistWarning (opcional).
--}}
<div class="modal fade" id="modalAuthorizePayment{{ $payment->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('payments.update', $payment) }}" method="POST" class="js-amount-preview-form"
                  @if (!empty($checklistWarning)) data-confirm="{{ $checklistWarning }}" @endif>
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="autorizado">
                @if (!empty($fromDetail))
                    <input type="hidden" name="origin" value="purchase_order_detail">
                @endif
                @if (!empty($returnTo))
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                @endif
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-check-line me-1"></i> Autorizar pago {{ $payment->folio }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13">
                        Monto del PAY: <strong>{{ $currency }} {{ number_format($payment->amount, 2) }}</strong>.
                        Decide cuánto se autoriza ahora; el sistema ajusta el resto y los hitos siguientes.
                    </p>
                    <label for="authorizedAmount{{ $payment->id }}" class="form-label fw-medium">Monto a autorizar <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $currency }}</span>
                        <input type="number" id="authorizedAmount{{ $payment->id }}" name="authorized_amount"
                               class="form-control js-preview-amount @error('authorized_amount') is-invalid @enderror"
                               step="0.01" min="0.01" value="{{ old('authorized_amount', number_format($payment->amount, 2, '.', '')) }}" required>
                    </div>
                    @error('authorized_amount')<div class="text-danger fs-12 mt-1">{{ $message }}</div>@enderror
                    <div class="js-preview-box mt-3"
                         data-url="{{ route('payments.preview', $payment) }}"
                         data-accion="autorizar"
                         @if (!empty($fromDetail)) data-origin="purchase_order_detail" @endif></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info js-preview-submit" disabled>
                        <i class="ri-check-line me-1"></i> Autorizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
