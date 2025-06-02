<?php
require_once 'db.php';

$searchTerm = $_GET['q'] ?? '';

$result = [];

if ($searchTerm) {
    // MongoDB regex case insensitive untuk first_name atau last_name
    $regex = new MongoDB\BSON\Regex($searchTerm, 'i');
    $cursor = $players->find([
        '$or' => [
            ['firstName' => $regex],
            ['lastName' => $regex],
        ]
    ], ['limit' => 10]);

    foreach ($cursor as $player) {
        $result[] = [
            'playerID' => $player['playerID'],
            'name' => ($player['firstName'] ?? '') . ' ' . ($player['lastName'] ?? ''),
            'pos' => $player['pos'] ?? '-',
            'team' => $player['teamID'] ?? '-',
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($result);
exit;
