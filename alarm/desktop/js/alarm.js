
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

$(function() {
    $('#tab_alarm a').click(function(e) {
        e.preventDefault()
        $(this).tab('show')
    })


    $('#bt_addZone').on('click', function() {
        bootbox.prompt("Nom de la zone ?", function(result) {
            if (result !== null) {
                addZone({name: result});
            }
        });
    });

    $("#div_zones").delegate('.bt_removeZone', 'click', function() {
        $(this).closest('.zone').remove();
    });

    $('#bt_addMode').on('click', function() {
        bootbox.prompt("Nom du mode ?", function(result) {
            if (result !== null) {
                addMode({name: result});
            }
        });
    });

    $("#div_modes").delegate('.bt_removeZoneMode', 'click', function() {
        $(this).closest('.zoneMode').remove();
    })

    $("#div_modes").delegate('.bt_addZoneMode', 'click', function() {
        var el = $(this);
        var select = '<select class="form-control">';
        $('#div_zones .zone').each(function() {
            var zone = $(this).getValues('.zoneAttr');
            zone = zone[0];
            select += '<option>' + zone.name + '</option>';
        });
        select += '</select>';
        $('#md_addZoneModeSelect').empty().append(select);
        $("#md_addZoneMode").modal('show');
        $('#bt_addZoneModeOk').off();
        $('#bt_addZoneModeOk').on('click', function() {
            $("#md_addZoneMode").modal('hide');
            addZoneMode(el.closest('.mode'), {zone: $('#md_addZoneModeSelect').find('select').value()});
        });
    })


    $("#div_modes").delegate('.bt_removeMode', 'click', function() {
        $(this).closest('.mode').remove();
    });

    $("#div_zones").delegate('.bt_addAction', 'click', function() {
        addAction({}, 'action', 'Action', $(this).closest('.zone'));
    });

    $("#div_zones").delegate('.bt_addActionImmediate', 'click', function() {
        addAction({}, 'actionImmediate', 'Action immédiat', $(this).closest('.zone'));
    });

    $("#div_zones").delegate('.bt_addTrigger', 'click', function() {
        addTrigger($(this).closest('.zone'), '');
    });

    $("#div_zones").delegate('.bt_removeTrigger', 'click', function() {
        $(this).closest('.trigger').remove();
    });

    $("#div_zones").delegate(".listCmdInfo", 'click', function() {
        var el = $(this).closest('.trigger').find('.triggerAttr[data-l1key=cmd]');
        cmd.getSelectModal({cmd: {type: 'info', subtype: 'binary'}}, function(result) {
            el.value(result.human);
        });
    });

    /**************** RAZ Alarm ***********/

    $('#btn_addRazAlarm').on('click', function() {
        addAction({}, 'raz', 'RaZ');
    });

    $('#btn_addRazImmediateAlarm').on('click', function() {
        addAction({}, 'razImmediate', 'RaZ immédiat');
    });

    /**************Activation OK**********************/

    $('#btn_addActionActivationOk').on('click', function() {
        addAction({}, 'actionActivationOk', 'Action');
    });

    /**************** PING ***********/

    $('#btn_addPingAction').on('click', function() {
        addAction({}, 'ping', 'Action');
    });

    $('#btn_addPingTest').on('click', function() {
        addPingTest({});
    });

    $("body").delegate(".listEquipement", 'click', function() {
        var type = $(this).attr('data-type');
        var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=eqLogic]');
        eqLogic.getSelectModal({}, function(result) {
            console.log(result);
            el.value(result.human);
        });
    });

    /**************** Commun ***********/
    $("body").delegate(".listCmdAction", 'click', function() {
        var type = $(this).attr('data-type');
        var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=cmd]');
        cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
            el.value(result.human);
            el.closest('.' + type).find('.actionOptions').html(displayActionOption(el.value(), ''));
        });
    });

    $("body").delegate('.bt_removeAction', 'click', function() {
        var type = $(this).attr('data-type');
        $(this).closest('.' + type).remove();
    });

    $('body').delegate('.cmdAction.expressionAttr[data-l1key=cmd]', 'focusout', function(event) {
        var type = $(this).attr('data-type')
        var expression = $(this).closest('.' + type).getValues('.expressionAttr');
        $(this).closest('.' + type).find('.actionOptions').html(displayActionOption($(this).value(), init(expression[0].options)));
    });

});

function displayActionOption(_expression, _options) {
    var html = '';
    $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "core/ajax/scenario.ajax.php", // url du fichier php
        data: {
            action: 'actionToHtml',
            version: 'scenario',
            expression: _expression,
            option: json_encode(_options)
        },
        dataType: 'json',
        async: false,
        global: false,
        error: function(request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            if (data.result.html != '') {
                html += '<div class="alert alert-info" style="margin : 0px; padding : 3px;">';
                html += data.result.html;
                html += '</div>';
            }
        }
    });
    return html;
}

