#!/usr/bin/php
<?php
require_once('config.php');

function areAdjacent($x1,$y1,$x2,$y2){
    $dx = abs($x1-$x2);
    $dy = abs($y1-$y2);
    return (($dx<=1) && ($dy<=1));
}

function checkPlayerDefeated($db_server,$gameID,$unitID){
    $query = "SELECT SeqNo FROM Units
              WHERE UnitID = '$unitID'";
    $result = $db_server->query($query);
    if (!$result){
        return;
    }
    $row = $result->fetch_row(); $result->free();
    $seqNo = $row[0];
    $query = "SELECT COUNT(UnitID) FROM UNITS
              WHERE SeqNo = '$seqNo' AND GameID = '$gameID'";
    $result = $db_server->query($query);
    if (!$result){
        return;
    }
    $row = $result->fetch_row(); $result->free();
    if ($row[0] <= 1){
      $query = "UPDATE PlayersGames 
                SET Alive = false
                WHERE SeqNo = '$seqNo' AND GameID = '$gameID'";
      $result = $db_server->query($query); $result->free();
    }   
}

if (!isset($_SESSION['username'])) {
    die("\"failure\"");
}

if (isset($_REQUEST['function'])) {
    $db_server = db_connect();
    $function = filter_string($db_server, $_REQUEST['function']);

    switch($function) {
    	
        case('start'):
            $gameid = filter_string($db_server, $_POST['gameid']);

            $query = "SELECT NoPlayers,MapID
                      FROM Games
                      WHERE GameID = '$gameid'";
            $result = $db_server->query($query); 
            $row = $result->fetch_row(); $result->free();
            $noplayers = $row[0];
            $mapid = $row[1];

            $query = "BEGIN;
                      UPDATE Games
                      SET InProgress = true, Day = '1', Turn = '1', LastUpdated = 'NOW()'
                      WHERE GameID = '$gameid';
                      INSERT INTO Units(GameID,SeqNo,UnitType,Xloc,Yloc,State,Health)
                          SELECT '$gameid',SeqNo,UnitType,Xloc,Yloc,State,Health
                          FROM InitialUnits
                          WHERE MapID = '$mapid' and SeqNo <= '$noplayers';
                      COMMIT;";

            if ($result = $db_server->query($query)) {
                echo json_encode("success");
                $result->free();
            } else {
                echo json_encode("failure");
            }

            break;

        case('resume'):
            $gameid = filter_string($db_server, $_REQUEST['gameid']);
            $username = $_SESSION['username'];

            $query = "SELECT MapName,Width,Height,GameID,GameName,TurnTimeout,
                      SeqNo,Turn, EXTRACT(EPOCH FROM (NOW() - LastUpdated)), Day
                      FROM Maps NATURAL JOIN Games NATURAL JOIN PlayersGames
                      WHERE GameID = '$gameid' and UserName = '$username'";
            $result = $db_server->query($query); 
            if (!$result) {
                echo json_encode("failure");
            } else {
                $row = $result->fetch_row(); $result->free();

                $map = array('mapname' => "map/" . $row[0] . ".json", 'width' => intVal($row[1]), 
                                'height' => intVal($row[2]));
                $game = array('gameid' => $row[3], 'gamename' => $row[4],
                 'turntimeout' => intVal($row[5]), 'localplayer' => intVal($row[6]),
                 'currentplayer' => intVal($row[7]), 'currenttimeleft' => intVal($row[5])-intVal($row[8]),
                  'day' => intVal($row[9]));

                $query = "SELECT UserName,Colour,Team,SeqNo,Alive
                          FROM PlayersGames
                          WHERE GameID = '$gameid'
                          ORDER BY SeqNo ASC";
                $result = $db_server->query($query); 
                if (!$result) {
                    echo json_encode("failure");
                    return;
                } else {
                    $players = array();
                    while($r = $result->fetch_assoc()) {
                        $players[] = $r;
                    }
                    $result->free();

                    $query = "SELECT SeqNo,UnitType,Xloc,Yloc,State,Health
                              FROM Units
                              WHERE GameID = '$gameid'";

                    $result = $db_server->query($query);
                    if (!$result) {
                        echo json_encode("failure");
                        return;
                    } else {
                        $i = 0;
                        while ($row = $result->fetch_row()) {
                          $units[$i] = array(
                            'unitType' => intVal($row[1]), 
                            'owner' => intVal($row[0]), 
                            'location' => array(intVal($row[2]),intVal($row[3])), 
                            'state' => $row[4],
                            'health' => intVal($row[5])
                          );
                          $i++;
                        }
                        $result->free();

                        $query = "DELETE FROM Updates
                                  WHERE GameID = '$gameid' 
                                  AND Username = '$username'";
                        $result = $db_server->query($query); $result->free();
                    }
                }

                $arr = array(
                  'map' => $map, 
                  'game' => $game, 
                  'players' => $players, 
                  'units' => $units
                );
                echo json_encode($arr);
             }
            
            break;

        case('update'):
            $gameID = filter_string($db_server, $_REQUEST['gameid']);
            $username = $_SESSION['username'];
            
            $query = "SELECT EXTRACT(EPOCH FROM (NOW() - LastUpdated)),TurnTimeout, Turn 
                      FROM Games WHERE GameID = '$gameID'";
            $result = $db_server->query($query); 
            if (!$result) {
              echo json_encode("failure");
              break;
            }
            $row  = $result->fetch_row(); $result->free();
            $diff = $row[0] - $row[1];
            if ($row[1]>0 && $diff>=0) {
                 endTurnOfPlayer($db_server,$row[2],$gameID,"System");
            } 
            $query = "SELECT Action FROM Updates
                      WHERE GameID = '$gameID' AND UserName = '$username'
                      ORDER BY Time ASC";
            
            $result = $db_server->query($query); 
            if (!$result) {
              echo json_encode("failure");
              break;
            }
            else{
              if ($result->num_rows < 1){
                echo json_encode(array());
              } 
              else {
                echo sqlresult_to_json($result);
              }
              $result->free();
            }
            
            
            $query = "DELETE FROM Updates
                      WHERE GameID = '$gameID' AND Username = '$username'";
            $result = $db_server->query($query); 
            $result->free();
            
            break;

        case('move'):
            $username = $_SESSION['username'];
            $gameID = filter_string($db_server, $_REQUEST['gameid']);
            $path = json_decode(filter_string($db_server, $_REQUEST['path']));
            $target = json_decode(filter_string($db_server, $_REQUEST['target']));
            
            $initial = $path[0];

            //check if its a valid unit, current player's unit and not tired
            $query = "SELECT UnitID, MapID
                      FROM Games NATURAL JOIN PlayersGames NATURAL JOIN Maps NATURAL JOIN Units
                      WHERE GameID = '$gameID'  AND SeqNo = Turn AND UserName = '$username' 
                            AND Xloc='$initial[0]' AND Yloc='$initial[1]' AND State <> 'tired'";

            $$result = $db_server->query($query); 
            if (!$result) {
              echo json_encode("failure");
              break;
            }
            else if($result->num_rows < 1){
              echo json_encode("failure");
              $result->free();
              break;
            }
            $row = $result->fetch_row(); $result->free();
            $unitID = $row[0];
            $mapID = $row[1];
            
            //fetch MoveAllowance, unit type
            $query = "SELECT MoveAllowance, UnitType FROM Units NATURAL JOIN UnitType
                      WHERE UnitID = '$unitID'";
            $result = $db_server->query($query); 
            if (!$result){
              echo json_encode("failure");
              break;
            }
            else if($result->num_rows < 1){
              echo json_encode("failure");
              $result->free();
              break;
            }
            $row = $result->fetch_row(); $result->free();
            $steps = $row[0];
            $unitType = $row[1];
            $size = count($path);
            if ($size-1 > $row[0]){
                echo json_encode("failure");
                break;
            }
            
            $validPath = true;
            // validate path
            for ($i = 1; $i<$size; $i++){
                if (!areAdjacent($path[$i-1][0],$path[$i-1][1],$path[$i][0],$path[$i][1])){
                    $validPath = false;
                    break;
                }
                $curr = $path[$i];
                //check if valid terrain for unit and fetch terain modifier
                $query = "SELECT Modifier 
                          FROM Terrain NATURAL JOIN Movement
                          WHERE UnitType = '$unitType' AND MapID = '$mapID' 
                            AND Xloc = '$curr[0]' AND Yloc = '$curr[1]'";
                $result = $db_server->query($query); 
                $rows = $result->num_rows;
                
                if ($rows < 1) {
                    $validPath = false;
                    $result->free();
                    break;
                } else {
                    $row = $result->fetch_row(); $result->free();
                    $steps = $steps - $row[0];
                    if ($steps<0){
                      $validPath = false;
                      break;
                    }
                }
                //check if cell not occupied by any other unit
                $query = "SELECT Count(UnitID) FROM Units 
                          WHERE gameID = '$gameID' AND Xloc = '$curr[0]' 
                            AND Yloc = '$curr[1]' AND UnitID <> '$unitID'";
                $result = $db_server->query($query); 
                $row = $result->fetch_row(); $result->free();
                if ($row[0]>0 ) {
                    $validPath = false;
                    break;
                } 
            }
            if (!$validPath){
                echo json_encode("failure");
                break;
            }
                
            //update unit's entry
            $final = $path[$size-1];
            $query = "UPDATE Units
                      SET Xloc = '$final[0]', Yloc = '$final[1]', State = 'tired'
                      WHERE UnitID = '$unitID'";
            if(!$result = $db_server->query($query)){
              echo json_encode("failure");
            }
            else { $result->free(); }         
            
            //add move updates for other players
            $action = json_encode(array(
              'type' => 'move', 
              'path' => $path, 
              'target' => $target
            ));
            $query = "INSERT INTO Updates(GameID, Username, Action)
                      SELECT GameID, Username, '$action' AS Action
                      FROM PlayersGames 
                      WHERE GameID = '$gameID' AND Username <>'$username'";
            $result = $db_server->query($query); $result->free();        

            //if the unit attacks
            if ($target != null) {
                $query = "SELECT PAMinDist, PAMaxDist, Health, Defence
                          FROM Units NATURAL JOIN UnitType NATURAL JOIN Terrain 
                              NATURAL JOIN TerrainType
                          WHERE UnitID = '$unitID' AND MapID = '$mapID'";
                $result = $db_server->query($query); 
                if (!$result){
                  echo json_encode("failure");
                  break;
                }
                else if ($result->num_rows < 1){
                  echo json_encode("failure");
                  $result->free();
                  break;
                }
                $row = $result->fetch_row(); $result->free();
                $attackMin = $row[0];
                $attackMax = $row[1];
                $attackerHealth = $row[2];
                $attackerDefence = $row[3];
                
                $dist = abs($final[0]-$target[0])+abs($final[1]-$target[1]);
                if ($dist>$attackMax || $dist<=$attackMin){
                    echo json_encode("failure");
                    break;
                }
                
                $query = "SELECT UnitID, UnitType, Defence, Health, PAMinDist, PAMaxDist
                          FROM Units NATURAL JOIN UnitType NATURAL JOIN Games 
                              NATURAL JOIN Terrain NATURAL JOIN TerrainType
                          WHERE GameID = '$gameID' 
                            AND Xloc = '$target[0]' AND Yloc = '$target[1]' 
                            AND SeqNo <> Turn";
                $result = $db_server->query($query); 
                if (!$result){
                  echo json_encode("failure");
                  break;
                }
                else if ($result->num_rows < 1){
                  echo json_encode("failure");
                  $result->free();
                  break;
                }
                
                $row = $result->fetch_row(); $result->free();
                $targetID =  $row[0];
                $targetType =  $row[1];
                $defence =  $row[2];
                $health =  $row[3];
                $defenderMin = $row[4];
                $defenderMax = $row[5];
                $query = "SELECT Modifier
                          FROM Attack
                          WHERE Attacker = '$unitType' AND Defender = '$targetType'";
                $result = $db_server->query($query); 
                if (!$result){
                  echo json_encode("failure");
                  break;
                }
                else if ($result->num_rows < 1){
                  echo json_encode("failure");
                  $result->free();
                  break;
                }
                
                $row = $result->fetch_row(); $result->free();
                $damage =  $row[0];
                $damage = ceil (($attackerHealth/2)*$damage*$defence) + rand(0,1);
                $health = $health - $damage;
                if ($health <= 0) $health =0;
                $query = "UPDATE Units
                            SET Health = '$health'
                            WHERE UnitID = '$targetID'";
                  if (!$result = $db_server->query($query)) {
                      echo json_encode("failure");
                      break;
                  }
                  else { $result->free(); }
                
                //add health updates for all players
                $action = json_encode(array(
                  'type' => 'setHealth',
                  'target' => $target, 
                  'health' => $health
                ));
                $query = "INSERT INTO Updates(GameID, Username, Action)
                          SELECT GameID, Username, '$action' AS Action
                          FROM PlayersGames 
                          WHERE GameID = '$gameID'";
                $result = $db_server->query($query);
                if (!$result ) {
                    echo json_encode("failure");
                    break;
                }
                else { $result->free(); }

                if ($health <= 0){
                    checkPlayerDefeated($db_server,$gameID,$targetID);
                    $query = "DELETE FROM Units 
                              WHERE UnitID = '$targetID'";
                    if (!$result = $db_server->query($query)) {
                        echo json_encode("failure");
                        break;
                    }
                    else { $result->free(); }

                } else if($dist>$defenderMin && $dist<=$defenderMax){
                    $query = "SELECT Modifier
                              FROM Attack
                              WHERE Attacker = '$targetType' 
                              AND Defender = '$unitType'";
                    $result = $db_server->query($query); 
                    if (!$result) {
                        echo json_encode("failure");
                        break;
                    }
                    if ($result->num_rows > 0){
                        $row = $result->fetch_row(); $result->free();
                        $damage =  $row[0];
                        $damage = 
                          ceil (($health/2)*$damage*$attackerDefence) 
                        + rand(0,1);
                        $attackerHealth = $attackerHealth - $damage;
                        if ($attackerHealth <= 0){
                            $attackerHealth = 0;
                            checkPlayerDefeated($db_server,$gameID,$unitID);
                            $query = "DELETE FROM Units 
                                      WHERE UnitID = '$unitID'";
                            if (!$result = $db_server->query($query)) {
                                echo json_encode("failure");
                                break;
                            }
                            else { $result->free(); }
                        }
                        $query = "UPDATE Units
                                  SET Health = '$attackerHealth'
                                  WHERE UnitID = '$unitID'";
                        if (!$result = $db_server->query($query)) {
                            echo json_encode("failure");
                            break;
                        }
                        else { $result->free(); }
                        $action = json_encode(array('type' => 'setHealth',
                                    'target' => $final, 'health' => $attackerHealth));
                        $query = "INSERT INTO Updates(GameID, Username, Action)
                                  SELECT GameID, Username, '$action' AS Action
                                  FROM PlayersGames 
                                  WHERE GameID = '$gameID'";
                        if (!$result = $db_server->query($query)) {
                            echo json_encode("failure");
                            break;
                        }
                        else { $result->free(); }
                    }
                }
                
            }
            echo json_encode("success");
            break;

        case('endTurn'):
            $gameid = filter_string($db_server, $_POST['gameid']);
            $username = $_SESSION['username'];
            
            $query = "SELECT SeqNo
                      FROM PlayersGames NATURAL JOIN Games
                      WHERE GameID = '$gameid' and UserName = '$username'
                            and SeqNo = Turn";
            $result = $db_server->query($query); 
            if (!$result) {
              echo json_encode("failure");
              break;
            }
            else if($result->num_rows < 1){
              $result->free();
              echo json_encode("failure");
              break;
            }
            $row = $result->fetch_row(); $result->free();
            $seqno = $row[0];

            echo endTurnOfPlayer($db_server,$seqno,$gameid,$username);

            break;
             
        case('resign'):
            $gameid = filter_string($db_server, $_POST['gameid']);
            $username = $_SESSION['username'];
            $currentplayer = false;

            $query = "SELECT SeqNo 
                      FROM PlayersGames NATURAL JOIN Games
                      WHERE GameID = '$gameid' and UserName = '$username'
                                and SeqNo = Turn";
            $result = $db_server->query($query);
            if ($result) {
              if($result->num_rows > 0){
                $currentplayer = true;
              }
              $result->free();
            }

            $query = "DELETE FROM PlayersGames
                      WHERE GameID = '$gameid' and UserName = '$username'
                      RETURNING SeqNo";
            $result = $db_server->query($query);
            if (!$result) {
                echo "failure";
                break;
            }
            else { $result->free(); }
            $row = $result->fetch_row(); $result->free();
            $seqNo = $row[0];

            $action = json_encode(array(
              'type' => 'removePlayer', 
              'player' => intVal($seqNo)
            ));
            $msg = $username . " has resigned";

            $query = "BEGIN;
                      DELETE FROM Units
                      WHERE GameID = '$gameid' and SeqNo = '$seqNo';
                      UPDATE Players
                      Set Defeats = Defeats + 1
                      WHERE UserName = '$username';
                      INSERT INTO Updates(GameID,Username,Action)
                          SELECT GameID,Username,'$action' AS Action
                          FROM PlayersGames 
                          WHERE GameID = '$gameid';
                      INSERT INTO Messages(GameID,UserName,Message)
                      VALUES('$gameid','System','$msg');
                      COMMIT;";
                      
            $result = $db_server->query($query);
            if (!$result){
                echo "failure";
            } else {
                if ($currentplayer){
                  endTurnOfPlayer($db_server,$seqNo,$gameid,$username);
                }
                $result->free(); 
                echo "success";
            }
            break;

        case('gameover'):
            $gameid = filter_string($db_server, $_POST['gameid']);
            $username = $_SESSION['username'];
                        
            $query = "SELECT Team 
                      FROM PlayersGames
                      WHERE GameID = '$gameid' and UserName = '$username'";
            $result = $db_server->query($query);
            if (!$result) {
              echo json_encode("failure");
              break;
            }
            else if ($result->num_rows < 1){
              echo json_encode("failure");
              $result->free();
              break;
            }
            $row = $result->fetch_row(); $result->free();
            $team = $row[0];

            $query = "SELECT GameID 
                      FROM Games 
                      WHERE GameID = '$gameid' 
                        AND InProgress = true";
            $result = $db_server->query($query);
            if (!$result) {
              echo json_encode("failure");
              break;
            }
            else if ($result->num_rows < 1){
              echo json_encode("failure");
              $result->free();
              break;
            }

            $result->free();

            $query = "SELECT UnitID
                      FROM Units NATURAL JOIN PlayersGames
                      WHERE GameID = '$gameid' 
                        AND Team <> '$team' 
                        AND Health <> '0'";
            $result = $db_server->query($query);
            if (!$result) {
              echo json_encode("failure");
              break;
            }
            else if ($result->num_rows > 0){
              $result->free();
              echo json_encode("failure");
              break;
            }
            else {
              $result->free();
              $query = "SELECT SeqNo
                        FROM PlayersGames
                        WHERE GameID = '$gameid' and Team = '$team'";
              $result = $db_server->query($query);
              $i = 0;
              while ($row = $result->fetch_row()) {
                  $players[$i] = intVal($row[0]);
                  $i++;
              }
              $result->free();
              $action = json_encode(array(
                'type' => 'gameOver', 
                'players' => $players
              ));
              $query = "BEGIN;
                        UPDATE Games SET InProgress = false
                            WHERE GameID = '$gameid';
                        UPDATE Players
                        SET Wins = Wins + 1
                        WHERE UserName in (SELECT UserName FROM PlayersGames 
                              WHERE GameID = '$gameid' and Team = '$team');
                        UPDATE Players
                        SET Defeats = Defeats + 1
                        WHERE UserName not in (SELECT UserName FROM PlayersGames 
                              WHERE GameID = '$gameid' and Team = '$team');
                        INSERT INTO Updates(GameID,Username,Action)
                            SELECT GameID,Username,'$action' AS Action
                            FROM PlayersGames 
                            WHERE GameID = '$gameid';
                        COMMIT;";
              $result = $db_server->query($query);
              if (!$result) {
                echo json_encode("failure");
              } 
              else {
                $result->free();
                echo json_encode("success");
              }
            }

            break;
    }

    $db_server->close();
}




