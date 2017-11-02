<?php
require_once('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 

$username = $_SESSION['username'];
$query = "SELECT Wins,Defeats 
          FROM Players
          WHERE UserName = '$username'";

$db_server = db_connect();
$result = $db_server->query($query);
if ($result) {
    $query_result_json_array = sql_result_to_json_array($result);
    $query_result_array = json_decode($query_result_json_array);
    if (count($query_result_array) == 1) {
      echo json_encode($query_result_array[0]);
    }
    else {
      // failure is silent on the client
      // TODO: log the failure on the client
      throw new Exception(
        "Unexpected query result: " . $query_result_json_array
        . "\t(query count: " . count($query_result_array) . ")";
      );
    }
    $result->closeCursor();
}
// silent fail
// TODO: log the failure on the client
//       maybe also on server?

// close connection
$db_server = null;

?>
