#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username']) 
    && isset($_POST['password']) 
    && isset($_POST['email'])) {
    
    $db_server = db_connect();
	
    $username = filter_string($db_server, $_POST['username']);
    $email = filter_string($db_server, $_POST['email']);
    $password = filter_string($db_server, $_POST['password']);
	
    $encrypted_pass = encrypt_password($password);
    $query = "INSERT INTO Players(UserName,Pass,Email)
              VALUES('$username','$encrypted_pass','$email')";
		
    if (!$result = $db_server->query($query)) {
        echo "false";
    } else {
        $_SESSION['username'] = $username;
        echo "true";
        $result->free();
    }

    $db_server->close();
}

?>
