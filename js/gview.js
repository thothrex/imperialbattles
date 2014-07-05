/*******************************************************************************
 * gview.js - a graphical view/controller of a game Model, presented using
 * gameQuery.
 */


/*******************************************************************************
 * GView(mode, root, [options]) - a new GView instance listening for events from
 * the given Model instance, and using the given `root', which may be any value
 * for which `$(root)' is defined by jQuery, as the HTML element containing the
 * game's content. `options`, if specified, may contain additional options to be
 * passed to $.playground().
 */
function GView(model, root, options) {
    // This constructor may be called without using the `new' keyword.
    if (this === (function () { return this; })())
        return GView.apply(Object.create(GView.prototype), arguments);

    options = $.extend({
        width: 640,
        height: 470
    }, options);

    // Set an event handler for each member whose name starts with "h_".
    var this_ = this;
    $.each(this, $.proxy(function (name, value) {
        var match = /^h_(.*)$/.exec(name);
        if (match) model.listen(match[1], $.proxy(function () {
            if (options.debug) GView.logEvent(match[1], arguments);
            value.apply(this, arguments);
        }, this));
    }, this));

    this.model = model;
    this.state = 'new';
    this.size = [options.width, options.height];
    this.root = $(root).playground(options);

    return this;
}

/*******************************************************************************
 * GView.logEvent(eventName, arguments) - log to the console a representation
 * of the specified event, for debugging purposes.
 */
GView.logEvent = function (event, args) {
    function show(x) {
        if (x instanceof Array)
            return '['+$.map(x, show)+']';
        else if (x && x.constructor && x.constructor.name === 'Cell'
                 && 'xloc' in x && 'yloc' in x)
            return '<'+x.xloc+','+x.yloc+'>';
        else
            return [x].toString();
    }
    console.log(event+'('+$.map(args, show)+')');
};

/*******************************************************************************
 * GView.CLICK_MS - the duration in milliseconds for which the mouse button must
 * be held down in order to invoke the "checkRange" event.
 */
GView.CLICK_MS = 400;

/*******************************************************************************
 * When a unit is damaged, a caption giving the amount of damage floats above it
 * for DAMAGE_MS millseconds, rising through DAMAGE_PX pixels.
 */
GView.DAMAGE_MS = 1000;
GView.DAMAGE_PX = 32;

$(function () {
    /***************************************************************************
     * GView.OVERLAY - a multi-animation, with entry GView.OVERLAY_INDEX[name]
     * giving the tile overlay graphic with the given name.
     */
    GView.OVERLAY = Animation({
        url:        'img/overlay.png',
        direction:  'vertical',
        multi:      true,
        frames:     1,
        distance:   32,
        size:       [32, 32]
    });
    GView.OVERLAY_INDEX = {
        'moveable':     0,
        'attackable':   1,
        'path':         2,
        'cursor':       3,
        'selected':     4,
        'attacked':     5
    };

    /***************************************************************************
     * GView.STATUS - a multi-animation, with entry GView.STATUS_INDEX[status]
     * giving a unit decoration representing that unit status.
     */
    GView.STATUS = Animation({
        url:        'img/overlay.png',
        offset:     [192,0],
        size:       [8,8],
        frames:     1,
        direction: 'horizontal',
        multi:      true,
        distance:   8
    });
    GView.STATUS_INDEX = {
        'normal':   1,
        'tired':    2
    };
});

/*******************************************************************************
 * (Private) view.promise - an object satisfying jQuery's Promise interface,
 * which represents the (possibly empty) queue of pending asynchronous tasks.
 * GView.prototype.promise is an immutable Promise which is permanently in the
 * "resolved" state, and is the initial promise of all newly created views.
 */
GView.prototype.promise = $.Deferred().resolve().promise();

/*******************************************************************************
 *  players = [_, p1Name, p2Name, ... pNName]
 */
