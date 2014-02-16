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

    if (init('action') == 'getWeather') {
        $weather = weather::byId(init('id'));
        if (!is_object($weather)) {
            throw new Exception('Weather inconnu verifié l\'id');
        }
        $return = utils::o2a($weather);
        $return['cmd'] = array();
        $return['print'] = $weather->toHtml('dashboard');
        foreach ($weather->getCmd() as $cmd) {
            $cmd_info = utils::o2a($cmd);
            $cmd_info['value'] = $cmd->execute();
            $return['cmd'][] = $cmd_info;
        }
        ajax::success($return);
    }

    throw new Exception('Aucune methode correspondante');
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>
