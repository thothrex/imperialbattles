#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username'])
&&  isset($_POST['password'])
&&  isset($_POST['email'])) {
    $username       = $_POST['username'];
    $encrypted_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $dbh = db_connect();
    $sth = $dbh->prepare(
        "INSERT INTO Players(UserName, PwdHash, Email)
         VALUES             (?,        ?,       ?    )"
    );
    $sth->execute([$username, $encrypted_pass, $_POST['email']]);

    // if no exception thrown
    $_SESSION['username'] = $username;
    echo "true";
    $db_server = null; //close connection
}

?>