GView.prototype.h_initGame = function (mapURL, players, localPlayer) {
    this.promise = this.promise.then($.proxy(function () {
        this.players = players.slice(1);
        this.localPlayer = localPlayer;
        this.readOnly = false;

        // Load map data.
        if (this.state != 'new') throw new Error(
            'initGame: this game has already been initialised.');
        this.state = 'init';
        return $.getJSON(mapURL);
    }, this)).then($.proxy(function (mapData) {
        // Initialise map container.
        this.mapGroup = this.root.addGroup('mapGroup');

        // Initialise tilemap.
        this.tilemaps = [];
        this.tilemap = Tilemap(mapData, mapURL, 'terrain', this.mapGroup);
        this.updateTilemaps();
        this.mapGroup.scroll($.proxy(this.refreshTilemaps, this));

        // Initialise unit underlay.
        this.underlay = this.mapGroup.addGroup('underlay');

        // Initialise selection.
        this.selectedUnit = null;

        // Initialise units.
        this.units = {};
        this.unitLayer = this.mapGroup.addGroup('unitLayer');

        // Initialise cursor.
        this.cursorSprite = this.mapGroup
            .addSprite('cursor')
            .find('#cursor')
            .css('visibility', 'hidden')
            .setAnimation(GView.OVERLAY)
            .setAnimation(GView.OVERLAY_INDEX['cursor'])
            .wh(GView.OVERLAY.size[0], GView.OVERLAY.size[1]);

        // Initialise mouse control.
        this.mouseIsDown = false;
        this.mouseTile = [-1, -1];
        this.clickTimeout = null;
        this.moveableCells = null;
        this.attackableCells = {};
        this.attackedCell = null;
        this.mapGroup.addGroup('topLayer')
            .wh(this.tilemap.pxSize[0], this.tilemap.pxSize[1])
            .mousemove($.proxy(this.mouseMove, this))
            .mouseenter($.proxy(this.mouseEnter, this))
            .mouseleave($.proxy(this.mouseLeave, this))
            .mousedown($.proxy(this.mouseDown, this))
            .mouseup($.proxy(this.mouseUp, this));

        // Initialise game status indicator.
        $('<div>')
            .attr('id', 'statusIndicator')
            .addClass('hud')
            .appendTo(this.root);

        // Initialise turn time indicator.
        $('<div>Time Left: <span id="timeLeft"></span></div>')
            .attr('id', 'timeIndicator')
            .addClass('hud')
            .appendTo(this.root);
        this.timeInterval = null;

        // Initialise player turn indicator.
        $('<div>Current Player: <span id="whoseTurn"></span></div>')
            .attr('id', 'turnIndicator')
            .addClass('hud')
            .appendTo(this.root);

        // Initialise tile index indicator.
        $('<div id="mouseTile"></div>')
            .addClass('hud')
            .appendTo(this.root);

        // Initialise current day indicator.
        $('<div>Day <span id="whichDay"></span></div>')
            .attr('id', 'dayIndicator')
            .addClass('hud')
            .css('visibility', 'hidden')
            .appendTo(this.root);

        // Initialise control buttons.
        $('<div id="hudButtons">')
            .addClass('hud hudButtons')
            .append(
                $('<button id="waitButton">Move</button>')
                .attr('disabled', true)
                .click($.proxy(this.waitClick, this))
            )
            .append(
                $('<button id="attackButton">Attack</button>')
                .attr('disabled', true)
                .click($.proxy(this.attackClick, this))
            )
            .append(
                $('<button id="endTurnButton">End Turn</button>')
                .attr('disabled', true)
                .click($.proxy(this.endTurnClick, this))
            )
            .appendTo(this.root);

        // Commence user interaction.
        this.root.startGame();
        this.mapGroup.scrollLeft(0).scrollTop(0);
    }, this), function (xhr, status) {
        throw new Error(status + ': ' + mapURL);
    });
};

/*******************************************************************************
 *  unitData = {
 *      location:   [x, y],
 *      type:       string,
 *      owner:      int,
 *      health:     int,
 *      status:     string?
 *  }
 */
GView.prototype.h_addUnit = function (unitData) {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('addUnit: this game has not been initialised.');

        GView.Unit(unitData, this);
    }, this));
};

/*******************************************************************************
 */
GView.prototype.h_removeUnit = function (location) {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('removeUnit: this game has not been initialised.');
        if (!(location in this.units)) throw new Error(
            'removeUnit: there is no unit at '+location+'.');

        return this.units[location].remove();
    }, this));
};

/*******************************************************************************
 */
GView.prototype.h_moveUnit = function (path) {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('moveUnit: this game has not been initialised.');

        return this.units[path[0]].move(path);
    }, this));
};

/*******************************************************************************
 *  status = 'normal' | 'tired'
 */
GView.prototype.h_setStatus = function (location, status) {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('setStatus: this game has not been initialised.');

        return this.units[location].setStatus(status);
    }, this));
};

