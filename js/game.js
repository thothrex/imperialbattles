
var loc = "game";

var model, view;

$(function () {
    model = new Model(
        game.gameid
    );

    model.listen('defeat', onPlayerFinish);
    model.listen('victory', onPlayerFinish);
    model.listen('gameOver', onPlayerFinish);

    view = new GView(
        model,
        '#playground',
        {
            width:800,
            height:650,
            debug: true
        }
    );

    model.startGame();
});


function onPlayerFinish() {
    // Disable resign button.
    $('#resignButton')
        .attr('disabled', true)
        .css('opacity', 0.25);

    // Update statistics.
    $.getJSON("updateStats.php",
        function(data){
            if (data != null) {
                $("#winsLabel").text(data[0]);
                $("#defeatsLabel").text(data[1]);
            }
    });	
}


function showGameScreen(name) {
    $.ajax({
         type: "GET",
         url: "onlinePlayers.php",
         data: {'function':'add'}
        });
    $("#chatScreen").fadeIn();
    moveChatWindow();
    enableChatUpdate();
    $("#gameLabel").text(name);
}



function moveChatWindow() {
    $("#chatScreen").css("top","5%");
    $("#chatScreen").css("left","5%");
    $("#chatScreen").css("width","30%");
    $("#chatScreen").css("height","100%");
}

function resign() {
    var conf = confirm("Are you sure you want to resign? This will count as a defeat.");
    if (!conf) return;
    $.post("gameServer.php",
        {
         'function': 'resign',
         'gameid' : game.gameid
        },
        function(data) {
            if (data.match("success")) {
               loc = "lobby";
               window.location='lobby.php';
            }
            else { alert("Cannot resign from game: " + data); }
        });
}

function returnToLobby() {
    loc = "lobby";
    window.location='lobby.php'
}

window.onbeforeunload = confirmExit;
function confirmExit() {
    if (loc != "lobby") {
        $.ajax({
         type: "GET",
         url: "onlinePlayers.php",
         data: {'function':'remove'},
         async:false
        });
    }
}





