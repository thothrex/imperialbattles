#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$db_server = db_connect();
$username  = $_SESSION['username'];

$sth = $db_server->prepare(
   "UPDATE Players
    SET    LoggedOn = false
    WHERE  UserName = ?"
);
$sth->execute([$username]);

$db_server = null; //close connection

$_SESSION = array();
setcookie(session_name(),'',time() - 24 * 60 * 60, '/');
session_destroy();

?>
