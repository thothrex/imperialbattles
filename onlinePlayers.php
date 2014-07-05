#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

if (isset($_REQUEST['function'])) {
    $db_server = db_connect();
    $function = filter_string($db_server, $_REQUEST['function']);    

    switch($function) {
        case('getList'):
            //error_log("\r\nLoading online players...", 3, "debug.log");
            $query = "SELECT UserName AS username
                      FROM Players
                      WHERE LoggedOn = true";

            $result = $db_server->query($query);
            if ($result) {
                echo sqlresult_to_json($result);
                $result->free();
            }
            else {
                //error_log("\tNo result!", 3, "debug.log");
            }
            break;

        case('remove'):
            $username = $_SESSION['username'];
            $query = "UPDATE Players
                      SET LoggedOn = false
                      WHERE UserName = '$username'";
            $result = $db_server->query($query);
            break;

        case('add'):
            $username = $_SESSION['username'];
            $query = "UPDATE Players
                      SET LoggedOn = true
                      WHERE UserName = '$username'";
            $result = $db_server->query($query);
            break;
    }
    $db_server->close();
}

?>
