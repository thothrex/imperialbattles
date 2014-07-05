/*******************************************************************************
 * gview-support.js - contains classes which support the functionality in
 * gview.js.
 */

/*******************************************************************************
 * (Private) GView.Unit({ location, type, owner, health, status }, view)
 */
GView.Unit = function (data, view) {
    // This constructor may be called without using the `new' keyword.
    if (this === (function () { return this; })() || this === GView)
        return GView.Unit.apply(Object.create(GView.Unit.prototype), arguments);

    if (data.location in view.units) throw new Error(
        'An attempt was made to create a unit at '+data.location+','
        +' but a unit already exists there.');

    this.view = view;
    this.view.units[data.location] = this;

    this.type = data.type;
    this.owner = data.owner;

    // Create containing group.
    var id = 'unit' + GView.Unit.nextID++;
    this.group = this.view.unitLayer.addGroup(id);

    // Create mark indicator.
    this.group
        .addSprite(id+'_mark')
        .find('#'+id+'_mark')
        .addClass('mark')
        .css('visibility', 'hidden')
        .setAnimation(GView.OVERLAY)
        .wh(GView.OVERLAY.size[0], GView.OVERLAY.size[1]);

    // Create graphical sprite.
    this.group
        .addSprite(id+'_sprite')
        .find('#'+id+'_sprite')
        .addClass('sprite');

    // Create health indicator.
    $('<div/>')
        .attr('id', id+'_health')
        .addClass('health')
        .appendTo(this.group);

    // Create status indicator.
    this.group
        .addSprite(id+'_status')
        .find('#'+id+'_status')
        .addClass('status')
        .setAnimation(GView.STATUS)
        .wh(GView.STATUS.size[0], GView.STATUS.size[1]);

    // Create "owner" label.
    $('<div/>')
        .attr('id', id+'_owner')
        .addClass('owner')
        .text(view.players[this.owner-1])
        .appendTo(this.group);

    this.attacked = false;
    this.attackable = false;
    this.selected = false;
    this.walking = null;
    this.colour = data.colour;
    this.quickSetHealth(data.health);
    this.setLocation(data.location);
    this.setSelectable(false);
    this.setStatus(data.status);
    this.refresh();
};
GView.Unit.nextID = 0;

/*******************************************************************************
 * (Public) unit.setHealth(health) - sets the health of the unit, returning a
 * Promise giving the progress of this operation.
 */
GView.Unit.prototype.setHealth = function (health) {
    var damage = this.health - health;
    this.quickSetHealth(health);

    var node = $('<div class="damage"></div>')
        .text(damage.toString())
        .appendTo(this.group);

    return node.animate({
        'margin-top':   '-'+GView.DAMAGE_PX+'px',
        'opacity':      0
    }, GView.DAMAGE_MS, 'linear').promise().then(function () {
        node.remove()
    });
}

/*******************************************************************************
 * (Public) unit.setSelected(true | false)
 */
GView.Unit.prototype.setSelected = function (selected) {
    this.selected = selected;
    this.refresh();
};

/*******************************************************************************
 * (Public) unit.setAttackable(true | false)
 */
GView.Unit.prototype.setAttackable = function (attackable) {
    this.attackable = attackable;
    this.refresh();
}

/*******************************************************************************
 * (Public) unit.setAttacked(true | false)
 */
GView.Unit.prototype.setAttacked = function (attacked) {
    this.attacked = attacked;
    this.refresh();
}

/*******************************************************************************
 * (Public) unit.setWalking('move_w' | 'move_n' | 'move_e' | 'move_s')
 */
GView.Unit.prototype.setWalking = function (walking) {
    this.walking = walking;
    this.refresh();
}

/*******************************************************************************
 * (Public) unit.setSelectable(true | false)
 */
GView.Unit.prototype.setSelectable = function (selectable) {
    this.group.find('.status').css('visibility',
        selectable ? 'visible' : 'hidden');
};

/*******************************************************************************
 * (Public) unit.move([[x1,y1], [x2,y2], ... [xN,yN]]) - moves the unit to
 * (xN,yN), starting at (x1,y1) and travelling through each of the intermediate
 * points. Returns a Promise to indicate when the movement is complete.
 */
