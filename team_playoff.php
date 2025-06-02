<?php
require_once 'db.php';
include 'header.php';

// Fungsi untuk menghitung persentase kemenangan
function calculateWinPercentage($won, $games, $lost) {
    $totalGames = $games;
    if ($games == 0) {
        $totalGames = $won + $lost;
    }
    return $totalGames > 0 ? round(($won / $totalGames) * 100, 2) : 0;
}

$teamYear = $_GET['year'] ?? null;
$teamName = $_GET['name'] ?? null;

if (!$teamYear || !$teamName) {
    echo "<div class='container mx-auto mt-8 p-6 bg-white rounded-md shadow-md text-center'><p class='text-gray-700'>Parameter tim tidak lengkap.</p><p><a href='teams_stats.php' class='text-blue-500 hover:underline'>Kembali ke Statistik Tim</a></p></div>";
    exit;
}

// Ambil detail tim - TAHUN INI TETAP MENGGUNAKAN $teamYear ASLI
$teamDetail = $teams->findOne(['year' => (int)$teamYear, 'name' => $teamName]);

if (!$teamDetail) {
    echo "<div class='container mx-auto mt-8 p-6 bg-white rounded-md shadow-md text-center'><p class='text-gray-700'>Detail tim tidak ditemukan untuk tahun " . htmlspecialchars($teamYear) . ".</p><p><a href='teams_stats.php' class='text-blue-500 hover:underline'>Kembali ke Statistik Tim</a></p></div>";
    exit;
}

// Ambil data playoff untuk tim ini PADA TAHUN YANG DISESUAIKAN
$playoffsCollection = $db->series_post;
$playoffYearInDb = (int)$teamYear - 1; // PENYESUAIAN TAHUN UNTUK DATA PLAYOFF

$playoffData = $playoffsCollection->find([
    'year' => $playoffYearInDb, // Menggunakan tahun yang sudah disesuaikan
    '$or' => [
        ['tmIDWinner' => $teamDetail['tmID']],
        ['tmIDLoser' => $teamDetail['tmID']]
    ]
])->toArray();

