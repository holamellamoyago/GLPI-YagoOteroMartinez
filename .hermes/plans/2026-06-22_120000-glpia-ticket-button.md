# Botón glpIA en textareas de tickets — Plan de Implementación

> **Para Hermes:** Usar `subagent-driven-development` para implementar este plan paso a paso.

**Goal:** Añadir un botón "glpIA" debajo de cada textarea en el panel de tickets de GLPI (descripción, seguimientos, tareas, soluciones) que al pulsar muestre un diálogo modal. Todo desde el plugin glpia, sin tocar código core de GLPI.

**Architecture:** Usar el hook `post_item_form` de GLPI 11 para inyectar JavaScript en las páginas de Ticket. El JS se ejecuta al cargar la página, detecta todos los textareas (incluyendo los de la timeline que se cargan dinámicamente vía Ajax), y añade un botón debajo de cada uno. El botón lanza un modal Bootstrap 5 con un placeholder.

**Tech Stack:** PHP (hook de GLPI), JavaScript vanilla + jQuery (GLPI ya carga jQuery), Bootstrap 5 (GLPI 11 lo usa nativamente).

---

## Investigación previa

### Dónde se invoca POST_ITEM_FORM en GLPI 11

| Template | Línea | Contexto |
|----------|-------|----------|
| `fields_panel.html.twig:266` | POST_ITEM_FORM | Form principal del ticket (descripción, análisis, etc.) |
| `form_followup.html.twig:210` | POST_ITEM_FORM | Form de seguimiento |
| `form_task_main_form.html.twig:355` | POST_ITEM_FORM | Form de tarea |
| `form_solution.html.twig:222` | POST_ITEM_FORM | Form de solución |
| `form_validation.html.twig:298` | POST_ITEM_FORM | Form de validación |
| `form/buttons.html.twig:41` | POST_ITEM_FORM | Botones del form |

Parámetros que recibe el hook: `{'item': $item, 'options': $params}`

### IDs de los textareas

- **Seguimiento**: `solution_content_<rand>` (ej: `solution_content_12345`)
- **Tarea**: similar, con `rand`
- **Descripción principal**: `content`, `impactcontent`, `causecontent`, etc. (nombres de campo)
- **Timeline dinámica**: los forms se cargan vía Ajax, hay que usar MutationObserver o delegación de eventos

### Estructura actual del plugin glpia

```
plugins/glpia/
├── setup.php          ← registra menú, CSRF
├── inc/
│   └── corrector.class.php
└── front/
    └── glpia.php      ← página principal
```

Faltan: `hook.php`, `js/`, `css/`

---

## Tasks

### Task 1: Crear `hook.php` con el handler del hook

**Objective:** Crear el archivo hook.php que implementa `plugin_glpia_post_item_form()`

**Files:**
- Create: `plugins/glpia/hook.php`

**Step 1: Crear el archivo**

```php
<?php
/**
 * Hook implementations for glpIA plugin.
 */

function plugin_glpia_post_item_form($params)
{
    $item = $params['item'] ?? null;
    if (!$item) {
        return;
    }

    // Solo en páginas de Ticket y sus sub-elementos
    $target_types = [
        'Ticket',
        'ITILFollowup',   // GLPI 11 usa ITILFollowup, no TicketFollowup
        'TicketTask',
        'ITILSolution',
    ];
    if (!in_array($item->getType(), $target_types)) {
        return;
    }

    // Inyectar JS una sola vez por página
    static $injected = false;
    if (!$injected) {
        $injected = true;
        $js_path = Plugin::getWebDir('glpia') . '/js/ticket_buttons.js';
        echo '<script src="' . $js_path . '"></script>';
    }
}
```

**Verificación:** El archivo existe, sintaxis OK.

---

### Task 2: Crear `js/ticket_buttons.js` con la lógica del botón

**Objective:** Crear el JavaScript que añade botones debajo de cada textarea

**Files:**
- Create: `plugins/glpia/js/ticket_buttons.js`

**Step 1: Crear el JS**

