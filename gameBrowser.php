#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$db_server = db_connect();
$username = $_SESSION['username'];
   
$query = "SELECT GameID AS gameid,       GameName AS gamename,
                 MapID AS mapid,         PlayersLimit AS playerslimit,
                 NoPlayers AS noplayers, InProgress AS inprogress
          FROM 
(
  (
    (
      SELECT GameID, GameName, MapID, PlayersLimit, NoPlayers, InProgress
      FROM PlayersGames NATURAL JOIN Games 
      WHERE UserName = '$username' AND InProgress = true
    )
    UNION
    (
      SELECT GameID, GameName, MapID, PlayersLimit, NoPlayers, InProgress
      FROM Games 
      WHERE InProgress = false AND HostName <> '$username' 
        AND NoPlayers < PlayersLimit
    )
  )
  NATURAL JOIN Maps
) 
ORDER BY InProgress DESC";
    	              
$result = $db_server->query($query);
if ($result) {
  echo sqlresult_to_json($result);
  $result->free();
}

$db_server->close();

?>
