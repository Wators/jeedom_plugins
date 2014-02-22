/******************************************************************************

 Z-Way TV UI 2
 Version: 1.0.1
 (c) Z-Wave.Me, 2013

 -----------------------------------------------------------------------------
 Author: Gregory Sitnin <sitnin@z-wave.me>
 Description:
     This is a main executable script for the pure-JavaScript Z-Way UI

******************************************************************************/

var DEBUG = qVar("debug") ? true : false;

var apiPort = qVar("port") ? qVar("port") : window.location.port;
var apiHost = qVar("host") ? qVar("host") : window.location.hostname;
var apiUrl = "http://"+apiHost+":"+apiPort+"/ZWaveAPI";

var zwayUpdateTime = 0;
var zwayUpdateTimer;
var zwayControllerNodeId;

var uiState = null;

var knownKeys = {
    13: 'enter', 27: 'escape', 8: 'return',
    37: 'left', 38: 'up', 39: 'right', 40: 'down',
    48: '0', 49: '1', 50: '2', 51: '3', 52: '4',
    53: '5', 54: '6', 55: '7', 56: '8', 57: '9'
};

var allWidgets = [];
var visibleWidgets = [];
var activeWidget = null;

Array.prototype.has = function (value) {
    return -1 != this.indexOf(value);
}

String.prototype.isJSON = function () {
    var f = this[0];
    var l = this[this.length-1];
    return ("{" === f && "}" === l) || ("[" === f && "]" === l);
}

Object.prototype.hasKey = function (key) {
    return -1 !== Object.keys(this).indexOf(""+key);
}

function qVar(variable) {
    var query = window.location.search.substring(1);
    var vars = query.split('&');
    for (var i = 0; i < vars.length; i++) {
        var pair = vars[i].split('=');
        if (decodeURIComponent(pair[0]) == variable) {
            return decodeURIComponent(pair[1]);
        }
    }
    return undefined;
}

function debugLog () {
    if (DEBUG) {
        document.getElementById("debug").innerHTML = "<b>DEBUG: </b>" + Array.prototype.join.call(arguments, ", ");
        console.log.apply(console, arguments);
    }
}

function uiStateChanged (fromState, toState) {
    debugLog("uiStateChanged", fromState, toState);
}

function showSetApiUrlDialog () {
    showPermanentError(new Error("Not implemented, yet"), "showSetApiUrlDialog");
}

function apiGet (url, callback) {
    var requestUrl = apiUrl + url;
    var xhr = new XMLHttpRequest();

    xhr.open("GET", requestUrl, true);
    xhr.onreadystatechange = function () {
        if (4 === xhr.readyState) {
            if (callback) {
                if (200 === xhr.status) {
                    if (xhr.responseText.isJSON()) {
                        callback(null, JSON.parse(xhr.responseText));
                    } else {
                        if (0 !== xhr.responseText.indexOf("ERROR")) {
                            callback(null, JSON.parse(xhr.responseText));
                        } else {
                            callback(new Error("API Replied Error " + xhr.responseText + ": " + url));
                        }
                    }
                } else {
                    callback(new Error("API Request returned Error " + xhr.status + ": " + url));
                }
            }
        }
    };

    xhr.send();
}

function connectToTheApi (callback) {
    apiGet("/Data/0", callback);
}

function updateTimerTick () {
    // Wait for the UI to be ready
    if ("ready" === uiState) {
        apiGet("/Data/" + zwayUpdateTime, function (err, data) {
            if (err) {
                debugLog("Error updating API data", err);
            } else {
                Object.keys(data).forEach(function (dataPoint) {
                    if ("updateTime" !== dataPoint && -1 !== dataPoint.indexOf("commandClasses")) {
                        var dpStringSplit = dataPoint.split(".");

                        var eventDeviceId = parseInt(dpStringSplit[1], 10);
                        var eventInstanceId = parseInt(dpStringSplit[3], 10);
                        var eventCommandClassId = parseInt(dpStringSplit[5], 10);

                        allWidgets.forEach(function (widget) {
                            if ((eventDeviceId == widget.deviceId) && (eventInstanceId == widget.instanceId)) {
                                widget.handleZwayUpdate(dataPoint, data[dataPoint]);
                            }
                        });
                    }
                });
                zwayUpdateTime = data.updateTime;
            }
        });
    }
}

