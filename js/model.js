/*******************************************************************************
 *  model.js - encapsulates the client-side game logic and server communication.
 */


/*******************************************************************************
 * Global variables. Some of these are now unused, and most should probably
 * be changed into properties of the Model instance.
 */

function Map(name, width, height) {
    this.name = name;
    this.width = width;
    this.height = height;
}

//function Player(username, colour, team ,+units)

var playername;
var hostname;
var gamename;
var gameid;
var map; //of type Map
var lastPlayerListChange;

/* Array that stores the relevant modifier 
 *  for that unit-type on that terrain-type 
 * If an entry is undefined for a unit-type,
 *  then that unit-type cannot traverse that terrain-type. */
var movementTable; //[unit-type][terrain-type] = modifier

/* Array that stores the relevant modifier 
 *  for an attacking unit-type against a defending unit-type 
 * If an entry is undefined for a unit-type,
 *  then that unit-type cannot attack the other unit-type. */
var attackingTable; //[attacker][defender] = modifier

var unitTypes;
var terrainTypes;

var cells; //array of Cell objects of length width * height

var unitSelected = null;        // unit object
var currentReachableLocations = null;

// Specific utility
var path = null; //array of 2-length arrays
var movesLeft = -1;


/*******************************************************************************
 * new Model(gameID) - constructs a Model instance representing the client-side
 * state of the game with the given ID.
 */
function Model(gameID, serverURL) {
    // Inherit Trigger's constructor.
    Trigger.apply(this);
    

    this.gameID = gameID;
    this.serverURL = serverURL || 'gameServer.php';

    this.turn = 1;
    this.day = 1;
    //this.players

    // The following events have been agreed upon so far:
    //
    //  this.trigger('initGame', mapURL, playerNames);
    //  this.trigger('addUnit', {[x,y], type, owner, health, status, colour});
    //  this.trigger('setHealth', [x,y], newHealth);
    //  this.trigger('removeUnit', [x,y]);
    //  this.trigger('moveUnit', [[x1,y1],[x2,y2],...[xN,yN]]);
    //  this.trigger('changeTurnTo', player, turnTimeout);
    //  this.trigger('markCells', 'attack'|'move', [[x1,y1],[x2,y2],...[xN,yN]]);
    //  this.trigger('markPath', [[x1,y1],[x2,y2],...[xN,yN]]);
    //  this.trigger('selectUnit', [x,y]);
    //  this.trigger('setStatus', [x, y], 'tired' | 'normal');
    //  this.trigger('changeTurnTo', playernum, turnTimeout);
    //  this.trigger('changeDayTo', dayNum);
    //  this.trigger('scrollTo', [x, y]);
    //
    // More information can possibly be found in the handlers in gview.js.
}

// Inherit Trigger's prototype.
Model.prototype = Object.create(Trigger.prototype);

// Static resources.
Model.TERRAIN_TYPE_URL  = 'json/terrain_type.json';
Model.UNIT_TYPE_URL     = 'json/unit_type.json';
Model.MOVE_TABLE_URL    = 'json/move_table.json';
Model.ATTACK_TABLE_URL  = 'json/attack_table.json';

// Server API URLs.
Model.RESUME_URL    = '?function=resume';
Model.UPDATE_URL    = '?function=update';
Model.MOVE_URL      = '?function=move';
Model.END_TURN_URL  = '?function=endTurn';
Model.GAME_OVER_URL = '?function=gameover';
Model.PLAYER_DEFEATED_URL = '?function=suggestDefeat';

// Miscellaneous constants.
Model.UNIT_TYPE_NAMES = ['spearman', 'archer', 'lancer'];
Model.SERVER_UPDATE_MS = 1000;
Model.TERRAIN_LAYER_NAME = "logic";
Model.DATABASE_TRUE = '1';


/*******************************************************************************
 * model.startGame() - commences the game. Should only be called after the model
 * is registered with any interested objects, such as a view.
 */