/*******************************************************************************
 */
GView.prototype.h_changeTurnTo = function (player, turnTimeout) {
    setInterval(function (interval) {
        if (turnTimeout > 0) --turnTimeout;
        else clearInterval(interval);
    }, 1000);

    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init') throw new Error(
            'changeTurnTo: this game has not been initialised.');
        if (player < 1 || player > this.players.length)
            throw new Error('changeTurnTo: player '+player+' does not exist.');

        // Update "Time Left" indicator.
        if (this.timeInterval != null) clearInterval(this.timeInterval);
        this.timeInterval = setInterval($.proxy(function () {
            this.displayTime(turnTimeout);
        }, this), 1000);
        this.displayTime(turnTimeout);

        // Update "End Turn" button.
        var isLocalPlayer = player == this.localPlayer;
        this.root.find('#endTurnButton').attr('disabled', !isLocalPlayer);

        // Update current player indicator.
        var playerName = this.players[player-1];
        this.root.find('#turnIndicator')
            .removeClass(isLocalPlayer ? 'otherTurn' : 'yourTurn')
            .addClass(isLocalPlayer ? 'yourTurn' : 'otherTurn')
            .find('#whoseTurn').text(playerName);

        // Update units.
        $.each(this.units, function (index, unit) {
            unit.setSelectable(unit.owner == player);
        });

        // Show a notification of the change.
        if (isLocalPlayer) this.notify(''
            +'<span class="yourTurn">'
                +'<b>'+playerName+', it is your turn.</b>'
            +'</span>');
        else this.notify(''
            +'<span class="otherTurn">'
                +'It is <b>' + playerName + '</b>\'s turn.'
            +'</span>');
    }, this));
};

/*******************************************************************************
 * type = 'attack' | 'move'; cells = [[x1,y1], ...]
 */
GView.prototype.h_markCells = function (type, cells) {
    cellMap = {}
    if (cells) $.each(cells, function (index, cell) {
        cellMap[[cell.xloc, cell.yloc]] = true;
    });

    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('moveUnit: this game has not been initialised.');

        if (type == 'move') {

            // Remove previous 'moveable' tilemap, if any.
            this.underlay.find('#markCells_move').remove();

            if (cells && cells.length) {
                // Add a new 'moveable' tilemap.
                this.underlay.addTilemap(
                    'markCells_move',
                    function (y, x) {
                        return cellMap[[x,y]]
                            ? GView.OVERLAY_INDEX['moveable'] + 1
                            : 0;
                    },
                    GView.OVERLAY,
                    this.tilemap.options
                );

                // Remember that these cells are moveable.
                this.moveableCells = cellMap;
                this.root.find('#waitButton').attr('disabled', false);
            } else {
                // Remember that no cells are moveable.
                this.root.find('#waitButton').attr('disabled', true);
                this.moveableCells = null;
            }

        } else if (type == 'attack') {

            // Remove any previous 'attackable' tilemap
            this.underlay.find('#markCells_attack').remove();

            // Add a new 'attackable' tilemap.
            this.underlay.addTilemap(
                'markCells_attack',
                function (y, x) {
                    return cellMap[[x,y]]
                        ? GView.OVERLAY_INDEX['attackable'] + 1
                        : 0;
                },
                GView.OVERLAY,
                this.tilemap.options
            );

            // Ensure the correct set of units is set as attackable.
            $.each(this.attackableCells, $.proxy(function (cell) {
                if (!(cell in this.units)) return;
                this.units[cell].setAttacked(false);
                if (!cell in cellMap) return;
                this.units[cell].setAttackable(false);
            }, this));
            $.each(cellMap, $.proxy(function (cell) {
                if (cell in this.units)
                    this.units[cell].setAttackable(true);
            }, this));

            // Remember that no cells are attackable.
            this.root.find('#attackButton').attr('disabled', true);
            this.attackableCells = cellMap;
            this.attackedCell = null;
        } else {
            throw new Error('markCells: unknown marking type: ' + type);
        }

        this.updateTilemaps();
    }, this));
};


/*******************************************************************************
 * path = [[x1,y1], [x2,y2], ... [xN,yN]]
 */
