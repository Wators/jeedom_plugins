/*** Battery Polling Virtual Device class module ******************************

Version: 1.0.0

-------------------------------------------------------------------------------

Author: Gregory Sitnin <sitnin@z-wave.me>

Copyright: (c) ZWave.Me, 2013

******************************************************************************/

BatteryPollingDevice = function (id, controller) {
    BatteryPollingDevice.super_.call(this, id, controller);

    this.deviceType = "virtual";
    this.deviceSubType = "batteryPolling";
    this.caps = ["customWidget"];
}

inherits(BatteryPollingDevice, VirtualDevice);

BatteryPollingDevice.prototype.performCommand = function (command) {
    console.log("--- BatteryPollingDevice.performCommand processing...");

    var handled = true;
    if ("update" === command) {
        for (var id in zway.devices) {
            zway.devices[id].Battery && zway.devices[id].Battery.Get();
        }
    } else {
        handled = false;
    }

    return handled ? true : BatteryPollingDevice.super_.prototype.performCommand.call(this, command);
}
