#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$dbh = db_connect();
$query = "SELECT UserName AS username, 
                 Wins     AS wins,
                 Defeats  AS defeats
          FROM  Players
          WHERE UserName <> 'System'    
          ORDER BY Wins-Defeats DESC, Wins DESC
          LIMIT 20";
$result = $dbh->query($query);

echo sql_result_to_json_array($result, PDO::FETCH_ASSOC);

$dbh = null; //close connection
?>
