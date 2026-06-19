<?php
class PluginDashboardDashboard extends \CommonGLPI
{
    private static $arr;

    static function getMenuContent()
    {
        self::$arr = ['title' => __('Dashboard', 'dashboard'),
            'page' => '/plugins/dashboard/front/dashboard.php',
            'icon' => 'ti ti-dashboard',
        ];

        return self::$arr;
    }
}