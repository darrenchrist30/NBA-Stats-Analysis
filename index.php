<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NBA Universe - The Ultimate Fan Experience</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@500;600;700&family=Rajdhani:wght@600;700&family=Montserrat:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0A0A14;
            color: #E0E0E0;
            overflow-x: hidden;
        }

        .font-teko {
            font-family: 'Teko', sans-serif;
        }

        .font-rajdhani {
            font-family: 'Rajdhani', sans-serif;
        }

        .hero-section-static {
            /* height: 100vh; */
            height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            /* background-image: url('image/hero-nba-universe.png'); */
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            padding: 20px;
            padding-bottom: 6rem;
            overflow: hidden;
        }

        .hero-video-background {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            object-fit: cover; 
            object-position: center 35%;
            transform: translate(-50%, -50%);
            z-index: 0; 
        }

        .hero-section-static::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(10, 10, 20, 0.3) 0%, rgba(10, 10, 20, 0.8) 100%);
            z-index: 1;
        }

        .hero-content-static {
            position: relative;
            z-index: 2;
        }

        .hero-nba-logo {
            width: clamp(200px, 30vw, 250px);
            margin-bottom: 1.5rem;
        }

        .hero-title-text {
            font-size: clamp(6rem, 15vw, 9rem);
            line-height: 1;
            font-weight: 700;
            color: white;
            text-shadow: 0px 4px 20px rgba(0, 0, 0, 0.7);
            letter-spacing: 0.01em;
            margin-bottom: 1.5rem;
        }

        .hero-subtitle-text {
            font-size: clamp(1rem, 2.5vw, 1.3rem);
            color: #d1d5db;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 3rem;
            line-height: 1.6;
        }

        .hero-cta-button {
            background-color: #1D4ED8;
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 0.5rem;
            font-weight: 700;
            font-size: clamp(1rem, 3vw, 1.25rem);
            box-shadow: 0 4px 20px rgba(29, 78, 216, 0.5);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .hero-cta-button:hover {
            background-color: #1E40AF;
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 7px 25px rgba(29, 78, 216, 0.6);
        }

        .hero-scroll-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            animation: bounceArrow 2s infinite;
        }

        @keyframes bounceArrow {

            0%,
            20%,
            50%,
            80%,
            100% {
                transform: translateX(-50%) translateY(0);
            }

            40% {
                transform: translateX(-50%) translateY(-10px);
            }

            60% {
                transform: translateX(-50%) translateY(-5px);
            }
        }

        .section-title {
            font-family: 'Teko', sans-serif;
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 0.5rem;
            color: #E5E7EB;
        }

        .section-subtitle {
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            color: #9CA3AF;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 3rem;
            line-height: 1.7;
        }

        .content-box {
            background-color: rgba(23, 23, 38, 0.7);
            border: 1px solid rgba(55, 65, 81, 0.4);
            border-radius: 0.75rem;
            padding: 2rem;
            backdrop-filter: blur(8px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .about-nba-icon {
            font-size: 2.5rem;
            color: #60A5FA;
            margin-bottom: 0.75rem;
        }

        .moment-card {
            background-color: rgba(30, 30, 48, 0.8);
            border-radius: 0.75rem;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .moment-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
        }

        .moment-card img {
            height: 200px;
            width: 100%;
            object-fit: cover;
        }

        .team-swiper-container {
            width: 100%;
            padding-top: 20px;
            padding-bottom: 40px;
        }

        .team-swiper-slide {
            background-position: center;
            background-size: cover;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
        }

        .team-swiper-slide:hover {
            transform: scale(1.1);
        }

        .team-swiper-slide img {
            display: block;
            max-width: 90px;
            max-height: 90px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
        }

        .swiper-pagination-bullet-active {
            background-color: #3B82F6 !important;
        }

        .stat-item {
            border-left: 4px solid #3B82F6;
            padding-left: 1.5rem;
        }

        .stat-number {
            font-family: 'Teko', sans-serif;
            font-size: clamp(2.5rem, 7vw, 4.5rem);
            font-weight: 700;
            color: #60A5FA;
            line-height: 1;
        }

        .mvp-card {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: rgba(23, 23, 38, 0.7);
            backdrop-filter: blur(5px);
        }

        .mvp-card:hover {
            transform: translateY(-10px) scale(1.03);
            box-shadow: 0 20px 40px rgba(29, 78, 216, 0.4);
        }

        .mvp-card img.mvp-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .mvp-card:hover img.mvp-photo {
            transform: scale(1.1);
        }

        .mvp-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.1) 60%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 1.5rem;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .mvp-play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(29, 78, 216, 0.6);
            color: white;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            cursor: pointer;
            border: none;
            backdrop-filter: blur(3px);
            transition: background-color 0.3s ease, transform 0.3s ease;
            opacity: 0;
        }

        .mvp-card:hover .mvp-play-button {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1.1);
        }

        .mvp-play-button:hover {
            background-color: #1E40AF;
        }

        .video-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            backdrop-filter: blur(8px);
        }

        .video-modal.is-active {
            opacity: 1;
            visibility: visible;
        }

        .video-modal-content {
            position: relative;
            width: 90%;
            max-width: 960px;
            background-color: #000;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
            border-radius: 8px;
            overflow: hidden;
        }

        .video-modal-close {
            position: absolute;
            top: -40px;
            right: 0px;
            background: none;
            border: none;
            color: white;
            font-size: 2.5rem;
            cursor: pointer;
            padding: 5px;
            line-height: 1;
        }

        .video-iframe-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }

        .video-iframe-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .scroll-reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }

        .scroll-reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .stagger-delay-1 {
            transition-delay: 0.1s;
        }

        .stagger-delay-2 {
            transition-delay: 0.2s;
        }

        .stagger-delay-3 {
            transition-delay: 0.3s;
        }

        .stagger-delay-4 {
            transition-delay: 0.4s;
        }
    </style>