function widgetsForDeviceInstance (instance, deviceId, instanceId) {
    var widgets = [];
    var instanceCommandClasses = Object.keys(instance.commandClasses);
    var isThermo = instanceCommandClasses.has("64") || instanceCommandClasses.has("67");

    // Add Thermostat widget if device is a thermostat
    if (isThermo) {
        console.log("Adding Thermostat widget");
        widgets.push(new ThermostatWidget("content", instance, deviceId, instanceId));
    }

    // Add other command class widgets
    instanceCommandClasses.forEach(function (commandClassId) {
        commandClassId = parseInt(commandClassId, 10);

        // debugLog("Device", deviceId, "Instance", instanceId, "Command class", commandClassId, typeof(commandClassId));

        // Ignore SwitchBinary if SwitchMultilevel exists
        if (0x25 === commandClassId && instanceCommandClasses.has("38")) {
            // NOTICE: instanceCommandClasses.has(0x26) -> instanceCommandClasses.has("38")
            debugLog("Ignoring SwitchBinary due to SwitchMultilevel existance");
            return;
        };

        // Ignore SensorMultilevel for thermostat
        if (0x25 === commandClassId) {
            // Create SwitchBinary widget
            debugLog("Adding SwitchBinary widget");
            widgets.push(new SwitchBinaryWidget("content", instance, deviceId, instanceId));
        } else if (0x26 === commandClassId) {
            // Create SwitchMultilevel widget
            debugLog("Adding SwitchMultilevel widget");
            widgets.push(new SwitchMultilevelWidget("content", instance, deviceId, instanceId));
        } else if (0x30 === commandClassId) {
            // Create SensorBinary widget
            debugLog("Adding SensorBinary widget");
            widgets.push(new SensorBinaryWidget("content", instance, deviceId, instanceId));
        } else if (0x31 === commandClassId) {
            // Create SensorMultilevel widget
            Object.keys(instance.commandClasses[0x31].data).forEach(function (scaleId) {
                var scaleId = parseInt(scaleId, 10);
                if (!isNaN(scaleId)) {
                    debugLog("Adding SensorMultilevel widget for scale", scaleId);
                    widgets.push(new SensorMultilevelWidget("content", instance, deviceId, instanceId, scaleId));
                }
            });
        } else if (0x32 === commandClassId) {
            // Create Meter widget
            Object.keys(instance.commandClasses[0x32].data).forEach(function (scaleId) {
                var scaleId = parseInt(scaleId, 10);
                if (!isNaN(scaleId)) {
                    debugLog("Adding Meter widget for scale", scaleId);
                    widgets.push(new MeterWidget("content", instance, deviceId, instanceId, scaleId));
                }
            });
        } else if (0x80 === commandClassId) {
            // Create Battery widget
            debugLog("Adding Battery widget");
            widgets.push(new BatteryWidget("content", instance, deviceId, instanceId));
        } else if (0x62 === commandClassId) {
            // Create Doorlock widget
            debugLog("Adding Doorlock widget");
            widgets.push(new DoorlockWidget("content", instance, deviceId, instanceId));
        } else if (0x44 === commandClassId) {
            // Create FanMode widget
            debugLog("Adding FanMode widget");
            widgets.push(new FanModeWidget("content", instance, deviceId, instanceId));
        }
    });

    return widgets;
}

