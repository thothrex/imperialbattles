<?php
declare(strict_types=1);

require_once('config.php');

$private_ini = load_private_ini();
$admin_user = $private_ini['AdminUserName'];
if (!isset($_SESSION['username']) || $_SESSION['username'] != $admin_user) {
    die ("<h1>Unauthorised access</h1>");
}

if ($_POST) {
    if (isset($_POST['terraintype']))   { load_terrain_types();  }
    else if (isset($_POST['unittype'])) { load_unit_types();     }
    else if (isset($_POST['movement'])) { load_movement_table(); }
    else if (isset($_POST['attack']))   { load_attack_table();   }
    else if (isset($_POST['map']))      { load_map_tables();     }
    else if (isset($_POST['game']))     { load_main_chatroom();  }
    else if (isset($_POST['initdatabase'])) { initialise_database(); }
}
else {
    echo file_get_contents("admin.html");
}

function load_terrain_types (PDO &$db_server = NULL) {
    $file = "json/terrain_type.json";
    $query = generate_terrain_type_table_population_query($file);
    if (is_null ($db_server)) { execute_query($query); }
    else                      { execute_component_query($query, $db_server); }
}

function load_unit_types (PDO &$db_server = NULL) {
    $file = "json/unit_type.json";
    $query = generate_unit_type_table_population_query($file);
    if (is_null ($db_server)) { execute_query($query); }
    else                      { execute_component_query($query, $db_server); }
}

function load_movement_table (PDO &$db_server = NULL) {
    $file = "json/move_table.json";
    $query = generate_movement_table_population_query($file);
    if (is_null ($db_server)) { execute_query($query); }
    else                      { execute_component_query($query, $db_server); }
}

function load_attack_table (PDO &$db_server = NULL) {
    $file = "json/attack_table.json";
    $query = generate_attack_table_population_query($file);
    if (is_null ($db_server)) { execute_query($query); }
    else                      { execute_component_query($query, $db_server); }
}

function load_map_tables (PDO &$db_server = NULL) {
    // Load the maps (Map, Terrain and InitialUnits tables).

    $map1 = array("name" => "King of the Hill", "maxplayers" => 4);
    $map2 = array("name" => "Close Quarters", "maxplayers" => 4);
    $map3 = array("name" => "Three Rivers", "maxplayers" => 5);
    $maps = array($map1,$map2,$map3);

    $db_server_provided = !is_null ($db_server);
    if (is_null ($db_server)) { $db_server = db_connect(); }

    // prepare re-used statements
    $mapname = "UNINITIALISED";
    $maxplayers = -1;
    $width = -1;
    $height = -1;
    $map_table_entry_insert_statement
        = generate_map_table_entry_insert_statement(
            $db_server, $mapname, $maxplayers, $width, $height
        );
    $mapID_retrieval_statement
        = generate_mapID_retrieval_statement($db_server, $mapname);

    foreach ($maps as $map) {
        // Set values of bound variables in prepared statements
        $filename   = "map/" . $map['name'] . ".json";
        $json       = json_decode(file_get_contents($filename), true);
        $mapname    = $map['name'];
        $maxplayers = $map['maxplayers'];
        $width      = $json['width'];
        $height     = $json['height'];
        // - Begin transaction -
        if (!$db_server_provided) { $db_server->beginTransaction(); }
        try {
            // Add basic map information to Maps table
            execute_statement($map_table_entry_insert_statement);
            $map_table_entry_insert_statement->closeCursor();

            // Get database auto-generated MapID
            execute_statement($mapID_retrieval_statement);
            $row = $mapID_retrieval_statement->fetch();
            $mapid = $row[0];
            $mapID_retrieval_statement->closeCursor();

            // Add terrain and initial units information to the database
            // these queries depend on earlier data so must be done within the transaction
            $terrain_query = generate_map_terrain_insert_query($filename, $mapid);
            execute_component_query($terrain_query, $db_server);

            $initial_units_query = generate_initial_units_insert_query($map, $mapid);
            execute_component_query($initial_units_query, $db_server);

            if (!$db_server_provided) {
                $db_server->commit(); // be wary of https://bugs.php.net/bug.php?id=66528
            }
        }
        catch (Exception $e) {
            if (!$db_server_provided) {
                $db_server->rollBack();
                echo "<p>Transaction rolled back</p>";
                if (!($e instanceof PDOException)) {
                    throw $e;
                }
                // continue with other maps as normal
            }
        }
    }
    if (!$db_server_provided) {
        $db_server = null; // close connection
        echo "<br /><br /><button type='button' onclick=window.location='admin.php'>Back</button>";
    }
}

