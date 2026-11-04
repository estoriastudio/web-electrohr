{{-- Navegación de la bandeja de RH: solo las pantallas de HR-006; las pestañas de specs posteriores se suman cuando se habiliten. --}}
<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $active === 'assignments' ? 'active' : '' }}" href="{{ route('rh.office.assignments.index') }}">
            <i class="ri-user-location-line me-1"></i> Asignaciones
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'inconsistencies' ? 'active' : '' }}" href="{{ route('rh.office.assignments.inconsistencies') }}">
            <i class="ri-error-warning-line me-1"></i> Incongruencias
        </a>
    </li>
</ul>
