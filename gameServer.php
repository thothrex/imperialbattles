#!/usr/bin/php
<?php
require_once('config.php');
require_once('time.php');
// requires DBH to be throwing exceptions on error

function areAdjacent($x1,$y1,$x2,$y2){
    $dx = abs($x1-$x2);
    $dy = abs($y1-$y2);
    return (($dx<=1) && ($dy<=1));
}

function checkPlayerDefeated($dbh, $gameID, $unitID){
    $sth = $dbh->prepare(
       "SELECT SeqNo
        FROM   Units
        WHERE  UnitID = ?"
    );
    $sth->execute([$unitID]);

    $row = $sth->fetch();
    if (!$row) {
        //error no such unit
        return;
    }
    $seqNo = $row[0];
    $sth = $dbh->prepare(
       "SELECT COUNT(UnitID)
        FROM   Units
        WHERE  SeqNo = ? AND GameID = ?"
    );
    $sth->execute([$seqNo,$gameID]);

    $row = $sth->fetch();
    if ($row[0] <= 1){
        $sth = $dbh->prepare(
           "UPDATE PlayersGames
            SET    Alive = false
            WHERE  SeqNo = ? AND GameID = ?"
        );
        $sth->execute([$seqNo,$gameID]);
    }
}

function clearUpdates($dbh, $gameid, $username){
    $sth = $dbh->prepare(
       "DELETE FROM Updates
        WHERE GameID = ? AND Username = ?"
    );
    $sth->execute([$gameid,$username]);
}

if (!isset($_SESSION['username'])) {
    die("\"failure\"");
}

