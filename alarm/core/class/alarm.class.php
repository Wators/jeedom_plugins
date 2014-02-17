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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class alarm extends eqLogic {
    /*     * *************************Attributs****************************** */

    public function postInsert() {
        $cmd = new alarmCmd();
        $cmd->setName('Armer');
        $cmd->setEqLogic_id($this->id);
        $cmd->setType('info');
        $cmd->setorder(1);
        $cmd->setSubType('binary');
        $cmd->setEventOnly(1);
        $cmd->save();
        $this->setConfiguration('cmd_armed_id', $cmd->getId());
        $this->save();
        $cmd_armed_id = $cmd->getId();

        $cmd = new alarmCmd();
        $cmd->setName('Status');
        $cmd->setEqLogic_id($this->id);
        $cmd->setType('info');
        $cmd->setorder(1);
        $cmd->setSubType('binary');
        $cmd->setEventOnly(1);
        $cmd->save();
        $this->setConfiguration('cmd_state_id', $cmd->getId());
        $this->save();

        $cmd = new alarmCmd();
        $cmd->setName('Armer');
        $cmd->setEqLogic_id($this->id);
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setorder(2);
        $cmd->setConfiguration('state', '1');
        $cmd->setConfiguration('cmd_armed_id', $cmd_armed_id);
        $cmd->save();

        $cmd = new alarmCmd();
        $cmd->setName('Libérer');
        $cmd->setEqLogic_id($this->id);
        $cmd->setType('action');
        $cmd->setorder(3);
        $cmd->setSubType('other');
        $cmd->setConfiguration('state', '0');
        $cmd->setConfiguration('cmd_armed_id', $cmd_armed_id);
        $cmd->save();
    }

    public function postUpdate() {
        if ($this->getIsEnable() == 1) {
            $cmd = cmd::byId($this->getConfiguration('cmd_state_id'));
            $cmd->event(0);
            $cmd = cmd::byId($this->getConfiguration('cmd_armed_id'));
            $cmd->event(0);
        }
    }

    /*     * ***********************Methode static*************************** */



    /*     * *********************Methode d'instance************************* */
}

class alarmCmd extends cmd {
    /*     * *************************Attributs****************************** */

    public function dontRemoveCmd() {
        return true;
    }

    public function preSave() {
        
    }

    public function execute($_options = array()) {
        if ($this->getConfiguration('cmd_armed_id') != '') {
            $cmd_armed = cmd::byId($this->getConfiguration('cmd_armed_id'));
            $cmd_armed->event($this->getConfiguration('state'));
        }
    }

    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */
}

?>