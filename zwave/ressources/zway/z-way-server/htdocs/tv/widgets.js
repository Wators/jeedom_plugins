// ----------------------------------------------------------------------------
// --- Prototypal inheritance support routine (from Node.JS)
// ----------------------------------------------------------------------------

function inherits (ctor, superCtor) {
    ctor.super_ = superCtor;
    ctor.prototype = Object.create(superCtor.prototype, {
        constructor: {
            value: ctor,
            enumerable: false,
            writable: true,
            configurable: true
        }
    });
}

// ----------------------------------------------------------------------------
// --- Abstract widget class
// ----------------------------------------------------------------------------

function AbstractWidget (parentElement, instanceObject, deviceId, instanceId) {
    this.parentElementId = parentElement;
    this.widgetType = null;

    this.elem = null;
    this.deviceId = deviceId;
    this.instanceId = instanceId;
    this.commandClassId = null;
    this.value = null;
}

AbstractWidget.prototype.init = function () {
    var parent = document.getElementById(this.parentElementId);
    this.elem = document.createElement("div");
    this.elem.classList.add('widget');
    parent.appendChild(this.elem);
    this.updateWidgetUI();
};

AbstractWidget.prototype.handleKeyPress = function (key) {
    console.log("Handling key press: ", key);
};

AbstractWidget.prototype.handleZwayUpdate = function (datapoint, value) {
    console.log("Handling z-way update: ", datapoint, value);
};

AbstractWidget.prototype.dataPointName = function (commandClassId, scaleId) {
    return "devices." + this.deviceId + ".instances." + this.instanceId + ".commandClasses." + commandClassId + ".data." + (undefined !== scaleId ? scaleId : "level");
};

AbstractWidget.prototype._dic = function (commandClassId) {
    var ccId = !!commandClassId ? commandClassId : this.commandClassId;
    return "devices." + this.deviceId + ".instances." + this.instanceId + ".commandClasses." + ccId;
};


AbstractWidget.prototype.setValue = function (value, callback) {
    this.value = value;
    this.updateWidgetUI();
    if (callback) callback(value);
};

AbstractWidget.prototype.updateWidgetUI = function () {
    debugLog("Don't know how to update widget UI", this);
};

AbstractWidget.prototype.listScales = function (obj) {
    var scales = {};

    var dataHolder = obj.commandClasses[this.commandClassId].data;

    Object.keys(dataHolder).forEach(function (key) {
        key = parseInt(key, 10);
        if (isNaN(key)) return;

        scales[key] = dataHolder[key];
    });

    return scales;
}

// ----------------------------------------------------------------------------
// --- Testing and debugging widget
// ----------------------------------------------------------------------------

function TestWidget (parentElement, instanceObject, deviceId, instanceId) {
    TestWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);

    function randomInt(max) {
        return Math.floor(Math.random() * (max + 1));
    }

    var possibleWidgetTypes = ["switch", "sensor", "meter", "thermo", "lock"];

    this.widgetType = possibleWidgetTypes[randomInt(possibleWidgetTypes.length-1)];
    this.widgetTitle = "Test Widget - " + this.widgetType;
}

inherits(TestWidget, AbstractWidget);

TestWidget.prototype.init = function () {
    TestWidget.super_.prototype.init.call(this);
    this.elem.innerHTML = this.widgetTitle;
};

TestWidget.prototype.handleKeyPress = function (key) {
    var self = this;

    if (this.timeout)
        clearTimeout(this.timeout);

    this.elem.innerHTML = "Pressed key: " + key;

    this.timeout = setTimeout(function () {
        self.elem.innerHTML = self.widgetTitle;
    }, 1000);
};

// ----------------------------------------------------------------------------
// --- Top Navigation widget (not attached to any device)
// ----------------------------------------------------------------------------

function DevicesFilterWidget (parentElement, instanceObject, deviceId, instanceId) {
    DevicesFilterWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);

    this.filters = {
        "All": function (t) { return t.widgetType !== "battery"; },
        "Switches": function (t) { return t.widgetType === "switch"; },
        "Sensors": function (t) { return t.widgetType === "sensor"; },
        "Meters": function (t) { return t.widgetType === "meter"; },
        "Thermostats": function (t) { return t.widgetType === "thermo"; },
        "Locks": function (t) { return t.widgetType === "lock"; },
        "Batteries": function (t) { return t.widgetType === "battery"; }
    };

    this.currentFilter = 0;
}

