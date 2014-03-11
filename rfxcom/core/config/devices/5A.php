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
    '5A' => array(
        'name' => 'Energie',
        'subtype' => array(
            '01' => array(
                'name' => 'Defaut',
                'commands' => array(
                    array('name' => 'Puissance', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => 'W', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'instant')
                    ),
                    array('name' => 'Consommation', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => 'kWh', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'total')
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
