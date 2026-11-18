{{--
    Modal "Registrar pagado" de un PAY autorizado: comprobante SPEI + importe del SPEI (precargado con lo
    autorizado). La vista previa avisa si el pago de más abona a hitos siguientes o pasa a saldo a favor.

    Variables: $payment, $currency, $returnTo (opcional), $fromDetail (bool).
--}}
<div class="modal fade" id="modalPayPayment{{ $payment->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('payments.update', $payment) }}" method="POST" enctype="multipart/form-data" class="js-amount-preview-form">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="pagado">
                @if (!empty($fromDetail))
                    <input type="hidden" name="origin" value="purchase_order_detail">
                @endif
                @if (!empty($returnTo))
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                @endif
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-bank-card-line me-1"></i> Registrar pagado — {{ $payment->folio }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13">
                        Autorizado: <strong>{{ $currency }} {{ number_format($payment->amount, 2) }}</strong>.
                        @if ($payment->origin === \App\Models\Payment::ORIGIN_CREDIT)
                            <span class="badge bg-info-subtle text-info fs-10">Abono de {{ $payment->sourcePayment?->folio ?? 'un PAY eliminado' }}</span>
                        @endif
                    </p>
                    <div class="mb-3">
                        <label for="paidAmount{{ $payment->id }}" class="form-label fw-medium">Importe del SPEI <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">{{ $currency }}</span>
                            <input type="number" id="paidAmount{{ $payment->id }}" name="paid_amount"
                                   class="form-control js-preview-amount @error('paid_amount') is-invalid @enderror"
                                   step="0.01" min="0.01" value="{{ old('paid_amount', number_format($payment->amount, 2, '.', '')) }}" required>
                        </div>
                        @error('paid_amount')<div class="text-danger fs-12 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">
                            Archivo comprobante SPEI <span class="text-danger">*</span>
                            <small class="text-muted fw-normal">(PDF, JPG, PNG, WEBP - máx. 10MB)</small>
                        </label>
                        <input type="file" name="spei_receipt_file"
                               class="form-control @error('spei_receipt_file') is-invalid @enderror"
                               accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        @error('spei_receipt_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="js-preview-box"
                         data-url="{{ route('payments.preview', $payment) }}"
                         data-accion="pagar"
                         @if (!empty($fromDetail)) data-origin="purchase_order_detail" @endif></div>
                    <div class="form-check mt-2 d-none js-preview-confirm">
                        <input class="form-check-input" type="checkbox" id="confirmExcess{{ $payment->id }}" name="confirm_excess" value="1">
                        <label class="form-check-label fs-13" for="confirmExcess{{ $payment->id }}">
                            Confirmo el pago de más y lo que hará el sistema con el excedente.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success js-preview-submit" disabled>
                        <i class="ri-check-double-line me-1"></i> Registrar pagado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
