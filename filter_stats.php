<?php
require_once 'db.php';

$playerID = $_GET['playerID'] ?? null;
$season = isset($_GET['season']) ? intval($_GET['season']) : null;
$team = $_GET['team'] ?? null;

$filter = [];
if ($playerID) $filter['playerID'] = $playerID;
if ($season) $filter['season_id'] = $season;
if ($team) $filter['tmID'] = $team;

$cursor = $players_teams->find($filter);

$results = [];
foreach ($cursor as $stat) {
    $results[] = [
        'season' => $stat['season_id'] ?? '',
        'team' => $stat['tmID'] ?? '',
        'games_played' => $stat['GP'] ?? 0,
        'points' => $stat['points'] ?? 0,
        'assists' => $stat['assists'] ?? 0,
        'rebounds' => $stat['rebounds'] ?? 0,
        'fg_made' => $stat['fgMade'] ?? 0,
        'fg_attempted' => $stat['fgAttempted'] ?? 0,
        'ft_made' => $stat['ftMade'] ?? 0,
        'ft_attempted' => $stat['ftAttempted'] ?? 0,
        'three_made' => $stat['threeMade'] ?? 0,
        'three_attempted' => $stat['threeAttempted'] ?? 0,
    ];
}

header('Content-Type: application/json');
echo json_encode($results);
