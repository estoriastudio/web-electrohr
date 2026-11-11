@php
    $docRecord = $project->documents->firstWhere('document_type', $dt);
    $hasFile   = $docRecord && $docRecord->file_path;
    $isNa      = $docRecord && $docRecord->not_applicable;
@endphp
<tr class="collapse doc-group-{{ $catKey }}" id="docRow_{{ $dt }}">
    <td class="ps-4">
        @if ($isNa)
            <span class="rounded-circle d-inline-block"
                  style="width: 12px; height: 12px; border: 1px solid #adb5bd; background: transparent;"
                  title="{{ $dtLabel }}: No aplica">
            </span>
        @else
            <span class="rounded-circle d-inline-block"
                  style="width: 12px; height: 12px; background: {{ $hasFile ? '#28a745' : '#adb5bd' }};"
                  title="{{ $dtLabel }}: {{ $hasFile ? 'Subido' : 'Pendiente' }}">
            </span>
        @endif
    </td>
    <td class="fw-medium fs-14">{{ $dtLabel }}</td>
    <td class="text-muted fs-12">
        {{ ! $isNa && $docRecord && $docRecord->uploaded_at ? $docRecord->uploaded_at->format('d/m/Y') : '—' }}
    </td>
    <td>
        @if ($isNa)
            <span class="badge bg-secondary-subtle text-secondary fw-normal">No aplica</span>
        @elseif ($hasFile)
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($docRecord->file_path) }}"
               target="_blank" class="btn btn-light btn-sm" title="Ver documento">
                <i class="ri-file-download-line"></i>
            </a>
        @else
            <span class="text-muted fs-12">Sin archivo</span>
        @endif
    </td>
    @can('projects.update')
    <td>
        <div class="d-flex gap-1 flex-wrap">
            @if ($isNa)
                <form action="{{ route('projects.document.applicability', [$project, $dt]) }}" method="POST"
                      class="js-applicability-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="not_applicable" value="0">
                    <button type="submit" class="btn btn-light btn-sm">
                        <i class="ri-arrow-go-back-line me-1"></i> Marcar como aplica
                    </button>
                </form>
            @else
                <button type="button"
                        class="btn btn-soft-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#modalProjectDoc_{{ $dt }}">
                    <i class="ri-upload-2-line me-1"></i>
                    {{ $hasFile ? 'Reemplazar' : 'Subir' }}
                </button>
                @unless ($hasFile)
                    <form action="{{ route('projects.document.applicability', [$project, $dt]) }}" method="POST"
                          class="js-applicability-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="not_applicable" value="1">
                        <button type="submit" class="btn btn-light btn-sm">No aplica</button>
                    </form>
                @endunless
            @endif
        </div>
    </td>
    @endcan
</tr>
