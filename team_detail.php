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

$coachesForThisTeamSeason = [];
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

$offensiveRadarData = null;
$defensiveRadarData = null;

if ($teamData && $leagueAverageStats) {
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
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Definisi Variabel CSS untuk Konsistensi Tema */
        :root {
            --color-bg-primary: #0F172A; /* Slate 900 - Background Utama */
            --color-bg-secondary: #1E293B; /* Slate 800 - Container Utama */
            --color-card-bg: #334155; /* Slate 700 - Latar Belakang Kartu/Chart */
            --color-text-main: #E2E8F0; /* Slate 200 - Teks Umum */
            --color-text-muted: #94A3B8; /* Slate 400 - Teks Sekunder/Muted */
            --color-heading: #67E8F9; /* Cyan 400 - Judul Halaman/Bagian */
            --color-accent-blue: #3B82F6; /* Blue 500 - Aksen Utama (Ofensif) */
            --color-accent-red: #EF4444; /* Red 500 - Aksen (Defensif) */
            --color-accent-green: #22C55E; /* Green 500 - Positif */
            --color-accent-yellow: #FACC15; /* Yellow 500 - Perhatian/Netral */
            --color-border-light: #475569; /* Slate 600 - Border/Garis Tipis */
            --shadow-subtle: rgba(0, 0, 0, 0.2);
            --shadow-strong: rgba(0, 0, 0, 0.4);
        }
        body {
            font-family: 'IBM Plex Sans', sans-serif; /* Font teks yang mudah dibaca */
            background-color: var(--color-bg-primary);
            color: var(--color-text-main);
            padding: 3rem 1.5rem; /* Padding yang lebih merata */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .font-display {
            font-family: 'Space Grotesk', sans-serif; /* Font khusus untuk judul besar */
        }

        .container {
            max-width: 1350px; /* Lebar container sedikit diperbesar */
            width: 100%;
            margin: 0 auto;
            background-color: var(--color-bg-secondary);
            padding: 3rem; /* Padding lebih luas */
            border-radius: 1rem; /* Sudut lebih membulat */
            box-shadow: 0 25px 50px -12px var(--shadow-strong); /* Shadow lebih dramatis */
            border: 1px solid var(--color-border-light); /* Border tipis */
        }

        h1.page-main-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 3.5rem; /* Ukuran font judul utama diperbesar */
            font-weight: 700;
            color: var(--color-heading);
            text-align: center;
            margin-bottom: 0.75rem;
            letter-spacing: -0.04em; /* Letter spacing disesuaikan */
            text-shadow: 0 4px 10px var(--shadow-subtle); /* Tambah text shadow */
        }

        p.page-subtitle {
            font-family: 'IBM Plex Sans', sans-serif;
            font-size: 1.3rem; /* Ukuran subtitle sedikit diperbesar */
            font-weight: 400;
            color: var(--color-text-muted);
            text-align: center;
            margin-bottom: 4rem; /* Jarak bawah lebih lega */
        }

        h2.section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem; /* Ukuran judul section diperbesar */
            font-weight: 600;
            color: var(--color-heading);
            margin-top: 3.5rem; /* Jarak atas lebih besar */
            margin-bottom: 2rem; /* Jarak bawah lebih besar */
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--color-border-light);
            display: flex;
            align-items: center;
            gap: 0.8rem; /* Jarak antara ikon dan teks judul */
        }

        h2.section-title i {
            color: var(--color-accent-blue); /* Ikon judul section dengan warna aksen */
            font-size: 1.8rem;
        }
        
        /* Stats Cards */
        .stats-display-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Min lebar kartu diperbesar */
            gap: 1.5rem; /* Jarak antar kartu lebih besar */
        }

        .stat-display-item {
            background-color: var(--color-card-bg);
            padding: 1.25rem 1.75rem; /* Padding kartu diperbesar */
            border-radius: 0.75rem; /* Sudut kartu lebih membulat */
            display: flex;
            flex-direction: column; /* Label di atas nilai */
            align-items: flex-start; /* Teks rata kiri */
            border-left: 5px solid transparent; /* Border kiri lebih tebal */
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); /* Transisi lebih halus */
            box-shadow: 0 8px 15px var(--shadow-subtle); /* Shadow lebih menonjol */
        }

        .stat-display-item:hover {
            transform: translateY(-8px); /* Efek melayang lebih terasa */
            box-shadow: 0 15px 30px var(--shadow-strong); /* Shadow lebih kuat saat hover */
            background-color: #3f5168; /* Sedikit lebih terang saat hover */
        }

        .stat-display-item .label {
            font-size: 0.95rem; /* Ukuran label sedikit diperbesar */
            color: var(--color-text-muted);
            margin-bottom: 0.5rem; /* Jarak antara label dan nilai */
            display: flex; /* Untuk ikon di label */
            align-items: center;
            gap: 0.5rem;
        }

        .stat-display-item .label i {
            font-size: 1.1rem; /* Ukuran ikon label */
        }

        .stat-display-item .value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem; /* Ukuran nilai sangat besar */
            font-weight: 700;
            color: var(--color-text-main);
            line-height: 1.2; /* Line height disesuaikan */
        }

        .value-green { color: var(--color-accent-green); }
        .value-red { color: var(--color-accent-red); }

        /* Warna border kiri kartu */
        .offensive-border { border-left-color: var(--color-accent-blue); }
        .defensive-border { border-left-color: var(--color-accent-red); }
        .neutral-border { border-left-color: var(--color-accent-yellow); }

        /* Chart Wrapper */
        .chart-wrapper {
            background-color: var(--color-card-bg);
            padding: 2.5rem; /* Padding chart diperbesar */
            border-radius: 1rem; /* Sudut membulat */
            box-shadow: 0 10px 20px var(--shadow-subtle);
            min-height: 480px; /* Tinggi minimal chart */
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid var(--color-border-light);
        }
        .chart-wrapper p.no-data-message {
            background-color: rgba(0,0,0,0.2); /* Latar belakang untuk pesan no data */
            border: 2px dashed var(--color-border-light); /* Border putus-putus */
            color: var(--color-text-muted);
            padding: 3rem; /* Padding lebih besar */
            border-radius: 0.75rem;
            font-size: 1.1rem;
            text-align: center;
            line-height: 1.5;
            display: flex; /* Untuk ikon di tengah */
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.2rem;
        }
        .chart-wrapper p.no-data-message i {
            font-size: 2.5rem; /* Ukuran ikon di pesan no data */
            color: var(--color-text-muted);
        }

        /* Buttons */
        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2.2rem; /* Padding tombol lebih besar */
            border-radius: 0.75rem; /* Sudut membulat */
            font-weight: 600;
            text-decoration: none;
            transition: all 0.35s cubic-bezier(0.25, 0.8, 0.25, 1); /* Transisi halus */
            box-shadow: 0 6px 15px var(--shadow-subtle);
            letter-spacing: 0.03em;
            text-transform: uppercase; /* Huruf kapital */
            font-size: 0.95rem; /* Ukuran font tombol */
            border: none;
            margin-top: 0.5rem; /* Added margin-top for flex layout */
        }
        .action-button i { margin-right: 0.75rem; font-size: 1.1rem;}
        
        .back-link {
            background-color: var(--color-card-bg); /* Warna latar lebih gelap */
            color: var(--color-text-main);
            border: 1px solid var(--color-border-light);
        }
        .back-link:hover {
            background-color: #475569; /* Sedikit lebih terang saat hover */
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 10px 20px var(--shadow-strong);
        }
        
        .playoff-link {
            background: linear-gradient(to right, var(--color-accent-green), #16A34A); /* Gradient warna hijau */
            color: white;
            border: none;
        }
        .playoff-link:hover {
            background: linear-gradient(to right, #16A34A, var(--color-accent-green));
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 10px 20px rgba(34, 197, 94, 0.4); /* Shadow hijau */
        }

        /* Coach List */
        .coach-list {
            list-style: none;
            padding-left: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem; /* Jarak antar item coach */
        }
        .coach-list li {
            background-color: var(--color-card-bg);
            padding: 1rem 1.25rem;
            border-radius: 0.6rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 10px var(--shadow-subtle);
            border-left: 5px solid var(--color-accent-blue); /* Border kiri biru */
            transition: all 0.25s ease-in-out;
        }
        .coach-list li:hover {
            transform: translateX(5px); /* Efek bergeser ke kanan */
            box-shadow: 0 8px 16px var(--shadow-strong);
            background-color: #3f5168;
        }
        .coach-list li i {
            color: var(--color-accent-blue);
            font-size: 1.8rem;
        }
        .coach-list li div {
            flex-grow: 1;
        }
        .coach-list li span.font-semibold {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.1rem;
            color: var(--color-text-main);
            display: block;
            margin-bottom: 0.1rem;
        }
        .coach-list li span.text-xs {
            font-family: 'IBM Plex Sans', sans-serif;
            color: var(--color-text-muted);
            font-size: 0.8rem;
        }
        
        /* Message if data not found */
        .no-data-full-message {
            background-color: var(--color-card-bg);
            border: 2px dashed var(--color-border-light);
            color: var(--color-text-muted);
            padding: 4rem;
            border-radius: 1rem;
            text-align: center;
            font-size: 1.3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.8rem;
            font-weight: 400;
            margin-top: 5rem;
            box-shadow: 0 10px 20px var(--shadow-strong);
        }
        .no-data-full-message i {
            font-size: 3.5rem;
            color: var(--color-text-muted);
        }
        .no-data-full-message .small-text {
            font-size: 1rem;
            color: var(--color-text-muted);
            margin-top: 0.5rem;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="text-left mb-10">
            <a href="<?= htmlspecialchars($back_to_dashboard_link) ?>"
               class="action-button back-link">
                <i class="fas fa-arrow-left"></i> KEMBALI KE DASHBOARD
            </a>
        </div>

        <h1 class="page-main-title font-display"><?= htmlspecialchars($teamNameToDisplay) ?></h1>
        <p class="page-subtitle">Analisis Statistik Mendalam untuk Musim <?= htmlspecialchars($year_param) ?></p>

        <?php if ($teamData): ?>
            <section class="mb-14">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                    <h2 class="section-title"><i class="fas fa-chart-bar"></i>Ringkasan Kinerja Tim</h2>
                    <?php if (isset($teamData['playoff']) && $teamData['playoff'] !== 'N/A' && !empty($teamData['playoff'])): ?>
                        <a href="team_playoff.php?year=<?= htmlspecialchars($year_param) ?>&tmID=<?= htmlspecialchars($tmID_param) ?><?= $team_season_filter_value_from_dashboard ? '&team_season_filter_dari_teams_stats=' . htmlspecialchars($team_season_filter_value_from_dashboard) : '' ?>"
                           class="action-button playoff-link">
                           <i class="fas fa-trophy"></i> LIHAT DETAIL PLAYOFF
                        </a>
                    <?php endif; ?>
                </div>

                <div class="stats-display-grid">
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-flag"></i>Liga</span><span class="value"><?= htmlspecialchars($teamData['lgID'] ?? '-') ?></span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-users"></i>Nama Tim</span><span class="value"><?= htmlspecialchars($teamData['name'] ?? '-') ?></span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-sitemap"></i>Divisi</span><span class="value"><?= htmlspecialchars($teamData['divID'] ?? '-') ?> (Rank: <?= htmlspecialchars($teamData['rank'] ?? '-') ?>)</span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-layer-group"></i>Konferensi</span><span class="value"><?= htmlspecialchars($teamData['confID'] ?? '-') ?> (Rank: <?= htmlspecialchars($teamData['confRank'] ?? '-') ?>)</span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-landmark"></i>Arena Utama</span><span class="value"><?= htmlspecialchars($teamData['arena'] ?? '-') ?></span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-users-cog"></i>Total Penonton</span><span class="value"><?= number_format($teamData['attendance'] ?? 0) ?></span></div>

                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-medal"></i>Hasil Playoff</span><span class="value"><?= htmlspecialchars($teamData['playoff'] ?? 'N/A') ?></span></div>
                    <div class="stat-display-item neutral-border"><span class="label"><i class="fas fa-basketball-ball"></i>Total Game Dimainkan</span><span class="value"><?= htmlspecialchars($teamData['games'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-plus-circle"></i>Kemenangan</span><span class="value value-green"><?= htmlspecialchars($teamData['won'] ?? '-') ?></span></div>
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-minus-circle"></i>Kekalahan</span><span class="value value-red"><?= htmlspecialchars($teamData['lost'] ?? '-') ?></span></div>
                    
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-bullseye"></i>FG% Ofensif</span><span class="value"><?= htmlspecialchars($teamData['o_fgp'] ?? '0') ?>%</span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-bullseye"></i>3P% Ofensif</span><span class="value"><?= htmlspecialchars($teamData['o_3pp'] ?? '0') ?>%</span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-bullseye"></i>FT% Ofensif</span><span class="value"><?= htmlspecialchars($teamData['o_ftp'] ?? '0') ?>%</span></div>
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-shield-alt"></i>FG% Lawan</span><span class="value value-red"><?= htmlspecialchars($teamData['d_fgp'] ?? '0') ?>%</span></div>
                    
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-star"></i>Points Per Game (PPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_pts'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-people-arrows"></i>Assists Per Game (APG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_asts'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-hand-lizard"></i>Rebounds Per Game (RPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_reb'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-hand-sparkles"></i>Steals Per Game (SPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_stl'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-hand-paper"></i>Blocks Per Game (BPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_blk'] ?? '0') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label"><i class="fas fa-exclamation-triangle"></i>Turnovers Per Game (TPG)</span><span class="value"><?= htmlspecialchars($teamData['avg_o_to'] ?? '0') ?></span></div>
                    
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-star-half-alt"></i>Opp. Points Per Game (PPG)</span><span class="value value-red"><?= htmlspecialchars($teamData['avg_d_pts'] ?? '0') ?></span></div>
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-people-arrows"></i>Opp. Assists Per Game (APG)</span><span class="value value-red"><?= htmlspecialchars($teamData['avg_d_asts'] ?? '0') ?></span></div>
                    <div class="stat-display-item defensive-border"><span class="label"><i class="fas fa-hand-lizard"></i>Opp. Rebounds Per Game (RPG)</span><span class="value value-red"><?= htmlspecialchars($teamData['avg_d_reb'] ?? '0') ?></span></div>
                </div>
            </section>

            <?php if (!empty($coachesForThisTeamSeason)): ?>
            <section class="mb-14">
                <h2 class="section-title"><i class="fas fa-user-tie"></i>Staf Pelatih Kunci</h2>
                <ul class="coach-list">
                    <?php foreach ($coachesForThisTeamSeason as $coach): ?>
                        <li>
                            <i class="fas fa-id-badge"></i>
                            <div>
                                <span class="font-semibold"><?= htmlspecialchars($coach['name']) ?></span>
                                <span class="text-xs">(ID: <?= htmlspecialchars($coach['id']) ?>)</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php elseif($teamData): ?>
            <section class="mb-14">
                <h2 class="section-title"><i class="fas fa-user-tie"></i>Staf Pelatih Kunci</h2>
                <div class="chart-wrapper"> <p class="no-data-message"><i class="fas fa-info-circle"></i>Informasi staf pelatih tidak tersedia untuk tim ini pada musim **<?= htmlspecialchars($year_param); ?>**.</p>
                </div>
            </section>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 mb-14">
                <section>
                    <h2 class="section-title"><i class="fas fa-chart-area"></i>Analisis Ofensif (Radar)</h2>
                    <?php if ($offensiveRadarData): ?>
                    <div class="chart-wrapper">
                        <canvas id="offensiveRadarChart"></canvas>
                    </div>
                    <?php else: ?>
                    <div class="chart-wrapper">
                        <p class="no-data-message"><i class="fas fa-chart-pie"></i>Data tidak cukup untuk menampilkan **Analisis Statistik Ofensif**.</p>
                    </div>
                    <?php endif; ?>
                </section>

                <section>
                    <h2 class="section-title"><i class="fas fa-shield-alt"></i>Analisis Defensif (Radar)</h2>
                    <?php if ($defensiveRadarData): ?>
                    <div class="chart-wrapper">
                        <canvas id="defensiveRadarChart"></canvas>
                    </div>
                    <?php else: ?>
                    <div class="chart-wrapper">
                        <p class="no-data-message"><i class="fas fa-chart-pie"></i>Data tidak cukup untuk menampilkan **Analisis Statistik Defensif**.</p>
                    </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="mb-14">
                <h2 class="section-title"><i class="fas fa-chart-line"></i>Tren Peringkat Divisi Historis</h2>
                <?php if ($historicalRankData): ?>
                <div class="chart-wrapper min-h-[550px]"> <canvas id="historicalRankChart"></canvas>
                </div>
                <?php else: ?>
                <div class="chart-wrapper">
                    <p class="no-data-message"><i class="fas fa-chart-line"></i>Data tren peringkat historis tidak tersedia untuk tim ini.</p>
                </div>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <div class="no-data-full-message">
                <i class="fas fa-exclamation-circle"></i>
                <p>Oops! Data tim tidak dapat ditemukan untuk ID Tim **<?= htmlspecialchars($tmID_param) ?>** pada musim **<?= htmlspecialchars($year_param) ?>**.</p>
                <p class="small-text">Mohon periksa kembali input Anda atau coba musim/ID tim lainnya.</p>
            </div>
        <?php endif; ?>

        <div class="text-center mt-16">
            <a href="<?= htmlspecialchars($back_to_dashboard_link) ?>" class="action-button back-link">
                <i class="fas fa-arrow-left"></i> KEMBALI KE DASHBOARD UTAMA
            </a>
        </div>
    </div>

<script>
    const teamDataPHP = <?= json_encode($teamData ?? null) ?>;
    const leagueAverageStatsPHP = <?= json_encode($leagueAverageStats ?? null) ?>;
    const offensiveRadarDataJS = <?= json_encode($offensiveRadarData ?? null) ?>;
    const defensiveRadarDataJS = <?= json_encode($defensiveRadarData ?? null) ?>;
    const historicalRankDataJS = <?= json_encode($historicalRankData ?? null) ?>;

    // Mengambil warna dari variabel CSS untuk konsistensi Chart.js
    const getCssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    const chartTextColor = getCssVar('--color-text-main');
    const chartGridColor = getCssVar('--color-border-light');
    const chartAngleColor = getCssVar('--color-border-light');
    const chartPointLabelColor = getCssVar('--color-text-muted');
    const accentBlueColor = getCssVar('--color-accent-blue');
    const accentRedColor = getCssVar('--color-accent-red');
    const accentGreenColor = getCssVar('--color-accent-green');
    const cardBgColor = getCssVar('--color-card-bg');
    const bgSecondaryColor = getCssVar('--color-bg-secondary');

    // Pengaturan default Chart.js untuk tampilan yang konsisten dan modern
    Chart.defaults.color = chartTextColor; 
    Chart.defaults.borderColor = chartGridColor; 
    Chart.defaults.font.family = "'IBM Plex Sans', sans-serif";
    Chart.defaults.font.weight = '400';
    Chart.defaults.plugins.tooltip.backgroundColor = bgSecondaryColor; /* Background tooltip dari container utama */
    Chart.defaults.plugins.tooltip.titleColor = getCssVar('--color-heading'); /* Warna judul tooltip */
    Chart.defaults.plugins.tooltip.bodyColor = chartTextColor;
    Chart.defaults.plugins.tooltip.borderColor = chartGridColor;
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.padding = 12;

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
                                font: { size: 12, weight: '500' }, 
                                padding: 20, 
                                usePointStyle: true, 
                                boxWidth: 10,
                                color: chartTextColor
                            } 
                        },
                        title: { display: false }, 
                        tooltip: {
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
                                        if (statLabel && (statLabel.includes('Opp Pts') || statLabel.includes('Opp FG%') || statLabel.includes('Opp 3P%') || statLabel.includes('Opp Reb') || statLabel.includes('Opp Asts'))) {
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
                            pointLabels: { 
                                font: { size: 11, weight: '500' }, 
                                color: chartPointLabelColor 
                            },
                            grid: { color: chartGridColor, lineWidth: 1 },
                            angleLines: { color: chartAngleColor, lineWidth: 1 },
                            ticks: {
                                backdropColor: 'transparent', /* Menggunakan warna transparan untuk tick background */
                                color: chartTextColor,
                                font: { size: 10, weight: '500' },
                                callback: function(value) { 
                                    const label = this.getLabelForValue(value);
                                    if (label && label.includes('%')) { // Cek label untuk persentase
                                        return value + '%';
                                    }
                                    return value;
                                }
                            }
                        }
                    },
                    elements: {
                        line: { borderWidth: 3, tension: 0.3 }, /* Ketebalan garis dan kehalusan kurva */
                        point: { radius: 5, hoverRadius: 8, backgroundColor: accentBlueColor, borderColor: 'white', borderWidth: 2 } /* Ukuran dan warna titik */
                    }
                }
            });
        } else if (document.getElementById(canvasId)) {
            // Jika tidak ada data, tampilkan pesan di dalam chart-wrapper
            document.getElementById(canvasId).parentNode.innerHTML = `<p class="no-data-message"><i class="fas fa-chart-pie"></i>Data tidak cukup untuk menampilkan **${titleText.toLowerCase()}**.</p>`;
        }
    }

    createRadarChart('offensiveRadarChart', offensiveRadarDataJS, 'Analisis Statistik Ofensif');
    createRadarChart('defensiveRadarChart', defensiveRadarDataJS, 'Analisis Statistik Defensif');

    // --- Line Chart for Historical Rank ---
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
                            font: { size: 14, weight: '600' }
                        },
                        reverse: true, // Peringkat, jadi lebih rendah lebih baik
                        min: 1,
                        grid: { 
                            color: chartGridColor,
                            drawBorder: false 
                        },
                        ticks: {
                            color: chartTextColor,
                            font: { size: 12, weight: '500' },
                            callback: function(value) { if (Number.isInteger(value)) { return value; } }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Musim',
                            color: chartTextColor,
                            font: { size: 14, weight: '600' }
                        },
                        grid: { display: false },
                        ticks: { 
                            color: chartTextColor,
                            font: { size: 12, weight: '500' },
                            autoSkip: true, 
                            maxRotation: 45, 
                            minRotation: 0
                        }
                    }
                },
                elements: {
                    line: { tension: 0.4, borderWidth: 3, borderColor: accentBlueColor, backgroundColor: 'rgba(59, 130, 246, 0.2)' }, /* Warna garis dan area */
                    point: { radius: 6, hoverRadius: 9, backgroundColor: accentGreenColor, borderColor: 'white', borderWidth: 2 } /* Titik data */
                }
            }
        });
    } else if (document.getElementById('historicalRankChart')) {
        document.getElementById('historicalRankChart').parentNode.innerHTML = `<p class="no-data-message"><i class="fas fa-chart-line"></i>Data tren peringkat historis tidak tersedia untuk tim ini.</p>`;
    }
</script>
</body>
</html>