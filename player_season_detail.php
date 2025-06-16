<?php
require_once 'db.php';
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
            'career_teams.year' => $year_param_int,
            'career_teams.tmID' => $team_param
        ]
    ],
    [
        '$limit' => 1
    ]
];

$result = $players_collection->aggregate($pipeline)->toArray();
$playerSeasonDetail = null;
$playerName = $playerID_param; // Default jika pemain tidak ditemukan

if (!empty($result)) {
    $doc = $result[0];
    $playerSeasonDetail = $doc['career_teams'];
    $playerName = trim(($doc['useFirst'] ?? ($doc['firstName'] ?? '')) . ' ' . ($doc['lastName'] ?? ''));
    
    $playerSeasonDetail['playerFirstName'] = $doc['firstName'] ?? '';
    $playerSeasonDetail['playerLastName'] = $doc['lastName'] ?? '';
    $playerSeasonDetail['playerPos'] = $doc['pos'] ?? '-';
    $playerSeasonDetail['college'] = $doc['college'] ?? '-';
    $playerSeasonDetail['birthDate'] = isset($doc['birthDate']) ? date("F j, Y", strtotime($doc['birthDate'])) : '-';

    $playerSeasonDetail['awards_for_year'] = [];
    if (isset($doc['player_awards']) && (is_array($doc['player_awards']) || $doc['player_awards'] instanceof \MongoDB\Model\BSONArray)) {
        foreach ($doc['player_awards'] as $award) {
            if (isset($award['year']) && $award['year'] == $year_param_int) {
                $playerSeasonDetail['awards_for_year'][] = $award;
            }
        }
    }

    $playerSeasonDetail['allstar_for_season'] = null;
    if (isset($doc['allstar_games']) && (is_array($doc['allstar_games']) || $doc['allstar_games'] instanceof \MongoDB\Model\BSONArray)) {
        foreach ($doc['allstar_games'] as $allstarGame) {
            if (isset($allstarGame['season_id']) && $allstarGame['season_id'] == $year_param_int) {
                $playerSeasonDetail['allstar_for_season'] = $allstarGame;
                break; 
            }
        }
    }
    
    $playerSeasonDetail['draft_details'] = $doc['draft_info'] ?? null;
} else {
    $playerDoc = $players_collection->findOne(['playerID' => $playerID_param], ['projection' => ['firstName' => 1, 'lastName' => 1, 'useFirst' => 1]]);
    if ($playerDoc) {
        $playerName = trim(($playerDoc['useFirst'] ?? ($playerDoc['firstName'] ?? '')) . ' ' . ($playerDoc['lastName'] ?? ''));
    }
}

// Hitung statistik per game
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