Model.prototype.startGame = function () {
    var resumeData, mapData;
    $.when(
        this.callServer(Model.RESUME_URL).then($.proxy(function (resumeData_) {
            // The server has responded to the "resume" call.
            resumeData = resumeData_;

            map = resumeData.map;
            this.numPlayers = resumeData.players.length;
            this.players = new Array();
            $.each(resumeData.players, $.proxy(function(index, value){
                this.players[value.seqno] = value;
                this.players[value.seqno].alive = 
                    (value.alive === Model.DATABASE_TRUE);
                this.players[value.seqno].units = new Array();
            }, this) );            

            return $.getJSON(resumeData.map.mapname).then(function (mapData_) {
                mapData = mapData_;
            }, $.proxy(this.ajaxError, this, resumeData.map.mapname));
        }, this)),
        $.getJSON(Model.TERRAIN_TYPE_URL).then(
            loadTerrainTypeData,
            $.proxy(this.ajaxError, this, Model.TERRAIN_TYPE_URL)
        ),
        $.getJSON(Model.UNIT_TYPE_URL).then(
            loadUnitTypeData,
            $.proxy(this.ajaxError, this, Model.UNIT_TYPE_URL)
        ),
        $.getJSON(Model.MOVE_TABLE_URL).then(
            loadMovementTable,
            $.proxy(this.ajaxError, this, Model.MOVE_TABLE_URL)
        ),
        $.getJSON(Model.ATTACK_TABLE_URL).then(
            loadAttackTable,
            $.proxy(this.ajaxError, this, Model.ATTACK_TABLE_URL)
        )
    ).then($.proxy(function () {
        // All of the above requests have been successful,
        // and resumeData, mapData have been set.
        this.turnTimeout = resumeData.game.turntimeout;
        this.localPlayer = resumeData.game.localplayer;
        this.turn = resumeData.game.currentplayer;

        this.trigger('initGame',
            resumeData.map.mapname,
            //mapping function below returns p.username when it is not undefined
            $.map(this.players, function (p) { return [p && p.username]; }),
            this.localPlayer
        );
        loadMapData(mapData);
        loadUnitData(this, resumeData.units);

        var timeLeft;
        if(this.turnTimeout === 0){ timeLeft = 0; }
        else { timeLeft = resumeData.game.currenttimeleft; }
        this.changeTurnTo(resumeData.game.currentplayer, timeLeft);
        this.changeDayTo(resumeData.game.day);

        setInterval($.proxy(this.callUpdate, this), Model.SERVER_UPDATE_MS);
    }, this));
};

/*******************************************************************************
 * model.callServer(url, [data]) - initiates an AJAX request to the server-side
 * function represented by the given URL.
 *
 * `data' is an optional object containing the HTTP POST parameters to be
 * supplied to the server. It need not contain the gameID, as this will be
 * automatically inserted.
 *
 * Returns a Promise indicating when the operation is complete, and providing
 * the received JSON-decoded data to its done callbacks.
 */
Model.prototype.callServer = function (url, data) {
    url = this.serverURL + url;
    return $.ajax(url, {
        data: $.extend({ gameid: this.gameID }, data),
        dataType: 'json',
        type: 'POST'
    }).then(function (response) {
        if (typeof response === 'string' && response.toLowerCase() === 'failure')
            throw new Error('Server request failed: ' + url);
        else if (typeof response === 'string'
             && response.toLowerCase() !== 'success')
            throw new Error('Server request ' + url + ' failed: ' + response);
        else
            return response;
    }, $.proxy(this.ajaxError, this, url));
};

/*******************************************************************************
 * model.getJSON(url, data) - a wrapper for $.getJSON, with error reporting.
 */
Model.prototype.getJSON = function (url) {
    return $.getJSON.apply($, arguments)
           .error($.proxy(this.ajaxError, this, url));
};

/*******************************************************************************
 * model.ajaxError(...) - handle a failed AJAX request.
 */
Model.prototype.ajaxError = function () {
    throw new Error(Array.prototype.slice.call(arguments).toString());
};


/*******************************************************************************
 * model.changeTurnTo(playerNumber)
 */
Model.prototype.changeTurnTo = function (player, timeleft) {
    console.log("Timeout: " + timeleft);
    this.turn = player;
    if(timeleft === undefined){
        this.trigger('changeTurnTo', player, this.turnTimeout); }
    else { this.trigger('changeTurnTo', player, timeleft); }
};

/*******************************************************************************
* model.changeDayTo(day)
*/
Model.prototype.changeDayTo = function (day) {
    this.day = day;
    this.trigger('changeDayTo', day);
};

/*******************************************************************************
 * model.callUpdate() - call the server's "update" function to receive any new
 * events, dispatching them to handler methods beginning with 'h_'.
 */
Model.prototype.callUpdate = function () {
    this.callServer(Model.UPDATE_URL).then($.proxy(function (events) {
        $.each(events, $.proxy(function (index, tableEntry) {
            var event;
            if(typeof tableEntry.action !== 'undefined'){
                  event = JSON.parse(tableEntry.action); }
            else{ event = JSON.parse(tableEntry); }
            
            var handler = this['h_'+event.type];
            if (handler) handler.call(this, event);
            else {
                throw new Error(
                    'Unknown server event type: ' + event.type
                  + ' - server event: ' + event
                );
            }
        }, this));
    }, this));
};
Model.prototype.h_move = function (event) {
    // move: A unit moved along event.path, attacking event.target (if any).
    var startLoc = event.path[0], endLoc = event.path[event.path.length-1],
        sCell = getCell(startLoc[0], startLoc[1]), 
        eCell = getCell(endLoc[0], endLoc[1]);
    if (!sCell.unit){
        throw new Error(
        'Server move: there is no unit at the start, '+ sCell.toString());
    }
    if(eCell.unit !== null && eCell.unit !== undefined
    && eCell.toString() !== sCell.toString()){
        throw new Error(
        'Server move: there is already a unit at the end, '+ eCell.toString());
    } 

    var path = $.map(event.path, function(loc){
        return getCell(loc[0], loc[1]);
    });
    this.moveUnit(sCell, eCell, path);
    if (event.target) this.attackUnit(sCell.unit, event.target);
};
Model.prototype.h_setHealth = function (event) {
    // setHealth: the unit at event.target's health changed to event.health.
    var targetCell = getCell(event.target[0], event.target[1]);
    if (!targetCell.unit) {
        throw new Error(
        'Server setHealth: there is no unit at the target, '+event.target);
    }
    this.setHealth(targetCell, event.health);
};
Model.prototype.h_endTurn = function (event) {
    // endTurn: the current player's turn ended.
    this.doEndTurn(event.next);
};
Model.prototype.h_removePlayer = function (event) {
    //event.player which is an int
    if(event.player === undefined){
        throw new Error("Invalid input from server for removePlayer: " 
                        + event.toString());
    }
    this.removePlayer(event.player);
};
Model.prototype.h_gameOver = function (event) {
    //event.player which is an int
    this.gameover(event.players);
};

