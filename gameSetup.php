#!/usr/bin/php
<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

if (isset($_REQUEST['function'])) {
    $db_server = db_connect();
    $function  = filter_string($db_server, $_REQUEST['function']);

    switch($function) {
    	
        case('loadmaps'):
            $query =
            "SELECT MapID AS mapid,
                    MapName AS mapname,
                    MaxPlayers AS maxplayers
             FROM Maps";
    	              
            $result = $db_server->query($query); 
            if ($result) {
                echo sqlresult_to_json($result);
                $result->free();
            } 
    	   
            break;
        
        case ('create'):
            $username = filter_string($db_server, $_SESSION['username']);
            $gamename = filter_string($db_server, $_GET['gamename']);
            $map = filter_string($db_server, $_GET['map']);
            
            $query = "SELECT MaxPlayers AS maxplayers
                      FROM Maps
                      WHERE MapID = '$map'";

            $result = $db_server->query($query);
            $playerslimit = $result->fetch_row(); 
            $playerslimit = $playerslimit[0];
            $result->free();

            $query = "INSERT INTO Games(GameName,MapID,PlayersLimit,HostName,LastUpdated, InProgress)
                      VALUES('$gamename','$map','$playerslimit','$username','time()', false)";

            $result = $db_server->query($query);
            if (!$result) {
                echo json_encode(false); break;
            } 
            //else
            $query = "SELECT GameID AS gameid
                      FROM Games
                      WHERE GameName = '" . $gamename . 
                      "' AND HostName = '" . $username . "'";
            $result = $db_server->query($query);
            if (!$result) {
                echo json_encode(false); break;
            }
            
            $row = $result->fetch_row(); $result->free();
            $gameID = $row[0];
           
            $query = "INSERT INTO PlayersGames(UserName,GameID,Colour,Ready)
                      VALUES('$username','$gameID','red', true)";  

            if (!$result = $db_server->query($query)) {
                echo json_encode(false); break;
            }
			
            $query = 
              "SELECT GameID AS gameid,           MapID AS mapid,
                      MapName AS mapname,         MaxPlayers AS maxplayers,
                      Width AS width,             Height AS height,
                      GameName AS gamename,       PlayersLimit AS playerslimit,
		                  TurnTimeout AS turntimeout, HostName AS hostname,
                      LastUpdated AS lastupdated, UserName AS username,
                      Colour AS colour,           Team AS team,
                      Ready AS ready
                      FROM Maps NATURAL JOIN Games NATURAL JOIN PlayersGames
			                WHERE GameID = '$gameID' ORDER BY SeqNo ASC";
					 
  			    $result = $db_server->query($query);
      	    if ($result) {
              echo sqlresult_to_json($result);
              $result->free();
            }            
            break;
            
        case('delete'):
            $gameID = filter_string($db_server, $_POST['gameid']);;

            $query = "DELETE FROM Games 
                      WHERE GameID='$gameID'";

            if (!$result = $db_server->query($query)) {
              echo "failure";
            } else {
              echo "success";
            }
            break;

        case ('join'):
            
            $gameid = filter_string($db_server, $_POST['gameid']);
            $username = filter_string($db_server, $_SESSION['username']);
			
            $query = "SELECT NoPlayers, PlayersLimit
                      FROM Games
                      WHERE GameID = '$gameid'";
            $result = $db_server->query($query);
            if (!$result){
              echo "failure";
              break;
            }

            $row = $result->fetch_row(); $result->free();
            if ($row[0] >= $row[1]){
              echo "failure";
              break;
            }
            $seqno = $row[0] + 1;

            //$db_server->begin_transaction(); requires PHP 5.5
            $db_server->autocommit(FALSE);

            $query = "INSERT INTO PlayersGames(UserName,GameID,SeqNo)
                      VALUES('$username','$gameid','$seqno')";
            $db_server->query($query);

            $query = "UPDATE Games SET NoPlayers = NoPlayers + 1 
                      WHERE GameID = '$gameid'";
            $db_server->query($query);

            $result = $db_server->commit(); $db_server->autocommit(TRUE);
					        
            if (!$result) {
                echo "failure";
            } else {
                echo "success";
            } 
            break;

        case ('initialRetrieve'):
            $gameID = filter_string($db_server, $_GET['gameid']);
			
            $query = "SELECT GameID AS gameid,    MapID AS mapid,
                      MapName AS mapname,         MaxPlayers AS maxplayers,
                      Width AS width,             Height AS height,
                      GameName AS gamename,       PlayersLimit AS playerslimit,
                      TurnTimeout AS turntimeout, HostName AS hostname,
                      LastUpdated AS lastupdated, UserName AS username,
                      Colour AS colour,           Team AS team,
                      Ready AS ready,             InProgress AS inprogress
                      FROM Maps NATURAL JOIN Games NATURAL JOIN PlayersGames
			                WHERE GameID = '$gameID' ORDER BY SeqNo ASC";
					 
            $result = $db_server->query($query);
            if ($result) {
                echo sqlresult_to_json($result);
                $result->free();
            } 
    	   
            break;

	      case ('retrieve'):
            $gameID = filter_string($db_server, $_GET['gameid']);
            $lastUpdated = filter_string($db_server, $_GET['lastUpdated']);
			
            $query = "SELECT GameID FROM Games
                      WHERE GameID = '$gameID'";
            $result = $db_server->query($query);
            if(!$result){
              $gameRetrieveError = $db_server->error;
              error_log("\r\ngameSetup: retrieve error: $gameRetrieveError",
                        3, "debug.log");
              echo json_encode(false);
              break;
            }
            else if($result->num_rows < 1){
              error_log("\r\ngameSetup: retrieve error: no such game exists",
                        3, "debug.log");
              echo json_encode(false);
              $result->free();
              break;
            }

            $query = 
              "SELECT GameID AS gameid,           MapID AS mapid, 
                      MapName AS mapname,         MaxPlayers AS maxplayers,
                      Width AS width,             Height AS height,
                      GameName AS gamename,       PlayersLimit AS playerslimit,
                      TurnTimeout AS turntimeout, HostName AS hostname, 
                      LastUpdated AS lastupdated, UserName AS username,
                      Colour AS colour,           Team AS team,
                      Ready AS ready,             InProgress AS inprogress
                      FROM Maps NATURAL JOIN Games NATURAL JOIN PlayersGames
                      WHERE GameID = '$gameID' AND LastUpdated > '$lastUpdated'
                      ORDER BY SeqNo ASC";
					 
            $result = $db_server->query($query);
            if(!$result){
              $gameRetrieveError = $db_server->error;
              error_log("\r\ngameSetup: retrieve error: $gameRetrieveError",
                        3, "debug.log");
              echo json_encode(true); //true means client is up-to-date (or SQL error)
              break;
            }
            else if($result->num_rows < 1){
              echo json_encode(true); //true means client is up-to-date (or SQL error)
              $result->free();
              break;
            }
            else {
              echo sqlresult_to_json($result);
              $result->free();
            }            
   
            break;

        case ('abandon'):
            $gameID = filter_string($db_server, $_POST['gameid']);
            $username = filter_string($db_server, $_SESSION['username']);
			
            //$db_server->begin_transaction(); requires PHP 5.5
            $db_server->autocommit(FALSE);

            $query = "UPDATE Games SET NoPlayers = NoPlayers - 1, LastUpdated ='NOW()'
			                WHERE GameID = '$gameID'";
            $db_server->query($query);

			      $query = "UPDATE PlayersGames SET SeqNo = SeqNo -1
			                WHERE GameID = '$gameID' 
			                    AND SeqNo > (SELECT SeqNo FROM PlayersGames
			                              WHERE Username = '$username' AND GameID ='$gameID')";
            $db_server->query($query);

            $query = "DELETE FROM PlayersGames
			                WHERE UserName = '$username' and GameID = '$gameID'";
            $db_server->query($query);
					        
            $result = $db_server->commit(); $db_server->autocommit(TRUE);
            if (!$result) {
                $gameAbandonError = $db_server->error;
                error_log("Error: $gameAbandonError",3,'debug.log');
                echo "failure";
            } else {
                echo "success";
            } 
            break;
          
         case('removePlayer'):
            $gameID = filter_string($db_server, $_POST['gameid']);
            $username = filter_string($db_server, $_POST['username']);
            $hostname = filter_string($db_server, $_SESSION['username']);
            
            $query = "SELECT HostName FROM Games WHERE HostName = '$hostname'";
            $result = $db_server->query($query);
            if (!$result) {
              echo "Only the host can kick out players";
              break;
            }
            else if ($result->num_rows < 1) {
              echo "Only the host can kick out players";
              $result->free();
              break;
            }
            
            $query = "SELECT SeqNo
                      FROM PlayersGames
                      WHERE UserName = '$username' and GameID = '$gameID'";
            $result = $db_server->query($query);
            if (!$result) {
              echo "The player has already been kicked out.";
              break;
            }
            else if ($result->num_rows < 1){
              echo "The player has already been kicked out.";
              $result->free();
              break;
            }
            $row = $result->fetch_row(); $result->free();
            $seqno = $row[0];

            //$db_server->begin_transaction(); requires PHP 5.5
            $db_server->autocommit(FALSE);

            $query = "DELETE FROM PlayersGames
			                WHERE UserName = '$username' and GameID = '$gameID'";
            $db_server->query($query);

            $query = "UPDATE Games SET NoPlayers = NoPlayers - 1, LastUpdated ='NOW()'
			                WHERE GameID = '$gameID'";
            $db_server->query($query);

	          $query = "UPDATE PlayersGames SET SeqNo = SeqNo -1
	                    WHERE SeqNo > '$seqno'";
            $db_server->query($query);
                      
            $result = $db_server->commit(); $db_server->autocommit(TRUE);
            if (!$result = $db_server->query($query)) {
              echo "The player has already been kicked out.";
            } 
            else {
              echo "success";
            }
            break;

        case('updateGame'):
            $mapID = filter_string($db_server, $_POST['mapID']);
            $playersLimit = filter_string($db_server, $_POST['playersLimit']);
            $turnTimeout = filter_string($db_server, $_POST['turnTimeout']);
            $gameID = filter_string($db_server, $_POST['gameid']);;
           
            $query = "SELECT NoPlayers FROM Games
                      WHERE GameID = '$gameID'";
            $result = $db_server->query($query);
            if (!$result){
              echo "failure";
              break;
            }
            $row = $result->fetch_row(); $result->free();
            if ($row[0] > $playersLimit){
              echo "failure";
              break;
            }
                      
            $query = "UPDATE Games 
                      SET MapID = '$mapID', PlayersLimit = '$playersLimit',
                          TurnTimeout = '$turnTimeout', LastUpdated = 'NOW()'
                      WHERE GameID = '$gameID'";

            if (!$result = $db_server->query($query)) {
                echo "failure";
            } else {
                echo "success";
                $result->free();
            } 
            break;   
       
        case('updatePlayer'):
            $gameID = filter_string($db_server, $_POST['gameid']);
			      $username = filter_string($db_server, $_SESSION['username']);
			      $colour = filter_string($db_server, $_POST['colour']);
			      $team   = filter_string($db_server, $_POST['team']);
			      
			      $query = "SELECT * FROM PlayersGames 
			                WHERE GameID = '$gameID' AND Colour = '$colour'";
			      $result = $db_server->query($query);

            //$db_server->begin_transaction(); requires PHP 5.5
            $db_server->autocommit(FALSE);

            $query = "UPDATE Games SET LastUpdated ='NOW()'
                      WHERE GameID = '$gameID'";
            $db_server->query($query);

            if(!$result){
              $query = "UPDATE PlayersGames 
                        SET Colour = '$colour', Team = '$team'
                        WHERE GameID = '$gameID' AND UserName = '$username'";
              $db_server->query($query);
            }
            else {
              $result->free();

              if($result->num_rows < 1){
                $query = "UPDATE PlayersGames 
                          SET Colour = '$colour', Team = '$team'
                          WHERE GameID = '$gameID' AND UserName = '$username'";
              }              
              else {
                $query = "UPDATE PlayersGames 
                          SET Team = '$team'
                          WHERE GameID = '$gameID' AND UserName = '$username'";  
              }
              $db_server->query($query);
            }

            $result = $db_server->commit(); $db_server->autocommit(TRUE);                      
            if (!$result) {
                 echo "failure";
            } else {
                 echo "success";
            } 
            break;

        case('ready'):
            $gameID = filter_string($db_server, $_POST['gameid']);
			      $username = filter_string($db_server, $_SESSION['username']);

            //$db_server->begin_transaction(); requires PHP 5.5
            $db_server->autocommit(FALSE);

            $query = "UPDATE Games SET LastUpdated ='NOW()'
			                WHERE GameID = '$gameID'";
            $db_server->query($query);

            $query = "UPDATE PlayersGames 
                      SET Ready = true
                      WHERE GameID = '$gameID' AND UserName = '$username'";
            $db_server->query($query);

            $result = $db_server->commit(); $db_server->autocommit(TRUE);
            if (!$result) {
              echo "failure";
            } 
            else {
              echo "success";
            } 
            break;
    }
    $db_server->close();
}

?>
