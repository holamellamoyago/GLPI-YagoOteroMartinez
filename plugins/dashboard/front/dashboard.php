<?php
include('../../../inc/includes.php');

Session::checkLoginUser(); // Solo usuarios logueados

Html::header('Dashboard de Yago Otero', $_SERVER['PHP_SELF'], 'plugins', 'dashboard');
echo '<div class="center">';
echo '<h1>Este es el plugin de Yago</h1>';
echo '</div>';
Html::footer();

