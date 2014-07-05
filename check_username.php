#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username'])) {
    $db_server = db_connect();
    $username = filter_string($db_server, $_POST['username']);

    $query = "SELECT * FROM Players WHERE UserName = '$username'";
    $result = $db_server->query($query);

    if(!$result){
        echo "Available.";
    }
    else if ($result->num_rows === 0){
        echo "Available.";
        $result->free();
    }        
    else {
        echo "Taken.";
        $result->free();
    }

    $db_server->close();
} 

?>