if (isset($_REQUEST['function'])) {
    $dbh      = db_connect();
    $function = $_REQUEST['function'];

    switch($function) {
    	
        case('start'):
            $gameid = $_POST['gameid'];

            $sth = $dbh->prepare(
               "SELECT NoPlayers, MapID
                FROM   Games
                WHERE  GameID = ?"
            );
            $sth->execute([$gameid]);
            $row = $sth->fetch();
            if(!$row) {
                echo json_encode("failure");
                throw new Exception("No game #$gameid exists");
                break;
            }
            $noplayers = $row[0];
            $mapid     = $row[1];
            $curTime   = isoNow();

            $dbh->beginTransaction(); // -----------------------------
            $sth = $dbh->prepare(
               "UPDATE Games
                SET    InProgress  = true, Day = '1',
                       LastUpdated = ?,    Turn = '1'
                WHERE  GameID = ?"
            );
            $sth->execute([$curTime, $gameid]);
            $sth = $dbh->prepare(
               "INSERT INTO Units
                       (GameID,SeqNo,UnitType,Xloc,Yloc,State,Health)
                SELECT       ?,SeqNo,UnitType,Xloc,Yloc,State,Health
                FROM  InitialUnits
                WHERE MapID = ? and SeqNo <= ?"
            );
            $sth->execute([$gameid, $mapid, $noplayers]);
            $dbh->commit(); // -----------------------------

            echo json_encode("success");
            break;

        case('resume'):
            $gameid   = $_REQUEST['gameid'];
            $username = $_SESSION['username'];

            $sth = $dbh->prepare(
               "SELECT MapName,     Width, Height, GameID, GameName,
                       TurnTimeout, SeqNo, Turn,   LastUpdated, Day
                FROM Maps NATURAL JOIN Games NATURAL JOIN PlayersGames
                WHERE GameID = ? and UserName = ?"
            );
            $sth->execute([$gameid,$username]);

            $row = $sth->fetch();
            if (!$row) {
                echo json_encode("failure");
                throw new Exception(
                    "Either no game #$gameid exists, "
                  . "or user $username does not exist, "
                  . "or user $username is not in game #$gameid."
                );
                break;
            }
            $timeSinceLastUpdate = timeSub(isoNow(), $row[8]);
            $turnTimeLeft        = intVal($row[5]) - $timeSinceLastUpdate;
            $map = array(
                'mapname'         => "map/" . $row[0] . ".json",
                'width'           => intVal($row[1]),
                'height'          => intVal($row[2])
            );
            $game = array(
                'gameid'          => $row[3],
                'gamename'        => $row[4],
                'turntimeout'     => intVal($row[5]),
                'localplayer'     => intVal($row[6]),
                'currentplayer'   => intVal($row[7]),
                'currenttimeleft' => $turnTimeLeft,
                'day'             => intVal($row[9])
            );

            $sth = $dbh->prepare(
               "SELECT   UserName,Colour,Team,SeqNo,Alive
                FROM     PlayersGames
                WHERE    GameID = ?
                ORDER BY SeqNo ASC"
            );
            $sth->execute([$gameid]);

            $row = $sth->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo json_encode('failure');
                throw new Exception("No players in game #$gameid");
                break;
            }
            $players = array();
            //convert database to javascript format
            for (; $row; $row = $sth->fetch(PDO::FETCH_ASSOC) ) {
                $newrow = array();
                foreach ($row as $key => $value) {
                    $newkey          = strtolower($key);
                    $newrow[$newkey] = $value;
                }
                $players[] = $newrow; //push $newrow onto $players
            }

            $sth = $dbh->prepare(
               "SELECT SeqNo, UnitType, Xloc, Yloc, State, Health
                FROM   Units
                WHERE  GameID = ?"
            );
            $sth->execute([$gameid]);

            $row = $sth->fetch();
            if (!$row) {
                echo json_encode('failure');
                throw new Exception("No units in game #$gameid");
                break;
            }

            for ($i = 0; $row; $row = $sth->fetch(), $i++) {
                $units[$i] = array(
                    'unitType' => intVal($row[1]),
                    'owner'    => intVal($row[0]),
                    'location' => array( intVal($row[2]),intVal($row[3]) ),
                    'state'    => $row[4],
                    'health'   => intVal($row[5])
                );
            }
            
            clearUpdates($dbh, $gameid, $username);

            $arr = array(
                'map'     => $map,
                'game'    => $game,
                'players' => $players,
                'units'   => $units
            );
            echo json_encode($arr);
            break;

        case('update'):
            $gameID   = $_REQUEST['gameid'];
            $username = $_SESSION['username'];
            
            $sth = $dbh->prepare(
               "SELECT LastUpdated, TurnTimeout, Turn
                FROM   Games
                WHERE  GameID = ?"
            );
            $sth->execute([$gameID]);

            $row = $sth->fetch();
            if (!$row) {
                echo json_encode("failure");
                throw new Exception("No game #$gameid exists");
                break;
            }

            $timeSinceLastUpdate = timeSub(isoNow(), $row[0]);
            $turnTimeLeft        = $row[1] - $timeSinceLastUpdate;
            if ($row[1] > 0 && $turnTimeLeft <= 0) {
                endTurnOfPlayer($dbh, $row[2], $gameID, "System");
            }
            $sth = $dbh->prepare(
               "SELECT   Action AS action
                FROM     Updates
                WHERE    GameID = ? AND UserName = ?
                ORDER BY Time ASC"
            );
            $sth->execute([$gameID,$username]);

            echo sqlresult_to_json($sth, PDO::FETCH_ASSOC);
            clearUpdates($dbh, $gameID, $username);
            break;

        case('move'):
            $username = $_SESSION['username'];
            $gameID   = $_REQUEST['gameid'];
            $path     = json_decode($_REQUEST['path']);
            $target   = null;
            if( isset($_REQUEST['target']) )
                $target = json_decode($_REQUEST['target']);
            
            $initial = $path[0];

            //check if its a valid unit, current player's unit and not tired
            $sth = $dbh->prepare(
               "SELECT UnitID, MapID
                FROM Games
                    NATURAL JOIN PlayersGames
                    NATURAL JOIN Maps
                    NATURAL JOIN Units
                WHERE GameID   = ? AND SeqNo = Turn
                  AND Xloc     = ? AND Yloc  = ?
                  AND UserName = ? AND State <> 'tired'"
            );
            $sth->execute([$gameID, $initial[0], $initial[1], $username]);

            $row = $sth->fetch();
            if (!$row){
                echo json_encode('failure');
                throw new Exception(
                    'No unit returned for input values: '
                  . "Game $gameID, Player $username, "
                  . "Location ({$initial[0]}, {$initial[1]})"
                );
                break;
            }
            $unitID = $row[0];
            $mapID  = $row[1];
            
            //fetch MoveAllowance, unit type
            $sth = $dbh->prepare(
               "SELECT MoveAllowance, UnitType
                FROM   Units NATURAL JOIN UnitType
                WHERE  UnitID = ?"
            );
            $sth->execute([$unitID]);

            $row = $sth->fetch();
            if(!$row){
              echo json_encode('failure');
              throw new Exception("No unit with id $unitID exists");
              break;
            }
            $unitType          = $row[1];
            $unitMoveAllowance = $row[0];
            $numSteps          = count($path) - 1;
            if ($numSteps > $unitMoveAllowance){
                echo json_encode("failure");
                //error
                break;
            }
            
            $stepsLeft = $unitMoveAllowance;
            $validPath = true;
            // validate path
            for ($i = 1; $i < count($path); $i++){
                if (!areAdjacent( $path[$i-1][0],
                                  $path[$i-1][1],
                                  $path[$i][0],
                                  $path[$i][1]   )){
                    $validPath = false;
                    break;
                }
                $curr = $path[$i];
                //check if valid terrain for unit and fetch terain modifier
                $sth = $dbh->prepare(
                   "SELECT Modifier
                    FROM   Terrain NATURAL JOIN Movement
                    WHERE  UnitType = ? AND MapID = ?
                      AND  Xloc     = ? AND Yloc  = ?"
                );
                $sth->execute([$unitType,$mapID,$curr[0],$curr[1]]);

                $row = $sth->fetch();
                if (!$row) {
                    $validPath = false;
                    //error
                    break;
                }
                $stepsLeft -= $row[0];
                if ($stepsLeft < 0){
                    $validPath = false;
                    //error
                    break;
                }
                //check if cell not occupied by any other unit
                $sth = $dbh->prepare(
                   "SELECT Count(UnitID)
                    FROM   Units
                    WHERE  gameID = ? AND UnitID <> ?
                      AND  Xloc   = ? AND Yloc    = ?"
                );
                $sth->execute([$gameID, $unitID, $curr[0], $curr[1]]);
                $row = $sth->fetch();
                if ($row[0] > 0) {
                    $validPath = false;
                    break;
                }
            }
            if (!$validPath) {
                echo json_encode("failure");
                throw new Exception(
                    'Given an invalid path: '
                  . $_REQUEST['path']
                );
                break;
            }

            //update unit's entry
            $final = end($path);
            //add move updates for other players
            $action = json_encode(array(
                'type'   => 'move',
                'path'   => $path,
                'target' => $target
            ));

            $dbh->beginTransaction(); // -----------------------------
            $sth = $dbh->prepare(
               "UPDATE Units
                SET    Xloc = ?, Yloc = ?, State = 'tired'
                WHERE  UnitID = ?"
            );
            $sth->execute([$final[0], $final[1], $unitID]);
            $sth = $dbh->prepare(
               "INSERT INTO Updates(GameID, Username, Action)
                SELECT GameID, Username, ? AS Action
                FROM   PlayersGames
                WHERE  GameID = ? AND Username <> ?"
            );
            $sth->execute([$action, $gameID, $username]);
            $dbh->commit(); // -----------------------------

            //if the unit attacks
            if ($target != null) {
                $sth = $dbh->prepare(
                   "SELECT PAMinDist, PAMaxDist, Health, Defence
                    FROM Units
                        NATURAL JOIN UnitType
                        NATURAL JOIN Terrain
                        NATURAL JOIN TerrainType
                    WHERE UnitID = ? AND MapID = ?"
                );
                $sth->execute([$unitID,$mapID]);
                $row = $sth->fetch();
                if (!$row){
                    echo json_encode("failure");
                    throw new Exception(
                        "Either no unit found with UnitID $unitID, "
                      . "or no map found with MapID $mapID"
                    );
                    break;
                }
                $attackMin       = $row[0];
                $attackMax       = $row[1];
                $attackerHealth  = $row[2];
                $attackerDefence = $row[3];
                
                $dist = abs($final[0] - $target[0])
                      + abs($final[1] - $target[1]);
                if ($dist > $attackMax || $dist <= $attackMin) {
                    echo json_encode("failure");
                    throw new Exception(
                        "Target ({$target[0]}, {$target[1]}) "
                      . "is at an invalid distance for unit #$unitID "
                      . " at ({$final[0]}, {$final[1]})"
                    );
                    break;
                }
                
                $sth = $dbh->prepare(
                   "SELECT UnitID, UnitType, Defence,
                           Health, PAMinDist, PAMaxDist
                    FROM Units NATURAL JOIN UnitType NATURAL JOIN Games
                               NATURAL JOIN Terrain  NATURAL JOIN TerrainType
                    WHERE GameID = ? AND SeqNo <> Turn
                      AND Xloc   = ? AND Yloc = ?"
                );
                $sth->execute([$gameID, $target[0], $target[1]]);

                $row = $sth->fetch();
                if (!$row){
                    echo json_encode("failure");
                    throw new Exception(
                        "No unit found at ({$target[0]}, {$target[1]}) "
                      . "in game #$gameID that is not owned by "
                      . 'the player whose turn it currently is'
                    );
                    break;
                }
                $targetID    = $row[0];
                $targetType  = $row[1];
                $defence     = $row[2];
                $health      = $row[3];
                $defenderMin = $row[4];
                $defenderMax = $row[5];

                $sth = $dbh->prepare(
                   "SELECT Modifier
                    FROM   Attack
                    WHERE  Attacker = ? AND Defender = ?"
                );
                $sth->execute([$unitType, $targetType]);

                $row = $sth->fetch();
                if (!$row){
                    echo json_encode("failure");
                    throw new Exception(
                        'No attack modifier entry found for '
                      . "$unitType vs $targetType"
                    );
                    break;
                }
                $modifier = $row[0];
                $damage   = ceil(($attackerHealth/2)*$modifier*$defence)
                          + rand(0,1);
                $health   = $health - $damage;
                if ($health <= 0) $health = 0;
                //add health updates for all players
                $action = json_encode(array(
                    'type'   => 'setHealth',
                    'target' => $target,
                    'health' => $health
                ));

                $dbh->beginTransaction(); // -----------------------------
                $sth = $dbh->prepare(
                   "UPDATE Units
                    SET    Health = ?
                    WHERE  UnitID = ?"
                );
                $sth->execute([$health,$targetID]);
                $sth = $dbh->prepare(
                   "INSERT INTO Updates(GameID, Username, Action)
                    SELECT GameID, Username, ? AS Action
                    FROM   PlayersGames
                    WHERE  GameID = ?"
                );
                $sth->execute([$action, $gameID]);

                if ($health <= 0){
                    checkPlayerDefeated($dbh,$gameID,$targetID);
                    $sth = $dbh->prepare
                        ("DELETE FROM Units WHERE UnitID = ?");
                    $sth->execute([$targetID]);
                }
                else if ($dist > $defenderMin && $dist <= $defenderMax){
                    $sth = $dbh->prepare(
                       "SELECT Modifier
                        FROM   Attack
                        WHERE  Attacker = ?
                        AND    Defender = ?"
                    );
                    $sth->execute([$targetType,$unitType]);

                    $row = $sth->fetch();
                    if ($row){
                        $modifier = $row[0];
                        $damage
                            = ceil(($health/2)*$modifier*$attackerDefence)
                            + rand(0,1);
                        $attackerHealth -= $damage;
                        if ($attackerHealth <= 0){
                            $attackerHealth = 0;
                            checkPlayerDefeated($dbh,$gameID,$unitID);
                            $sth = $dbh->prepare(
                               "DELETE FROM Units
                                WHERE UnitID = ?"
                            );
                            $sth->execute([$unitID]);
                        }
                        $sth = $dbh->prepare(
                           "UPDATE Units
                            SET    Health = ?
                            WHERE  UnitID = ?"
                        );
                        $sth->execute([$attackerHealth, $unitID]);

                        $action = json_encode(array(
                            'type'   => 'setHealth',
                            'target' => $final,
                            'health' => $attackerHealth
                        ));
                        $sth = $dbh->prepare(
                           "INSERT INTO Updates(GameID, Username, Action)
                            SELECT GameID, Username, ? AS Action
                            FROM   PlayersGames
                            WHERE  GameID = ?"
                        );
                        $sth->execute([$action, $gameID]);
                    }
                }
                $dbh->commit(); // -----------------------------
            }
            echo json_encode("success");
            break;

        case('endTurn'):
            $gameid   = $_POST['gameid'];
            $username = $_SESSION['username'];
            $sth = $dbh->prepare(
               "SELECT SeqNo
                FROM   PlayersGames NATURAL JOIN Games
                WHERE  GameID = ? AND UserName = ? AND SeqNo = Turn"
            );
            $sth->execute([$gameid,$username]);

            $row = $sth->fetch();
            if(!$row){
                echo json_encode("failure");
                throw new Exception(
                    "It is not $username's turn in game #$gameid"
                );
                break;
            }
            $seqno = $row[0];
            echo endTurnOfPlayer($dbh,$seqno,$gameid,$username);
            break;
             
        case('resign'):
            $gameid   = $_POST['gameid'];
            $username = $_SESSION['username'];

            $sth = $dbh->prepare(
               "SELECT SeqNo, Turn
                FROM   PlayersGames NATURAL JOIN Games
                WHERE  GameID = ? AND UserName = ?"
            );
            $sth->execute([$gameid,$username]);

            $row = $sth->fetch();
            if (!$row) {
                //error
                break;
            }
            $msg           = $username . " has resigned";
            $currentplayer = intVal($row[1]) === intVal($row[0]);
            $seqNo         = $row[0];
            $action        = json_encode(array(
              'type'   => 'removePlayer',
              'player' => intVal($seqNo)
            ));

            $dbh->beginTransaction(); // -----------------------------
            $sth = $dbh->prepare(
               "DELETE FROM PlayersGames
                WHERE GameID = ? AND UserName = ?"
            );
            $sth->execute([$gameid,$username]);
            $sth = $dbh->prepare(
               "DELETE FROM Units
                WHERE GameID = ? AND SeqNo = ?"
            );
            $sth->execute([$gameid, $seqNo]);
            $sth = $dbh->prepare(
               "UPDATE Players
                SET    Defeats = Defeats + 1
                WHERE  UserName = ?"
            );
            $sth->execute([$username]);
            $sth = $dbh->prepare(
               "INSERT INTO Updates(GameID,Username,Action)
                SELECT GameID, Username, ? AS Action
                FROM   PlayersGames
                WHERE  GameID = ?"
            );
            $sth->execute([$action, $gameid]);
            $sth = $dbh->prepare(
               "INSERT INTO Messages(GameID, UserName, Message)
                VALUES              (?,      'System', ?      )"
            );
            $sth->execute([$gameid, $msg]);
            $dbh->commit(); // -----------------------------
                      
            if ($currentplayer) {
                endTurnOfPlayer($dbh,$seqNo,$gameid,$username);
            }
            echo "success";
            break;

        case('gameover'):
            $gameid = $_POST['gameid'];
            $username = $_SESSION['username'];
                        
            $sth = $dbh->prepare(
               "SELECT Team
                FROM   PlayersGames
                WHERE  GameID = ? AND UserName = ?"
            );
            $sth->execute([$gameid,$username]);

            $row = $sth->fetch();
            if (!$row){
              echo json_encode("failure");
              //error
              break;
            }
            $team = $row[0];
            $sth = $dbh->prepare(
               "SELECT GameID
                FROM   Games
                WHERE  GameID = ? AND InProgress = true"
            );
            $sth->execute([$gameid]);

            $row = $sth->fetch();
            if (!$row){
                echo json_encode("failure");
                throw new Exception("No game #$gameid currently in progress");
                break;
            }
            $sth = $dbh->prepare(
               "SELECT UnitID
                FROM   Units NATURAL JOIN PlayersGames
                WHERE  GameID = ? AND Team <> ? AND Health <> 0"
            );
            $sth->execute([$gameid,$team]);

            $row = $sth->fetch();
            if (!$row){
                echo json_encode("failure");
                throw new Exception(
                    "No units in game #$gameid not on team #$team"
                );
                break;
            }
            $sth = $dbh->prepare(
               "SELECT SeqNo
                FROM   PlayersGames
                WHERE  GameID = ? and Team = ?"
            );
            $sth->execute([$gameid,$team]);

            for ($i = 0; $row = $sth->fetch(); $i++) {
                $players[$i] = intVal($row[0]);
            }
            $action = json_encode(array(
              'type'    => 'gameOver',
              'players' => $players
            ));

            $dbh->beginTransaction(); // -----------------------------
            $sth = $dbh->prepare(
               "UPDATE Games
                SET    InProgress = false
                WHERE  GameID = ?"
            );
            $sth->execute([$gameid]);
            $sth = $dbh->prepare(
               "UPDATE Players
                SET Wins = Wins + 1
                WHERE UserName IN
                   (SELECT UserName
                    FROM   PlayersGames
                    WHERE  GameID = ? AND Team = ?)"
            );
            $sth->execute([$gameid, $team]);
            $sth = $dbh->prepare(
               "UPDATE Players
                SET    Defeats = Defeats + 1
                WHERE  UserName NOT IN
                   (SELECT UserName
                    FROM   PlayersGames
                    WHERE  GameID = ? AND Team = ?)"
            );
            $sth->execute([$gameid, $team]);
            $sth = $dbh->prepare(
               "INSERT INTO Updates(GameID, Username, Action)
                SELECT GameID, Username, ? AS Action
                FROM   PlayersGames
                WHERE  GameID = ?"
            );
            $sth->execute([$action, $gameid]);
            $dbh->commit(); // -----------------------------

            echo json_encode("success");
            break;
    }

    $dbh = null; //close connection
}

