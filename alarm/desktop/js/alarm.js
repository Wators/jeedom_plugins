
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
        var zones = $('#div_zones').getValues('.zoneAttr');
        var select = '<select class="form-control">';
        for (var i in zones) {
            select += '<option>' + zones[i].name + '</option>';
        }
        select += '</select>';
        $('#md_addZoneModeSelect').empty().append(select);
        $("#md_addZoneMode").dialog('open');
        $('#bt_addZoneModeOk').off();
        $('#bt_addZoneModeOk').on('click', function() {
            addZoneMode($(this).closest('.mode'), {zone: $('#md_addZoneModeSelect').find('select').value()});
        });
    })


    $("#div_modes").delegate('.bt_removeMode', 'click', function() {
        $(this).closest('.mode').remove();
    });

    $("#div_zones").delegate('.bt_addAction', 'click', function() {
        addAction($(this).closest('.zone'), '');
    });

    $("#div_zones").delegate('.bt_removeAction', 'click', function() {
        $(this).closest('.action').remove();
    })

    $("#div_zones").delegate('.bt_addTrigger', 'click', function() {
        addTrigger($(this).closest('.zone'), '');
    });

    $("#div_zones").delegate('.bt_removeTrigger', 'click', function() {
        $(this).closest('.trigger').remove();
    });

    $("#div_zones").delegate(".listEquipementInfo", 'click', function() {
        var el = $(this).closest('.trigger').find('.triggerAttr[data-l1key=cmd]');
        cmd.getSelectModal({cmd: {type: 'info', subtype: 'binary'}}, function(result) {
            el.value(result.human);
        });
    });


    $("#div_zones").delegate(".listEquipementAction", 'click', function() {
        var el = $(this).closest('.action').find('.expressionAttr[data-l1key=cmd]');
        cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
            el.value(result.human);
            el.closest('.action').find('.actionOptions').html(displayActionOption(el.value(), ''));
        });
    });

    $('body').delegate('.action .expressionAttr[data-l1key=cmd]', 'focusout', function(event) {
        var expression = $(this).closest('.action').getValues('.expressionAttr');
        $(this).closest('.action').find('.actionOptions').html(displayActionOption($(this).value(), init(expression[0].options)));
    });

    $('#btn_addRazAlarm').on('click', function() {
        addRazAlarm({});
    });

    $("#div_razAlarm").delegate(".listEquipementRaz", 'click', function() {
        var el = $(this).closest('.raz').find('.expressionAttr[data-l1key=cmd]');
        cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
            el.value(result.human);
            el.closest('.raz').find('.actionOptions').html(displayActionOption(el.value(), ''));
        });
    });

    $('#div_razAlarm').delegate('.raz .expressionAttr[data-l1key=cmd]', 'focusout', function(event) {
        var expression = $(this).closest('.raz').getValues('.expressionAttr');
        $(this).closest('.raz').find('.actionOptions').html(displayActionOption($(this).value(), init(expression[0].options)));
    });

    $("#div_razAlarm").delegate('.bt_removeRaz', 'click', function() {
        $(this).closest('.raz').remove();
    })

});

function addRazAlarm(_raz) {
    if (!isset(_raz)) {
        _raz = {};
    }
    if (!isset(_raz.options)) {
        _raz.options = {};
    }
    var div = '<div class="raz">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-1 control-label">Action</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn btn-default btn-sm listEquipementRaz"><i class="fa fa-list-alt"></i></a>';
    div += '</div>';
    div += '<div class="col-lg-3">';
    div += '<input class="expressionAttr form-control input-sm" data-l1key="cmd" />';
    div += '</div>';
    div += '<div class="col-lg-6 actionOptions">';
    div += displayActionOption(init(_raz.cmd, ''), _raz.options);
    div += '</div>';
    div += '<div class="col-lg-1">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeRaz"></i>';
    div += '</div>';
    div += '</div>';
    $('#div_razAlarm').append(div);
    $('#div_razAlarm .raz:last').setValues(_raz, '.expressionAttr');
}

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
        zone.triggers = $(this).find('.trigger').getValues('.triggerAttr');
        _eqLogic.configuration.zones.push(zone);
    });
    $('#div_zones .mode').each(function() {
        var mode = $(this).getValues('.modeAttr');
        mode = mode[0];
        _eqLogic.configuration.modes.push(mode);
    });
    _eqLogic.configuration.raz = $('#div_razAlarm .raz').getValues('.expressionAttr');


    return _eqLogic;
}

function printEqLogic(_eqLogic) {
    for (var i in _eqLogic.configuration.zones) {
        addZone(_eqLogic.configuration.zones[i]);
    }
    for (var i in _eqLogic.configuration.modes) {
        addMode(_eqLogic.configuration.modes[i]);
    }
    for (var i in _eqLogic.configuration.raz) {
        addRazAlarm(_eqLogic.configuration.raz[i]);
    }
}