inherits(DevicesFilterWidget, AbstractWidget);

DevicesFilterWidget.prototype.init = function () {
    DevicesFilterWidget.super_.prototype.init.call(this);

    var htmlString = "";

    Object.keys(this.filters).forEach(function (filterTitle) {
        htmlString += '<span class="filterTitle">'+filterTitle+'</span>';
    });

    this.elem.innerHTML = htmlString;

    this.elem.childNodes[this.currentFilter].classList.add('active');
};

DevicesFilterWidget.prototype.applyCurrentFilter = function () {
    var currentFilterFunc = this.filters[Object.keys(this.filters)[this.currentFilter]];
    var filteredWidgets = allWidgets.filter(currentFilterFunc);
    var newVisibleWidgets = [allWidgets[0]];

    allWidgets.forEach(function (widget) {
        if (widget instanceof DevicesFilterWidget) return;
        var isVisible = -1 !== filteredWidgets.indexOf(widget);
        widget.elem.style.display = isVisible ? "block" : "none";
        if (isVisible) newVisibleWidgets.push(widget);
    });

    visibleWidgets = newVisibleWidgets;
};

DevicesFilterWidget.prototype.handleKeyPress = function (key) {
    if ("right" === key || "left" === key) {
        this.elem.childNodes[this.currentFilter].classList.remove('active');

        if ("right" === key && this.currentFilter < this.elem.childNodes.length -1) {
            this.currentFilter++;
        } else if ("left" === key && this.currentFilter > 0) {
            this.currentFilter--;
        }

        this.elem.childNodes[this.currentFilter].classList.add('active');
        this.applyCurrentFilter();
    }
};

DevicesFilterWidget.prototype.handleZwayUpdate = function (datapoint, value) {
    // Empty method to ignore z-way updates
};

DevicesFilterWidget.prototype.updateWidgetUI = function () {
    // TODO: Move widget's UI renderer here
};

// ----------------------------------------------------------------------------
// --- SwitchBinary widget
// ----------------------------------------------------------------------------
// states: on, off
// commands:
//     enter: toggle state
//     left:  switch off
//     right: switch on
// ----------------------------------------------------------------------------

function SwitchBinaryWidget (parentElement, instanceObject, deviceId, instanceId) {
    SwitchBinaryWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);
    this.widgetType = "switch";
    this.commandClassId = 0x25;

    this.widgetTitle = "Switch";
    if (DEBUG) this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+"]";

    this.value = instanceObject.commandClasses[this.commandClassId].data.level.value;
}

inherits(SwitchBinaryWidget, AbstractWidget);

SwitchBinaryWidget.prototype.updateWidgetUI = function () {
    this.elem.innerHTML = this.widgetTitle + ": " + (255 === this.value ? "On" : "Off");
};

SwitchBinaryWidget.prototype.handleZwayUpdate = function (datapoint, value) {
    var myDP = this.dataPointName(this.commandClassId);

    if (myDP === datapoint) {
        debugLog("Updaing value", this.deviceId, this.instanceId, value.value);
        this.setValue(value.value);
    }
};

SwitchBinaryWidget.prototype.sendStateToDevice = function () {
    var self = this;

    return function (value) {
        debugLog("Sending new level to the device", self.deviceId, self.instanceId, value);
        apiGet("/Run/devices["+self.deviceId+"].instances["+self.instanceId+"].commandClasses[0x25].Set("+value+")", function (err, reply) {
            if (err) {
                debugLog("API Replied Error", err.message);
            } else {
                debugLog("API Replied Ok", reply);
            }
        });
    };
};

SwitchBinaryWidget.prototype.handleKeyPress = function (key) {
    var self = this;

    if ("enter" === key) {
        this.setValue(255 === this.value ? 0 : 255, self.sendStateToDevice());
    } else if ("left" === key) {
        this.setValue(0, self.sendStateToDevice());
    } else if ("right" === key) {
        this.setValue(255, self.sendStateToDevice());
    }
};

