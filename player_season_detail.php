<?php
require_once 'db.php'; // Menggunakan $players_collection dari db.php
// include 'header.php'; // Anda bisa uncomment ini jika header.php berisi navigasi/layout umum

// Ambil playerID dan year dari GET request
$playerID_param = $_GET['playerID'] ?? '';
$year_param = $_GET['year'] ?? '';
$team_param = $_GET['team'] ?? ''; // Ambil parameter tim juga jika ada

if (!$playerID_param || !$year_param) {
    // Jika header di-include, mungkin lebih baik redirect atau tampilkan pesan dalam layout
    echo "<div class='p-4 text-red-700 bg-red-100 border border-red-400 rounded'>Error: Parameter playerID dan tahun (year) diperlukan. <a href='player_stats.php' class='underline'>Kembali ke daftar</a></div>";
    exit;
}

// Konversi tahun ke integer
$year_param_int = (int)$year_param;

// Ambil data pemain utama dan musim spesifik menggunakan aggregation
$pipeline = [
    [
        '$match' => [
            'playerID' => $playerID_param
        ]
    ],
    [
        '$unwind' => '$career_teams' // Bongkar array career_teams
    ],
    [
        '$match' => [
            'career_teams.year' => $year_param_int
            // Jika Anda juga ingin memfilter berdasarkan tim spesifik untuk musim itu (jika ada beberapa stint)
            // , 'career_teams.tmID' => $team_param // Uncomment dan pastikan $team_param ada jika perlu
        ]
    ],
    [
        '$limit' => 1 // Kita hanya butuh satu entri musim yang cocok (atau yang pertama jika ada beberapa stint dalam setahun dan tim tidak difilter)
    ]
];

$result = $players_collection->aggregate($pipeline)->toArray();
$playerSeasonDetail = null;
$playerName = $playerID_param; // Default jika pemain tidak ditemukan

if (!empty($result)) {
    $doc = $result[0]; // Ambil dokumen pertama (karena ada $limit: 1)
    $playerSeasonDetail = $doc['career_teams']; // Data musim spesifik ada di sini
    $playerName = trim(($doc['useFirst'] ?? ($doc['firstName'] ?? '')) . ' ' . ($doc['lastName'] ?? ''));
    
    // Tambahkan informasi pemain utama ke $playerSeasonDetail jika perlu ditampilkan
    $playerSeasonDetail['playerFirstName'] = $doc['firstName'] ?? '';
    $playerSeasonDetail['playerLastName'] = $doc['lastName'] ?? '';
    $playerSeasonDetail['playerPos'] = $doc['pos'] ?? '-';
    // Anda bisa tambahkan field lain dari dokumen pemain utama jika mau
    $playerSeasonDetail['college'] = $doc['college'] ?? '-';
    $playerSeasonDetail['birthDate'] = $doc['birthDate'] ?? '-';

    // Data untuk awards dan allstar (jika ingin ditampilkan di halaman ini)
    $playerSeasonDetail['awards_for_year'] = [];
    if (isset($doc['player_awards']) && is_array($doc['player_awards'])) {
        foreach ($doc['player_awards'] as $award) {
            if (isset($award['year']) && $award['year'] == $year_param_int) {
                $playerSeasonDetail['awards_for_year'][] = $award;
            }
        }
    }

    $playerSeasonDetail['allstar_for_season'] = null;
    if (isset($doc['allstar_games']) && is_array($doc['allstar_games'])) {
        foreach ($doc['allstar_games'] as $allstarGame) {
            // season_id di allstar_games mungkin merepresentasikan tahun dimulainya musim,
            // jadi cocokkan dengan tahun yang dicari.
            if (isset($allstarGame['season_id']) && $allstarGame['season_id'] == $year_param_int) {
                $playerSeasonDetail['allstar_for_season'] = $allstarGame;
                break; 
            }
        }
    }
     // Informasi draft (jika relevan dengan tahun tersebut atau ingin ditampilkan)
    $playerSeasonDetail['draft_details'] = $doc['draft_info'] ?? null;


} else {
    // Coba ambil nama pemain saja jika data musim tidak ditemukan
    $playerDoc = $players_collection->findOne(['playerID' => $playerID_param], ['projection' => ['firstName' => 1, 'lastName' => 1, 'useFirst' => 1]]);
    if ($playerDoc) {
        $playerName = trim(($playerDoc['useFirst'] ?? ($playerDoc['firstName'] ?? '')) . ' ' . ($playerDoc['lastName'] ?? ''));
    }
}

