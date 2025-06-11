<!DOCTYPE html>
<!-- 
    CATATAN: Idealnya, file header.php hanya berisi <nav>...</nav>.
    Namun, untuk memudahkan dan agar sesuai dengan struktur file Anda yang lain,
    saya sertakan struktur HTML lengkap di sini. Pastikan file lain yang
    meng-include header ini tidak memiliki duplikasi tag <html>, <head>, atau <body>.

    Jika file lain (seperti index.php) sudah memiliki tag-tag tersebut,
    maka Anda cukup menyalin bagian <nav>...</nav> saja ke dalam file header.php.
-->
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Script dan Link yang dibutuhkan di semua halaman -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@500;600;700&family=Rajdhani:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Anda bisa menambahkan title default di sini, tapi lebih baik di-set di setiap halaman -->
    <!-- <title>NBA Universe Dashboard</title> -->

    <style>
        /* Style dasar untuk konsistensi */
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
        <!-- Logo di Kiri -->
        <a href="index.php" class="flex items-center gap-3">
            <img src="https://cdn.nba.com/logos/nba/nba-logoman-word-white.svg" alt="NBA Universe Logo" class="h-10">
        </a>

        <!-- Link Navigasi di Kanan (untuk layar medium ke atas) -->
        <div class="hidden md:flex items-center space-x-6">
            <a href="index.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Home</a>
            <a href="player_stats.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Player Stats</a>
            <a href="teams_stats.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Team Stats</a>

            <!-- === LINK BARU YANG DITAMBAHKAN === -->
            <a href="awards_stats.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Awards</a>

            <a href="search_page.php" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Search</a>
        </div>

        <!-- Tombol Menu Mobile (untuk layar kecil) -->
        <div class="md:hidden">
            <button id="mobile-menu-button" class="text-gray-300 hover:text-white focus:outline-none">
                <i class="fas fa-bars fa-lg"></i>
            </button>
        </div>
    </nav>

    <!-- Menu Mobile (awalnya tersembunyi) -->
    <div id="mobile-menu" class="hidden md:hidden bg-gray-900 p-4">
        <a href="index.php" class="block text-gray-300 hover:text-white py-2">Home</a>
        <a href="player_stats.php" class="block text-gray-300 hover:text-white py-2">Player Stats</a>
        <a href="teams_stats.php" class="block text-gray-300 hover:text-white py-2">Team Stats</a>
        <a href="awards_stats.php" class="block text-gray-300 hover:text-white py-2">Awards</a>
        <a href="search_page.php" class="block text-gray-300 hover:text-white py-2">Search</a>
    </div>

    <script>
        // Script sederhana untuk toggle menu mobile
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            var menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
    </script>

    <!-- Tag penutup </body> dan </html> JANGAN diletakkan di sini,
     tetapi di akhir setiap file utama (index.php, player_stats.php, dll) -->