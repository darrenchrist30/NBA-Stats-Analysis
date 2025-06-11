<?php
// api_search.php

// Memuat koneksi database dan variabel collection Anda
require 'db.php';

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 3) {
    echo json_encode(['result_type' => 'empty', 'content' => []]);
    exit;
}

$exact_match_regex = new MongoDB\BSON\Regex('^' . preg_quote($q) . '$', 'i');

// A. Cari Player Card
$player_pipeline = [
    ['$addFields' => ['fullName' => ['$concat' => ['$firstName', ' ', '$lastName']]]],
    ['$match' => [
        '$or' => [
            ['fullName' => $exact_match_regex],
            ['useFirst' => $exact_match_regex]
        ]
    ]],
    ['$limit' => 1]
];
$player_cursor = $players_collection->aggregate($player_pipeline);
$player_card_data = current($player_cursor->toArray());

if ($player_card_data) {
    $achievements = [];
    if (!empty($player_card_data['player_awards']) && (is_array($player_card_data['player_awards']) || is_object($player_card_data['player_awards']))) {
        $award_count = 0;
        foreach ($player_card_data['player_awards'] as $award) {
            if ($award_count >= 5) break;
            if (!empty($award['award'])) {
                $achievements[] = ($award['award']) . (isset($award['year']) ? ' (' . $award['year'] . ')' : '');
                $award_count++;
            }
        }
    }

    $playerNameForUrl = trim(($player_card_data['useFirst'] ?? '') ?: ($player_card_data['firstName'] ?? '') . ' ' . ($player_card_data['lastName'] ?? ''));

    $response = [
        'result_type' => 'card',
        'content' => [
            'type' => 'player',
            'name' => $playerNameForUrl,
            'imageUrl' => "https://www.basketball-reference.com/req/202106291/images/headshots/" . strtolower(substr($player_card_data['lastName'], 0, 5)) . substr(strtolower($player_card_data['firstName']), 0, 2) . "01.jpg",
            'description' => "Detailed statistics and career path for " . $playerNameForUrl . ", a prominent figure in the NBA.",
            'biodata' => [
                'Born' => !empty($player_card_data['birthDate']) ? date('F j, Y', strtotime($player_card_data['birthDate'])) : 'N/A',
                'From' => trim(($player_card_data['birthCity'] ?? '') . ', ' . ($player_card_data['birthCountry'] ?? ''), ' ,'),
                'College' => $player_card_data['college'] ?? 'N/A',
                'Position' => $player_card_data['pos'] ?? 'N/A',
                'Height / Weight' => ($player_card_data['height'] ?? 'N/A') . ' in / ' . ($player_card_data['weight'] ?? 'N/A') . ' lbs',
            ],
            'achievements' => $achievements,
            // FIX: Mengarahkan ke player_stats.php dengan filter nama
            'url' => 'player_stats.php?players[]=' . urlencode($playerNameForUrl)
        ]
    ];
    echo json_encode($response);
    exit;
}

// B. Cari Team Card (dari coaches_collection)
$team_pipeline = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => ['teams.team_season_details.name' => $exact_match_regex]],
    ['$sort' => ['teams.team_season_details.year' => -1]],
    ['$limit' => 1],
    ['$replaceRoot' => ['newRoot' => '$teams.team_season_details']]
];
$team_cursor = $coaches_collection->aggregate($team_pipeline);
$team_card_data = current($team_cursor->toArray());

if ($team_card_data) {
    $teamIdForUrl = $team_card_data['tmID'] ?? '';
    $response = [
        'result_type' => 'card',
        'content' => [
            'type' => 'team',
            'name' => $team_card_data['name'] ?? 'Unknown Team',
            'imageUrl' => 'https://via.placeholder.com/400x300.png?text=' . urlencode($team_card_data['name'] ?? 'Team'),
            'description' => "The " . ($team_card_data['name'] ?? '') . " are a professional basketball team competing in the NBA.",
            'details' => [
                'Most Recent Season' => isset($team_card_data['year']) ? ((int)$team_card_data['year'] + 1) : 'N/A',
                'Record (W-L)' => ($team_card_data['won'] ?? '0') . ' - ' . ($team_card_data['lost'] ?? '0'),
                'Conference Rank' => $team_card_data['rank'] ?? 'N/A',
                'Playoff Result' => $team_card_data['playoff'] ?? 'Did not qualify',
            ],
            // FIX: Mengarahkan ke team_stats.php dengan filter ID tim
            'url' => 'team_stats.php?teams[]=' . urlencode($teamIdForUrl)
        ]
    ];
    echo json_encode($response);
    exit;
}


// 2. Jika tidak ada kecocokan persis, cari daftar saran (FALLBACK)
$partial_match_regex = new MongoDB\BSON\Regex(preg_quote($q), 'i');
$results = [];

// A. Cari Players
$player_list_pipeline = [
    ['$addFields' => ['fullName' => ['$concat' => ['$firstName', ' ', '$lastName']]]],
    ['$match' => ['$or' => [['fullName' => $partial_match_regex], ['useFirst' => $partial_match_regex]]]],
    ['$limit' => 5],
    ['$project' => ['_id' => 0, 'fullName' => 1, 'useFirst' => 1, 'pos' => 1]]
];
$player_list_cursor = $players_collection->aggregate($player_list_pipeline);

foreach ($player_list_cursor as $doc) {
    $playerNameForUrl = $doc['useFirst'] ?? $doc['fullName'];
    $results[] = [
        'type' => 'player',
        'name' => $playerNameForUrl,
        'info' => 'Position: ' . ($doc['pos'] ?? 'N/A'),
        // FIX: Mengarahkan ke player_stats.php dengan filter nama
        'url' => 'player_stats.php?players[]=' . urlencode($playerNameForUrl)
    ];
}

// B. Cari Teams
$team_list_pipeline = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => ['teams.team_season_details.name' => $partial_match_regex]],
    ['$group' => ['_id' => '$teams.team_season_details.name', 'tmID' => ['$first' => '$teams.team_season_details.tmID']]],
    ['$limit' => 5]
];
$team_list_cursor = $coaches_collection->aggregate($team_list_pipeline);
foreach ($team_list_cursor as $doc) {
    $teamIdForUrl = $doc['tmID'];
    $results[] = [
        'type' => 'team',
        'name' => $doc['_id'],
        'info' => 'NBA Team',
        // FIX: Mengarahkan ke team_stats.php dengan filter ID tim
        'url' => 'teams_stats.php?teams[]=' . urlencode($teamIdForUrl)
    ];
}

echo json_encode(['result_type' => 'list', 'content' => $results]);
