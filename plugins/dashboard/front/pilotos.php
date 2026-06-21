<?php
include('../../../inc/includes.php');

Session::checkLoginUser(); // Solo usuarios logueados

Html::header('Dashboard de Yago Otero', $_SERVER['PHP_SELF'], 'mydashboard', 'mydashboard-pilotos');

//Html::displayTitle('Hola');

//Html::textarea();
//Search::showList('Computer', []);

$url = 'https://api.openf1.org/v1';
$urlPilotos = $url . '/drivers';
$data = PluginDashboardApiConsumer::fetchData($urlPilotos);

// Volcamos el array entero a la pantalla para ver su estructura
//echo '<h3>Estructura del Array:</h3>';
//echo '<pre>';
//print_r($data);
//echo '</pre>';

if (isset($data['error'])) {
    echo '<div class="alert">Hay un error: ' . $data['error'] . '</div>';
    echo '<h3>Estructura del Array:</h3>';
    echo '<pre>';
    print_r($data);
    echo '</pre>';
} else {
    echo '<table class="tab_cadre_fixe">';
    echo '<tr><th>Nombre</th><th>Equipo</th><th>País</th></tr>';
    foreach ($data as $piloto) {
        echo '<tr class="tab_bg_1">';
        echo '<td>' . $piloto['full_name'] . '</td>';
        echo '<td>' . $piloto['team_name'] . '</td>';
        echo '<td>' . $piloto['country_code'] . '</td>';
        echo '</tr>';
    }

    echo '</table>';
}

//echo '<div class="center">';
//echo '<h1>Pilotos de F1</h1>';
//echo '<button>Pulsar</button>';
//echo '</div>';

Html::footer();
