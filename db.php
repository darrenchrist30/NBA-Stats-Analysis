<?php
require_once 'autoload.php'; // Sesuaikan path jika struktur folder Anda berbeda

$mongoConnectionString = "mongodb://localhost:27017";
$databaseName = "nba_projek";

try {
    // $client = new MongoDB\Client();
    // $client = new MongoDB\Client("mongodb://localhost:27017");
    $client = new MongoDB\Client();
    $db = $client->selectDatabase($databaseName);

    // Koleksi yang akan kita gunakan:
    $players_collection = $db->players_collection; // Untuk data pemain jika diperlukan (mis. nama pelatih)
    $coaches_collection = $db->coaches_collection; // Sumber utama untuk statistik tim tahunan dan playoff
    // karena disematkan di stint pelatih.

    // $teams = $db->teams; // KOLEKSI INI MUNGKIN SUDAH TIDAK RELEVAN JIKA DATA TIM SUDAH DISEMATKAN
    // Jika masih ada dan berisi data master nama tim (tmID -> name), bisa tetap digunakan.
    // Untuk contoh ini, kita asumsikan nama tim ada di team_season_details.name

} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) { /* ... error handling ... */
    die("...");
} catch (MongoDB\Driver\Exception\AuthenticationException $e) { /* ... error handling ... */
    die("...");
} catch (MongoDB\Driver\Exception\Exception $e) { /* ... error handling ... */
    die("...");
} catch (Exception $e) { /* ... error handling ... */
    die("...");
}

if (!function_exists('isValidMongoId')) {
    function isValidMongoId($id)
    { /* ... */
    }
}
