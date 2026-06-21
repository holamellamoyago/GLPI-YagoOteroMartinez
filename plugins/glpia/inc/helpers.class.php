<?php

class PluginGlpiaHelpers
{
    static function openForm(string $method = 'post')
    {
        echo '<form method="' . $method . '" action="' . $_SERVER['PHP_SELF'] . '">';
        echo '<div class="card">';
        echo '<div class="card-body">';
    }
}
