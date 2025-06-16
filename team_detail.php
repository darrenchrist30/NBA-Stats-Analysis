<?php
require_once 'db.php';
// include 'header.php'; // Uncomment jika Anda memiliki header umum

$year_param = $_GET['year'] ?? null;
$tmID_param = $_GET['tmID'] ?? null;
$team_season_filter_value_from_dashboard = $_GET['team_season_filter_dari_teams_stats'] ?? null; // Added from new logic

if (!$year_param || !$tmID_param) {
    die("Parameter tahun (year) dan ID Tim (tmID) diperlukan.");
}

$year_param_int = (int)$year_param;

// 1. Ambil data tim spesifik (User's Original Logic)
$pipelineTeamDetail = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => [
        'teams.team_season_details.year' => $year_param_int,
        'teams.team_season_details.tmID' => $tmID_param
    ]],
    ['$limit' => 1],
    ['$replaceRoot' => ['newRoot' => '$teams.team_season_details']]
];
$resultTeam = $coaches_collection->aggregate($pipelineTeamDetail)->toArray();
$teamData = null;
$teamNameToDisplay = $tmID_param;

if (!empty($resultTeam)) {
    $teamData = $resultTeam[0];
    $teamNameToDisplay = $teamData['name'] ?? $tmID_param;

    $totalGames = ($teamData['won'] ?? 0) + ($teamData['lost'] ?? 0);
    if ($totalGames > ($teamData['games'] ?? 0)) {
        $teamData['games'] = $totalGames;
    }


    $teamData['o_fgp'] = (isset($teamData['o_fga']) && $teamData['o_fga'] > 0) ? round(($teamData['o_fgm'] / $teamData['o_fga']) * 100, 1) : 0;
    $teamData['o_3pp'] = (isset($teamData['o_3pa']) && $teamData['o_3pa'] > 0) ? round(($teamData['o_3pm'] / $teamData['o_3pa']) * 100, 1) : 0;
    $teamData['o_ftp'] = (isset($teamData['o_fta']) && $teamData['o_fta'] > 0) ? round(($teamData['o_ftm'] / $teamData['o_fta']) * 100, 1) : 0;
    $teamData['d_fgp'] = (isset($teamData['d_fga']) && $teamData['d_fga'] > 0) ? round(($teamData['d_fgm'] / $teamData['d_fga']) * 100, 1) : 0; // Opponent FG%
    $teamData['d_3pp'] = (isset($teamData['d_3pa']) && $teamData['d_3pa'] > 0) ? round(($teamData['d_3pm'] / $teamData['d_3pa']) * 100, 1) : 0; // Opponent 3P%
    
    // BARU: Kalkulasi statistik rata-rata per game (PPG, RPG, APG, dll.)
    if (isset($teamData['games']) && $teamData['games'] > 0) {
        $games = $teamData['games'];
        // Rata-rata Ofensif per game
        $teamData['avg_o_pts'] = round(($teamData['o_pts'] ?? 0) / $games, 1);
        $teamData['avg_o_reb'] = round(($teamData['o_reb'] ?? 0) / $games, 1);
        $teamData['avg_o_asts'] = round(($teamData['o_asts'] ?? 0) / $games, 1);
        $teamData['avg_o_stl'] = round(($teamData['o_stl'] ?? 0) / $games, 1);
        $teamData['avg_o_blk'] = round(($teamData['o_blk'] ?? 0) / $games, 1);
        $teamData['avg_o_to'] = round(($teamData['o_to'] ?? 0) / $games, 1);

        // Rata-rata Defensif per game (statistik lawan)
        $teamData['avg_d_pts'] = round(($teamData['d_pts'] ?? 0) / $games, 1);
        $teamData['avg_d_reb'] = round(($teamData['d_reb'] ?? 0) / $games, 1);
        $teamData['avg_d_asts'] = round(($teamData['d_asts'] ?? 0) / $games, 1);
    }
    // --- AKHIR MODIFIKASI ---
}