```javascript
/**
 * glpIA — Botón de prueba en textareas de tickets
 * Añade un botón debajo de cada textarea en el panel de tickets.
 * Al pulsar, muestra un modal Bootstrap con placeholder.
 */
(function () {
    'use strict';

    const BUTTON_CLASS = 'glpia-ticket-btn';
    const BUTTON_HTML =
        '<button type="button" class="btn btn-sm btn-outline-secondary ' +
        BUTTON_CLASS +
        ' mt-1">' +
        '<i class="ti ti-robot"></i> glpIA analizar' +
        '</button>';

    /**
     * Añade botón debajo de un textarea si no existe ya.
     * @param {HTMLElement} textarea
     */
    function addButtonToTextarea(textarea) {
        // Evitar duplicados
        var parent = textarea.closest('.col-12, .form-group, .mb-3, .form-field');
        if (!parent) {
            parent = textarea.parentElement;
        }
        if (parent.querySelector('.' + BUTTON_CLASS)) {
            return; // ya tiene botón
        }

        // Insertar botón después del textarea
        var btn = document.createElement('div');
        btn.innerHTML = BUTTON_HTML;
        var buttonEl = btn.firstChild;

        buttonEl.addEventListener('click', function (e) {
            e.preventDefault();
            showGlpiaModal();
        });

        textarea.insertAdjacentElement('afterend', buttonEl);
    }

    /**
     * Muestra modal Bootstrap con placeholder.
     */
    function showGlpiaModal() {
        // Si ya existe un modal, eliminarlo
        var oldModal = document.getElementById('glpia-modal');
        if (oldModal) {
            oldModal.remove();
        }

        var modalHTML =
            '<div class="modal fade" id="glpia-modal" tabindex="-1">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title">' +
            '<i class="ti ti-robot me-2"></i>glpIA' +
            '</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p>Funcionalidad en desarrollo.</p>' +
            '<p class="text-muted">Próximamente: análisis inteligente del texto.</p>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>' +
            '</div>' +
            '</div></div></div>';

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        var modalEl = document.getElementById('glpia-modal');
        var modal = new bootstrap.Modal(modalEl);
        modal.show();

        // Limpiar al cerrar
        modalEl.addEventListener('hidden.bs.modal', function () {
            modalEl.remove();
        });
    }

    /**
     * Escanea la página en busca de textareas y añade botones.
     */
    function scanTextareas() {
        // Textareas visibles (no hidden)
        document.querySelectorAll('textarea:not([style*="display:none"])').forEach(function (ta) {
            // Solo textareas de contenido, no buscadores ni filtros
            if (
                ta.closest('.search-form') ||
                ta.closest('.filter-panel') ||
                ta.closest('[role="search"]') ||
                ta.offsetParent === null
            ) {
                return;
            }
            addButtonToTextarea(ta);
        });
    }

    // ── Inicialización ──────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scanTextareas);
    } else {
        scanTextareas();
    }

    // Re-escanear cuando se cargan forms dinámicos (timeline Ajax)
    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) {
                        // Si el nodo añadido contiene textareas
                        if (node.querySelectorAll) {
                            node.querySelectorAll('textarea').forEach(function (ta) {
                                if (ta.offsetParent !== null) {
                                    addButtonToTextarea(ta);
                                }
                            });
                        }
                        // Si el propio nodo es un textarea
                        if (node.tagName === 'TEXTAREA' && node.offsetParent !== null) {
                            addButtonToTextarea(node);
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }
})();
```

**Verificación:** El JS es sintácticamente válido. Se carga en las páginas de ticket.

---

### Task 3: Actualizar `setup.php` para registrar el hook y el JS

**Objective:** Registrar `hook.php` y el hook `post_item_form` en el init del plugin

**Files:**
- Modify: `plugins/glpia/setup.php`

**Step 1: Añadir las líneas de registro**

En `plugin_init_glpIA()`, después de la línea de `menu_toadd`, añadir:

```php
// Hook para inyectar botón en textareas de tickets
require_once __DIR__ . '/hook.php';
$PLUGIN_HOOKS['post_item_form']['glpia'] = 'plugin_glpia_post_item_form';
```

El `plugin_init_glpIA()` queda:

```php
function plugin_init_glpIA(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpia'] = true;
    $PLUGIN_HOOKS['menu_toadd']['glpia'] = ['glpia' => ['PluginGlpiaCorrector']];

    // Hook para inyectar botón glpIA en textareas de tickets
    require_once __DIR__ . '/hook.php';
    $PLUGIN_HOOKS['post_item_form']['glpia'] = 'plugin_glpia_post_item_form';
}
```

**Verificación:** `docker exec glpi_server php -l //var/.../plugins/glpia/setup.php` → sin errores.

---

### Task 4: Re-activar el plugin para aplicar cambios

**Objective:** Tras modificar setup.php, GLPI necesita re-leer los hooks. Desactivar y reactivar el plugin.

**Step 1: Comandos (pedir permiso al usuario primero)**

```bash
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:deactivate --allow-superuser glpia
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:activate --allow-superuser glpia
```

**Verificación:**
```bash
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:list --allow-superuser | grep glpia
# | glpia | glpIA | 1.0.0 | Activado | Instalado manualmente |
```

---

### Task 5: Verificación funcional

**Objective:** Confirmar que los botones aparecen en los tickets

**Step 1: Pruebas manuales**

1. Abrir `http://localhost:81/` y loguearse
2. Ir a un ticket existente (Asistencia → Tickets)
3. Verificar que aparece botón "glpIA analizar" debajo de:
   - [ ] El textarea de descripción (si el ticket está en edición)
   - [ ] El textarea de seguimiento (al abrir el form de respuesta)
   - [ ] El textarea de tarea (al añadir tarea)
4. Pulsar el botón → debe aparecer modal con "Funcionalidad en desarrollo"

**Step 2: Verificar que no hay errores en consola**
- F12 → Console → sin errores JS
- Network → `ticket_buttons.js` carga con 200

---

## Riesgos y notas

| Riesgo | Mitigación |
|--------|------------|
| El tipo de item en GLPI 11 es `ITILFollowup`, no `TicketFollowup` | La lista `$target_types` ya usa los nombres correctos de GLPI 11 |
| Los forms de timeline se cargan vía Ajax después del DOMContentLoaded | El MutationObserver re-escaneará los nuevos nodos |
| TinyMCE reemplaza el textarea por un iframe | La detección busca `<textarea>`, no editores; TinyMCE crea un textarea oculto — ajustar si es necesario |
| El botón se añade en textareas de búsqueda/filtros también | Los filtros de `scanTextareas()` excluyen `.search-form`, `.filter-panel`, `[role="search"]` |
