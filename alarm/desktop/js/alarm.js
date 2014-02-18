
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
                var mode = {name: result};
                mode['mode::' + result] = [];
                mode['mode::' + result]['name'] = result;
                addMode(mode);
            }
        });
    });

    $("#div_modes").delegate(".listEquipementInfo", 'click', function() {
        var el = $(this);
        cmd.getSelectModal({type: 'info'}, function(result) {
            var calcul = el.closest('.mode').find('.eqLogicAttr[data-l1key=configuration][data-l3key=trigger]');
            calcul.value(calcul.value() + ' ' + result.human);
        });
    });

    $("#div_modes").delegate('.bt_removeMode', 'click', function() {
        $(this).closest('.mode').remove();
    })

});


function addEqLogic(_eqLogic) {
    for (var i in _eqLogic.configuration) {
        if (i.indexOf('mode::') === 0) {
            var mode = {name: _eqLogic.configuration[i].name};
            mode[i] = json_decode(_eqLogic.configuration[i]);
            mode.name = mode[i].name;
            addMode(mode);
        }
    }
}

function addMode(_mode) {
    var div = '<div class="mode well">';
    div += '<i class="fa fa-minus-circle pull-right cursor bt_removeMode"></i>';

    div += '<form class="form-horizontal" role="form">';

    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Nom du mode</label>';
    div += '<div class="col-lg-4">';
    div += '<span class="eqLogicAttr label label-info" data-l1key="configuration" data-l2key="mode::' + _mode.name + '" data-l3key="name" ></span>';
    div += '</div>';
    div += '</div>';

    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Délai avant déclenchement (en secondes)</label>';
    div += '<div class="col-lg-1">';
    div += '<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mode::' + _mode.name + '" data-l3key="triggerDelay" />';
    div += '</div>';
    div += '</div>';

    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Déclencheur</label>';
    div += '<div class="col-lg-7">';
    div += '<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mode::' + _mode.name + '" data-l3key="trigger" />';
    div += '</div>';
    div += '<div class="col-lg-2">';
    div += '<a class="btn btn-default form-control listEquipementInfo"><i class="fa fa-list-alt "></i> Rechercher équipement<a>';
    div += '</div>';
    div += '</div>';

    div += '<hr/>';


    div += '</form>';
    div += '</div>';
    $('#div_modes').append(div);
    $('#div_modes .mode:last').setValues({configuration: _mode}, '.eqLogicAttr');
}