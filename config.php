<?php
session_save_path("session");
ini_set('session.gc_maxlifetime',24 * 60 * 60);
session_start();
date_default_timezone_set('Europe/London');
set_error_handler("php_sql_error");
/* Creates a connection to the MySQL server. */
require_once('lib/password.php'); //backports password_hash from PHP 5.5

function db_connect() {
    $ini = parse_ini_file('privateInfo.ini'); //holds DB user/password etc.
    $db_server = new mysqli(
        $ini['dbDomain'],
        $ini['dbUsername'],
        $ini['dbPassword'],
        $ini['dbName']
    );
    if ($db_server->connect_error) {
        die('Connect Error (' . $db_server->connect_errno . ') '
            . $db_server->connect_error);
    }
    return $db_server;
}

/* Sanitizes a string that is to be inserted into the database. */
function filter_string($db_server, $string) {
    if (get_magic_quotes_gpc())
        $string = stripslashes($string);
    return htmlentities(strip_tags($db_server->real_escape_string($string)));
}

function php_sql_error($errno, $errstr, $error_file, $error_line) {
    $file = fopen("error.log","a");
    $error = isoNow() . " [$errno] '$errstr' in file: $error_file on line: $error_line\n";
    fwrite($file,$error);
    fclose($file);
}


function export($string) {
    $file = fopen("php_console.log","w");
    fwrite($file,$string);
    fclose($file);
}

function sqlresult_to_json($result) {
    $rows = array();
    while($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }

    return json_encode($rows);
}

function isoNow(){
    return date('Y-m-d\TH:i:s');
}

?>
