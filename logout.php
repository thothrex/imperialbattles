#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$db_server = db_connect();
$username = $_SESSION['username'];
$query = "UPDATE Players 
          SET LoggedOn = false 
          WHERE UserName = '$username'";
$result = $db_server->query($query);
// non-selects return booleans,
// not resultsets
// so don't free them!
$db_server->close();  
   
$_SESSION = array();
setcookie(session_name(),'',time() - 24 * 60 * 60, '/');
session_destroy();

?>
