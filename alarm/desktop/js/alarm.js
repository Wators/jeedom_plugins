
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
    $('#bt_addMode').on('click', function() {
        bootbox.prompt("Nom du mode ?", function(result) {
            if (result !== null) {
                addMode({name: result});
            }
        });
    });

    $("#div_modes").delegate('.bt_removeMode', 'click', function() {
        $(this).closest('.mode').remove();
    })

    $("#div_modes").delegate('.bt_addAction', 'click', function() {
        addAction($(this).closest('.mode'), '');
    });

    $("#div_modes").delegate('.bt_removeAction', 'click', function() {
        $(this).closest('.action').remove();
    })

    $("#div_modes").delegate('.bt_addTrigger', 'click', function() {
        addTrigger($(this).closest('.mode'), '');
    });

    $("#div_modes").delegate('.bt_removeTrigger', 'click', function() {
        $(this).closest('.trigger').remove();
    })

    $("#div_modes").delegate(".listEquipementInfo", 'click', function() {
        var el = $(this).closest('.trigger').find('.triggerAttr[data-l1key=cmd]');
        cmd.getSelectModal({type: 'info', subtype: 'binary'}, function(result) {
            el.value(result.human);
        });
    });


    $("#div_modes").delegate(".listEquipementAction", 'click', function() {
        var el = $(this).closest('.action').find('.expressionAttr[data-l1key=cmd]');
        cmd.getSelectModal({type: 'action'}, function(result) {
            el.value(result.human);
            el.closest('.action').find('.actionOptions').html(displayActionOption(el.value(), ''));
        });
    });

    $('body').delegate('.action .expressionAttr[data-l1key=cmd]', 'focusout', function(event) {
        var expression = $(this).closest('.action').getValues('.expressionAttr');
        $(this).closest('.action').find('.actionOptions').html(displayActionOption($(this).value(), init(expression[0].options)));
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
    _eqLogic.configuration.modes = [];
    $('#div_modes .mode').each(function() {
        var mode = $(this).getValues('.modeAttr');
        mode = mode[0];
        mode.actions = $(this).find('.action').getValues('.expressionAttr');
        mode.triggers = $(this).find('.trigger').getValues('.triggerAttr');
        _eqLogic.configuration.modes.push(mode);
    });
    return _eqLogic;
}

function printEqLogic(_eqLogic) {
    for (var i in _eqLogic.configuration.modes) {
        addMode(_eqLogic.configuration.modes[i]);
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
    div += '<label class="col-lg-2 control-label">Action</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn btn-default form-control listEquipementAction"><i class="fa fa-list-alt "></i><a>';
    div += '</div>';
    div += '<div class="col-lg-5">';
    div += '<input class="expressionAttr form-control" data-l1key="cmd" value="' + init(_action.cmd, '') + '"/>';
    div += '</div>';

    div += '<div class="col-lg-4 actionOptions">';
    div += '</div>';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeAction"></i>';
    div += '</div>';
    div += '</div>';
    _el.find('.div_actions').append(div);
    displayActionOption(init(_action, ''), _action.options)
}

function addTrigger(_el, _trigger) {
    if (!isset(_trigger)) {
        _trigger = {};
    }
    var div = '<div class="trigger">';
    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Déclencheur</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn btn-default form-control listEquipementInfo"><i class="fa fa-list-alt"></i><a>';
    div += '</div>';
    div += '<div class="col-lg-5">';
    div += '<input class="triggerAttr form-control" data-l1key="cmd" value="' + init(_trigger.cmd, '') + '"/>';
    div += '</div>';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeTrigger"></i>';
    div += '</div>';
    _el.find('.div_triggers').append(div);
}

function addMode(_mode) {
    var div = '<div class="mode well">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeMode"></i>';

    div += '<form class="form-horizontal" role="form">';

    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Nom du mode</label>';
    div += '<div class="col-lg-4">';
    div += '<span class="modeAttr label label-info" data-l1key="name" ></span>';
    div += '</div>';
    div += '<div class="col-lg-2">';
    div += '<a class="btn btn-default form-control bt_addTrigger"><i class="fa fa-plus-circle"></i> Déclencheur</a>';
    div += '</div>';
    div += '<div class="col-lg-2">';
    div += '<a class="btn btn-default form-control bt_addAction"><i class="fa fa-plus-circle"></i> Action</a>';
    div += '</div>';
    div += '</div>';

    div += '<div class="div_actions">';
    div += '<hr />';
    div += '<div class="div_triggers">';


    div += '</form>';

    div += '</div>';
    $('#div_modes').append(div);
    $('#div_modes .mode:last').setValues(_mode, '.modeAttr');
    if (is_array(_mode.actions)) {
        for (var i in _mode.actions) {
            addAction($('#div_modes .mode:last'), _mode.actions[i]);
        }
    } else {
        if (_mode.actions != '') {
            addAction($('#div_modes .mode:last'), _mode.actions);
        }
    }

    if (is_array(_mode.triggers)) {
        for (var i in _mode.triggers) {
            addTrigger($('#div_modes .mode:last'), _mode.triggers[i]);
        }
    } else {
        if (_mode.triggers != '') {
            addTrigger($('#div_modes .mode:last'), _mode.triggers);
        }
    }


}