/*******************************************************************************
 * model.moveUnit(startCell, endCell, path) -
 * move a unit from `startCell' to `endCell', along the given path.
 */
Model.prototype.moveUnit = function (startCell, endCell, path) {
    if(startCell !== endCell) { this.performMove(startCell, endCell, path); }
    endCell.unit.state = "tired";
    this.trigger('setStatus', endCell.toCoord(), "tired");
};

/*******************************************************************************
 * model.attackUnit(attacker, target) - cause one unit to attack another;
 * does not move or set the health of any unit.
 */
Model.prototype.attackUnit = function (attacker, target) {
    //console.log('Model.prototype.attackUnit: not implemented.');
};

/*******************************************************************************
 * model.setHealth(unit, health) - set the health of `unit' to `health'.
 */
Model.prototype.setHealth = function (targetCell, health) {
    var unit = targetCell.unit;

    if(health === 0){ 
        unit.die(this, false);
    }
    else{
        unit.health = health;
        this.trigger('setHealth', targetCell.toCoord(), health);
        console.log("Setting health of " 
            + targetCell.toString() 
            + " to " 
            + unit.health);
    }
};

/*******************************************************************************
 * model.removePlayer(playerNum) - removes player in position 'playerNum'
 * from the game
 */
 Model.prototype.removePlayer = function (playerNum) {
    if(this.players[playerNum].units !== undefined){
        while(this.players[playerNum].units.length > 0){
            var unit = this.players[playerNum].units[0];
            unit.die(this, true);
        }
        this.players[playerNum].alive = false;
    }
    else if(this.players[playerNum] === undefined){
        throw new Error("Player " + playerNum + "does not exist in the model");
    }

    if(playerNum === this.localPlayer){
        model.trigger('defeat');
    }

    //check if game over
    for(var i; i < this.numPlayers; i++){
        if(i !== this.localPlayer
        && players[i].alive){
            return;
        }
    }
    //if no other players
    this.checkGameOver(); //check with server
 };

 Model.prototype.checkGameOver = function() {
    this.callServer(Model.GAME_OVER_URL
        ).then($.proxy(

        function (result) { 
            if(result === "success"){
                //this.gameover();
            }
            else { 
                throw new Error(
                    "Unexpected gameover() server response: " + check(result)); 
            }

        }, this), $.proxy(this.ajaxError, this, Model.MOVE_URL));    
 };

 Model.prototype.gameover = function(victors) {
    //console.log("Victors are: " + victors.toString());
    //alert("Game Over");
    if($.inArray(this.localPlayer, victors) > -1){
        this.trigger('victory'); //defeat already triggered
    }
    this.trigger('gameOver', victors);
 };

/**
 * Expects a list of Unit-Type class objects
 */
function loadTerrainTypeData(terrainTypeList){
    terrainTypes = new Array(terrainTypeList.length);

    $.each(terrainTypeList, function(index, value){
        terrainTypes[index] 
            = new TerrainType(value.defence);
    });
}

/**
 * Expects a list of Unit-Type class objects
 */
function loadUnitTypeData(unitTypeList){
    unitTypes = new Array(unitTypeList.length);

    $.each(unitTypeList, function(index, value){
        unitTypes[index] 
            = new UnitType(value.moveAllowance, value.PAMaxDist, value.PAMinDist);
    });
}

function loadMovementTable(moveTable){
    movementTable = new Array();

    $.each(moveTable, function(index, value){
        if(movementTable[value.unitType] === undefined){
            movementTable[value.unitType] = new Array();
        }
        movementTable[value.unitType][value.terrainType] = value.modifier;
    });
}

function loadAttackTable(attackTable){
    attackingTable = new Array();

    $.each(attackTable, function(index, value){
        if(attackingTable[value.attacker] === undefined){
            attackingTable[value.attacker] = new Array();
        }
        attackingTable[value.attacker][value.defender] = value.modifier;
    });
}

