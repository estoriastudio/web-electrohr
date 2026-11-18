<?php

namespace App\Http\Controllers;

use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateInvoice;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectEstimateInvoiceController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function store(Request $request, ProjectEstimate $estimate): RedirectResponse
    {
        $validated = $request->validateWithBag('estimateInvoices', [
            'invoices' => ['required', 'array', 'min:1'],
            'invoices.*.pdf_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'invoices.*.xml_file' => ['required', 'file', 'max:10240', $this->xmlExtensionRule()],
            'invoices.*.folio' => ['nullable', 'string', 'max:100'],
            'invoices.*.invoice_date' => ['required', 'date'],
            'invoices.*.payment_date' => ['required', 'date'],
        ], [
            'invoices.required' => 'No se recibieron facturas. Verifica que el tamaño total no exceda el límite del servidor.',
            'invoices.*.pdf_file.required' => 'Cada factura requiere su archivo PDF.',
            'invoices.*.pdf_file.mimes' => 'El archivo PDF de la factura no tiene un formato válido.',
            'invoices.*.xml_file.required' => 'Cada factura requiere su archivo XML.',
            'invoices.*.invoice_date.required' => 'La fecha de la factura es obligatoria.',
            'invoices.*.payment_date.required' => 'La fecha de pago es obligatoria.',
            'invoices.*.pdf_file.max' => 'Cada archivo debe pesar máximo 10 MB.',
            'invoices.*.xml_file.max' => 'Cada archivo debe pesar máximo 10 MB.',
        ]);

        $folios = [];
        $errors = [];

        foreach ($validated['invoices'] as $index => $data) {
            $uuid = $this->extractFiscalFolio($data['xml_file']);

            if (! $uuid) {
                $errors["invoices.{$index}.xml_file"] = 'No fue posible leer el folio fiscal (UUID) del XML. Verifica que sea un CFDI timbrado válido.';

                continue;
            }

            $inputFolio = strtoupper(trim((string) ($data['folio'] ?? '')));
            if ($inputFolio !== '' && $inputFolio !== $uuid) {
                $errors["invoices.{$index}.folio"] = 'El folio capturado no coincide con el folio fiscal (UUID) del XML.';

                continue;
            }

            if (in_array($uuid, $folios, true) || $estimate->invoices()->where('folio', $uuid)->exists()) {
                $errors["invoices.{$index}.xml_file"] = "La factura {$uuid} ya está registrada en esta estimación.";

                continue;
            }

            $folios[$index] = $uuid;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors)->errorBag('estimateInvoices');
        }

        $uploadedPaths = [];

        try {
            DB::transaction(function () use ($validated, $estimate, $folios, &$uploadedPaths) {
                $directory = "projects/{$estimate->project_id}/estimates/{$estimate->id}";

                foreach ($validated['invoices'] as $index => $data) {
                    $pdfPath = Storage::disk('s3')->putFileAs($directory, $data['pdf_file'], Str::uuid().'.pdf');
                    $xmlPath = Storage::disk('s3')->putFileAs($directory, $data['xml_file'], Str::uuid().'.xml');

                    foreach ([$pdfPath, $xmlPath] as $path) {
                        if ($path) {
                            $uploadedPaths[] = $path;
                        }
                    }

                    if (! $pdfPath || ! $xmlPath) {
                        throw new \RuntimeException('No se pudo guardar la factura en S3.');
                    }

                    $estimate->invoices()->create([
                        'folio' => $folios[$index],
                        'invoice_date' => $data['invoice_date'],
                        'payment_date' => $data['payment_date'],
                        'pdf_path' => $pdfPath,
                        'pdf_original_name' => Str::limit($data['pdf_file']->getClientOriginalName(), 255, ''),
                        'xml_path' => $xmlPath,
                        'xml_original_name' => Str::limit($data['xml_file']->getClientOriginalName(), 255, ''),
                        'uploaded_by' => Auth::id(),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredPaths($uploadedPaths);
            report($exception);

            return back()->withInput()->withErrors([
                'invoices' => 'No se pudieron almacenar las facturas en S3. Intenta nuevamente.',
            ], 'estimateInvoices');
        }

        $count = count($folios);

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $estimate->id,
            'data' => 'cargó '.$count.($count === 1 ? ' factura' : ' facturas').' en la estimación '.$estimate->estimate_number.' del proyecto "'.$estimate->project->name.'".',
        ]);

        return redirect()->route('projects.show', $estimate->project)
            ->with('success', $count === 1 ? 'Factura cargada correctamente.' : "{$count} facturas cargadas correctamente.");
    }

    public function download(ProjectEstimateInvoice $invoice, string $format): mixed
    {
        abort_unless(in_array($format, ['pdf', 'xml'], true), 404);

        $path = $invoice->{$format.'_path'};

        abort_unless($path && Storage::disk('s3')->exists($path), 404);

        return Storage::disk('s3')->download($path, $invoice->{$format.'_original_name'} ?: ($invoice->folio.'.'.$format));
    }

    public function destroy(ProjectEstimateInvoice $invoice): RedirectResponse
    {
        $estimate = $invoice->estimate;
        $paths = $invoice->storedPaths();
        $folio = $invoice->folio ?: '#'.$invoice->id;

        $invoice->delete();
        $this->deleteStoredPaths($paths);

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $estimate->id,
            'data' => 'eliminó la factura '.$folio.' de la estimación '.$estimate->estimate_number.' del proyecto "'.$estimate->project->name.'".',
        ]);

        return redirect()->route('projects.show', $estimate->project)
            ->with('success', 'Factura eliminada correctamente.');
    }

    // `mimes` falla con CFDI sin declaración XML, por eso se valida la extensión (igual que en facturas de OC).
    private function xmlExtensionRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value instanceof UploadedFile && strtolower($value->getClientOriginalExtension()) !== 'xml') {
                $fail('El archivo debe ser de tipo XML.');
            }
        };
    }

    private function extractFiscalFolio(UploadedFile $xml): ?string
    {
        $raw = @file_get_contents($xml->getRealPath());
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $dom = new \DOMDocument;
        if (! @$dom->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS)) {
            return null;
        }

        $nodes = (new \DOMXPath($dom))->query("//*[local-name()='TimbreFiscalDigital']");
        if (! $nodes || $nodes->length === 0) {
            return null;
        }

        $attributes = $nodes->item(0)->attributes;
        $uuid = strtoupper(trim((string) ($attributes?->getNamedItem('UUID')?->nodeValue
            ?? $attributes?->getNamedItem('Uuid')?->nodeValue
            ?? $attributes?->getNamedItem('uuid')?->nodeValue
            ?? '')));

        return preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/', $uuid) ? $uuid : null;
    }

    private function deleteStoredPaths(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }
    }
}