function initStructures (initialTree, callback) {
    debugLog(initialTree);

    // Setup Z-Way updated
    zwayUpdateTime = initialTree.updateTime;
    zwayUpdateTimer = setInterval(updateTimerTick, 1000);

    zwayControllerNodeId = initialTree.controller.data.nodeId.value;
    debugLog("Controller Node ID:", zwayControllerNodeId);

    // Create top widget
    var topWidget = new DevicesFilterWidget("content");
    allWidgets.push(topWidget);
    visibleWidgets.push(topWidget);

    // Go through the tree and get devices list
    Object.keys(initialTree.devices).forEach(function (deviceId) {
        deviceId = parseInt(deviceId, 10);
        var device = initialTree.devices[deviceId];

        debugLog("D", deviceId);

        // Ignore Z-Wave controller device
        if (zwayControllerNodeId === deviceId) {
            debugLog("Ignoring controller device", deviceId);
            return;
        };

        // Ignore broadcast
        if (255 === deviceId) {
            debugLog("Ignoring broadcasting device", deviceId);
            return;
        };

        Object.keys(device.instances).forEach(function (instanceId) {
            instanceId = parseInt(instanceId, 10);
            var instance = device.instances[instanceId];

            debugLog("D-I", deviceId, instanceId);

            // Ignore first device instance in case of extended instances existence
            if (0 === instanceId && Object.keys(device.instances).length > 1) {
                debugLog("Ignoring instance 0 due to other instances existence", deviceId);
                return;
            };

            var diWidgets = widgetsForDeviceInstance(instance, deviceId, instanceId);

            debugLog("Device instance widgets", diWidgets);

            allWidgets.push.apply(allWidgets, diWidgets);
            visibleWidgets.push.apply(visibleWidgets, diWidgets);

            // Object.keys(instance.commandClasses).forEach(function (commandClassId) {
            //     commandClassId = parseInt(commandClassId, 10);
            //     var commandClass = instance.commandClasses[commandClassId];

            //     debugLog("D-I-C", deviceId, instanceId, commandClassId);
            // });
        });
    });

    callback();
}

function showMainUI () {
    visibleWidgets.forEach(function (widget) {
        widget.init();
    });

    setActiveWidget(0);
    allWidgets[0].applyCurrentFilter();

    setUIState("ready");
}

function setUIState (state) {
    var prevState = uiState;
    uiState = state;
    uiStateChanged(prevState, uiState);
}

function setContentHtml (html) {
    document.getElementById("content").innerHTML = html;
}

function showPermanentError (err, add) {
    setUIState("error");
    setContentHtml(add ? '<p class=error><b>'+add+':</b> '+err.message+'</p>' : '<p class=error>'+err.message+'</p>');
}

function setActiveWidget (index) {
    // do not change anything if index is null
    if (null === index) return;

    var w;

    // deactivate previous widget
    if (null !== activeWidget) {
        w = visibleWidgets[activeWidget];
        w.elem.classList.remove("active");
    }

    // activate given widget
    activeWidget = index;
    w = visibleWidgets[activeWidget];
    w.elem.classList.add("active");
    w.elem.scrollIntoView(false);
}

function onKeyPress (event) {
    // handle key strokes only if ui in ready state
    if ("ready" !== uiState) return;

    // depending on a type of the event get key code
    var key = "keydown" === event.type ? event.keyCode : event.charCode;

    // process known keys
    if (-1 !== Object.keys(knownKeys).indexOf(key+"")) {
        var keyName = knownKeys[key];

        var handledInWidget = false;
        if ("down" === keyName) {
            // select next widget
            handledInWidget = visibleWidgets[activeWidget].handleKeyPress(keyName);
            if (!handledInWidget) {
                setActiveWidget(activeWidget < visibleWidgets.length-1 ? activeWidget+1 : activeWidget);
            }
        } else if ("up" === keyName) {
            // select prev widget
            handledInWidget = visibleWidgets[activeWidget].handleKeyPress(keyName);
            if (!handledInWidget) {
                setActiveWidget(activeWidget > 0 ? activeWidget-1 : 0);
            }
        } else {
            // pass keypress to the widget itself
            visibleWidgets[activeWidget].handleKeyPress(keyName);
        }

        // stop keystroke processing
        return false;
    } else {
        if (key) {
            debugLog("UNHANDLED KEY STROKE", event.type, key);
        }
        // pass keystroke to the browser
        // this allows unhandled key strokes to be processed as usual (ie: Cmd+R to reload a page)
        return true;
    }
}

function uiStarter () {
    if (!apiUrl) {
        showPermanentError(new Error("apiUrl must be set"), "uiStarter");
    } else {
        debugLog("Connecting to the Z-Way API...");
        connectToTheApi(function (err, initialTree) {
            if (err) {
                showPermanentError(err, "connectToTheApi");
            } else {
                debugLog("Setting up UI...");
                setUIState("connected");
                initStructures(initialTree, function (err) {
                    debugLog("Initializing internal structures...");
                    if (err) {
                        showPermanentError(err, "initStructures");
                    } else {
                        setUIState("initialized");
                        showMainUI(function (err) {
                            debugLog("Rendering UI...");
                            if (err) {
                                showPermanentError(err, "showMainUI");
                            } else {
                                setUIState("ready");
                            }
                        });
                    }
                });
            }
        });
    }
}
