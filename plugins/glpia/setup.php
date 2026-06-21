<?php


function plugin_version_glpIA(): array
{
    return [
        'name' => 'glpIA',
        'version' => '1.0.0',
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
    $PLUGIN_HOOKS['menu_toadd']['glpia'] = ['glpia' => ['PluginGlpiaCorrector']];


}

function plugin_glpIA_check_prerequisites()
{
    return true;
}

function plugin_glpIA_check_config()
{
    return true;
}
