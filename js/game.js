// requires utility.js
if (typeof getQueryStrings !== "function") {
    throw "utility.js not included before game.js";
}

var loc = "game";
var game = new Object();
var htmlParameters = getQueryStrings();
game.gameid = htmlParameters['gameid'];
game.gamename = htmlParameters['gamename'];

var model, view;

$(document).ready($(function () {showGameScreen(game.gamename)}));
$(document).ready(initialiseCurrentUserData);

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

// --


function onPlayerFinish() {
    // Disable resign button.
    $('#resignButton')
        .attr('disabled', true)
        .css('opacity', 0.25);

    // Update statistics.
    $.getJSON("updateStats.php",
        function(data){
            if (data != null) {
                $("#winsLabel").text(data["Wins"]);
                $("#defeatsLabel").text(data["Defeats"]);
            }
    });	
}

function showGameScreen(name) {
    // TODO: why is this onlinePlayers thing here?
    $.ajax({
         type: "GET",
         url: "onlinePlayers.php",
         data: {'function':'add'}
        });
    $("#gameLabel").text(name);
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

// TODO: deduplicate with lobby.js
function initialiseCurrentUserData () {
    $.get("getCurrentUserData.php", function(data) {
        var winslossarray = $.parseJSON(data);
        $("#winsLabel").append(winslossarray[0]);
        $("#lossesLabel").append(winslossarray[1]);
    });
    $('#usernameLabel').append(Cookies.get('username'));
}
