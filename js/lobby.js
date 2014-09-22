// requires chat.js

/* MODEL */
var SERVER_DUPLICATE_RESPONSE = 'duplicate';

var playername;
var map;
var game = {"gameid":1};
var players;

var gameBrowserInterval;
var gameSetupInterval;
var scoresInterval;
var onlinePlayersInterval;

var maps;
var colours = new Array("red","blue","green","white","yellow","cyan","black");
var playercolours = new Array("red","blue","green","white","yellow","cyan","black");
var lastUpdated; //ISO datetime string
var loc = "lobby";
var popup = false;
var kickingOut = false;

var databaseTrue = '1';

function lobbyInitialise() {
    $.ajax({
         type: "GET",
         url: "onlinePlayers.php",
         data: {'function':'add'}
    });
    playername = $('#usernameLabel').text();
    enableChatUpdate();
    enablePlayersUpdate();
    enableGameBrowserUpdate();
    showScreen();
    loadScores();

}

function switchToGameSelection() {
    game = {"gameid":1};
    clearOptionsScreen();
    enableChatUpdate();
    enablePlayersUpdate();
    disableGameSetupUpdate();
    enableGameBrowserUpdate();
    $("#backBtn").unbind();
    $("#startReadyBtn").unbind();
    loc = "lobby";
    showGameSelectionScreen();
}

function switchToHostGameSetup() {
    $.getJSON("gameSetup.php",
    {
     'function': 'loadmaps'
    },
    function(result){
        maps = result;
        $("#backBtn").click(function() {deleteGame();});
        $("#startReadyBtn").click(function() {startGame();});
        loc = "host";
        showGameOptionsForHost();
        switchToGameSetup();
    });
}

function switchToClientGameSetup(gameid) {
    $.getJSON("gameSetup.php",
    {
     'function': 'initialRetrieve',
     'gameid' : gameid
    },
    function(result){
        lastUpdated = result[0].lastupdated;
        setMapGamePlayers(result);
        $("#backBtn").click(function() {abandonGame();});
        $("#startReadyBtn").click(function() {playerReady();});
        loc = "client";
        showGameOptionsForClient();
        switchToGameSetup();
    });
}

function switchToGameSetup() {
    enableChatUpdate();
    disablePlayersUpdate();
    disableGameBrowserUpdate()
    enableGameSetupUpdate();
    showGameSetupScreen();
}

function enableGameBrowserUpdate() {
    updateGameBrowser();
	gameBrowserInterval = setInterval('updateGameBrowser()',1000);
}

function disableGameBrowserUpdate() {
    clearInterval(gameBrowserInterval);
}

function enableGameSetupUpdate() {
    updateGameSetup();
	gameSetupInterval = setInterval('updateGameSetup()',1000);
}

function disableGameSetupUpdate() {
    clearInterval(gameSetupInterval);
}

function updateGameBrowser() {
    $.getJSON("gameBrowser.php",
        function(result){
            emptyContainer('serverList');
            $.each(result, function(i, field){
                appendGame(field);
            }); 
            showSelectedGame();
        }
    );
}

function createGame() {
    if (!popup) {
        var gamename = prompt("Enter a name for the new game:");
        if (gamename){ 
            $.getJSON("gameSetup.php",
                {
                 'function': 'create',
                 'gamename': gamename,
                 'map': '1'
                },
                function(result) {
                    if (result === SERVER_DUPLICATE_RESPONSE){
                        alert("A game with name '" + gamename + "' already exists");
                    }
                    else if (result) {
                        lastUpdated = result[0].lastupdated;
                        setMapGamePlayers(result);
                        switchToHostGameSetup();
                    }
                    else {
                        alert("Game creation error: " + result);
                    }
                });
        } 
    }
}

//lastUpdated need to be initialised
function updateGameSetup() {
    $.getJSON("gameSetup.php",
        {
            'function':'retrieve',
            'gameid' : game.gameid,
            'lastUpdated': lastUpdated
        },
        function(result){
            if (result.length > 0) {
                setMapGamePlayers(result);
                if (game.inprogress == databaseTrue) {
                    $.post("gameSetup.php",
                        {
                            'function': 'begin'
                        },
                        function(data) {
                            loc = "game";
                            document.forms["startGameForm"]["gameid"].value = game.gameid;
                            $("#startGameForm").submit();
                        });
                }

                if(isStillInGame()){
                    lastUpdated = result[0].lastupdated;
                    updateSetupView();
                }
                else {
                    alert("You have been kicked out of the game");
                    switchToGameSelection();
                }
            }
            else if (!result && loc == "client") {
                alert("The game has been canceled");
                switchToGameSelection();
            }
        });
}