function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }
    _eqLogic.configuration.zones = [];
    $('#div_zones .zone').each(function() {
        var zone = $(this).getValues('.zoneAttr');
        zone = zone[0];
        zone.actions = $(this).find('.action').getValues('.expressionAttr');
        zone.actionsImmediate = $(this).find('.actionImmediate').getValues('.expressionAttr');
        zone.triggers = $(this).find('.trigger').getValues('.triggerAttr');
        _eqLogic.configuration.zones.push(zone);
    });

    _eqLogic.configuration.modes = [];
    $('#div_modes .mode').each(function() {
        var mode = $(this).getValues('.modeAttr');
        mode = mode[0];
        _eqLogic.configuration.modes.push(mode);
    });

    _eqLogic.configuration.raz = $('#div_raz .raz').getValues('.expressionAttr');
    _eqLogic.configuration.razImmediate = $('#div_razImmediat .razImmediate').getValues('.expressionAttr');
    _eqLogic.configuration.ping = $('#div_ping .ping').getValues('.expressionAttr');
    _eqLogic.configuration.pingTest = $('#div_pingTest .pingTest').getValues('.expressionAttr');
    _eqLogic.configuration.activationOk = $('#div_activationOk .activationOk').getValues('.expressionAttr');

    return _eqLogic;
}

function printEqLogic(_eqLogic) {
    $('#div_zones').empty();
    $('#div_modes').empty();
    $('#div_razAlarm').empty();
    for (var i in _eqLogic.configuration.zones) {
        addZone(_eqLogic.configuration.zones[i]);
    }
    for (var i in _eqLogic.configuration.modes) {
        addMode(_eqLogic.configuration.modes[i]);
    }
    for (var i in _eqLogic.configuration.raz) {
        addAction(_eqLogic.configuration.raz[i], 'raz', 'RaZ');
    }
    for (var i in _eqLogic.configuration.razImmediate) {
        addAction(_eqLogic.configuration.razImmediate[i], 'razImmediate', 'RaZ immédiat');
    }
    for (var i in _eqLogic.configuration.ping) {
        addAction(_eqLogic.configuration.ping[i], 'ping', 'Action');
    }
    for (var i in _eqLogic.configuration.pingTest) {
        addPingTest(_eqLogic.configuration.pingTest[i]);
    }

    for (var i in _eqLogic.configuration.activationOk) {
        addAction(_eqLogic.configuration.activationOk[i], 'activationOk', 'Action');
    }
}

function addAction(_action, _type, _name, _el) {
    if (!isset(_action)) {
        _action = {};
    }
    if (!isset(_action.options)) {
        _action.options = {};
    }
    var div = '<div class="' + _type + '">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-1 control-label">' + _name + '</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn btn-default btn-sm listCmdAction" data-type="' + _type + '"><i class="fa fa-list-alt"></i></a>';
    div += '</div>';
    div += '<div class="col-lg-3">';
    div += '<input class="expressionAttr form-control input-sm cmdAction" data-l1key="cmd" data-type="' + _type + '" />';
    div += '</div>';
    div += '<div class="col-lg-6 actionOptions">';
    div += displayActionOption(init(_action.cmd, ''), _action.options);
    div += '</div>';
    div += '<div class="col-lg-1">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeAction" data-type="' + _type + '"></i>';
    div += '</div>';
    div += '</div>';
    if (isset(_el)) {
        _el.find('.div_' + _type).append(div);
        _el.find('.' + _type + ':last').setValues(_action, '.expressionAttr');
    } else {
        $('#div_' + _type).append(div);
        $('#div_' + _type + ' .' + _type + ':last').setValues(_action, '.expressionAttr');
    }
}

function addPingTest(_pingTest) {
    if (!isset(_pingTest)) {
        _pingTest = {};
    }
    if (!isset(_pingTest.options)) {
        _pingTest.options = {};
    }
    var div = '<div class="pingTest">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-1 control-label">Equipement à tester</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn btn-default btn-sm listEquipement" data-type="pingTest"><i class="fa fa-list-alt"></i></a>';
    div += '</div>';
    div += '<div class="col-lg-3">';
    div += '<input class="expressionAttr form-control input-sm" data-l1key="eqLogic" data-type="pingTest" />';
    div += '</div>';
    div += '<div class="col-lg-1">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeAction" data-type="pingTest"></i>';
    div += '</div>';
    div += '</div>';
    $('#div_pingTest').append(div);
    $('#div_pingTest .pingTest:last').setValues(_pingTest, '.expressionAttr');
}

