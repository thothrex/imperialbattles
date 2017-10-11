#!/usr/bin/php
<?php
require_once('config.php');

$private_ini = load_private_ini();
$admin_user = $private_ini['AdminUserName'];
if (!isset($_SESSION['username']) || $_SESSION['username'] != $admin_user) 
    die ("<h1>Unauthorised access</h1>");

$config_ini = load_config_ini();
if ($_POST) {
    $db_server = db_connect();

    /* Load Terrain Types. */
    if (isset($_POST['terraintype'])) {

        $query = "INSERT INTO TerrainType(TerrainType,Defence) VALUES";
        $file = "json/terrain_type.json";
        $json = json_decode(file_get_contents($file), true);

        $i = 0;
        foreach ($json as $elem) {
            $query .= "('" . $i . "','" . $elem['defence'] . "'),";
            $i++;
        }
        $query = rtrim($query, ",");


    /* Load Unit Types. */
    } else if (isset($_POST['unittype'])) {

        $query = "INSERT INTO UnitType(UnitType,MoveAllowance,PAMinDist,PAMaxDist) VALUES";
        $file = "json/unit_type.json";
        $json = json_decode(file_get_contents($file), true);

        $i = 0;
        foreach ($json as $elem) {
            $query .= "('" . $i . "','" . $elem['moveAllowance'] . "','" . $elem['PAMinDist'] . "','" . 
                        $elem['PAMaxDist'] . "'),";
            $i++;
        }
        $query = rtrim($query, ",");


    /* Load Movement Table. */
    } else if (isset($_POST['movement'])) {

        $query = "INSERT INTO Movement(UnitType,TerrainType,Modifier) VALUES";
        $file = "json/move_table.json";
        $json = json_decode(file_get_contents($file), true);

        foreach ($json as $elem) {
            $query .= "('" . $elem['unitType'] . "','" . $elem['terrainType'] . "','" 
                        . $elem['modifier'] . "'),";
        }
        $query = rtrim($query, ",");


    /* Load Attack Table. */
    } else if (isset($_POST['attack'])) {

        $query = "INSERT INTO Attack(Attacker,Defender,Modifier) VALUES";
        $file = "json/attack_table.json";
        $json = json_decode(file_get_contents($file), true);

        foreach ($json as $elem) {
            $query .= "('" . $elem['attacker'] . "','" . $elem['defender'] . "','" 
                        . $elem['modifier'] . "'),";
        }
        $query = rtrim($query, ",");


    /* Load the maps (Map, Terrain and InitialUnits tables). */
    } else if (isset($_POST['map'])) { 

        $map1 = array("name" => "King of the Hill", "maxplayers" => 4);
        $map2 = array("name" => "Close Quarters", "maxplayers" => 4);
        $map3 = array("name" => "Three Rivers", "maxplayers" => 5);
        $maps = array($map1,$map2,$map3);

        foreach ($maps as $map) {
            $query = "INSERT INTO Maps(MapName,MaxPlayers,Width,Height) VALUES";
            $file = "map/" . $map['name'] . ".json";
            $json = json_decode(file_get_contents($file), true);
            $query .= "('" . $map['name']  . "','" . $map['maxplayers']  . "','" . $json['width'] . "','" 
                        . $json['height'] . "');";

            
            $result = $db_server->query($query);
            if (!$result){
                echo "<p>FAILED: " . $db_server->error . "</p>";
                return;
            } // else
            echo "<p>EXECUTED: " . $query . "</p>";
            
            $query = "SELECT MapID FROM Maps WHERE MapName = '" . $map['name'] . "';";
            $result = $db_server->query($query);
            if (!$result){
                echo "<p>FAILED: " . $db_server->error . "</p>";
                return;
            } // else 
            echo "<p>EXECUTED: " . $query . "</p>";
            
            $row = $result->fetch_row();
            $mapid = $row[0];

            //$db_server->begin_transaction(); requires PHP 5.5
            $db_server->autocommit(FALSE);
            $query = "INSERT INTO Terrain(MapID,Xloc,Yloc,TerrainType) VALUES";
            $data = $json['layers'][1]['data'];
            
            for ($i = 0; $i < ($json['width'] * $json['height']); $i++) {
                $x = $i % $json['width'];
                $y = floor($i / $json['width']);
                $terraintype = $data[$i] - 1;
                $query .= "('" . $mapid . "','" . $x . "','" . $y . "','" . $terraintype . "'),";
            }

            $query = rtrim($query, ",");
            $db_server->query($query);
            echo "<p>SENDING: " . $query . "</p>";

            $query = "INSERT INTO InitialUnits(MapID,SeqNo,UnitType,Xloc,Yloc,State,Health) VALUES";
            $file = "units/" . $map['name'] . ".json";
            $json = json_decode(file_get_contents($file), true);

            foreach ($json as $elem) {
                $query .= "('" . $mapid . "','" . $elem['owner'] . "','" . $elem['unitType'] 
                                . "','" . $elem['location'][0] . "','" 
                                . $elem['location'][1] . "','" . $elem['state'] . "','" 
                                . $elem['health'] . "'),";
            }

            $query = rtrim($query, ",");
            $db_server->query($query);
            

            echo "<p>SENDING: " . $query . "</p>";
            $result = $db_server->commit(); $db_server->autocommit(TRUE);
            if (!$result)
                echo "<p>FAILED: " . $db_server->error . "</p>";
            else {
                echo "<p>SUCCESS</p>";
            }
        }

        $db_server->close();

        echo "<br /><br /><button type='button' onclick=window.location='admin.php'>Back</button>";
        return;


    /* Create the initial game needed for the chat module. */
    // Throws an exception if the game already exists (caught by the try/catch block below)
    } else if (isset($_POST['game'])) {

        $query = "INSERT INTO Games(GameName,MapID,InProgress) VALUES('idle',1,false)";
    

    /* Create the player 'System'. */
    } else if (isset($_POST['system'])) {

        $query = "INSERT INTO Players(UserName,Pass,Email) 
                  VALUES('System','bmjl762Vgh57bZZxwlfejvw03543fdt',
                            'g1227109@imperial.ac.uk')";
    }


    /* Execute all queries. */
    echo "<p>EXECUTED: " . $query . "</p>";
    try {
        $result = $db_server->query($query);
        echo "<p>SUCCESS</p>";
    }
    catch (Exception $e) {
        echo "<p>FAILED: " . $e->getMessage() . "</p>";
    }
    finally {
        $db_server = null; // close the connection
        echo "<br /><br /><button type='button' onclick=window.location='admin.php'>Back</button>";
    }
} else {
    echo file_get_contents("admin.html");
}
?>

