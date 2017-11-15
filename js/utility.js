/*******************************************************************************
 * utility.js - general utility functions and classes, including implementation
 * of standard library functions that may not be supported by some browsers.
 */


/*******************************************************************************
 * Object.create(proto) - a new, otherwise empty Object that has `proto' as its
 * prototype.
 */
if (!Object.create)
    Object.create = function (proto) {
        function F () {};
        F.prototype = proto;
        return new F;
    };


/*******************************************************************************
 * Object.keys(object) - an array of the names of `object's -own- properties,
 * that is, excluding properties that `object' inherits from other objects.
 */
if (!Object.keys)
    Object.keys = function (object) {
        var keys = [];
        for (var name in object)
            if (object.hasOwnProperty(name)) keys.push(name);
        return keys;
    };


/*******************************************************************************
 * array.filter(f, [this_]) - a new array containing exactly the elements `x' of
 * `array' for which `call(this_, f, i, array)' returns a value casting to
 * `true', where `x = array[i]'.
 */
if (!Array.prototype.filter)
    Array.prototype.filter = function (f, this_) {
        var result = [];
        for (var i = 0; i < this.length; ++i) {
            var x = this[i];
            if (call(this_, f, x, i, this)) result.push(x);
        }
        return result;
    };


/*******************************************************************************
 * array.indexOf(element) - the smallest i such that 0 <= i < array.length and
 * array[i] == element, or -1 if there is no such i.
 */
if (!Array.prototype.indexOf)
    Array.prototype.indexOf = function (element) {
        for (var i = start; i < this.length; ++i)
            if (this[i] == element) return i;
        return -1;
    };


/*******************************************************************************
 * Date.now() - the number of milliseconds since the UNIX Epoch.
 */
if (!Date.now)
    Date.now = function () {
        return new Date.getTime();
    };


/*******************************************************************************
 * Trigger() - an object on which named events can be listened for or triggered.
 */
function Trigger() {
    // This constructor may be called without using the `new' keyword.
    if (this === (function () { return this; })())
        return Trigger.apply(Object.create(Trigger.prototype), arguments);

    this.listeners = {};
    return this;
}

/*******************************************************************************
 * trigger.listen(event, handler) - register the function `handler' to be called
 * whenever `event' is triggered.
 */
Trigger.prototype.listen = function (event, handler) {
    if (!(event in this.listeners)) this.listeners[event] = [];
    this.listeners[event].push(handler);
    return handler;
}

/*******************************************************************************
 * trigger.unlisten(event, handler) - remove any instances of the function
 * `handler' listening for `event'.
 */
Trigger.prototype.unlisten = function (event, handler) {
    if (!(event in this.listeners)) return;
    this.listeners[event] = this.listeners[event].filter(function (h) {
        return h !== handler;
    });
}

/*******************************************************************************
 * trigger.trigger(event, ...) - broadcast `event' with zero or more arguments,
 * which will be passed to any handlers registered for this event.
 */
Trigger.prototype.trigger = function (event) {
    if (!(event in this.listeners)) return;
    var args = Array.prototype.slice.call(arguments, 1);
    $.each(this.listeners[event], function (index, handler) {
        handler.apply(null, args);
    });

    if (!this.listeners[event].length) console.log(
        'Warning: no listeners for event ' + event + '.');
}

//*******************************************************************************
// Courtesy of codeling (https://stackoverflow.com/users/671366/codeling)
// via https://stackoverflow.com/questions/2907482/how-to-get-the-query-string-by-javascript
//
// Extracts the query string section of an HTML page
// e.g. game.html?gameid=42 returns { gameid: 42 }
function getQueryStrings() {
  var assoc  = {};
  var decode = function (s) { return decodeURIComponent(s.replace(/\+/g, " ")); };
  var queryString = location.search.substring(1);
  var keyValues = queryString.split('&');

  for(var i in keyValues) {
    var key = keyValues[i].split('=');
    if (key.length > 1) {
      assoc[decode(key[0])] = decode(key[1]);
    }
  }

  return assoc;
}
