<?php

class PluginGlpiaCorrector extends \CommonGLPI
{

    static function getIcon(): string
    {
        return 'ti ti-users-group';
    }

    public static function getMenuName()
    {
        return 'Nombre de menú';
    }

    static function getMenuContent(): array|false
    {
        return [
            'title' => __('glpia', 'glpIA'),
            'page' => '/plugins/glpia/front/glpia.php',
            'icon' => 'ti ti-users-group',
        ];

    }
}