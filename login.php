#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username']) && isset($_POST['password'])) {
    $db_server = db_connect();

    $username = filter_string($db_server, $_POST['username']);
    $password = filter_string($db_server, $_POST['password']);

    $query = "SELECT * FROM Players WHERE UserName = '$username'";
    $result = $db_server->query($query);
    if (!$result) {
        echo "Invalid username.";
    }
    else if($result->num_rows != 1){
        echo "Invalid username.";
        $result->free();
    }        
    else {
        $row = $result->fetch_row(); $result->free();

        if ($row[0] === $username && $row[1] === encrypt_password($password)) {
            $_SESSION['username'] = $username;
            $query = "UPDATE Players 
                      SET LoggedOn = true
                      WHERE UserName = '$username'";
            $result = $db_server->query($query);
            if (!$result) {
            }
            else {
                echo 'true';
                $result->free();
            }

            echo 'true'; //following old logic            
        } 
        else {
            echo "Invalid username/password combination";
        }
    } 

    $db_server->close();
} 

?>
