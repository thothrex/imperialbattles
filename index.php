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
    <script src="https://cdn.jsdelivr.net/npm/js-cookie@2/src/js.cookie.min.js"></script>
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
    </div>

    <br />
    <br />

    <div id="loginScreen">
        <div id="loginContainer">
            <form id="loginForm">
                <div id="loginTable">
                    <div class="row">
                        <input class="col-sm-6 col-sm-offset-3"
                               type="text" name="username" required
                               onKeyDown="if (event.keyCode==13) login();"
                               placeholder="Username" />
                    </div>
                    <div class="row">
                        <input class="col-sm-6 col-sm-offset-3"
                               type="password" name="password" required
                               onKeyDown="if (event.keyCode==13) login();"
                               placeholder="Password" />
                    </div>
                    <div class="row button-row">
                        <button class="col-sm-6 col-sm-offset-3"
                                type="button" id="login-button"
                                onclick="login()">
                            Login
                        </button>
                        <!--
                        <span id="rememberme-text" class="col-sm-3">
                            <input type="checkbox" name="remember" />
                            Remember me
                        </span>
                        -->
                    </div>
                    <div class="row button-row">
                        <button type="button"
                                class="col-sm-6 col-sm-offset-3 login-option"
                                onclick="showRegisterForm()">
                            Register
                        </button>
                    </div>
                    <div class="row button-row">
                        <button type="button"
                                class="col-sm-6 col-sm-offset-3 login-option"
                                onclick="showForgotPassForm()">
                            Forgot password
                        </button>
                    </div>
                </div>
            </form>
            <div id="loginStatusLabel">&nbsp;</div>
            <br />
            <div class="row">
                <a href="#"><img src="img/story.png" alt="story" onclick="popUpStory()" /></a>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <a href="#"><img src="img/rules.png" alt="rules" onclick="popUpRules()" /></a>
            </div>
        </div>

        <div id="registerContainer">
            <form id="registerForm">
                <div class="row">
                    <span class="register-caption pixel-title">User name:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="text" name="username" onchange="checkUserName()" required/>
                    <span class="col-sm-2 status-caption"
                          id="userNameStatus"></span>
                </div>
                <div class="row">
                    <span class="register-caption pixel-title">E-mail:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="text" name="email" required/>
                </div>
                <div class="row">
                    <span class="register-caption pixel-title">Re-type e-mail:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="text" name="confirmemail" required/>
                </div>
                <div class="row">
                    <span class="register-caption pixel-title">Password:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="password" name="password" required/>
                </div>
                <div class="row">
                    <span class="register-caption pixel-title">Re-type password:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="password" name="confirmpassword" required
                           onKeyDown="if (event.keyCode==13) register();"/>
                </div>
                <div class="row button-row">
                    <button type="button" onclick="register()">
                        Register
                    </button>
                </div>
            </form>
            <div id="registerStatusLabel">&nbsp;</div>
            <div class="row">
                <button type="button" onclick="showLoginForm()">
                    Back
                </button>
            </div>
            <br />
        </div>

        <div id="forgotPassContainer">
            <form id="sendCodeForm">
                <div class="row">
                    <span class="recoverpassword-caption pixel-title">User name:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="text" name="username" required/>
                </div>
                <div class="row">
                    <span class="recoverpassword-caption pixel-title">E-mail:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="text" name="email" required/>
                </div>
                <div class="row button-row">
                    <span class="recoverpassword-caption">&nbsp;</span>
                    <span class="col-sm-5 col-xs-11">
                        <button type="button" class="recoverpassword-button" onclick="sendActivationCode()">
                            Send Activation Code
                        </button>
                    </span>
                </div>
            </form>
            <br />
            <br />
            <form id="resetPasswordForm">
                <div class="row">
                    <span class="recoverpassword-caption pixel-title">Activation Code:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="text" name="code" required/>
                </div>
                <div class="row">
                    <span class="recoverpassword-caption pixel-title">New Password:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="password" name="password" required/>
                </div>
                <div class="row">
                    <span class="recoverpassword-caption pixel-title">Re-type New Password:</span>
                    <input class="col-sm-5 col-xs-11"
                           type="password" name="confirmpassword" required/>
                </div>
                <div class="row button-row">
                    <span class="recoverpassword-caption">&nbsp;</span>
                    <span class="col-sm-5 col-xs-11">
                        <button type="button" class="recoverpassword-button" onclick="resetPassword()">
                            Reset Password
                        </button>
                    </span>
                </div>
            </form>
            <div id="forgotPassStatusLabel">&nbsp;</div>
            <div class="row">
                <button type="button" onclick="showLoginForm()">
                    Back
                </button>
            </div>
            <br />
        </div>
    </div>
    <br />
    <div id="screenshots">
        <h3 class="pixel-title">Screenshots</h3>
        <div class="row">
            <a href="#">
                <img class="border" src="img/preview1.jpg" alt="1" width="120" height = "80" onclick="preview('1')"/>
            </a>
            <a href="#">
                <img class="border" src="img/preview2.jpg" alt="2" width="120" height = "80" onclick="preview('2')"/>
            </a>
        </div>
        <div class="row">
            <a href="#">
                <img class="border" src="img/preview3.jpg" alt="3" width="120" height = "80" onclick="preview('3')"/>
            </a>
            <a href="#">
                <img class="border" src="img/preview4.jpg" alt="4" width="120" height = "80" onclick="preview('4')"/>
            </a>
        </div>
    </div>
</div>

<footer class="row">
    <?php include "copyright_info_string.html" ?>
</footer>

<div id="story" class="popup">
    <h1>Story</h1>
    <?php include_once("story.html"); ?>
    <br />
    <button type="button" onclick="hideStory()">Close</button>
</div>
<div id="rules" class="popup">
    <h1>How to play</h1>
    <?php include_once("rules.html"); ?>
    <br />
    <button type="button" onclick="hideRules()">Close</button>
</div>

<div id="preview" class="popup">
</div>

<img src="img/bg_blur.jpg" alt="blur" style="display:none" />

</body>

</html>