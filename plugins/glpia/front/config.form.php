<?php

/**
 * glpIA plugin configuration page.
 *
 * Allows setting:
 *   - DeepSeek API key
 *   - API endpoint URL
 *   - Model (dropdown with recommended options)
 *   - Temperature
 *   - Max tokens
 *   - Custom system prompt
 *
 * @since 1.0.0
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

// ── Load config ────────────────────────────────────────────
require_once __DIR__ . '/../inc/config.class.php';
$config = PluginGlpiaConfig::getAll();

// ── Handle form submission ─────────────────────────────────
$message      = '';
$message_type = 'info';

if (isset($_POST['save_config'])) {
    $model = $_POST['model'] ?? '';
    // If "custom" selected, use the custom input value
    if ($model === 'custom' && !empty($_POST['model_custom'])) {
        $model = trim($_POST['model_custom']);
    }

    PluginGlpiaConfig::save([
        'api_key'       => $_POST['api_key']       ?? '',
        'api_url'       => $_POST['api_url']       ?? '',
        'model'         => $model,
        'temperature'   => $_POST['temperature']   ?? '',
        'max_tokens'    => $_POST['max_tokens']    ?? '',
        'system_prompt' => $_POST['system_prompt'] ?? '',
    ]);

    $message      = 'Configuracion guardada correctamente.';
    $message_type = 'success';

    // Reload config after save
    $config = PluginGlpiaConfig::getAll();
}

// ── Page header ────────────────────────────────────────────
Html::header(
    'glpIA — Configuracion',
    $_SERVER['PHP_SELF'],
    'config',
    'plugin_glpia_config'
);

// ── Feedback message ───────────────────────────────────────
if ($message) {
    echo '<div class="alert alert-' . $message_type . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($message);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
    echo '</div>';
}

echo '<div class="card">';
echo '<div class="card-header">';
echo '<h5 class="card-title mb-0">';
echo '<i class="ti ti-robot me-2"></i>Configuracion de glpIA';
echo '</h5>';
echo '</div>';
echo '<div class="card-body">';

echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';

// ── API Settings section ───────────────────────────────────
echo '<h6 class="mb-3">Conexion a la API</h6>';

// API Key
echo '<div class="mb-3">';
echo '<label class="form-label" for="api_key">';
echo 'API Key <span class="text-danger">*</span>';
echo '</label>';
echo '<input type="password" name="api_key" id="api_key"';
echo ' class="form-control"';
echo ' value="' . htmlspecialchars($config['api_key'] ?? '') . '"';
echo ' placeholder="sk-...">';
echo '<div class="form-text">Clave API de DeepSeek (o del proveedor compatible).</div>';
echo '</div>';

// API URL
echo '<div class="mb-3">';
echo '<label class="form-label" for="api_url">URL de la API</label>';
echo '<input type="text" name="api_url" id="api_url"';
echo ' class="form-control"';
echo ' value="' . htmlspecialchars($config['api_url'] ?? '') . '">';
echo '<div class="form-text">Endpoint compatible con OpenAI Chat Completions.</div>';
echo '</div>';

// ── Model Settings section ─────────────────────────────────
echo '<h6 class="mb-3 mt-4">Parametros del modelo</h6>';

echo '<div class="row">';

// Model dropdown
echo '<div class="col-md-4 mb-3">';
echo '<label class="form-label" for="model">Modelo</label>';
echo '<select name="model" id="model" class="form-select">';

$models = [
    'deepseek-v4-flash' => 'DeepSeek V4 Flash (recomendado)',
    'deepseek-chat'     => 'DeepSeek V3 (deepseek-chat)',
    'deepseek-reasoner' => 'DeepSeek R1 (reasoner)',
    'gpt-4o-mini'       => 'OpenAI GPT-4o Mini',
    'gpt-4o'            => 'OpenAI GPT-4o',
    'custom'            => 'Personalizado...',
];

$selectedModel = $config['model'] ?? 'deepseek-v4-flash';
$isCustom = !array_key_exists($selectedModel, $models) && !empty($selectedModel);

foreach ($models as $value => $label) {
    $sel = ($value === $selectedModel && !$isCustom) ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($value) . '"' . $sel . '>'
        . htmlspecialchars($label) . '</option>';
}

if ($isCustom) {
    echo '<option value="' . htmlspecialchars($selectedModel)
        . '" selected>Personalizado: ' . htmlspecialchars($selectedModel) . '</option>';
}

echo '</select>';

// Custom model input
echo '<input type="text" name="model_custom" id="model_custom"';
echo ' class="form-control mt-2"';
echo ' style="display:' . ($isCustom ? 'block' : 'none') . '"';
echo ' placeholder="Escribe el nombre del modelo..."';
echo ' value="' . ($isCustom ? htmlspecialchars($selectedModel) : '') . '">';

echo '<div class="form-text">Modelo de IA para mejorar textos.</div>';
echo '</div>';

// Temperature
echo '<div class="col-md-4 mb-3">';
echo '<label class="form-label" for="temperature">Temperature</label>';
echo '<input type="number" name="temperature" id="temperature"';
echo ' class="form-control"';
echo ' step="0.1" min="0" max="2"';
echo ' value="' . htmlspecialchars($config['temperature'] ?? '0.7') . '">';
echo '<div class="form-text">0 = deterministico, 2 = muy creativo.</div>';
echo '</div>';

// Max tokens
echo '<div class="col-md-4 mb-3">';
echo '<label class="form-label" for="max_tokens">Max tokens</label>';
echo '<input type="number" name="max_tokens" id="max_tokens"';
echo ' class="form-control"';
echo ' min="50" max="8000"';
echo ' value="' . htmlspecialchars($config['max_tokens'] ?? '1000') . '">';
echo '<div class="form-text">Maximo de tokens en la respuesta.</div>';
echo '</div>';

echo '</div>'; // row

// ── System Prompt section ──────────────────────────────────
echo '<h6 class="mb-3 mt-4">Prompt del sistema</h6>';

echo '<div class="mb-3">';
echo '<label class="form-label" for="system_prompt">';
echo 'System Prompt (personalizado)';
echo '</label>';
echo '<textarea name="system_prompt" id="system_prompt"';
echo ' class="form-control"';
echo ' rows="10"';
echo '>' . htmlspecialchars($config['system_prompt'] ?? '') . '</textarea>';
echo '<div class="form-text">';
echo 'Si se deja en blanco, se usa el prompt por defecto de glpIA. ';
echo '<a href="#" id="load-default-prompt" class="text-decoration-none">';
echo '(cargar prompt por defecto)</a>';
echo '</div>';
echo '</div>';

// ── Save button ────────────────────────────────────────────
echo '<div class="mt-4">';
echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
echo '<input type="hidden" name="save_config" value="1">';
echo '<button type="submit" class="btn btn-primary">';
echo '<i class="ti ti-device-floppy me-1"></i> Guardar configuracion';
echo '</button>';
echo '</div>';

echo '</form>';

echo '</div>'; // card-body
echo '</div>'; // card

// ── JS for custom model toggle and default prompt loader ──
$defaultPromptJson = json_encode(PluginGlpiaConfig::getDefaultSystemPrompt());
echo <<<JS
<script>
(function() {
    var modelSelect = document.getElementById('model');
    var customInput = document.getElementById('model_custom');
    if (modelSelect && customInput) {
        modelSelect.addEventListener('change', function() {
            customInput.style.display = (this.value === 'custom') ? 'block' : 'none';
        });
    }
    var loadLink = document.getElementById('load-default-prompt');
    var promptArea = document.getElementById('system_prompt');
    if (loadLink && promptArea) {
        loadLink.addEventListener('click', function(e) {
            e.preventDefault();
            promptArea.value = $defaultPromptJson;
        });
    }
})();
</script>
JS;

Html::footer();
