<?php

require_once 'autoload.php';

$client = new MongoDB\Client('mongodb://mongo:mongo@localhost:27017/');
$collection = $client->nba_projek->players;


?>