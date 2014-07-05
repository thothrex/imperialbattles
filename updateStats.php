#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$db_server = db_connect();
$username = $_SESSION['username'];

$query = "SELECT Wins,Defeats 
          FROM Players
          WHERE UserName = '$username'";

$result = $db_server->query($query);
if ($result) {
    echo sqlresult_to_json($result);
    $result->free();
}

$db_server->close();

?>
