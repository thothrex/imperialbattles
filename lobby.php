<?php require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
} 
else {
    echo file_get_contents("lobby.html");
}

?>