function addTrigger(_el, _trigger) {
    if (!isset(_trigger)) {
        _trigger = {};
    }
    var div = '<div class="trigger">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-1 control-label">Déclencheur</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn btn-default btn-sm listCmdInfo btn-success"><i class="fa fa-list-alt"></i></a>';
    div += '</div>';
    div += '<div class="col-lg-3 has-success">';
    div += '<input class="triggerAttr form-control input-sm" data-l1key="cmd" />';
    div += '</div>';
    div += '<label class="col-lg-2 control-label">Délai activation</label>';
    div += '<div class="col-lg-1 has-success">';
    div += '<input class="triggerAttr form-control input-sm" data-l1key="armedDelay" />';
    div += '</div>';
    div += '<label class="col-lg-2 control-label">Délai déclenchement</label>';
    div += '<div class="col-lg-1 has-success">';
    div += '<input class="triggerAttr form-control input-sm" data-l1key="waitDelay" />';
    div += '</div>';
    div += '<div class="col-lg-1">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeTrigger"></i>';
    div += '</div>';
    div += '</div>';
    _el.find('.div_triggers').append(div);
    _el.find('.trigger:last').setValues(_trigger, '.triggerAttr');
}

function addZone(_zone) {
    var div = '<div class="zone well">';
    div += '<form class="form-horizontal" role="form">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Nom de la zone</label>';
    div += '<div class="col-lg-2">';
    div += '<span class="zoneAttr label label-info" data-l1key="name" ></span>';
    div += '</div>';
    div += '<div class="col-lg-3 col-lg-offset-5">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeZone"></i>';
    div += '<a class="btn btn-sm bt_addAction btn-danger  pull-right" style="margin-left : 5px;"><i class="fa fa-plus-circle"></i> Action</a>';
    div += '<a class="btn btn-warning btn-sm bt_addActionImmediate pull-right" style="margin-left : 5px;"><i class="fa fa-plus-circle"></i> Action immédiate</a>';
    div += '<a class="btn btn-sm bt_addTrigger btn-success pull-right"><i class="fa fa-plus-circle"></i> Déclencheur</a>';
    div += '</div>';
    div += '</div>';

    div += '<div class="div_triggers"></div>';
    div += '<hr/>';
    div += '<div class="div_actionImmediate"></div>';
    div += '<hr/>';
    div += '<div class="div_action"></div>';

    div += '</form>';

    div += '</div>';
    $('#div_zones').append(div);
    $('#div_zones .zone:last').setValues(_zone, '.zoneAttr');
    if (is_array(_zone.actions)) {
        for (var i in _zone.actions) {
            addAction(_zone.actions[i], 'action', 'Action', $('#div_zones .zone:last'));
        }
    } else {
        if ($.trim(_zone.actions) != '') {
            addAction(_zone.actions[i], 'action', 'Action', $('#div_zones .zone:last'));
        }
    }

    if (is_array(_zone.actionsImmediate)) {
        for (var i in _zone.actionsImmediate) {
            addAction(_zone.actionsImmediate[i], 'actionImmediate', 'Action immédiat', $('#div_zones .zone:last'));
        }
    } else {
        if ($.trim(_zone.actionsImmediate) != '') {
            addAction(_zone.actionsImmediate, 'actionImmediate', 'Action immédiat', $('#div_zones .zone:last'));
        }
    }

    if (is_array(_zone.triggers)) {
        for (var i in _zone.triggers) {
            addTrigger($('#div_zones .zone:last'), _zone.triggers[i]);
        }
    } else {
        if ($.trim(_zone.triggers) != '') {
            addTrigger($('#div_zones .zone:last'), _zone.triggers);
        }
    }
}

function addMode(_mode) {
    var div = '<div class="mode well">';
    div += '<form class="form-horizontal" role="form">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Nom du mode</label>';
    div += '<div class="col-lg-2">';
    div += '<span class="modeAttr label label-info" data-l1key="name" ></span>';
    div += '</div>';
    div += '<div class="col-lg-2 col-lg-offset-6">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeMode"></i>';
    div += '<a class="btn btn-default btn-sm bt_addZoneMode pull-right"><i class="fa fa-plus-circle"></i> Zone</a>';

    div += '</div>';
    div += '</div>';
    div += '<div class="div_zonesMode">';
    div += '</div>';
    div += '</form>';
    div += '</div>';
    $('#div_modes').append(div);
    $('#div_modes .mode:last').setValues(_mode, '.modeAttr');

    if (is_array(_mode.zone)) {
        for (var i in _mode.zone) {
            if (_mode.zone[i] != '') {
                addZoneMode($('#div_modes .mode:last'), {zone: _mode.zone[i]});
            }
        }
    } else {
        if ($.trim(_mode.zone) != '') {
            addZoneMode($('#div_modes .mode:last'), {zone: _mode.zone});
        }
    }
}

function addZoneMode(_el, _mode) {
    if (!isset(_mode)) {
        _mode = {};
    }
    var div = '<div class="zoneMode">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-1 control-label">Zone</label>';
    div += '<div class="col-lg-3">';
    div += '<span class="modeAttr label label-primary" data-l1key="zone"></span>';
    div += '</div>';
    div += '<div class="col-lg-1 col-lg-offset-7">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeZoneMode"></i>';
    div += '</div>';
    div += '</div>';

    _el.find('.div_zonesMode').append(div);
    _el.find('.zoneMode:last').setValues(_mode, '.modeAttr');
}