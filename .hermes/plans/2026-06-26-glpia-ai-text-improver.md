# glpIA — Botón de mejora de texto con IA en tickets

> **Goal:** Añadir un botón debajo de cada textarea en los tickets de GLPI (seguimientos, tareas, soluciones, descripción) que al pulsar llame a DeepSeek para mejorar el texto usando el contexto completo de la conversación del ticket.

**Stack:** PHP (GLPI hook + AJAX endpoint), JavaScript (vanilla + jQuery, MutationObserver), Bootstrap 5 (modal), DeepSeek API.

---

## Fase 0: Entender lo que YA existe

El plugin `glpia` ya tiene:
- `setup.php` — registra menú, CSRF
- `inc/corrector.class.php` — CommonGLPI para entrada de menú
- `inc/helpers.class.php` — helper `openForm()`
- `front/glpia.php` — página standalone con formulario de prueba

Lo que **falta** (y que ya estaba planeado en el plan anterior pero NO implementado):
- `hook.php` — handler del hook `post_item_form`
- `js/ticket_buttons.js` — JS para inyectar botones + modal placeholder
- Registro del hook en `setup.php`

Este plan **reemplaza y amplía** el plan anterior `2026-06-22_120000-glpia-ticket-button.md`.

---

## Arquitectura

```
plugins/glpia/
├── setup.php              ← [MODIFICAR] Añadir registro de hook + JS/CSS
├── hook.php               ← [CREAR] Handler de post_item_form
├── inc/
│   ├── corrector.class.php   ← (existe, sin cambios)
│   ├── helpers.class.php     ← [AMPLIAR] Añadir método para llamar a DeepSeek
│   └── apiconsumer.class.php ← [CREAR] Clase para llamadas HTTP a APIs
├── ajax/
│   └── improve_text.php   ← [CREAR] Endpoint AJAX que recibe ticket_id + texto, devuelve texto mejorado
├── js/
│   └── ticket_buttons.js  ← [CREAR] Botones en textareas + modal con lógica de IA
├── css/
│   └── glpia.css          ← [CREAR] Estilos (spinner, botón, modal)
└── front/
    └── glpia.php          ← (existe, sin cambios)
```

### Flujo de datos

```
Usuario escribe en textarea
       ↓
Pulsa botón "glpIA mejorar"
       ↓
JS captura: ticket_id (de la URL), itemtype, texto actual
       ↓
POST AJAX → /plugins/glpia/ajax/improve_text.php
       ↓
PHP: obtiene ticket_id → busca todos los mensajes del ticket (descripción, followups, tareas, soluciones)
       ↓
PHP: construye prompt para DeepSeek con contexto + texto a mejorar
       ↓
PHP: llama a DeepSeek API → recibe respuesta
       ↓
JSON response → JS muestra resultado en modal
       ↓
Usuario pulsa "Usar este texto" → se reemplaza el contenido del textarea
```

---

## Task 1: Crear `inc/apiconsumer.class.php` — Cliente HTTP genérico

**Archivo:** `plugins/glpia/inc/apiconsumer.class.php`

Clase `PluginGlpiaApiConsumer` con método `postJson(string $url, array $data, array $headers = []): array`

Usa `Toolbox::callCurl()` de GLPI (mismo patrón que `PluginDashboardApiConsumer` en el plugin dashboard).

```php
class PluginGlpiaApiConsumer
{
    static function postJson(string $url, array $data, array $headers = []): array
    {
        $out = [
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json',
                'Content-Type: application/json',
            ], $headers),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
        ];

        $error = null;
        $response = Toolbox::callCurl($url, $out, $error);

        if ($error !== null || empty($response)) {
            return ['error' => $error ?? 'Empty response'];
        }

        return json_decode($response, true) ?? ['error' => 'Invalid JSON'];
    }
}
```

**Verificación:** `php -l` sin errores.

---

## Task 2: Crear `ajax/improve_text.php` — Endpoint de mejora de texto

**Archivo:** `plugins/glpia/ajax/improve_text.php`

### Qué hace:

1. Bootstrap GLPI (`include('../../../inc/includes.php')`)
2. Verifica sesión (`Session::checkLoginUser()`)
3. Lee `$_POST['ticket_id']`, `$_POST['text']`, `$_POST['itemtype']`
4. Obtiene TODO el contexto del ticket:
   - `Ticket::getById($ticket_id)` → contenido, name
   - `ITILFollowup::getForItem($ticket)` → todos los seguimientos
   - `TicketTask::getForItem($ticket)` → todas las tareas
   - `ITILSolution::getForItem($ticket)` → todas las soluciones
