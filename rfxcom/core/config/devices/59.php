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

global $deviceConfiguration;
$deviceConfiguration = array(
    '59' => array(
        'name' => 'Courant',
        'subtype' => array(
            '01' => array(
                'name' => 'Defaut',
                'commands' => array(
                    array('name' => 'Compteur', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'counter')
                    ),
                    array('name' => 'Canal 1', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'channel1')
                    ),
                    array('name' => 'Canal 2', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'channel2')
                    ),
                    array('name' => 'Canal 3', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'channel3')
                    ),
                    array('name' => 'Batterie', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 0, 'isHistorized' => 0, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'battery')
                    ),
                    array('name' => 'Signal', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 0, 'isHistorized' => 0, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'signal')
                    ),
                )
            ),
        )
    )
);
?>
