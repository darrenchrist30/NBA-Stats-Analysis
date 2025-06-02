<?php 
    require_once 'db.php'; 
    include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NBA Player Performance Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        .hero-bg {
            background: linear-gradient(135deg, #e0e7ff 0%, #f0fdfa 100%);
        }
        .card {
            transition: transform 0.15s;
        }
        .card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 8px 24px 0 rgba(59,130,246,0.15);
        }
        .pulse {
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
    </style>
</head>
<body class="hero-bg min-h-screen">
    <!-- <nav class="bg-white shadow p-4 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <img src="image/nba.png" alt="NBA" class="h-8">
            <h1 class="text-2xl font-extrabold text-blue-700 tracking-tight drop-shadow">NBA Player Dashboard</h1>
        </div>
        <div class="space-x-4">
            <a href="index.php" class="text-gray-700 hover:text-blue-500 font-semibold">Home</a>
            <a href="player_stats.php" class="text-gray-700 hover:text-blue-500 font-semibold">Player Stats</a>
            <a href="teams_stats.php" class="text-gray-700 hover:text-blue-500 font-semibold">Team Stats</a>
            <a href="search_page.php" class="text-gray-700 hover:text-blue-500 font-semibold">Search</a>
        </div>
    </nav> -->

    <main class="p-6 max-w-7xl mx-auto">
        <!-- Hero Section -->
        <section class="rounded-2xl shadow-xl bg-gradient-to-br from-blue-100 to-cyan-100 p-10 flex flex-col md:flex-row items-center justify-between mb-10">
            <div class="max-w-xl">
                <h2 class="text-4xl font-extrabold text-blue-900 mb-4 drop-shadow animate-bounce">Selamat Datang di NBA Player Dashboard!</h2>
                <p class="text-lg text-gray-700 mb-6">Eksplorasi statistik pemain NBA dengan visualisasi interaktif, filter canggih, dan tampilan modern. Temukan insight performa pemain favoritmu!</p>
                <a href="player_stats.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-lg shadow hover:bg-blue-700 transition pulse">Lihat Statistik Pemain</a>
            </div>
            <img src="https://cdn.nba.com/logos/nba/nba-logoman-word-white.svg" alt="NBA" class="h-40 mt-8 md:mt-0 md:ml-10 drop-shadow-lg animate-fade-in">
        </section>

        <!-- Feature Cards -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
            <div class="card bg-white rounded-2xl shadow p-6 flex flex-col items-center text-center hover:bg-blue-50 transition">
                <span class="text-4xl font-bold text-blue-700 mb-2 animate-spin-slow">📊</span>
                <h3 class="text-xl font-semibold mb-2">Statistik Lengkap</h3>
                <p class="text-gray-600">Lihat statistik poin, rebound, assist, shooting %, dan banyak lagi untuk setiap pemain NBA.</p>
            </div>
            <div class="card bg-white rounded-2xl shadow p-6 flex flex-col items-center text-center hover:bg-green-50 transition">
                <span class="text-4xl font-bold text-green-600 mb-2 animate-bounce">🎯</span>
                <h3 class="text-xl font-semibold mb-2">Filter Dinamis</h3>
                <p class="text-gray-600">Filter data berdasarkan musim, tim, posisi, atau nama pemain favoritmu dengan mudah.</p>
            </div>
            <div class="card bg-white rounded-2xl shadow p-6 flex flex-col items-center text-center hover:bg-yellow-50 transition">
                <span class="text-4xl font-bold text-yellow-500 mb-2 animate-pulse">📈</span>
                <h3 class="text-xl font-semibold mb-2">Visualisasi Interaktif</h3>
                <p class="text-gray-600">Nikmati bar chart, radar chart, dan visualisasi lain untuk analisis performa yang lebih dalam.</p>
            </div>
        </section>

        <!-- Mini Interactive Chart -->
        <section class="bg-white rounded-2xl shadow p-8 flex flex-col md:flex-row items-center justify-between mb-10">
            <div class="w-full md:w-1/2 mb-8 md:mb-0">
                <h4 class="text-2xl font-bold text-blue-800 mb-2">Statistik NBA Sepanjang Masa</h4>
                <p class="text-gray-700 mb-4">Simak tren rata-rata poin per musim NBA (dummy data, hanya contoh visualisasi interaktif):</p>
                <canvas id="miniChart" height="120"></canvas>
            </div>
            <img src="image/nba.png" alt="NBA" class="h-24 mt-6 md:mt-0 md:ml-10 animate-bounce">
        </section>

        <!-- Call to Action -->
        <section class="bg-gradient-to-r from-blue-200 to-cyan-200 rounded-2xl shadow p-8 flex flex-col md:flex-row items-center justify-between">
            <div>
                <h4 class="text-2xl font-bold text-blue-800 mb-2">Mulai Eksplorasi!</h4>
                <p class="text-gray-700 mb-4">Klik tombol di bawah untuk langsung menuju halaman statistik pemain NBA.</p>
                <a href="player_stats.php" class="bg-cyan-600 text-white px-5 py-2 rounded-lg font-semibold shadow hover:bg-cyan-700 transition pulse">Go to Player Stats</a>
            </div>
            <img src="https://cdn.nba.com/logos/nba/nba-primary-logo.svg" alt="NBA" class="h-24 mt-6 md:mt-0 md:ml-10">
        </section>
    </main>

    <footer class="text-center p-6 text-gray-500 mt-10">
        &copy; 2025 NBA Stats Dashboard &mdash; by Kamu
    </footer>

    <script>
    // Animasi custom
    tailwind.config = {
        theme: {
            extend: {
                animation: {
                    'spin-slow': 'spin 3s linear infinite',
                    'fade-in': 'fadeIn 2s ease-in',
                },
                keyframes: {
                    fadeIn: {
                        '0%': { opacity: 0 },
                        '100%': { opacity: 1 },
                    }
                }
            }
        }
    }
    // Mini Chart Interaktif (dummy data)
    const ctx = document.getElementById('miniChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['2018', '2019', '2020', '2021', '2022', '2023'],
            datasets: [{
                label: 'Rata-rata Poin/Game',
                data: [106.3, 111.2, 112.1, 110.4, 114.7, 115.2],
                fill: true,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(59,130,246,0.08)',
                tension: 0.4,
                pointBackgroundColor: '#2563eb',
                pointRadius: 5
            }]
        },
        options: {
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: false, grid: { color: '#e0e7eb' } },
                x: { grid: { color: '#e0e7eb' } }
            }
        }
    });
    </script>
</body>
</html>
