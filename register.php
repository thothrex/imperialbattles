#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username'])
&&  isset($_POST['password'])
&&  isset($_POST['email'])) {
    $username = $_POST['username'];
    $encrypted_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $db_server = db_connect();
    $statement
        = $db_server->prepare("INSERT INTO Players(UserName,PwdHash,Email)
                               VALUES(?,?,?)");
    $statement->bind_param('sss',
                           $username,
                           $encrypted_pass,
                           $_POST['email']);
    if (!( $statement->execute() )) {
        $registerError = $db_server->error;
        error_log("\r\nregister.php error: $registerError", 3, 'debug.log');
        echo "false";
    }
    else {
        $_SESSION['username'] = $username;
        echo "true";
    }

    $statement->close();
    $db_server->close();
}

?>
