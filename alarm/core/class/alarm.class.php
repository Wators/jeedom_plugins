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

    /*     * ***********************Methode static*************************** */

    public static function pull() {
        $events = internalEvent::getNewInternalEvent('alarm');
        foreach ($events as $event) {
            if ($event->getEvent() == 'event::cmd') {
                $cmd_id = $event->getOptions('id');
                if (is_numeric($cmd_id)) {
                    $eqLogics = eqLogic::byTypeAndSearhConfiguration('alarm', '#' . $cmd_id . '#');
                    if (is_array($eqLogics) && count($eqLogics) != 0) {
                        foreach ($eqLogics as $eqLogic) {
                            $cmd_armed = cmd::byId($eqLogic->getConfiguration('cmd_armed_id'));
                            if ($cmd_armed->execCmd() == 1) {
                                if ($eqLogic->getConfiguration('cmd_mode_id') != '') {
                                    $cmd_mode = cmd::byId($eqLogic->getConfiguration('cmd_mode_id'));
                                    $mode = json_decode($eqLogic->getConfiguration('mode::' . $cmd_mode->execCmd()),true);
                                    if ($mode != '' && is_array($mode)) {
                                        $trigger = cmd::cmdToValue($mode['trigger']);
                                        $test = new evaluate();
                                        $result = $test->Evaluer($trigger);
                                        if (!is_bool($result)) {
                                            throw new Exception('[Alarme] Erreur evaluation de : ' . $trigger . ' => ' . $result);
                                        }
                                        $cmd_state = cmd::byId($eqLogic->getConfiguration('cmd_state_id'));
                                        $cmd_state->event($result);
                                        if ($result) {
                                            
                                        } else {
                                            
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /*     * *********************Methode d'instance************************* */

    public function preAjax() {
        foreach ($this->getConfiguration() as $key => $value) {
            if (strpos($key, 'mode::') !== false) {
                $this->setConfiguration($key, null);
            }
        }
    }

    public function postSave() {
        $existing_mode = array('Armer', 'Libérer');
        foreach ($this->getConfiguration() as $key => $value) {
            if (strpos($key, 'mode::') !== false) {
                $value = json_decode($value, true);
                $existing_mode[] = $value['name'];
                $find = false;
                foreach ($this->getCmd() as $cmd) {
                    if ($cmd->getName() == $value['name']) {
                        $find = true;
                        break;
                    }
                }
                if (!$find) {
                    $cmd = new alarmCmd();
                    $cmd->setName($value['name']);
                    $cmd->setEqLogic_id($this->id);
                    $cmd->setType('action');
                    $cmd->setSubType('other');
                    $cmd->setConfiguration('mode', '1');
                    $cmd->setConfiguration('state', $value['name']);
                    $cmd->save();
                }
            }
        }

        if ($this->getConfiguration('cmd_mode_id') != '') {
            $cmd_mode = cmd::byId($this->getConfiguration('cmd_mode_id'));
            if ($cmd_mode->execCmd() == '') {
                $cmd_mode->event($value['name']);
            }
        }

        foreach ($this->getCmd() as $cmd) {
            if ($cmd->getType() == 'action' && !in_array($cmd->getName(), $existing_mode)) {
                $cmd->remove();
            }
        }
    }

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
        $cmd->setorder(2);
        $cmd->setSubType('binary');
        $cmd->setEventOnly(1);
        $cmd->save();
        $this->setConfiguration('cmd_state_id', $cmd->getId());
        $this->save();

        $cmd = new alarmCmd();
        $cmd->setName('Mode');
        $cmd->setEqLogic_id($this->id);
        $cmd->setType('info');
        $cmd->setorder(3);
        $cmd->setSubType('string');
        $cmd->setEventOnly(1);
        $cmd->save();
        $this->setConfiguration('cmd_mode_id', $cmd->getId());
        $this->save();

        $cmd = new alarmCmd();
        $cmd->setName('Armer');
        $cmd->setEqLogic_id($this->id);
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setorder(4);
        $cmd->setConfiguration('state', '1');
        $cmd->setConfiguration('armed', '1');
        $cmd->save();

        $cmd = new alarmCmd();
        $cmd->setName('Libérer');
        $cmd->setEqLogic_id($this->id);
        $cmd->setType('action');
        $cmd->setorder(5);
        $cmd->setSubType('other');
        $cmd->setConfiguration('state', '0');
        $cmd->setConfiguration('armed', '1');
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

}

class alarmCmd extends cmd {
    /*     * *************************Attributs****************************** */

    public function dontRemoveCmd() {
        return true;
    }

    public function preSave() {
        
    }

    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic();
        if ($this->getConfiguration('armed') == '1') {
            if ($eqLogic->getConfiguration('cmd_armed_id') != '') {
                $cmd_armed = cmd::byId($eqLogic->getConfiguration('cmd_armed_id'));
                $cmd_armed->event($this->getConfiguration('state'));
            }
        }
        if ($this->getConfiguration('mode') == '1') {
            if ($eqLogic->getConfiguration('cmd_mode_id') != '') {
                $cmd_mode = cmd::byId($eqLogic->getConfiguration('cmd_mode_id'));
                $cmd_mode->event($this->getConfiguration('state'));
            }
        }
    }

    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */
}

?>