(function () {

    GView.UNITS = {}

    GView.UNITS.SPEED_PPS = 80;

    var OTHER_MS    = 100,
        IDLE_MS     = 1000,
        SELECT_MS   = 500;

    var base = {
        url: 'img/sprite_sheet.png',
        size: [32, 32],
        direction: 'horizontal',
        delta: 33,
        rate: OTHER_MS,
    };

    function anim (options) {
        return Animation($.extend({}, base, options));
    }

    function offset (i, j) {
        return [1 + 33*i, 1 + 33*j];
    }

    var colours = 'blue pink green cyan yellow black white red'.split(' ')
    var types = 'archer spearman lancer'.split(' ')

    var type='archer', v=0;
    if (!(type in GView.UNITS)) GView.UNITS[type] = {};
    for (var h=0; h<8; ++h) {
        var colour = colours[h];
        var type = types[v];
        var x = 4*h, y = 7*v;
        GView.UNITS[type][colour] = {
            'idle':     anim({ offset:offset(x,y+1), frames:2, rate:IDLE_MS }),
            'select':   anim({ offset:offset(x,y+2), frames:2, rate:SELECT_MS }),
            'walk_w':   anim({ offset:offset(x,y+3), frames:4 }),
            'walk_e':   anim({ offset:offset(x,y+4), frames:4 }),
            'walk_s':   anim({ offset:offset(x,y+5), frames:4 }),
            'walk_n':   anim({ offset:offset(x,y+6), frames:4 })
        };
    }

    var type='spearman', v=1;
    if (!(type in GView.UNITS)) GView.UNITS[type] = {};
    for (var h=0; h<8; ++h) {
        var colour = colours[h];
        var type = types[v];
        var x = 4*h, y = 7*v;
        GView.UNITS[type][colour] = {
            'idle':     anim({ offset:offset(x,y+1), frames:2, rate:IDLE_MS }),
            'select':   anim({ offset:offset(x,y+2), frames:2, rate:SELECT_MS }),
            'walk_w':   anim({ offset:offset(x,y+3), frames:4 }),
            'walk_e':   anim({ offset:offset(x,y+4), frames:4 }),
            'walk_s':   anim({ offset:offset(x,y+5), frames:4 }),
            'walk_n':   anim({ offset:offset(x,y+6), frames:4 })
        };
    }

    var type='lancer', v=2;
    if (!(type in GView.UNITS)) GView.UNITS[type] = {};
    for (var h=0; h<8; ++h) {
        var colour = colours[h];
        var type = types[v];
        var x = 4*h, y = 7*v;
        GView.UNITS[type][colour] = {
            'select':   anim({ offset:offset(x,y+1), frames:2, rate:SELECT_MS }),
            'idle':     anim({ offset:offset(x,y+2), frames:2, rate:IDLE_MS }),
            'walk_w':   anim({ offset:offset(x,y+3), frames:4 }),
            'walk_e':   anim({ offset:offset(x,y+4), frames:4 }),
            'walk_s':   anim({ offset:offset(x,y+5), frames:4 }),
            'walk_n':   anim({ offset:offset(x,y+6), frames:4 })
        };
    }

})()
