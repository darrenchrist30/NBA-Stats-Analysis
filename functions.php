<?php
// functions.php

/**
 * Fungsi untuk menghitung poin per game (PPG).
 *
 * @param int $points Jumlah poin.
 * @param int $games_played Jumlah pertandingan yang dimainkan.
 * @return float Poin per game.
 */
function calculate_ppg($points, $games_played) {
    return $games_played > 0 ? round($points / $games_played, 2) : 0;
}

/**
 * Fungsi untuk melakukan sanitasi input.
 *
 * @param string $data Data yang akan disanitasi.
 * @return string Data yang sudah disanitasi.
 */
function sanitize_input($data) {
    return htmlspecialchars(trim($data));
}

// Fungsi lain dapat ditambahkan di sini...
?>