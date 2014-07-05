#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$db_server = db_connect();               
$query = "SELECT UserName AS username, 
                 Wins AS wins,
                 Defeats AS defeats
          FROM Players      
          WHERE UserName <> 'System'    
          ORDER BY Wins-Defeats DESC, Wins DESC
          LIMIT 20";
$result = $db_server->query($query);
if ($result) {
    echo sqlresult_to_json($result);
    $result->free();
}     

$db_server->close();
?>