// ----------------------------------------------------------------------------
// --- SwitchMultilevel widget
// ----------------------------------------------------------------------------
// states: 0-99 %
// commands:
//     enter: toggle on/off
//     right: increase level by 10
//     left:  decrease level by 10
//     digits 1-9: set level to (10*value)
//     digit 0:
//         while state >0%: set level to 0
//         while state =0%: set level to 99
// ----------------------------------------------------------------------------

function SwitchMultilevelWidget (parentElement, instanceObject, deviceId, instanceId) {
    SwitchMultilevelWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);
    this.widgetType = "switch";
    this.commandClassId = 0x26;

    this.widgetTitle = "Dimmer";
    if (DEBUG) this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+"]";

    this.value = instanceObject.commandClasses[this.commandClassId].data.level.value;
}

inherits(SwitchMultilevelWidget, AbstractWidget);

SwitchMultilevelWidget.prototype.updateWidgetUI = function () {
    var valueString="";

    if (this.value >= 99) {
        valueString = "Full";
    } else if (0 === this.value) {
        valueString = "Off";
    } else {
        valueString = this.value + "%";
    }

    this.elem.innerHTML = this.widgetTitle + ": " + valueString;
};

SwitchMultilevelWidget.prototype.handleZwayUpdate = function (datapoint, value) {
    var myDP = this.dataPointName(this.commandClassId);

    if (myDP === datapoint) {
        debugLog("Updaing value", this.deviceId, this.instanceId, value.value);
        this.setValue(value.value);
    }
};

SwitchMultilevelWidget.prototype.sendStateToDevice = function () {
    var self = this;

    return function (value) {
        debugLog("Sending new level to the device", self.deviceId, self.instanceId, value);
        apiGet("/Run/devices["+self.deviceId+"].instances["+self.instanceId+"].commandClasses[0x26].Set("+value+")", function (err, reply) {
            if (err) {
                debugLog("API Replied Error", err.message);
            } else {
                debugLog("API Replied Ok", reply);
            }
        });
    };
};

SwitchMultilevelWidget.prototype.handleKeyPress = function (key) {
    var self = this;
    var newValue;

    if ("enter" === key) {
        this.setValue(0 < this.value ? 0 : 255, self.sendStateToDevice());
    } else if ("left" === key) {
        newValue = this.value-10;
        if (newValue < 0) newValue = 0;
        this.setValue(newValue, self.sendStateToDevice());
    } else if ("right" === key) {
        newValue = this.value+10;
        if (newValue >= 100) newValue = 99;
        this.setValue(newValue, self.sendStateToDevice());
    } else {
        var digit = parseInt(key, 10);
        if (!isNaN(digit)) {
            if (0 === digit) {
                if (0 === this.value) {
                   this.setValue(99, self.sendStateToDevice());
                } else {
                   this.setValue(0, self.sendStateToDevice());
                }
            } else {
                this.setValue(digit*10, self.sendStateToDevice());
            }
        }
    }
};

// ----------------------------------------------------------------------------
// --- SensorBinary widget
// ----------------------------------------------------------------------------
// states:
//     true: Triggered
//     false: Not triggered
// commands:
//     enter: Update value
// ----------------------------------------------------------------------------

function SensorBinaryWidget (parentElement, instanceObject, deviceId, instanceId) {
    SensorBinaryWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);
    this.widgetType = "sensor";
    this.commandClassId = 0x30;

    this.widgetTitle = "Sensor";
    if (DEBUG) this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+"]";

    this.value = instanceObject.commandClasses[this.commandClassId].data.level.value;
}

inherits(SensorBinaryWidget, AbstractWidget);

SensorBinaryWidget.prototype.updateWidgetUI = function () {
    this.elem.innerHTML = this.widgetTitle + ": " + (this.value ? "Triggered" : "Not triggered");
};

SensorBinaryWidget.prototype.handleZwayUpdate = function (datapoint, value) {
    var myDP = this.dataPointName(this.commandClassId);

    if (myDP === datapoint) {
        debugLog("Updaing value", this.deviceId, this.instanceId, value.value);
        this.setValue(value.value);
    }
};

SensorBinaryWidget.prototype.handleKeyPress = function (key) {
    var self = this;

    if ("enter" === key) {
        debugLog("Requesting current level from the device", self.deviceId, self.instanceId);
        apiGet("/Run/devices["+self.deviceId+"].instances["+self.instanceId+"].commandClasses[0x30].Get()", function (err, reply) {
            if (err) {
                debugLog("API Replied Error", err.message);
            } else {
                debugLog("API Replied Ok", reply);
            }
        });
    }
};