function deleteGame() {
    $.post("gameSetup.php",
        {
            'function': 'delete',
            'gameid' : game.gameid
        },
        function(data) {
            if (data === 'success'){
                switchToGameSelection();
            }
            else if (data === 'failure') {
                alert("gameSetup.php?function=delete failed");
            }
            else {
                alert("deleteGame error: received " + data);
            }
        });
}

function joinGame() {
    if (!popup) {
        var selectedGame = document.forms["serverForm"]["server"].value;
        if (typeof selectedGame === 'undefined') return;

	    var gameid = selectedGame.substring(1);
	    $.post("gameSetup.php",
        {
         'function': 'join',
          gameid: gameid
        },
        function(data) {
            if (data === 'success') {
               switchToClientGameSetup(gameid);
            }
            else if (data === 'failure'){
                console.error("gameSetup.php:join failed (properly)");
            }
            else {
                console.error("gameSetup.php:join failed (improperly)");
            }
        });
    }
}

function abandonGame() {
    disableGameSetupUpdate();
    $.post("gameSetup.php",
        {
         'function': 'abandon',
         'gameid' : game.gameid
        },
        function(data) {
            if (data === 'success'){
               switchToGameSelection();
            }
            else if (data === 'failure'){
                console.log("gameSetup.php:abandon failed");
            }
        }); 
}

function startGame() {
    if (players.length != game.playerslimit) {
        alert("Players missing");
        return;
    }
    for (var i = 0; i < players.length; i++) {
        if (players[i].ready != databaseTrue) {
            alert("Players are not ready yet");
            return;
        }
    }
    
    var team = players[0].team;
    var valid = false;
    for (var i = 1; i<players.length;i++){
       if (team != players[i].team){
           valid = true;
           break;
       }
    }
    if (!valid){
       alert("There must be more than one team");
       return;
    }
    if (playercolours.length>(colours.length-players.length+1)){
       alert("Some players have the same colour");
       return;
    }
    $.post("gameServer.php",
        {
            'function': 'start',
            'gameid'  : game.gameid
        },
        function(data) {
            if ( data.match("success") ) { //deliberate - errors otherwise
                loc = "game";
                document.forms["startGameForm"]["gameid"].value = game.gameid;
                $("#startGameForm").submit();
            }
            else {
                alert("unable to start game: " + data);
            }            
        });
}

function playerReady() {
    $.post("gameSetup.php",
        {
         'function': 'ready',
         'gameid' : game.gameid
        },
        function(data) {
            if (data === 'failure') {
               alert("There was a server error");
            }
            else if (data === 'success') {
               $("#startReadyBtn").text("WAITING...").attr('disabled','disabled');
               $('#colourOption').attr('disabled','disabled');
               $('#teamOption').attr('disabled','disabled');
            }
            else {
                alert("There was an error in marking yourself as ready: "
                      + data);
            }
        }
    );
}

function setMap() {
    var selectedMap = maps[$("#mapOptions").val()];
    var noPlayers = $("#noPlayersOptions").val();
    var maxPlayers = selectedMap.maxplayers;
    if (maxPlayers>=players.length) {
        showMapPreview(selectedMap.mapname);
        updateNoPlayersSelectionBox(maxPlayers);
        if (noPlayers > maxPlayers) {
            $("#noPlayersOptions").val(maxPlayers);
            setListOfPlayers();
        } else {
            $("#noPlayersOptions").val(noPlayers);
            updateGame();
        }
    } else {
        alert("You have to kick out at least " + (players.length-maxPlayers) 
                + " player(s) before changing the map to " + selectedMap.mapname);
        var i=0;
        while(maps[i].mapid != map.mapid){
         i++
        }
        $("#mapOptions").val(i);
    }
    
}

