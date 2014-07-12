#!/usr/bin/php
<?php
require_once('config.php');
require_once('time.php');
// requires DBH to be throwing exceptions on error

if (!isset($GLOBALS['playerColours'])) {
    $GLOBALS['playerColours']
        = ["red","blue","green","white","yellow","cyan","black"];
}

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

if (isset($_REQUEST['function'])) {
    $dbh      = db_connect();
    $function = $_REQUEST['function'];

    switch($function) {
        case('loadmaps'):
            $sth = $dbh->prepare(
               "SELECT MapID AS mapid,
                       MapName AS mapname,
                       MaxPlayers AS maxplayers
                FROM Maps"
            );
            $sth->execute();
            echo sqlresult_to_json($sth);
            break;
        
        case ('create'):
            $username = $_SESSION['username'];
            $gamename = $_GET['gamename'];
            $map      = $_GET['map'];
            
            $sth = $dbh->prepare(
               "SELECT MaxPlayers AS maxplayers
                FROM Maps
                WHERE MapID = ?"
            );
            $result = $sth->execute([$map]);

            $playerslimit = $sth->fetch();
            $playerslimit = strval($playerslimit[0]);
            $curTime      = isoNow();

            $dbh->beginTransaction(); // -----------------------------
            $sth = $dbh->prepare(
                "INSERT INTO Games(GameName,MapID,PlayersLimit,
                                   HostName,LastUpdated, InProgress)
                 VALUES(?,?,?,?,?,?)"
            );
            $sth->execute([$gamename, $map,     $playerslimit,
                                     $username, $curTime, false]);

            $sth = $dbh->prepare(
               "SELECT GameID AS gameid
                FROM   Games
                WHERE  GameName = ? AND HostName = ?"
            );
            $sth->execute([$gamename, $username]);

            $row    = $sth->fetch();
            $gameID = strval($row[0]);
            $sth = $dbh->prepare(
               "INSERT INTO PlayersGames(UserName,GameID,Colour,Ready)
                VALUES(?,?,'red', true)"
            );
            $sth->execute([$username, $gameID]);
            $dbh->commit(); // -----------------------------
			
            $sth = $dbh->prepare(
              "SELECT GameID AS gameid,           MapID AS mapid,
                      MapName AS mapname,         MaxPlayers AS maxplayers,
                      Width AS width,             Height AS height,
                      GameName AS gamename,       PlayersLimit AS playerslimit,
		                  TurnTimeout AS turntimeout, HostName AS hostname,
                      LastUpdated AS lastupdated, UserName AS username,
                      Colour AS colour,           Team AS team,
                      Ready AS ready
              FROM Maps NATURAL JOIN Games NATURAL JOIN PlayersGames
              WHERE GameID = ?
              ORDER BY SeqNo ASC"
            );
            $result = $sth->execute([$gameID]);
            echo sqlresult_to_json($sth);
            break;

        case ('join'):
            $gameid   = strval($_POST['gameid']);
            $username = $_SESSION['username'];
			
            $sth = $dbh->prepare(
               "SELECT NoPlayers, PlayersLimit
                FROM Games
                WHERE GameID = ?"
            );
            $sth->execute([$gameid]);

            $row = $sth->fetch();
            if ($row[0] >= $row[1]){
              // too many players
              echo "failure";
              break;
            }
            $seqno  = strval($row[0] + 1);
            $colour = findFreePlayerColour($dbh, $gameid);

            $dbh->beginTransaction(); //---------------------

            $sth = $dbh->prepare(
               "INSERT INTO PlayersGames(UserName,GameID,SeqNo,Colour)
                VALUES(?,?,?,?)"
            );
            $sth->execute([$username, $gameid, $seqno, $colour]);

            $curTime = isoNow();
            $sth = $dbh->prepare(
               "UPDATE Games
                SET NoPlayers = NoPlayers + 1, LastUpdated = ?
                WHERE GameID = ?"
            );
            $sth->execute([$curTime, $gameid]);

            $result = $dbh->commit(); //---------------------

            echo "success";
            break;

        case ('initialRetrieve'):
            $gameID = strval($_GET['gameid']);
            $sth = $dbh->prepare(
               "SELECT GameID AS gameid,        MapID AS mapid,
                    MapName AS mapname,         MaxPlayers AS maxplayers,
                    Width AS width,             Height AS height,
                    GameName AS gamename,       PlayersLimit AS playerslimit,
                    TurnTimeout AS turntimeout, HostName AS hostname,
                    LastUpdated AS lastupdated, UserName AS username,
                    Colour AS colour,           Team AS team,
                    Ready AS ready,             InProgress AS inprogress
                FROM Maps NATURAL JOIN Games NATURAL JOIN PlayersGames
			    WHERE GameID = ?
                ORDER BY SeqNo ASC"
            );
            $sth->execute([$gameID]);
            echo sqlresult_to_json($sth);
            break;

	      case ('retrieve'):
            $gameID      = strval($_GET['gameid']);
            $lastUpdated = $_GET['lastUpdated'];
			
            $sth = $dbh->prepare(
               "SELECT GameID FROM Games
                WHERE GameID = ?"
            );
            $result = $sth->execute([$gameID]);
            if (!$sth->fetch()) {
              error_log("\r\ngameSetup: retrieve error: no game with ID $gameID exists",
                        3, "debug.log");
              echo json_encode(false);
              break;
            }

            $sth = $dbh->prepare(
               "SELECT GameID AS gameid,        MapID AS mapid,
                    MapName AS mapname,         MaxPlayers AS maxplayers,
                    Width AS width,             Height AS height,
                    GameName AS gamename,       PlayersLimit AS playerslimit,
                    TurnTimeout AS turntimeout, HostName AS hostname,
                    LastUpdated AS lastupdated, UserName AS username,
                    Colour AS colour,           Team AS team,
                    Ready AS ready,             InProgress AS inprogress
                FROM Maps NATURAL JOIN Games NATURAL JOIN PlayersGames
                WHERE GameID = ? AND LastUpdated > ?
                ORDER BY SeqNo ASC"
			);
            $result = $sth->execute([$gameID, $lastUpdated]);
            $rows   = $sth->fetchAll(PDO::FETCH_ASSOC);
            //true means client is up-to-date
            if (!$rows){ echo json_encode(true);  }
            else       { echo json_encode($rows); }
            break;

        case ('abandon'):
            $gameID   = strval($_POST['gameid']);
            $username = $_SESSION['username'];

            $sth = $dbh->prepare(
               "SELECT SeqNo FROM PlayersGames
                WHERE Username = ? AND GameID = ?"
            );
            $sth->execute([$username, $gameID]);
            $seqNo = $sth->fetch()[0];

            $dbh->beginTransaction(); // ------------------
            $sth = $dbh->prepare(
               "DELETE FROM PlayersGames
			    WHERE UserName = ? AND GameID = ?"
            );
            $sth->execute([$username, $gameID]);

            $sth = $dbh->prepare(
               "UPDATE PlayersGames
                SET    SeqNo = SeqNo -1
                WHERE  GameID = ?
                  AND  SeqNo > ?"
            );
            $sth->execute([$gameID, $seqNo]);

            $curTime = isoNow();
            $sth = $dbh->prepare(
               "UPDATE Games
                SET NoPlayers = NoPlayers - 1, LastUpdated = ?
                WHERE GameID = ?"
            );
            $sth->execute([$curTime, $gameID]);
            $dbh->commit(); // -------------------

            echo "success";
            break;

        case('delete'):
            $gameID = strval($_POST['gameid']);

            $dbh->beginTransaction(); // ------------------
            $sth = $dbh->prepare(
               "DELETE FROM Games
                WHERE GameID = ?"
            );
            $sth->execute([$gameID]);
            $sth = $dbh->prepare(
               "DELETE FROM PlayersGames
                WHERE GameID = ?"
            );
            $sth->execute([$gameID]);
            $dbh->commit(); // -------------------

            echo "success";
            break;
          
         case('removePlayer'):
            $gameID   = strval($_POST['gameid']);
            $username = $_POST['username'];
            $hostname = $_SESSION['username'];
            
            $sth = $dbh->prepare(
               "SELECT HostName FROM Games WHERE HostName = ?"
            );
            $sth->execute([$hostname]);
            if (!$sth->fetch()) {
                echo "Only the host can kick out players";
                break;
            }
            
            $sth = $dbh->prepare(
               "SELECT SeqNo
                FROM PlayersGames
                WHERE UserName = ? AND GameID = ?"
            );
            $sth->execute([$username, $gameID]);

            $row = $sth->fetch();
            if (!$row) {
              echo "The player has already been kicked out.";
              break;
            }
            $seqno = strval($row[0]);

            $dbh->beginTransaction(); // -------------
            $sth = $dbh->prepare(
               "DELETE FROM PlayersGames
			    WHERE UserName = ? AND GameID = ?"
            );
            $sth->execute([$username, $gameID]);

            $curTime = isoNow();
            $sth = $dbh->prepare(
               "UPDATE Games
                SET NoPlayers = NoPlayers - 1, LastUpdated = ?
			    WHERE GameID = ?"
            );
            $sth->execute([$curTime, $gameID]);

	       $sth = $dbh->prepare(
               "UPDATE PlayersGames
                SET    SeqNo  = SeqNo -1
	            WHERE  GameID = ? AND SeqNo > ?"
            );
            $sth->execute([$gameID, $seqno]);
            $result = $dbh->commit(); // --------------

            echo "success";
            break;

        case('updateGame'):
            $mapID        = strval($_POST['mapID']);
            $playersLimit = $_POST['playersLimit'];
            $turnTimeout  = $_POST['turnTimeout'];
            $gameID       = strval($_POST['gameid']);
           
            $sth = $dbh->prepare(
               "SELECT NoPlayers FROM Games
                WHERE GameID = ?"
            );
            $sth->execute([$gameID]);

            $row = $sth->fetch();
            if ($row[0] > $playersLimit){
              echo "failure"; //too many players to reduce limit
              break;
            }
                      
            $curTime = isoNow();
            $sth = $dbh->prepare(
               "UPDATE Games 
                SET MapID        = ?,
                    PlayersLimit = ?,
                    TurnTimeout  = ?,
                    LastUpdated  = ?
                WHERE GameID = ?"
            );
            $sth->execute(
                [$mapID, $playersLimit, $turnTimeout, $curTime, $gameID]
            );
            echo "success";
            break;   
       
        case('updatePlayer'):
            $gameID   = strval($_POST['gameid']);
	        $username = $_SESSION['username'];
	        $colour   = $_POST['colour'];
	        $team     = $_POST['team'];

	        $sth = $dbh->prepare(
               "SELECT UserName AS username, GameID AS gameid,
                       Colour AS colour,     SeqNo AS seqno,
                       Team AS team,         Ready AS ready,
                       Alive AS alive
                FROM  PlayersGames
			    WHERE GameID = ? AND Colour = ?"
            );
			$sth->execute([$gameID, $colour]);

            $dbh->beginTransaction(); // ----------------
            if ( $sth->fetch() ){
                $sth = $dbh->prepare(
                   "UPDATE PlayersGames
                    SET    Team = ?
                    WHERE  GameID = ? AND UserName = ?"
                );
                $sth->execute([$team, $gameID, $username]);
            }
            else {
                $sth = $dbh->prepare(
                   "UPDATE PlayersGames
                    SET    Colour = ?, Team = ?
                    WHERE  GameID = ? AND UserName = ?"
                );
                $sth->execute([$colour, $team, $gameID, $username]);
            } 

            $curTime = isoNow();
            $sth = $dbh->prepare(
               "UPDATE Games
                SET    LastUpdated = ?
                WHERE  GameID = ?"
            );
            $sth->execute([$curTime, $gameID]);
            $dbh->commit(); // -------------

            echo "success";
            break;

        case('ready'):
            $gameID   = strval($_POST['gameid']);
			$username = $_SESSION['username'];
            $curTime  = isoNow();

            $dbh->beginTransaction(); // -------------
            $sth = $dbh->prepare(
               "UPDATE Games
                SET LastUpdated = ?
			    WHERE GameID = ?"
            );
            $sth->execute([$curTime, $gameID]);
            $sth = $dbh->prepare(
               "UPDATE PlayersGames
                SET Ready = true
                WHERE GameID = ? AND UserName = ?"
            );
            $sth->execute([$gameID, $username]);

            $dbh->commit(); // -------------
            echo "success";
            break;
    }
    $dbh = null; //close connection
}

function findFreePlayerColour($dbh, $gameid){
    $sth = $dbh->prepare(
        "SELECT Colour
         FROM PlayersGames
         WHERE GameID = ?"
    );
    $sth->execute([$gameid]);

    $coloursAlreadyTaken = $sth->fetchAll(PDO::FETCH_NUM);
    foreach ($GLOBALS['playerColours'] as $colour) {
        $colourIsFree = true;
        foreach($coloursAlreadyTaken as $takenColour) {
            if ($takenColour[0] === $colour) {
                $colourIsFree = false;
            }
        }
        if ($colourIsFree) {
            return $colour;
        }
    }
    return false;
}

?>