// trusting $seqno
function endTurnOfPlayer($dbh, $seqno, $gameid, $username) {
    $seqno  = intVal(filter_var(trim($seqno),  FILTER_SANITIZE_NUMBER_INT));
    $gameid = intVal(filter_var(trim($gameid), FILTER_SANITIZE_NUMBER_INT));
    $sth = $dbh->prepare(
       "SELECT SeqNo
        FROM   PlayersGames
        WHERE  GameID = ? AND Alive = true
        ORDER BY SeqNo ASC"
    );
    $sth->execute([$gameid]);
    $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        throw new Exception("No alive players in game #$gameid");
        return json_encode("failure");
    }

    $noplayers = count($rows);
    $i = 0; // deliberate - propagating
    for (; $i < $noplayers; $i++) {
        if ($rows[$i]['SeqNo'] > $seqno) break;
    }
    if ($i === $noplayers) $i = 0; //cycle
    $curTime = isoNow();
    $turn    = intVal($rows[$i]['SeqNo']);
    if (!$turn){
        throw new Exception("Invalid sequence numbers");
        return json_encode("failure");
    }
    $action  = json_encode(array(
        'type' => 'endTurn',
        'next' => $turn
    ));

    $dbh->beginTransaction(); // -----------------------------
    $query =  'UPDATE Games
               SET    Turn = ?, LastUpdated = ? ';
    if ($i === 0) $query  .= ', Day = Day + 1 ';
    $query .= 'WHERE  GameID = ?';
    $sth = $dbh->prepare($query);
    $sth->execute([$turn, $curTime, $gameid]);
    $sth = $dbh->prepare(
       "UPDATE Units
        SET    State = 'normal'
        WHERE  GameID = ? AND SeqNo = ?"
    );
    $sth->execute([$gameid, $turn]);
    $sth = $dbh->prepare(
       "INSERT INTO Updates(GameID, Username, Action)
        SELECT GameID, Username, ? AS Action
        FROM   PlayersGames
        WHERE  GameID = ? AND Username <> ?"
    );
    $sth->execute([$action, $gameid, $username]);
    $dbh->commit(); // -----------------------------

    return json_encode("success");
}

?>
