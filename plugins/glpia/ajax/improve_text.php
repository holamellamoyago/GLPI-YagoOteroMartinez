<?php

/**
 * glpIA — Text improvement AJAX endpoint.
 *
 * Receives a ticket ID + raw text, gathers all ticket conversation
 * context, sends it to DeepSeek API, and returns the improved text.
 *
 * POST parameters:
 *   - ticket_id  (int)    GLPI ticket ID
 *   - text       (string) Raw text to improve
 *   - itemtype   (string) Type of the form (Ticket, ITILFollowup, …)
 *
 * Returns JSON:
 *   { success: true,  improved_text: "..." }
 *   { success: false, error: "..." }
 *
 * @since 1.0.0
 */

// ── Bootstrap GLPI ─────────────────────────────────────────
defined('GLPI_ROOT') || define('GLPI_ROOT', dirname(__DIR__, 3));
include GLPI_ROOT . '/inc/includes.php';

Session::checkLoginUser();

// ── Input validation ───────────────────────────────────────
$ticketId = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
$rawText  = isset($_POST['text']) ? trim($_POST['text']) : '';
$itemtype = isset($_POST['itemtype']) ? $_POST['itemtype'] : '';

if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid ticket_id']);
    exit;
}

if ($rawText === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No text provided to improve']);
    exit;
}

// ── Load ticket ────────────────────────────────────────────
$ticket = new Ticket();
if (!$ticket->getFromDB($ticketId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Ticket not found']);
    exit;
}

// ── Gather conversation context ────────────────────────────

$contextParts = [];

// Ticket title and description
$contextParts[] = "Título: " . ($ticket->fields['name'] ?? '(sin título)');
if (!empty($ticket->fields['content'])) {
    $contextParts[] = "Descripción: " . strip_tags($ticket->fields['content']);
}

// Followups (ITILFollowup)
$followup = new ITILFollowup();
$followups = $followup->find(
    ['items_id' => $ticketId, 'itemtype' => 'Ticket'],
    ['date ASC']
);
foreach ($followups as $f) {
    $who = $f['users_id'] ? glpiaGetUserName($f['users_id']) : 'Sistema';
    $contextParts[] = "[Seguimiento de {$who}]: " . strip_tags($f['content']);
}

// Tasks (TicketTask)
$task = new TicketTask();
$tasks = $task->find(
    ['tickets_id' => $ticketId],
    ['date ASC']
);
foreach ($tasks as $t) {
    $who = $t['users_id_recipient'] ? glpiaGetUserName($t['users_id_recipient']) : 'Técnico';
    $contextParts[] = "[Tarea asignada a {$who}]: " . strip_tags($t['content']);
}

// Solutions (ITILSolution)
$solution = new ITILSolution();
$solutions = $solution->find(
    ['items_id' => $ticketId, 'itemtype' => 'Ticket'],
    ['date_creation ASC']
);
foreach ($solutions as $s) {
    $who = $s['users_id'] ? glpiaGetUserName($s['users_id']) : 'Sistema';
    $status = SolutionTemplate::getStatus($s['status'] ?? 0);
    $contextParts[] = "[Solución de {$who} — {$status}]: " . strip_tags($s['content']);
}

$conversationContext = implode("\n\n", $contextParts);

// ── Call AI API ────────────────────────────────────────────
require_once __DIR__ . '/../inc/config.class.php';

$apiKey  = PluginGlpiaConfig::get('api_key');
$apiUrl  = PluginGlpiaConfig::get('api_url') ?: 'https://api.deepseek.com/v1/chat/completions';
$model   = PluginGlpiaConfig::get('model') ?: 'deepseek-chat';
$temp    = (float) (PluginGlpiaConfig::get('temperature') ?: '0.7');
$maxTok  = (int) (PluginGlpiaConfig::get('max_tokens') ?: '1000');
$systemPrompt = PluginGlpiaConfig::getSystemPrompt();

if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'API key no configurada. Ve a Administracion > Configuracion > glpIA.']);
    exit;
}

$userMessage = "Contexto del ticket:\n" . $conversationContext
    . "\n\n---\n\nTexto original a mejorar:\n" . $rawText;

require_once __DIR__ . '/../inc/apiconsumer.class.php';

$response = PluginGlpiaApiConsumer::postJson(
    $apiUrl,
    [
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userMessage],
        ],
        'temperature' => $temp,
        'max_tokens'  => $maxTok,
    ],
    ['Authorization: Bearer ' . $apiKey]
);

// ── Parse response ─────────────────────────────────────────
if (isset($response['error'])) {
    $errMsg = is_array($response['error']) 
        ? ($response['error']['message'] ?? json_encode($response['error']))
        : $response['error'];
    echo json_encode(['success' => false, 'error' => 'API: ' . $errMsg]);
    exit;
}

$improvedText = $response['choices'][0]['message']['content'] ?? '';
if ($improvedText === '') {
    echo json_encode(['success' => false, 'error' => 'DeepSeek returned empty response']);
    exit;
}

echo json_encode([
    'success'       => true,
    'improved_text' => trim($improvedText),
    'token_usage'   => $response['usage'] ?? null,
]);

// ── Helper ─────────────────────────────────────────────────
function glpiaGetUserName(int $userId): string
{
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }

    $user = new User();
    if ($user->getFromDB($userId)) {
        $name = trim(
            ($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? '')
        );
        $cache[$userId] = $name ?: 'Usuario #' . $userId;
    } else {
        $cache[$userId] = 'Usuario #' . $userId;
    }
    return $cache[$userId];
}