if (!$teamData) {
    die("Data tim tidak ditemukan untuk ID Tim ".htmlspecialchars($tmID_param)." musim ".htmlspecialchars($year_param).".");
}

// 1.5. Ambil data pelatih untuk tim dan musim ini (New Addition)
$coachesForThisTeamSeason = [];
// This assumes your 'coaches_collection' documents are structured with coach details at the root
// and they have a 'teams.team_season_details' array that links them to specific team seasons.
// Adjust if your schema for linking coaches to team seasons is different.
// 1.5. Ambil data pelatih untuk tim dan musim ini (New Addition)
$coachesForThisTeamSeason = [];
$pipelineCoachesList = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => [
        'teams.team_season_details.year' => $year_param_int,
        'teams.team_season_details.tmID' => $tmID_param
    ]],
    ['$project' => [
        '_id' => 0, 'coachID' => 1, 'firstName' => 1, 'lastName' => 1,
    ]],
    ['$group' => [
        '_id' => '$coachID', 'firstName' => ['$first' => '$firstName'], 'lastName' => ['$first' => '$lastName']
    ]]
];
$coachesResult = $coaches_collection->aggregate($pipelineCoachesList)->toArray();
if (!empty($coachesResult)) {
    foreach ($coachesResult as $coach) {
        $coachesForThisTeamSeason[] = [
            'name' => trim(($coach['firstName'] ?? '') . ' ' . ($coach['lastName'] ?? '')),
            'id' => $coach['_id'] ?? 'N/A'
        ];
    }
}


// 2. Ambil Rata-Rata Liga/Semua Tim untuk Musim yang Sama
$leagueAverageStats = null;
$avgPipeline = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => ['teams.team_season_details.year' => $year_param_int]],
    ['$group' => [
        '_id' => '$teams.team_season_details.year',
        'avg_o_pts' => ['$avg' => '$teams.team_season_details.o_pts'],
        'avg_o_fgm' => ['$avg' => '$teams.team_season_details.o_fgm'],
        'avg_o_fga' => ['$avg' => '$teams.team_season_details.o_fga'],
        'avg_o_3pm' => ['$avg' => '$teams.team_season_details.o_3pm'],
        'avg_o_3pa' => ['$avg' => '$teams.team_season_details.o_3pa'],
        'avg_o_ftm' => ['$avg' => '$teams.team_season_details.o_ftm'],
        'avg_o_fta' => ['$avg' => '$teams.team_season_details.o_fta'],
        'avg_o_reb' => ['$avg' => '$teams.team_season_details.o_reb'],
        'avg_o_asts' => ['$avg' => '$teams.team_season_details.o_asts'],
        'avg_o_stl' => ['$avg' => '$teams.team_season_details.o_stl'],
        'avg_o_blk' => ['$avg' => '$teams.team_season_details.o_blk'],
        'avg_o_to' => ['$avg' => '$teams.team_season_details.o_to'],
        'avg_d_pts' => ['$avg' => '$teams.team_season_details.d_pts'],
        'avg_d_fgm' => ['$avg' => '$teams.team_season_details.d_fgm'],
        'avg_d_fga' => ['$avg' => '$teams.team_season_details.d_fga'],
        'avg_d_3pm' => ['$avg' => '$teams.team_season_details.d_3pm'],
        'avg_d_3pa' => ['$avg' => '$teams.team_season_details.d_3pa'],
        'avg_d_reb' => ['$avg' => '$teams.team_season_details.d_reb'],
        'avg_d_asts' => ['$avg' => '$teams.team_season_details.d_asts'],
        'count' => ['$sum' => 1]
    ]]
];
$leagueAvgResult = $coaches_collection->aggregate($avgPipeline)->toArray();
if (!empty($leagueAvgResult)) {
    $leagueAverageStats = $leagueAvgResult[0];
}


// ---- PERSIAPAN DATA UNTUK RADAR CHARTS ---- (User's Original Logic for data values)
$offensiveRadarData = null;
$defensiveRadarData = null;