// ----------------------------------------------------------------------------
// --- SensorMultilevel widget
// ----------------------------------------------------------------------------
// commands:
// states:
// ----------------------------------------------------------------------------

function SensorMultilevelWidget (parentElement, instanceObject, deviceId, instanceId, scaleId) {
    SensorMultilevelWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);
    this.widgetType = "sensor";
    this.commandClassId = 0x31;

    this.scaleId = scaleId;
    this.sensorTitle = instanceObject.commandClasses[this.commandClassId].data[scaleId].sensorTypeString.value;
    this.scaleTitle = instanceObject.commandClasses[this.commandClassId].data[scaleId].scaleString.value;

    this.widgetTitle = this.sensorTitle+" Sensor";
    if (DEBUG) this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+":"+this.scaleId+"]";

    this.value = instanceObject.commandClasses[this.commandClassId].data[scaleId].val.value;
}

inherits(SensorMultilevelWidget, AbstractWidget);

SensorMultilevelWidget.prototype.updateWidgetUI = function () {
    this.elem.innerHTML = this.widgetTitle + ": " + this.value + " " + this.scaleTitle;
};

SensorMultilevelWidget.prototype.handleZwayUpdate = function (datapoint, value) {
    var myDP = this.dataPointName(this.commandClassId, this.scaleId);

    if (myDP === datapoint) {
        debugLog("Updaing value", this.deviceId, this.instanceId, this.scaleId, value.val.value);
        this.setValue(value.val.value);
    }
};

SensorMultilevelWidget.prototype.handleKeyPress = function (key) {
    var self = this;

    if ("enter" === key) {
        debugLog("Requesting current level from the device", self.deviceId, self.instanceId, this.scaleId);
        apiGet("/Run/devices["+self.deviceId+"].instances["+self.instanceId+"].commandClasses[0x31].Get()", function (err, reply) {
            if (err) {
                debugLog("API Replied Error", err.message);
            } else {
                debugLog("API Replied Ok", reply);
            }
        });
    }
};

// ----------------------------------------------------------------------------
// --- Meter widget
// ----------------------------------------------------------------------------
// commands:
//     enter: update immidiately
// scales:
//     TODO: Add scales descriptions
// ----------------------------------------------------------------------------

function MeterWidget (parentElement, instanceObject, deviceId, instanceId, scaleId) {
    MeterWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);
    this.widgetType = "meter";
    this.commandClassId = 0x32;

    this.scaleId = scaleId;
    this.sensorTitle = instanceObject.commandClasses[this.commandClassId].data[scaleId].sensorTypeString.value;
    this.scaleTitle = instanceObject.commandClasses[this.commandClassId].data[scaleId].scaleString.value;

    this.widgetTitle = this.sensorTitle+" Meter";
    if (DEBUG) this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+":"+this.scaleId+"]";

    this.value = instanceObject.commandClasses[this.commandClassId].data[scaleId].val.value;
}

inherits(MeterWidget, AbstractWidget);

MeterWidget.prototype.updateWidgetUI = function () {
    this.elem.innerHTML = this.widgetTitle + ": " + this.value + " " + this.scaleTitle;
};

MeterWidget.prototype.handleZwayUpdate = function (datapoint, value) {
    var myDP = this.dataPointName(this.commandClassId, this.scaleId);

    if (myDP === datapoint) {
        debugLog("Updaing value", this.deviceId, this.instanceId, this.scaleId, value.val.value);
        this.setValue(value.val.value);
    }
};

MeterWidget.prototype.handleKeyPress = function (key) {
    var self = this;

    if ("enter" === key) {
        debugLog("Requesting current level from the device", self.deviceId, self.instanceId, this.scaleId);
        apiGet("/Run/devices["+self.deviceId+"].instances["+self.instanceId+"].commandClasses[0x32].Get()", function (err, reply) {
            if (err) {
                debugLog("API Replied Error", err.message);
            } else {
                debugLog("API Replied Ok", reply);
            }
        });
    }
};

// ----------------------------------------------------------------------------
// --- BatteryWidget widget
// ----------------------------------------------------------------------------
// states: Battery health in %%
// commands:
//     enter: Request battery status update
// ----------------------------------------------------------------------------

