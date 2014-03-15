<?php

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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception('401 Unauthorized');
    }

    if (init('action') == 'get') {
        $return = array();
        $eqLogic = eqLogic::byId(init('id'));
        if (!is_object($eqLogic)) {
            throw new Exception('Equipement non trouvé vérifiez l\'id : ' . init('id'));
        }
        $return['eqLogic'] = utils::o2a($eqLogic);
        $energy = energy::byEqLogic_id($eqLogic->getId());
        $return['energy'] = (is_object($energy)) ? utils::o2a($energy) : array('eqLogic_id' => $eqLogic->getId(),'id' => '');
        ajax::success($return);
    }

    if (init('action') == 'save') {
        $energy_json = json_decode(init('energy'), true);
        $energy = energy::byId($energy_json['id']);
        if (!is_object($energy)) {
            $energy = new energy();
        }
        utils::a2o($energy, $energy_json);
        $energy->save();
        ajax::success();
    }

    throw new Exception('Aucune methode correspondante');
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>