function endTurnOfPlayer($db_server,$seqno,$gameid,$username) {
    $query = "SELECT SeqNo
              FROM PlayersGames
              WHERE GameID = '$gameid' and Alive = true
              ORDER BY SeqNo ASC";
    $result = $db_server->query($query);
    if (!$result) {
        return json_encode("failure");
    }

    $noplayers = $result->num_rows;
    $rows = array();
    while($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    $result->free();
    $i = 0;

    for (; $i < $noplayers; $i++) {
        if ($rows[$i]['seqno'] > $seqno) {
            break;
        }
    }

    if ($i == $noplayers) {
        $i = 0;
    }
    $turn = $rows[$i]['seqno'];
    
    
    $query = "BEGIN;
              UPDATE Games
              SET Turn = '$turn', LastUpdated = 'NOW()'
              WHERE GameID = '$gameid';
              UPDATE Units
              SET State = 'normal'
              WHERE GameID = '$gameid' and SeqNo = '$turn';
              COMMIT;";
    $result = $db_server->query($query);
    if (!$result) {
        return json_encode("failure" . $turn);
    }
    else { $result->free(); }

    if ($i == 0) {
        $query = "UPDATE Games
                  SET Day = Day + 1
                  WHERE GameID = '$gameid'";
        $result = $db_server->query($query);
        if (!$result) {
            return json_encode("failure");
        }
        else { $result->free(); }
    }
        
    $action = json_encode(array(
      'type' => 'endTurn', 
      'next' => intVal($turn)
    ));
    $query = "INSERT INTO Updates(GameID, Username, Action)
                  SELECT GameID, Username, '$action' AS Action
                  FROM PlayersGames 
                  WHERE GameID = '$gameid' AND Username <>'$username'";
    if (!$result = $db_server->query($query)) {
      return json_encode("failure");
    } 
    else {
      $result->free();
      return json_encode("success");
    }
}

?>