function BatteryWidget (parentElement, instanceObject, deviceId, instanceId) {
    SwitchBinaryWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);
    this.widgetType = "battery";
    this.commandClassId = 0x80;

    this.widgetTitle = "Battery";
    if (DEBUG) this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+"]";

    this.value = instanceObject.commandClasses[this.commandClassId].data.last.value;
}

inherits(BatteryWidget, AbstractWidget);

BatteryWidget.prototype.updateWidgetUI = function () {
    this.elem.innerHTML = this.widgetTitle + ": " + this.value + " %";

    this.elem.classList.remove("batteryFresh");
    this.elem.classList.remove("batteryCritical");
    this.elem.classList.remove("batteryNormal");

    if (this.value >= 75) {
        this.elem.classList.add("batteryFresh");
    } else if (this.value <= 10) {
        this.elem.classList.add("batteryCritical");
    } else {
        this.elem.classList.add("batteryNormal");
    }
};

BatteryWidget.prototype.dataPointName = function (commandClassId, scaleId) {
    return "devices." + this.deviceId + ".instances." + this.instanceId + ".commandClasses." + commandClassId + ".data.last";
};

BatteryWidget.prototype.handleZwayUpdate = function (datapoint, value) {
    var myDP = this.dataPointName(0x80);

    if (myDP === datapoint) {
        debugLog("Updaing value", this.deviceId, this.instanceId, value.value);
        this.setValue(value.value);
    }
};

BatteryWidget.prototype.handleKeyPress = function (key) {
    var self = this;

    if ("enter" === key) {
        debugLog("Requesting battery status from the device", self.deviceId, self.instanceId);
        apiGet("/Run/devices["+self.deviceId+"].instances["+self.instanceId+"].commandClasses[0x80].Get()", function (err, reply) {
            if (err) {
                debugLog("API Replied Error", err.message);
            } else {
                debugLog("API Replied Ok", reply);
            }
        });
    }
};

// ----------------------------------------------------------------------------
// --- Thermostat widget
// ----------------------------------------------------------------------------
// commands:
//     enter: update immidiately
// scales:
//     TODO: Add scales descriptions
// ----------------------------------------------------------------------------

function ThermostatWidget (parentElement, instanceObject, deviceId, instanceId, scaleId) {
    ThermostatWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);
    this.widgetType = "thermo";

    this.inControl = false;
    this.activeControl = -1;

    this.sensorAvailable = Object.keys(instanceObject.commandClasses).has("49");
    this.modeAvailable = Object.keys(instanceObject.commandClasses).has("64");
    this.setPointAvailable = Object.keys(instanceObject.commandClasses).has("67");

    this.widgetTitle = "Thermostat";
    if (DEBUG) {
        if (this.modeAvailable) this.widgetTitle += "+Mod";
        if (this.setPointAvailable) this.widgetTitle += "+SPt";
        if (this.sensorAvailable) this.widgetTitle += "+Sns";

        this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+"]";
    }

    var modes = this.modeAvailable ? this.scanForModes(instanceObject) : {};
    var points = this.setPointAvailable ? this.scanForSetpoints(instanceObject) : {};
    this.widgetModes = this.assembleWidgetModes(modes, points);

    if (this.modeAvailable) {
        this.currentWidgetMode = this.findWidgetModeByDeviceMode(instanceObject.commandClasses[64].data.mode.value);
    } else {
        this.currentWidgetMode = 0;
    }

    this.currentSensorValue = null;
    this.sensorScaleString = "";
    if (this.sensorAvailable) {
        this.sensorScaleString = instanceObject.commandClasses[49].data[1].scaleString.value;
        this.currentSensorValue = instanceObject.commandClasses[49].data[1].val.value;
    }
}

inherits(ThermostatWidget, AbstractWidget);

ThermostatWidget.prototype.setInControl = function (value) {
    console.log("Switching inControl state", value);
    this.inControl = value;
    this.activeControl = value ? 0 : -1;
}

ThermostatWidget.prototype.scanForModes = function (obj) {
    var modes = {};

    Object.keys(obj.commandClasses[64].data).forEach(function (key) {
        key = parseInt(key, 10);
        if (isNaN(key)) return;

        var modeObj = obj.commandClasses[64].data[key];
        modes[key] = modeObj.modeName.value;
    });

    return modes;
}

