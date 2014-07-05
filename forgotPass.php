`#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_POST['username']) && isset($_POST['email'])) {
    $db_server = db_connect();

    $username = filter_string($db_server, $_POST['username']);
    $useremail = filter_string($db_server, $_POST['email']);

    $headers = "From: Imperial Battles <g1227107@imperial.ac.uk>\r\n";
    $subject = "Imperial Battles: Password Reset";
	$email = "";
	$code = time() + 4586324;
    $msg = "Your activation code is: " . $code . "\r\n\r\nIf you did not request your password to be reset then simply ignore this message.";
	
    

    $query = "SELECT * FROM Players WHERE UserName = '$username' and Email = '$useremail'";
    $result = mysqli_query($db_server, $query);
    if (!$result) {
        echo "Invalid combination of username and email address.";
    }
    else if ($result->num_rows != 1){
        echo "Invalid combination of username and email address.";
        $result->free();
    } 
    else {
        $row = $result->fetch_row();
        $email = $row[2];
        $result->free();
			
	    $query = "UPDATE Players SET Code = '$code' WHERE UserName = '$username'";
        $result = $db_server->query($query);
		if (!$result)
		    echo "Failed to generate activation code.";
        else {
            $result->free();
            if (mail($email, $subject, $msg, $headers)) {       
                echo "The activation code has been sent to your email address.";
            } else {
                echo "The activation code could not be sent. Please try again later.";
            }
        }        
    }        
	
	$db_server->close();
	
} else if (isset($_POST['code']) && isset($_POST['password'])) {
    $db_server = db_connect();

    $code = filter_string($db_server, $_POST['code']);
    $password = filter_string($db_server, $_POST['password']);    

    $query = "SELECT * FROM Players WHERE Code = '$code'";
    $result = $db_server->query($query);
    if (!$result) {
        echo "Invalid activation code.";
    }
    else if ($result->num_rows != 1){
        echo "Invalid activation code.";
        $result->free();
    } 
    else {
        $result->free();
        $encrypted_pass = encrypt_password($password);
        $query = 
            "UPDATE Players 
            SET Pass = '$encrypted_pass', Code = NULL 
            WHERE Code = '$code'";
        $result = $db_server->query($query);
		if ($result){
            echo "Password has been reset successfully.";
            $result->free();
        }		    
	    else
		    echo "Failed to reset password.";
    }
	
	$db_server->close();
}
?>
