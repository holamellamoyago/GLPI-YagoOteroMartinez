<?php
include('../../../inc/includes.php');

Session::checkLoginUser(); // Solo usuarios logueados

Html::header('Dashboard de Yago Otero', $_SERVER['PHP_SELF'], 'mydashboard', 'mydashboard-pilotos');

Html::displayTitle('Hola');

Html::textarea();
Search::showList('Computer', []);
echo '<div class="center">';
echo '<h1>Pilotos de F1</h1>';
echo '<button>Pulsar</button>';
echo '</div>';

Html::footer();