function loadMapData(terrainJSON){
    var layerNum;
    $.each(terrainJSON.layers, function(index, value){

        if(value.name === Model.TERRAIN_LAYER_NAME){
            layerNum = index;
            return false; //exit loop
        }
        else { return true; } //continue
    });
    var terrain = terrainJSON.layers[layerNum];
    if(terrain.width != map.width || terrain.height != map.height){
        throw new Error("map dimensions != JSON terrain dimensions");
        return;
    }

    cells = new Array(map.width * map.height);
    var tiles = terrain.data;
    $.each(tiles, function(index, value){
        var xloc = index % map.width;
        var yloc = Math.floor(index / map.width);
        cells[index] = new Cell(xloc,yloc,value-1,null);
        //$("#results").append("<p>Added cell at " + xloc + "," + yloc + "</p>");
    });
}

function loadUnitData(model, unitList){
    $.each(unitList, function(index, value){
        var unit = new Unit(value.unitType, value.owner, 
                            value.location[0], value.location[1], 
                            value.state, value.health); 
        cells[value.location[1] * map.width + value.location[0]].unit = unit;
        model.players[value.owner].units.push(unit);

        // for view
        unit.type = Model.UNIT_TYPE_NAMES[unit.unitType];
        unit.colour = model.players[unit.owner].colour;
        unit.location = [unit.xloc, unit.yloc];
        unit.status = unit.state;

        model.trigger('addUnit', unit);
        //$("#results").append("<p>Added unit at " + value.xloc + "," + value.yloc + "</p>");
    });
}


/*******************************************************************************
 *  (Public) model.select([x,y]) - try to select a cell by e.g. clicking it.
 */
Model.prototype.select = function (location) {
    var x = location[0];
    var y = location[1];
    var cell = getCell(x,y);
    
    if( !(
       unitSelected === null
    && x >= 0 && x < map.width
    && y >= 0 && y < map.height
    && cell.unit !== null
    && cell.unit.owner === this.turn
    && cell.unit.owner === this.localPlayer
    && cell.unit.state !== "tired"
    ) ) {
        return;        
    }

    unitSelected = cell.unit;
    this.trigger('selectUnit', location);

    currentReachableLocations = unitSelected.reachableLocations();
    this.trigger('markCells','move', currentReachableLocations);

    model.markAttackable();
};


/******************************************************************************
 *  (Public) model.deselect() - cancel the current selection by e.g. clicking
 *  outside of the movable area.
 */
Model.prototype.deselect = function () {
    unitSelected = null;
    this.trigger('selectUnit');

    currentReachableLocations = null;
    this.trigger('markCells', 'move');
    this.trigger('markCells', 'attack');

    path = null;
    this.trigger('markPath');
};


/******************************************************************************
 *  (Public) model.checkRange([x,y]) - query the move range of a unit by e.g.
 *  holding the mouse button over it.
 */
Model.prototype.checkRange = function (location) {
    var cell = getCell(location[0], location[1]);
    this.trigger('markCells', 'attack', cell.attackableLocations('primary'));
};


/******************************************************************************
 *  (Public) model.addWaypoint([x,y]) - try to extend the current path (if any)
 *  to the given cell by e.g. clicking and dragging into this cell.
 */
Model.prototype.addWaypoint = function (location) {
    var cell = getCell(location[0], location[1]);
    var model = this;
    if( !(
       unitSelected !== null
    && cell.xloc >= 0 && cell.xloc < map.width
    && cell.yloc >= 0 && cell.yloc < map.height
    && cell.validTerrainFor(unitSelected)
    && currentReachableLocations != null
    && $.inArray(cell, currentReachableLocations) > (-1)
    ) ) {
        throw new Error("Attempted to add a waypoint to an invalid location.");
        return;
    }

    if(path !== null 
    && $.inArray(cell, path) > (-1)){
        // rewind path
        movesLeft += path.length - ($.inArray(cell, path) + 1);
        path.splice($.inArray(cell, path) + 1);
    }
    else if(path !== null
    && movesLeft > 0
    && $.inArray(cell, path[path.length - 1].neighbours()) > -1
    && unitSelected.pathCost([path[path.length - 1], cell]) <= movesLeft){
        // specify individal cells of path
        path.push(cell);
        movesLeft -= unitSelected.pathCost([path[path.length - 1], cell]);
    }
    else {
        unitSelected.newPathTo(cell, this);
    }

    model.trigger('markPath', pathToCoordList(path));
    model.markAttackable();
}; 

/******************************************************************************
 *  (Public) model.wait() - try to wait at the current target location (if any)
 */