// Hitung statistik per game jika data ditemukan
$statsPerGame = [];
if ($playerSeasonDetail && isset($playerSeasonDetail['GP']) && $playerSeasonDetail['GP'] > 0) {
    $gp = $playerSeasonDetail['GP'];
    $statsPerGame = [
        'ppg' => round(($playerSeasonDetail['points'] ?? 0) / $gp, 1),
        'apg' => round(($playerSeasonDetail['assists'] ?? 0) / $gp, 1),
        'rpg' => round(($playerSeasonDetail['rebounds'] ?? 0) / $gp, 1),
        'spg' => round(($playerSeasonDetail['steals'] ?? 0) / $gp, 1),
        'bpg' => round(($playerSeasonDetail['blocks'] ?? 0) / $gp, 1),
        'tpg' => round(($playerSeasonDetail['turnovers'] ?? 0) / $gp, 1),
        'fg_percent' => (isset($playerSeasonDetail['fgAttempted']) && $playerSeasonDetail['fgAttempted'] > 0) ? round(($playerSeasonDetail['fgMade'] / $playerSeasonDetail['fgAttempted']) * 100, 1) : 0,
        'ft_percent' => (isset($playerSeasonDetail['ftAttempted']) && $playerSeasonDetail['ftAttempted'] > 0 && isset($playerSeasonDetail['ftMade'])) ? round(($playerSeasonDetail['ftMade'] / $playerSeasonDetail['ftAttempted']) * 100, 1) : 0,
        'three_percent' => (isset($playerSeasonDetail['threeAttempted']) && $playerSeasonDetail['threeAttempted'] > 0 && isset($playerSeasonDetail['threeMade'])) ? round(($playerSeasonDetail['threeMade'] / $playerSeasonDetail['threeAttempted']) * 100, 1) : 0,
        'mpg' => round(($playerSeasonDetail['minutes'] ?? 0) / $gp, 1),
    ];
}

