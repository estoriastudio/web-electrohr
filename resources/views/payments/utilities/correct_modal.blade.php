{{--
    Corrección de admin sobre un pago pagado: cambiar el importe o el comprobante, regresarlo a autorizado
    o eliminarlo. Cada acción muestra su vista previa y, si deja sin cubrir un hito con hitos siguientes ya
    pagados, pide confirmar la advertencia.

    Variables: $payment, $currency, $returnTo (opcional), $fromDetail (bool).
--}}
@php
    $paidValue = number_format($payment->paid_amount_value, 2, '.', '');
    $previewUrl = route('payments.preview', $payment);
@endphp
<div class="modal fade" id="modalCorrectPayment{{ $payment->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-edit-box-line me-1"></i> Corregir pago {{ $payment->folio }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted fs-13">
                    Pagado: <strong>{{ $currency }} {{ number_format($payment->paid_amount_value, 2) }}</strong>
                    (aplicado al hito {{ $currency }} {{ number_format($payment->applied_amount_value, 2) }}).
                    Solo admin corrige un pago ya registrado con SPEI.
                </p>

                {{-- Importe y comprobante --}}
                <form action="{{ route('payments.update', $payment) }}" method="POST" enctype="multipart/form-data" class="js-amount-preview-form border rounded p-3 mb-3">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="pagado">
                    @if (!empty($returnTo))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                    <h6 class="fs-14">Cambiar importe o comprobante</h6>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label fs-12 mb-1">Importe pagado</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ $currency }}</span>
                                <input type="number" name="paid_amount" class="form-control js-preview-amount" step="0.01" min="0.01" value="{{ $paidValue }}" required>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fs-12 mb-1">Reemplazar comprobante (opcional)</label>
                            <input type="file" name="spei_receipt_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                    </div>
                    <div class="js-preview-box mt-2" data-url="{{ $previewUrl }}" data-accion="pagar" data-correccion="importe" data-lazy="1"></div>
                    <div class="form-check mt-2 d-none js-preview-confirm">
                        <input class="form-check-input" type="checkbox" id="confirmCorrectionAmount{{ $payment->id }}" name="confirm_correction" value="1">
                        <label class="form-check-label fs-13" for="confirmCorrectionAmount{{ $payment->id }}">Confirmo la corrección y la advertencia.</label>
                    </div>
                    <div class="text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-primary js-preview-submit" disabled>Guardar corrección</button>
                    </div>
                </form>

                {{-- Regresar a autorizado --}}
                <form action="{{ route('payments.update', $payment) }}" method="POST" class="js-amount-preview-form border rounded p-3 mb-3"
                      onsubmit="return confirm('¿Regresar este pago a «Autorizado»? Se revierten sus efectos.')">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="autorizado">
                    @if (!empty($returnTo))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                    <h6 class="fs-14">Regresar a autorizado</h6>
                    <div class="js-preview-box" data-url="{{ $previewUrl }}" data-accion="pagar" data-correccion="autorizado" data-amount="{{ $paidValue }}" data-lazy="1"></div>
                    <div class="form-check mt-2 d-none js-preview-confirm">
                        <input class="form-check-input" type="checkbox" id="confirmCorrectionRevert{{ $payment->id }}" name="confirm_correction" value="1">
                        <label class="form-check-label fs-13" for="confirmCorrectionRevert{{ $payment->id }}">Confirmo la corrección y la advertencia.</label>
                    </div>
                    <div class="text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-soft-warning js-preview-submit" disabled>
                            <i class="ri-arrow-go-back-line me-1"></i>Regresar a autorizado
                        </button>
                    </div>
                </form>

                {{-- Eliminar --}}
                <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="js-amount-preview-form border border-danger-subtle rounded p-3"
                      onsubmit="return confirm('¿Eliminar este pago ya registrado con SPEI?')">
                    @csrf @method('DELETE')
                    <h6 class="fs-14 text-danger">Eliminar pago</h6>
                    <div class="js-preview-box" data-url="{{ $previewUrl }}" data-accion="pagar" data-correccion="eliminar" data-amount="{{ $paidValue }}" data-lazy="1"></div>
                    <div class="form-check mt-2 d-none js-preview-confirm">
                        <input class="form-check-input" type="checkbox" id="confirmCorrectionDelete{{ $payment->id }}" name="confirm_correction" value="1">
                        <label class="form-check-label fs-13" for="confirmCorrectionDelete{{ $payment->id }}">Confirmo la corrección y la advertencia.</label>
                    </div>
                    <div class="text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-soft-danger js-preview-submit" disabled>
                            <i class="ri-delete-bin-line me-1"></i>Eliminar pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