Model.prototype.wait = function(){
    if( !(
       unitSelected !== null
    && path !== null
    ) ){
        throw new Error("Wait attempt failed");
        return;
    }
    //send current path to server to check
    var sentPath = pathToCoordList(path);

    this.callServer(Model.MOVE_URL, { path: JSON.stringify(sentPath)}
        ).then($.proxy(

        function (result) { 
            if(result === "success"){
                //if valid, move unit & set tired
                this.performMove();

                var dest = path[path.length - 1];
                var newx = dest.xloc; var newy = dest.yloc;
    
                unitSelected.state = "tired";
                this.trigger('setStatus', [newx, newy], "tired");
                this.deselect();
                this.checkEndTurn();
            }
            else { 
                throw new Error(
                    "Unexpected wait() server response: " + check(result)); 
            }

        }, this), $.proxy(this.ajaxError, this, Model.MOVE_URL));    
};

/******************************************************************************
 *  (Public) model.attack([x,y]) - attempt to have the currently selected unit
 *  attack a target at location [x,y]
 */
Model.prototype.attack = function(location){
    var target = getCell(location[0], location[1]);
    if( !(
       target !== undefined
    && target.unit !== null && target.unit !== undefined
    && unitSelected.isEnemy(target.unit, this)
    && unitSelected !== null
    && unitSelected.state !== "tired"
    && ((path !== null 
        && unitSelected.attackableTargets("primary", this,
                                          path[path.length - 1]).length > 0
        && $.inArray(target,
                     unitSelected.attackableTargets("primary", this,
                                                     path[path.length - 1])
                     ) !== -1)
        ||  
        (path === null 
        && unitSelected.attackableTargets("primary", this).length > 0
        && $.inArray(target,
                     unitSelected.attackableTargets("primary", this)) !== -1) )
    ) ){
        throw new Error("Attack attempt with target " 
                        + cellToString(target) 
                        +" failed");
        return;
    }

    //send current path to server to check
    var sentPath;
    if(path !== null){ sentPath = pathToCoordList(path); }
    else { sentPath = [[unitSelected.xloc, unitSelected.yloc]]; }

    this.callServer(Model.MOVE_URL, {
        path: JSON.stringify(sentPath),
        target: JSON.stringify(location)
    }).then($.proxy(
        function (result) { 
            if(result === "success"){
                // if valid, move unit (if moving)
                if(path !== null){ this.performMove(); }

                if(unitSelected !== undefined){
                    unitSelected.state = "tired";
                    this.trigger('setStatus', 
                                 [unitSelected.xloc, unitSelected.yloc], 
                                 "tired");
                }
                this.deselect();
                this.checkEndTurn();
            }
            else { 
                throw new Error(
                    "Unexpected attack() server response: " + check(result)); 
            }

        }, this), $.proxy(this.ajaxError, this, Model.MOVE_URL));      
};

/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
// Private Sub-functions


/******************************************************************************
 *  The view ends the current player's turn
 */
Model.prototype.endTurn = function () {
    this.callServer(Model.END_TURN_URL).then($.proxy(function (result) {
            if(result === "success"){ this.doEndTurn(); }
            else { 
                throw new Error(
                    "Unexpected endTurn server response: " + check(result)); 
            }
        }, this), $.proxy(this.ajaxError, this, Model.END_TURN_URL));
};

Model.prototype.doEndTurn = function (nextPlayer) {
    var oldturn = this.turn;
    var iterations = 0; //prevent infinite loop on error
    if(nextPlayer === undefined){
        do {
            iterations++;
            this.turn = (this.turn /* -1 +1*/ % this.numPlayers) + 1;
        } while(!this.players[this.turn].alive
              && iterations < this.numPlayers)
    }
    else { this.turn = nextPlayer; }

    if (iterations >= this.numPlayers)
        throw new Error("No alive players");

    this.trigger('changeTurnTo', this.turn, this.turnTimeout);
    console.log("Changing turn to " + this.turn);

    if(this.turn < oldturn){ this.changeDayTo(this.day + 1); }

    this.deselect();
    this.startTurn();
};

Model.prototype.checkEndTurn = function () {
    var hasNontiredUnit = false;
    $.each(this.players[this.turn].units, function(index, unit){
        if(unit.state === 'normal'){
            hasNontiredUnit = true;
            return false; //break loop
        }
    });

    if(!hasNontiredUnit){ this.endTurn(); }
};


/******************************************************************************
 *  starts the current player's turn
 */
Model.prototype.startTurn = function () {
    var model = this;
    if( !(
          this.players[this.turn] !== undefined
       && this.players[this.turn].alive
       && this.players[this.turn].units !== undefined
       && this.players[this.turn].units.length > 0
       )) {
        throw new Error(
            "Cannot start player " + this.turn + "\'s turn.\n"
          + 'this.players is '
          + (typeof this.players === 'undefined' ? 'NOT ' : '')
          + "defined.\n"
          + 'this.players[this.turn] is '
          + (typeof this.players[this.turn] === 'undefined' ? 'NOT ' : '')
          + "defined.\n"
          + 'Player ' + this.turn + ' is '
          +  (this.players[this.turn].alive ? 'NOT ' : '')
          + "alive.\n"
          + 'Player ' + this.turn + ' has '
          + (typeof this.players[this.turn].units === 'undefined' ? 'NO' : 'a')
          + " units array.\n"
          + 'Player ' + this.turn + ' has '
          + (this.players[this.turn].units.length > 0 ? 'NO ' : '')
          + "units.\n"
        );
        return;
    }

    var arbitraryUnit = this.players[this.turn].units[0];
    var x = arbitraryUnit.xloc; var y = arbitraryUnit.yloc;
    model.trigger('focus', [x, y]);

    $.each(this.players[this.turn].units, function(index, value){
        value.state = "normal";
        model.trigger('setStatus', [value.xloc, value.yloc], "normal");
    });
};