function load_main_chatroom (PDO &$db_server = NULL) {
    // Create the initial game needed for the chat module.
    // Throws an exception if the game already exists (caught by the try/catch block in execute_query())
    $query = "INSERT INTO Games(GameName,MapID,InProgress) VALUES('idle',NULL,false)";
    if (is_null ($db_server)) { execute_query($query); }
    else                      { execute_component_query($query, $db_server); }
}

// 'Players' table has to be loaded already
// for this page to be accessible
// (one cannot log on without it)
function initialise_database () {
    // the order of SQL loading matters
    // due to foreign-key constraint dependencies
    $table_list_load_ordered
        = array("TerrainType", "UnitType", "Maps", // 0 dependencies
                "Terrain", "InitialUnits", "Games", "Messages", "PlayersGames", // first-order dependencies
                "Units", "Updates" // second-order dependencies
        );
    $table_data_import_functions_ordered
        = array("load_terrain_types", "load_unit_types",
                "load_map_tables", "load_main_chatroom");
    $db_server = db_connect();
    // Create table can't be rolled back on MySQL
    // (It can for other vendors)
    // but this doesn't hurt to have
    $db_server->beginTransaction();
    try {
        for ($i = 0; $i < count($table_list_load_ordered); $i++) {
            $filename = "sql/" . $table_list_load_ordered[$i] . ".sql";
            $query = file_get_contents($filename);
            execute_component_query($query, $db_server);
        }
        for ($i = 0; $i < count($table_data_import_functions_ordered); $i++) {
            // call the function listed in the array,
            // passing the db_server as a parameter
            ($table_data_import_functions_ordered[$i])($db_server);
        }
        $db_server->commit(); // be wary of https://bugs.php.net/bug.php?id=66528
    }
    catch (Throwable $e) { // error or exception
        $db_server->rollBack();
        echo "<p>Transaction rolled back</p>";
        if (!($e instanceof PDOException)) {
            throw $e;
        }
    }
    finally {
        echo "<br /><br /><button type='button' onclick=window.location='admin.php'>Back</button>";
    }
}



// The name isn't great, sorry.
// Generates the query needed to populate (i.e. fill in the starting contents) of the terrain type table
// using the data read from the provided json file located at $file_location.
function generate_terrain_type_table_population_query (string $file_location): string {
    $query = "INSERT INTO TerrainType(TerrainType,Defence) VALUES";
    $json = json_decode(file_get_contents($file_location), true);
    $i = 0;
    foreach ($json as $elem) {
        $query .= "('" . $i . "','" . $elem['defence'] . "'),";
        $i++;
    }
    $query = rtrim($query, ",");
    return $query;
}

// Generates the query needed to populate (i.e. fill in the starting contents) of the unit type table
// using the data read from the provided json file located at $file_location.
function generate_unit_type_table_population_query (string $file_location): string {
    $query = "INSERT INTO UnitType(UnitType,MoveAllowance,PrimaryAttackMinDist,PrimaryAttackMaxDist) VALUES";
    $json = json_decode(file_get_contents($file_location), true);
    $i = 0;
    foreach ($json as $elem) {
        $query .= "('" . $i . "','" . $elem['moveAllowance'] . "','" . $elem['PrimaryAttackMinDist'] . "','" .
                    $elem['PrimaryAttackMaxDist'] . "'),";
        $i++;
    }
    $query = rtrim($query, ",");
    return $query;
}

// Generates the query needed to populate (i.e. fill in the starting contents) of the movement table
// using the data read from the provided json file located at $file_location.
function generate_movement_table_population_query (string $file_location): string {
    $query = "INSERT INTO Movement(UnitType,TerrainType,Modifier) VALUES";
    $json = json_decode(file_get_contents($file_location), true);
    foreach ($json as $elem) {
        $query .= "('" . $elem['unitType'] . "','" . $elem['terrainType'] . "','" 
                    . $elem['modifier'] . "'),";
    }
    $query = rtrim($query, ",");
    return $query;
}

// Generates the query needed to populate (i.e. fill in the starting contents) of the attack table
// using the data read from the provided json file located at $file_location.
function generate_attack_table_population_query (string $file_location): string {
    $query = "INSERT INTO Attack(Attacker,Defender,Modifier) VALUES";
    $json = json_decode(file_get_contents($file_location), true);
    foreach ($json as $elem) {
        $query .= "('" . $elem['attacker'] . "','" . $elem['defender'] . "','" 
                    . $elem['modifier'] . "'),";
    }
    $query = rtrim($query, ",");
    return $query;
}

