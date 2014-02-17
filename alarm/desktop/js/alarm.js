
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
                mode['mode::'+result] = [];
                mode['mode::'+result]['name'] = result;
                addMode(mode);
            }
        });
    });


});


function addEqLogic(_eqLogic){
    for (var i in _eqLogic.configuration){
        if(i.indexOf('mode::') === 0){
            var mode = {name : _eqLogic.configuration[i].name};
            mode[i] = json_decode(_eqLogic.configuration[i]); 
            mode.name = mode[i].name;
            addMode(mode);
        }
    }
}

function addMode(_mode) {
    console.log(_mode);
    var div = '<div class="mode">';
    div += '<form class="form-horizontal" role="form">';

    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Nom du mode</label>';
    div += '<div class="col-lg-4">';
    div += '<span class="eqLogicAttr label label-info" data-l1key="configuration" data-l2key="mode::' + _mode.name + '" data-l3key="name" ></span>';
    div += '</div>';
    div += '</div>';

    div += '<div class="form-group">';
    div += '<label class="col-lg-2 control-label">Déclencheur</label>';
    div += '<div class="col-lg-4">';
    div += '<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mode::' + _mode.name + '" data-l3key="trigger" />';
    div += '</div>';
    div += '</div>';

    div += '<hr/>';


    div += '</form>';
    div += '</div>';
    $('#div_modes').append(div);
    $('#div_modes .mode:last').setValues({configuration: _mode}, '.eqLogicAttr');
}