/******************************************************************************
 *  Performs the move currently stored, or that specified by the arguments,
 *  for the unit in begincell
 */
Model.prototype.performMove = function (begincell, endcell, pathgiven) {

    if(begincell !== undefined 
    && endcell !== undefined 
    && pathgiven !== undefined
    &&    (begincell !== pathgiven[0]
        || endcell !== pathgiven[pathgiven.length - 1])
    ) {
        throw new Error("Move from "
                        + cellToString(begincell)
                        + " to "
                        + cellToString(endcell)
                        + " failed.");
        return;
    }

    var startCell;
    var movingUnit;
    var destCell;
    var pathToUse;

    if(begincell === undefined) { 
        startCell = getCell(unitSelected.xloc, unitSelected.yloc); }
    else { startCell = begincell; }
    movingUnit = startCell.unit;

    if(endcell === undefined)   { destCell = path[path.length - 1]; }
    else { destCell = endcell; }

    if(pathgiven === undefined) { pathToUse = path; }
    else { pathToUse = pathgiven; }

    startCell.unit    = null;
    destCell.unit     = movingUnit;
    movingUnit.xloc   = destCell.xloc; movingUnit.yloc = destCell.yloc;
    this.trigger('moveUnit', pathToCoordList(pathToUse));
};

Model.prototype.markAttackable = function () {
    if(path !== null){
        this.trigger('markCells','attack', 
                     unitSelected.attackableTargets("primary", this,
                                                     path[path.length - 1]));
    }
    else {
        this.trigger('markCells','attack', 
                     unitSelected.attackableTargets("primary", this));
    }
};


// ------ My classes ------

function TerrainType(defence){
    this.defence = defence
}

function Cell(xloc, yloc, terrainType, unit){
    this.xloc = xloc;
    this.yloc = yloc;
    this.terrainType = terrainType;
    this.unit = unit;
}

function UnitType(moveAllowance, maxAttack, minAttack){
    this.moveAllowance = moveAllowance;

    // up to and including
    this.primAttackMax = maxAttack; 
    // attacks have to be strictly greater than this distance
    this.primAttackMin = minAttack; 
}


function Unit(unitType, owner, xloc, yloc, state, health){
    this.unitType = unitType;
    this.owner = owner;
    this.xloc = xloc;
    this.yloc = yloc;
    this.state = state;
    this.health = health;
}

Cell.prototype.nextTo = function(direction){
    //modifier for x & y
    var xmod; 
    var ymod;
    switch(direction) {
        case "north": xmod =  0; ymod =  1; break;
        case "east" : xmod =  1; ymod =  0; break;
        case "south": xmod =  0; ymod = -1; break;
        case "west" : xmod = -1; ymod =  0; break;
    }
    return getCell(this.xloc + xmod, this.yloc + ymod);
};

Cell.prototype.neighbours = function() {
    var neighbourArray = new Array();
    if(this.xloc === 0){
        neighbourArray.push(this.nextTo("east"));
    }
    else if(this.xloc === map.width - 1){
        neighbourArray.push(this.nextTo("west"));
    }
    else{
        neighbourArray.push(this.nextTo("east"));
        neighbourArray.push(this.nextTo("west"));
    }

    if(this.yloc === 0){
        neighbourArray.push(this.nextTo("north"));
    }
    else if(this.yloc === map.height - 1){
        neighbourArray.push(this.nextTo("south"));
    }
    else{
        neighbourArray.push(this.nextTo("north"));
        neighbourArray.push(this.nextTo("south"));
    }

    return neighbourArray;
};

Cell.prototype.validTerrainFor = function(unit){
    return movementTable[unit.unitType][this.terrainType] != null;
};

Cell.prototype.reachableLocations = function() {
    var locs = new Array();
    locs.push(this);
    var unitType = this.unit.unitType
    var cell = this;
    var maxDepth = unitTypes[unitType].moveAllowance;
    //$("#results").append("<p>maxDepth: " + maxDepth + "</p>");

    $.each(this.neighbours(), function(index, value){
        DLS(value, unitType, 0, maxDepth, locs, "movement", cell);
    });
    return locs;
};