function updateGame() {
    if (!$.isNumeric($("#turnTimeVal").val()) || $("#turnTimeVal").val() < 0) {
        $("#turnTimeVal").val(game.turntimeout);
        alert("Turn time must be a number.");
    } else {
        $.post("gameSetup.php",
            {
             'function': 'updateGame',
             'gameid' : game.gameid,
             'mapID': maps[$("#mapOptions").val()].mapid,
             'playersLimit': $("#noPlayersOptions").val(),
             'turnTimeout': $("#turnTimeVal").val()
            },
            function(data) {
                if (data.match("failure")) {
                   alert("Error on updating game settings");
                }
            });
    }
}

function removePlayer(username){
    if(!kickingOut){
      kickingOut = true;
      $.post("gameSetup.php",
          {
              'function': 'removePlayer',
              'gameid' : game.gameid,
              'username': username
          },
          function(data) {
              if (!data.match("success"))
                alert("Error On kicking Player: " + data);
              kickingOut = false;
          }).fail(function(data){kickingOut = false;
          });
    }
}

function updatePlayer() {
    $.post("gameSetup.php",
        {
         'function': 'updatePlayer',
         'gameid' : game.gameid,
         'colour'  : playercolours[$("#colourOption").val()],
         'team'    : $("#teamOption").val() 
        },
        function(data) {
            if (data.match("failure")) {
               alert("Error on updating player settings");
            }
        });
}

function setMapGamePlayers(json) {
    map = {"mapid":json[0].mapid,"mapname":json[0].mapname,"maxplayers":json[0].maxplayers,
            "width":json[0].width,"height":json[0].height};
    game = {"gameid":json[0].gameid,"gamename":json[0].gamename,"playerslimit":json[0].playerslimit,
            "turntimeout":json[0].turntimeout,"hostname":json[0].hostname,"inprogress":json[0].inprogress};
    players = new Array();
    for (var i = 0; i < json.length; i++) {
        players[i]={"username":json[i].username,"colour":json[i].colour,"team":json[i].team,"ready":json[i].ready};
    }
}

function isStillInGame(){
    for (var i = 0; i<players.length; i++){
        if (players[i].username == playername){
            return true;
        }
    }
    return false;
    
}

function resumeGame() {
    if (!popup) {
        loc = "game";
        document.forms["startGameForm"]["gameid"].value = 
            (document.forms["serverForm"]["server"].value).substring(1);
        $("#startGameForm").submit();
    }
}


function loadScores() {
    $.getJSON("scores.php",
        function(result){
            showScoreBoard(result);
        });
}


function popUpScoresL() {
    enableScoresUpdate();
    showPopUpL('scores');
}

function enableScoresUpdate() {
    loadScores();
	scoresInterval = setInterval('loadScores()',1000);
}

function disableScoresUpdate() {
    clearInterval(scoresInterval);
}

function hideScoresL() {
    hidePopUpL('scores');
    disableScoresUpdate();
}

function popUpStoryL() {
    showPopUpL('story');
}

function hideStoryL() {
    hidePopUpL('story');
}

function popUpRulesL() {
    showPopUpL('rules');;
}

function hideRulesL() {
    hidePopUpL('rules');
}

function enablePlayersUpdate() {
    updatePlayersList();
    onlinePlayersInterval = setInterval('updatePlayersList()',1000);
}

function disablePlayersUpdate() {
    clearInterval(onlinePlayersInterval);
}

function updatePlayersList(){
    $.ajax({
        dataType: "json",
        url:      "onlinePlayers.php",
        cache:     false,
        data: {'function': 'getList'},
        success: function(players){
            if (players != null){
                displayPlayers(players);
            }
            else {
                alert("Error on updating players");
            }
        }
    });
}

window.onbeforeunload = confirmExit;
function confirmExit(){
   if (loc == "host")
      return "If you leave the page the game will be canceled";
   else if  (loc == "client")
      return "If you leave the page you will leave the game";
}

window.onunload = clearGamePlayer;
function clearGamePlayer() {

    if (loc != "game") {
        $.ajax({
         type: "GET",
         url: "onlinePlayers.php",
         data: {'function':'remove'},
         async:false
        });
    }

    if (loc == "host" || loc == "client") {
        //if ("Are you sure you want to exit this page?") {
            if (loc == "host") {
                $.ajax({
                  type: "POST",
                  url: "gameSetup.php",
                  data: {'function':'delete', 'gameid':game.gameid},
                  async:false
                });
            } else {
                $.ajax({
                  type: "POST",
                  url: "gameSetup.php",
                  data: {'function':'abandon','gameid':game.gameid},
                  async:false
                });
            }            
        
    }
}




    





















