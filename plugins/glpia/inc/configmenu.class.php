<?php

/**
 * Menu entry for glpIA config page under Configuration section.
 *
 * Provides the menu item that links to the plugin's config form,
 * appearing under "Configuración" in the GLPI sidebar.
 *
 * @since 1.0.0
 */
class PluginGlpiaConfigMenu extends CommonGLPI
{
    public static function getMenuName(): string
    {
        return 'glpIA';
    }

    public static function getMenuContent(): array|false
    {
        return [
            'title' => 'glpIA',
            'page'  => '/plugins/glpia/front/config.form.php',
            'icon'  => 'ti ti-robot',
        ];
    }

    public static function getIcon(): string
    {
        return 'ti ti-robot';
    }
}
