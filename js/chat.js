/* MODEL */

var lastfetched;
var updating = false;
var updateInterval;

function enableChatUpdate() {
    clearInterval(updateInterval);
    lastfetched = null;
    $.getJSON("chat.php",
        {  
            'function': 'fetch',
			'gameid': game.gameid
        },
        function(messages){
            if (messages != null){
                displayChat(messages);
                if (messages.length>0){
                    lastfetched =  messages[messages.length-1].time;
                }
            }
            else {
                throw new Error("Error on fetching messages");
            }
            
            updateInterval = setInterval('updateChat()', 1000);
        }
    );
}

function updateChat() {
    if (!updating){
        updating = true;
        $.getJSON("chat.php",
            {
                'function' : 'update',
                'timestamp': lastfetched,
                'gameid'   : game.gameid,
                cache      : false
            },
            function(messages){
                if (messages != null){
                    if (messages.length>0){
                        lastfetched = messages[messages.length-1].time;
                        displayMessages(messages);
                    }
                }
                updating = false;
            }
        );
    }
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
                updateChat();
                moveScrollbarToBottom();
            }
            else {
                alert("Error on sending message");
            }
        });
    }
}




/* VIEW */

function displayChat(messages){
    $('#chat-area').html("");
    $.each(messages, function(i, field){
        var parts = field.time.split(/[\s:-]+/);
        appendChat("<b>" + field.username 
                      + "(" + parts[3] + ":" + parts[4] + "): </b>"
                      + field.message, field.username);                 
        });
        moveScrollbarToBottom();
}

function displayMessages(messages){
    var position = $('#chat-area')[0].scrollTop;
    var scroll = position + $('#chat-area').height() + 40 
                        > $('#chat-area')[0].scrollHeight;
    $.each(messages, function(i, field){
        var parts = field.time.split(/[\s:-]+/);
        appendChat("<b>" + field.username 
                      + "(" + parts[3] + ":" + parts[4] + "): </b>"
                      + field.message,field.username);                 
        });
    if (scroll)
        moveScrollbarToBottom();
    else
        $('#chat-area').scrollTop(position);
}

function appendChat(message,username) {
    var i;
    var width = $('#chat-area').width()/8;
    var p;
    if (username == "System") {
        p = "<p class='red'>";
    } else {
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

