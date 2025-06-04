<?php
// db.php - koneksi MongoDB
require_once 'autoload.php';
$client = new MongoDB\Client('mongodb://mongo:mongo@localhost:27017/');
// $client = new MongoDB\Client();
$db = $client->nba_projek;

// Koleksi
$players = $db->players;
$playersTeams = $db->players_teams;
$allstarPlayers = $db->player_allstar;
$teams = $db->teams;
$playoffsCollection = $db->series_post;
