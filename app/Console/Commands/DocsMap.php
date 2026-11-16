<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Estoria SDD · docs:map
 *
 * Lee el código real del proyecto (modelos, esquema, relaciones, rutas, menú,
 * controllers) y lo exporta a docs/architecture/_code.json para el visor de
 * arquitectura. Con --check compara contra los specs de docs/specs/ y revisa
 * que los manuales de docs/manual/ estén al día.
 *
 * Solo lectura: no escribe en la base de datos ni modifica código.
 * Requiere la base de datos local con las migraciones corridas.
 *
 * Forma parte del kit Estoria SDD. No lo modifiques por proyecto:
 * los ajustes se hacen en el kit y se vuelven a copiar.
 */
class DocsMap extends Command
{
    public const VERSION = '0.5.0';

    protected $signature = 'docs:map
        {--check : Compara el código contra los specs y termina con error si hay diferencias; revisa los manuales}
        {--strict : Con --check, también falla por modelos sin spec y por manuales atrasados}
        {--output=docs/architecture/_code.json : Ruta del JSON de salida}
        {--no-write : No escribe el JSON (útil junto con --check)}
        {--suggest-architecture : Imprime una sección `architecture` sugerida para docs/specs/_project.json (no escribe nada)}';

    protected $description = 'Exporta la arquitectura real del proyecto (modelos, relaciones, rutas y módulos) para el visor de Estoria SDD';

    /** Columnas que Laravel agrega solo y no se comparan contra los specs. */
    private const AUTO_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    private const RELATION_CALL = '/\$this->(hasOne|hasMany|belongsTo|belongsToMany|hasOneThrough|hasManyThrough|morphOne|morphMany|morphTo|morphToMany|morphedByMany)\s*\(/';

    /** Clave de módulo funcional: snake_case en minúsculas, sin acentos. */
    private const ARCH_KEY = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/';

    private array $warnings = [];

    /** Cómo se asignó el módulo técnico de cada modelo: controller, spec o parent. */
    private array $technicalVia = [];

    /** Motivo por el que un modelo quedó sin módulo funcional. */
    private array $unresolved = [];

    public function handle(): int
    {
        $project = $this->readJson(base_path('docs/specs/_project.json')) ?? [];
        $specs = $this->readSpecs();
        // Specs reemplazados por un cambio ya implementado: historial. Cuentan para saber qué modelos tienen spec
        // y a qué módulo pertenecen, pero no se comparan columna por columna contra el código.
        $archived = $this->readSpecs('docs/specs/_archive');

        $models = $this->discoverModels();
        $routes = $this->discoverRoutes();
        $nav = $this->discoverNav($project['nav_partial'] ?? null);
        $controllers = $this->inspectControllers($routes);
        $routes = $this->inspectActions($routes, $models);
        $modules = $this->buildModules($models, $routes, $controllers, $nav, array_merge($specs, $archived));

        [$architecture, $errors] = $this->buildArchitecture($project['architecture'] ?? null, $models, $modules);
        if ($errors) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }
            $this->components->error('Corrige la sección `architecture` de docs/specs/_project.json. No se escribió el mapa.');

