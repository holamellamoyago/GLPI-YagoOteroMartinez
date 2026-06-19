# Guía de Desarrollo de Plugins para GLPI 11

Guía práctica basada en la experiencia desarrollando plugins para GLPI 11.0.7.

---

## 1. Estructura de un Plugin

```
plugins/mi-plugin/
├── setup.php                          # Manifiesto (obligatorio)
├── hook.php                           # Hooks adicionales (opcional)
├── inc/                               # Clases PHP
│   └── clase.class.php                # 1 archivo por clase
├── front/                             # Páginas accesibles vía navegador
│   └── pagina.php
├── ajax/                              # Endpoints AJAX
│   └── endpoint.php
├── js/                                # JavaScript
├── css/                               # CSS
└── locales/                           # Traducciones
    └── es_ES.php
```

### Convención de nombres de clase

| Regla | Ejemplo |
|-------|---------|
| Prefijo `Plugin<nombreplugin>` | `PluginDashboard` |
| + nombre de la clase en CamelCase | `PluginDashboardDashboard` |
| Archivo: `inc/<nombre>.class.php` | `inc/dashboard.class.php` |

---

## 2. setup.php — Funciones obligatorias

GLPI 11 requiere estas 6 funciones para reconocer el plugin:

```php
function plugin_version_mi_plugin(): array {
    return [
        'name'           => 'Mi Plugin',
        'version'        => '1.0.0',
        'minGlpiVersion' => '11.0',
        'author'         => 'Yago',
        'license'        => 'GPLv3+',
    ];
}

function plugin_mi_plugin_install(): bool {
    // Crear tablas de BD aquí
    return true;
}

function plugin_mi_plugin_uninstall(): bool {
    // Borrar tablas de BD aquí
    return true;
}

function plugin_init_mi_plugin(): void {
    global $PLUGIN_HOOKS;
    // Registrar hooks aquí (ver sección 3)
}

function plugin_mi_plugin_check_prerequisites() {
    return true;  // false si falta alguna dependencia
}

function plugin_mi_plugin_check_config() {
    return true;  // false si la configuración es inválida
}
```

> **Nota**: El sufijo `_mi_plugin` en cada función es el nombre del plugin en snake_case. Reemplázalo por el tuyo.

---

## 3. Hooks de GLPI — Sistema de Eventos

Un **hook** es un punto de extensión. Cuando GLPI construye algo (un menú, un formulario, etc.), notifica a los plugins activos para que puedan inyectar contenido.

Los hooks se registran en `plugin_init_xxx()` dentro del array global `$PLUGIN_HOOKS`:

```php
function plugin_init_mi_plugin(): void {
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['nombre_del_hook']['mi_plugin'] = valor;
}
```

### Hooks más comunes

| Hook | Descripción | Ejemplo de valor |
|------|-------------|-----------------|
| `menu_toadd` | Añadir entradas al menú lateral | `['config' => 'MiClase']` |
| `csrf_compliant` | Marcar páginas del plugin como seguras | `true` |
| `post_item_form` | Inyectar HTML en formularios | `['ClaseEjemplo', 'metodo']` |
| `item_add` | Reaccionar cuando se crea un item | `['ClaseEjemplo', 'metodo']` |
| `pre_item_update` | Interceptar antes de actualizar | `['ClaseEjemplo', 'metodo']` |
| `dashboard_cards` | Añadir tarjetas al dashboard | Callable |
| `dashboard_filters` | Añadir filtros al dashboard | Array de clases |

---

## 4. Sistema de Menús en GLPI 11 🚨

Esta es la parte más delicada. El hook `menu_toadd` tiene **dos formatos diferentes** y usar el incorrecto hace que el menú desaparezca sin errores.

### Las secciones base de GLPI 11

El menú lateral de GLPI tiene estas secciones fijas (definidas en `src/Html.php:getMenuInfos()`):

```
assets      → Activos
helpdesk    → Asistencia
management  → Gestión
tools       → Herramientas
plugins     → Plugins
admin       → Administración
config      → Configuración
preference  → Mis preferencias
```

### Formato A: Añadir un item DENTRO de una sección existente

```php
// Aparece dentro de "Configuración"
$PLUGIN_HOOKS['menu_toadd']['mi_plugin'] = [
    'config' => 'PluginMiPluginMiClase'
];
//  ^^^^^^     ^^^^^^^^^^^^^^^^^^^^^^^^^^^
//  sección    string = nombre de clase
//  existente
```

- La clave (`config`) debe coincidir con una sección base de GLPI.
- El valor es un **string** con el nombre de la clase.
- GLPI comprueba `isset($menu['config'])` → si no existe, descarta la entrada.

### Formato B: Crear una sección COMPLETAMENTE NUEVA

```php
// Crea una nueva sección en el menú lateral llamada 'mi_seccion'
$PLUGIN_HOOKS['menu_toadd']['mi_plugin'] = [
    'mi_seccion' => ['PluginMiPluginMiClase']
];
//  ^^^^^^^^^^^    ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
//  clave nueva    ARRAY con 1+ nombres de clase
```

- La clave es un nombre nuevo (no está en las secciones base).
- El valor es un **array** de strings (los nombres de clase).
- PHP crea automáticamente `$menu['mi_seccion']`, saltándose el `isset()`.
- Requisitos para la nueva sección:

| Necesitas | De dónde sale |
|-----------|---------------|
| **Título** | `getMenuContent()` → clave `'title'` |
| **Página por defecto** | `getMenuContent()` → clave `'page'` |
| **Icono** | Método estático `getIcon()` en la clase |

