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
        case('fetch'):
            $gameID = $_GET['gameid'];
            $sth = $db_server->prepare(
               "SELECT Time     AS time,
                       UserName AS username,
                       MessageText  AS message
                FROM Messages
                WHERE GameID = ?
                ORDER BY Time, MessageID ASC
                LIMIT 60"
            );
            $sth->execute([$gameID]);
            echo sql_result_to_json_array($sth);
            break;
        
        case('update'):
            $gameID    = $_GET['gameid'];
            $timestamp = $_GET['timestamp'];

            $query =
               "SELECT Time AS time,
                       UserName AS username,
                       MessageText AS message
                FROM Messages
                WHERE GameID = ?";
            $result; $sth;
            if ($timestamp) {
                $query .= " AND Time > ?
                            ORDER BY Time, MessageID ASC";
                $sth    = $db_server->prepare($query);
                $sth->execute([$gameID, $timestamp]);
            }
            else {
                $query .= " ORDER BY Time, MessageID ASC";
                $sth    = $db_server->prepare($query);
                $sth->execute([$gameID]);
            }
            echo sql_result_to_json_array($sth);
            break;

        case('send'):
            $gameID   = $_POST['gameid'];
            $username = $_SESSION['username'];
            $message  = filter_var($_POST['message'],
                                   FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $sth = $db_server->prepare(
               "INSERT INTO Messages(GameID, UserName, MessageText)
                VALUES(?, ?, ?)"
            );
            $sth->execute([$gameID, $username, $message]);
            echo true;
            break;
            
    }
    $db_server = null; //close connection
}

?>
