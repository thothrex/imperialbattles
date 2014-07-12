#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$db_server = db_connect();
$username = $_SESSION['username'];
   
$sth = $db_server->prepare(
   "SELECT DISTINCT GameID AS gameid,       GameName AS gamename,
                    MapName AS mapname,     PlayersLimit AS playerslimit,
                    NoPlayers AS noplayers, InProgress AS inprogress
    FROM PlayersGames
         RIGHT JOIN Games USING(GameID)
         NATURAL JOIN Maps
    WHERE (UserName = ? AND InProgress = true)
       OR (InProgress = false
           AND HostName <> ?
           AND NoPlayers < PlayersLimit)
    ORDER BY InProgress DESC"
);
$result = $sth->execute([$username, $username]);
echo sqlresult_to_json($sth);

$db_server = null; //close connection
?>