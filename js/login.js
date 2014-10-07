var popup = false;

function initialise() {
    if ($.cookie("user") && $.cookie("pass")) {
        document.forms["loginForm"]["username"].value = $.cookie("user");
        document.forms["loginForm"]["password"].value = $.cookie("pass");
        login();
    }
    else {
        $("#images").fadeIn();
        $("#loginScreen").fadeIn();
        $("#screenshots").fadeIn();
    }
}

/* Switches to the Register Form. */
function showRegisterForm() {
    $("#loginContainer").hide();
    $("#registerContainer").fadeIn();
    $("#loginStatusLabel").html("&nbsp;");
}

/* Switches to the Login Form. */
function showLoginForm() {
    $("#registerContainer").hide();
    $("#forgotPassContainer").hide();
    $("#loginContainer").fadeIn();
    $("#registerStatusLabel").html("&nbsp;");
    $("#forgotPassStatusLabel").html("&nbsp;");
}

/* Switches to the Forgot Password Form. */
function showForgotPassForm() {
    $("#loginContainer").hide();
    $("#forgotPassContainer").fadeIn();
    $("#loginStatusLabel").html("&nbsp;");
}

/* Attempts to login to the system. */
function login() {
    var user = document.forms["loginForm"]["username"].value.toLowerCase();
    var pass = document.forms["loginForm"]["password"].value;

    if (user == null || user == "") {
        $("#loginStatusLabel").text("Please enter your username.");
    } else if (pass == null || pass == "") {
        $("#loginStatusLabel").text("Please enter your password.");
    } else {
	    $("#loginStatusLabel").html('<img src="img/loading.gif" alt="loading" />');
        $.post("login.php",
        {
         username: user,
         password: pass
        },
        function(data) {
            if (data.match("true")) {
				location.reload();
            } else {
                $("#loginStatusLabel").text(data);
            }
        });
    }
}

function setCookie(username,password) {
    $.cookie("user", username, { expires : 7 });
    $.cookie("pass", password, { expires : 7 });
}

/* Logs the user out of the system. */
function logout() {
    $.removeCookie("user");
    $.removeCookie("pass");

    if (loc == "host") {
        $.ajax({
          type: "POST",
          url: "gameSetup.php",
          data: {'function':'delete', 'gameid':game.gameid},
          async:false
        });
    } else if (loc == "client") {
        $.ajax({
          type: "POST",
          url: "gameSetup.php",
          data: {'function':'abandon','gameid':game.gameid},
          async:false
        });
    }          

	$.ajax({url:"logout.php",
	    success:function() {
		    window.location.href = 'index.php';
	    }
	});	
}

/* Registers a new user into the database and logs him in. */
function register() {
    var user = document.forms["registerForm"]["username"].value.toLowerCase();
    var email = document.forms["registerForm"]["email"].value;
    var cemail = document.forms["registerForm"]["confirmemail"].value;
    var pass = document.forms["registerForm"]["password"].value;
    var cpass = document.forms["registerForm"]["confirmpassword"].value;

    if (user == null || user == "") {
        $("#registerStatusLabel").text("Please enter a username.");
    } else if (email == null || email == "") {
        $("#registerStatusLabel").text("Please enter an email address.");
    } else if (!isValidEmail(email)) {
        $("#registerStatusLabel").text("Please enter a valid email address.");
    } else if (cemail == null || cemail == "") {
        $("#registerStatusLabel").text("Please enter the email address twice.");
    } else if (email != cemail) {
        $("#registerStatusLabel").text("The email addresses do not match up.");
    } else if (pass == null || pass == "" || pass.length < 6) {
        $("#registerStatusLabel").text("Please enter a password at least 6 character long.");
    } else if (cpass == null || cpass == "") {
        $("#registerStatusLabel").text("Please enter the password twice.");
    } else if (pass != cpass) {
        $("#registerStatusLabel").text("The passwords do not match up.");
    } else {
        $.post("register.php",
        {
         username: user,
         password: pass,
         email: email
        },
        function(data) {
            if (data.match("true")) {
                location.reload();
            } else {
                $("#registerStatusLabel").text("Username '" + user + "' already exists.");
            }
        });
    }
}

