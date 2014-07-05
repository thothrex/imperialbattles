#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 
?>
<!DOCTYPE html>
<html>
<head>
    <title>IMPERIAL BATTLES</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="css/lobby.css" />
	<link rel="stylesheet" type="text/css" href="css/chat.css" />
    <script src="js/jquery-1.10.1.min.js"></script>
    <script src="js/jquery.cookie.js"></script>
    <script src="js/login.js"></script>
	<script src="js/chat.js"></script>
	<script src="js/lobby.js"></script>
    <noscript>
        This page requires JavaScript. You can either switch to a browser that supports
        JavaScript or turn your browser's script support on.
    </noscript>
</head>

<body onload="initialise()">
<div id="page">

<div id="logoutScreen">
<?php
$db_server = db_connect();
echo "<span id='usernameLabel'>" 
    . filter_string($db_server, $_SESSION['username']) 
    . "</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
$username = filter_string($db_server, $_SESSION['username']);

$query = "SELECT Wins,Defeats
          FROM Players
          WHERE UserName = '$username'";
$result = $db_server->query($query); 
$row = $result->fetch_row();
echo "W: $row[0] &nbsp;&nbsp;&nbsp; D: $row[1]&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

$result->free();
$db_server->close();
?>
<button type="button" onclick="logout()">Logout</button>
</div>

<div id="headerMenu">
<button class = "green" type="button" onclick="popUpStoryL()">Story</button><!--
--><button class = "green" type="button" onclick="popUpRulesL()">Gameplay</button><!--
--><button class = "green" type="button" onclick="popUpScoresL()">HighScores</button>
</div>

<div id="onlinePlayers">
<h2 class="yellow">Players Online</h2>
<hr />
<table id="onlinePlayersTable">
</table>
</div>

<div id="chatScreen">
<h2 id="chatLabel" class="yellow">Pre-Game Chat Room</h2>
<div id="chat-area">
</div>
<form id="messageForm">
<textarea id="msg" onKeyDown="if(event.keyCode==13) sendMessage();" maxlength = '60' cols='60' rows='1'></textarea>
</form>
</div>


<div id="gameSelectionScreen">
<div id="serverBrowser">
<h2 class="yellow">Game Browser</h2>
<form id="serverForm">
<div id="serverList">
</div>
<br />
<input type="hidden" name="server" />
<button type="button" onclick="createGame()">Create</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<button id="joinBtn" type="button" disabled>Join</button>
</form>
</div>
</div>

<div id="gameSetupScreen">
<div id="gameOptions">
<h2 class="yellow">Game options</h2>
<table id="optionsTable" cellspacing="10">
    <tr>
        <th>Game:</th>
        <td id="gameName"></td>
    </tr>
    <tr>
        <th>Map:</th>
        <td id="map"></td>
    </tr>
    <tr>
        <th>Number of players:</th>
        <td id="noPlayers"></td>
    </tr>
    <tr>
        <th>Maximum turn time:</th>
        <td id="turnTime"></td>
        <td>seconds&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(0 = unlimited)</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
    </tr>
    <tr>
        <th>Player</th>
        <th>Name</th>
        <th>Colour</th>
        <th>Team</th>
        <th>Ready</th>
    </tr>
    <tr>
        <td>Player 1</td>
        <td id="p1Name"></td>
        <td id="p1Colour"></td>
        <td id="p1Team"></td>
        <td id="p1Ready"></td>
    </tr>
    <tr>
        <td>Player 2</td>
        <td id="p2Name"></td>
        <td id="p2Colour"></td>
        <td id="p2Team"></td>
        <td id="p2Ready"></td>
        <td id="p2Kick"></td>
    </tr>
    <tr id="p3">
        <td>Player 3</td>
        <td id="p3Name"></td>
        <td id="p3Colour"></td>
        <td id="p3Team"></td>
        <td id="p3Ready"></td>
        <td id="p3Kick"></td>
    </tr>
    <tr id="p4">
        <td>Player 4</td>
        <td id="p4Name"></td>
        <td id="p4Colour"></td>
        <td id="p4Team"></td>
        <td id="p4Ready"></td>
        <td id="p4Kick"></td>
    </tr>
    <tr id="p5">
        <td>Player 5</td>
        <td id="p5Name"></td>
        <td id="p5Colour"></td>
        <td id="p5Team"></td>
        <td id="p5Ready"></td>
        <td id="p5Kick"></td>
    </tr>
    <tr id="p6">
        <td>Player 6</td>
        <td id="p6Name"></td>
        <td id="p6Colour"></td>
        <td id="p6Team"></td>
        <td id="p6Ready"></td>
        <td id="p6Kick"></td>
    </tr>
</table>
</div>

<div id="mapPreview">
<h2 class="yellow">Map Preview </h2>
<div id="mapImage"></div>

<button id="backBtn" type="button">Back</button>
<p>&nbsp;</p>
<form id="startGameForm" action="game.php" method="post">
<button type="button" id="startReadyBtn"></button>
<input type="hidden" name="gameid" />
</form>
</div>
</div>


<div id="story" class="popup">
<h1 class="yellow">Story</h1>
<?php
include_once("story.html");
?>
<br />
<button type="button" onclick="hideStoryL()">Close</button>
</div>

<div id="rules" class="popup">
<h1 class="yellow">How to play</h1>
<?php
include_once("rules.html");
?>
<br />
<button type="button" onclick="hideRulesL()">Close</button>
</div>

<div id="scores" class="popup">
<h1 class="yellow">Highscores</h1>
<table id="scoresTable" border="5">
</table>
<br />
<button type="button" onclick="hideScoresL()">Close</button>
</div>

<img src="img/bg_blur.jpg" alt="blur" style="display:none" />

</body>
</html>

