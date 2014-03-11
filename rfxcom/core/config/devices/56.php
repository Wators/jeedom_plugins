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
    '56' => array(
        'name' => 'Vent',
        'subtype' => array(
            '01' => array(
                'name' => 'Defaut',
                'commands' => array(
                    array('name' => 'Moyenne', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => 'Km/h', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'average')
                    ),
                    array('name' => 'Rafales', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => 'Km/h', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'windgust')
                    ),
                    array('name' => 'Batterie', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 0, 'isHistorized' => 0, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'battery')
                    ),
                    array('name' => 'Signal', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 0, 'isHistorized' => 0, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'signal')
                    ),
                )
            ),
            '04' => array(
                'name' => 'Avec température et refroidissement',
                'commands' => array(
                    array('name' => 'Température', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => '°C', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'temperature')
                    ),
                    array('name' => 'Moyenne', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => 'km/h', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'average')
                    ),
                    array('name' => 'Refroidissement', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => '°C', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'windchill')
                    ),
                    array('name' => 'Rafales', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => 'Km/h', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'windgust')
                    ),
                    array('name' => 'Batterie', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 0, 'isHistorized' => 0, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'battery')
                    ),
                    array('name' => 'Signal', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 0, 'isHistorized' => 0, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'signal')
                    ),
                )
            ),
            '05' => array(
                'name' => 'Sans moyenne',
                'commands' => array(
                    array('name' => 'Température', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => '°C', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'temperature')
                    ),
                    array('name' => 'Refroidissement', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => '°C', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'windchill')
                    ),
                    array('name' => 'Rafales', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 1, 'isHistorized' => 1, 'unite' => 'Km/h', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'windgust')
                    ),
                    array('name' => 'Batterie', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 0, 'isHistorized' => 0, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'battery')
                    ),
                    array('name' => 'Signal', 'type' => 'info', 'subtype' => 'numeric', 'isVisible' => 0, 'isHistorized' => 0, 'unite' => '', 'eventOnly' => 1,
                        'configuration' => array('logicalId' => 'signal')
                    ),
                )
            )
        )
    )
);
?>
