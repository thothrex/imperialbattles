<?php

require_once('config.php');

$db_server = db_connect();
$username = $_SESSION['username'];

$sth = $db_server->prepare(
   "SELECT Wins, Defeats
    FROM Players
    WHERE UserName = ?"
);
$sth->execute([$username]);
$row = $sth->fetch();
echo json_encode($row);

$db_server = null; //close connection

?>
