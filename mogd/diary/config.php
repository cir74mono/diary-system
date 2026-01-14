<?php

$genres = [
    'black' => 'ANIME&MANGA',
    'white' => 'GAME',
    'red'   => 'STREAMER&VTUBER',
    'blue'  => 'OTHER'
];

if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>