5. Construye el prompt para DeepSeek
6. Llama a `PluginGlpiaApiConsumer::postJson()` a la API de DeepSeek
7. Devuelve JSON con `{success: true, improved_text: "..."}` o `{success: false, error: "..."}`

### API de DeepSeek:

- URL: `https://api.deepseek.com/v1/chat/completions`
- Model: `deepseek-chat`
- API key: de una constante o variable de entorno (usar `DEEPSEEK_API_KEY` como env var del contenedor Docker)
- Temperature: 0.7

### Prompt:

```
Eres un asistente que mejora textos técnicos de tickets de soporte IT escritos por técnicos.
Tu tarea es reescribir el siguiente texto para que sea más profesional, claro y detallado.

Contexto del ticket:
[Título del ticket]
[Descripción]
[Seguimientos previos]
[Tareas]
[Soluciones previas]

Texto original a mejorar:
[texto del usuario]

Reglas:
- NO inventes información que no esté en el contexto
- Mantén el idioma original (español)
- Sé conciso pero completo
- Si el texto habla de una solución, explica qué se hizo y cómo
- Si es un seguimiento, incluye el estado actual
- NO añadas saludos ni despedidas
- Devuelve SOLO el texto mejorado, sin explicaciones adicionales
```

### API key:

Usar variable de entorno del contenedor Docker. Añadir `-e DEEPSEEK_API_KEY=sk-xxx` al `docker run`.

---

## Task 3: Crear `hook.php` — Handler del hook post_item_form

**Archivo:** `plugins/glpia/hook.php`

### Qué hace:

- Implementa `plugin_glpia_post_item_form($params)`
- Filtra por tipos: `Ticket`, `ITILFollowup`, `TicketTask`, `ITILSolution`
- Inyecta el JS `ticket_buttons.js` UNA sola vez por página (static flag)
- Inyecta el CSS `glpia.css`

```php
function plugin_glpia_post_item_form($params)
{
    $item = $params['item'] ?? null;
    if (!$item) return;

    $target_types = ['Ticket', 'ITILFollowup', 'TicketTask', 'ITILSolution'];
    if (!in_array($item->getType(), $target_types)) return;

    static $injected = false;
    if (!$injected) {
        $injected = true;
        $js_path = Plugin::getWebDir('glpia') . '/js/ticket_buttons.js';
        $css_path = Plugin::getWebDir('glpia') . '/css/glpia.css';
        echo '<script src="' . $js_path . '"></script>';
        echo '<link rel="stylesheet" href="' . $css_path . '">';
    }
}
```

---

## Task 4: Crear `js/ticket_buttons.js` — Botones + Modal + Llamada IA

**Archivo:** `plugins/glpia/js/ticket_buttons.js`

### Comportamiento:

1. Al cargar la página, escanea todos los `<textarea>` visibles
2. Añade un botón "glpIA mejorar" debajo de cada uno (con clase marcadora para evitar duplicados)
3. MutationObserver para textareas de forms cargados dinámicamente (timeline Ajax)
4. Al pulsar el botón:
   - Extrae `ticket_id` de la URL (`/ticket.form.php?id=XXX`)
   - Detecta el itemtype del textarea (por el form que lo contiene)
   - Abre modal Bootstrap 5 con:
     - Texto original en un card
     - Botón "Mejorar con IA"
     - Al pulsar: loading spinner → POST al endpoint AJAX → muestra resultado
     - Botón "Usar este texto" → reemplaza el textarea y cierra modal
     - Botón "Cerrar"

### Estructura del modal:

```
┌─────────────────────────────────────┐
│ glpIA - Mejorar texto              X│
├─────────────────────────────────────┤
│ Texto original:                     │
│ ┌─────────────────────────────────┐ │
│ │ "todo funciona bien"            │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [ Mejorar con IA ]                  │
│                                     │
│ ⏳ Mejorando... (loading spinner)   │
│                                     │
│ Resultado:                          │
│ ┌─────────────────────────────────┐ │
│ │ "Se solucionó el problema de X  │ │
│ │  aplicando la configuración Y.  │ │
│ │  Verificado funcionamiento OK." │ │
│ └─────────────────────────────────┘ │
│                                     │
│     [ Usar este texto ]  [ Cerrar ] │
└─────────────────────────────────────┘
```