// Siapkan data untuk chart interaktif
$playoffRounds = [];
$playoffResults = [];
foreach ($playoffData as $playoff) {
    $round = "Round " . $playoff['round'];
    $isWinner = $playoff['tmIDWinner'] === $teamDetail['tmID'];
    $playoffRounds[] = $round;
    $playoffResults[] = $isWinner ? $playoff['W'] : $playoff['L'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Judul tetap menampilkan tahun yang diminta pengguna -->
    <title>Detail Tim - <?= htmlspecialchars($teamDetail['name']) ?> (<?= htmlspecialchars($teamYear) ?>) - NBA Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        .stat-card {
            transition: transform 0.15s;
        }
        .stat-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 24px 0 rgba(59,130,246,0.15);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-cyan-50 min-h-screen">
    <div class="max-w-3xl mx-auto mt-10 p-8 bg-white rounded-2xl shadow-xl">
        <div class="flex flex-col md:flex-row items-center justify-between mb-6">
            <div>
                <!-- Tampilkan tahun yang diminta pengguna ($teamYear), bukan $playoffYearInDb -->
                <h2 class="text-3xl font-extrabold text-green-900 mb-2 drop-shadow">Tim: <?= htmlspecialchars($teamDetail['name']) ?> <span class="text-lg text-gray-500">(<?= htmlspecialchars($teamYear) ?>)</span></h2>
                <span class="inline-block bg-green-100 text-green-700 px-3 py-1 rounded-full font-semibold mb-2"><?= $teamDetail['confID'] ?> Conference</span>
            </div>
            <img src="image/nba.png" alt="NBA" class="h-16 md:ml-8 drop-shadow-lg">
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="stat-card bg-gradient-to-br from-green-100 to-cyan-100 rounded-xl shadow p-4 flex flex-col items-center text-center">
                <span class="text-2xl font-bold text-green-700 mb-1"><?= $teamDetail['won'] ?></span>
                <span class="text-gray-600">Wins</span>
            </div>
            <div class="stat-card bg-gradient-to-br from-red-100 to-orange-100 rounded-xl shadow p-4 flex flex-col items-center text-center">
                <span class="text-2xl font-bold text-red-600 mb-1"><?= $teamDetail['lost'] ?></span>
                <span class="text-gray-600">Losses</span>
            </div>
            <div class="stat-card bg-gradient-to-br from-blue-100 to-cyan-100 rounded-xl shadow p-4 flex flex-col items-center text-center">
                <span class="text-2xl font-bold text-blue-700 mb-1"><?= calculateWinPercentage($teamDetail['won'], $teamDetail['games'], $teamDetail['lost']) ?>%</span>
                <span class="text-gray-600">Win %</span>
            </div>
            <div class="stat-card bg-gradient-to-br from-yellow-100 to-orange-100 rounded-xl shadow p-4 flex flex-col items-center text-center">
                <span class="text-2xl font-bold text-yellow-600 mb-1"><?= $teamDetail['rank'] ?? '-' ?></span>
                <span class="text-gray-600">Rank</span>
            </div>
        </div>

       <div class="mb-8">
            <h3 class="text-xl font-bold text-green-700 mb-2">Status Playoff:
                <span class="font-semibold">
                    <?php
                    // Logika status playoff tetap sama, karena $playoffData sekarang sudah berisi data yang benar (meskipun dari tahun DB yang berbeda)
                    if (!empty($playoffData)) {
                        $lastPlayoffResult = null;
                        $wonFinalRound = false; // Flag untuk menandakan apakah tim memenangkan round terakhir yang tercatat

                        // Urutkan playoffData berdasarkan round, jika belum terurut
                        // Asumsi data sudah terurut dari database atau tidak masalah urutannya untuk logika ini
                        // Jika urutan penting, Anda mungkin perlu mengurutkannya di sini
                        // usort($playoffData, fn($a, $b) => $a['round'] <=> $b['round']);


                        $maxRoundReached = 0;
                        foreach ($playoffData as $playoff) {
                            if ($playoff['tmIDLoser'] === $teamDetail['tmID']) {
                                $lastPlayoffResult = "Kalah di Round " . $playoff['round'];
                                // Jika kalah, kita hentikan pencarian status lebih lanjut untuk kekalahan
                                break; 
                            } elseif ($playoff['tmIDWinner'] === $teamDetail['tmID']) {
                                // Tandai bahwa tim ini menang di suatu round
                                // Kita perlu cek apakah ini round terakhir yang dimainkan tim
                                if ($playoff['round'] > $maxRoundReached) {
                                    $maxRoundReached = $playoff['round'];
                                }
                            }
                        }

                        if ($lastPlayoffResult) { // Jika ada catatan kekalahan
                            echo $lastPlayoffResult;
                        } elseif ($maxRoundReached > 0) { // Jika ada catatan kemenangan dan tidak ada kekalahan setelahnya
                            // Cek apakah ada round selanjutnya dimana tim ini tidak tercatat (bisa jadi juara)
                            // Ini asumsi, karena kita tidak tahu apakah ini round final atau tidak
                            // Untuk NBA, round 4 (Finals) adalah yang tertinggi
                            if ($maxRoundReached == 4) { // Asumsi round 4 adalah Finals
                                echo "Juara NBA";
                            } else {
                                echo "Menang hingga Round " . $maxRoundReached . " (data mungkin tidak lengkap untuk hasil akhir)";
                            }
                        } elseif ($teamDetail['playoff'] === 'Y') { // Lolos playoff tapi tidak ada data menang/kalah spesifik di $playoffData
                             echo "Tidak ada catatan detail playoff (data mungkin tidak lengkap)";
                        } else { // Tidak lolos playoff berdasarkan data tim
                            echo "Tidak Lolos";
                        }
                    } else { // Tidak ada data playoff sama sekali di $playoffData
                        if ($teamDetail['playoff'] === 'Y') {
                            echo "Lolos Playoff (tidak ada detail pertandingan tersedia untuk tahun data " . $playoffYearInDb . ")";
                        } else {
                            echo "Tidak Lolos";
                        }
                    }
                    ?>
                </span>
            </h3>
        </div>

        <?php if (!empty($playoffData)): ?>
            <div class="mb-8">
                <h3 class="text-xl font-bold text-blue-700 mb-4">Performa Playoff (Data dari Tahun <?= $playoffYearInDb ?>)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto border-collapse border border-gray-200 text-sm mb-4">
                        <thead>
                            <tr class="bg-blue-100 text-blue-900">
                                <th class="border border-gray-200 px-3 py-2">Round</th>
                                <th class="border border-gray-200 px-3 py-2">Lawan</th>
                                <th class="border border-gray-200 px-3 py-2">Hasil</th>
                                <th class="border border-gray-200 px-3 py-2">Score Seri</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playoffData as $playoff): ?>
                                <?php
                                $isWinner = $playoff['tmIDWinner'] === $teamDetail['tmID'];
                                $opponentTeamID = $isWinner ? $playoff['tmIDLoser'] : $playoff['tmIDWinner'];
                                
                                // Coba ambil nama tim lawan dari koleksi teams untuk tahun playoff yang sesuai
                                $opponentDetail = $teams->findOne(['year' => $playoffYearInDb, 'tmID' => $opponentTeamID]);
                                $opponentName = $opponentDetail ? $opponentDetail['name'] : $opponentTeamID; // Fallback ke tmID jika nama tidak ditemukan

                                $result = $isWinner ? "Menang" : "Kalah";
                                $score = $playoff['W'] . " - " . $playoff['L'];
                                ?>
                                <tr class="border border-gray-200 hover:bg-blue-50 transition">
                                    <td class="border px-3 py-1"><?= $playoff['round'] ?></td>
                                    <td class="border px-3 py-1"><?= htmlspecialchars($opponentName) ?></td>
                                    <td class="border px-3 py-1 font-semibold <?= $isWinner ? 'text-green-700' : 'text-red-600' ?>"><?= $result ?></td>
                                    <td class="border px-3 py-1"><?= $score ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Chart Playoff -->
                <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl shadow p-6 mb-4">
                    <h4 class="text-lg font-semibold text-blue-700 mb-2 text-center">Grafik Jumlah Kemenangan per Round</h4>
                    <canvas id="playoffChart" height="80"></canvas>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-8">
                 <p class="mt-4 text-gray-600 text-center">
                    <?php
                    if ($teamDetail['playoff'] === 'Y') {
                        echo 'Tidak ada detail playoff yang tersedia untuk tahun data ' . $playoffYearInDb . '.';
                    } else {
                        echo 'Tim tidak lolos playoff pada tahun ' . htmlspecialchars($teamYear) . '.';
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="mt-8 text-center">
            <a href="teams_stats.php" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold shadow hover:bg-blue-700 transition">Kembali ke Statistik Tim</a>
        </div>
    </div>

    <?php if (!empty($playoffData)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('playoffChart').getContext('2d');
        const playoffLabels = <?= json_encode($playoffRounds) ?>;
        const playoffResults = <?= json_encode($playoffResults) ?>;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: playoffLabels,
                datasets: [{
                    label: 'Jumlah Kemenangan di Round',
                    data: playoffResults,
                    backgroundColor: 'rgba(59,130,246,0.7)',
                    borderRadius: 8,
                    barPercentage: 0.7,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e0e7eb' } },
                    x: { grid: { color: '#e0e7eb' } }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>