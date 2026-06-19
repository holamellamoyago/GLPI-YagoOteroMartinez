<?php

function plugin_version_dashboard() {
    return [
        'name'           => 'Dashboard',
        'version'        => '1.0.0',
        'author'         => 'Yago',
        'license'        => 'GPLv3+',
        'homepage'       => '',
        'minGlpiVersion' => '11.0',
    ];
}

function plugin_dashboard_install(): bool {
    return true;
}

function plugin_dashboard_uninstall() :bool {
    return true;
}

function plugin_init_dashboard() :void {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['dashboard'] = true;
    //$PLUGIN_HOOKS['menu_entry']['dashboard'] = 'front/dashboard.class.php';
    $PLUGIN_HOOKS['menu_toadd']['dashboard'] = ['config' => 'PluginDashboardDashboard'];

}

function plugin_dashboard_check_prerequisites() {
    return true;
}

function plugin_dashboard_check_config() {
    return true;
}
