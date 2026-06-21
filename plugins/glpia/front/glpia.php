<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

// ── Procesar formulario ─────────────────────────────────
$message = '';
$message_type = 'info';

if (isset($_POST['add'])) {
    $descripcion = $_POST['descripcion'] ?? '';
    $message = 'Acción "Crear" ejecutada' . ($descripcion ? ': ' . substr($descripcion, 0, 80) : '');
}

if (isset($_POST['delete'])) {
    $message = 'Acción "Eliminar" ejecutada';
    $message_type = 'warning';
}

if (isset($_POST['send_prompt'])) {

    echo Ajax::createModalWindow(
        'Modal de prueba',
        '/plugins/glpia/front/glpia.php',
        [
            'title' => 'Resultado del prmpt'
        ]);
}

// ── Header GLPI ─────────────────────────────────────────
Html::header('Panel central de glpIA', $_SERVER['PHP_SELF'], 'glpia', 'glpia-main');

// ── Título con breadcrumb ───────────────────────────────
Html::displayTitle("", '', '¿Qué te gustaría corregir?', "");

// ── Mensaje de feedback ─────────────────────────────────
if ($message) {
    echo '<div class="alert alert-' . $message_type . '">' . htmlspecialchars($message) . '</div>';
}

// ── Formulario ──────────────────────────────────────────
//TODO Implementar ahora el helpers
PluginGlpiaHelpers::openForm();

// Textarea: por defecto display=true, hace echo solo
Html::textarea([
    'name' => 'descripcion',
    'rows' => 5,
    'value' => $_POST['descripcion'] ?? '',
]);

// ── Botones (submit y link RETORNAN, hay que hacer echo) ─
echo '<div class="mt-3 d-flex gap-2">';

echo Html::submit('Crear', [
    'icon' => 'ti ti-plus',
    'class' => 'btn btn-primary',
    'name' => 'add',
]);

echo Html::submit("Enviar prompt", [
    'icon' => 'ti ti-plus',
    'class' => 'btn btn-primary',
    'name' => 'send_prompt',
]);

echo Html::submit('Eliminar', [
    'confirm' => '¿Estás seguro de que quieres eliminar?',
    'class' => 'btn btn-danger',
    'name' => 'delete',
]);

echo Html::link(
    'Pulsar',
    '#',
    ['class' => 'btn btn-secondary', 'onclick' => 'alert("Botón pulsado"); return false;']
);

echo '</div>'; // d-flex

echo '</div>'; // card-body
echo '</div>'; // card

// ── Cerrar formulario (CSRF token + </form>) ────────────

Html::closeForm();

// ── Debug ───────────────────────────────────────────────
echo '<p style="color:#FF1493; text-align:center; font-size:10px; margin-top:20px;">';
echo '[DEBUG] ' . date('Y-m-d H:i:s');
echo '</p>';

Html::footer();