/* VIEW */

function showScreen() {
	$("#gameSelectionScreen").fadeIn();
	$("#logoutScreen").fadeIn();
    $("#chatScreen").fadeIn();
}

function showGameSelectionScreen() {
    $("#gameSetupScreen").fadeOut();
    $("#gameSelectionScreen").fadeIn();
    $("#onlinePlayers").fadeIn();
    moveChatWindowUpwards()
}

function showGameSetupScreen() {
    $("#gameSelectionScreen").fadeOut();
    $("#gameSetupScreen").fadeIn();
    $("#onlinePlayers").fadeOut();
    moveChatWindowDownwards();
    $("#gameName").text(game.gamename);
}

function moveChatWindowDownwards() {
    $("#chatScreen").css("top","68%");
    $("#chatScreen").css("left","0%");
    $("#chatScreen").css("width","60%");
    $("#chatScreen").css("height","35%");
}

function moveChatWindowUpwards() {
    $("#chatScreen").css("top","10%");
    $("#chatScreen").css("left","8%");
    $("#chatScreen").css("width","50%");
    $("#chatScreen").css("height","100%");
}

function emptyContainer(container) {
    $("#" + container).html("");
}

function appendGame(field) {
    var inprogress = (field.inprogress == databaseTrue) ? "IN PROGRESS" : "";
    $("#serverList").append(
                "<div id='g" + field.gameid
              +"' class='box' onclick=selectGame('g" + field.gameid + "','"
              + field.inprogress + "')><img src='img/"+ field.mapname
              + ".png' alt='map' height='60px' width='60px' style='float:left;'/><table class='property'><tr><th>Name: </th><td>" 
              + field.gamename + "</td></tr><tr><th>Map:</th><td>" + field.mapname + "</td><td class='orange'> " 
              + inprogress + "</td></tr><tr><th>Players:</th><td>" + field.noplayers + " / " 
              + field.playerslimit + "</td></tr></table></div>");
}

function showSelectedGame() {
    var selected = document.forms["serverForm"]["server"].value;
    if (selected != null && selected != "")
        $("#" + selected).css("background","red");
}

function selectGame(gameID,inProgress) {
    $("#joinBtn").removeAttr('disabled');
    if (document.forms["serverForm"]["server"].value != null || document.forms["serverForm"]["server"].value != "")
        $("#" + document.forms["serverForm"]["server"].value).css("background","");
    document.forms["serverForm"]["server"].value = gameID;
    $("#" + gameID).css("background","red");
	if (inProgress == databaseTrue) {
		$("#joinBtn").text("Resume");
	    $("#joinBtn").unbind().click(function() {resumeGame();});
	} else {
		$("#joinBtn").text("Join");
	    $("#joinBtn").unbind().click(function() {joinGame();});
	}
}

function showGameOptionsForHost() {
    playercolours = colours.slice(0);
    $("#map").html("<select id='mapOptions' onchange='setMap()'></select>");
    for (var i = 0; i < maps.length; i++) {
        $("#mapOptions").append("<option value=" + i + ">"
            + maps[i].mapname+ " / max players:" + maps[i].maxplayers 
            + "</option>");
    }
    $("#mapOptions").val(0);
    showMapPreview(maps[0].mapname);

    $("#noPlayers").html("<select id='noPlayersOptions' onchange='setListOfPlayers()'><option value='2'>2</option></select>");
    for (var i = 3; i <= maps[0].maxplayers; i++) {
        $("#noPlayersOptions").append("<option value=" + i + ">" + i + "</option>");
        $("#p" + i).show();
    }
    $("#noPlayersOptions").val(maps[0].maxplayers);

    $("#turnTime").html("<input type='text' id='turnTimeVal' size='5' onchange='updateGame()' value='0'/>");

    $("#p1Name").text(playername);
    $("#p1Colour").html("<select id='colourOption' onchange='setColour()' style='background-color:red;'><option value='0' style='background-color:red;'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option></select>");
    for (var i = 1; i < colours.length; i++) {
        $("#colourOption").append("<option value='" + i + "' style='background-color:" + colours[i] + ";'></option>");
    }
    $("#colourOption").val(0); 
    $("#p1Team").html("<select id='teamOption' onchange='updatePlayer()'><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option><option value='6'>6</option></select>");
    $("teamOption").val(1);

    $("#startReadyBtn").text("START");
    $("#startReadyBtn").removeAttr('disabled');
}

