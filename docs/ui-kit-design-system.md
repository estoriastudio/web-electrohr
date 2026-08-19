# UI Kit — Sistema de Diseño Completo
> **Framework:** Bootstrap 5.3 · **Base:** Live Software

Este documento es la **fuente de verdad** para mantener cohesión visual en todas las pantallas. Antes de escribir CSS personalizado, primero busca aquí la clase correcta. Toda extensión del sistema debe apoyarse en la paleta y las variables ya definidas.

---

## Índice

1. [Tipografía](#1-tipografía)
2. [Paleta de Colores](#2-paleta-de-colores)
3. [Variables CSS Globales](#3-variables-css-globales)
4. [Estructura de Layout](#4-estructura-de-layout)
5. [Topbar](#5-topbar)
6. [Sidebar / Menú Principal](#6-sidebar--menú-principal)
7. [Page Title y Breadcrumb](#7-page-title-y-breadcrumb)
8. [Cards](#8-cards)
9. [Tablas](#9-tablas)
10. [Botones](#10-botones)
11. [Badges / Etiquetas](#11-badges--etiquetas)
12. [Avatares](#12-avatares)
13. [Alertas](#13-alertas)
14. [Formularios](#14-formularios)
15. [Modales](#15-modales)
16. [Tabs y Pills](#16-tabs-y-pills)
17. [Acordeones](#17-acordeones)
18. [Progress Bars](#18-progress-bars)
19. [Popovers y Tooltips](#19-popovers-y-tooltips)
20. [Paginación](#20-paginación)
21. [Spinners](#21-spinners)
22. [Dropdowns](#22-dropdowns)
23. [Offcanvas](#23-offcanvas)
24. [Utilidades del Kit](#24-utilidades-del-kit)
25. [Íconos](#25-íconos)
26. [Layout de Autenticación](#26-layout-de-autenticación)
27. [Modo Oscuro](#27-modo-oscuro)
28. [Convenciones Blade](#28-convenciones-blade)
29. [Patrones Reutilizables](#29-patrones-reutilizables)

---

## 1. Tipografía

### Fuentes cargadas

| Familia | Pesos | Uso |
|---------|-------|-----|
| **Figtree** | 300–900 | Fuente principal (body y headings) |
| Inter | 100–900 | Importada, disponible para uso puntual |
| Baloo Bhai 2 | 400–800 | Importada, disponible para uso puntual |
| SFMono / Menlo / Consolas | — | Fuente monoespaciada para `<code>` |

### Escala de tamaño de fuente — `fs-*`

El kit extiende Bootstrap con clases granulares de pixel a pixel (10–36) y saltos mayores:

```
fs-10   fs-11   fs-12   fs-13   fs-14   fs-15   fs-16   fs-17   fs-18
fs-19   fs-20   fs-21   fs-22   fs-23   fs-24   fs-25   fs-26   fs-27
fs-28   fs-29   fs-30   fs-31   fs-32   fs-33   fs-34   fs-35   fs-36
fs-48   fs-60
```

También están disponibles las Bootstrap estándar `fs-1` → `fs-6` (rem-based).

> **Regla de uso:** `fs-12` para metadata/timestamps · `fs-13` para texto secundario · `fs-14`/`fs-15` para cuerpo de tabla · `fs-16`/`fs-18` para subheadings · `fs-24` para íconos en topbar.

### Clases `font-*` (alias numérico en px)

```html
<span class="font-14">14px</span>
<span class="font-16">16px</span>
```

> Estas son clases heredadas del kit base. Se prefiere `fs-*` en código nuevo.

### Pesos de fuente — `fw-*`

```
fw-lighter   fw-light   fw-normal   fw-medium   fw-semibold   fw-bold   fw-bolder
```

### Tamaños de heading por defecto

| Tag | Tamaño (≥1200px) | Peso |
|-----|------------------|------|
| `h1` / `.h1` | 2.25rem | 500 |
| `h2` / `.h2` | 1.875rem | 500 |
| `h3` / `.h3` | 1.5rem | 500 |
| `h4` / `.h4` | 1.125rem | 500 |
| `h5` / `.h5` | 0.9375rem | 500 |
| `h6` / `.h6` | 0.75rem | 500 |

Tamaño base del body: **0.875rem** (14px). Color: `#5d7186`.

---

## 2. Paleta de Colores

### Colores de marca (Light Mode)

| Token | Hex | RGB | Uso |
|-------|-----|-----|-----|
| `primary` | `#604ae3` | 96, 74, 227 | CTA principal, activos, menú hover |
| `secondary` | `#9035e3` | 144, 53, 227 | Roles, etiquetas secundarias |
| `success` | `#5cc184` | 92, 193, 132 | Confirmación, creación |
| `warning` | `#f0934e` | 240, 147, 78 | Alertas, actualización |
| `danger` | `#e96767` | 233, 103, 103 | Errores, eliminación |
| `info` | `#45c5cd` | 69, 197, 205 | Información neutral |
| `light` | `#eef2f7` | 238, 242, 247 | Fondos sutiles |
| `dark` | `#323a46` | 50, 58, 70 | Texto oscuro, fondos |

### Colores extendidos del kit

| Token | Hex | Descripción |
|-------|-----|-------------|
| `red` | `#e96767` | Alias de danger |
| `yellow` | `#f8ac59` | Amarillo cálido |
| `green` | `#5cc184` | Alias de success |
| `cyan` | `#45c5cd` | Alias de info |
| `orange` | *(via badge/badge-soft)* | Disponible en badges |
| `pink` | *(via badge/badge-soft)* | Disponible en badges |
| `purple` | *(via badge/badge-soft)* | Disponible en badges |

### Escala de grises

```
gray-100: #f8f9fa    ←  fondos alternativos
gray-200: #eef2f7    ←  fondos de cards (light)
gray-300: #d8dfe7    ←  bordes de inputs
gray-400: #b0b0bb    ←  bordes de focus
gray-500: #8486a7    ←  iconos inactivos, placeholder
gray-600: #687d92    ←  texto secundario
gray-700: #424e5a    ←  texto terciario
gray-800: #36404a    ←  texto strong
gray-900: #323a46    ←  texto headings
```

### Fondos del sistema

| Variable | Light | Dark |
|----------|-------|------|
| `--bs-body-bg` | `#f9f9fc` | `#141A21` |
| `--bs-secondary-bg` (card bg) | `#ffffff` | `#1C252E` |
| `--bs-tertiary-bg` | `#f8f9fa` | `#2f3943` |
| `--bs-border-color` | `#eaedf1` | `#29333d` |
| `--bs-body-color` | `#5d7186` | `#aab8c5` |

---

## 3. Variables CSS Globales

### Layout

```css
--bs-main-nav-width:          260px    /* sidebar expandido */
--bs-main-nav-width-sm:        75px    /* sidebar condensado */
--bs-main-nav-item-icon-size:  18px
--bs-main-nav-item-font-size:  15px
--bs-main-nav-item-padding-x:  15px
--bs-main-nav-item-padding-y:  10px
--bs-main-nav-item-margin-y:    2px
--bs-topbar-height:            70px
--bs-footer-height:            60px
--bs-logo-lg-height:           45px
--bs-logo-sm-height:           28px
```

### Inputs

```css
--bs-input-border-color:       #d8dfe7
--bs-input-focus-border-color: #b0b0bb
```

### Cards

```css
--bs-card-spacer-y:       1.25rem
--bs-card-spacer-x:       1.25rem
--bs-card-cap-padding-y:  0.9375rem
--bs-card-cap-padding-x:  1.25rem
--bs-card-border-width:   0              /* sin borde por defecto */
--bs-card-box-shadow:     0px 3px 4px 0px rgba(0,0,0,0.03)
--bs-card-border-radius:  var(--bs-border-radius)   /* 0.35rem */
```

### Bordes y radios

```css
--bs-border-radius:     0.35rem
--bs-border-radius-sm:  0.15rem
--bs-border-radius-lg:  0.5rem
--bs-border-radius-xl:  1rem
--bs-border-radius-xxl: 2rem
--bs-border-radius-pill: 50rem
--bs-box-shadow:        0px 3px 4px 0px rgba(0,0,0,0.03)
--bs-box-shadow-lg:     0 5px 10px rgba(30,32,37,0.12)
```

### Menú — colores temáticos

```css
/* Light menu */
--bs-main-nav-bg:              #eeeef3
--bs-main-nav-item-color:      #696b84
--bs-main-nav-item-hover-bg:   #f8f9fa
--bs-main-nav-item-hover-color: #604ae3
--bs-main-nav-item-active-color: #604ae3
--bs-main-nav-border-color:    #eaedf1

/* Dark menu */
--bs-main-nav-bg:              #1C252E
--bs-main-nav-item-color:      #a8b2b5
--bs-main-nav-item-hover-bg:   rgba(255,255,255,0.06)
--bs-main-nav-item-hover-color: #ffffff
--bs-main-nav-item-active-color: #eef2f7
--bs-main-nav-border-color:    #2f3944
```

### Topbar — colores temáticos

```css
/* Light topbar */
--bs-topbar-bg:       #ffffff
--bs-topbar-item-color: #707793
--bs-topbar-search-bg: #e8edf1

/* Dark topbar */
--bs-topbar-bg:       #141A21
--bs-topbar-item-color: #afb9cf
--bs-topbar-search-bg: #212b36
```

---

## 4. Estructura de Layout

### Atributos de tema en `<html>`

```html
<html
  data-bs-theme="light | dark"
  data-menu-color="light | dark"
  data-topbar-color="light | dark"
  data-menu-size="default | condensed | hidden | sm-hover | sm-hover-active"
>
```

### Estructura HTML del wrapper

```html
<div class="wrapper">
    <header>
        <div class="topbar">...</div>
    </header>

    <div class="main-nav">...</div>   <!-- sidebar izquierdo -->

    <div class="page-content">
        <div class="container-fluid">
            <div class="page-title-box">...</div>
            @yield('content')
        </div>
        <footer class="footer">...</footer>
    </div>
</div>
```

### Modos de sidebar (`data-menu-size`)

| Valor | Comportamiento |
|-------|---------------|
| `default` | Sidebar expandido 260px |
| `condensed` | Solo íconos 75px |
| `hidden` | Oculto, toggle manual |
| `sm-hover` | Compacto, expande al hover |
| `sm-hover-active` | Compacto, activo al hover |

---

## 5. Topbar

### Estructura

```html
<header>
    <div class="topbar">
        <div class="container-fluid">
            <div class="navbar-header">

                <!-- ← Izquierda -->
                <div class="d-flex align-items-center gap-2">
                    <div class="topbar-item">
                        <button class="button-toggle-menu topbar-button">
                            <i class="ri-menu-2-line fs-24"></i>
                        </button>
                    </div>
                    <!-- Buscador (opcional) -->
                    <form class="app-search d-none d-md-block">
                        <div class="position-relative">
                            <input type="search" class="form-control border-0" placeholder="Buscar...">
                            <i class="ri-search-line search-widget-icon"></i>
                        </div>
                    </form>
                </div>

                <!-- → Derecha -->
                <div class="d-flex align-items-center gap-1">

                    <!-- Toggle dark/light -->
                    <div class="topbar-item">
                        <button class="topbar-button" id="light-dark-mode">
                            <i class="ri-moon-line fs-24 light-mode"></i>
                            <i class="ri-sun-line fs-24 dark-mode"></i>
                        </button>
                    </div>

                    <!-- Fullscreen -->
                    <div class="dropdown topbar-item d-none d-lg-flex">
                        <button class="topbar-button" data-toggle="fullscreen">
                            <i class="ri-fullscreen-line fs-24 fullscreen"></i>
                            <i class="ri-fullscreen-exit-line fs-24 quit-fullscreen"></i>
                        </button>
                    </div>

                    <!-- Notificaciones -->
                    <div class="dropdown topbar-item">
                        <button class="topbar-button position-relative"
                                data-bs-toggle="dropdown">
                            <i class="ri-notification-3-line fs-24"></i>
                            <span class="position-absolute topbar-badge fs-10 translate-middle badge bg-danger rounded-pill">3</span>
                        </button>
                        <div class="dropdown-menu py-0 dropdown-lg dropdown-menu-end">
                            <!-- contenido de notificaciones -->
                        </div>
                    </div>

                    <!-- Ajustes de tema -->
                    <div class="topbar-item d-none d-md-flex">
                        <button class="topbar-button"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#theme-settings-offcanvas">
                            <i class="ri-settings-4-line fs-24"></i>
                        </button>
                    </div>

                    <!-- Usuario -->
                    <div class="dropdown topbar-item">
                        <a class="topbar-button" data-bs-toggle="dropdown">
                            <img class="rounded-circle" width="32" height="32" src="...">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">Nombre del usuario</h6>
                            <a class="dropdown-item" href="#">Perfil</a>
                            <div class="dropdown-divider my-1"></div>
                            <button class="dropdown-item text-danger">Cerrar sesión</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</header>
```

### Clases del Topbar

| Clase | Descripción |
|-------|-------------|
| `.topbar` | Contenedor raíz |
| `.navbar-header` | Flex row izquierda/derecha |
| `.topbar-item` | Wrapper de cada elemento |
| `.topbar-button` | Botón icono (sin borde) |
| `.topbar-badge` | Badge flotante sobre botón |
| `.button-toggle-menu` | Hamburguesa que colapsa sidebar |
| `.dropdown-lg` | Dropdown ancho (~300px) para notificaciones |
| `.app-search` | Buscador del topbar |
| `.search-widget-icon` | Ícono dentro del input de búsqueda |
| `.light-mode` / `.dark-mode` | Visibilidad condicional por tema |
| `.fullscreen` / `.quit-fullscreen` | Visibilidad condicional por estado |

---

## 6. Sidebar / Menú Principal

### Estructura completa

```html
<div class="main-nav">

    <!-- Logo -->
    <div class="logo-box">
        <a href="/" class="logo-dark">   <!-- visible en light mode -->
            <img src="logo-sm.png"   class="logo-sm">
            <img src="logo-dark.png" class="logo-lg">
        </a>
        <a href="/" class="logo-light">  <!-- visible en dark mode -->
            <img src="logo-sm.png"    class="logo-sm">
            <img src="logo-light.png" class="logo-lg">
        </a>
    </div>

    <!-- Toggle hover -->
    <button class="button-sm-hover" aria-label="Show Full Sidebar">
        <i class="ri-menu-2-line fs-24 button-sm-hover-icon"></i>
    </button>

    <div class="scrollbar" data-simplebar>
        <ul class="navbar-nav" id="navbar-nav">

            <!-- Separador de sección -->
            <li class="menu-title">Sección</li>

            <!-- Ítem simple -->
            <li class="nav-item">
                <a class="nav-link" href="/ruta">
                    <span class="nav-icon"><i class="ri-dashboard-2-line"></i></span>
                    <span class="nav-text">Ítem</span>
                </a>
            </li>

            <!-- Ítem con submenú (collapse) -->
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarX"
                   data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarX">
                    <span class="nav-icon"><i class="ri-pages-line"></i></span>
                    <span class="nav-text">Módulo</span>
                </a>
                <div class="collapse" id="sidebarX">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="/sub">Subítem</a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Ítem con badge -->
            <li class="nav-item">
                <a class="nav-link" href="/ruta">
                    <span class="nav-icon"><i class="ri-shapes-line"></i></span>
                    <span class="nav-text">Módulo</span>
                    <span class="badge bg-danger badge-pill text-end">Hot</span>
                </a>
            </li>

        </ul>
    </div>
</div>
```

### Clases del Sidebar

| Clase | Descripción |
|-------|-------------|
| `.main-nav` | Contenedor del sidebar |
| `.logo-box` | Área del logo |
| `.logo-dark` / `.logo-light` | Versiones del logo por tema |
| `.logo-sm` / `.logo-lg` | Tamaño del logo por modo de sidebar |
| `.scrollbar` | Área scrollable con SimpleBar |
| `.navbar-nav` | Lista principal de navegación |
| `.menu-title` | Separador/label de sección |
| `.nav-item` | Ítem de menú |
| `.nav-link` | Enlace de ítem |
| `.nav-icon` | Contenedor del ícono |
| `.nav-text` | Texto del ítem |
| `.menu-arrow` | Flecha de collapse (auto vía CSS) |
| `.sub-navbar-nav` | Lista de submenú |
| `.sub-nav-item` | Ítem de submenú |
| `.sub-nav-link` | Enlace de submenú |
| `.button-sm-hover` | Botón toggle en modo sm-hover |
| `.button-sm-hover-icon` | Ícono de ese botón |

---

## 7. Page Title y Breadcrumb

```html
<div class="page-title-box">
    <h4 class="mb-0 fw-semibold">Título de la página</h4>
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item active">Sección actual</li>
    </ol>
</div>
```

**En Blade:**

```blade
@section('page_title', 'Título')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Sección actual</li>
@endsection
```

> El separador del breadcrumb usa automáticamente un ícono de Remixicon (definido en CSS con `font-family: "remixicon"`).

---

## 8. Cards

### Card estándar con header/footer

```html
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h4 class="card-title mb-0">Título</h4>
        <button class="btn btn-sm btn-primary">Acción</button>
    </div>
    <div class="card-body">
        <!-- contenido -->
    </div>
    <div class="card-footer border-top">
        <!-- paginación u otras acciones -->
    </div>
</div>
```

### Card sin padding (para tablas o listas)

```html
<div class="card">
    <div class="card-header ...">...</div>
    <div class="card-body p-0">
        <div class="table-responsive">...</div>
    </div>
</div>
```

### Card de perfil (centrada)

```html
<div class="card text-center">
    <div class="card-body py-4">
        <img src="..." class="rounded-circle mb-3" width="80" height="80">
        <h5 class="fw-semibold mb-1">Nombre</h5>
        <p class="text-muted fs-13 mb-3">email@ejemplo.com</p>
        <p class="text-muted fs-12 mt-3 mb-0">
            <i class="ri-calendar-line me-1"></i>Desde 01/01/2025
        </p>
    </div>
</div>
```

### Card con imagen

```html
<div class="card">
    <img src="..." class="card-img-top" alt="...">
    <div class="card-body">
        <h5 class="card-title">Título</h5>
        <p class="card-text text-muted">Descripción</p>
        <a href="#" class="btn btn-primary btn-sm">Ver más</a>
    </div>
</div>
```

### Card con list-group

```html
<div class="card">
    <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Lista</h5>
    </div>
    <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between align-items-center">
            Ítem
            <span class="badge bg-primary-subtle text-primary">5</span>
        </li>
    </ul>
</div>
```

### Card group

```html
<div class="card-group">
    <div class="card">...</div>
    <div class="card">...</div>
    <div class="card">...</div>
</div>
```

---

## 9. Tablas

### Patrón estándar

```html
<div class="table-responsive">
    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
        <thead class="bg-light-subtle">
            <tr>
                <th>Columna A</th>
                <th>Columna B</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="fs-14">Dato</td>
                <td class="text-muted fs-13">Secundario</td>
            </tr>
            <!-- Estado vacío -->
            <tr>
                <td colspan="N" class="text-center text-muted py-4">
                    <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                    Sin registros
                </td>
            </tr>
        </tbody>
    </table>
</div>
```

### Clases de tabla del kit

| Clase | Descripción |
|-------|-------------|
| `table-centered` | Centra verticalmente `td` y `th` |
| `table-nowrap` | Sin salto de línea en celdas |
| `table-hover` | Resalta fila al hover |
| `table-striped` | Filas alternas |
| `table-striped-columns` | Columnas alternas |
| `table-bordered` | Bordes en todas las celdas |
| `table-borderless` | Sin bordes |
| `table-sm` | Tabla compacta (menor padding) |
| `table-dark` | Tema oscuro en tabla |
| `table-responsive` | Scroll horizontal en pantallas pequeñas |
| `table-responsive-sm` | Scroll horizontal solo en sm |
| `table-group-divider` | Divisor entre `thead`/`tbody`/`tfoot` |
| `bg-light-subtle` | Fondo suave para `thead` |

**Clases de color de fila (Bootstrap):**
`table-primary` · `table-secondary` · `table-success` · `table-warning` · `table-danger` · `table-info` · `table-light` · `table-active`

---

## 10. Botones

### Variantes sólidas

```html
<button class="btn btn-primary">Primary</button>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-warning">Warning</button>
<button class="btn btn-danger">Danger</button>
<button class="btn btn-info">Info</button>
<button class="btn btn-light">Light</button>
<button class="btn btn-dark">Dark</button>
<button class="btn btn-link">Link</button>
```

### Variantes Soft (fondo tenue → relleno al hover)

```html
<button class="btn btn-soft-primary">Soft Primary</button>
<button class="btn btn-soft-secondary">Soft Secondary</button>
<button class="btn btn-soft-success">Soft Success</button>
<button class="btn btn-soft-warning">Soft Warning</button>
<button class="btn btn-soft-danger">Soft Danger</button>
<button class="btn btn-soft-info">Soft Info</button>
<button class="btn btn-soft-light">Soft Light</button>
<button class="btn btn-soft-dark">Soft Dark</button>
```

### Variantes Outline

```html
<button class="btn btn-outline-primary">Outline Primary</button>
<button class="btn btn-outline-secondary">Outline Secondary</button>
<button class="btn btn-outline-success">Outline Success</button>
<button class="btn btn-outline-warning">Outline Warning</button>
<button class="btn btn-outline-danger">Outline Danger</button>
<button class="btn btn-outline-info">Outline Info</button>
<button class="btn btn-outline-light">Outline Light</button>
```

### Tamaños

```html
<button class="btn btn-primary btn-xs">Extra pequeño</button>  <!-- 0.75rem padding -->
<button class="btn btn-primary btn-sm">Pequeño</button>
<button class="btn btn-primary">Normal</button>
<button class="btn btn-primary btn-lg">Grande</button>
```

### Grupos y contenedores

```html
<!-- Grupo horizontal -->
<div class="btn-group">
    <button class="btn btn-primary">A</button>
    <button class="btn btn-primary">B</button>
</div>

<!-- Grupo vertical -->
<div class="btn-group-vertical">
    <button class="btn btn-primary">A</button>
    <button class="btn btn-primary">B</button>
</div>

<!-- Lista con separación (para demos) -->
<div class="button-list">
    <button class="btn btn-primary">A</button>
    <button class="btn btn-soft-primary">B</button>
</div>
```

### Botones de acción en tablas

```html
<!-- Editar -->
<button class="btn btn-soft-primary btn-sm">
    <i class="ri-edit-line align-middle fs-18"></i>
</button>

<!-- Eliminar -->
<button class="btn btn-soft-danger btn-sm">
    <i class="ri-delete-bin-line align-middle fs-18"></i>
</button>

<!-- Ver -->
<button class="btn btn-soft-info btn-sm">
    <i class="ri-eye-line align-middle fs-18"></i>
</button>
```

### Botón ancho completo

```html
<div class="d-grid">
    <button class="btn btn-primary">Botón full width</button>
</div>
```

---

## 11. Badges / Etiquetas

### Variantes Sólidas (Bootstrap)

```html
<span class="badge bg-primary">Primary</span>
<span class="badge bg-success">Success</span>
<span class="badge bg-warning">Warning</span>
<span class="badge bg-danger">Danger</span>
<span class="badge bg-info">Info</span>
<span class="badge bg-dark">Dark</span>
```

### Variantes Subtle (Bootstrap 5.3)

```html
<!-- Fondo muy suave, texto coloreado — patrón preferido en el kit -->
<span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">Primary</span>
<span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Secondary</span>
<span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Success</span>
<span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Warning</span>
<span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Danger</span>
<span class="badge bg-info-subtle text-info py-1 px-2 fs-12">Info</span>
<span class="badge bg-light-subtle text-dark py-1 px-2 fs-12">Light</span>
```

### Variantes Soft (kit — opacidad 18%)

```html
<span class="badge badge-soft-primary">Soft Primary</span>
<span class="badge badge-soft-secondary">Soft Secondary</span>
<span class="badge badge-soft-success">Soft Success</span>
<span class="badge badge-soft-warning">Soft Warning</span>
<span class="badge badge-soft-danger">Soft Danger</span>
<span class="badge badge-soft-info">Soft Info</span>
<span class="badge badge-soft-dark">Soft Dark</span>
<span class="badge badge-soft-orange">Soft Orange</span>
<span class="badge badge-soft-pink">Soft Pink</span>
<span class="badge badge-soft-purple">Soft Purple</span>
```

### Variantes Outline (kit)

```html
<span class="badge badge-outline-primary">Outline Primary</span>
<span class="badge badge-outline-secondary">Outline Secondary</span>
<span class="badge badge-outline-success">Outline Success</span>
<span class="badge badge-outline-warning">Outline Warning</span>
<span class="badge badge-outline-danger">Outline Danger</span>
<span class="badge badge-outline-info">Outline Info</span>
<span class="badge badge-outline-dark">Outline Dark</span>
<span class="badge badge-outline-orange">Outline Orange</span>
<span class="badge badge-outline-pink">Outline Pink</span>
<span class="badge badge-outline-purple">Outline Purple</span>
```

### Badge texto con fondo (text-bg-*)

```html
<span class="badge text-bg-primary">Primary</span>
<span class="badge text-bg-success">Success</span>
<span class="badge text-bg-warning">Warning</span>
<span class="badge text-bg-danger">Danger</span>
```

### Modificadores de forma

```html
<span class="badge bg-primary badge-pill">Pill</span>    <!-- border-radius: 50rem -->
<span class="badge bg-primary rounded-pill">Pill BS5</span>
```

### Regla de uso semántico

| Contexto | Clase recomendada |
|----------|-------------------|
| Roles, permisos | `bg-primary-subtle text-primary` |
| Estado activo | `bg-success-subtle text-success` |
| Estado pendiente | `bg-warning-subtle text-warning` |
| Estado error | `bg-danger-subtle text-danger` |
| Tipo/categoría | `bg-info-subtle text-info` |
| Contador en nav | `badge bg-danger badge-pill` |
| Protegido/especial | `bg-warning-subtle text-warning` |

---

## 12. Avatares

### Tamaños

| Clase | Dimensión |
|-------|-----------|
| `avatar-xs` | 1.5rem × 1.5rem |
| `avatar-sm` | 2.25rem × 2.25rem |
| `avatar` | 3rem × 3rem (base) |
| `avatar-md` | 3.5rem × 3.5rem |
| `avatar-lg` | 4.5rem × 4.5rem |
| `avatar-xl` | 6rem × 6rem |
| `avatar-xxl` | 7.5rem × 7.5rem |

### Con inicial (initials avatar)

```html
<!-- xs — icono de notificación -->
<div class="avatar-xs bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
    <i class="ri-add-line text-success fs-14"></i>
</div>

<!-- sm — ítem de tabla -->
<div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
    <span class="text-primary fw-semibold fs-15">J</span>
</div>

<!-- md — card de perfil -->
<div class="avatar-md bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
    <span class="text-primary fw-bold fs-20">JD</span>
</div>
```

### Con imagen

```html
<!-- Circular pequeño (topbar) -->
<img class="rounded-circle" width="32" height="32" src="..." alt="Nombre">

<!-- Circular con borde -->
<img class="rounded-circle avatar-border" width="64" height="64" src="...">

<!-- Thumbnail (rectangular con borde) -->
<img class="img-thumbnail rounded-circle" width="80" src="...">
```

### Avatar title (para fondos de color sólido)

```html
<div class="avatar-sm bg-primary rounded-circle">
    <span class="avatar-title text-white fs-16">J</span>
</div>
```

### Grupo de avatares

```html
<div class="avatar-group">
    <div class="avatar avatar-group-item">
        <img class="rounded-circle" src="..." alt="">
    </div>
    <div class="avatar avatar-group-item">
        <img class="rounded-circle" src="..." alt="">
    </div>
    <!-- Grupo pequeño -->
    <div class="avatar-group avatar-group-sm">
        <div class="avatar avatar-group-item">...</div>
    </div>
</div>
```

---

## 13. Alertas

### Bootstrap estándar

```html
<div class="alert alert-primary" role="alert">Mensaje primario.</div>
<div class="alert alert-success" role="alert">Operación exitosa.</div>
<div class="alert alert-warning" role="alert">Advertencia.</div>
<div class="alert alert-danger"  role="alert">Error.</div>
<div class="alert alert-info"    role="alert">Información.</div>
```

### Con botón de cierre (dismissible)

```html
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Operación exitosa.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

### Con ícono (`alert-icon`)

```html
<div class="alert alert-primary d-flex align-items-center gap-2" role="alert">
    <div class="alert-icon">
        <i class="ri-information-line fs-18"></i>
    </div>
    <div>Mensaje con ícono.</div>
</div>
```

### Con heading y enlace

```html
<div class="alert alert-warning" role="alert">
    <h5 class="alert-heading fw-semibold">Atención</h5>
    <p class="mb-0">Texto del alert. <a href="#" class="alert-link">Más info</a>.</p>
</div>
```

### Mensajes flash de Laravel

```blade
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
```

---

## 14. Formularios

### Campo de texto estándar

```html
<div class="mb-3">
    <label class="form-label" for="campo">Etiqueta</label>
    <input type="text" id="campo" name="campo"
           class="form-control @error('campo') is-invalid @enderror"
           value="{{ old('campo') }}" placeholder="Escribe aquí..." required>
    @error('campo')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Texto de ayuda opcional.</div>
</div>
```

### Tamaños de input

```html
<input class="form-control form-control-sm" type="text" placeholder="Pequeño">
<input class="form-control"                 type="text" placeholder="Normal">
<input class="form-control form-control-lg" type="text" placeholder="Grande">
```

### Input de autenticación (fondo suave)

```html
<input type="email" class="form-control bg-light bg-opacity-50 border-light py-2"
       placeholder="correo@ejemplo.com">
```

### Select y Textarea

```html
<select class="form-select">
    <option value="">Selecciona...</option>
    <option value="1">Opción 1</option>
</select>

<textarea class="form-control" rows="4" placeholder="Escribe..."></textarea>
```

### Input Group

```html
<div class="input-group">
    <span class="input-group-text"><i class="ri-search-line"></i></span>
    <input type="text" class="form-control" placeholder="Buscar...">
    <button class="btn btn-primary">Buscar</button>
</div>
```

### Checkbox estándar

```html
<div class="form-check">
    <input class="form-check-input" type="checkbox" id="check1" name="campo" value="1">
    <label class="form-check-label" for="check1">Opción</label>
</div>
```

### Checkbox con color (`form-checkbox-{color}`)

```html
<div class="form-check form-checkbox-primary">
    <input class="form-check-input" type="checkbox" id="c1" checked>
    <label class="form-check-label" for="c1">Primary</label>
</div>
<div class="form-check form-checkbox-success">...</div>
<div class="form-check form-checkbox-warning">...</div>
<div class="form-check form-checkbox-danger">...</div>
<div class="form-check form-checkbox-info">...</div>
<div class="form-check form-checkbox-secondary">...</div>
<div class="form-check form-checkbox-dark">...</div>
```

### Radio con color (`form-radio-{color}`)

```html
<div class="form-check form-radio-primary">
    <input class="form-check-input" type="radio" name="grupo" id="r1" value="1">
    <label class="form-check-label" for="r1">Opción A</label>
</div>
```

**Colores disponibles para checkbox y radio:** `primary` · `secondary` · `success` · `warning` · `info` · `danger` · `red` · `yellow` · `green` · `teal` · `cyan` · `light` · `dark`

### Switch

```html
<div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" id="switch1" role="switch">
    <label class="form-check-label" for="switch1">Activo</label>
</div>
```

### Inline

```html
<div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" id="i1">
    <label class="form-check-label" for="i1">Opción A</label>
</div>
<div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" id="i2">
    <label class="form-check-label" for="i2">Opción B</label>
</div>
```

### Checkbox tipo TODO

```html
<div class="form-todo">
    <input type="checkbox" id="todo1">
    <label for="todo1">Tarea pendiente</label>
</div>
```

---

## 15. Modales

### Modal centrado estándar (tamaño grande)

```html
<div class="modal fade" id="modalId" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Título</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <!-- campos del formulario en grid -->
                <div class="col-md-6">
                    <label class="form-label">Campo</label>
                    <input type="text" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </div>
    </div>
</div>
```

### Modal pequeño (confirmación)

```html
<div class="modal fade" id="modalSmall" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                ¿Estás seguro de que deseas continuar?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
                <button type="button" class="btn btn-danger">Sí, eliminar</button>
            </div>
        </div>
    </div>
</div>
```

### Trigger

```html
<button class="btn btn-primary btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#modalId">
    <i class="ri-add-line me-1"></i> Nuevo
</button>
```

### Abrir modal via JS (para mostrar errores de validación)

```blade
@push('scripts')
<script>
    @if ($errors->any())
        document.addEventListener('DOMContentLoaded', function () {
            new bootstrap.Modal(document.getElementById('modalId')).show();
        });
    @endif
</script>
@endpush
```

---

## 16. Tabs y Pills

### Nav Tabs (estilo pestaña)

```html
<!-- Tabs fuera de la card -->
<ul class="nav nav-tabs" id="miTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-uno-btn"
                data-bs-toggle="tab" data-bs-target="#tab-uno"
                type="button" role="tab">
            <i class="ri-group-line me-1"></i> Pestaña 1
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-dos-btn"
                data-bs-toggle="tab" data-bs-target="#tab-dos"
                type="button" role="tab">
            <i class="ri-shield-user-line me-1"></i> Pestaña 2
        </button>
    </li>
</ul>

<!-- Contenido — mt-0 pt-0 elimina el gap visual -->
<div class="tab-content mt-0 pt-0" id="miTabContent">
    <div class="tab-pane fade show active" id="tab-uno" role="tabpanel">
        <!-- contenido -->
    </div>
    <div class="tab-pane fade" id="tab-dos" role="tabpanel">
        <!-- contenido -->
    </div>
</div>
```

> **Regla:** los `nav-tabs` siempre van **fuera de la card**. El `tab-content` va inmediatamente después con `mt-0 pt-0`.

### Nav Pills (estilo botón redondeado)

```html
<ul class="nav nav-pills" id="pills-tab" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pill-uno">
            Opción 1
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pill-dos">
            Opción 2
        </button>
    </li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="pill-uno">...</div>
    <div class="tab-pane fade" id="pill-dos">...</div>
</div>
```

### Nav Underline (solo línea inferior)

```html
<ul class="nav nav-underline">
    <li class="nav-item"><a class="nav-link active" href="#">Activo</a></li>
    <li class="nav-item"><a class="nav-link" href="#">Normal</a></li>
</ul>
```

---

## 17. Acordeones

```html
<div class="accordion" id="acordeonId">
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button" type="button"
                    data-bs-toggle="collapse" data-bs-target="#itemUno">
                Pregunta uno
            </button>
        </h2>
        <div id="itemUno" class="accordion-collapse collapse show"
             data-bs-parent="#acordeonId">
            <div class="accordion-body">
                Respuesta al ítem uno.
            </div>
        </div>
    </div>
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#itemDos">
                Pregunta dos
            </button>
        </h2>
        <div id="itemDos" class="accordion-collapse collapse"
             data-bs-parent="#acordeonId">
            <div class="accordion-body">
                Respuesta al ítem dos.
            </div>
        </div>
    </div>
</div>

<!-- Sin bordes entre ítems -->
<div class="accordion accordion-flush" id="acordeonFlush">...</div>
```

---

## 18. Progress Bars

### Tamaños del kit

| Clase | Altura |
|-------|--------|
| `progress-xs` | 1px |
| `progress-sm` | 5px |
| `progress-md` | 8px |
| `progress-lg` | 12px |
| `progress-xl` | 15px |
| *(default)* | 16px |

```html
<!-- Normal -->
<div class="progress progress-sm">
    <div class="progress-bar bg-primary" style="width: 65%"></div>
</div>

<!-- Animada -->
<div class="progress">
    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 80%"></div>
</div>

<!-- Con etiqueta de valor -->
<div class="position-relative" style="padding-top: 28px">
    <div class="progress progress-md">
        <div class="progress-bar bg-warning" style="width: 45%">
            <span class="progress-value">45%</span>
        </div>
    </div>
</div>

<!-- Soft (fondo tenue automático por color del bar) -->
<div class="progress progress-soft progress-md">
    <div class="progress-bar bg-primary" style="width: 70%"></div>
</div>

<!-- Apiladas -->
<div class="progress progress-stacked">
    <div class="progress" style="width: 40%">
        <div class="progress-bar bg-primary"></div>
    </div>
    <div class="progress" style="width: 25%">
        <div class="progress-bar bg-success"></div>
    </div>
</div>
```

---

## 19. Popovers y Tooltips

### Popovers con color de marca

```html
<!-- Trigger -->
<button class="btn btn-primary"
        data-bs-toggle="popover"
        data-bs-placement="top"
        data-bs-custom-class="primary-popover"
        data-bs-title="Título"
        data-bs-content="Contenido del popover.">
    Abrir popover
</button>
```

**Clases de popover con color:** `primary-popover` · `secondary-popover` · `success-popover` · `warning-popover` · `info-popover` · `danger-popover` · `red-popover` · `yellow-popover` · `green-popover` · `teal-popover` · `cyan-popover` · `light-popover` · `dark-popover`

### Tooltips

```html
<button class="btn btn-secondary"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        title="Texto del tooltip">
    Hover sobre mí
</button>
```

---

## 20. Paginación

### Estándar

```html
<nav>
    <ul class="pagination">
        <li class="page-item disabled"><a class="page-link" href="#">Anterior</a></li>
        <li class="page-item active"><a class="page-link" href="#">1</a></li>
        <li class="page-item"><a class="page-link" href="#">2</a></li>
        <li class="page-item"><a class="page-link" href="#">Siguiente</a></li>
    </ul>
</nav>

<!-- Tamaños -->
<ul class="pagination pagination-sm">...</ul>
<ul class="pagination pagination-lg">...</ul>

<!-- Redondeada (del kit) -->
<ul class="pagination pagination-rounded">...</ul>
```

### En Laravel (paginator)

```blade
<div class="card-footer border-top">
    {{ $items->links() }}
</div>
```

---

## 21. Spinners

```html
<!-- Border spinner -->
<div class="spinner-border text-primary" role="status">
    <span class="visually-hidden">Cargando...</span>
</div>

<!-- Grow spinner -->
<div class="spinner-grow text-success" role="status">
    <span class="visually-hidden">Cargando...</span>
</div>

<!-- Tamaño pequeño -->
<div class="spinner-border spinner-border-sm text-primary" role="status"></div>
<div class="spinner-grow spinner-grow-sm text-success" role="status"></div>

<!-- En botón -->
<button class="btn btn-primary" disabled>
    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
    Guardando...
</button>
```

---

## 22. Dropdowns

```html
<div class="dropdown">
    <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
        Opciones
    </button>
    <ul class="dropdown-menu">
        <li><h6 class="dropdown-header">Sección</h6></li>
        <li><a class="dropdown-item" href="#"><i class="ri-edit-line me-2"></i>Editar</a></li>
        <li><a class="dropdown-item" href="#">Ver</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#">Eliminar</a></li>
    </ul>
</div>

<!-- Alineado a la derecha -->
<div class="dropdown">
    <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
        Más
    </button>
    <ul class="dropdown-menu dropdown-menu-end">...</ul>
</div>

<!-- Dropdown largo (para notificaciones) -->
<div class="dropdown-menu dropdown-lg dropdown-menu-end">...</div>
```

---

## 23. Offcanvas

### Offcanvas de ajustes de tema (patrón del kit)

```html
<!-- Trigger en topbar -->
<button class="topbar-button"
        data-bs-toggle="offcanvas"
        data-bs-target="#theme-settings-offcanvas">
    <i class="ri-settings-4-line fs-24"></i>
</button>

<!-- Panel -->
<div class="offcanvas offcanvas-end border-0 rounded-start-4 overflow-hidden"
     tabindex="-1" id="theme-settings-offcanvas">

    <div class="d-flex align-items-center bg-primary p-3 offcanvas-header">
        <h5 class="text-white m-0">Ajustes de tema</h5>
        <button class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body p-0">
        <div data-simplebar class="h-100">
            <div class="p-3 settings-bar">
                <!-- radios de configuración -->
            </div>
        </div>
    </div>

    <div class="offcanvas-footer border-top p-3 text-center">
        <button class="btn btn-danger w-100" id="reset-layout">Restablecer</button>
    </div>
</div>
```

### Offcanvas genérico

```html
<!-- Desde derecha (end) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasId">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">Título</h5>
        <button class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <!-- contenido -->
    </div>
</div>

<!-- Desde arriba (top) / abajo (bottom) / izquierda (start) -->
<div class="offcanvas offcanvas-top">...</div>
```

---

## 24. Utilidades del Kit

### Fondos especiales

| Clase | Efecto |
|-------|--------|
| `bg-soft-primary` | Fondo opacidad 25% del primary |
| `bg-soft-secondary` | Fondo opacidad 25% del secondary |
| `bg-soft-success` | Fondo opacidad 25% del success |
| `bg-soft-warning` | Fondo opacidad 25% del warning |
| `bg-soft-info` | Fondo opacidad 25% del info |
| `bg-ghost` | Opacity 0.4 al elemento completo |
| `bg-overlay` | Overlay absoluto al 70% de opacidad |

### Border utilities

```html
<div class="border border-dashed">...</div>        <!-- borde punteado -->
<div class="border border-primary">...</div>       <!-- borde coloreado -->
<div class="border-bottom border-dashed">...</div> <!-- solo borde inferior punteado -->
```

### Scroll

```html
<!-- SimpleBar (scroll personalizado) -->
<div data-simplebar style="max-height: 300px">
    <!-- contenido scrollable -->
</div>

<!-- Ocultar scrollbar nativo -->
<div class="scroll-hidden">...</div>
```

### Animación del footer

```html
<span class="footer-text fw-bold">Live Software</span>
<!-- Gradiente animado: primary → warning → success -->
```

### Utilidad `button-list`

```html
<!-- Agrega margen entre botones en demos o vistas de UI -->
<div class="button-list">
    <button class="btn btn-primary">A</button>
    <button class="btn btn-soft-primary">B</button>
    <button class="btn btn-outline-primary">C</button>
</div>
```

### Clases de texto adicionales

```html
<span class="text-bg-primary">Texto con fondo primary</span>
<span class="text-bg-success">Texto con fondo success</span>
<p class="text-muted fs-13">Texto secundario</p>
```

---

## 25. Íconos

El kit usa **dos sistemas** de íconos, ambos deben incluirse via `icons.min.css` y el script de Iconify.

### Remix Icons (`ri-*`)

Íconos de línea (sufijo `-line`) y de relleno (sufijo `-fill`). Son los más usados en el kit.

```html
<!-- Layout y navegación -->
<i class="ri-dashboard-2-line"></i>
<i class="ri-menu-2-line"></i>
<i class="ri-pages-line"></i>
<i class="ri-layout-line"></i>

<!-- Usuarios y seguridad -->
<i class="ri-user-add-line"></i>
<i class="ri-group-line"></i>
<i class="ri-shield-user-line"></i>
<i class="ri-shield-star-line"></i>
<i class="ri-lock-password-line"></i>

<!-- Acciones CRUD -->
<i class="ri-add-line"></i>
<i class="ri-add-circle-line"></i>
<i class="ri-edit-line"></i>
<i class="ri-delete-bin-line"></i>
<i class="ri-eye-line"></i>
<i class="ri-save-line"></i>

<!-- Estado y notificaciones -->
<i class="ri-notification-3-line"></i>
<i class="ri-checkbox-circle-line"></i>
<i class="ri-information-line"></i>
<i class="ri-inbox-line"></i>

<!-- UI general -->
<i class="ri-search-line"></i>
<i class="ri-settings-4-line"></i>
<i class="ri-arrow-right-line"></i>
<i class="ri-arrow-left-right-line"></i>
<i class="ri-fullscreen-line"></i>
<i class="ri-fullscreen-exit-line"></i>
<i class="ri-moon-line"></i>
<i class="ri-sun-line"></i>
<i class="ri-key-line"></i>
<i class="ri-calendar-line"></i>
<i class="ri-shapes-line"></i>

<!-- Negocio / módulos -->
<i class="ri-briefcase-line"></i>
<i class="ri-survey-line"></i>
<i class="ri-table-line"></i>
<i class="ri-bar-chart-line"></i>
<i class="ri-road-map-line"></i>
<i class="ri-home-office-line"></i>
<i class="ri-community-line"></i>
<i class="ri-news-line"></i>
<i class="ri-discuss-line"></i>
```

### Iconify — Solar Set

Íconos más expresivos, usar cuando se quiere énfasis o variedad.

```html
<iconify-icon icon="solar:settings-broken"  class="align-middle me-2 fs-18"></iconify-icon>
<iconify-icon icon="solar:logout-3-broken"  class="align-middle me-2 fs-18"></iconify-icon>
<iconify-icon icon="solar:user-bold"        class="align-middle fs-20"></iconify-icon>
```

### Reglas de uso

- Usar `align-middle` cuando el ícono va junto a texto en línea.
- Usar `me-1` o `me-2` para separar del texto a la derecha.
- Para íconos en topbar: `fs-24`.
- Para íconos en botones de tabla: `fs-18`.
- Para íconos decorativos en cards: `fs-20`–`fs-24`.

---

## 26. Layout de Autenticación

El layout `layouts/auth.blade.php` es independiente del layout principal y **no incluye** topbar, sidebar ni footer.

```html
<!-- login.blade.php -->
@extends('layouts.auth')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-5">

            <div class="card auth-card">
                <div class="card-body px-3 py-5">

                    <!-- Logo -->
                    <div class="mx-auto mb-4 text-center auth-logo">
                        <a href="/" class="logo-dark">
                            <img src="logo-dark.png" height="45" alt="">
                        </a>
                        <a href="/" class="logo-light">
                            <img src="logo-light.png" height="45" alt="">
                        </a>
                    </div>

                    <h2 class="fw-bold text-uppercase text-center fs-18">Inicio de sesión</h2>
                    <p class="text-muted text-center mt-1 mb-4">Descripción breve...</p>

                    <div class="px-4">
                        <form class="authentication-form" method="POST" action="{{ route('login') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="email">Correo</label>
                                <input type="email" id="email" name="email"
                                       class="form-control bg-light bg-opacity-50 border-light py-2"
                                       placeholder="correo@ejemplo.com" required autofocus>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password">Contraseña</label>
                                <input type="password" id="password" name="password"
                                       class="form-control bg-light bg-opacity-50 border-light py-2"
                                       placeholder="Contraseña" required>
                            </div>

                            <div class="mb-1 text-center d-grid">
                                <button class="btn btn-primary py-2 fw-medium" type="submit">
                                    Iniciar sesión
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            <p class="text-center text-white mb-0">
                ¿Problemas? <a href="#" class="text-reset fw-bold ms-1">Contacta soporte</a>
            </p>

        </div>
    </div>
</div>
@endsection
```

### Clases exclusivas de auth

| Clase | Descripción |
|-------|-------------|
| `auth-card` | Card de pantalla de login/registro |
| `auth-logo` | Contenedor del logo centrado |
| `authentication-form` | Formulario de autenticación |
| `logo-dark` | Logo visible en light mode |
| `logo-light` | Logo visible en dark mode |

---

## 27. Modo Oscuro

El sistema soporta dark mode completo vía `data-bs-theme="dark"` en `<html>`. Las variables CSS cambian automáticamente. No se requiere CSS adicional.

```html
<!-- Activar dark mode -->
<html data-bs-theme="dark">

<!-- Mostrar/ocultar por modo -->
<i class="ri-moon-line light-mode"></i>   <!-- solo visible en claro -->
<i class="ri-sun-line dark-mode"></i>     <!-- solo visible en oscuro -->
```

### Variables que cambian en dark mode

```css
--bs-body-bg:       #141A21
--bs-body-color:    #aab8c5
--bs-secondary-bg:  #1C252E
--bs-tertiary-bg:   #2f3943
--bs-border-color:  #29333d
--bs-main-nav-bg:   #1C252E  (siempre oscuro en dark mode)
--bs-topbar-bg:     #141A21  (siempre oscuro en dark mode)
```

### Config JS (`config.min.js`)

El archivo `assets/js/config.min.js` debe cargarse en el `<head>` antes que cualquier CSS para leer los valores almacenados en `localStorage` y aplicar los atributos `data-*` al `<html>` antes del render.

---

## 28. Convenciones Blade

### Estructura mínima de una vista

```blade
@extends('layouts.app')

@push('styles')
{{-- Solo CSS específico de esta página --}}
@endpush

@section('page_title', 'Título de la Página')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Sección</li>
@endsection

@section('content')

    {{-- Alertas flash (siempre al inicio del content) --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Contenido principal --}}
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                ...
            </div>
        </div>
    </div>

@endsection

@push('scripts')
{{-- Solo JS específico de esta página --}}
@endpush
```

### Assets incluidos en `layouts/app.blade.php` (no repetir)

```html
<!-- Head -->
<link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
<script src="{{ asset('assets/js/config.min.js') }}"></script>

<!-- Body (al final) -->
<script src="{{ asset('assets/js/vendor.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
```

### Librerías vendor disponibles (en `vendor.min.css` / `vendor.js`)

SimpleBar · ApexCharts · Bootstrap 5 · Choices.js · Flatpickr · Dropzone · Dragula · noUiSlider · SweetAlert2 · Swiper · Quill · GridJS · FullCalendar · Prism.js · Waves

---

## 29. Patrones Reutilizables

### Celda de tabla: avatar + nombre

```html
<td>
    <div class="d-flex align-items-center gap-2">
        <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
            <span class="text-primary fw-semibold">{{ strtoupper(substr($item->name, 0, 1)) }}</span>
        </div>
        <div>
            <span class="text-dark fw-medium fs-15">{{ $item->name }}</span>
        </div>
    </div>
</td>
```

### Celda de tabla: múltiples badges

```html
<td>
    @foreach ($item->roles as $role)
        <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $role->name }}</span>
    @endforeach
</td>
```

### Celda de tabla: acciones

```html
<td>
    <div class="d-flex gap-2">
        <button class="btn btn-soft-primary btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalEdit{{ $item->id }}">
            <i class="ri-edit-line align-middle fs-18"></i>
        </button>
        <form method="POST" action="{{ route('items.destroy', $item) }}" class="d-inline"
              onsubmit="return confirm('¿Eliminar este ítem?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-soft-danger btn-sm">
                <i class="ri-delete-bin-line align-middle fs-18"></i>
            </button>
        </form>
    </div>
</td>
```

### Estado vacío de tabla

```html
<tr>
    <td colspan="N" class="text-center text-muted py-4">
        <i class="ri-inbox-line fs-24 d-block mb-1"></i>
        Sin registros
    </td>
</tr>
```

### Indicadores semánticos de acción

```html
<span class="text-success fw-medium fs-13">
    <i class="ri-add-circle-line me-1"></i>Creación
</span>
<span class="text-warning fw-medium fs-13">
    <i class="ri-edit-line me-1"></i>Actualización
</span>
<span class="text-danger fw-medium fs-13">
    <i class="ri-delete-bin-line me-1"></i>Eliminación
</span>
```

### Header de card con conteo

```html
<div class="card-header border-bottom d-flex justify-content-between align-items-center">
    <h4 class="card-title mb-0">Listado</h4>
    <span class="text-muted fs-13">{{ $items->total() }} registros</span>
</div>
```

### Header de card con botón de creación

```html
<div class="card-header d-flex justify-content-between align-items-center border-bottom">
    <h4 class="card-title mb-0">Módulo</h4>
    <button type="button" class="btn btn-sm btn-primary"
            data-bs-toggle="modal" data-bs-target="#modalCreate">
        <i class="ri-add-line me-1"></i> Nuevo
    </button>
</div>
```

### Layout dos columnas: perfil + formulario

```html
<div class="row g-3">
    <div class="col-xl-4 col-lg-5">
        <!-- card lateral de perfil -->
    </div>
    <div class="col-xl-8 col-lg-7">
        <!-- cards de formulario -->
    </div>
</div>
```

### Notificación en dropdown del topbar

```html
<a href="#" class="dropdown-item py-3 border-bottom text-wrap">
    <div class="d-flex align-items-start gap-2">
        <div class="avatar-xs bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1">
            <i class="ri-add-line text-success fs-14"></i>
        </div>
        <div class="flex-grow-1">
            <p class="mb-0 fs-13">
                <span class="fw-medium">Usuario</span> realizó una acción.
            </p>
            <span class="text-muted fs-11">Hace 5 minutos</span>
        </div>
    </div>
</a>
```

### SimpleBar en dropdown/panel

```html
<div id="lista" data-simplebar style="max-height: 280px;">
    <!-- ítems scrollables -->
</div>
```

### Gravatar como avatar

```php
// En controlador o vista
$hash = md5(strtolower(trim($user->email)));
$url  = "https://www.gravatar.com/avatar/{$hash}?s=64&d=identicon";
```

```html
<img class="rounded-circle" width="32" height="32" src="{{ $gravatarUrl }}" alt="{{ $user->name }}">
```