GView.prototype.h_markPath = function (path) {
    // Enumerate the cells lying on the path.
    var cellMap = {};
    if (path) $.each(path, function (i, cell) {
        if (cell[0] != ~~cell[0] || cell[1] != ~~cell[1]) throw new Error(
            'markPath: '+cell.toSource()+' is not a valid cell.');

        if (i == 0) {
            cellMap[cell] = true;
            return;
        }
        var prev = path[i - 1];
        var step = [
            prev[0] > cell[0] ? 1 : prev[0] < cell[0] ? -1 : 0,
            prev[1] > cell[1] ? 1 : prev[1] < cell[1] ? -1 : 0
        ];

        if (step[0] != 0 && step[1] != 0) throw new Error(
            'markPath: the path between waypoints '+prev+' and '+cell
            +' is not straight horizontal or vertical.');

        cell = cell.slice();
        while (cell[0] != prev[0] || cell[1] != prev[1]) {
            cellMap[cell] = true;
            cell[0] += step[0], cell[1] += step[1];
        }
    });

    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('markPath: this game has not been initialsied.');

        // If no path or an empty list is given, just clear any existing path.
        this.underlay.find('#underlay_markPath').remove();
        this.updateTilemaps();
        this.movedCell = null;
        if (!path || !path.length) return;

        if (path[path.length-1].toString() != path[0].toString()) {
            this.movedCell = path[path.length - 1];
        }

        this.underlay.addTilemap(
            'underlay_markPath',
            function (y, x) {
                return cellMap[[x,y]]
                    ? GView.OVERLAY_INDEX['path'] + 1
                    : 0;
            },
            GView.OVERLAY,
            this.tilemap.options
        );
        this.updateTilemaps();
    }, this));
};

/*******************************************************************************
 * Mark the unit at `location' as selected.
 * If called with no arguments, deselect any currently selected unit.
 */
GView.prototype.h_selectUnit = function (location) {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('selectUnit: this game has not been initialised.');

        if (this.selectedUnit) {
            this.selectedUnit.setSelected(false);
            this.selectedUnit = false;
        }

        if (location in this.units) {
            if (this.selectedUnit) this.selectedUnit.setSelected(false);
            this.selectedUnit = this.units[location];
            this.selectedUnit.setSelected(true);
        }
    }, this));
};

/*******************************************************************************
 */
GView.prototype.h_changeDayTo = function (day) {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('changeDayTo: this game has not been initialised.');

        this.root.find('#dayIndicator')
            .find('#whichDay')
                .text(day.toString())
                .end()
            .css('visibility', 'visible');

    }, this));
};

/*******************************************************************************
 * Scrolls the centre of the tile `location = [x,y]' into view.
 */
GView.prototype.h_focus = function (location) {
    var vs = this.size, ms = this.tilemap.pxSize, ts = this.tilemap.tileSize;
    var of = this.tilemap.offset(location);

    var x = Math.max(0, Math.min(ms[0]-vs[0], of.left - vs[0]/2) + ts[0]/2);
    var y = Math.max(0, Math.min(ms[1]-vs[1], of.top - vs[1]/2) + ts[1]/2);

    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('focus: this game has not been initialised.');

        this.mapGroup.scrollLeft(x).scrollTop(y);
    }, this));};


/*******************************************************************************
 * Sets the health of the unit at `location' to `health'.
 */
GView.prototype.h_setHealth = function (location, health) {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('setHealth: this game has not been initialised.');

        return this.units[location].setHealth(health);
    }, this));
};

/*******************************************************************************
 */
GView.prototype.h_victory = function () {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('victory: this game has not been initialised.');

        this.disableInput();

        var defer = $.Deferred();
        var node = $(''
        +'<div class="popup victory"><div>'
            +'<div class="background"></div>'
            +'<div class="text">You are victorious!</div>'
            +'<div class="buttons">'
                +'<button id="exit">Exit</button>'
                +'<button id="continue">Continue</button>'
            +'</div>'
        +'</div></div>');
        node.find('.background').animate({
            'opacity': 0.8
        }, 1000, 'linear');
        node.find('#exit').click(function () {
            returnToLobby();
        });
        node.find('#continue').click(function () {
            node.remove();
            defer.resolve();
        });
        node.appendTo(this.root);
        return defer.promise();
    }, this));
};

/*******************************************************************************
 */