Cell.prototype.attackableLocations = function(attackType, speculativeUnit) {
    // set difference:
    // DLS(max distance) - DLS(min distance);
    var maxLocs = new Array();
    var cell = this;
    var unitType;
    if(speculativeUnit === undefined){
        unitType = this.unit.unitType;
    } else {
        unitType = speculativeUnit.unitType;
    }

    var maxDepth;
    if(attackType === "primary"){
        maxDepth = unitTypes[unitType].primAttackMax;
    }
    $.each(this.neighbours(), function(index, value){
        DLS(value, unitType, 0, maxDepth, maxLocs, "attack", cell);
    });

    var minLocs = new Array();
    if(attackType === "primary"){
        maxDepth = unitTypes[unitType].primAttackMin;
    }
    $.each(this.neighbours(), function(index, value){
        DLS(value, unitType, 0, maxDepth, minLocs, "attack", cell);
    });

    var diff = jQuery.grep(maxLocs, function(element, index){
        return jQuery.inArray(element, minLocs) === -1;
    });
   
    return diff;
};

function DLS(curcell, unitType, curdepth, maxdepth, returnArray, searchType, cell){
    var addedDepth;
    if(searchType === "movement"){
        if(movementTable[unitType] !== undefined 
        && movementTable[unitType][curcell.terrainType] !== undefined
        && curcell.unit === null){
            addedDepth = movementTable[unitType][curcell.terrainType];
        }
        else {
            return; //cannot path; do not add to available cells
        }
    }
    else if(searchType === "attack"){
        if(curcell.unit === undefined
        || curcell !== cell){
            addedDepth = 1;
        }
        else {
            return; //cannot attack; do not add to available cells
        }
    }
    else {
        return; //incorrect DLS type
    }

    var newdepth = curdepth + addedDepth;
    if(newdepth > maxdepth){
        return;
    }
    else
    {
        if(!($.inArray(curcell, returnArray) > -1)){
            returnArray.push(curcell);
        }

        $.each(curcell.neighbours(), function(index, value){
            DLS(value, unitType, newdepth, maxdepth, returnArray, searchType, cell);
        });
    }
}

Cell.prototype.toCoord = function(){
    return [this.xloc, this.yloc];
};

Cell.prototype.toString = function(){
    return "[" + this.xloc + "," + this.yloc + "]";
};

Unit.prototype.moveAllowance = function(){
    return unitTypes[this.unitType].moveAllowance;
};

/* Expects a list of cells. 
 * Format: current cell, cell1, cell2 ..., goalcell */
Unit.prototype.pathCost = function(path){
    var totalDistance = 0;
    var unitType = this.unitType;
    var unit = this;
    $.each(path, function(index, value){
        if(movementTable[unitType] !== undefined 
        && movementTable[unitType][value.terrainType] !== undefined
        && (value.unit === null ||    (unit.xloc === value.xloc 
                                    && unit.yloc === value.yloc))) {
            totalDistance += movementTable[unitType][value.terrainType];
        }
        else {
            totalDistance = Infinity;
            return false; //break
        }
    });

    if(totalDistance === Infinity){
        return totalDistance;
    }
    else {
        return totalDistance - 
        movementTable[unitType][path[0].terrainType];
    }
};

// takes cells
function manhattanEstimate(from, to){
    return Math.abs(to.xloc - from.xloc) + Math.abs(to.yloc - from.yloc);
}

/** 
 * A* implementation
 * - Manhatten heuristic
 * 
 * broad structure copied from http://en.wikipedia.org/wiki/A*_search_algorithm
 */ 
Unit.prototype.shortestPathTo = function(goal, start){
    var closedSet = new Array();
    var cameFrom = {}; //path map
    var openSet = new Array();
    var unit = this;
    var startCell;
    if(start === undefined){ startCell = getCell(this.xloc, this.yloc); } 
        else { startCell = start; }
    openSet.push(startCell);

    var knownDistanceTo = {}; //g score
    knownDistanceTo[cellToString(startCell)] = 0;
    var estimateToGoalFrom = {}; //f score
    estimateToGoalFrom[cellToString(startCell)] = 
        knownDistanceTo[cellToString(startCell)] + manhattanEstimate(startCell, goal);

    while(openSet.length > 0){
        var currentCell = lowestEstimatedDistance(openSet, estimateToGoalFrom);
        var index = $.inArray(currentCell, openSet);
        if(currentCell === goal){
            return reconstructPath(cameFrom, goal);
        }

        openSet.splice(index, 1);
        closedSet.push(currentCell);
        var neighbours = currentCell.neighbours();

        $.each(neighbours, function(index, value){

            var realDistance;
            var pathCost = unit.pathCost([currentCell, value]);
            if (pathCost === Infinity){
                realDistance = Infinity;
            }
            else {
                realDistance = knownDistanceTo[cellToString(currentCell)] 
                             + pathCost;
            }

            if(($.inArray(value, closedSet) > -1) 
               && knownDistanceTo[cellToString(value)] !== undefined
               && realDistance >= knownDistanceTo[cellToString(value)]){
                return true; //continue
            }
            else if(!($.inArray(value, openSet) > -1) 
               ||  ( knownDistanceTo[cellToString(value)] !== undefined
                   && realDistance < knownDistanceTo[cellToString(value)])){

                cameFrom[cellToString(value)] = cellToString(currentCell);
                knownDistanceTo[cellToString(value)] = realDistance;
                estimateToGoalFrom[cellToString(value)] = 
                    realDistance + manhattanEstimate(value, goal);

                if(!($.inArray(value, openSet) > -1)){
                    openSet.push(value);
                }
            }
        });
    }
    //fail
    return null;
};

