<?php

namespace App\Http\Controllers\Hr\Field;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use App\Services\Hr\HrPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyWorkerController extends Controller
{
    /** Columnas del perfil reducido: nunca sueldo, tipo de pago ni nada de importes. */
    private const PROFILE_COLUMNS = [
        'id', 'employee_code', 'first_name', 'last_name', 'nickname', 'rfc', 'curp', 'birth_date', 'hire_date',
        'position_category_id', 'worker_group_id', 'status', 'file_incomplete', 'profile_complete_deadline',
        'field_registered_at', 'ine_expiration_date', 'medical_certificate_expiration_date',
        'emergency_contact_name', 'emergency_contact_phone', 'nss', 'is_dc5',
    ];

    /** Mis trabajadores (Livewire): personas asignadas a las Obras del usuario. */
    public function index(): View
    {
        $this->authorizeField();

        return view('hr.field.workers.index');
    }

    /** Perfil reducido de solo lectura; una persona fuera del alcance responde 403. */
    public function show(int $worker): View
    {
        $this->authorizeField();

        abort_unless(Worker::query()->inFieldScope(Auth::user())->whereKey($worker)->exists(), 403);

        $worker = Worker::query()
            ->select(self::PROFILE_COLUMNS)
            ->with([
                'positionCategory',
                'workerGroup',
                'currentAssignment.projectWork.project',
                'file',
                'dc3s',
            ])
            ->findOrFail($worker);

        return view('hr.field.workers.show', [
            'worker' => $worker,
            'documents' => $this->documents($worker),
        ]);
    }

    /** Documentos del expediente con su vencimiento (los vencimientos de INE y certificado viven en el trabajador). */
    private function documents(Worker $worker): array
    {
        $labels = [
            'ine_path' => ['INE', 'ine_expiration_date', true],
            'birth_certificate_path' => ['Acta de nacimiento', 'birth_certificate_expiration_date', false],
            'address_proof_path' => ['Comprobante de domicilio', 'address_proof_expiration_date', false],
            'nss_path' => ['Comprobante NSS', 'nss_expiration_date', false],
            'license_path' => ['Licencia', 'license_expiration_date', false],
            'tax_status_path' => ['Constancia fiscal', 'tax_status_expiration_date', false],
            'medical_certificate_path' => ['Certificado médico', 'medical_certificate_expiration_date', true],
            'cv_path' => ['Currículum', 'cv_expiration_date', false],
            'emergency_contact_ine_path' => ['INE contacto de emergencia', 'emergency_contact_ine_expiration_date', false],
            'professional_title_path' => ['Título profesional (opcional)', null, false],
            'professional_license_path' => ['Cédula profesional (opcional)', null, false],
        ];

        $file = $worker->file;

        return collect($labels)->map(fn (array $document, string $column) => [
            'key' => $column,
            'label' => $document[0],
            'available' => filled($file?->{$column}),
            'expiration' => $document[1] ? ($document[2] ? $worker->{$document[1]} : $file?->{$document[1]}) : null,
        ])->values()->all();
    }

    private function authorizeField(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole([HrPermissions::ROLE_SUPERVISOR, HrPermissions::ROLE_MANAGER, HrPermissions::ROLE_ADMIN]),
            403
        );
    }
}
