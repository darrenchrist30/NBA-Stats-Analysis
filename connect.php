<?php

require_once 'autoload.php';
$client = new MongoDB\Client('');

$collection = $client->nba_projek->players;
