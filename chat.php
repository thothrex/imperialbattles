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
    	
        case('fetch'):
    	    $gameID = filter_string($db_server, $_GET['gameid']);
    	    $query = "SELECT * FROM 
    	              (SELECT Time AS time,
                            UserName AS username,
                            Message AS message FROM Messages 
    	              WHERE GameID = '$gameID'
    	              ORDER BY Time DESC
    	              LIMIT 60)
    	              ORDER BY Time ASC";
    	              
    	    $result = $db_server->query($query);
    	    if ($result) {
                echo sqlresult_to_json($result);
                $result->free();
            }
            break;
        
        case('update'):
    	    $gameID = filter_string($db_server, $_GET['gameid']);
            $timestamp = filter_string($db_server, $_GET['timestamp']);
            if ($timestamp)
                $query = "SELECT Time AS time,
                            UserName AS username,
                            Message AS message FROM Messages 
                          WHERE GameID = '$gameID' AND Time > '$timestamp'
                          ORDER BY Time ASC";
            else
                $query = "SELECT Time AS time,
                            UserName AS username,
                            Message AS message FROM Messages 
                          WHERE GameID = '$gameID'
                          ORDER BY Time ASC";
    	              
    	    $result = $db_server->query($query);
            if ($result) {
                echo sqlresult_to_json($result);
                $result->free();
            }
            break;
    	 
        case('send'):
            $gameID = filter_string($db_server, $_POST['gameid']);
            $username = $_SESSION['username'];
            $message = filter_string($db_server, $_POST['message']);
            $query = "INSERT INTO Messages(GameID,UserName,Message)
                      VALUES('$gameID','$username','$message')";

            $result = $db_server->query($query);
            if ($result) {
                echo $result;
                // non-selects return booleans,
                // not resultsets
                // so don't free them!
            }
            break;
            
    }
    $db_server->close();
}

?>