**Error común**: Usar el formato A con una clave nueva. El `isset()` falla y GLPI descarta la entrada sin avisar.

### La clase del menú

La clase que referencias en `menu_toadd` debe extender `\CommonGLPI` y definir:

```php
class PluginMiPluginMiClase extends \CommonGLPI
{
    static function getIcon(): string {
        return 'ti ti-dashboard';  // Icono Tabler (necesario para sección nueva)
    }

    static function getMenuContent(): array|false {
        return [
            'title' => __('Mi Plugin', 'mi_plugin'),         // Etiqueta del menú
            'page'  => '/plugins/mi_plugin/front/pagina.php', // Enlace
        ];
        // return false para ocultar condicionalmente (ej: por permisos)
    }
}
```

---

## 5. Páginas front-end (las que ve el usuario)

Toda página accesible desde el navegador va en `front/` y sigue este esqueleto:

```php
<?php
include('../../../inc/includes.php');  // Bootstrap de GLPI (¡ruta correcta!)

Session::checkLoginUser();              // Solo usuarios logueados

Html::header(
    'Título de pestaña',               // <title> del navegador
    $_SERVER['PHP_SELF'],              // URL actual (para breadcrumb)
    'mi_seccion',                      // Sección del menú (misma clave que en menu_toadd)
    'mi_plugin'                        // Sub-item activo (nombre del plugin o clase en minúsculas)
);

// ... tu HTML aquí ...

Html::footer();
```

### Significado de los parámetros de Html::header()

```php
Html::header($title, $url, $section, $item);
//           ^^^^^^  ^^^^  ^^^^^^^^  ^^^^^^
//           pestaña URL   sección   sub-item
//           browser       del menú  destacado
```

- **$section**: DEBE coincidir con la clave que usaste en `menu_toadd`. Si pusiste `'config'` en el hook, usa `'config'` aquí. Si creaste una sección nueva `'mi_seccion'`, usa `'mi_seccion'`.
- **$item**: Identificador para resaltar el sub-elemento activo. Para plugins simples, el nombre del plugin en minúsculas funciona.

Si no coinciden, el breadcrumb y la navegación lateral no se resaltan correctamente.

---

## 6. Traducciones con __()

```php
__('Dashboard', 'mi_plugin')
//  ^^^^^^^^^^^  ^^^^^^^^^^^
//  Texto en     Dominio = nombre del plugin
//  inglés
```

- **1er argumento**: Texto en inglés (idioma por defecto).
- **2º argumento**: Dominio — normalmente el nombre del plugin. Evita colisiones con otros plugins.
- Las traducciones se guardan en `plugins/mi_plugin/locales/es_ES.php`:

```php
$LANG['mi_plugin']['Dashboard'] = 'Panel de control';
$LANG['mi_plugin']['Settings']  = 'Configuración';
```

---

## 7. Tabla de Errores Frecuentes

| Síntoma | Causa probable | Solución |
|---------|---------------|----------|
| El plugin no aparece en la lista | Falta alguna función en `setup.php` | Revisa que las 6 funciones estén definidas |
| Menú no aparece en sidebar | Formato incorrecto en `menu_toadd` | Sección nueva → array value. Sección existente → string value |
| Menú estaba en Configuración y desapareció | Cambiaste la clave pero no el formato | Revisa si usas `['nueva' => ['Clase']]` (array) no `['nueva' => 'Clase']` (string) |
| Breadcrumb raro / menú no se ilumina | `Html::header()` usa una sección que no coincide con `menu_toadd` | Iguala las claves |
| Error 404 en la página del plugin | Ruta equivocada en `getMenuContent()` → `'page'` | Comprueba que empieza por `/plugins/tu-plugin/front/...` |
| Pantalla en blanco / error 500 | Error de PHP no visible | Mira los logs del contenedor: `docker logs glpi_server` |
| Traducciones no funcionan | Falta el archivo `locales/es_ES.php` o dominio incorrecto | El 2º arg de `__()` debe coincidir con el nombre del plugin |

---

## 8. Depuración

### Ver logs de GLPI
```bash
docker logs glpi_server
```

### Probar si el plugin se carga
```bash
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:list --allow-superuser
```

### Reinstalar el plugin tras cambios en BD
```bash
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:deactivate --allow-superuser mi_plugin
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:uninstall --allow-superuser mi_plugin
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:install --allow-superuser mi_plugin
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:activate --allow-superuser mi_plugin
```

---

## 9. Resumen visual: Flujo del menú

```
plugin_init_mi_plugin()
    │
    └─► $PLUGIN_HOOKS['menu_toadd']['mi_plugin'] = ['seccion' => [...]]
            │
            ▼ (GLPI construye el menú en generateMenuSession())
            │
            ├─ ¿La clave 'seccion' existe en el menú base?
            │   ├─ SÍ (ej: 'config') → se añade dentro de esa sección
            │   └─ NO (ej: 'mi_seccion') → se crea una sección nueva
            │       (solo si usaste array value, no string)
            │
            └─► Por cada clase registrada, GLPI llama a:
                ├─ getIcon()        → icono de la sección
                └─ getMenuContent() → título, página, etc.

front/tu_pagina.php
    │
    ├─ include('../../../inc/includes.php')   → bootstrap
    ├─ Session::checkLoginUser()              → auth
    ├─ Html::header(..., 'seccion', 'item')   → navegación
    └─ Html::footer()                         → cierre
```
