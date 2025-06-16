<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Script dan Link yang dibutuhkan di semua halaman -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@500;600;700&family=Rajdhani:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>NBA DASHBOARD</title>

    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0A0A14;
            color: #E0E0E0;
        }

        .font-teko {
            font-family: 'Teko', sans-serif;
        }

        .font-rajdhani {
            font-family: 'Rajdhani', sans-serif;
        }
    </style>
</head>

<body>

    <nav class="bg-gray-900/80 backdrop-blur-sm border-b border-gray-700/50 shadow-lg p-4 flex justify-between items-center sticky top-0 z-50">
        <a href="index.php" class="flex items-center gap-3">
            <img src="https://cdn.nba.com/logos/nba/nba-logoman-word-white.svg" alt="NBA Universe Logo" class="h-10">
        </a>

        <div class="hidden md:flex items-center space-x-6">
            <a href="index.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Home</a>
            <a href="player_stats.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Player Stats</a>
            <a href="teams_stats.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Team Stats</a>
            <a href="awards_stats.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Awards</a>
            <a href="college.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Draft</a>
            <a href="search_page.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Search</a>
        </div>

        <div class="md:hidden">
            <button id="mobile-menu-button" class="text-gray-300 hover:text-white focus:outline-none">
                <i class="fas fa-bars fa-lg"></i>
            </button>
        </div>
    </nav>

    <div id="mobile-menu" class="hidden md:hidden bg-gray-900 p-4">
        <a href="index.php" class="block text-gray-300 hover:text-white py-2">Home</a>
        <a href="player_stats.php" class="block text-gray-300 hover:text-white py-2">Player Stats</a>
        <a href="teams_stats.php" class="block text-gray-300 hover:text-white py-2">Team Stats</a>
        <a href="awards_stats.php" class="block text-gray-300 hover:text-white py-2">Awards</a>
        <a href="college.php" class="block text-gray-300 hover:text-white py-2">Draft</a>
        <a href="search_page.php" class="block text-gray-300 hover:text-white py-2">Search</a>
    </div>

    <script>
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            var menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
    </script>