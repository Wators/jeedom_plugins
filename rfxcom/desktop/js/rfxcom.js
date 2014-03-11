
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
    
});


function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }

    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" >';
    tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="display : none;margin-top : 5px;" title="La valeur de la commande vaut par defaut la commande">';
    tr += eqLogic.builSelectCmd($(".li_eqLogic.active").attr('data-eqLogic_id'), {type: 'info'});
    tr += '</select>';
    tr += '</td>';
    tr += '<td class="expertModeHidden">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';
    tr += '<td class="expertModeHidden"><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="logicalId" value="0"></td>';
    tr += '<td>';
    tr += '<span><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" /> Historiser<br/></span>';
    tr += '<span><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/> Afficher<br/></span>';
    tr += '<span><input type="checkbox" class="cmdAttr expertModeHidden" data-l1key="eventOnly" /> Evénement<br/></span>';
    tr += '<span><input type="checkbox" class="cmdAttr expertModeHidden" data-l1key="display" data-l2key="invertBinary" /> Inverser<br/></span>';
    tr += '<input style="width : 150px;" class="tooltips cmdAttr form-control expertModeHidden input-sm" data-l1key="cache" data-l2key="lifetime" placeholder="Lifetime cache">';
    tr += '</td>';
    tr += '<td>';
    tr += '<input class="cmdAttr form-control tooltips input-sm" data-l1key="unite"  style="width : 100px;" placeholder="Unité" title="Unité">';
    tr += '<input class="tooltips cmdAttr form-control input-sm expertModeHidden" data-l1key="configuration" data-l2key="minValue" placeholder="Min" title="Min"> ';
    tr += '<input class="tooltips cmdAttr form-control input-sm expertModeHidden" data-l1key="configuration" data-l2key="maxValue" placeholder="Max" title="Max" style="margin-top : 5px;">';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> Tester</a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    cmd.changeType($('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]'), init(_cmd.subType));
}