// Ambil nama tim untuk tampilan
$teamNameForDisplay = $playerSeasonDetail['tmID'] ?? $team_param;
if (isset($playerSeasonDetail['tmID']) && isset($teams) && $teams instanceof MongoDB\Collection) {
    $teamDoc = $teams->findOne(['tmID' => $playerSeasonDetail['tmID']], ['projection' => ['name' => 1]]);
    if ($teamDoc && isset($teamDoc['name'])) {
        $teamNameForDisplay = $teamDoc['name'];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Season Detail: <?= htmlspecialchars($playerName) ?> - <?= htmlspecialchars($year_param) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F3F4F6; } /* gray-100 */
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .card { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.07); }
        .stat-label { color: #6B7280; /* gray-500 */ font-size: 0.875rem; /* text-sm */ }
        .stat-value { color: #1F2937; /* gray-800 */ font-size: 1.25rem; /* text-xl */ font-weight: 600; /* font-semibold */ }
        .section-title { color: #111827; /* gray-900 */ }
    </style>
</head>
<body class="text-gray-800 antialiased">
    <div class="container mx-auto p-4 md:p-8 max-w-5xl">
        
        <div class="mb-8 text-center">
            <a href="player_stats.php" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline mb-2 inline-block">
                <i class="fas fa-arrow-left mr-1"></i> Back to Player Stats
            </a>
            <h1 class="text-3xl md:text-4xl font-bold section-title font-condensed">
                <?= htmlspecialchars($playerName) ?>
            </h1>
            <p class="text-xl md:text-2xl text-slate-600">
                Season Statistics: <?= htmlspecialchars($year_param) ?>
                <?php if ($playerSeasonDetail && isset($playerSeasonDetail['tmID'])): ?>
                    <span class="text-lg">(<?= htmlspecialchars($teamNameForDisplay) ?>)</span>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($playerSeasonDetail): ?>
            <div class="card p-6 md:p-8 mb-8">
                <h2 class="text-2xl font-semibold mb-6 section-title border-b pb-3">Season Performance</h2>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 mb-6">
                    <div><p class="stat-label">Games Played (GP)</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['GP'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Games Started (GS)</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['GS'] ?? 'N/A') ?></p></div>
                    <div><p class="stat-label">Minutes</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['minutes'] ?? '-') ?></p></div>
                    <div><p class="stat-label">MPG</p><p class="stat-value"><?= htmlspecialchars($statsPerGame['mpg'] ?? '-') ?></p></div>
                </div>

                <h3 class="text-xl font-semibold mb-4 mt-6 text-slate-700">Per Game Averages</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4 mb-6">
                    <div><p class="stat-label">Points (PPG)</p><p class="stat-value text-blue-600"><?= htmlspecialchars($statsPerGame['ppg'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Assists (APG)</p><p class="stat-value text-green-600"><?= htmlspecialchars($statsPerGame['apg'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Rebounds (RPG)</p><p class="stat-value text-yellow-600"><?= htmlspecialchars($statsPerGame['rpg'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Steals (SPG)</p><p class="stat-value text-purple-600"><?= htmlspecialchars($statsPerGame['spg'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Blocks (BPG)</p><p class="stat-value text-orange-600"><?= htmlspecialchars($statsPerGame['bpg'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Turnovers (TPG)</p><p class="stat-value text-red-600"><?= htmlspecialchars($statsPerGame['tpg'] ?? '-') ?></p></div>
                </div>

                <h3 class="text-xl font-semibold mb-4 mt-6 text-slate-700">Shooting</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4 mb-6">
                    <div><p class="stat-label">FG Made / Att</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['fgMade'] ?? '-') ?> / <?= htmlspecialchars($playerSeasonDetail['fgAttempted'] ?? '-') ?></p></div>
                    <div><p class="stat-label">FG%</p><p class="stat-value"><?= htmlspecialchars($statsPerGame['fg_percent'] ?? '-') ?>%</p></div>
                    <div><p class="stat-label">FT Made / Att</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['ftMade'] ?? '-') ?> / <?= htmlspecialchars($playerSeasonDetail['ftAttempted'] ?? '-') ?></p></div>
                    <div><p class="stat-label">FT%</p><p class="stat-value"><?= htmlspecialchars($statsPerGame['ft_percent'] ?? '-') ?>%</p></div>
                    <div><p class="stat-label">3P Made / Att</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['threeMade'] ?? '-') ?> / <?= htmlspecialchars($playerSeasonDetail['threeAttempted'] ?? '-') ?></p></div>
                    <div><p class="stat-label">3P%</p><p class="stat-value"><?= htmlspecialchars($statsPerGame['three_percent'] ?? '-') ?>%</p></div>
                </div>
                
                <h3 class="text-xl font-semibold mb-4 mt-6 text-slate-700">Totals</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4">
                    <div><p class="stat-label">Total Points</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['points'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Total Assists</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['assists'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Total Rebounds</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['rebounds'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Off. Rebounds</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['oRebounds'] ?? 'N/A') ?></p></div>
                    <div><p class="stat-label">Def. Rebounds</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['dRebounds'] ?? 'N/A') ?></p></div>
                    <div><p class="stat-label">Total Steals</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['steals'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Total Blocks</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['blocks'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Total Turnovers</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['turnovers'] ?? '-') ?></p></div>
                    <div><p class="stat-label">Personal Fouls (PF)</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PF'] ?? '-') ?></p></div>
                </div>
            </div>

            <?php if (!empty($playerSeasonDetail['awards_for_year']) || $playerSeasonDetail['allstar_for_season']): ?>
            <div class="card p-6 md:p-8 mb-8">
                <h2 class="text-2xl font-semibold mb-6 section-title border-b pb-3">Accolades for <?= htmlspecialchars($year_param) ?> Season</h2>
                <?php if (!empty($playerSeasonDetail['awards_for_year'])): ?>
                    <h3 class="text-lg font-semibold mb-2 text-slate-700">Awards:</h3>
                    <ul class="list-disc list-inside space-y-1 text-slate-600 mb-4">
                        <?php foreach ($playerSeasonDetail['awards_for_year'] as $award): ?>
                            <li><?= htmlspecialchars($award['award']) ?> (<?= htmlspecialchars($award['lgID']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($playerSeasonDetail['allstar_for_season']): 
                    $asg = $playerSeasonDetail['allstar_for_season'];    
                ?>
                    <h3 class="text-lg font-semibold mb-2 mt-4 text-slate-700">All-Star Game Appearance:</h3>
                    <p class="text-slate-600 text-sm">
                        Conference: <?= htmlspecialchars($asg['conference'] ?? '-') ?>, 
                        Points: <?= htmlspecialchars($asg['points'] ?? '-') ?>, 
                        Rebounds: <?= htmlspecialchars($asg['rebounds'] ?? '-') ?>, 
                        Assists: <?= htmlspecialchars($asg['assists'] ?? '-') ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>


            <?php if (isset($playerSeasonDetail['PostGP']) && $playerSeasonDetail['PostGP'] > 0): ?>
            <div class="card p-6 md:p-8">
                <h2 class="text-2xl font-semibold mb-6 section-title border-b pb-3">Postseason Performance (<?= htmlspecialchars($year_param) ?>)</h2>
                 <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                    <div><p class="stat-label">Games Played (PostGP)</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PostGP']) ?></p></div>
                    <div><p class="stat-label">Points (PostPts)</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PostPoints']) ?></p></div>
                    <div><p class="stat-label">Rebounds (PostReb)</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PostRebounds']) ?></p></div>
                    <div><p class="stat-label">Assists (PostAst)</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PostAssists']) ?></p></div>
                    <!-- Tambahkan statistik postseason lainnya jika ada -->
                </div>
            </div>
            <?php endif; ?>


        <?php else: ?>
            <div class="card p-8 text-center">
                <i class="fas fa-ghost fa-4x text-slate-300 mb-4"></i>
                <p class="text-xl font-semibold text-slate-700">No detailed statistics found for <?= htmlspecialchars($playerName) ?> for the <?= htmlspecialchars($year_param) ?> season.</p>
                <p class="text-slate-500 mt-2">The player may not have played in this season, or the data is unavailable.</p>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>