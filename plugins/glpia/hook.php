<?php

/**
 * Hook implementations for glpIA plugin.
 *
 * Handles GLPI hook callbacks to inject JavaScript and CSS
 * into ticket forms (description, followups, tasks, solutions).
 *
 * @since 1.0.0
 */

/**
 * POST_ITEM_FORM hook — called when GLPI renders any item form.
 *
 * Injects the glpIA JavaScript and CSS once per page into
 * Ticket, ITILFollowup, TicketTask, and ITILSolution forms.
 *
 * @param array $params  Contains 'item' (the object being displayed)
 *                       and 'options' (display parameters)
 *
 * @return void
 */
function plugin_glpia_post_item_form(array $params): void
{
    $item = $params['item'] ?? null;
    if (!$item) {
        return;
    }

    // Only inject into ticket-related item types
    $targetTypes = [
        'Ticket',
        'ITILFollowup',
        'TicketTask',
        'ITILSolution',
    ];

    if (!in_array($item->getType(), $targetTypes, true)) {
        return;
    }

    // Inject JS and CSS only ONCE per page load
    // (GLPI calls this hook from 6 different Twig templates per page)
    static $injected = false;
    if ($injected) {
        return;
    }
    $injected = true;

    // Get plugin info for cache-busting version param
    $pluginInfo = plugin_version_glpIA();
    $version = $pluginInfo['version'] ?? '1.0.0';
    $v = '?v=' . $version;

    $jsPath  = Plugin::getWebDir('glpia') . '/js/ticket_buttons.js' . $v;
    $cssPath = Plugin::getWebDir('glpia') . '/css/glpia.css' . $v;

    echo '<script src="' . htmlspecialchars($jsPath) . '"></script>' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars($cssPath) . '">' . "\n";
}
