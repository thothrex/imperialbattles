/* MODEL */

var lastfetched = null;
var updating = false;
var updateInterval;
var chatServerScriptUrl = "chat.php";
var chatUpdateInterval = 1000; // In milliseconds

function enableChatUpdate() {
    $.getJSON(chatServerScriptUrl,
        {  
            'function': 'fetch',
			'gameid': game.gameid
        },
        function(messages){
            updateChat(messages);
            updateInterval = setInterval('requestChatUpdate()', chatUpdateInterval);
        }
    );
}

function requestChatUpdate() {
    $.getJSON(chatServerScriptUrl,
        {
            'function' : 'update',
            'timestamp': lastfetched,
            'gameid'   : game.gameid,
            cache      : false
        },
        function(messages){
            updateChat(messages);
        }
    );
}

function sendMessage() {
    var message = document.forms["messageForm"]["msg"].value;
    document.forms["messageForm"]["msg"].value="";
    if (message != null && message != "" && message != "\n") {
        $.post("chat.php",
        {
         'function': 'send',
          message: message,
		 'gameid': game.gameid
        },
        function(data) {
            if (data!=null) {
                requestChatUpdate();
                moveScrollbarToBottom();
            }
            else {
                alert("Error on sending message");
                throw "sendMessage received null server response.";
            }
        });
    }
}

function updateChat (messages) {
    if (messages != null && messages.length > 0){
        lastfetched = messages[messages.length-1].time;
        displayMessages(messages);
    }
}


/* VIEW */

function displayMessages(messages){
    var position = $('#chat-area')[0].scrollTop;
    var scroll
        = position + $('#chat-area').height() + 40
        > $('#chat-area')[0].scrollHeight;

    $.each(messages, function(i, field){
        var parts = field.time.split(/[\s:-]+/);
        appendMessage(
              parts[3] + ":" + parts[4] + " " // hours, minutes
            + "<b>" + field.username + ":</b>"
            + field.message
            , field.username
        );
    });

    if (scroll) { moveScrollbarToBottom(); }
    else        { $('#chat-area').scrollTop(position); }
}

function appendMessage(message,username) {
    var i;
    var width = $('#chat-area').width()/8;
    var p;
    if (username == "System") {
        p = "<p class='red'>";
    }
    else {
        p = "<p class='normal'>";
    }
    for(i = 0; i < message.length - width; i += width){
        $('#chat-area').append(p + message.substring(i,i+width) + "</p>");
    }
    $('#chat-area').append(p + message.substring(i) + "</p>");
}

function moveScrollbarToBottom() {
    $('#chat-area').scrollTop($('#chat-area')[0].scrollHeight);
}

function displayError (error) {
    console.log(
        error.name + " @ " + error.fileName + error.lineNumber
        + ": " + error.message
    );
}
