<?php include 'connect.php'; ?>

<div class="container">
  <h1>NBA Data Analysis</h1>

  <h2>Featured Players</h2>
  <div class="player-list">
    <?php
      $players = $db->players->find([], ['limit' => 10]); // Get a few players
      foreach ($players as $player) {
        echo "<div class='player-card'>";
        echo "<h3>" . $player['firstName'] . " " . $player['lastName'] . "</h3>";
        echo "<p>Position: " . $player['pos'] . "</p>";
        echo "<a href='players.php?id=" . $player['playerID'] . "'>View Stats</a>";
        echo "</div>";
      }
    ?>
  </div>

</div>

