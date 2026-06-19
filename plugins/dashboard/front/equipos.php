<?php
include('../../../inc/includes.php');

Session::checkLoginUser(); // Solo usuarios logueados

Html::header('Dashboard de Yago Otero', $_SERVER['PHP_SELF'], 'mydashboard', 'mydashboard-equipos');

echo '<div class="center">';
echo '<h1>Este es el plugin de Yago dibalo</h1>';
echo '<button>Pulsar</button>';
echo '</div>';

Html::footer();

