<?php
session_save_path("session");
ini_set('session.gc_maxlifetime', 24 * 60 * 60);
session_start();
date_default_timezone_set('Europe/London');
set_error_handler("php_sql_error");
set_exception_handler("exceptionHandler");
/* Creates a connection to the MySQL server. */
require_once('lib/password.php'); //backports password_hash from PHP 5.5
require_once('time.php');

function load_private_ini() {
    return parse_ini_file('privateInfo.ini'); //holds DB user/password etc.
}

function load_config_ini() {
    return parse_ini_file('ServerConfig.ini'); // holds per-vendor info for database
}

function db_connect() : PDO {
    $priv_ini    = load_private_ini();
    $domain_name = $priv_ini['dbDomain'];
    $db_name     = $priv_ini['dbName'];
    $cfg_ini     = load_config_ini();
    $db_type     = $cfg_ini['dbType']; //mysql
    $dsn         = "$db_type:dbname=$db_name;host=$domain_name";
    $dbh;
    try {
        $dbh = new PDO($dsn, $priv_ini['dbUsername'], $priv_ini['dbPassword']);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch (PDOException $e) {
        die ('Connection failed: ' . $e->getMessage());
    }
    return $dbh;
}

function php_sql_error($errno, $errstr, $error_file, $error_line) {
    $timestamp       = preg_replace('/T/', ' ', isoNow());
    $justTheFileName = stripDirectories($error_file);
    $error = "$timestamp [$errno] $justTheFileName line $error_line: "
           . "$errstr\r\n";
    error_log($error, 3, 'error.log');
}

function exceptionHandler($e){
    $timestamp    = preg_replace('/T/', ' ', isoNow());
    $printedError = "\r\n\r\n$timestamp Exception:";
    if (get_class($e) === 'PDOException') {
        $printedError .= "\r\nSQL Error: {$e->errorInfo[2]}";
    }
    $fileName      = stripDirectories($e->getFile());
    $printedError .= "\r\n$fileName line {$e->getLine()}: {$e->getMessage()}";

    $cur = $e;
    while ($prev = $cur->getPrevious()){
        $fileName = stripDirectories($prev->getFile());
        $printedError
            .= "\r\n\tCaused by exception in "
             . "$fileName at line {$prev->getLine()}: "
             . "{$prev->getMessage()}";
        $cur = $prev;
    }
    error_log($printedError, 3, "exceptions.log");
}

function export($string) {
    $file = fopen("php_console.log","w");
    fwrite($file,$string);
    fclose($file);
}

function sqlresult_to_json($result, $fetchType = PDO::FETCH_BOTH) {
    $rows = array();
    while($r = $result->fetch($fetchType)) {
        $rows[] = $r;
    }
    return json_encode($rows);
}

function stripDirectories($fileString){
    return preg_replace('/(?:\w*\/)*([\w.]+)/', "$1", $fileString);
}

?>
