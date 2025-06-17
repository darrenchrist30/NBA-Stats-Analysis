<?php
require_once 'autoload.php';

$mongoConnectionString = "mongodb://localhost:27017";
$databaseName = "nba_projek";

try {
    // $client = new MongoDB\Client();
    $client = new MongoDB\Client("mongodb://mongo:mongo@localhost:27017");
    // $client = new MongoDB\Client();
    $db = $client->selectDatabase($databaseName);

    $players_collection = $db->players_collection; // Untuk data pemain jika diperlukan (mis. nama pelatih)
    $coaches_collection = $db->coaches_collection; // Sumber utama untuk statistik tim tahunan dan playoff
    // $teams_collection = $db->teams;
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