            return self::FAILURE;
        }

        if ($this->option('suggest-architecture')) {
            $this->printSuggestion($project['architecture'] ?? null, $models, $modules, $architecture);

            return self::SUCCESS;
        }

        $manuals = $this->buildManuals($modules, $architecture, $specs);

        $map = [
            'generator' => 'estoria/docs:map '.self::VERSION,
            'generated_at' => now()->toIso8601String(),
            'project' => $project['project'] ?? config('app.name'),
            'laravel' => app()->version(),
            'packages' => $this->installedPackages(),
            'modules' => array_values($modules),
            'models' => array_values($models),
            'architecture' => $architecture,
            'services' => $this->services($models, $controllers),
            'specs' => array_map(fn ($s) => [
                'id' => $s['meta']['id'] ?? null,
                'type' => $s['type'] ?? null,
                'title' => $s['meta']['title'] ?? null,
                'module' => $s['meta']['module'] ?? null,
                'module_key' => $s['meta']['module_key'] ?? null,
                'extends' => ($s['meta']['extends'] ?? '') ?: null,
                'status' => $s['meta']['status'] ?? null,
                'archived' => str_contains($s['_file'], '/_archive/'),
                'file' => $s['_file'],
            ], array_merge($specs, $archived)),
            'manuals' => $manuals,
            'warnings' => $this->warnings,
        ];

        if (! $this->option('no-write')) {
            $path = base_path($this->option('output'));
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $this->components->info('Arquitectura exportada a '.$this->option('output'));
        }

        $this->printSummary($modules, $models, $architecture);

        if (! $this->option('check')) {
            return self::SUCCESS;
        }
        $specsResult = $this->check($specs, $models, $archived);
        $manualsResult = $this->checkManuals($manuals);

        return $specsResult === self::SUCCESS && $manualsResult === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    /* ───────────────────────── Modelos ───────────────────────── */

    private function discoverModels(): array
    {
        $dir = app_path('Models');
        if (! File::isDirectory($dir)) {
            $this->warn('No existe app/Models.');

            return [];
        }

        $models = [];
        foreach (File::allFiles($dir) as $file) {
            $class = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            if (! class_exists($class)) {
                continue;
            }
            $ref = new ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
                continue;
            }
            $models[$class] = $this->inspectModel($ref);
        }
        ksort($models);

        return $models;
    }

    private function inspectModel(ReflectionClass $ref): array
    {
        $class = $ref->getName();
        /** @var Model $instance */
        $instance = $ref->newInstanceWithoutConstructor();
        try {
            $instance = new $class;
        } catch (Throwable) {
            // algunos modelos requieren argumentos; se usa la instancia sin constructor
        }

        $table = $instance->getTable();
        $traits = array_map('class_basename', array_values(class_uses_recursive($class)));
        $hasTable = Schema::hasTable($table);
        if (! $hasTable) {
            $this->warning('table_missing', class_basename($class), "La tabla `{$table}` no existe. ¿Faltan migraciones por correr?");
        }

        return [
            'class' => $class,
            'name' => class_basename($class),
            'file' => Str::after($ref->getFileName(), base_path().DIRECTORY_SEPARATOR),
            'table' => $table,
            'soft_deletes' => in_array('SoftDeletes', $traits, true),
            'traits' => $traits,
            'fillable' => $instance->getFillable(),
            'casts' => $instance->getCasts(),
            'columns' => $hasTable ? $this->columns($table) : [],
            'foreign_keys' => $hasTable ? $this->foreignKeys($table) : [],
            'relations' => $this->relations($ref, $instance),
            'module' => null,
        ];
    }

    private function columns(string $table): array
    {
        return array_map(fn ($c) => [
            'name' => $c['name'],
            'type' => $c['type_name'],
            'full_type' => $c['type'],
            'nullable' => (bool) $c['nullable'],
            'default' => $c['default'],
            'auto' => in_array($c['name'], self::AUTO_COLUMNS, true),
        ], Schema::getColumns($table));
    }

    private function foreignKeys(string $table): array
    {
        return array_map(fn ($fk) => [
            'columns' => $fk['columns'],
            'foreign_table' => $fk['foreign_table'],
            'foreign_columns' => $fk['foreign_columns'],
            'on_delete' => $fk['on_delete'] ?? null,
        ], Schema::getForeignKeys($table));
    }

    /**
     * Relaciones declaradas en el modelo (o en traits del proyecto).
     * Las que vienen de traits de paquetes (HasRoles, Notifiable…) se omiten:
     * quedan registradas en `traits` y en `services`.
     */
    private function relations(ReflectionClass $ref, Model $instance): array
    {
        $found = [];
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }
            $file = (string) $method->getFileName();
            if ($file === '' || str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $type = $method->getReturnType();
            $byType = $type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_a($type->getName(), Relation::class, true);
            $byScan = false;
            if (! $byType && ($type === null)) {
                $byScan = (bool) preg_match(self::RELATION_CALL, $this->methodSource($method));
            }
            if (! $byType && ! $byScan) {
                continue;
            }

            try {
                $relation = $method->invoke($instance);
            } catch (Throwable $e) {
                $this->warning('relation_error', class_basename($ref->getName()).'::'.$method->getName(), 'No se pudo leer la relación: '.$e->getMessage());

                continue;
            }
            if (! $relation instanceof Relation) {
                continue;
            }
            if ($byScan) {
                $this->warning('relation_without_return_type', class_basename($ref->getName()).'::'.$method->getName(), 'Relación sin return type. Agrega `: '.class_basename($relation).'` para que se detecte por tipo.');
            }
            $found[] = $this->describeRelation($method->getName(), $relation, $byType ? 'return_type' : 'source_scan');
        }

        return $found;
    }

    private function describeRelation(string $name, Relation $relation, string $detectedBy): array
    {
        $related = get_class($relation->getRelated());
        $data = [
            'name' => $name,
            'type' => class_basename($relation),
            'related' => class_basename($related),
            'related_class' => $related,
            'external' => ! str_starts_with($related, 'App\\'),
            'detected_by' => $detectedBy,
        ];
        foreach ([
            'foreign_key' => 'getForeignKeyName',
            'owner_key' => 'getOwnerKeyName',
            'local_key' => 'getLocalKeyName',
            'pivot_table' => 'getTable',
            'morph_type' => 'getMorphType',
        ] as $key => $getter) {
            if (method_exists($relation, $getter)) {
                try {
                    $data[$key] = $relation->{$getter}();
                } catch (Throwable) {
                }
            }
        }
        if (! str_contains($data['type'], 'ToMany') && ! str_contains($data['type'], 'Through')) {
            unset($data['pivot_table']);
        }
        if ($data['type'] === 'MorphTo') {
            $data['related'] = null;
            $data['related_class'] = null;
        }

        return $data;
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $lines = @file($method->getFileName());
        if (! $lines) {
            return '';
        }

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    /* ───────────────────────── Rutas y controllers ───────────────────────── */

    private function discoverRoutes(): array
    {
        $routes = [];
        /** @var LaravelRoute $route */
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_starts_with($action, 'App\\Http\\Controllers\\')) {
                continue;
            }
            [$controller, $method] = array_pad(explode('@', $action), 2, '__invoke');
            $middleware = array_values(array_map(fn ($m) => is_string($m) ? $m : get_class($m), $route->gatherMiddleware()));
            $routes[] = [
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => '/'.ltrim($route->uri(), '/'),
                'name' => $route->getName(),
                'controller' => $controller,
                'action' => $method,
                'middleware' => $middleware,
                'public' => ! collect($middleware)->contains(fn ($m) => $m === 'auth' || str_starts_with($m, 'auth:')),
            ];
        }

        return $routes;
    }

    private function inspectControllers(array $routes): array
    {
        $controllers = [];
        $classes = collect($routes)->pluck('controller')->unique();
        foreach (File::isDirectory(app_path('Http/Controllers')) ? File::allFiles(app_path('Http/Controllers')) : [] as $file) {
            $classes->push('App\\Http\\Controllers\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()));
        }

        foreach ($classes->unique() as $class) {
            if (! class_exists($class) || $class === 'App\\Http\\Controllers\\Controller') {
                continue;
            }
            $ref = new ReflectionClass($class);
            if ($ref->isAbstract()) {
                continue;
            }
            $source = (string) @file_get_contents($ref->getFileName());

            $injects = [];
            foreach ($ref->getConstructor()?->getParameters() ?? [] as $param) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    $injects[] = $type->getName();
                }
            }

            preg_match_all("/Storage::disk\\(\\s*['\"]([\\w-]+)['\"]\\s*\\)/", $source, $disks);
            preg_match_all("/['\"]type['\"]\\s*=>\\s*['\"](\\w+)['\"]/", $source, $audited);

            // Modelos que el controller recibe por route model binding
            $bound = [];
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getParameters() as $param) {
                    $type = $param->getType();
                    if ($type instanceof ReflectionNamedType && is_a($type->getName(), Model::class, true)) {
                        $bound[] = $type->getName();
                    }
                }
            }

            $controllers[$class] = [
                'class' => $class,
                'name' => class_basename($class),
                'file' => Str::after($ref->getFileName(), base_path().DIRECTORY_SEPARATOR),
                'routed' => collect($routes)->contains('controller', $class),
                'notifies' => in_array('App\\Services\\NotificationService', $injects, true),
                'audited_types' => array_values(array_unique($audited[1])),
                'disks' => array_values(array_unique($disks[1])),
                'bound_models' => array_values(array_unique($bound)),
            ];
        }

        return $controllers;
    }

    /* ───────────────────────── Acciones y huellas (manuales) ───────────────────────── */

    /**
     * Agrega a cada ruta la vista que regresa, los componentes Livewire que monta esa vista
     * y una huella (fp) de lo que el usuario ve o puede hacer en esa acción: el método del
     * controller, su vista con sus @include, los métodos de modelos que llama y el middleware.
     * Los manuales guardan la huella en cada tarea; si cambia, la tarea se marca para revisar.
     */
    private function inspectActions(array $routes, array $models): array
    {
        $modelClasses = collect($models)->mapWithKeys(fn ($m, $class) => [$m['name'] => $class])->all();
        $dynamic = [];
        foreach ($routes as &$route) {
            $route += ['view' => null, 'fp' => null, 'livewire' => []];
            if (! class_exists($route['controller']) || ! method_exists($route['controller'], $route['action'])) {
                continue;
            }
            $source = $this->methodSource(new ReflectionMethod($route['controller'], $route['action']));
            $bundle = '';
            if (preg_match('/\bview\(\s*([\'"])([\w.\-:\/]+)\1/', $source, $m)) {
                $route['view'] = $m[2];
                $bundle = $this->viewBundle($m[2]);
            } elseif (preg_match('/\bview\(/', $source)) {
                $dynamic[class_basename($route['controller']).'@'.$route['action']] = true;
            }
            foreach ($this->livewireTags($bundle) as $component) {
                $class = 'App\\Livewire\\'.collect(explode('.', $component))->map(fn ($p) => Str::studly($p))->implode('\\');
                if (class_exists($class)) {
                    $route['livewire'][] = ['component' => $component, 'class' => Str::after($class, 'App\\Livewire\\'), 'actions' => $this->livewireActions($class, $modelClasses)];
                }
            }
            $route['fp'] = $this->fingerprint($source.$bundle.$this->modelMethods($source, $modelClasses).implode(',', $route['middleware']));
        }
        unset($route);

        // store y update guardan lo que se captura en el formulario de create y edit: su huella incluye ese formulario
        $byName = collect($routes)->filter(fn ($r) => $r['name'])->keyBy('name');
        foreach ($routes as &$route) {
            foreach (['store' => 'create', 'update' => 'edit'] as $action => $form) {
                $sibling = $route['name'] && str_ends_with($route['name'], '.'.$action) ? $byName[Str::beforeLast($route['name'], '.').'.'.$form] ?? null : null;
                if ($sibling && $route['fp'] && $sibling['fp']) {
                    $route['fp'] = $this->fingerprint($route['fp'].$sibling['fp']);
                }
            }
        }
        unset($route);

        foreach (array_keys($dynamic) as $action) {
            $this->warning('dynamic_view', $action, 'La vista se arma en tiempo de ejecución; la huella de la acción no incluye su contenido. Usa un nombre de vista fijo si el manual debe detectar cambios en la pantalla.');
        }

        return $routes;
    }

    /** Huella corta y estable: ignora cambios de espacios y saltos de línea. */
    private function fingerprint(string $text): string
    {
        return substr(sha1(trim(preg_replace('/\s+/', ' ', $text))), 0, 10);
    }

    /** Contenido de una vista Blade y de sus @include (hasta 3 niveles). */
    private function viewBundle(string $name, int $depth = 0, array &$seen = []): string
    {
        if ($depth > 3 || isset($seen[$name])) {
            return '';
        }
        $seen[$name] = true;
        try {
            $source = (string) @file_get_contents(view()->getFinder()->find($name));
        } catch (Throwable) {
            return '';
        }
        preg_match_all('/@include(?:If)?\(\s*[\'"]([\w.\-:\/]+)[\'"]/', $source, $includes);
        foreach (array_unique($includes[1]) as $partial) {
            $source .= $this->viewBundle($partial, $depth + 1, $seen);
        }

        return $source;
    }

    /** Componentes Livewire montados en una vista: <livewire:x.y> y @livewire('x.y'). */
    private function livewireTags(string $blade): array
    {
        preg_match_all('/<livewire:([\w.\-]+)|@livewire\(\s*[\'"]([\w.\-]+)[\'"]/', $blade, $m);

        return array_values(array_unique(array_filter(array_merge($m[1], $m[2]))));
    }

    /**
     * Acciones de un componente Livewire (métodos públicos propios, sin los del ciclo de vida)
     * con su huella: el método, la vista del componente y los métodos de modelos que llama.
     */
    private function livewireActions(string $class, array $modelClasses): array
    {
        $ref = new ReflectionClass($class);
        $bundle = '';
        if ($ref->hasMethod('render') && preg_match('/\bview\(\s*([\'"])([\w.\-:\/]+)\1/', $this->methodSource($ref->getMethod('render')), $m)) {
            $bundle = $this->viewBundle($m[2]);
        }
        $actions = [];
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Los métodos que llegan por traits (WithFileUploads, WithPagination) no son acciones del usuario
            if ($method->getDeclaringClass()->getName() !== $class || $method->getFileName() !== $ref->getFileName()
                || $method->isStatic() || str_starts_with($method->name, '_')
                || preg_match('/^(mount|render|boot|booted|hydrate|dehydrate|updating|updated|rendering|rendered|exception|placeholder)/', $method->name)) {
                continue;
            }
            $source = $this->methodSource($method);
            $actions[$method->name] = $this->fingerprint($source.$bundle.$this->modelMethods($source, $modelClasses));
        }
        ksort($actions);

        return $actions;
    }

    /**
     * Código de los métodos de modelos que usa una acción (Article::formRules(), $article->canTransitionTo()):
     * ahí suelen vivir reglas de validación y permisos que cambian lo que el usuario puede hacer.
     */
    private function modelMethods(string $source, array $modelClasses): string
    {
        preg_match_all('/(?:::|->)(\w+)\s*\(/', $source, $calls);
        $calls = array_unique($calls[1]);
        $out = '';
        foreach ($modelClasses as $name => $class) {
            if (! preg_match('/\b'.preg_quote($name, '/').'\b/', $source)) {
                continue;
            }
            $ref = new ReflectionClass($class);
            foreach ($calls as $call) {
                if ($ref->hasMethod($call) && $ref->getMethod($call)->getDeclaringClass()->getName() === $class) {
                    $out .= $this->methodSource($ref->getMethod($call));
                }
            }
        }

        return $out;
    }

    /* ───────────────────────── Menú de navegación ───────────────────────── */

    /**
     * Lee el parcial del menú y devuelve, por nombre de ruta, su área
     * (menu-title) y la etiqueta visible.
     */
    private function discoverNav(?string $configured): array
    {
        $file = $configured ? base_path($configured) : null;
        if (! $file || ! File::exists($file)) {
            $file = collect(File::isDirectory(resource_path('views/layouts')) ? File::allFiles(resource_path('views/layouts')) : [])
                ->map(fn ($f) => $f->getPathname())
                ->filter(fn ($p) => str_ends_with($p, '.blade.php'))
                ->sortByDesc(fn ($p) => substr_count((string) file_get_contents($p), "route('"))
                ->first();
        }
        if (! $file || ! File::exists($file)) {
            $this->warning('nav_not_found', 'menú', 'No se encontró el parcial del menú. Define `nav_partial` en docs/specs/_project.json.');

            return ['file' => null, 'items' => []];
        }

        $html = (string) file_get_contents($file);
        preg_match_all(
            '/<li[^>]*class="[^"]*menu-title[^"]*"[^>]*>(?<title>.*?)<\/li>'
            .'|href="\{\{\s*route\(\s*[\'"](?<route>[^\'"]+)[\'"][^"]*"(?:[^<>]|->|=>)*>(?<text>[^<]*)'
            .'|<span[^>]*class="[^"]*nav-text[^"]*"[^>]*>(?<navtext>.*?)<\/span>/s',
            $html, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
        );

        $items = [];
        $area = null;
        $group = null;
        $pending = null; // ruta de un nav-link que espera su etiqueta (nav-text)
        foreach ($matches as $m) {
            if ($m['title'] !== null) {
                $area = trim(strip_tags($m['title']));
                $group = $pending = null;
            } elseif ($m['route'] !== null) {
                $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $m['text'])));
                if (isset($items[$m['route']]) && $items[$m['route']]['label'] !== null) {
                    continue; // la primera aparición con etiqueta gana (ej. el logo no cuenta)
                }
                if ($text !== '') { // sub-nav-link: la etiqueta es el texto del enlace
                    $items[$m['route']] = ['area' => $area, 'group' => $group, 'label' => $text];
                    $pending = null;
                } else {            // nav-link: la etiqueta llega en el siguiente nav-text
                    $items[$m['route']] = ['area' => $area, 'group' => null, 'label' => null];
                    $pending = $m['route'];
                    $group = null;
                }
            } elseif ($m['navtext'] !== null) {
                $text = trim(strip_tags($m['navtext']));
                if ($pending) {
                    $items[$pending]['label'] = $text;
                    $pending = null;
                } else {            // nav-text de un menú desplegable: agrupa los sub-enlaces
                    $group = $text;
                }
            }
        }

        return ['file' => Str::after($file, base_path().DIRECTORY_SEPARATOR), 'items' => $items];
    }

    /* ───────────────────────── Módulos ───────────────────────── */

    private function buildModules(array &$models, array $routes, array $controllers, array $nav, array $specs): array
    {
        $modules = [];
        $byController = collect($routes)->groupBy('controller');

        foreach ($byController as $controller => $controllerRoutes) {
            $prefix = $controllerRoutes->pluck('name')->filter()
                ->map(fn ($n) => Str::before($n, '.'))
                ->countBy()->sortDesc()->keys()->first()
                ?? trim(Str::before(ltrim($controllerRoutes->first()['uri'], '/'), '/'), '/');
            if ($prefix === '' || $prefix === null) {
                $prefix = Str::snake(Str::beforeLast(class_basename($controller), 'Controller'));
            }

            $modules[$prefix] ??= [
                'key' => $prefix,
                'name' => null,
                'area' => null,
                'nav_label' => null,
                'nav_group' => null,
                'spec_name' => null,
                'controllers' => [],
                'models' => [],
                'routes' => [],
                'public' => false,
                'notifies' => false,
                'disks' => [],
                'specs' => [],
            ];
            $module = &$modules[$prefix];
            $module['controllers'][] = class_basename($controller);
            $module['routes'] = array_merge($module['routes'], $controllerRoutes->map(fn ($r) => [
                'methods' => $r['methods'], 'uri' => $r['uri'], 'name' => $r['name'], 'action' => class_basename($r['controller']).'@'.$r['action'],
                'view' => $r['view'] ?? null, 'fp' => $r['fp'] ?? null, 'livewire' => $r['livewire'] ?? [],
            ])->all());
            $module['public'] = $module['public'] || $controllerRoutes->every('public');

            $info = $controllers[$controller] ?? null;
            if ($info) {
                $module['notifies'] = $module['notifies'] || $info['notifies'];
                $module['disks'] = array_values(array_unique(array_merge($module['disks'], $info['disks'])));
            }

            $model = $this->modelForController($controller, $info, $models);
            if ($model && ! in_array(class_basename($model), $module['models'], true)) {
                $module['models'][] = class_basename($model);
                if ($models[$model]['module'] === null) {
                    $models[$model]['module'] = $prefix;
                    $this->technicalVia[$model] = 'controller';
                }
            }

            foreach ($nav['items'] as $routeName => $item) {
                if (Str::before($routeName, '.') === $prefix) {
                    $module['area'] ??= $item['area'];
                    $module['nav_label'] ??= $item['group'] && $this->isGenericLabel($item['label']) ? $item['group'] : $item['label'];
                    $module['nav_group'] ??= $item['group'];
                }
            }
            unset($module);
        }

        // Specs: nombre del módulo y modelos secundarios que no tienen controller propio
        foreach ($specs as $spec) {
            $id = $spec['meta']['id'] ?? null;
            $specModels = collect($spec['models'] ?? [])->pluck('name')->filter()->all();
            $target = $this->extFieldsModel($spec, $models) ?? ($spec['v']['target'] ?? null);
            $moduleKey = $spec['meta']['module_key'] ?? null;
            $key = ($moduleKey && isset($modules[$moduleKey]) ? $moduleKey : null)
                ?? collect($modules)->first(fn ($m) => array_intersect($m['models'], $specModels) || ($target && in_array($target, $m['models'], true)))['key'] ?? null;
            if (! $key) {
                continue;
            }
            $modules[$key]['specs'][] = $id;
            if (($spec['type'] ?? '') === 'new' && ! empty($spec['meta']['module'])) {
                $modules[$key]['spec_name'] ??= $spec['meta']['module'];
            }
            foreach ($specModels as $name) {
                $class = 'App\\Models\\'.$name;
                if (isset($models[$class]) && $models[$class]['module'] === null) {
                    $models[$class]['module'] = $key;
                    $this->technicalVia[$class] = 'spec';
                    $modules[$key]['models'][] = $name;
                }
            }
        }

        // Modelos sin módulo: se asignan al módulo de su padre (belongsTo) si es único.
        // Los que no se resuelven aquí lo intentan en la vista funcional (buildArchitecture),
        // que es la que emite el aviso model_without_module.
        foreach ($models as $class => $model) {
            if ($model['module'] !== null) {
                continue;
            }
            $parents = collect($model['relations'])->where('type', 'BelongsTo')->pluck('related')->unique()
                ->map(fn ($name) => $models['App\\Models\\'.$name]['module'] ?? null)->filter()->unique();
            if ($parents->count() === 1) {
                $models[$class]['module'] = $parents->first();
                $this->technicalVia[$class] = 'parent';
                $modules[$parents->first()]['models'][] = $model['name'];
            }
        }

        foreach ($modules as &$module) {
            // Nombre visible: el del menú (lo que ve el usuario), luego el del spec
            $module['name'] = $module['nav_label'] ?? $module['spec_name'] ?? Str::headline($module['key']);
            $module['models'] = array_values(array_unique($module['models']));
            $module['specs'] = array_values(array_unique($module['specs']));
            $module['depends_on'] = $this->dependencies($module, $models);
        }
        unset($module);

        return $modules;
    }

    private function isGenericLabel(?string $label): bool
    {
        return $label === null || in_array(Str::lower(Str::ascii($label)), ['listado', 'lista', 'ver todos', 'todos', 'inicio', 'general'], true);
    }

    private function modelForController(string $controller, ?array $info, array $models): ?string
    {
        $guess = 'App\\Models\\'.Str::beforeLast(class_basename($controller), 'Controller');
        if (isset($models[$guess])) {
            return $guess;
        }
        $bound = array_values(array_filter($info['bound_models'] ?? [], fn ($m) => isset($models[$m])));

        return count(array_unique($bound)) === 1 ? $bound[0] : null;
    }

    private function dependencies(array $module, array $models): array
    {
        $deps = [];
        foreach ($module['models'] as $name) {
            foreach ($models['App\\Models\\'.$name]['relations'] ?? [] as $relation) {
                if (! $this->isDependency($relation, $name, $models)) {
                    continue;
                }
                $target = $models['App\\Models\\'.$relation['related']]['module'] ?? null;
                if ($target && $target !== $module['key']) {
                    $deps[$target][] = $name.'.'.$relation['name'].' ('.$relation['type'].')';
                }
            }
        }

        return collect($deps)->map(fn ($via, $key) => ['module' => $key, 'via' => array_values(array_unique($via))])->values()->all();
    }

    /**
     * Depende de otro módulo quien guarda la llave foránea (belongsTo) y quien declara un
     * belongsToMany. Si ambos modelos declaran el belongsToMany, cuenta solo desde el de nombre
     * menor, para no dibujar una dependencia de ida y vuelta.
     */
    private function isDependency(array $relation, string $from, array $models): bool
    {
        if ($relation['external'] || ! $relation['related']) {
            return false;
        }
        if ($relation['type'] === 'BelongsTo') {
            return true;
        }
        if ($relation['type'] !== 'BelongsToMany') {
            return false;
        }
        $inverse = collect($models['App\\Models\\'.$relation['related']]['relations'] ?? [])
            ->contains(fn ($r) => $r['type'] === 'BelongsToMany' && $r['related'] === $from);

        return ! $inverse || strcmp($from, $relation['related']) < 0;
    }

    /* ───────────────────────── Pertenencia funcional ───────────────────────── */

    /**
     * Agrupa los modelos en módulos de negocio. Orden de resolución por modelo:
     *   1. declared   → listado en `architecture.modules.*.models` de _project.json
     *   2. technical  → su módulo técnico (controller o spec), traducido por `from`
     *   3. prefix     → hereda del padre belongsTo cuyo nombre es prefijo del suyo
     *      inherited  → hereda si sus padres belongsTo (sin `shared`) están en un solo módulo
     *   4. unresolved → module null y aviso model_without_module con el motivo
     * `area` (opcional) agrupa el módulo en el visor; si falta, el visor usa la de sus módulos técnicos.
     * El paso 3 se repite por rondas hasta que no cambia nada, así que el orden de
     * descubrimiento no influye y nunca se elige entre módulos distintos.
     *
     * @return array{0: ?array, 1: list<string>} [bloque architecture, errores de configuración]
     */
    private function buildArchitecture(mixed $config, array $models, array $modules): array
    {
        $errors = [];
        $hasConfig = $config !== null;
        if ($hasConfig && (! is_array($config) || ($config !== [] && array_is_list($config)))) {
            return [null, ['`architecture` debe ser un objeto con `shared` y `modules`.']];
        }
        $config ??= [];
        foreach (array_diff(array_keys($config), ['shared', 'modules']) as $field) {
            $errors[] = "`architecture.{$field}` no es un campo conocido (usa `shared` y `modules`).";
        }

        $byName = [];
        foreach ($models as $class => $model) {
            if (isset($byName[$model['name']])) {
                $errors[] = "Hay dos modelos llamados {$model['name']} ({$byName[$model['name']]} y {$class}); el mapa los identifica por nombre.";
            }
            $byName[$model['name']] ??= $class;
        }
        ksort($byName);

        $strings = function (mixed $value, string $where) use (&$errors): array {
            if ($value === null) {
                return [];
            }
            if (! is_array($value) || ! array_is_list($value) || array_filter($value, fn ($v) => ! is_string($v) || trim($v) === '')) {
                $errors[] = "{$where} debe ser una lista de textos entre corchetes, aunque tenga uno solo (ej. [\"stocks\"]).";

                return [];
            }

            return array_values(array_unique(array_map('trim', $value)));
        };

        $shared = $strings($config['shared'] ?? null, '`architecture.shared`');
        foreach ($shared as $name) {
            if (! isset($byName[$name])) {
                $errors[] = "`architecture.shared`: el modelo {$name} no existe en app/Models.";
            }
        }
        sort($shared);

        $declared = $config['modules'] ?? [];
        if (! is_array($declared) || ($declared !== [] && array_is_list($declared))) {
            $errors[] = '`architecture.modules` debe ser un objeto { "clave": { "name", "from", "models" } }.';
            $declared = [];
        }

        $functional = []; // clave funcional => módulo
        $claims = [];     // módulo técnico => clave funcional que lo agrupa
        $explicit = [];   // modelo => clave funcional declarada
        foreach ($declared as $key => $def) {
            $where = "architecture.modules.{$key}";
            if (! is_string($key) || ! preg_match(self::ARCH_KEY, $key)) {
                $errors[] = "`{$where}`: la clave debe ir en snake_case, minúsculas y sin acentos (ej. ordenes_de_compra).";

                continue;
            }
            if (! is_array($def) || ($def !== [] && array_is_list($def))) {
                $errors[] = "`{$where}` debe ser un objeto.";

                continue;
            }
            foreach (array_diff(array_keys($def), ['name', 'area', 'from', 'models']) as $field) {
                $errors[] = "`{$where}.{$field}` no es un campo conocido (usa `name`, `area`, `from` y `models`).";
            }
            [$name, $area] = [$def['name'] ?? null, $def['area'] ?? null];
            if ($name !== null && (! is_string($name) || trim($name) === '')) {
                $errors[] = "`{$where}.name` debe ser un texto no vacío.";
                $name = null;
            }
            if ($area !== null && (! is_string($area) || trim($area) === '')) {
                $errors[] = "`{$where}.area` debe ser un texto no vacío.";
                $area = null;
            }

            $from = $strings($def['from'] ?? null, "`{$where}.from`");
            foreach ($from as $technical) {
                if (! isset($modules[$technical])) {
                    $errors[] = "`{$where}.from`: el módulo técnico `{$technical}` no existe (ver `modules[].key` en _code.json).";
                }
            }
            if (isset($modules[$key]) && ! in_array($key, $from, true)) {
                $from[] = $key; // un módulo funcional con la misma clave que uno técnico lo agrupa
            }
            sort($from);
            foreach ($from as $technical) {
                if (isset($claims[$technical]) && $claims[$technical] !== $key) {
                    $errors[] = "El módulo técnico `{$technical}` lo agrupan `{$claims[$technical]}` y `{$key}`; debe estar en uno solo.";
                }
                $claims[$technical] ??= $key;
            }

            foreach ($strings($def['models'] ?? null, "`{$where}.models`") as $model) {
                if (! isset($byName[$model])) {
                    $errors[] = "`{$where}.models`: el modelo {$model} no existe en app/Models.";

                    continue;
                }
                if (isset($explicit[$model]) && $explicit[$model] !== $key) {
                    $errors[] = "El modelo {$model} está declarado en `{$explicit[$model]}` y en `{$key}`.";
                }
                $explicit[$model] ??= $key;
            }

            $functional[$key] = [
                'key' => $key,
                'name' => $name !== null ? trim($name) : $this->defaultModuleName($key, $from, $modules),
                'area' => $area !== null ? trim($area) : $this->sharedArea($from, $modules),
                'declared' => true,
                'from' => $from,
                'models' => [],
            ];
        }
        if ($errors) {
            return [null, $errors];
        }

        // Los módulos técnicos que nadie agrupa pasan tal cual, marcados como no declarados
        foreach ($modules as $technical => $module) {
            if (! isset($claims[$technical])) {
                $claims[$technical] = $technical;
                $functional[$technical] = ['key' => $technical, 'name' => $module['name'], 'area' => $module['area'] ?? null, 'declared' => false, 'from' => [$technical], 'models' => []];
            }
        }

        $assign = [];
        foreach ($byName as $name => $class) {
            if (isset($explicit[$name])) {
                $assign[$name] = ['module' => $explicit[$name], 'source' => 'declared'];
            } elseif ($models[$class]['module'] !== null && ($this->technicalVia[$class] ?? null) !== 'parent') {
                // la herencia técnica se recalcula sobre los módulos funcionales
                $assign[$name] = ['module' => $claims[$models[$class]['module']], 'source' => 'technical'];
            }
        }

        $parentsOf = [];
        foreach ($byName as $name => $class) {
            $parentsOf[$name] = collect($models[$class]['relations'])
                ->filter(fn ($r) => $r['type'] === 'BelongsTo' && ! $r['external'] && $r['related'] && $r['related'] !== $name && isset($byName[$r['related']]))
                ->pluck('related')->unique()->sort()->values()->all();
        }

        $sharedSet = array_flip($shared);
        $pending = array_values(array_diff(array_keys($byName), array_keys($assign)));
        do {
            // cada ronda decide con lo resuelto en las anteriores: el resultado no depende del orden
            $round = [];
            foreach ($pending as $name) {
                [$result, $this->unresolved[$name]] = $this->inheritModule($name, $parentsOf[$name], $assign, $sharedSet);
                if ($result) {
                    $round[$name] = $result;
                    unset($this->unresolved[$name]);
                }
            }
            $assign += $round;
            $pending = array_values(array_diff($pending, array_keys($round)));
        } while ($round);

        foreach ($pending as $name) {
            $assign[$name] = ['module' => null, 'source' => 'unresolved'];
            $hint = str_starts_with($this->unresolved[$name], 'padres en módulos distintos')
                ? 'Marca los catálogos en `architecture.shared` o declara el modelo en `architecture.modules.*.models` de _project.json.'
                : 'Decláralo en `architecture.modules.*.models` de docs/specs/_project.json.';
            $this->warning('model_without_module', $name, "No se pudo asignar a un módulo: {$this->unresolved[$name]}. {$hint}");
        }
        ksort($assign);

        foreach ($assign as $name => $a) {
            if ($a['module'] !== null) {
                $functional[$a['module']]['models'][] = $name;
            }
        }
        foreach ($functional as $key => &$module) {
            $deps = [];
            foreach ($module['models'] as $name) {
                foreach ($models[$byName[$name]]['relations'] as $r) {
                    if (! $this->isDependency($r, $name, $models)) {
                        continue;
                    }
                    $target = $assign[$r['related']]['module'] ?? null;
                    if ($target !== null && $target !== $key) {
                        $deps[$target][] = $name.'.'.$r['name'].' ('.$r['type'].')';
                    }
                }
            }
            ksort($deps);
            $module['depends_on'] = collect($deps)->map(fn ($via, $m) => ['module' => $m, 'via' => array_values(array_unique($via))])->values()->all();
        }
        unset($module);
        ksort($functional);

        return [[
            'version' => 1,
            // Con `modules` vacío no hay agrupación propia: los técnicos no se marcan "sin declarar"
            'declared' => $declared !== [],
            'shared' => $shared,
            'modules' => array_values($functional),
            'models' => collect($assign)->map(fn ($a, $name) => ['class' => $byName[$name]] + $a)->all(),
        ], []];
    }

    /** @return array{0: ?array, 1: ?string} [asignación, motivo si no se pudo] */
    private function inheritModule(string $name, array $parents, array $assign, array $shared): array
    {
        if (! $parents) {
            return [null, 'no tiene relaciones belongsTo'];
        }

        // Prefijo en límite de palabra: PurchaseOrderItem → PurchaseOrder (Workers no → Worker)
        $prefix = collect($parents)
            ->filter(fn ($p) => strlen($name) > strlen($p) && str_starts_with($name, $p) && ctype_upper($name[strlen($p)]))
            ->sortByDesc(fn ($p) => strlen($p))->first();
        if ($prefix !== null) {
            return isset($assign[$prefix])
                ? [['module' => $assign[$prefix]['module'], 'source' => 'prefix', 'via' => $prefix], null]
                : [null, "su padre {$prefix} no tiene módulo"];
        }

        $voters = array_values(array_filter($parents, fn ($p) => ! isset($shared[$p])));
        if (! $voters) {
            return [null, 'solo tiene padres compartidos ('.implode(', ', $parents).')'];
        }
        $missing = array_values(array_filter($voters, fn ($p) => ! isset($assign[$p])));
        if ($missing) {
            return [null, 'sus padres '.implode(', ', $missing).' no tienen módulo'];
        }
        $byModule = collect($voters)->groupBy(fn ($p) => $assign[$p]['module'])->sortKeys();
        if ($byModule->count() === 1) {
            return [['module' => $byModule->keys()->first(), 'source' => 'inherited', 'via' => implode(', ', $voters)], null];
        }

        return [null, 'padres en módulos distintos: '.$byModule->map(fn ($ps, $m) => $m.' ('.$ps->implode(', ').')')->implode(', ')];
    }

    /** Área de un módulo agrupado sin `area`: la de sus módulos técnicos, si todos comparten la misma. */
    private function sharedArea(array $from, array $modules): ?string
    {
        $areas = collect($from)->map(fn ($t) => $modules[$t]['area'] ?? null)->filter()->unique();

        return $areas->count() === 1 ? $areas->first() : null;
    }

    private function defaultModuleName(string $key, array $from, array $modules): string
    {
        if (isset($modules[$key])) {
            return $modules[$key]['name'];
        }

        return count($from) === 1 ? $modules[$from[0]]['name'] : Str::headline($key);
    }

    /** --suggest-architecture: borrador de la sección para revisar y pegar en _project.json */
    private function printSuggestion(mixed $config, array $models, array $modules, array $architecture): void
    {
        $config = is_array($config) ? $config : [];

        // Candidatos a catálogo: modelos a los que apuntan muchos otros por belongsTo
        $referrers = [];
        foreach ($models as $model) {
            foreach ($model['relations'] as $r) {
                if ($r['type'] === 'BelongsTo' && ! $r['external'] && $r['related'] && $r['related'] !== $model['name']) {
                    $referrers[$r['related']][$model['name']] = true;
                }
            }
        }
        $candidates = collect($referrers)->map(fn ($by) => count($by))->filter(fn ($n) => $n >= 5)->sortDesc();

        $suggested = $config['modules'] ?? [];
        foreach ($architecture['modules'] as $module) {
            if (! $module['declared'] && $module['models']) {
                $suggested[$module['key']] = ['name' => $module['name']];
            }
        }

        $section = ['architecture' => [
            'shared' => $config['shared'] ?? $candidates->keys()->sort()->values()->all(),
            'modules' => $suggested,
        ]];
        $this->line(json_encode($section, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->line('Borrador para docs/specs/_project.json. Revísalo antes de pegarlo:');
        $this->line('  · Renombra claves y nombres al lenguaje del negocio; agrupa módulos técnicos con "from".');
        $this->line('  · Cada entrada sin "from" agrupa el módulo técnico de su misma clave.');
        if (! isset($config['shared']) && $candidates->isNotEmpty()) {
            $this->line('  · "shared" se sugirió por número de modelos que los referencian; deja solo los catálogos:');
            $candidates->each(fn ($n, $name) => $this->line("      {$name}: {$n} modelos"));
        }
        $unresolved = collect($architecture['models'])->filter(fn ($m) => $m['module'] === null);
        if ($unresolved->isNotEmpty()) {
            $this->line('  · Modelos sin módulo con la configuración actual (decláralos en "models"):');
            $unresolved->each(fn ($m, $name) => $this->line("      {$name}: {$this->unresolved[$name]}"));
        }
        $this->line('Nada se escribió. Después de guardar _project.json corre php artisan docs:map.');
    }

    /* ───────────────────────── Servicios transversales ───────────────────────── */

    private function services(array $models, array $controllers): array
    {
        $disks = [];
        foreach ($controllers as $c) {
            foreach ($c['disks'] as $disk) {
                $disks[$disk][] = $c['name'];
            }
        }

        return [
            'notifications' => [
                'service' => class_exists('App\\Services\\NotificationService') ? 'App\\Services\\NotificationService' : null,
                'controllers' => array_values(array_map(fn ($c) => $c['name'], array_filter($controllers, fn ($c) => $c['notifies']))),
                'audited_types' => array_values(array_unique(array_merge(...array_values(array_map(fn ($c) => $c['audited_types'], $controllers)) ?: [[]]))),
            ],
            'storage' => [
                'default' => config('filesystems.default'),
                'disks' => $disks,
            ],
            'auth' => [
                'roles' => array_values(array_map(fn ($m) => $m['name'], array_filter($models, fn ($m) => in_array('HasRoles', $m['traits'], true)))),
                'fortify' => class_exists('Laravel\\Fortify\\Fortify'),
            ],
        ];
    }

    private function installedPackages(): array
    {
        $composer = $this->readJson(base_path('composer.json')) ?? [];
        $lock = collect(($this->readJson(base_path('composer.lock')) ?? [])['packages'] ?? [])->keyBy('name');

        return collect($composer['require'] ?? [])->keys()
            ->reject(fn ($name) => $name === 'php' || str_starts_with($name, 'ext-'))
            ->mapWithKeys(fn ($name) => [$name => $lock[$name]['version'] ?? null])
            ->all();
    }

    /* ───────────────────────── Comparación contra specs ───────────────────────── */

    private function check(array $specs, array $models, array $archived = []): int
    {
        $issues = [];
        $covered = [];
        // Los modelos de specs archivados tienen spec (en el historial): no cuentan como "sin spec"
        foreach ($archived as $spec) {
            foreach ($spec['models'] ?? [] as $m) {
                if (! empty($m['name'])) {
                    $covered[$m['name']] = true;
                }
            }
        }
        $history = array_merge($specs, $archived);
        $pivots = $this->pivotTables($models);
        $active = array_filter($specs, fn ($s) => in_array($s['meta']['status'] ?? '', ['aprobado', 'implementado'], true));

        foreach ($active as $spec) {
            $id = $spec['meta']['id'] ?? $spec['_file'];
            $type = $spec['type'] ?? 'new';
            $declared = [];

            if (in_array($type, ['new', 'ext-sub'], true)) {
                foreach ($spec['models'] ?? [] as $m) {
                    if (! empty($m['name'])) {
                        $declared[$m['name']] = $m;
                    }
                }
            }
            if ($type === 'ext-fields' && ($target = $this->extFieldsModel($spec, $models))) {
                // C1: solo lo que se agrega o modifica debe existir; lo que se elimina no debe existir; ocultar no toca la tabla
                $declared[$target] = ['name' => $target, 'fields' => array_values(array_filter($spec['newFields'] ?? [], fn ($f) => in_array($f['action'] ?? 'add', ['add', 'modify'], true))), 'relations' => [], '_partial' => true];
                $class = 'App\\Models\\'.$target;
                foreach (array_filter($spec['newFields'] ?? [], fn ($f) => ($f['action'] ?? 'add') === 'remove' && ! empty($f['name'])) as $f) {
                    if (isset($models[$class]) && collect($models[$class]['columns'])->contains('name', trim($f['name'])) && ($spec['meta']['status'] ?? '') === 'implementado') {
                        $issues[] = [$id, $target, "El spec elimina la columna `{$f['name']}` y sigue existiendo en `{$models[$class]['table']}`."];
                    }
                }
            }

            foreach ($declared as $name => $m) {
                $class = 'App\\Models\\'.$name;
                $covered[$name] = true;
                $pivot = null;
                if (! isset($models[$class])) {
                    // Pivote con datos sin clase: el spec lo declara como modelo de detalle con `table`
                    // y el código lo usa desde un belongsToMany. Se compara contra la tabla real.
                    $table = trim($m['table'] ?? '');
                    if ($table === '' || ! isset($pivots[$table])) {
                        $issues[] = [$id, $name, $table === ''
                            ? 'El modelo no existe en app/Models.'
                            : "El modelo no existe en app/Models y ningún belongsToMany usa la tabla `{$table}` como pivote."];

                        continue;
                    }
                    if (! Schema::hasTable($table)) {
                        $issues[] = [$id, $name, "La tabla pivote `{$table}` no existe. ¿Faltan migraciones por correr?"];

                        continue;
                    }
                    $pivot = $pivots[$table];
                    $code = ['table' => $table, 'columns' => $this->columns($table), 'soft_deletes' => false, 'relations' => []];
                } else {
                    $code = $models[$class];
                }
                $columns = collect($code['columns'])->keyBy('name');
                $specFields = collect($m['fields'] ?? [])->pluck('name')->filter()->map(fn ($f) => trim($f))->reject(fn ($f) => in_array($f, self::AUTO_COLUMNS, true));
                // Un belongsTo declarado implica su llave foránea (client_id por convención de Laravel)
                $impliedKeys = collect($m['relations'] ?? [])
                    ->filter(fn ($r) => ($r['kind'] ?? '') === 'belongsTo' && ! empty($r['model']))
                    ->map(fn ($r) => trim($r['key'] ?? '') ?: Str::snake(trim($r['model'])).'_id');

                foreach ($specFields as $field) {
                    if (! $columns->has($field) && ! $this->changedElsewhere($history, $models, $name, $field, ['remove'])) {
                        $issues[] = [$id, $name, "La columna `{$field}` está en el spec y no existe en `{$code['table']}`."];
                    }
                }
                if (empty($m['_partial'])) {
                    foreach ($columns->reject(fn ($c) => $c['auto'])->keys() as $column) {
                        if (! $specFields->contains($column) && ! $impliedKeys->contains($column) && ! $this->changedElsewhere($history, $models, $name, $column, ['add', 'modify'])) {
                            $issues[] = [$id, $name, "La columna `{$column}` existe en el código y ningún spec la declara."];
                        }
                    }
                    if (array_key_exists('soft_deletes', $m) && (bool) $m['soft_deletes'] !== $code['soft_deletes']) {
                        $issues[] = [$id, $name, 'soft_deletes: el spec dice '.($m['soft_deletes'] ? 'sí' : 'no').' y el código dice '.($code['soft_deletes'] ? 'sí' : 'no').'.'];
                    }
                }
                if ($pivot !== null) {
                    continue; // sin clase no hay relaciones propias: las tiene el belongsToMany ($pivot)
                }
                $codeRelations = collect($code['relations'])->map(fn ($r) => $r['type'].':'.$r['related']);
                foreach ($m['relations'] ?? [] as $rel) {
                    if (empty($rel['model'])) {
                        continue;
                    }
                    $expected = ucfirst($rel['kind'] ?? 'belongsTo').':'.trim($rel['model']);
                    if (! $codeRelations->contains($expected)) {
                        $issues[] = [$id, $name, "La relación {$rel['kind']} → {$rel['model']} está en el spec y no en el modelo."];
                    }
                }
            }
        }

        $uncovered = collect($models)->reject(fn ($m) => isset($covered[$m['name']]))->pluck('name')->all();

        $this->newLine();
        if ($issues) {
            $this->components->error(count($issues).' diferencia(s) entre los specs y el código');
            $this->table(['Spec', 'Modelo', 'Diferencia'], $issues);
        } else {
            $this->components->info('Los specs aprobados coinciden con el código.');
        }
        if ($uncovered) {
            $this->components->warn('Modelos sin spec: '.implode(', ', $uncovered).($this->option('strict') ? '' : ' (no cuenta como error; usa --strict)'));
        }

        return ($issues || ($this->option('strict') && $uncovered)) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Tablas pivote que usan las relaciones belongsToMany / morphToMany del código.
     *
     * @return array<string, list<string>> tabla => ["Modelo::relación", ...]
     */
    private function pivotTables(array $models): array
    {
        $pivots = [];
        foreach ($models as $model) {
            foreach ($model['relations'] as $r) {
                if (in_array($r['type'], ['BelongsToMany', 'MorphToMany'], true) && ! empty($r['pivot_table'])) {
                    $pivots[$r['pivot_table']][] = $model['name'].'::'.$r['name'];
                }
            }
        }

        return $pivots;
    }

    /**
     * Una columna puede cambiar en un spec C1 (cambio de campos) posterior, vigente o archivado:
     * agregada o modificada (existe aunque el spec original no la declare) o eliminada (ya no existe
     * aunque el original la declare). Solo cuentan los C1 implementados para eliminar.
     */
    private function changedElsewhere(array $specs, array $models, string $model, string $column, array $actions): bool
    {
        foreach ($specs as $spec) {
            if (($spec['type'] ?? '') !== 'ext-fields' || $this->extFieldsModel($spec, $models) !== $model) {
                continue;
            }
            if (in_array('remove', $actions, true) && ($spec['meta']['status'] ?? '') !== 'implementado') {
                continue;
            }
            foreach ($spec['newFields'] ?? [] as $f) {
                if (trim($f['name'] ?? '') === $column && in_array($f['action'] ?? 'add', $actions, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Modelo que extiende un spec C1: por su tabla (v.table) o, en specs anteriores, por v.target.
     */
    private function extFieldsModel(array $spec, array $models): ?string
    {
        if (($spec['type'] ?? '') !== 'ext-fields') {
            return null;
        }
        $table = trim($spec['v']['table'] ?? '');
        if ($table !== '') {
            foreach ($models as $model) {
                if (($model['table'] ?? null) === $table) {
                    return $model['name'];
                }
            }
        }

        return trim($spec['v']['target'] ?? '') ?: null;
    }

    /* ───────────────────────── Manuales (docs/manual) ───────────────────────── */

    /**
     * Estado de cada manual contra el código y los specs:
     *   behind         specs implementados del módulo que no están en `covers`
     *   stale          tareas cuya huella (fp) cambió desde que se escribieron
     *   orphan         tareas de rutas o métodos Livewire que ya no existen
     *   missing_steps  acciones del módulo sin tarea en el manual
     *   unknown        tareas sin huella (manuales escritos antes de 0.3.0)
     *   nav            entradas del menú del módulo («Área → Etiqueta») que el manual no menciona:
     *                  se renombraron o movieron y el menú no entra en ninguna huella
     * y la lista de módulos con specs implementados que no tienen manual.
     */
    private function buildManuals(array $modules, array $architecture, array $specs): array
    {
        $dir = base_path('docs/manual');
        $functional = collect($architecture['modules'])->keyBy('key');
        $items = [];
        foreach (File::isDirectory($dir) ? File::files($dir) : [] as $file) {
            $name = $file->getFilename();
            if (! str_ends_with($name, '.md') || str_starts_with($name, '_')) {
                continue;
            }
            $text = (string) file_get_contents($file->getPathname());
            $fm = $this->frontmatter($text);
            $key = trim((string) ($fm['module'] ?? '')) ?: Str::beforeLast($name, '.md');
            $from = $functional[$key]['from'] ?? (isset($modules[$key]) ? [$key] : []);
            $routes = collect($from)->flatMap(fn ($t) => $modules[$t]['routes'] ?? [])->filter(fn ($r) => $r['name'])->keyBy('name');
            $covers = array_values(array_filter((array) ($fm['covers'] ?? [])));

            preg_match_all('/<!--\s*step:(\S+?)(?:\s+fp:(\w+))?\s*-->/', $text, $m, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
            $steps = [];
            foreach ($m as [, $id, $fp]) {
                $current = $this->stepFingerprint($id, $routes);
                $state = $current === false ? 'orphan' : (($fp ?? '') === '' ? 'unknown' : ($fp === $current ? 'ok' : 'stale'));
                $steps[] = ['id' => $id, 'fp' => ($fp ?? '') ?: null, 'current_fp' => $current ?: null, 'state' => $state];
            }
            $ids = array_column($steps, 'id');

            $items[] = [
                'module' => $key,
                'file' => 'docs/manual/'.$name,
                'name' => $fm['name'] ?? null,
                'status' => $fm['status'] ?? null,
                'generated_at' => $fm['generated_at'] ?? null,
                'covers' => $covers,
                'pending' => preg_match_all('/^>\s*⏳\s*Pendiente:/mu', $text),
                'steps' => $steps,
                'behind' => array_values(array_diff($this->documentableSpecs($key, $from, $modules, $specs), $covers)),
                'stale' => array_column(array_filter($steps, fn ($s) => $s['state'] === 'stale'), 'id'),
                'orphan' => array_column(array_filter($steps, fn ($s) => $s['state'] === 'orphan'), 'id'),
                'unknown' => array_column(array_filter($steps, fn ($s) => $s['state'] === 'unknown'), 'id'),
                'missing_steps' => $this->missingSteps($routes, $ids),
                'nav' => $this->missingNav($from, $modules, $text),
            ];
        }

        $documented = array_column($items, 'module');
        $missing = [];
        foreach ($functional as $key => $module) {
            $ids = $this->documentableSpecs($key, $module['from'], $modules, $specs);
            if ($ids && ! in_array($key, $documented, true)) {
                $missing[] = ['module' => $key, 'name' => $module['name'], 'specs' => $ids];
            }
        }

        return ['items' => $items, 'missing' => $missing];
    }

    /**
     * Rutas de menú («Área → Etiqueta») de los módulos técnicos que el manual no menciona.
     * El manual las escribe en negritas (**Comercial → Pagos**); se comparan sin formato.
     *
     * @return list<string>
     */
    private function missingNav(array $from, array $modules, string $text): array
    {
        $plain = preg_replace('/\s+/u', ' ', str_replace(['**', '__'], '', $text));
        $missing = [];
        foreach ($from as $technical) {
            $label = $modules[$technical]['nav_label'] ?? null;
            if (! $label) {
                continue;
            }
            $area = $modules[$technical]['area'] ?? null;
            $path = $area ? "{$area} → {$label}" : $label;
            if (! str_contains($plain, $path)) {
                $missing[] = $path;
            }
        }

        return array_values(array_unique($missing));
    }

    /** Huella actual de una tarea: ruta, ruta#tema o ruta@metodoLivewire. false si ya no existe. */
    private function stepFingerprint(string $id, $routes): string|false
    {
        $base = preg_split('/[@#]/', $id)[0];
        $route = $routes[$base] ?? null;
        if (! $route) {
            return false;
        }
        if (str_contains($id, '@')) {
            $method = Str::after($id, '@');
            foreach ($route['livewire'] as $component) {
                if (isset($component['actions'][$method])) {
                    return $component['actions'][$method];
                }
            }

            return false;
        }

        return (string) $route['fp'];
    }

    /**
     * Acciones del módulo sin tarea. Las rutas que solo muestran un formulario no son tareas:
     * `create` se cubre con `store` (o con una tarea de Livewire en la misma página) y `edit` con `update`.
     * Las acciones de un componente Livewire se cubren con cualquier tarea `ruta@...` de esa página.
     */
    private function missingSteps($routes, array $ids): array
    {
        $has = fn (string $id) => in_array($id, $ids, true) || collect($ids)->contains(fn ($s) => str_starts_with($s, $id.'@') || str_starts_with($s, $id.'#'));
        $missing = [];
        foreach ($routes as $name => $route) {
            $prefix = Str::beforeLast($name, '.');
            $covered = $has($name)
                || (str_ends_with($name, '.create') && $has($prefix.'.store'))
                || (str_ends_with($name, '.edit') && $has($prefix.'.update'));
            if (! $covered) {
                $missing[] = $name;
            }
            foreach ($route['livewire'] as $component) {
                foreach (array_keys($component['actions']) as $method) {
                    if (! $has($name.'@'.$method) && ! collect($ids)->contains(fn ($s) => str_starts_with($s, $name.'@'))) {
                        $missing[] = $name.'@'.$method;
                    }
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /** IDs de specs implementados del módulo que el manual debe incluir (sin vistas públicas). */
    private function documentableSpecs(string $key, array $from, array $modules, array $specs): array
    {
        $technical = collect($from)->flatMap(fn ($t) => $modules[$t]['specs'] ?? [])->all();

        return collect($specs)
            ->filter(fn ($s) => ($s['meta']['status'] ?? '') === 'implementado')
            ->reject(fn ($s) => ($s['type'] ?? '') === 'public' && ($s['v']['access'] ?? 'public') !== 'admin')
            ->filter(fn ($s) => in_array($s['meta']['id'] ?? null, $technical, true) || in_array($s['meta']['module_key'] ?? null, array_merge([$key], $from), true))
            ->pluck('meta.id')->filter()->unique()->sort()->values()->all();
    }

    /** Frontmatter simple del manual: clave: valor, clave: [a, b] y comentarios con #. */
    private function frontmatter(string $text): array
    {
        if (! preg_match('/^---\r?\n(.*?)\r?\n---/s', $text, $m)) {
            return [];
        }
        $out = [];
        foreach (preg_split('/\r?\n/', $m[1]) as $line) {
            if (! preg_match('/^([A-Za-z_]+):\s*(.*)$/', $line, $kv)) {
                continue;
            }
            $value = trim(preg_replace('/\s+#.*$/', '', $kv[2]));
            $out[$kv[1]] = preg_match('/^\[(.*)\]$/', $value, $list)
                ? array_values(array_filter(array_map(fn ($v) => trim($v, " '\""), explode(',', $list[1]))))
                : (trim($value, "'\"") ?: null);
        }

        return $out;
    }

    /** --check: avisos de manuales. Solo fallan con --strict. */
    private function checkManuals(array $manuals): int
    {
        $rows = [];
        foreach ($manuals['missing'] as $m) {
            $rows[] = ['manual_missing', $m['module'], 'Sin manual. Specs implementados: '.implode(', ', $m['specs']).'. Corre /generar-manual '.$m['module'].'.'];
        }
        foreach ($manuals['items'] as $m) {
            foreach ($m['behind'] as $id) {
                $rows[] = ['manual_behind', $m['module'], "{$id} está implementado y no está en `covers`."];
            }
            foreach ($m['stale'] as $id) {
                $rows[] = ['manual_stale_step', $m['module'], "La tarea {$id} cambió en el código desde que se escribió."];
            }
            foreach ($m['orphan'] as $id) {
                $rows[] = ['manual_orphan_step', $m['module'], "La tarea {$id} ya no existe en el código."];
            }
            foreach ($m['missing_steps'] as $id) {
                $rows[] = ['manual_missing_step', $m['module'], "La acción {$id} no tiene tarea en el manual."];
            }
            foreach ($m['nav'] as $path) {
                $rows[] = ['manual_nav_changed', $m['module'], "El menú dice «{$path}» y el manual no lo menciona: ¿se renombró o se movió la entrada?"];
            }
            if ($m['unknown']) {
                $rows[] = ['manual_no_fp', $m['module'], count($m['unknown']).' tarea(s) sin huella: no se puede saber si cambiaron. Vuelve a generar el manual.'];
            }
        }

        $this->newLine();
        if (! $rows) {
            $this->components->info('Los manuales están al día con el código y los specs.');

            return self::SUCCESS;
        }
        $this->components->warn(count($rows).' aviso(s) de manuales'.($this->option('strict') ? '' : ' (no cuenta como error; usa --strict)'));
        $this->table(['Aviso', 'Módulo', 'Detalle'], $rows);

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    /* ───────────────────────── Utilidades ───────────────────────── */

    private function readSpecs(string $relative = 'docs/specs'): array
    {
        $dir = base_path($relative);
        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.spec.json'))
            ->map(function ($f) use ($relative) {
                $data = $this->readJson($f->getPathname());
                if (! is_array($data) || empty($data['type'])) {
                    $this->warning('spec_unreadable', $f->getFilename(), 'No es un spec válido de la Spec Tool.');

                    return null;
                }

                return $data + ['_file' => $relative.'/'.$f->getFilename()];
            })
            ->filter()
            ->sortBy(fn ($s) => ($s['meta']['created'] ?? '').($s['meta']['id'] ?? ''))
            ->values()
            ->all();
    }

    private function readJson(string $path): ?array
    {
        if (! File::exists($path)) {
            return null;
        }
        $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path)), true);

        return is_array($data) ? $data : null;
    }

    private function warning(string $code, string $subject, string $message): void
    {
        $this->warnings[] = ['code' => $code, 'subject' => $subject, 'message' => $message];
    }

    private function printSummary(array $modules, array $models, array $architecture): void
    {
        // La tabla muestra la vista funcional; la técnica sigue completa en _code.json
        $functional = collect($architecture['modules'])->filter(fn ($m) => $m['models']);
        $this->table(
            ['Módulo', 'Agrupa', 'Modelos', 'Depende de'],
            $functional->map(fn ($m) => [
                $m['name'].($m['declared'] || ! $architecture['declared'] ? '' : ' (sin declarar)'),
                implode(', ', $m['from']) ?: '—',
                implode(', ', $m['models']),
                implode(', ', array_column($m['depends_on'], 'module')) ?: '—',
            ])->values()->all()
        );
        $this->line(count($models).' modelos · '.$functional->count().' módulos'
            .($architecture['declared'] ? ' ('.$functional->where('declared', true)->count().' declarados en _project.json)' : '')
            .' · '.count($modules).' módulos técnicos · '.count($this->warnings).' advertencias');
        foreach ($this->warnings as $w) {
            $this->components->warn("{$w['subject']}: {$w['message']}");
        }
    }
}