ThermostatWidget.prototype.scanForSetpoints = function (obj) {
    var setpoints = {};

    Object.keys(obj.commandClasses[67].data).forEach(function (key) {
        key = parseInt(key, 10);
        if (isNaN(key)) return;

        var setpointObj = obj.commandClasses[67].data[key];
        setpoints[key] = {
            value: setpointObj.val.value,
            title: setpointObj.modeName.value
        };
    });

    return setpoints;
}

ThermostatWidget.prototype.assembleWidgetModes = function (modes, points) {
    var widgetModes = [];

    if (this.modeAvailable) {
        // Thermostat has number of widget modes, some of which can have setpoint
        Object.keys(modes).forEach(function (modeId) {
            modeId = parseInt(modeId, 10);
            widgetModes.push({
                title: modes[modeId],
                point: points.hasKey(modeId) ? points[modeId].value : null,
                modeId: modeId
            });
        });
    } else {
        // Thermostat has only one widget mode (completely from setpoint)
        var modeId = parseInt(Object.keys(points)[0], 10);
        widgetModes.push({
            title: points[modeId].title,
            point: points[modeId].value,
            modeId: modeId
        });
    }

    return widgetModes;
}

ThermostatWidget.prototype.findWidgetModeByDeviceMode = function (deviceModeId) {
    deviceModeId = parseInt(deviceModeId, 10);
    var foundId = null;

    for (var i=0; i<this.widgetModes.length; i++) {
        if (this.widgetModes[i].modeId === deviceModeId) {
            foundId = i;
        }
    }

    return null === foundId ? -1 : foundId;
}

ThermostatWidget.prototype.updateWidgetUI = function () {
    var self = this;
    var htmlCode = this.widgetTitle + ":";

    var widgetMode = this.widgetModes[this.currentWidgetMode];

    var activeClass = 0 === this.activeControl ? 'active': '';
    htmlCode += '<div class="subwidget swThermoMode '+activeClass+'">'+widgetMode.title+'</div>';

    if (this.sensorAvailable) {
        htmlCode += '<div class="subwidget swThermoSensor">'+this.currentSensorValue+' '+this.sensorScaleString+'</div>';
    }

    if (null !== widgetMode.point) {
        activeClass = 1 === this.activeControl ? 'active': '';
        htmlCode += '<div class="subwidget swThermoPoint '+activeClass+'">&rarr;'+widgetMode.point+' '+this.sensorScaleString+'</div>';
    }

    this.elem.innerHTML = htmlCode;
};

ThermostatWidget.prototype.zwaySetMode = function (modeId) {
    debugLog("Setting thermostat mode:", modeId, this.widgetModes[this.currentWidgetMode].title);

    apiGet("/Run/devices["+this.deviceId+"].instances["+this.instanceId+"].commandClasses[64].Set("+modeId+")", function (err, reply) {
        if (err) {
            debugLog("API Replied Error", err.message);
        } else {
            debugLog("API Replied Ok", reply);
        }
    });
}

ThermostatWidget.prototype.zwaySetPoint = function (modeId, pointVal) {
    debugLog("Setting thermostat setPoint:", modeId, this.widgetModes[this.currentWidgetMode].title, pointVal);

    apiGet("/Run/devices["+this.deviceId+"].instances["+this.instanceId+"].commandClasses[67].Set("+modeId+","+pointVal+")", function (err, reply) {
        if (err) {
            debugLog("API Replied Error", err.message);
        } else {
            debugLog("API Replied Ok", reply);
        }
    });
}

ThermostatWidget.prototype.handleZwayUpdate = function (dataPoint, value) {
    var dpBase = "devices."+this.deviceId+".instances."+this.instanceId+".commandClasses.";

    if (dpBase+"64.data.mode" === dataPoint) {
        this.currentWidgetMode = this.findWidgetModeByDeviceMode(value.value);
        this.updateWidgetUI();
    } else if (dpBase+"67.data." === dataPoint.substring(0, dpBase.length+8)) {
        this.widgetModes[this.findWidgetModeByDeviceMode(value.name)].point = value.val.value;
        this.updateWidgetUI();
    }
};

