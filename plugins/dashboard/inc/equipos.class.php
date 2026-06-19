<?php

class PluginDashboardEquipos extends \CommonGLPI
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
            'title' => __('Equipos', 'Teams'),
            'page' => '/plugins/dashboard/front/equipos.php',
            'icon' => 'ti ti-users-group',
        ];

    }
}