### Detección del ticket_id:

```javascript
var ticketId = null;
var match = window.location.search.match(/[?&]id=(\d+)/);
if (match) ticketId = match[1];
// También buscar en el DOM si no está en URL:
if (!ticketId) {
    var idInput = document.querySelector('input[name="id"], input[name="tickets_id"]');
    if (idInput) ticketId = idInput.value;
}
```

---

## Task 5: Crear `css/glpia.css` — Estilos del botón y modal

**Archivo:** `plugins/glpia/css/glpia.css`

Estilos mínimos:
- Botón glpIA: color distintivo (branding), margen inferior
- Spinner de carga
- Animación de fade para el resultado

---

## Task 6: Modificar `setup.php` — Registrar hook, JS y CSS

**Archivo:** `plugins/glpia/setup.php`

Añadir en `plugin_init_glpIA()`:

```php
// Cargar hook handlers
require_once __DIR__ . '/hook.php';
$PLUGIN_HOOKS['post_item_form']['glpia'] = 'plugin_glpia_post_item_form';
```

Queda:

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

---

## Task 7: Configurar API Key de DeepSeek en el contenedor Docker

Añadir variable de entorno al contenedor `glpi_server`:

```bash
# Opción A: Recrear el contenedor con -e DEEPSEEK_API_KEY=sk-xxx
# Opción B: Pasar la key como variable de entorno en el docker run existente
```

La key se lee en PHP con `getenv('DEEPSEEK_API_KEY')`.

---

## Task 8: Re-activar el plugin y verificar

```bash
# Desactivar y reactivar para que GLPI relea los hooks
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:deactivate --allow-superuser glpia
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:activate --allow-superuser glpia

# Verificar
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:list --allow-superuser | grep glpia
```

---

## Task 9: Pruebas funcionales

1. Abrir `http://localhost:81`, loguearse
2. Ir a un ticket existente con mensajes
3. Verificar botón "glpIA mejorar" debajo de textareas de:
   - [ ] Seguimiento (abrir form de respuesta)
   - [ ] Tarea (añadir tarea)
   - [ ] Solución (abrir form de solución)
4. Escribir texto tipo "arreglado todo ok"
5. Pulsar botón → modal → "Mejorar con IA" → ver spinner → ver resultado mejorado
6. Pulsar "Usar este texto" → textarea se actualiza
7. F12 → sin errores en consola ni Network

---

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| DeepSeek API key expuesta en cliente | La key solo vive en el backend PHP (nunca en JS); el endpoint AJAX la lee de `getenv()` |
| GLPI 11.0.7 y formularios dinámicos | MutationObserver ya maneja los forms de la timeline cargados vía Ajax |
| TinyMCE enmascara el textarea | Si GLPI usa TinyMCE, el textarea real está oculto. Hay que buscar el editor activo y usar `tinymce.activeEditor.setContent()` |
| Ticket sin mensajes previos (contexto vacío) | El prompt igual funciona — solo usa el texto actual sin contexto adicional |
| Timeout de la API (>30s) | Configurar CURLOPT_TIMEOUT a 60s y mostrar spinner mientras |
| La API key no está configurada | El endpoint devuelve error claro: "DeepSeek API key not configured" |
| Coste de API | DeepSeek es barato (~$0.27/M tokens). Solo se llama bajo demanda cuando el usuario pulsa el botón |
| Botón duplicado por MutationObserver | Dataset flag (`data-glpia-done`) previene duplicados por textarea |

---

## Orden de implementación

1. **Task 1**: `apiconsumer.class.php` (dependencia de Task 2)
2. **Task 2**: `improve_text.php` (dependencia de Task 4)
3. **Task 3**: `hook.php` (dependencia de Task 6)
4. **Task 4**: `ticket_buttons.js` (el grueso del frontend)
5. **Task 5**: `glpia.css`
6. **Task 6**: modificar `setup.php`
7. **Task 7**: configurar API key en Docker
8. **Task 8**: reactivar plugin
9. **Task 9**: pruebas funcionales

Tasks 1-3 pueden hacerse en paralelo. Tasks 4-5 en paralelo. Tasks 6-9 son secuenciales.
