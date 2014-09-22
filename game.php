#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username']) || !isset($_POST['gameid'])) {
    header("Location: index.php");
    exit;
}
$dbh = db_connect();
$sth = $dbh->prepare(
   "SELECT GameName
    FROM   Games
    WHERE  GameID = ? AND InProgress = true"
);
$sth->execute([ trim($_POST['gameid']) ]);

$row = $sth->fetch();
if (!$row) {
    header("Location: lobby.php");
    exit;
} //else continue execution
$gamename = filter_var($row[0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
?>

<!DOCTYPE html>
<html>
<head>
    <title>IMPERIAL BATTLES - Battlefield</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
  	<link rel="stylesheet" type="text/css" href="css/chat.css" />
    <link rel="stylesheet" type="text/css" href="css/game.css" />
    <link rel="stylesheet" href="css/gview.css">
    
    <script src="js/jquery-1.10.1.min.js"></script>
    <script src="js/jquery.gamequery-0.7.1-patch1.js"></script>
    <script src="js/jquery.cookie.js"></script>
    <script src="js/json2.js"></script>
    <script src="js/login.js"></script>
  	<script src="js/game.js"></script>
    <script src="js/utility.js"></script>
    <script src="js/model.js"></script>
    <script src="js/gview.js"></script>
    <script src="js/gview-support.js"></script>
    <script src="js/gview-units.js"></script>

    <?php
        $gameid = filter_var(trim($_POST['gameid']), FILTER_SANITIZE_NUMBER_INT);
        echo "<script>var game = new Object(); game.gameid = $gameid;</script>";
    ?>
  	
    <noscript>
        This page requires JavaScript. You can either switch to a browser that supports
        JavaScript or turn tour browser's script support on.
    </noscript>
</head>

<?php
    echo "<body onload=\"showGameScreen('$gamename')\">";
?>

<div id="page">
    <div id="logoutScreen">
        <?php
            $username         = $_SESSION['username'];
            $filteredUsername = filter_var($username,
                                           FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            echo "<span id='usernameLabel'>"
               .  $filteredUsername
               . "</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

            $sth = $dbh->prepare(
               "SELECT Wins, Defeats
                FROM   Players
                WHERE  UserName = ?"
            );
            $sth->execute([$username]);

            $row    = $sth->fetch();
            $wins   = filter_var($row[0], FILTER_SANITIZE_NUMBER_INT);
            $losses = filter_var($row[1], FILTER_SANITIZE_NUMBER_INT);
            echo "W: <span id='winsLabel'>$wins</span>"
               . "&nbsp;&nbsp;&nbsp; "
               . "D: <span id='defeatsLabel'>$losses</span>"
               . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

            $sth = $dbh->prepare(
               "UPDATE Players
                SET    LoggedOn = true
                WHERE  UserName = ?"
            );
            $sth->execute([$username]);
            $dbh = null; //close connection
        ?>
        <button type="button" onclick="logout()">Logout</button>
    </div>
    
    <?php include "chat.html"; ?>

    <div id ="buttons2">
        <button class="green" type="button" onclick="resign()" id="resignButton">
            Resign
        </button>
        &nbsp;&nbsp;&nbsp;
        <button class="green" type="button" onclick="returnToLobby()">
            Back to Home
        </button>
    </div>

    <div id = "gameScreen">
        <h2 id="gameLabel" class="yellow"></h2>
        <div id="playground"></div>
    </div>
</div>
</body>
</html>
