<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

if (isset($_REQUEST['function'])) {
    $db_server = db_connect();
    $function  = $_REQUEST['function'];

    switch($function) {
        case('getList'):
            $sth = $db_server->prepare(
               "SELECT UserName AS username
                FROM Players
                WHERE LoggedOn = true"
            );
            $sth->execute();
            echo sql_result_to_json_array($sth);
            break;

        case('remove'):
            $username = $_SESSION['username'];
            $sth = $db_server->prepare(
               "UPDATE Players
                SET LoggedOn = false
                WHERE UserName = ?"
            );
            $sth->execute([$username]);
            break;

        case('add'):
            $username = $_SESSION['username'];
            $sth = $db_server->prepare(
               "UPDATE Players
                SET LoggedOn = true
                WHERE UserName = ?"
            );
            $sth->execute([$username]);
            break;
    }
    $db_server = null; //close connection
}

?>
