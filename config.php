<?php
session_save_path("session");
ini_set('session.gc_maxlifetime',24 * 60 * 60);
session_start();
date_default_timezone_set('Europe/London');
set_error_handler("php_sql_error");
set_exception_handler("exceptionHandler");
/* Creates a connection to the MySQL server. */
require_once('lib/password.php'); //backports password_hash from PHP 5.5
require_once('time.php');

function db_connect() {
    $ini         = parse_ini_file('privateInfo.ini'); //holds DB user/password etc.
    $domain_name = $ini['dbDomain'];
    $db_name     = $ini['dbName'];
    $dsn         = "mysql:dbname=$db_name;host=$domain_name";
    $dbh;
    try {
        $dbh = new PDO($dsn, $ini['dbUsername'], $ini['dbPassword']);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch (PDOException $e) {
        die ('Connection failed: ' . $e->getMessage());
    }
    return $dbh;
}

function php_sql_error($errno, $errstr, $error_file, $error_line) {
    $file = fopen("error.log","a");
    $error = isoNow() . " [$errno] '$errstr' in file: $error_file on line: $error_line\n";
    fwrite($file,$error);
    fclose($file);
}

function exceptionHandler($e){
    $printedError = "\r\n\r\nException:";
    if (get_class($e) === 'PDOException') {
        $printedError
            .= "\r\nSQL Error: {$e->errorInfo[2]}";
    }
    $printedError
        .= "\r\n{$e->getFile()} at line {$e->getLine()}: {$e->getMessage()}";

    $cur = $e;
    while ($prev = $cur->getPrevious()){
        $printedError
            .= "\r\n\tCaused by exception in "
             . "{$e->getFile()} at line {$e->getLine()}: {$e->getMessage()}";
        $cur = $prev;
    }
    error_log($printedError, 3, "exceptions.log");
}

function export($string) {
    $file = fopen("php_console.log","w");
    fwrite($file,$string);
    fclose($file);
}

function sqlresult_to_json($result) {
    $rows = array();
    while($r = $result->fetch()) {
        $rows[] = $r;
    }
    return json_encode($rows);
}

?>