function addAction(_el, _action) {
    if (!isset(_action)) {
        _action = {};
    }
    if (!isset(_action.options)) {
        _action.options = {};
    }
    var div = '<div class="action">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-1 control-label">Action</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn btn-default btn-sm listEquipementAction"><i class="fa fa-list-alt "></i></a>';
    div += '</div>';
    div += '<div class="col-lg-3">';
    div += '<input class="expressionAttr form-control input-sm" data-l1key="cmd" />';
    div += '</div>';
    div += '<div class="col-lg-4 actionOptions">';
    div += displayActionOption(init(_action.cmd, ''), _action.options);
    div += '</div>';
    div += '<div class="col-lg-1 col-lg-offset-1">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeAction"></i>';
    div += '</div>';
    div += '</div>';
    _el.find('.div_actions').append(div);
    _el.find('.action:last').setValues(_action, '.expressionAttr');

}

function addTrigger(_el, _trigger) {
    if (!isset(_trigger)) {
        _trigger = {};
    }
    var div = '<div class="trigger">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-1 control-label">Déclencheur</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn btn-default btn-sm listEquipementInfo"><i class="fa fa-list-alt"></i></a>';
    div += '</div>';
    div += '<div class="col-lg-3">';
    div += '<input class="triggerAttr form-control input-sm" data-l1key="cmd" />';
    div += '</div>';
    div += '<label class="col-lg-2 control-label">Délai activation</label>';
    div += '<div class="col-lg-1">';
    div += '<input class="triggerAttr form-control input-sm" data-l1key="armedDelay" />';
    div += '</div>';
    div += '<label class="col-lg-2 control-label">Délai déclenchement</label>';
    div += '<div class="col-lg-1">';
    div += '<input class="triggerAttr form-control input-sm" data-l1key="waitDelay" />';
    div += '</div>';
    div += '<div class="col-lg-1 actionOptions">';
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
    div += '<label class="col-lg-1 control-label">Nom de la zone</label>';
    div += '<div class="col-lg-2">';
    div += '<span class="zoneAttr label label-info" data-l1key="name" ></span>';
    div += '<input type="checkbox" class="zoneAttr form-control" data-l1key="isVisible" checked>';
    div += '</div>';
    div += '<div class="col-lg-2 col-lg-offset-5">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeZone"></i>';
    div += '<a class="btn btn-default btn-sm bt_addTrigger pull-right"><i class="fa fa-plus-circle"></i> Déclencheur</a>';
    div += '<a class="btn btn-default btn-sm bt_addAction  pull-right" style="margin-left : 5px;"><i class="fa fa-plus-circle"></i> Action</a>';

    div += '</div>';
    div += '</div>';

    div += '<div class="div_actions">';
    div += '<hr />';
    div += '<div class="div_triggers">';

    div += '</form>';

    div += '</div>';
    $('#div_zones').append(div);
    $('#div_zones .zone:last').setValues(_zone, '.zoneAttr');
    if (is_array(_zone.actions)) {
        for (var i in _zone.actions) {
            addAction($('#div_zones .zone:last'), _zone.actions[i]);
        }
    } else {
        if (_zone.actions != '') {
            addAction($('#div_zones .zone:last'), _zone.actions);
        }
    }

    if (is_array(_zone.triggers)) {
        for (var i in _zone.triggers) {
            addTrigger($('#div_zones .zone:last'), _zone.triggers[i]);
        }
    } else {
        if (_zone.triggers != '') {
            addTrigger($('#div_zones .zone:last'), _zone.triggers);
        }
    }
}

function addMode(_mode) {
    var div = '<div class="mode well">';
    div += '<form class="form-horizontal" role="form">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-1 control-label">Nom du mode</label>';
    div += '<div class="col-lg-2">';
    div += '<span class="modeAttr label label-info" data-l1key="name" ></span>';
    div += '<input type="checkbox" class="modeAttr form-control" data-l1key="isVisible" checked>';
    div += '</div>';
    div += '<div class="col-lg-2 col-lg-offset-5">';
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
    if (is_array(_mode.zones)) {
        for (var i in _mode.zones) {
            addZoneMode($('#div_modes .mode:last'), _mode.zones[i]);
        }
    } else {
        if (_mode.zones != '') {
            addZoneMode($('#div_modes .mode:last'), _mode.zones);
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
    div += '<span class="modeAttr label label-info" data-l1key="zone"></span>';
    div += '</div>';
    div += '<div class="col-lg-1 actionOptions">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeZoneMode"></i>';
    div += '</div>';
    div += '</div>';
    _el.find('.div_zonesMode').append(div);
    _el.find('.zoneMode:last').setValues(_mode, '.modeAttr');
}