</head>

<body class="text-gray-200">
    <section class="hero-section-static">
        <video autoplay loop muted playsinline class="hero-video-background">
            <source src="videos/dunk.mp4" type="video/mp4">
            Browser Anda tidak mendukung tag video.
        </video>
        <div class="hero-content-static"> <img src="https://cdn.nba.com/logos/nba/nba-logoman-word-white.svg" alt="NBA Logo" class="hero-nba-logo mx-auto scroll-reveal">
            <h1 class="hero-title-text font-teko scroll-reveal stagger-delay-1">NBA UNIVERSE</h1>
            <p class="hero-subtitle-text scroll-reveal stagger-delay-2"> Dive into the world of NBA statistics. Explore player performances, team dynamics, and iconic moments. </p> <a href="#about-nba-section" class="hero-cta-button scroll-reveal stagger-delay-3"> Discover the League </a>
        </div> <a href="#about-nba-section" aria-label="Scroll down" class="hero-scroll-indicator"> <svg class="w-8 h-8 md:w-10 md:h-10 text-white opacity-75" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                <path d="M19 9l-7 7-7-7"></path>
            </svg> </a>
    </section>
    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24 space-y-20 md:space-y-32">
        <section id="about-nba-section" class="scroll-reveal">
            <h2 class="section-title text-center">THE GLOBAL GAME</h2>
            <p class="section-subtitle text-center"> More than just a league, the NBA is a global phenomenon captivating millions with its athleticism, drama, and star power. </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                <div class="content-box scroll-reveal stagger-delay-1"> <i class="fas fa-basketball-ball about-nba-icon"></i>
                    <h3 class="text-xl font-semibold font-rajdhani mb-2">Rich History</h3>
                    <p class="text-gray-400 text-sm">Founded in 1946, evolving through decades of legendary players and dynasties.</p>
                </div>
                <div class="content-box scroll-reveal stagger-delay-2"> <i class="fas fa-globe-americas about-nba-icon"></i>
                    <h3 class="text-xl font-semibold font-rajdhani mb-2">Global Reach</h3>
                    <p class="text-gray-400 text-sm">Broadcasted in over 200 countries, with a diverse international player base.</p>
                </div>
                <div class="content-box scroll-reveal stagger-delay-3"> <i class="fas fa-users about-nba-icon"></i>
                    <h3 class="text-xl font-semibold font-rajdhani mb-2">Passionate Fans</h3>
                    <p class="text-gray-400 text-sm">Millions of dedicated fans worldwide, creating an electric atmosphere.</p>
                </div>
            </div>
        </section>
        <section id="mvp-section" class="scroll-reveal">
            <h2 class="section-title text-center">ICONS OF THE ERA</h2>
            <p class="section-subtitle text-center">Relive the brilliance of the league's Most Valuable Players from a golden age of basketball.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 md:gap-8">
                <?php
                $mvps_data = [
                    ["photo" => "https://cdn.bleacherreport.net/images_root/slides/photos/000/649/247/108099592_original.jpg?1295450612", "name" => "Steve Nash", "year" => 2006, "team" => "Phoenix Suns", "videoId" => "7AxN0pB6HF0"],
                    ["photo" => "https://www.usatoday.com/gcdn/-mm-/ad07fc0628987cc179f39e4ceddb377d02845c02/c=0-40-2272-3069/local/-/media/2017/03/08/USATODAY/USATODAY/636245933357106206-USATSI-9856648.jpg", "name" => "Dirk Nowitzki", "year" => 2007, "team" => "Dallas Mavericks", "videoId" => "BfixJPEky1I"],
                    ["photo" => "https://www.azcentral.com/gcdn/-mm-/cbfeb8d6b0b0bc78b7d9dceb61cef28e69e10a81/c=787-0-2838-2735/local/-/media/2016/03/18/Phoenix/Phoenix/635939105909599013-kobe-bryant.jpg", "name" => "Kobe Bryant", "year" => 2008, "team" => "Los Angeles Lakers", "videoId" => "1fjhIWJSxfw"],
                    ["photo" => "https://i.redd.it/x4haghyycwga1.jpg", "name" => "LeBron James", "year" => 2009, "team" => "Cleveland Cavaliers", "videoId" => "-9lP95Qo-I0"],
                    ["photo" => "https://cdn.bleacherreport.net/images_root/slides/photos/000/791/675/109813237_original.jpg?1300221121", "name" => "Derrick Rose", "year" => 2011, "team" => "Chicago Bulls", "videoId" => "ybOi639tb7U"],
                ];
                $delay_index = 1;
                foreach ($mvps_data as $mvp) :
                ?>
                    <div class="mvp-card aspect-[3/4] scroll-reveal stagger-delay-<?php echo $delay_index++; ?>"> <img src="<?php echo htmlspecialchars($mvp['photo']); ?>" alt="<?php echo htmlspecialchars($mvp['name']); ?> - MVP <?php echo $mvp['year']; ?>" class="mvp-photo" loading="lazy">
                        <div class="mvp-overlay">
                            <div>
                                <h3 class="text-2xl lg:text-xl xl:text-2xl font-bold font-teko tracking-wide"><?php echo htmlspecialchars($mvp['name']); ?></h3>
                                <p class="text-sm text-blue-400">MVP <?php echo $mvp['year']; ?> <span class="text-gray-400"><?php echo isset($mvp['team']) ? ' - ' . htmlspecialchars($mvp['team']) : ''; ?></span></p>
                            </div>
                        </div> <button class="mvp-play-button" aria-label="Play video for <?php echo htmlspecialchars($mvp['name']); ?>" data-video-id="<?php echo htmlspecialchars($mvp['videoId']); ?>"> <i class="fas fa-play"></i> </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section id="legendary-moments" class="scroll-reveal">
            <h2 class="section-title text-center">LEGENDARY MOMENTS</h2>
            <p class="section-subtitle text-center"> The NBA is built on unforgettable plays and performances that define generations. Here are a few. </p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                $moments = [["image" => "https://cdn.nba.com/teams/legacy/www.nba.com/magic/sites/magic/files/fern-story-1.jpg", "title" => "Jordan's Last Shot", "description" => "Michael Jordan's iconic game-winner in the 1998 NBA Finals.", "link" => "https://en.wikipedia.org/wiki/The_Shot"], ["image" => "https://pbs.twimg.com/media/EO5HAshW4AEO_5M.jpg:large", "title" => "Kobe's 81 Points", "description" => "Kobe Bryant's monumental 81-point performance against the Raptors.", "link" => "https://lakersnation.com/this-day-in-lakers-history-kobe-bryant-scores-81-points-against-raptors/"], ["image" => "https://franchisesports.co.uk/wp-content/uploads/2020/04/LeBron-block.jpg", "title" => "Blocked by James", "description" => "With the score tied late in Game 7, LeBron chased down Iguodala and made an iconic block to preserve the tie.", "link" => "https://en.wikipedia.org/wiki/The_Block_(basketball)"],];
                $moment_delay = 1;
                foreach ($moments as $moment):
                ?>
                    <div class="moment-card content-box p-0 scroll-reveal stagger-delay-<?php echo $moment_delay++; ?>"> <img src="<?php echo htmlspecialchars($moment['image']); ?>" alt="<?php echo htmlspecialchars($moment['title']); ?>" loading="lazy">
                        <div class="p-6">
                            <h3 class="text-xl font-semibold font-rajdhani mb-2 text-blue-300"><?php echo htmlspecialchars($moment['title']); ?></h3>
                            <p class="text-sm text-gray-400 mb-4"><?php echo htmlspecialchars($moment['description']); ?></p> <a href="<?php echo htmlspecialchars($moment['link']); ?>" target="_blank" class="text-sm font-semibold text-blue-400 hover:text-blue-300 transition">Watch/Read More <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="scroll-reveal">
            <h2 class="section-title text-center">NBA BY THE NUMBERS</h2>
            <p class="section-subtitle text-center">A quick look at some fascinating figures that shape the league.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-center md:text-left">
                <div class="stat-item scroll-reveal stagger-delay-1">
                    <p class="stat-number">30</p>
                    <p class="text-lg font-semibold font-rajdhani text-gray-300">Teams Competing</p>
                </div>
                <div class="stat-item scroll-reveal stagger-delay-2">
                    <p class="stat-number">75+</p>
                    <p class="text-lg font-semibold font-rajdhani text-gray-300">Years of History</p>
                </div>
                <div class="stat-item scroll-reveal stagger-delay-3">
                    <p class="stat-number">450+</p>
                    <p class="text-lg font-semibold font-rajdhani text-gray-300">Active Players</p>
                </div>
                <div class="stat-item scroll-reveal stagger-delay-4">
                    <p class="stat-number">200+</p>
                    <p class="text-lg font-semibold font-rajdhani text-gray-300">Countries Reached</p>
                </div>
            </div>
        </section>
        <section class="scroll-reveal">
            <h2 class="section-title text-center">MEET THE TEAMS</h2>
            <p class="section-subtitle text-center">Discover the franchises that make up the National Basketball Association.</p>
            <div class="team-swiper-container swiper-container">
                <div class="swiper-wrapper">
                    <?php
                    $teams_data = [["name" => "Atlanta Hawks", "code" => "ATL", "logo" => "https://cdn.nba.com/logos/nba/1610612737/global/L/logo.svg"], ["name" => "Boston Celtics", "code" => "BOS", "logo" => "https://cdn.nba.com/logos/nba/1610612738/global/L/logo.svg"], ["name" => "Brooklyn Nets", "code" => "BKN", "logo" => "https://cdn.nba.com/logos/nba/1610612751/global/L/logo.svg"], ["name" => "Charlotte Hornets", "code" => "CHA", "logo" => "https://cdn.nba.com/logos/nba/1610612766/global/L/logo.svg"], ["name" => "Chicago Bulls", "code" => "CHI", "logo" => "https://cdn.nba.com/logos/nba/1610612741/global/L/logo.svg"], ["name" => "Cleveland Cavaliers", "code" => "CLE", "logo" => "https://cdn.nba.com/logos/nba/1610612739/global/L/logo.svg"], ["name" => "Detroit Pistons", "code" => "DET", "logo" => "https://cdn.nba.com/logos/nba/1610612765/global/L/logo.svg"], ["name" => "Indiana Pacers", "code" => "IND", "logo" => "https://cdn.nba.com/logos/nba/1610612754/global/L/logo.svg"], ["name" => "Miami Heat", "code" => "MIA", "logo" => "https://cdn.nba.com/logos/nba/1610612748/global/L/logo.svg"], ["name" => "Milwaukee Bucks", "code" => "MIL", "logo" => "https://cdn.nba.com/logos/nba/1610612749/global/L/logo.svg"], ["name" => "New York Knicks", "code" => "NYK", "logo" => "https://cdn.nba.com/logos/nba/1610612752/global/L/logo.svg"], ["name" => "Orlando Magic", "code" => "ORL", "logo" => "https://cdn.nba.com/logos/nba/1610612753/global/L/logo.svg"], ["name" => "Philadelphia 76ers", "code" => "PHI", "logo" => "https://cdn.nba.com/logos/nba/1610612755/global/L/logo.svg"], ["name" => "Toronto Raptors", "code" => "TOR", "logo" => "https://cdn.nba.com/logos/nba/1610612761/global/L/logo.svg"], ["name" => "Washington Wizards", "code" => "WAS", "logo" => "https://cdn.nba.com/logos/nba/1610612764/global/L/logo.svg"], ["name" => "Dallas Mavericks", "code" => "DAL", "logo" => "https://cdn.nba.com/logos/nba/1610612742/global/L/logo.svg"], ["name" => "Denver Nuggets", "code" => "DEN", "logo" => "https://cdn.nba.com/logos/nba/1610612743/global/L/logo.svg"], ["name" => "Golden State Warriors", "code" => "GSW", "logo" => "https://cdn.nba.com/logos/nba/1610612744/global/L/logo.svg"], ["name" => "Houston Rockets", "code" => "HOU", "logo" => "https://cdn.nba.com/logos/nba/1610612745/global/L/logo.svg"], ["name" => "LA Clippers", "code" => "LAC", "logo" => "https://cdn.nba.com/logos/nba/1610612746/global/L/logo.svg"], ["name" => "Los Angeles Lakers", "code" => "LAL", "logo" => "https://cdn.nba.com/logos/nba/1610612747/global/L/logo.svg"], ["name" => "Memphis Grizzlies", "code" => "MEM", "logo" => "https://cdn.nba.com/logos/nba/1610612763/global/L/logo.svg"], ["name" => "Minnesota Timberwolves", "code" => "MIN", "logo" => "https://cdn.nba.com/logos/nba/1610612750/global/L/logo.svg"], ["name" => "New Orleans Pelicans", "code" => "NOP", "logo" => "https://cdn.nba.com/logos/nba/1610612740/global/L/logo.svg"], ["name" => "Oklahoma City Thunder", "code" => "OKC", "logo" => "https://cdn.nba.com/logos/nba/1610612760/global/L/logo.svg"], ["name" => "Phoenix Suns", "code" => "PHX", "logo" => "https://cdn.nba.com/logos/nba/1610612756/global/L/logo.svg"], ["name" => "Portland Trail Blazers", "code" => "POR", "logo" => "https://cdn.nba.com/logos/nba/1610612757/global/L/logo.svg"], ["name" => "Sacramento Kings", "code" => "SAC", "logo" => "https://cdn.nba.com/logos/nba/1610612758/global/L/logo.svg"], ["name" => "San Antonio Spurs", "code" => "SAS", "logo" => "https://cdn.nba.com/logos/nba/1610612759/global/L/logo.svg"], ["name" => "Utah Jazz", "code" => "UTA", "logo" => "https://cdn.nba.com/logos/nba/1610612762/global/L/logo.svg"],];
                    foreach ($teams_data as $team):
                    ?>
                        <div class="swiper-slide">
                            <a href="teams_stats.php?teams[]=<?php echo urlencode($team['code']); ?>" title="View stats for <?php echo htmlspecialchars($team['name']); ?>">
                                <img src="<?php echo htmlspecialchars($team['logo']); ?>" alt="<?php echo htmlspecialchars($team['name']); ?> Logo" loading="lazy">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
            <div class="text-center mt-8"> <a href="teams_stats.php" class="hero-cta-button text-sm px-6 py-3">View All Teams</a> </div>
        </section>
        <section class="content-box text-center py-12 md:py-16 scroll-reveal">
            <h2 class="section-title">READY TO DIVE DEEPER?</h2>
            <p class="section-subtitle !mb-8"> Your ultimate NBA journey starts now. Explore detailed player stats, team performances, and historical data. </p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4"> <a href="player_stats.php" class="hero-cta-button w-full sm:w-auto">Explore Player Stats</a> <a href="search_page.php" class="bg-gray-600 hover:bg-gray-500 text-white px-8 py-3 rounded-lg font-semibold w-full sm:w-auto transition transform hover:scale-105">Search Database</a> </div>
        </section>
    </main>
    <footer class="text-center p-8 mt-12 border-t border-gray-700/30">
        <p class="text-gray-500 text-sm">© <?php echo date("Y"); ?> NBA Universe Dashboard. All Rights Reserved by You.</p>
    </footer>
    <div id="videoModal" class="video-modal">
        <div class="video-modal-content"> <button id="videoModalClose" class="video-modal-close" aria-label="Close video modal">×</button>
            <div id="videoPlayerContainer" class="video-iframe-container"></div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const scrollRevealElements = document.querySelectorAll('.scroll-reveal');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                    }
                });
            }, {
                threshold: 0.05
            });
            scrollRevealElements.forEach(el => observer.observe(el));
            const videoModal = document.getElementById('videoModal');
            const videoModalClose = document.getElementById('videoModalClose');
            const videoPlayerContainer = document.getElementById('videoPlayerContainer');
            document.querySelectorAll('.mvp-play-button').forEach(button => {
                button.addEventListener('click', () => {
                    const videoId = button.dataset.videoId;
                    if (videoId && !videoId.includes("YOUR_") && videoId.trim() !== "") {
                        videoPlayerContainer.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
                        videoModal.classList.add('is-active');
                        document.body.style.overflow = 'hidden';
                    } else {
                        alert('Video for this player is not available yet.');
                    }
                });
            });
            const closeModal = () => {
                videoModal.classList.remove('is-active');
                videoPlayerContainer.innerHTML = '';
                document.body.style.overflow = '';
            };
            videoModalClose.addEventListener('click', closeModal);
            videoModal.addEventListener('click', (event) => {
                if (event.target === videoModal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && videoModal.classList.contains('is-active')) closeModal();
            });
            if (typeof Swiper !== 'undefined') {
                new Swiper('.team-swiper-container', {
                    effect: 'coverflow',
                    grabCursor: true,
                    centeredSlides: true,
                    slidesPerView: 'auto',
                    loop: true,
                    coverflowEffect: {
                        rotate: 30,
                        stretch: 0,
                        depth: 100,
                        modifier: 1,
                        slideShadows: false,
                    },
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                    autoplay: {
                        delay: 3000,
                        disableOnInteraction: false,
                    },
                    breakpoints: {
                        640: {
                            slidesPerView: 3,
                            spaceBetween: 20,
                        },
                        768: {
                            slidesPerView: 4,
                            spaceBetween: 30,
                        },
                        1024: {
                            slidesPerView: 5,
                            spaceBetween: 40,
                        },
                        1280: {
                            slidesPerView: 7,
                            spaceBetween: 40,
                        },
                    }
                });
            }
        });
    </script>
</body>

</html>