/* Checks whether the given email address is valid. */
function isValidEmail(email) {
    return ((email.indexOf(".") > 0) && (email.indexOf("@") > 0)) 
	            || /[^a-zA-Z0-9.@_-]/.test(email);
}

/* Checks if the current username is available. */
function checkUserName() {
    var user = document.forms["registerForm"]["username"].value.toLowerCase();
    $.post("check_username.php",
    {
     username: user
    },
    function(data) {
        $("#userNameStatus").text(data);
            if (data.match("Available.")) {
                $("#userNameStatus").css("color", "#1DF236");
                $("#userNameStatus").text("Available");
            } else {
                $("#userNameStatus").css("color", "#F24447");
                $("#userNameStatus").text("Taken");
            }
    });
}

/* Sends to the user an activation code to reset his password. */
function sendActivationCode() {
    var user = document.forms["sendCodeForm"]["username"].value.toLowerCase();
    var email = document.forms["sendCodeForm"]["email"].value;
    
    if (user == null || user == "") {
        $("#forgotPassStatusLabel").text("Please enter your username.");
    } else if (email == null || email == "") {
        $("#forgotPassStatusLabel").text("Please enter your email.");
    } else {
	    $.post("forgotPass.php",
        {
          username: user,
          email: email
        },
        function(data) {
            $("#forgotPassStatusLabel").text(data);
			if (data.match("has been sent"))
			    $("#forgotPassStatusLabel").css("color", "#1DF236");
            else           
                $("#forgotPassStatusLabel").css("color", "#F24447");	
        });
	}
}

/* Sets a new password for the current user. */
function resetPassword() {
    var code = document.forms["resetPasswordForm"]["code"].value;
	var pass = document.forms["resetPasswordForm"]["password"].value;
	var cpass = document.forms["resetPasswordForm"]["confirmpassword"].value;
	
	if (code == null || code == "") {
        $("#forgotPassStatusLabel").text("Please enter the activation code.");
    } else if (pass == null || pass == "" || pass.length < 6) {
        $("#forgotPassStatusLabel").text("Please enter a password at least 6 character long.");
    } else if (cpass == null || cpass == "") {
        $("#forgotPassStatusLabel").text("Please enter the password twice.");
    } else if (pass != cpass) {
        $("#forgotPassStatusLabel").text("The passwords do not match up.");
    } else {
	    $.post("forgotPass.php",
        {
          code: code,
		  password: pass
        },
        function(data) {
		    $("#forgotPassStatusLabel").text(data);
		    if (data.match("successfully"))
			    $("#forgotPassStatusLabel").css("color", "#1DF236");
            else           
                $("#forgotPassStatusLabel").css("color", "#F24447");			
        });
	}
}

function popUpStory() {
    showPopUp('story');
}

function hideStory() {
    hidePopUp('story');
}

function popUpRules() {
    showPopUp('rules');
}

function hideRules() {
    hidePopUp('rules');
}

function showPopUp(context) {
    if (!popup) {
        blurBackground();
        $("#loginScreen").fadeOut();
        $("#" + context).fadeIn();
        popup = true;
    }

}

function hidePopUp(context) {
    $("#" + context).fadeOut('fast');
    restoreBackground();
    $("#loginScreen").fadeIn();
    popup = false;
}

function blurBackground() {
    $("html").css("background-image","url('img/bg_blur.jpg')");
    $("#logo").css("opacity","0.3");
    $("#screenshots").css("opacity","0.3");
}

function restoreBackground() {
    $("html").css("background-image","url('img/bg.jpg')");
    $("#logo").css("opacity","1.0");
    $("#screenshots").css("opacity","1.0");
}

function preview(i) {
    if (!popup) {
        $("#preview").append("<img src='img/preview" + i + ".jpg' alt='preview' width='100%'/>");
        showPopUp('preview');
        $("#preview").click(function() {hidePreview();});
        popup = true;
    }
}

function hidePreview() {
    popup = false;
    $("#preview").fadeOut('fast', function() {
        $("#preview").html("");
    });
    $("#loginScreen").show();
    restoreBackground();
    $("#preview").unbind();
}
