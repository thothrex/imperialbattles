#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username']) && isset($_POST['password'])) {
    $db_server = db_connect();

    $username = $_POST['username'];
    $password = $_POST['password'];

    $db_server = db_connect();
    $sth = $db_server->prepare(
       "SELECT UserName, PwdHash
        FROM Players
        WHERE UserName = ?"
    );
    $result = $sth->execute([$username]);

    $row = $sth->fetch();
    if (!$row) {
        echo "Invalid username or password";
    }
    else if ($sth->fetch()) {
        echo "Invalid username or password";
        error_log('Error in login.php - non-unique username',
                   3, 'debug.log');
    }
    else {
        if ( $row[0] === $username && password_verify($password, $row[1]) ){
            $_SESSION['username'] = $username;
            $sth = $db_server->prepare(
               "UPDATE Players
                SET LoggedOn = true
                WHERE UserName = ?"
            );
            $result = $sth->execute([$username]);
            echo 'true';
        } 
        else { echo "Invalid username or password"; }
    }
    $db_server = null; //close connection
} 

?>