GView.Unit.prototype.move = function (path) {
    this.setLocation(path.shift());
    var promise = $.Deferred().resolve().promise();
    $.each(path, $.proxy(function (i, target) {
        target = this.view.tilemap.offset(target);

        promise = promise.then($.proxy(function () {
            var source = this.group.position();
            var dx = target.left - source.left, dy = target.top - source.top;

            var angle = (Math.round(Math.atan2(dy,dx)*2/Math.PI) + 2) % 4;
            this.setWalking(['walk_w', 'walk_n', 'walk_e', 'walk_s'][angle]);

            var ms = 1000 * Math.sqrt(dx*dx + dy*dy) / GView.UNITS.SPEED_PPS;

            // This is a hack to allow the z-index to be animated:
            // http://stackoverflow.com/questions/3122926
            var group = this.group;
            $({ z:source.top }).animate({ z:target.top }, {
                duration: ms,
                easing: 'linear',
                step: function (z) { group.css('z-index', ~~z) }
            });

            return this.group.animate(target, {
                duration: ms,
                easing: 'linear'
            }).promise();
        }, this));
    }, this));
    return promise.then($.proxy(function () {
        this.setWalking(null);
        if (path.length) this.setLocation(path[path.length-1]);
    }, this));
};

/*******************************************************************************
 * (Public) unit.remove()
 */
GView.Unit.prototype.remove = function () {
    var this_ = this;
    delete this.view.units[this.location];
    this.group.fadeOut(function () {
        this_.group.remove();
    });
};

/*******************************************************************************
 * (Private) unit.refresh() - update the unit's appearance after a state change.
 */
GView.Unit.prototype.refresh = function () {
    // Set animation.
    if (this.walking) this.setAnimation(this.walking);
    else if (this.selected) this.setAnimation('select');
    else this.setAnimation('idle');

    // Set mark.
    if (this.selected) this.setMark('selected');
    else if (this.attacked) this.setMark('attacked');
    //else if (this.attackable) this.setMark('attackable');
    else this.setMark();
};

/*******************************************************************************
 * (Private) unit.quickSetHealth(newHealth) - set health without animation.
 */
GView.Unit.prototype.quickSetHealth = function (health) {
    this.health = health;
    this.group.find('.health').text(health.toString());
};

/*******************************************************************************
 * (Private) unit.setLocation([x, y])
 */
GView.Unit.prototype.setLocation = function (location) {
    var offset = this.view.tilemap.offset(location);
    this.group.css({ left:offset.left, top:offset.top, 'z-index':offset.top });
    if (this.view.units[this.location] === this)
        delete this.view.units[this.location]
    this.view.units[location] = this;
    this.location = location;
};

/*******************************************************************************
 * (Private) unit.setAnimation('stand' | 'walk_n' | ...)
 */
GView.Unit.prototype.setAnimation = function (name) {
    if (name == this.currentAnimation) return;
    this.currentAnimation = name;
    var anim = GView.UNITS[this.type][this.colour][name];
    this.group
        .wh(anim.size[0], anim.size[1])
        .find('.sprite')
            .setAnimation(anim)
            .fliph(anim.flipH)
            .flipv(anim.flipV)
        .andSelf()
            .wh(anim.size[0], anim.size[1]);
};

/*******************************************************************************
 * unit.setStatus(status) - changes the graphically indicated status of this
 * unit. Returns a Promise to indicate when the change is complete.
 */
GView.Unit.prototype.setStatus = function (status) {
    if (!(status in GView.STATUS_INDEX)) throw new Error(
        'setStatus: unknown status: '+status+'.');
    var index = GView.STATUS_INDEX[status];
    this.group.find('.status').setAnimation(index - 1);
};

/*******************************************************************************
 * unit.setMark(type) - shows a named GView.OVERLAY animation beneath the unit,
 * or removes any current marking if none is given.
 */
GView.Unit.prototype.setMark = function (type) {
    if (!type) {
        this.group.find('.mark')
            .css('visibility', 'hidden');
    } else {
        this.group.find('.mark')
            .setAnimation(GView.OVERLAY_INDEX[type])
            .css('visibility', 'visible');
    }
};


/*******************************************************************************
 * Tilemap(data, url, name, root) - wraps a gameQuery tilemap created from from
 * the given Tiled map JSON representation, `data', by extracting the layer
 * named `name' and the tileset named `name' (which must be exclusively used by
 * the layer) and creating a tilemap with id `name' inside `root'. `url' is the
 * URL relative to which filenames in `data' are retrieved.
 */
