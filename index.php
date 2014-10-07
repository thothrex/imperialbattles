#!/usr/bin/php
<?php
require_once('config.php');

if (isset($_SESSION['username'])) {
    header("Location: lobby.php");
    exit;
} 
?>
<!DOCTYPE html>
<html>
<head>
    <title>IMPERIAL BATTLES - Login</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <?php include_once "common.html"; ?>
    <link rel="stylesheet" type="text/css" href="css/login.css" />
    <script src="js/jquery.cookie.js"></script>
    <script src="js/login.js"></script>
    <noscript>
        This page requires JavaScript. You can either switch to a browser that supports
        JavaScript or turn your browser's script support on.
    </noscript>
</head>
<body onload="initialise()">

<div id="page">
    <div id="images">
        <br />
        <img id="logo" src="img/logo.png" alt="logo" />
        <br />
        <br />
        <br />
        <br />
        <a href="#"><img src="img/story.png" alt="story" onclick="popUpStory()" /></a>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <a href="#"><img src="img/rules.png" alt="rules" onclick="popUpRules()" /></a>
    </div>

    <div id="story" class="popup">
        <h1 class="yellow">Story</h1>
        <?php include_once("story.html"); ?>
        <br />
        <button type="button" onclick="hideStory()">Close</button>
    </div>
    <div id="rules" class="popup">
        <h1 class="yellow">How to play</h1>
        <?php include_once("rules.html"); ?>
        <br />
        <button type="button" onclick="hideRules()">Close</button>
    </div>


    <div id="loginScreen">
        <div id="loginContainer">
            <form id="loginForm">
                <table cellspacing="4">
                    <tr>
                        <td>User name:</td>
                        <td><input type="text" name="username" onKeyDown="if (event.keyCode==13) login();" required/></td>
                    </tr>
                    <tr>
                        <td>Password:</td>
                        <td><input type="password" name="password" onKeyDown="if (event.keyCode==13) login();" required/></td>
                    </tr>
                    <tr>
                        <td colspan="2"><input type="checkbox" name="remember" />Remember me</td>
                    </tr>
                    <tr>
                        <td> </td>
                    </tr>
                    <tr>
                        <td colspan="2"><button type="button" onclick="login()">Login</button></td>
                    </tr>
                </table>
            </form>
            <div id="loginStatusLabel">&nbsp;</div>
            <br />
            <table cellspacing="5">
                <tr>
                    <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                    <td><button type="button" onclick="showRegisterForm()">Register</button></td>
                    <td></td><td></td>
                    <td><button type="button" onclick="showForgotPassForm()">Forgot password</button></td>
                </tr>
            </table>
        </div>

        <div id="registerContainer">
        <form id="registerForm">
            <table cellspacing="4">
                <tr>
                    <td>User name:</td>
                    <td><input type="text" name="username" onchange="checkUserName()" required/></td>
                    <td><span id="userNameStatus"></span></td>
                </tr>
                <tr>
                    <td>E-mail:</td>
                    <td><input type="text" name="email" required/></td>
                </tr>
                <tr>
                    <td>Re-type e-mail:</td>
                    <td><input type="text" name="confirmemail" required/></td>
                </tr>
                <tr>
                    <td>Password:</td>
                    <td><input type="password" name="password" required/></td>
                </tr>
                <tr>
                    <td>Re-type password:</td>
                    <td><input type="password" name="confirmpassword"  onKeyDown="if (event.keyCode==13) register();" required/></td>
                </tr>
                <tr>
                    <td> </td>
                </tr>
                <tr>
                    <td colspan="2"><button type="button" onclick="register()">Register</button></td>
                </tr>
            </table>
        </form>
        <div id="registerStatusLabel">&nbsp;</div>
        <br />
        <table>
            <tr>
                <td colspan="2"><button type="button" onclick="showLoginForm()">Back</button></td>
            </tr>
        </table>
    </div>

    <div id="forgotPassContainer">
        <form id="sendCodeForm">
            <table cellspacing="4">
                <tr>
                    <td>User name:</td>
                    <td><input type="text" name="username" required/></td>
                </tr>
                <tr>
                    <td>E-mail:</td>
                    <td><input type="text" name="email" required/></td>
                </tr>
                <tr>
                    <td> </td>
                </tr>
                <tr>
                    <td colspan="2"><button type="button" onclick="sendActivationCode()">Send Activation Code</button></td>
                </tr>
            </table>
        </form>
        <br />
        <br />
        <form id="resetPasswordForm">
            <table>
                <tr>
                    <td>Activation Code:</td>
                    <td><input type="text" name="code" required/></td>
                </tr>
                <tr>
                    <td>New Password:</td>
                    <td><input type="password" name="password" required/></td>
                </tr>
                <tr>
                    <td>Re-type New Password:</td>
                    <td><input type="password" name="confirmpassword" required/></td>
                </tr>
                <tr>
                    <td> </td>
                </tr>
                <tr>
                    <td colspan="2"><button type="button" onclick="resetPassword()">Reset Password</button></td>
                </tr>
            </table>
        </form>
        <div id="forgotPassStatusLabel">&nbsp;</div>
        <br />
        <table>
            <tr>
                <td colspan="2"><button type="button" onclick="showLoginForm()">Back</button></td>
            </tr>
        </table>
    </div>
</div>

<div id="screenshots">
    <a href="#"><img class="border" src="img/preview1.jpg" alt="1" width="120" height = "80" onclick="preview('1')"/></a>&nbsp;&nbsp;
    <a href="#"><img class="border" src="img/preview2.jpg" alt="2" width="120" height = "80" onclick="preview('2')"/></a>&nbsp;&nbsp;
    <a href="#"><img class="border" src="img/preview3.jpg" alt="3" width="120" height = "80" onclick="preview('3')"/></a>&nbsp;&nbsp;
    <a href="#"><img class="border" src="img/preview4.jpg" alt="4" width="120" height = "80" onclick="preview('4')"/></a>
    <h3 class="yellow">Screenshots</h3>
</div>

<div id="preview" class="popup">
</div>

<img src="img/bg_blur.jpg" alt="blur" style="display:none" />

</body>
</html>