if ($teamData && $leagueAverageStats) {
    // Statistik ofensif untuk radar (nilai lebih tinggi lebih baik)
    $offensiveRadarLabels = ['Points', 'FG%', '3P%', 'FT%', 'Rebounds', 'Assists', 'Steals', 'Blocks'];
    $teamOffensiveValues = [ // User's original calculation for chart values
        $teamData['o_pts'] ?? 0,
        isset($teamData['o_fga']) && $teamData['o_fga'] > 0 ? round(($teamData['o_fgm'] / $teamData['o_fga']) * 100, 1) : 0,
        isset($teamData['o_3pa']) && $teamData['o_3pa'] > 0 ? round(($teamData['o_3pm'] / $teamData['o_3pa']) * 100, 1) : 0,
        isset($teamData['o_fta']) && $teamData['o_fta'] > 0 ? round(($teamData['o_ftm'] / $teamData['o_fta']) * 100, 1) : 0,
        $teamData['o_reb'] ?? 0,
        $teamData['o_asts'] ?? 0,
        $teamData['o_stl'] ?? 0,
        $teamData['o_blk'] ?? 0,
    ];
    $leagueAvgOffensiveValues = [ // User's original calculation for chart values
        round($leagueAverageStats['avg_o_pts'] ?? 0, 1),
        isset($leagueAverageStats['avg_o_fga']) && $leagueAverageStats['avg_o_fga'] > 0 ? round(($leagueAverageStats['avg_o_fgm'] / $leagueAverageStats['avg_o_fga']) * 100, 1) : 0,
        isset($leagueAverageStats['avg_o_3pa']) && $leagueAverageStats['avg_o_3pa'] > 0 ? round(($leagueAverageStats['avg_o_3pm'] / $leagueAverageStats['avg_o_3pa']) * 100, 1) : 0,
        isset($leagueAverageStats['avg_o_fta']) && $leagueAverageStats['avg_o_fta'] > 0 ? round(($leagueAverageStats['avg_o_ftm'] / $leagueAverageStats['avg_o_fta']) * 100, 1) : 0,
        round($leagueAverageStats['avg_o_reb'] ?? 0, 1),
        round($leagueAverageStats['avg_o_asts'] ?? 0, 1),
        round($leagueAverageStats['avg_o_stl'] ?? 0, 1),
        round($leagueAverageStats['avg_o_blk'] ?? 0, 1),
    ];

    $offensiveRadarData = [
        'labels' => $offensiveRadarLabels,
        'datasets' => [
            [
                'label' => htmlspecialchars($teamNameToDisplay),
                'data' => $teamOffensiveValues,
                // Using new dark-theme friendly colors
                'backgroundColor' => 'rgba(59, 130, 246, 0.4)', // Tailwind blue-500
                'borderColor' => 'rgba(59, 130, 246, 1)',
                'pointBackgroundColor' => 'rgba(59, 130, 246, 1)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgba(59, 130, 246, 1)',
                'borderWidth' => 2
            ],
            [
                'label' => 'Rata-Rata Liga',
                'data' => $leagueAvgOffensiveValues,
                // Using new dark-theme friendly colors
                'backgroundColor' => 'rgba(107, 114, 128, 0.3)', // Tailwind gray-500
                'borderColor' => 'rgba(156, 163, 175, 1)', // Tailwind gray-400
                'pointBackgroundColor' => 'rgba(156, 163, 175, 1)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgba(156, 163, 175, 1)',
                'borderWidth' => 2
            ]
        ]
    ];

    $defensiveRadarLabels = ['Opp Pts', 'Opp FG%', 'Opp 3P%', 'Opp Reb', 'Opp Asts', 'Forced TO', 'Steals (Def)', 'Blocks (Def)'];
    $teamDefensiveValues = [ // User's original calculation for chart values
        $teamData['d_pts'] ?? 0,
        isset($teamData['d_fga']) && $teamData['d_fga'] > 0 ? round(($teamData['d_fgm'] / $teamData['d_fga']) * 100, 1) : 100,
        isset($teamData['d_3pa']) && $teamData['d_3pa'] > 0 ? round(($teamData['d_3pm'] / $teamData['d_3pa']) * 100, 1) : 100,
        $teamData['d_reb'] ?? 0,
        $teamData['d_asts'] ?? 0,
        $teamData['d_to'] ?? 0,
        $teamData['o_stl'] ?? 0,
        $teamData['o_blk'] ?? 0,
    ];
    $leagueAvgDefensiveValues = [ // User's original calculation for chart values
        round($leagueAverageStats['avg_d_pts'] ?? 0, 1),
        isset($leagueAverageStats['avg_d_fga']) && $leagueAverageStats['avg_d_fga'] > 0 ? round(($leagueAverageStats['avg_d_fgm'] / $leagueAverageStats['avg_d_fga']) * 100, 1) : 100,
        isset($leagueAverageStats['avg_d_3pa']) && $leagueAverageStats['avg_d_3pa'] > 0 ? round(($leagueAverageStats['avg_d_3pm'] / $leagueAverageStats['avg_d_3pa']) * 100, 1) : 100,
        round($leagueAverageStats['avg_d_reb'] ?? 0, 1),
        round($leagueAverageStats['avg_d_asts'] ?? 0, 1),
        round($leagueAverageStats['avg_d_to'] ?? 0, 1),
        round($leagueAverageStats['avg_o_stl'] ?? 0, 1),
        round($leagueAverageStats['avg_o_blk'] ?? 0, 1),
    ];

    $defensiveRadarData = [
        'labels' => $defensiveRadarLabels,
        'datasets' => [
            [
                'label' => htmlspecialchars($teamNameToDisplay),
                'data' => $teamDefensiveValues,
                // Using new dark-theme friendly colors
                'backgroundColor' => 'rgba(239, 68, 68, 0.4)', // Tailwind red-500
                'borderColor' => 'rgba(239, 68, 68, 1)',
                'pointBackgroundColor' => 'rgba(239, 68, 68, 1)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgba(239, 68, 68, 1)',
                'borderWidth' => 2
            ],
            [
                'label' => 'Rata-Rata Liga',
                'data' => $leagueAvgDefensiveValues,
                // Using new dark-theme friendly colors
                'backgroundColor' => 'rgba(107, 114, 128, 0.3)', // Tailwind gray-500
                'borderColor' => 'rgba(156, 163, 175, 1)',    // Tailwind gray-400
                'pointBackgroundColor' => 'rgba(156, 163, 175, 1)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgba(156, 163, 175, 1)',
                'borderWidth' => 2
            ]
        ]
    ];
}