ThermostatWidget.prototype.handleKeyPress = function (key) {
    var self = this;
    var handled = false;

    if (!this.inControl) {
        if ("enter" === key || "right" === key) {
            this.setInControl(true);
        }
    } else  {
        var cwM = this.widgetModes[this.currentWidgetMode];

        if ("enter" === key || "return" === key) {
            this.setInControl(false);
        } else if ("left" === key) {
            if (null === cwM.point) return;
            this.activeControl--;
            if (this.activeControl < 0) {
                this.activeControl = 1;
            }
        } else if ("right" === key) {
            if (null === cwM.point) return;
            this.activeControl++;
            if (this.activeControl > 1) {
                this.activeControl = 0;
            }
        } else if ("up" === key) {
            // prevent main keyboard handler from processing
            handled = true;
            if (0 === this.activeControl) {
                // switch to the previous mode
                this.currentWidgetMode--;
                if (this.currentWidgetMode < 0) {
                    this.currentWidgetMode = 0;
                }
                // send new mode to z-wave thermostat mode
                this.zwaySetMode(this.widgetModes[this.currentWidgetMode].modeId);
            } else if (1 === this.activeControl) {
                // increase point temperature
                cwM.point++;
                // send updated val to z-wave setpoint
                this.zwaySetPoint(cwM.modeId, cwM.point);
            }
        } else if ("down" === key) {
            // prevent main keyboard handler from processing
            handled = true;
            if (0 === this.activeControl) {
                // switch to the next mode
                this.currentWidgetMode++;
                if (this.currentWidgetMode >= this.widgetModes.length) {
                    this.currentWidgetMode = this.widgetModes.length-1;
                }
                // send update val to z-wave setpoint
                this.zwaySetMode(this.widgetModes[this.currentWidgetMode].modeId);
            } else if (1 === this.activeControl) {
                // send new mode to z-wave thermostat mode
                cwM.point--;
                // send updated val to z-wave setpoint
                this.zwaySetPoint(cwM.modeId, cwM.point);
            }
        }
    }

    this.updateWidgetUI();

    return handled;
};

// ----------------------------------------------------------------------------
// --- Doorlock widget
// ----------------------------------------------------------------------------
// states: open, closed
// commands:
//     enter: toggle state
//     left:  switch off
//     right: switch on
// ----------------------------------------------------------------------------

function Doorlock (parentElement, instanceObject, deviceId, instanceId) {
    SwitchBinaryWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId);
    this.widgetType = "lock";
    this.commandClassId = 98;

    this.widgetTitle = "Doorlock";
    if (DEBUG) this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+"]";

    this.value = instanceObject.commandClasses[this.commandClassId].data.level.value;
}

inherits(Doorlock, AbstractWidget);

Doorlock.prototype.updateWidgetUI = function () {
    this.elem.innerHTML = this.widgetTitle + ": " + (255 === this.value ? "Open" : "Closed");
};

Doorlock.prototype.handleZwayUpdate = function (datapoint, value) {
    var myDP = this.dataPointName(this.commandClassId);

    if (myDP === datapoint) {
        debugLog("Updaing value", this.deviceId, this.instanceId, value.value);
        this.setValue(value.value);
    }
};

Doorlock.prototype.sendStateToDevice = function () {
    var self = this;

    return function (value) {
        debugLog("Sending new level to the device", self.deviceId, self.instanceId, value);
        apiGet("/Run/devices["+self.deviceId+"].instances["+self.instanceId+"].commandClasses["+this.commandClassId+"].Set("+value+")", function (err, reply) {
            if (err) {
                debugLog("API Replied Error", err.message);
            } else {
                debugLog("API Replied Ok", reply);
            }
        });
    };
};

Doorlock.prototype.handleKeyPress = function (key) {
    var self = this;

    if ("enter" === key) {
        this.setValue(255 === this.value ? 0 : 255, self.sendStateToDevice());
    } else if ("left" === key) {
        this.setValue(0, self.sendStateToDevice());
    } else if ("right" === key) {
        this.setValue(255, self.sendStateToDevice());
    }
};

// ----------------------------------------------------------------------------
// --- FanMode widget
// ----------------------------------------------------------------------------
// commands:
// modes:
//     TODO: Add modes descriptions
// ----------------------------------------------------------------------------

