<?php

class PluginDashboardPilotos extends \CommonGLPI
{

    static function getIcon(): string
    {
        return 'ti ti-helmet';
    }

    static function getMenuContent(): array|false
    {
        return [
            'title' => __('Pilotos F1', 'Pilotos F1'),
            'page' => '/plugins/dashboard/front/pilotos.php',
            'icon' => 'ti ti-dashboard',
        ];

    }
}