function generate_map_table_entry_insert_statement
(PDO &$db_server, string &$mapname, int &$maxplayers, int &$width, int&$height)
: PDOStatement {
    $statement_string
        = "INSERT INTO Maps(MapName,MaxPlayers,Width,Height) VALUES"
        . "(:mapname,:maxplayers,:width,:height);";
    $stmt = $db_server->prepare($statement_string);
    $stmt->bindParam(':mapname', $mapname);
    $stmt->bindParam(':maxplayers', $maxplayers);
    $stmt->bindParam(':width', $width);
    $stmt->bindParam(':height', $height);
    return $stmt;
}

function generate_mapID_retrieval_statement
(PDO &$db_server, string &$mapname) : PDOStatement {
    $statement_string = 'SELECT MapID FROM Maps WHERE MapName = :mapname;';

    $stmt = $db_server->prepare($statement_string);
    $stmt->bindParam(':mapname', $mapname, PDO::PARAM_STR);
    
    return $stmt;
}

//   This is constructed as a string, rather than as a prepared statement because
// we can't know the number of rows to insert at once beforehand,
// (different maps have different sizes, i.e. width*height values)
// and executing a single statement which inserts multiple rows
// is more efficient than multiple executions of a single row insertion
// example explanation: https://www.red-gate.com/simple-talk/sql/performance/comparing-multiple-rows-insert-vs-single-row-insert-with-three-data-load-methods/
//   Preparing a statement is also less efficient than a text query
// if you're only using it once.
function generate_map_terrain_insert_query(string $json_filename, $mapid) : string {
    $query = "INSERT INTO Terrain(MapID,Xloc,Yloc,TerrainType) VALUES";
    $map_data = json_decode(file_get_contents($json_filename), true);
    $data = $map_data['layers'][1]['data'];
    
    for ($i = 0; $i < ($map_data['width'] * $map_data['height']); $i++) {
        $x = $i % $map_data['width'];
        $y = floor($i / $map_data['width']);
        $terraintype = $data[$i] - 1;
        $query .= "('" . $mapid . "','" . $x . "','" . $y . "','" . $terraintype . "'),";
    }

    $query = rtrim($query, ",");
    return $query;
}

//   This is constructed as a string, rather than as a prepared statement because
// we can't know the number of units to insert at once beforehand,
// (different maps have different numbers of initial units)
// and executing a single statement which inserts multiple rows
// is more efficient than multiple executions of a single row insertion
// example explanation: https://www.red-gate.com/simple-talk/sql/performance/comparing-multiple-rows-insert-vs-single-row-insert-with-three-data-load-methods/
//   Preparing a statement is also less efficient than a text query
// if you're only using it once.
function generate_initial_units_insert_query(array $map_data, $mapid) : string {
    $query = "INSERT INTO InitialUnits(MapID,SeqNum,UnitType,Xloc,Yloc,State,Health) VALUES";
    $file = "units/" . $map_data['name'] . ".json";
    $json = json_decode(file_get_contents($file), true);

    foreach ($json as $elem) {
        $query .= "('" . $mapid . "','" . $elem['owner'] . "','" . $elem['unitType'] 
                        . "','" . $elem['location'][0] . "','" 
                        . $elem['location'][1] . "','" . $elem['state'] . "','" 
                        . $elem['health'] . "'),";
    }

    $query = rtrim($query, ",");
    return $query;
}

function execute_query(string $query) {
    $db_server = db_connect();
    echo "<p>EXECUTING: " . $query . "</p>";
    try {
        $result = $db_server->query($query);
        echo "<p>SUCCESS</p>";
    }
    catch (PDOException $e) {
        echo "<p>FAILED: " . $e->getMessage() . "</p>";
    }
    finally {
        $db_server = null; // close the connection
        echo "<br /><br /><button type='button' onclick=window.location='admin.php'>Back</button>";
    }
}

// we expect the caller to clean up
function execute_statement(PDOStatement &$prepared_statement) {
    echo "<p>EXECUTING: ";
    $prepared_statement->debugDumpParams();
    echo "</p>";
    try {
        $prepared_statement->execute();
        echo "<p>SUCCESS</p>";
    }
    catch (PDOException $e) {
        echo "<p>FAILED: " . $e->getMessage() . "</p>";
    }
}

// THROWS: all generated exceptions, probably PDOExceptions
// Executes a query intended to be part of a series of queries
function execute_component_query(string $query, PDO &$db_server) {
    echo "<p>EXECUTING: " . $query . "</p>";
    try {
        $result = $db_server->query($query);
        echo "<p>SUCCESS</p>";
    }
    catch (PDOException $e) {
        echo "<p>FAILED: " . $e->getMessage() . "</p>";
        throw $e; // propagate to allow for db rollback
    }
}

?>
