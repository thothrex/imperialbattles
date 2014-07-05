#!/usr/bin/php
<?php

require_once('config.php');

if (!isset($_SESSION['username']) || !isset($_POST['gameid'])) {
    header("Location: index.php");
    exit;
}

$db_server = db_connect();
$query = "SELECT GameName FROM Games WHERE GameID = '" 
                    . filter_string($db_server, $_POST['gameid']) 
                    . "' and InProgress = true";
$result = $db_server->query($query);
if (!$result) {
    header("Location: lobby.php");
    exit;
}
else if($result->num_rows < 1){
    header("Location: lobby.php");
    $result->free();
    exit;
}

//else continue execution
$result = $result->fetch_row();
$gamename = $result[0];

$db_server->close();
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
  	<script src="js/chat.js"></script>
  	<script src="js/game.js"></script>
    <script src="js/utility.js"></script>
    <script src="js/model.js"></script>
    <script src="js/gview.js"></script>
    <script src="js/gview-support.js"></script>
    <script src="js/gview-units.js"></script>

<?php
    $db_server = db_connect();
    $gameid = filter_string($db_server, $_POST['gameid']);
    echo "<script>var game = {}; game.gameid = " . $gameid . ";</script>";
    $db_server->close();
?>
  	
    <noscript>
        This page requires JavaScript. You can either switch to a browser that supports
        JavaScript or turn tour browser's script support on.
    </noscript>
</head>

<?php
    echo "<body onload=\"showGameScreen(" . "'"  . $gamename . "'" . ")\">";
?>

<div id="page">

<div id="logoutScreen">
    <?php
        echo "<span id='usernameLabel'>" . $_SESSION['username'] . 
                "</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $username = $_SESSION['username'];
        $db_server = db_connect();
        $query = "SELECT Wins,Defeats
                  FROM Players
                  WHERE UserName = '$username'";
        $result = $db_server->query($query);
        $row = $result->fetch_row();
        echo "W: <span id='winsLabel'>" . $row[0] . 
                "</span>&nbsp;&nbsp;&nbsp; D: <span id='defeatsLabel'>" 
                . $row[1] . "</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $result->free();

        $query = "UPDATE Players
                  SET LoggedOn = true
                  WHERE UserName = '$username'";
        $db_server->query($query);
        $db_server->close();
    ?>
    <button type="button" onclick="logout()">Logout</button>
    
</div>


<div id="chatScreen">
    <h2 id="chatLabel" class="yellow">In-Game Chat Room</h2>
    <div id="chat-area">
    </div>
    <form id="messageForm">
    <textarea id="msg" onKeyDown="if(event.keyCode==13) sendMessage();" 
          maxlength = '60' cols='60' rows='1'></textarea>
    </form>
</div>
<div id ="buttons2">
<button class = "green" type="button" onclick="resign()" id="resignButton">Resign</button>
&nbsp;&nbsp;&nbsp;
<button class = "green" type="button" onclick="returnToLobby()">Back to Home</button>
</div>

<div id = "gameScreen">
<h2 id="gameLabel" class="yellow"></h2>
<div id="playground"></div>
</div>
</div>
</body>
</html>
