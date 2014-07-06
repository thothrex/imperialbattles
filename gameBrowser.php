#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$db_server = db_connect();
$username = $_SESSION['username'];
   
$query =
"SELECT DISTINCT Games.GameID AS gameid,GameName AS gamename,
                 MapName AS mapname,     PlayersLimit AS playerslimit,
                 NoPlayers AS noplayers, InProgress AS inprogress
FROM PlayersGames
     RIGHT JOIN Games ON PlayersGames.GameID = Games.GameID
     NATURAL JOIN Maps
WHERE (UserName = '$username' AND InProgress = true)
   OR (InProgress = false
       AND HostName <> '$username'
       AND NoPlayers < PlayersLimit)
ORDER BY InProgress DESC";
    	              
$result = $db_server->query($query);
if ($result) {
  echo sqlresult_to_json($result);
  $result->free();
}

$db_server->close();

?>