// Ambil nama tim
$teamNameForDisplay = $team_param;
if (isset($playerSeasonDetail['tmID'])) {
    $teamDoc = $teams_collection->findOne(['tmID' => $playerSeasonDetail['tmID']], ['projection' => ['name' => 1]]);
    if ($teamDoc && isset($teamDoc['name'])) {
        $teamNameForDisplay = $teamDoc['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Season Detail: <?= htmlspecialchars($playerName) ?> - <?= htmlspecialchars($year_param_int + 1) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@500;600;700&family=Rajdhani:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { 
            font-family: 'Montserrat', sans-serif; 
            background-color: #0A0A14; 
            color: #E0E0E0;
        }
        .font-teko { font-family: 'Teko', sans-serif; }
        .font-rajdhani { font-family: 'Rajdhani', sans-serif; }
        .content-container {
            background-color: rgba(23, 23, 38, 0.7);
            border: 1px solid rgba(55, 65, 81, 0.4);
            border-radius: 0.75rem;
            padding: 1.5rem;
            backdrop-filter: blur(8px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .stat-card {
            background-color: rgba(31, 41, 55, 0.6);
            border: 1px solid rgba(55, 65, 81, 0.5);
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            background-color: rgba(55, 65, 81, 0.8);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .stat-label {
            font-size: 0.75rem; /* text-xs */
            color: #9ca3af; /* gray-400 */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stat-value {
            font-family: 'Teko', sans-serif;
            font-size: 2.5rem; /* text-4xl */
            font-weight: 600;
            line-height: 1.1;
            margin-top: 0.25rem;
        }
        .section-title {
            font-family: 'Rajdhani', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #60a5fa; /* blue-400 */
            border-bottom: 2px solid rgba(59, 130, 246, 0.2);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(55, 65, 81, 0.7);
            font-size: 0.9rem;
        }
        .detail-item:last-child { border-bottom: none; }
        .detail-label { color: #9ca3af; }
        .detail-value { color: #e5e7eb; font-weight: 600; }
    </style>
</head>
<body class="antialiased">
    <div class="max-w-6xl mx-auto p-4 md:p-6 lg:p-8">
        
        <header class="mb-8 text-center">
            <a href="player_stats.php" class="text-sm text-indigo-400 hover:text-indigo-300 hover:underline mb-2 inline-block">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Dashboard Pemain
            </a>
            <h1 class="text-4xl md:text-5xl font-bold text-gray-100 font-teko tracking-wider uppercase">
                <?= htmlspecialchars($playerName) ?>
            </h1>
            <p class="text-xl md:text-2xl text-indigo-400 font-rajdhani">
                Statistik Musim <?= htmlspecialchars($year_param_int + 1) ?>
            </p>
        </header>

        <?php if ($playerSeasonDetail): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kolom Kiri: Info Pemain -->
                <div class="lg:col-span-1 content-container">
                    <h2 class="section-title text-lg">Player Info</h2>
                    <div class="space-y-3">
                        <div class="detail-item"><span class="detail-label">Full Name</span> <span class="detail-value"><?= htmlspecialchars($playerName) ?></span></div>
                        <div class="detail-item"><span class="detail-label">Position</span> <span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['playerPos']) ?></span></div>
                        <div class="detail-item"><span class="detail-label">Team</span> <span class="detail-value"><?= htmlspecialchars($teamNameForDisplay) ?> (<?= htmlspecialchars($playerSeasonDetail['tmID']) ?>)</span></div>
                        <div class="detail-item"><span class="detail-label">College</span> <span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['college']) ?></span></div>
                        <div class="detail-item"><span class="detail-label">Birth Date</span> <span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['birthDate']) ?></span></div>
                        <?php if($playerSeasonDetail['draft_details']): ?>
                            <div class="detail-item"><span class="detail-label">Draft</span> <span class="detail-value"><?= $playerSeasonDetail['draft_details']['year'] ?? '' ?> Rnd <?= $playerSeasonDetail['draft_details']['round'] ?? '' ?> (#<?= $playerSeasonDetail['draft_details']['pick'] ?? '' ?>)</span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kolom Kanan: Statistik Utama -->
                <div class="lg:col-span-2 content-container">
                    <h2 class="section-title text-lg">Season Averages</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div class="stat-card"><p class="stat-label">Points</p><p class="stat-value text-blue-400"><?= htmlspecialchars($statsPerGame['ppg'] ?? '-') ?></p></div>
                        <div class="stat-card"><p class="stat-label">Assists</p><p class="stat-value text-green-400"><?= htmlspecialchars($statsPerGame['apg'] ?? '-') ?></p></div>
                        <div class="stat-card"><p class="stat-label">Rebounds</p><p class="stat-value text-yellow-400"><?= htmlspecialchars($statsPerGame['rpg'] ?? '-') ?></p></div>
                        <div class="stat-card"><p class="stat-label">Steals</p><p class="stat-value text-purple-400"><?= htmlspecialchars($statsPerGame['spg'] ?? '-') ?></p></div>
                        <div class="stat-card"><p class="stat-label">Blocks</p><p class="stat-value text-orange-400"><?= htmlspecialchars($statsPerGame['bpg'] ?? '-') ?></p></div>
                        <div class="stat-card"><p class="stat-label">Minutes</p><p class="stat-value text-teal-400"><?= htmlspecialchars($statsPerGame['mpg'] ?? '-') ?></p></div>
                    </div>
                </div>
            </div>

            <!-- Bagian Statistik Tambahan -->
            <div class="content-container mt-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    <!-- Shooting Stats -->
                    <div>
                        <h3 class="section-title text-base">Shooting</h3>
                        <div class="space-y-3">
                            <div class="detail-item"><span class="detail-label">FG Made / Att</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['fgMade'] ?? '-') ?> / <?= htmlspecialchars($playerSeasonDetail['fgAttempted'] ?? '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">FG%</span><span class="detail-value"><?= htmlspecialchars($statsPerGame['fg_percent'] ?? '-') ?>%</span></div>
                            <div class="detail-item"><span class="detail-label">3P Made / Att</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['threeMade'] ?? '-') ?> / <?= htmlspecialchars($playerSeasonDetail['threeAttempted'] ?? '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">3P%</span><span class="detail-value"><?= htmlspecialchars($statsPerGame['three_percent'] ?? '-') ?>%</span></div>
                            <div class="detail-item"><span class="detail-label">FT Made / Att</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['ftMade'] ?? '-') ?> / <?= htmlspecialchars($playerSeasonDetail['ftAttempted'] ?? '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">FT%</span><span class="detail-value"><?= htmlspecialchars($statsPerGame['ft_percent'] ?? '-') ?>%</span></div>
                        </div>
                    </div>
                    <!-- Total Stats -->
                    <div>
                        <h3 class="section-title text-base">Totals & Misc</h3>
                        <div class="space-y-3">
                            <div class="detail-item"><span class="detail-label">Games Played</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['GP'] ?? '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">Games Started</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['GS'] ?? 'N/A') ?></span></div>
                            <div class="detail-item"><span class="detail-label">Total Points</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['points'] ?? '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">Total Rebounds</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['rebounds'] ?? '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">Total Assists</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['assists'] ?? '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">Personal Fouls (PF)</span><span class="detail-value"><?= htmlspecialchars($playerSeasonDetail['PF'] ?? '-') ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accolades Section -->
            <?php if (!empty($playerSeasonDetail['awards_for_year']) || $playerSeasonDetail['allstar_for_season']): ?>
            <div class="content-container mt-6">
                <h2 class="section-title text-lg">Accolades for <?= htmlspecialchars($year_param_int + 1) ?> Season</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php if (!empty($playerSeasonDetail['awards_for_year'])): ?>
                        <div>
                            <h3 class="font-rajdhani text-base font-bold text-gray-300 mb-2"><i class="fas fa-award mr-2 text-yellow-400"></i>Awards</h3>
                            <ul class="space-y-2">
                                <?php foreach ($playerSeasonDetail['awards_for_year'] as $award): ?>
                                    <li class="bg-gray-800/50 p-3 rounded-md text-sm"><?= htmlspecialchars($award['award']) ?> (<?= htmlspecialchars($award['lgID']) ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($playerSeasonDetail['allstar_for_season']): $asg = $playerSeasonDetail['allstar_for_season']; ?>
                        <div>
                            <h3 class="font-rajdhani text-base font-bold text-gray-300 mb-2"><i class="fas fa-star mr-2 text-blue-400"></i>All-Star Appearance</h3>
                            <div class="bg-gray-800/50 p-3 rounded-md text-sm space-y-2">
                                <p><strong>Conference:</strong> <?= htmlspecialchars($asg['conference'] ?? '-') ?></p>
                                <p><strong>Stats:</strong> <?= htmlspecialchars($asg['points'] ?? '-') ?> pts, <?= htmlspecialchars($asg['rebounds'] ?? '-') ?> reb, <?= htmlspecialchars($asg['assists'] ?? '-') ?> ast</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Postseason Section -->
            <?php if (isset($playerSeasonDetail['PostGP']) && $playerSeasonDetail['PostGP'] > 0): ?>
            <div class="content-container mt-6">
                <h2 class="section-title text-lg">Postseason Performance (<?= htmlspecialchars($year_param_int + 1) ?>)</h2>
                 <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="stat-card"><p class="stat-label">Games Played</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PostGP']) ?></p></div>
                    <div class="stat-card"><p class="stat-label">Points</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PostPoints']) ?></p></div>
                    <div class="stat-card"><p class="stat-label">Rebounds</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PostRebounds']) ?></p></div>
                    <div class="stat-card"><p class="stat-label">Assists</p><p class="stat-value"><?= htmlspecialchars($playerSeasonDetail['PostAssists']) ?></p></div>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="content-container p-8 text-center">
                <i class="fas fa-ghost fa-4x text-slate-700 mb-4"></i>
                <p class="text-xl font-semibold text-slate-300">No detailed statistics found for <?= htmlspecialchars($playerName) ?> for the <?= htmlspecialchars($year_param_int + 1) ?> season.</p>
                <p class="text-slate-400 mt-2 text-sm">The player may not have played in this season, or the data is unavailable.</p>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>