function showMapPreview(mapname) {
    $('#mapImage').css("background-image","url('img/" + mapname + ".png')");
}

function updateNoPlayersSelectionBox(maxplayers) {
    emptyContainer("noPlayersOptions");
    for (var i = 2; i <= maxplayers; i++){
        $("#noPlayersOptions").append("<option value=" + i + ">" + i +"</option>");
    }
}

function showGameOptionsForClient() {
    playercolours = colours.slice(0);
    $("#map").html(map.mapname + " / max players:" + map.maxplayers);
    showMapPreview(map.mapname);
    $("#noPlayers").html(game.playerslimit);
    $("#turnTime").html(game.turntimeout);

    for (var i = 3; i <= game.playerslimit; i++) {
        $("#p" + i).show();
    }
    
    for (i = 0; i < players.length; i++) {
        $("#p" + (i+1) + "Name").text(players[i].username);
        if (players[i].ready == databaseTrue)
            $("#p" + (i+1) + "Ready").html("<img src='img/greendot.png' alt='yes' />");
        else
            $("#p" + (i+1) + "Ready").html("<img src='img/reddot.png' alt='no' />");
        if (playername != players[i].username) {
            var index = playercolours.indexOf(players[i].colour);
            playercolours.splice(index,1);
            $("#p" + (i+1) + "Colour").html("<span style='background:" + players[i].colour 
              + ";'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>");
            $("#p" + (i+1) + "Team").text(players[i].team);
        } else {
            $("#p" + (i+1) + "Colour").html("<select id='colourOption' onchange='setColour()' style='background-color:" + playercolours[0] + ";'></select>");
            for (var j = 0; j < playercolours.length ; j++) {
                $("#colourOption").append("<option value='" + j + "' style='background:" 
                    + playercolours[j] + ";'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>");
            }	
            $("#colourOption").val(0);
            $("#p" + (i+1) + "Team").html("<select id='teamOption' onchange='updatePlayer()'><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option><option value='6'>6</option></select>");
            $("#teamOption").val(i+1);
        }  
    }
    updatePlayer();
    $("#startReadyBtn").text("READY");
     $("#startReadyBtn").removeAttr('disabled');
}

function setListOfPlayers() {
    var noPlayers = $("#noPlayersOptions").val();
    if (noPlayers<players.length){
        alert("You have to kick out at least " + (players.length-noPlayers) 
                + " player(s) before changing the players limit to " + noPlayers);
        $("#noPlayersOptions").val(players.length);
        noPlayers = players.length;
    } 
    if (game.playerslimit!=noPlayers) {
        game.playerslimit = noPlayers;
        for (var i = 3; i <= noPlayers; i++) {
            $("#p" + i).show();
        }
        for (var j = parseInt(noPlayers); j < 6; j++) {
            $("#p" + (j+1)).hide();
        }
        updateGame();
    }
}

function setColour() {
    var colour = playercolours[$("#colourOption").val()];
    $("#colourOption").css("background-color",colour);
    updatePlayer();
}

function clearOptionsScreen() {
    emptyContainer('gameName');
    emptyContainer('map');
    emptyContainer('noPlayers');
    emptyContainer('turnTime');
    $("#p3").hide();
    $("#p4").hide();
    $("#p5").hide();
    $("#p6").hide();
    for (var i = 1; i <= 6; i++) {
        emptyContainer("p" + i + "Name");
        emptyContainer("p" + i + "Colour");
        emptyContainer("p" + i + "Team");
        emptyContainer("p" + i + "Ready");
    }
    for (var j = 2; j <= 6; j++) {
        emptyContainer("p" + j + "Kick");
    }
}