function Tilemap(mapData, mapURL, name, root) {
    // This constructor may be called without using the `new' keyword.
    if (this === (function () { return this; })())
        return Tilemap.apply(Object.create(Tilemap.prototype), arguments);

    this.name = name;
    this.root = root;
    this.mapSize = [mapData.width, mapData.height];
    this.tileSize = [mapData.tilewidth, mapData.tileheight];
    this.pxSize = [this.mapSize[0]*this.tileSize[0],
                   this.mapSize[1]*this.tileSize[1]];

    // Extract the correct layer.
    var layer = mapData.layers.filter(function (layer) {
        return layer.name == name;
    })[0];

    // Extract the correct tileset.
    var tileset = mapData.tilesets.filter(function (tileset) {
        return tileset.name == name;
    })[0];

    // Create an Animation from the tileset.
    var w = mapData.width, h = mapData.height, data = new Array(h);
    for (var i = h; i--;)
        data[i] = new Array(w);
    for (var i = w*h; i--;)
        data[Math.floor(i/w)][i%w] = layer.data[i] - tileset.firstgid + 1;
    var baseURL = /.*\//.exec(mapURL)[0];
    var animation = Animation.fromTileset(tileset, baseURL, baseURL);

    // Create the tilemap.
    this.options = {
        sizex: mapData.width,
        sizey: mapData.height,
        width: mapData.tilewidth,
        height: mapData.tileheight
    };
    this.node = this.root
        .addGroup(name+'_parent')
        .addTilemap(name, data, animation, this.options);

    return this;
}

/*******************************************************************************
 * tilemap.offset([i, j]) = { left:x, top:y }, where (x,y) are the DOM
 * coordinates of the tile (i,j), within tilemap's container.
 */
Tilemap.prototype.offset = function (index) {
    return {
        left: this.tileSize[0] * index[0],
        top: this.tileSize[1] * index[1]
    };
}

/*******************************************************************************
 * Animation(options) - a wrapper for `new $.gQ.Animation(options)' which
 * supports some additional options.
 *
 *  options = {
 *      // Aliases for $.gQ.Animation options:
 *      url:        imageURL,
 *      frames:     numberOfFrame,
 *      offset:     [offsetx, offsety],
 *      direction:  'horizontal' | 'vertical'
 *      pingPong:   type & $.gQ.ANIMATION_PINGPONG,
 *      once:       type & $.gQ.ANIMATION_ONCE,
 *      multi:      type & $.gQ.ANIMATION_MULTI,
 *
 *      // Sprite-related data:
 *      size:       [width, height],
 *      flipH:      bool,
 *      flipV:      bool
 *  }
 */
function Animation(options) {
    // This constructor may be called without using the `new' keyword.
    if (this === (function () { return this; })())
        return Animation.apply(Object.create(Animation.prototype), arguments);

    options = $.extend({}, options);

    if ('url' in options)
        options.imageURL = options.url;
    if ('frames' in options)
        options.numberOfFrame = options.frames;
    if ('offset' in options)
        options.offsetx = options.offset[0],
        options.offsety = options.offset[1];
    if (options.direction == 'horizontal')
        options.type |= $.gQ.ANIMATION_HORIZONTAL;
    if (options.direction == 'vertical')
        options.type |= $.gQ.ANIMATION_VERTICAL;
    if (options.pingPong)
        options.type |= $.gQ.ANIMATION_PINGPING;
    if (options.once)
        options.type |= $.gQ.ANIMATION_ONCE;
    if (options.multi)
        options.type |= $.gQ.ANIMATION_MULTI;

    this.size = options.size || [undefined, undefined];
    this.flipH = !!options.flipH;
    this.flipV = !!options.flipV;

    $.gQ.Animation.call(this, options);
    return this;
}
Animation.prototype = Object.create($.gQ.Animation.prototype);

/*******************************************************************************
 * Animation.fromTileset(tilesetData, baseURL) - a multi-animation corresponding
 * to a tileset as specified by JSON data exported from the Tiled map editor,
 * with filenames retrieved relative to `baseURL'.
 */
Animation.fromTileset = function (ts, baseURL) {
    var hFrames = Math.floor((ts.imagewidth - 2*ts.margin + ts.spacing)
                             / (ts.tilewidth + ts.spacing));
    var vFrames = Math.floor((ts.imageheight - 2*ts.margin + ts.spacing)
                             / (ts.tileheight + ts.spacing));

    if (hFrames != 1 && vFrames != 1) throw new Error(
        'The given tileset contains '+hFrames+'*'+vFrames+' tiles;'
        +' only 1*N (vertical) or N*1 (horizontal) tilesets are supported.');

    var vertical = hFrames == 1;

    return Animation({
        url: baseURL + ts.image,
        frames: 1,
        direction: vertical ? 'horizontal' : 'vertical',
        multi: true,
        distance: ts.spacing + (vertical ? ts.tileheight : ts.tilewidth),
        offset: [ts.margin, ts.margin],
        size: [ts.tilewidth, ts.tileheight]
    });
}