$historicalRankData = null;
$pipelineRankHistory = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => [
        'teams.team_season_details.tmID' => $tmID_param,
        'teams.team_season_details.year' => ['$gte' => 1979]
    ]],
    ['$sort' => ['teams.team_season_details.year' => 1, 'teams.team_season_details.stint' => 1]],
    ['$group' => [
        '_id' => '$teams.team_season_details.year',
        'finalRank' => ['$last' => '$teams.team_season_details.confRank'] 
    ]],
    ['$sort' => ['_id' => 1]],
    ['$project' => [
        '_id' => 0,
        'year' => '$_id',
        'confRank' => '$finalRank'
    ]]
];
$rankHistoryResult = $coaches_collection->aggregate($pipelineRankHistory)->toArray();

if (!empty($rankHistoryResult)) {
    $labels = [];
    $data = [];
    foreach ($rankHistoryResult as $record) {
        $labels[] = $record['year'];
        $data[] = isset($record['confRank']) ? (int)$record['confRank'] : null; 
    }
    $historicalRankData = [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Peringkat Divisi',
                'data' => $data,
                'fill' => true,
                'borderColor' => 'rgba(99, 102, 241, 1)',
                'backgroundColor' => 'rgba(99, 102, 241, 0.2)',
                'tension' => 0.3,
                'pointBackgroundColor' => 'rgba(99, 102, 241, 1)',
                'pointRadius' => 4,
                'pointHoverRadius' => 7
            ]
        ]
    ];
}