function updatePlayersView(){
    var colour = playercolours[$("#colourOption").val()]; //get current colour
    var team = $('#teamOption').val();
    playercolours = colours.slice(0); //copy
    var i;
    // show players options
    for (i = 0; i < players.length; i++) {
        $("#p" + (i+1) + "Name").text(players[i].username);
        if (players[i].ready == databaseTrue)
            $("#p" + (i+1) + "Ready").html("<img src='img/greendot.png' alt='yes' />");
        else
            $("#p" + (i+1) + "Ready").html("<img src='img/reddot.png' alt='no' />");
        if (playername != players[i].username) {
            var index = playercolours.indexOf(players[i].colour);
            playercolours.splice(index,1);  //remove colour from available colours
            $("#p" + (i+1) + "Colour").html("<span style='background:" + players[i].colour 
                + ";'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>");
            $("#p" + (i+1) + "Team").text(players[i].team);
        } else {
            colour = players[i].colour;
            $("#p" + (i+1) + "Colour").html("<select id='colourOption' onchange='setColour()'"
                + "style='background-color:" + colour + ";'></select>");
            $("#p" + (i+1) + "Team").html("<select id='teamOption' onchange='updatePlayer()'>"
                + "<option value='1'>1</option><option value='2'>2</option>"
                + "<option value='3'>3</option><option value='4'>4</option>"
                + "<option value='5'>5</option><option value='6'>6</option></select>");
            $("#teamOption").val(team );
            if (players[i].ready == databaseTrue && playername != game.hostname){
                $('#colourOption').attr('disabled','disabled');
                $('#teamOption').attr('disabled','disabled');
            }
        }
    }
    //clear the empty players values
    for (var j = i+1; j<=game.playerslimit;j++){
        $("#p" + j + "Name").text("");
        $("#p" + j + "Ready").html("");
        $("#p" + j + "Colour").html("");
        $("#p" + j + "Team").text("");  
        $("#p" + j + "Kick").html("");
    }
    //re-make colour drop down list
    var colourIndex;
    for (var j = 0; j < playercolours.length ; j++) {
        if (colour.match(playercolours[j]))
            colourIndex = j;
        $("#colourOption").append("<option value='" + j + "' style='background:" 
            + playercolours[j] + ";'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>");
    }
    $("#colourOption").val(colourIndex);
    
}

function updateSetupView(){ 
    updatePlayersView();
    if (playername == game.hostname) {
        for (var i = 1; i < players.length; i++) {
                $("#p" + (i+1) + "Kick").html("<button type='button' onclick='removePlayer(" 
                    + '"' +  $("#p" + (i+1) + "Name").text() + '"' + ")'>Kick</button>");
        }
    } else {
        //update game settings fields
        $("#map").html(map.mapname + " / max players:" + map.maxplayers);
        showMapPreview(map.mapname);
        $("#noPlayers").html(game.playerslimit);
        $("#turnTime").html(game.turntimeout);
    }
    // hide/show players
        var noPlayers = game.playerslimit;
        for (var i = 3; i <= noPlayers; i++) {
            $("#p" + i).show();
        }
        for (var j = parseInt(noPlayers); j < 6; j++) {
            $("#p" + (j+1)).hide();
        }
}

function showScoreBoard(scores) {
    $("#scoresTable").html("<tr><th>Rank</th><th>Username</th><th>Wins</th><th>Defeats</th></tr>");
    for (var i = 0; i<scores.length; i++){
        $('#scoresTable').append("<tr><td>" + (i+1) + "</td><td>"
                + scores[i].username + "</td><td>" + scores[i].wins 
                + "</td><td>" + scores[i].defeats + "</td></tr>");
    }
}

function blurBackgroundL() {
    $("html").css("background-image","url('img/bg_blur.jpg')");
    $("#chatScreen").css("opacity","0.3");
    $("#gameSelectionScreen").css("opacity","0.3");
    $("#onlinePlayers").css("opacity","0.3");
}

function restoreBackgroundL() {
    $("html").css("background-image","url('img/bg.jpg')");
    $("#chatScreen").css("opacity","1.0");
    $("#gameSelectionScreen").css("opacity","1.0");
    $("#onlinePlayers").css("opacity","1.0");
}

function showPopUpL(context) {
    if (!popup) {
        blurBackgroundL();
        $("#" + context).fadeIn();
        popup = true;
    }
}

function hidePopUpL(context) {
    restoreBackgroundL();
    $("#" + context).fadeOut();
    popup = false;
}

function displayPlayers(players){
    emptyContainer('onlinePlayersTable');
    $.each(players, function(i, field){
        $('#onlinePlayersTable').append("<tr><td>" + field.username 
                + "</td><td><img src='img/greendot.png' alt'online' /></td></tr>");        
        });
}

