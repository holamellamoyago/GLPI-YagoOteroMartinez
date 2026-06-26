<?php


function plugin_version_glpIA(): array
{
    return [
        'name' => 'glpIA',
        'version' => '1.0.1',
        'author' => 'Yago',
        'license' => 'GPLv3+',
        'homepage' => '',
        'minGlpiVersion' => '11.0',
    ];
}

function plugin_glpIA_install(): bool
{
    return true;
}

function plugin_glpIA_uninstall(): bool
{
    return true;
}

function plugin_init_glpIA(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpia'] = true;
    $PLUGIN_HOOKS['menu_toadd']['glpia'] = [
        'glpia'  => ['PluginGlpiaCorrector'],
        'config' => ['PluginGlpiaConfigMenu'],
    ];

    // Hook para inyectar boton glpIA en textareas de tickets
    require_once __DIR__ . '/hook.php';
    $PLUGIN_HOOKS['post_item_form']['glpia'] = 'plugin_glpia_post_item_form';

    // Pagina de configuracion del plugin
    $PLUGIN_HOOKS['config_page']['glpia'] = 'front/config.form.php';
}

function plugin_glpIA_check_prerequisites()
{
    return true;
}

function plugin_glpIA_check_config()
{
    return true;
}
