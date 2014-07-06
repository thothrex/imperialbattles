#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username']) && isset($_POST['password'])) {
    $db_server = db_connect();

    $username = $_POST['username'];
    $password = $_POST['password'];

    $db_server = db_connect();
    $statement = $db_server->prepare("SELECT UserName, PwdHash
                                      FROM Players
                                      WHERE UserName = ?");
    $statement->bind_param('s', $username);
    if (!( $statement->execute() )) {
        error_log('SQL Error in login.php', 3, 'debug.log');
    }

    $result = $statement->get_result(); $statement->close();
    if (!$result) {
        echo "Invalid username.";
    }
    else if($result->num_rows != 1){
        echo "Invalid username.";
        $result->free();
    }        
    else {
        $row = $result->fetch_row(); $result->free();
        if ( $row[0] === $username && password_verify($password, $row[1]) ){
            $_SESSION['username'] = $username;
            $statement = $db_server->prepare("UPDATE Players
                                              SET LoggedOn = true
                                              WHERE UserName = ?");
            $statement->bind_param('s', $username);
            if (!( $statement->execute() )) {
                error_log('SQL Error in login.php', 3, 'debug.log');
                $statement->close();
            }

            $result = $statement->get_result(); $statement->close();
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