function FanModeWidget (parentElement, instanceObject, deviceId, instanceId, scaleId) {
    MeterWidget.super_.call(this, parentElement, instanceObject, deviceId, instanceId, scaleId);
    this.widgetType = "thermo";
    this.commandClassId = 68; // 0x44

    this.inControl = false;

    this.widgetTitle = "Fan";
    if (DEBUG) {
        this.widgetTitle += " ["+this.deviceId+":"+this.instanceId+"]";
    }

    this.state = instanceObject.commandClasses[this.commandClassId].data.on.value;

    var self = this;
    this.modes = [];
    var deviceModeScales = this.listScales(instanceObject);
    Object.keys(deviceModeScales).forEach(function (modeId) {
        modeId = parseInt(modeId, 10);
        self.modes.push({
            modeId: modeId,
            title: deviceModeScales[modeId].modeName.value
        });
    });


    this.currentModeId = instanceObject.commandClasses[this.commandClassId].data.mode.value;
    this.currentMode = this.modeIdByDeviceModeId(this.currentModeId);
}

inherits(FanModeWidget, AbstractWidget);

FanModeWidget.prototype.setInControl = function (value) {
    console.log("Switching inControl state", value);
    this.inControl = value;
    this.activeControl = value ? 0 : -1;
}

FanModeWidget.prototype.modeIdByDeviceModeId = function (deviceModeId) {
    deviceModeId = parseInt(deviceModeId, 10);
    var foundId = null;

    for (var i=0; i<this.modes.length; i++) {
        if (this.modes[i].modeId === deviceModeId) {
            foundId = i;
        }
    }

    return null === foundId ? -1 : foundId;
}

FanModeWidget.prototype.updateWidgetUI = function () {
    var self = this;
    var mode = this.modes[this.currentMode];

    var htmlCode = this.widgetTitle + ":";
    htmlCode += '<div class="subwidget swFanState">' + (this.state ? "On" : "Off") + '</div>';

    var activeClass = this.inControl ? 'active': '';
    htmlCode += '<div class="subwidget swFanMode '+activeClass+'">('+mode.title+')</div>';

    this.elem.innerHTML = htmlCode;
};

FanModeWidget.prototype.zwaySendUpdate = function () {
    apiGet("/Run/devices["+this.deviceId+"].instances["+this.instanceId+"].commandClasses["+this.commandClassId+"].Set("+this.state+","+this.modes[this.currentMode].modeId+")", function (err, reply) {
        if (err) {
            debugLog("API Replied Error", err.message);
        } else {
            debugLog("API Replied Ok", reply);
        }
    });
}

FanModeWidget.prototype.handleZwayUpdate = function (dataPoint, value) {
    var dpBase = "devices."+this.deviceId+".instances."+this.instanceId+".commandClasses."+this.commandClassId+".data.";

    if (dpBase+"on" === dataPoint) {
        this.state = value.value;
        this.updateWidgetUI();
    } else if (dpBase+"mode" === dataPoint) {
        this.currentModeId = value.value;
        this.currentMode = this.modeIdByDeviceModeId(this.currentModeId);
        this.updateWidgetUI();
    }
};

FanModeWidget.prototype.handleKeyPress = function (key) {
    var self = this;
    var handled = false;

    if (!this.inControl) {
        if ("enter" === key) {
            this.state = !this.state;
            this.zwaySendUpdate();
        } else if ("right" === key) {
            this.setInControl(true);
        }
    } else {
        var mode = this.modes[this.currentMode];

        if ("enter" === key || "return" === key || "left" === key) {
            this.setInControl(false);
        } else if ("up" === key) {
            // prevent main keyboard handler from processing
            handled = true;
            // switch to the previous mode
            this.currentMode--;
            if (this.currentMode < 0) {
                this.currentMode = 0;
            }
            // send new mode to z-wave thermostat mode
            this.zwaySendUpdate();
        } else if ("down" === key) {
            // prevent main keyboard handler from processing
            handled = true;
            // switch to the next mode
            this.currentMode++;
            if (this.currentMode >= this.modes.length) {
                this.currentMode = this.modes.length-1;
            }
            // send update val to z-wave setpoint
            this.zwaySendUpdate();
        }
    }

    this.updateWidgetUI();

    return handled;
};
