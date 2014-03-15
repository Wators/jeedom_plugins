
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
    $(".li_eqLogic").on('click', function(event) {
        $.hideAlert();
        $(".li_eqLogic").removeClass('active');
        $(this).addClass('active');
        get($(this).attr('data-eqLogic_id'));
    });

    if (getUrlVars('saveSuccessFull') == 1) {
        $('#div_alert').showAlert({message: 'Sauvegarde effectuée avec succès', level: 'success'});
    }

    $('.eqLogicAction[data-action=save]').on('click', function() {
        save();
    });


    if (is_numeric(getUrlVars('id'))) {
        if ($('#ul_eqLogic .li_eqLogic[data-eqLogic_id=' + getUrlVars('id') + ']').length != 0) {
            $('#ul_eqLogic .li_eqLogic[data-eqLogic_id=' + getUrlVars('id') + ']').click();
        } else {
            $('#ul_eqLogic .li_eqLogic:first').click();
        }
    } else {
        $('#ul_eqLogic .li_eqLogic:first').click();
    }
});


function get(_id) {
    $.ajax({
        type: 'POST',
        url: 'plugins/energy/core/ajax/energy.ajax.php',
        data: {
            action: 'get',
            id: _id
        },
        dataType: 'json',
        error: function(request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('body .energyAttr').value('');
            $('body').setValues(data.result.eqLogic, '.eqLogicAttr');
            $('body').setValues(data.result.energy, '.energyAttr');
            $('.eqLogic').show();
        }
    });
}

function save() {
    var energy = $('body').getValues('.energyAttr');
    energy = energy[0];
    $.ajax({
        type: 'POST',
        url: 'plugins/energy/core/ajax/energy.ajax.php',
        data: {
            action: 'save',
            energy: json_encode(energy)
        },
        dataType: 'json',
        error: function(request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            var vars = getUrlVars();
            var url = 'index.php?';
            for (var i in vars) {
                if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull') {
                    url += i + '=' + vars[i].replace('#', '') + '&';
                }
            }
            url += 'id=' + energy.eqLogic_id + '&saveSuccessFull=1';
            modifyWithoutSave = false;
            window.location.href = url;
        }
    });
}