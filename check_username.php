#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username'])) {
    $dbh       = db_connect();
    $username  = $_POST['username'];

    $sth = $dbh->prepare("SELECT * FROM Players WHERE UserName = ?");
    $sth->execute([$username]);

    if (!$sth->fetch()) { echo "Available."; }
    else                { echo "Taken.";     }

    $db_server = null; //close connection
}

?>