GView.prototype.h_defeat = function () {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('defeat: this game has not been initialised.');

        this.disableInput();

        var defer = $.Deferred();
        var node = $(''
        +'<div class="popup defeat"><div>'
            +'<div class="background"></div>'
            +'<div class="text">You have been defeated.</div>'
            +'<div class="buttons">'
                +'<button id="exit">Exit</button>'
                +'<button id="continue">Continue</button>'
            +'</div>'
        +'</div></div>');
        node.find('.background').animate({
            'opacity': 0.8
        }, 3000, 'linear');
        node.find('#exit').click(function () {
            returnToLobby();
        });
        node.find('#continue').click(function () {
            node.remove();
            defer.resolve();
        });
        node.appendTo(this.root);
        return defer.promise();
    }, this));
};

/*******************************************************************************
 */
GView.prototype.h_gameOver = function () {
    this.promise = this.promise.then($.proxy(function () {
        if (this.state != 'init')
            throw new Error('gameOver: this game has not been initialised.');

        this.disableInput();

        this.root
            .find('#turnIndicator, #timeIndicator, .hudButtons')
                .css('display', 'none').end()
            .find('#statusIndicator')
                .text('Game Over').end();

        $.each(this.units, function (index, unit) {
            unit.setSelectable(false);
        });
    }, this));
};

/*******************************************************************************
 * (Private) view.mouseEventTile(mouseEvent) = [x,y] - the tile index to which
 * the mouse points in the given mouse event, assuming the target of the event
 * is aligned with the tile map.
 */
GView.prototype.mouseEventTile = function (event) {
    var origin = $(event.currentTarget).offset();
    var offset = $(event.currentTarget).offset();
    var tileSize = this.tilemap.tileSize;
    var x = Math.floor((event.pageX - offset.left) / tileSize[0]);
    var y = Math.floor((event.pageY - offset.top) / tileSize[1]);
    return [x, y];
}

/*******************************************************************************
 * (Private) view.mouseMove(event) - handles the jQuery `mousemove' event over
 * the tilemap, which occurs when the mouse cursor's position changes.
 */
GView.prototype.mouseMove = function (event) {
    // Possibly generate a mouseTileMove event.
    var tile = this.mouseEventTile(event);
    if (tile[0] != this.mouseTile[0] || tile[1] != this.mouseTile[1]) {
        this.mouseTile = tile;
        this.mouseTileMove(tile);
    }
};

/*******************************************************************************
 * (Private) view.mouseEnter(event) - handles the jQuery `mouseenter' event over
 * the tilemap, which occurs when the mouse cursor enters the tilemap.
 */
GView.prototype.mouseEnter = function (event) {
    this.cursorSprite.css('visibility', 'visible');
};

/*******************************************************************************
 * (Private) view.mouseLeave(event) - handles the jQuery `mouseleave' event over
 * the tilemap, which occurs when the mouse cursor leaves the tilemap.
 */
GView.prototype.mouseLeave = function (event) {
    this.mouseIsDown = false;
    this.cursorSprite.css('visibility', 'hidden');
};

/*******************************************************************************
 * (Private) view.mouseDown(event) - handles the jQuery `mousedown' event over
 * the tilemap, which occurs when the mouse button is clicked down.
 */
GView.prototype.mouseDown = function (event) {
    this.mouseIsDown = true;
    this.clickTimeout = setTimeout(
        $.proxy(this.mouseHold, this, this.mouseEventTile(event)),
        GView.CLICK_MS
    );
    if (this.readOnly) return;

    var tile = this.mouseEventTile(event);
    if (this.attackedCell && tile.toString() == this.attackedCell.toString()) {
        // Double click on an attackable cell.
        this.model.attack(this.attackedCell);
    } else if (this.movedCell && tile.toString() == this.movedCell.toString()) {
        // Double click on a moveable cell.
        this.model.wait();
    } else if (this.moveableCells && this.moveableCells[tile]) {
        // Single click on a moveable cell.
        this.model.addWaypoint(tile);
    }
};

/*******************************************************************************
 * (Private) view.mouseUp(event) - handles the jQuery `mouseup' event over the
 * tilemap, which occurs when the mouse button is clicked up.
 */
GView.prototype.mouseUp = function (event) {
    this.mouseIsDown = false;
    if (this.clickTimeout !== null) clearTimeout(this.clickTimeout);
    if (this.readOnly) return;
    if (this.checkRangeCalled) return this.clearCheckRange();

    var tile = this.mouseEventTile(event);
    if (tile in this.attackableCells) {
        // Single-click on an attackable cell.
        if (this.attackedCell) this.units[this.attackedCell].setAttacked(false);
        this.attackedCell = tile;
        this.units[tile].setAttacked(true);
        this.root.find('#attackButton').attr('disabled', false);
    } else if (this.moveableCells && !this.moveableCells[tile]) {
        // Clicking outside the moveable cells, when any exist, deselects.
        this.model.deselect(tile);
    } else {
        this.model.select(tile);
    }
};

