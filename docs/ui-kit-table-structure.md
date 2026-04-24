# Estructura de tablas — UI Kit (Lahomes / Techzaa)

> Referencia extraída de: `customers-list.html`, `transactions.html`, `property-list.html`  
> Aplicar **siempre** esta estructura para mantener consistencia visual en toda la aplicación.

---

## 1. Esqueleto general de una vista con tabla

```html
<!-- Contenedor de página -->
<div class="page-content">
  <div class="container-fluid">

    <!-- Título de página -->
    <div class="row">
      <div class="col-12">
        <div class="page-title-box">
          <h4 class="mb-0 fw-semibold">Título de la sección</h4>
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="#">Sección padre</a></li>
            <li class="breadcrumb-item active">Página actual</li>
          </ol>
        </div>
      </div>
    </div>

    <!-- (Opcional) Tarjetas de resumen KPI arriba -->
    <div class="row">
      <div class="col-md-6 col-xl-3">
        <div class="card">
          <div class="card-body">…</div>
        </div>
      </div>
      <!-- repetir para cada KPI -->
    </div>

    <!-- Tabla principal -->
    <div class="row">
      <div class="col-xl-12">
        <div class="card">
          <!-- card-header -->
          <!-- card-body p-0 con la tabla -->
        </div>
      </div>
    </div>

  </div>
</div>
```

---

## 2. Estructura interna de la tarjeta con tabla

```html
<div class="card">

  <!-- ── Encabezado ── -->
  <div class="card-header d-flex justify-content-between align-items-center border-bottom">
    <div>
      <h4 class="card-title mb-0">Título de la tabla</h4>
    </div>
    <div>
      <!-- Acción principal (botón o dropdown) -->
      <button type="button" class="btn btn-sm btn-primary">
        <i class="ri-add-line me-1"></i> Nueva entrada
      </button>
      <!-- Alternativa: dropdown de opciones (Download / Export / Import) -->
      <div class="dropdown">
        <a href="#" class="dropdown-toggle btn btn-sm btn-outline-light rounded"
           data-bs-toggle="dropdown">Esta mes</a>
        <div class="dropdown-menu dropdown-menu-end">
          <a href="#!" class="dropdown-item">Descargar</a>
          <a href="#!" class="dropdown-item">Exportar</a>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Cuerpo: sin padding para que la tabla ocupe todo el ancho ── -->
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle text-nowrap table-hover table-centered mb-0">
        <thead class="bg-light-subtle">
          <tr>
            <!-- Columna checkbox (opcional) -->
            <th style="width: 20px;">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="checkAll">
                <label class="form-check-label" for="checkAll"></label>
              </div>
            </th>
            <th>Columna A</th>
            <th>Columna B</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>…</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
```

---

## 3. Clases CSS obligatorias

| Elemento | Clases |
|---|---|
| `<table>` | `table align-middle text-nowrap table-hover table-centered mb-0` |
| `<thead>` | `bg-light-subtle` |
| Card body (con tabla) | `card-body p-0` |
| Card header | `card-header d-flex justify-content-between align-items-center border-bottom` |
| Título en card header | `h4` con clase `card-title mb-0` |

---

## 4. Columna de nombre con avatar

```html
<td>
  <div class="d-flex align-items-center gap-2">
    <div>
      <img src="…" alt="" class="avatar-sm rounded-circle">
    </div>
    <div>
      <a href="#!" class="text-dark fw-medium fs-15">Nombre Apellido</a>
    </div>
  </div>
</td>
```

---

## 5. Columna de estado (badge)

```html
<!-- Éxito -->
<span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activo</span>

<!-- Advertencia -->
<span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Pendiente</span>

<!-- Peligro -->
<span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Inactivo</span>

<!-- Info -->
<span class="badge bg-info-subtle text-info py-1 px-2 fs-12">En revisión</span>

<!-- Secundario (etiquetas/permisos) -->
<span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">etiqueta</span>

<!-- Primario (roles) -->
<span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">admin</span>
```

---

## 6. Columna de acciones

```html
<td>
  <div class="d-flex gap-2">
    <!-- Ver -->
    <a href="#!" class="btn btn-light btn-sm">
      <iconify-icon icon="solar:eye-broken" class="align-middle fs-18"></iconify-icon>
    </a>
    <!-- Editar -->
    <a href="#!" class="btn btn-soft-primary btn-sm">
      <iconify-icon icon="solar:pen-2-broken" class="align-middle fs-18"></iconify-icon>
    </a>
    <!-- Eliminar -->
    <a href="#!" class="btn btn-soft-danger btn-sm">
      <iconify-icon icon="solar:trash-bin-minimalistic-2-broken" class="align-middle fs-18"></iconify-icon>
    </a>
  </div>
</td>
```

> **Nota:** El UI Kit usa `iconify-icon`. En el proyecto ElectroHR se pueden usar los íconos equivalentes de **Remix Icon** (`ri-*`) que ya están cargados vía `icons.min.css`.

---

## 7. Fila vacía (estado vacío)

```html
<tr>
  <td colspan="N" class="text-center text-muted py-4">
    No hay registros disponibles.
  </td>
</tr>
```

---

## 8. Tarjetas KPI (resumen superior, opcional)

```html
<div class="card">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h4 class="card-title mb-2">Título KPI</h4>
        <p class="text-muted fw-medium fs-22 mb-0">$12,780</p>
      </div>
      <div>
        <div class="avatar-md bg-primary bg-opacity-10 rounded">
          <iconify-icon icon="solar:wallet-money-broken"
                        class="fs-32 text-primary avatar-title"></iconify-icon>
        </div>
      </div>
    </div>
    <div class="d-flex align-items-center justify-content-between mt-3">
      <p class="mb-0">
        <span class="text-success fw-medium"><i class="ri-arrow-up-line"></i>34%</span>
        vs mes anterior
      </p>
      <a href="#!" class="link-primary fw-medium">
        Ver detalle <i class="ri-arrow-right-line align-middle"></i>
      </a>
    </div>
  </div>
</div>
```

---

## 9. Uso de pestañas (tabs) junto a tablas

Cuando una vista requiere múltiples pestañas cada una con su propia tabla, las pestañas van **fuera** de las tarjetas y cada pestaña contiene su propia tarjeta completa:

```html
<!-- Tabs nav -->
<ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-uno"
            type="button" role="tab">
      <i class="ri-group-line me-1"></i> Pestaña 1
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-dos"
            type="button" role="tab">
      <i class="ri-shield-user-line me-1"></i> Pestaña 2
    </button>
  </li>
</ul>

<!-- Tab content -->
<div class="tab-content" id="myTabContent">
  <div class="tab-pane fade show active" id="tab-uno" role="tabpanel">
    <div class="row">
      <div class="col-xl-12">
        <!-- card con tabla (estructura del punto 2) -->
      </div>
    </div>
  </div>
  <div class="tab-pane fade" id="tab-dos" role="tabpanel">
    <div class="row">
      <div class="col-xl-12">
        <!-- card con tabla (estructura del punto 2) -->
      </div>
    </div>
  </div>
</div>
```