function lowestEstimatedDistance(cellList, estimatorMap){
    var lowestValue = Infinity;
    var lowestCell = null;
    $.each(cellList, function(index, value){
        if(estimatorMap[cellToString(value)] < lowestValue){
            lowestValue = estimatorMap[cellToString(value)];
            lowestCell = value;
        }
    });
    return lowestCell;
}

function reconstructPath(routeMap, currentNode){
    var curNode = currentNode;
    var path = new Array();
    while(routeMap[cellToString(curNode)] !== undefined){
        path.push(curNode);
        curNode = stringToCell(routeMap[cellToString(curNode)]);
    }
    path.push(curNode);
    return path.reverse();
}

Unit.prototype.newPathTo = function(cell, model){
    path = this.shortestPathTo(cell);
    var uma = this.moveAllowance();   
    movesLeft = uma - this.pathCost(path); //uma has to be >=path.length
};


Unit.prototype.reachableLocations = function() {
    var cell = getCell(this.xloc, this.yloc);
    movesLeft = this.moveAllowance();
    return cell.reachableLocations();
};

Unit.prototype.attackableLocations = function(attackType, originCell) {
    if(originCell === undefined) {
        return getCell(this.xloc, this.yloc).attackableLocations(attackType);
    } else {
        return originCell.attackableLocations(attackType, this);
    }
};

Unit.prototype.attackableTargets = function(attackType, model, originCell){
    var cell;
    if(originCell === undefined) { 
        cell = getCell(this.xloc, this.yloc);
    } else {
        cell = originCell;
    }

    var locs = cell.attackableLocations(attackType, this);

    var unit = this;
    var targets = jQuery.grep(locs, function(element, index){ 
        var curcell = element;

        return curcell.unit !== null 
        && unit.isEnemy(curcell.unit, model)
        && attackingTable[unit.unitType] !== undefined
        && attackingTable[unit.unitType][curcell.unit.unitType] !== undefined; 
    });

    return targets;
};

Unit.prototype.isEnemy = function(unit, model){
    // a person is always on same team as themself
    if(model.players === undefined){
        throw new Error("List \'players\' undefined");
    }
    else if(model.players[this.owner] === undefined){
        throw new Error("Players entry " + this.owner + " is undefined");
    }
    else if(model.players[this.owner].team === undefined){
        throw new Error("Player " + this.owner + "'s team is undefined");
    }
    
    return (model.players[this.owner].team 
       !== model.players[unit.owner].team); 
};

Unit.prototype.damageTo = function(targetCell){
    //TODO: balance/modify
    return this.health;
};

Unit.prototype.die = function(model, alreadyDefeated){
    if(model === undefined){
        throw new Error("Unit cannot die; model undefined.");
    }

    var cell = getCell(this.xloc, this.yloc);
    cell.unit = null;
    this.xloc = null;
    this.yloc = null;
    model.trigger('removeUnit', cell.toCoord());

    var unitsArray = model.players[this.owner].units;
    unitsArray.splice($.inArray(this, unitsArray), 1);

    if(unitsArray.length === 0 && alreadyDefeated !== true) { 
        model.removePlayer(this.owner); 
    }
};

//utility
function getCell(x, y){
    return cells[y * map.width + x];
}

function pathToCoordList(path){
    var coordList = new Array();
     $.each(path, function(index, value){
        var tempArray = new Array();
        tempArray.push(value.xloc);
        tempArray.push(value.yloc);

        coordList.push(tempArray);
    });
    return coordList;
}


function check(object){
    var string;
    $.each(object, function(key, value){
        string += ("Field:" + key + "\t");
    });
    return string + "\n";
}

function listCells(cellList){
    $("#results").append("<p>");
    $.each(cellList, function(index, value){
        $("#results").append(" [" + value.xloc + "," + value.yloc + "]");
    });
    $("#results").append("</p>");
}

function cellToString(cell){
    if(cell !== undefined){
        return "[" + cell.xloc + "," + cell.yloc + "]";
    }
    else {
        return "undefined";
    }
}

function stringToCell(cellstring){
    var commaindex = cellstring.indexOf(',');
    var xloc = parseInt(cellstring.substring(1,
                                             commaindex));

    var yloc = parseInt(cellstring.substring(commaindex + 1, 
                                             cellstring.lastIndexOf(']') ));
    return getCell(xloc, yloc);
}

function setNotEmpty(object){
    var returnval = false;
    $.each(object, function(index, value){
        //cannot break with return true, as return bool is a control for $.each
        returnval = true;
    });
    return returnval;
}