// Prepare back link (New Addition)
$back_to_dashboard_link = 'teams_stats.php';
if ($team_season_filter_value_from_dashboard) {
    $back_to_dashboard_link .= '?team_season=' . htmlspecialchars($team_season_filter_value_from_dashboard);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tim: <?= htmlspecialchars($teamNameToDisplay) ?> (<?= htmlspecialchars($year_param) ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Condensed:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #111827; /* gray-900 */ color: #D1D5DB; /* gray-300 */ padding-top: 2rem; padding-bottom: 3rem;}
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .container { max-width: 1300px; margin: 0 auto; background-color: #1f2937; /* gray-800 */ padding: 2rem; border-radius: 0.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3), 0 10px 10px -5px rgba(0,0,0,0.2); }
        
        h1.page-main-title { color: #93C5FD; /* blue-300 */ text-align: center; margin-bottom: 0.25rem; font-family: 'Roboto Condensed', sans-serif; font-size: 2.8rem; font-weight: 700; letter-spacing: -0.025em;}
        p.page-subtitle { text-align: center; color: #9CA3AF; /* gray-400 */ margin-bottom: 2.5rem; font-size: 1.2rem; font-family: 'Roboto Condensed', sans-serif; font-weight: 300;}
        
        h2.section-title { font-family: 'Roboto Condensed', sans-serif; font-size: 1.75rem; font-weight: 700; color: #60A5FA; /* blue-400 */ margin-top: 2.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid #374151; /* gray-700 */ padding-bottom: 0.75rem;}
        
        .stats-display-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
        .stat-display-item { background-color: #374151; /* gray-700 */ padding: 0.85rem 1.15rem; border-radius: 0.5rem; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid transparent; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .stat-display-item:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .stat-display-item .label { font-size: 0.875rem; color: #9CA3AF; /* gray-400 */ }
        .stat-display-item .value { font-size: 1rem; font-weight: 600; color: #F3F4F6; /* gray-100 */ }
        .value-green { color: #4ADE80; /* green-400 */ }
        .value-red { color: #F87171; /* red-400 */ }

        .offensive-border { border-left-color: #3B82F6; /* blue-500 */ }
        .defensive-border { border-left-color: #EF4444; /* red-500 */ }
        .neutral-border { border-left-color: #A5B4FC; /* indigo-300 */}
        
        .chart-wrapper { background-color: #374151; /* gray-700 */ padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); min-height: 420px; }
        
        .action-button { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .action-button i { margin-right: 0.5rem; }
        
        .back-link { background-color: #4B5563; /* gray-600 */ color: #E5E7EB; /* gray-200 */ }
        .back-link:hover { background-color: #6B7280; /* gray-500 */ }
        
        .playoff-link { background-color: #10B981; /* emerald-500 */ color: white; }
        .playoff-link:hover { background-color: #059669; /* emerald-600 */ transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.3); }

        .coach-list { list-style: none; padding-left: 0; }
        .coach-list li { background-color: #4B5563; /* gray-600 */ padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 0.5rem; display: flex; align-items: center; }
        .coach-list li i { color: #93C5FD; margin-right: 0.75rem; } /* blue-300 */
    </style>
</head>
<body>
    <div class="container">
        <div class="text-left mb-8">
             <a href="<?= htmlspecialchars($back_to_dashboard_link) ?>"
               class="action-button back-link text-sm">
               <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

        <h1 class="page-main-title"><?= htmlspecialchars($teamNameToDisplay) ?></h1>
        <p class="page-subtitle">Statistik Detail Musim <?= htmlspecialchars($year_param) ?></p>

        <?php if ($teamData): ?>
            <section class="mb-10">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
                    <h2 class="section-title mt-0 mb-2 sm:mb-0"><i class="fas fa-clipboard-list mr-2"></i>Ringkasan Tim & Musim</h2>
                    <?php if (isset($teamData['playoff']) && $teamData['playoff'] !== 'N/A' && !empty($teamData['playoff'])): ?>
                        <a href="team_playoff.php?year=<?= htmlspecialchars($year_param) ?>&tmID=<?= htmlspecialchars($tmID_param) ?><?= $team_season_filter_value_from_dashboard ? '&team_season_filter_dari_teams_stats=' . htmlspecialchars($team_season_filter_value_from_dashboard) : '' ?>"
                           class="action-button playoff-link">
                           <i class="fas fa-trophy"></i> Lihat Detail Playoff
                        </a>
                    <?php endif; ?>
                </div>

                <div class="stats-display-grid mt-4">
                    <!-- Info Umum -->
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-flag text-indigo-300"></i>Liga</span><span class="value"><?= htmlspecialchars($teamData['lgID'] ?? '-') ?></span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-users text-indigo-300"></i>Tim</span><span class="value"><?= htmlspecialchars($teamData['name'] ?? '-') ?></span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-sitemap text-indigo-300"></i>Divisi</span><span class="value"><?= htmlspecialchars($teamData['divID'] ?? '-') ?> (Peringkat: <?= htmlspecialchars($teamData['rank'] ?? '-') ?>)</span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-layer-group text-indigo-300"></i>Konferensi</span><span class="value"><?= htmlspecialchars($teamData['confID'] ?? '-') ?> (Peringkat: <?= htmlspecialchars($teamData['confRank'] ?? '-') ?>)</span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-landmark text-gray-400"></i>Arena</span><span class="value"><?= htmlspecialchars($teamData['arena'] ?? '-') ?></span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-users-cog text-gray-400"></i>Penonton</span><span class="value"><?= number_format($teamData['attendance'] ?? 0) ?></span></div>

                    <!-- Rekor Tim -->
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-medal text-yellow-400"></i>Hasil Playoff</span><span class="value"><?= htmlspecialchars($teamData['playoff'] ?? 'N/A') ?></span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-basketball-ball text-orange-400"></i>Total Game</span><span class="value"><?= htmlspecialchars($teamData['games'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-plus-circle text-blue-400"></i>Menang</span><span class="value value-green"><?= htmlspecialchars($teamData['won'] ?? '-') ?></span></div>
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-minus-circle text-red-400"></i>Kalah</span><span class="value value-red"><?= htmlspecialchars($teamData['lost'] ?? '-') ?></span></div>
                    
                    <!-- Persentase Shooting -->
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-bullseye text-blue-400"></i>Off. FG%</span><span class="value"><?= htmlspecialchars($teamData['o_fgp'] ?? '0') ?>%</span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-bullseye text-blue-400"></i>Off. 3P%</span><span class="value"><?= htmlspecialchars($teamData['o_3pp'] ?? '0') ?>%</span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-bullseye text-blue-400"></i>Off. FT%</span><span class="value"><?= htmlspecialchars($teamData['o_ftp'] ?? '0') ?>%</span></div>
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-shield-alt text-red-400"></i>Opp. FG%</span><span class="value value-red"><?= htmlspecialchars($teamData['d_fgp'] ?? '0') ?>%</span></div>
                    
                    <!-- Rata-rata Ofensif per Game -->
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-star text-blue-400"></i>Points (PPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_pts'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-people-arrows text-blue-400"></i>Assists (APG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_asts'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-hand-lizard text-blue-400"></i>Rebounds (RPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_reb'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-hand-sparkles text-blue-400"></i>Steals (SPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_stl'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-hand-paper text-blue-400"></i>Blocks (BPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_blk'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-exclamation-triangle text-yellow-400"></i>Turnovers (TPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_to'] ?? '0') ?></span></div>
                    
                    <!-- Rata-rata Defensif per Game -->
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-star-half-alt text-red-400"></i>Opp. Points (PPG)</span><span class="value value-red"><?= htmlspecialchars($teamData['avg_d_pts'] ?? '0') ?></span></div>
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-people-arrows text-red-400"></i>Opp. Assists (APG)</span><span class="value value-red"><?= htmlspecialchars($teamData['avg_d_asts'] ?? '0') ?></span></div>
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-hand-lizard text-red-400"></i>Opp. Rebounds (RPG)</span><span class="value value-red"><?= htmlspecialchars($teamData['avg_d_reb'] ?? '0') ?></span></div>
                </div>
            </section>

            <?php if (!empty($coachesForThisTeamSeason)): ?>
            <section class="mb-10">
                <h2 class="section-title"><i class="fas fa-user-tie mr-2"></i>Staf Pelatih Musim Ini</h2>
                <ul class="coach-list mt-4">
                    <?php foreach ($coachesForThisTeamSeason as $coach): ?>
                        <li>
                            <i class="fas fa-id-badge"></i>
                            <div>
                                <span class="font-semibold text-gray-100"><?= htmlspecialchars($coach['name']) ?></span>
                                <span class="text-xs text-gray-400 ml-2">(ID: <?= htmlspecialchars($coach['id']) ?>)</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php elseif($teamData): // Show a message if teamData exists but no coaches found, only if the section would be relevant ?>
            <section class="mb-10">
                <h2 class="section-title"><i class="fas fa-user-tie mr-2"></i>Staf Pelatih Musim Ini</h2>
                <p class="text-gray-400 mt-4 bg-gray-700 p-4 rounded-md">Informasi pelatih tidak tersedia untuk tim ini pada musim <?= htmlspecialchars($year_param); ?>.</p>
            </section>
            <?php endif; ?>


            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
                <?php if ($offensiveRadarData): ?>
                <section>
                    <h2 class="section-title"><i class="fas fa-chart-line mr-2"></i>Statistik Ofensif (Radar)</h2>
                    <div class="chart-wrapper mt-4">
                        <canvas id="offensiveRadarChart"></canvas>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($defensiveRadarData): ?>
                <section>
                    <h2 class="section-title"><i class="fas fa-shield-virus mr-2"></i>Statistik Defensif (Radar)</h2>
                    <div class="chart-wrapper mt-4">
                        <canvas id="defensiveRadarChart"></canvas>
                    </div>
                </section>
                <?php endif; ?>
            </div>


            <!-- MODIFIKASI: Mengganti Chart Perbandingan Umum dengan Chart Peringkat Historis -->
            <?php if ($historicalRankData): ?>
            <section class="mb-8">
                <h2 class="section-title"><i class="fas fa-chart-area mr-2"></i>Performa Peringkat Tim (Sejak 1979)</h2>
                <div class="chart-wrapper mt-4 min-h-[450px] lg:min-h-[500px]">
                    <canvas id="historicalRankChart"></canvas>
                </div>
            </section>
            <?php else: ?>
                <p class="text-center text-gray-500 my-8 bg-gray-700 p-6 rounded-md shadow-md">Data peringkat historis tidak tersedia untuk tim ini.</p>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center text-red-400 mt-10 py-10 bg-gray-700 rounded-lg shadow-lg">...</div>
        <?php endif; ?>

        <div class="text-center mt-12">
            <a href="<?= htmlspecialchars($back_to_dashboard_link) ?>" class="action-button back-link text-base"><i class="fas fa-arrow-left"></i>Kembali ke Dashboard Tim</a>
        </div>
    </div>

<script>
    const teamDataPHP = <?= json_encode($teamData ?? null) ?>;
    const leagueAverageStatsPHP = <?= json_encode($leagueAverageStats ?? null) ?>;
    const comparisonChartDataJS = <?= json_encode($comparisonChartData ?? null) ?>;
    const offensiveRadarDataJS = <?= json_encode($offensiveRadarData ?? null) ?>;
    const defensiveRadarDataJS = <?= json_encode($defensiveRadarData ?? null) ?>;
    const historicalRankDataJS = <?= json_encode($historicalRankData ?? null) ?>;

    const chartTextColor = '#D1D5DB'; // gray-300
    const chartGridColor = 'rgba(255, 255, 255, 0.1)'; 
    const chartAngleColor = 'rgba(255, 255, 255, 0.1)';
    const chartPointLabelColor = '#9CA3AF'; // gray-400

    Chart.defaults.color = chartTextColor; 
    Chart.defaults.borderColor = chartGridColor; 

    function createRadarChart(canvasId, chartData, titleText) {
        const ctx = document.getElementById(canvasId)?.getContext('2d');
        if (ctx && chartData && chartData.datasets && chartData.datasets.length > 0 && chartData.datasets[0].data && chartData.datasets[0].data.length > 0) {
            new Chart(ctx, {
                type: 'radar',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'top', 
                            labels: { 
                                font: { size: 11 }, 
                                padding: 10, 
                                usePointStyle: true, 
                                boxWidth: 8,
                                color: chartTextColor
                            } 
                        },
                        title: { display: false }, 
                        tooltip: {
                            backgroundColor: '#1F2937', 
                            titleColor: '#E5E7EB', 
                            bodyColor: '#D1D5DB', 
                            borderColor: '#374151', 
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (typeof context.formattedValue !== 'undefined') {
                                        label += context.formattedValue;
                                    } else if (typeof context.raw !== 'undefined') {
                                        label += context.raw;
                                    }
                                    
                                    if (canvasId === 'defensiveRadarChart') {
                                        const statIndex = context.dataIndex;
                                        const statLabel = chartData.labels[statIndex];
                                        if (statLabel && (statLabel.startsWith('Opp Pts') || statLabel.startsWith('Opp FG%') || statLabel.startsWith('Opp 3P%') || statLabel.startsWith('Opp Reb') || statLabel.startsWith('Opp Asts'))) {
                                            label += ' (Lower is Better)';
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            pointLabels: { font: { size: 10 }, color: chartPointLabelColor },
                            grid: { color: chartGridColor },
                            angleLines: { color: chartAngleColor },
                            ticks: {
                                backdropColor: 'rgba(0,0,0,0)', 
                                color: chartTextColor,
                            }
                        }
                    },
                    elements: {
                        line: { borderWidth: 2.5 },
                        point: { radius: 3.5, hoverRadius: 6 }
                    }
                }
            });
        } else if (document.getElementById(canvasId)) {
             document.getElementById(canvasId).parentNode.innerHTML = `<p class="text-center text-sm text-gray-400 py-8 bg-gray-800 rounded">Data tidak cukup untuk menampilkan ${titleText.toLowerCase()}.</p>`;
        }
    }

    createRadarChart('offensiveRadarChart', offensiveRadarDataJS, 'Statistik Ofensif Tim vs Liga');
    createRadarChart('defensiveRadarChart', defensiveRadarDataJS, 'Statistik Defensif Tim vs Liga');

    // --- BARU: Logika untuk membuat Line Chart Peringkat ---
    if (historicalRankDataJS && document.getElementById('historicalRankChart')) {
        const ctxRank = document.getElementById('historicalRankChart').getContext('2d');
        new Chart(ctxRank, {
            type: 'line',
            data: historicalRankDataJS,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false 
                    },
                    tooltip: {
                        backgroundColor: '#1F2937', 
                        titleColor: '#E5E7EB',
                        bodyColor: '#D1D5DB',
                        borderColor: '#374151',
                        borderWidth: 1, padding: 10, cornerRadius: 6,
                        callbacks: {
                            title: function(tooltipItems) {
                                return 'Musim: ' + tooltipItems[0].label;
                            },
                            label: function(context) {
                                return 'Peringkat Divisi: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Peringkat (Lebih Rendah Lebih Baik)',
                            color: chartTextColor,
                            font: { size: 12 }
                        },
                        reverse: true,
                        min: 1,
                        grid: { color: chartGridColor },
                        ticks: {
                            color: chartTextColor,
                            callback: function(value) { if (Number.isInteger(value)) { return value; } }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Musim',
                            color: chartTextColor,
                            font: { size: 12 }
                        },
                        grid: { display: false },
                        ticks: { color: chartTextColor }
                    }
                }
            }
        });
    }

</script>
</body>
</html>