#!/usr/bin/php

<?php

if (isset($_REQUEST['function'])) {

    switch($_REQUEST['function']) {

        case('resume'):
echo <<<END
    {
        "map": {
            "mapname": "map/King of the Hill.json",
            "width": 32,
            "height": 32
        },
        "game": {
            "gameid": 0,
            "gamename": "gamename",
            "turntimeout": 120,
            "currenttimeleft": 120,
            "localplayer": 1,
            "currentplayer": 1
        },
        "players": [
            { "username":"Player 1", "colour":"red",    "team":1, "seqno":0 },
            { "username":"Player 2", "colour":"blue",   "team":2 },
            { "username":"Player 3", "colour":"green",  "team":1 },
            { "username":"Player 4", "colour":"yellow", "team":2 }
        ],
        "units": [
            { "unitType":2, "owner":1, "location":[ 4, 4], "state":"normal", "health":10 },
            { "unitType":1, "owner":1, "location":[ 5, 4], "state":"normal", "health":10 },
            { "unitType":0, "owner":1, "location":[ 4, 5], "state":"normal", "health":10 },
            { "unitType":1, "owner":1, "location":[ 6, 4], "state":"normal", "health":10 },
            { "unitType":0, "owner":1, "location":[ 4, 6], "state":"normal", "health":10 },

            { "unitType":2, "owner":2, "location":[ 6, 6], "state":"normal", "health":10 },
            { "unitType":0, "owner":2, "location":[ 7, 6], "state":"normal", "health":10 },
            { "unitType":1, "owner":2, "location":[ 6, 7], "state":"normal", "health":10 },

            { "unitType":0, "owner":2, "location":[29, 2], "state":"normal", "health":10 },
            { "unitType":1, "owner":2, "location":[29, 3], "state":"normal", "health":10 },
            { "unitType":2, "owner":2, "location":[28, 2], "state":"normal", "health":10 },

            { "unitType":0, "owner":3, "location":[28,28], "state":"normal", "health":10 },
            { "unitType":1, "owner":3, "location":[27,28], "state":"normal", "health":10 },
            { "unitType":2, "owner":3, "location":[28,27], "state":"normal", "health":10 },

            { "unitType":0, "owner":4, "location":[ 3,28], "state":"normal", "health":10 },
            { "unitType":1, "owner":4, "location":[ 4,28], "state":"normal", "health":10 },
            { "unitType":2, "owner":4, "location":[ 3,27], "state":"normal", "health":10 }
        ]
    }
END;
            break;

        case('update'):
            echo '[]';
            break;

        case('move'):
            echo json_encode('success');
            break;

        case('endTurn'):
            echo json_encode('success');
            break;
    }
}

?>