/*******************************************************************************
 * (Private) view.mouseHold(tile) - called after the mouse is held clicked over
 * `tile' for GView.CLICK_MS milliseconds.
 */
GView.prototype.mouseHold = function (tile) {
    this.clickTimeout = null;
    if (tile in this.units) {
        // The mouse is held clicked over a unit, and no unit is selected.
        this.model.deselect();
        this.model.checkRange(tile.slice());
        this.checkRangeCalled = true;
    }
};

/*******************************************************************************
 * (Private) view.mouseTileMove(tile) - called when the tile that the mouse is
 * pointing to changes to `tile'.
 */
GView.prototype.mouseTileMove = function (tile) {
    this.cursorSprite.css(this.tilemap.offset(tile));
    this.root.find('#mouseTile').text(tile.toString());

    if (this.clickTimeout !== null) clearTimeout(this.clickTimeout);
    if (this.checkRangeCalled) this.clearCheckRange();

    if (this.mouseIsDown && this.moveableCells && this.moveableCells[tile])
        this.model.addWaypoint(tile);
};

/*******************************************************************************
 * (Private) view.waitClick() - the "Wait" button has been clicked.
 */
GView.prototype.waitClick = function () {
    if (this.readOnly) return;
    this.model.wait();
};

/*******************************************************************************
 * (Private) view.attackClick() - the "Attack" button has been clicked.
 */
GView.prototype.attackClick = function () {
    if (this.readOnly) return;
    this.model.attack(this.attackedCell);
};

/*******************************************************************************
 * (Private) view.endTurnClick() - the "End Turn" button has been clicked.
 */
GView.prototype.endTurnClick = function () {
    if (this.readOnly) return;
    this.model.endTurn();
};

/*******************************************************************************
 * (Private) view.displayTime(seconds) - displays for the user that the current
 * player has the given number of seconds left in their turn.
 */
GView.prototype.displayTime = function (timeLeft) {
    var mins = Math.floor(timeLeft / 60);
    var secs = (timeLeft - 60*mins).toString();
    if (secs.length < 2) secs = '0' + secs;
    this.root
        .find('#timeIndicator')
        .css('visibility', timeLeft ? 'visible' : 'hidden')
        .find('#timeLeft')
        .text(''+mins+':'+secs);
};

/*******************************************************************************
 * (Private) view.clearCheckRange() - clear the marked cells resulting from a
 * call to view.model.checkRange(), after the mouse click is released or the
 * cursor is moved from the corresponding tile.
 */
GView.prototype.clearCheckRange = function () {
    this.checkRangeCalled = false;
    this.h_markCells('attack');
};

/*******************************************************************************
 * (Private) view.refreshTilemaps() - refresh all tilemaps, assuming they are
 * kept up to date with calls to updateTilemaps(), so that any areas scrolled
 * out of view are displayed.
 */
GView.prototype.refreshTilemaps = function () {
    $.each(this.tilemaps, function () { $(this).xy(0, 0, true) });
};

/*******************************************************************************
 * (Private) view.updateTilemaps() - update the internal list of tilemaps, after
 * a tilemap has been added or removed.
 */
GView.prototype.updateTilemaps = function () {
    this.tilemaps = this.mapGroup.find('.'+$.gQ.tilemapCssClass);
};

/*******************************************************************************
 * (Private) view.notify(html) - display the given HTMl on the screen briefly,
 * returning a Promise that will be resolved when it disappears.
 */
GView.prototype.notify = function (html) {
    var node = $('<div></div>')
        .attr('id', 'notify')
        .html(html)
        .appendTo(this.root);

    return $.Deferred(function (d) {
        setTimeout(function () { d.resolve() }, 1500)
    }).then(function () {
        return node.fadeOut(500).promise();
    }).then(function () {
        node.remove();
    });
};

/*******************************************************************************
 * (Private) gview.disableInput() - disable all further user input.
 */
GView.prototype.disableInput = function () {
    this.model.deselect();
    this.root.find('#hudButtons button').attr('disabled', true);
    this.readOnly = true;
}
