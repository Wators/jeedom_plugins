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
                $trigger_id = $event->getOptions('id');
                if (is_numeric($trigger_id)) {
                    $eqLogics = eqLogic::byTypeAndSearhConfiguration('alarm', '#' . $trigger_id . '#');
                    if (is_array($eqLogics) && count($eqLogics) != 0) {
                        foreach ($eqLogics as $eqLogic) {
                            $eqLogic->launch($trigger_id, $event->getOptions('value'));
                        }
                    }
                }
            }
        }
    }

    public static function cron() {
        foreach (eqLogic::byType('alarm') as $eqLogic) {
            $cmd_armed = cmd::byId($eqLogic->getConfiguration('cmd_armed_id'));
            if ($cmd_armed->execCmd() == 1) {
                $eqLogic->ping();
            }
        }
    }

    /*     * *********************Methode d'instance************************* */

    public function postSave() {
        if ($this->getConfiguration('cmd_armed_id') == '') {
            $cmd = new alarmCmd();
            $cmd->setName('Actif');
            $cmd->setEqLogic_id($this->id);
            $cmd->setType('info');
            $cmd->setorder(1);
            $cmd->setSubType('binary');
            $cmd->setEventOnly(1);
            $cmd->save();
            $this->setConfiguration('cmd_armed_id', $cmd->getId());
            $this->save();
        }

        if ($this->getConfiguration('cmd_state_id') == '') {
            $cmd = new alarmCmd();
            $cmd->setName('Status');
            $cmd->setEqLogic_id($this->id);
            $cmd->setType('info');
            $cmd->setorder(2);
            $cmd->setSubType('binary');
            $cmd->setEventOnly(1);
            $cmd->setDisplay('invertBinary', 1);
            $cmd->save();
            $this->setConfiguration('cmd_state_id', $cmd->getId());
            $this->save();
        }

        if ($this->getConfiguration('cmd_immediatState_id') == '') {
            $cmd = new alarmCmd();
            $cmd->setName('Status immédiat');
            $cmd->setEqLogic_id($this->id);
            $cmd->setType('info');
            $cmd->setorder(2);
            $cmd->setSubType('binary');
            $cmd->setIsVisible('binary');
            $cmd->setEventOnly(1);
            $cmd->setDisplay('invertBinary', 1);
            $cmd->save();
            $this->setConfiguration('cmd_immediatState_id', $cmd->getId());
            $this->save();
        }

        $existing_mode = array('Armer', 'Libérer');
        foreach ($this->getConfiguration('modes') as $key => $value) {
            $existing_mode[] = $value['name'];
            $cmd = null;
            foreach ($this->getCmd() as $cmd_list) {
                if ($cmd_list->getName() == $value['name']) {
                    $cmd = $cmd_list;
                    break;
                }
            }
            if ($cmd == null) {
                $cmd = new alarmCmd();
            }
            $cmd->setName($value['name']);
            $cmd->setEqLogic_id($this->id);
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setConfiguration('mode', '1');
            $cmd->setConfiguration('state', $value['name']);
            $cmd->save();
        }

        if ($this->getIsEnable() == 1 && $this->getConfiguration('cmd_mode_id') != '') {
            $cmd_zone = cmd::byId($this->getConfiguration('cmd_mode_id'));
            if ($cmd_zone->execCmd() == '') {
                $cmd_zone->event($value['name']);
            }
        }

        foreach ($this->getCmd() as $cmd) {
            if ($cmd->getType() == 'action' && !in_array($cmd->getName(), $existing_mode)) {
                $cmd->remove();
            }
            if ($cmd->getName() == 'Armer') {
                if ($this->getConfiguration('always_active') == 1) {
                    $cmd->setIsVisible(0);
                } else {
                    $cmd->setIsVisible($this->getConfiguration('armed_visible', 1));
                }
                $cmd->save();
            }
            if ($cmd->getName() == 'Libérer') {
                if ($this->getConfiguration('always_active') == 1) {
                    $cmd->setIsVisible(0);
                } else {
                    $cmd->setIsVisible($this->getConfiguration('free_visible', 1));
                }
                $cmd->save();
            }
        }

        if ($this->getConfiguration('always_active') == 1) {
            $cmd_armed = cmd::byId($this->getConfiguration('cmd_armed_id'));
            $cmd_armed->event(1);
        }


        $cmd_immediateState = cmd::byId($this->getConfiguration('cmd_immediatState_id'));
        if (is_object($cmd_immediateState)) {
            $cmd_immediateState->setIsVisible($this->getConfiguration('immediateState_visible', 1));
            $cmd_immediateState->save();
        }
    }

    public function postInsert() {
        $cmd = new alarmCmd();
        $cmd->setName('Mode');
        $cmd->setEqLogic_id($this->id);
        $cmd->setType('info');
        $cmd->setSubType('string');
        $cmd->setEventOnly(1);
        $cmd->setorder(3);
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
            $cmd_state = cmd::byId($this->getConfiguration('cmd_state_id'));
            if (is_object($cmd_state) && $cmd_state->execCmd() == '') {
                $cmd_state->event(0);
            }
            $cmd_immediatState = cmd::byId($this->getConfiguration('cmd_immediatState_id'));
            if (is_object($cmd_immediatState) && $cmd_immediatState->execCmd() == '') {
                $cmd_immediatState->event(0);
            }
            $cmd_armed = cmd::byId($this->getConfiguration('cmd_armed_id'));
            if (is_object($cmd_armed) && $cmd_armed->execCmd() == '') {
                $cmd_armed->event(0);
            }
        }
    }

    public function launch($_trigger_id, $_value) {
        $cmd = 'nohup php ' . dirname(__FILE__) . '/../../core/php/jeeAlarm.php ';
        $cmd.= ' eqLogic_id=' . $this->getId() . ' trigger_id=' . $_trigger_id . ' value=' . $_value;
        $cmd.= ' >> ' . log::getPathToLog('alarm') . ' 2>&1 &';
        shell_exec($cmd);
        return true;
    }

    public function ping() {
        if ($this->getConfiguration('pingState', 1) != 1) {
            return true;
        }
        log::add('alarm', 'debug', 'Lancement du ping de l\'alarme : ' . $this->getHumanName());
        foreach ($this->getConfiguration('pingTest') as $pingTest) {
            $eqLogic = eqLogic::byId(str_replace('#', '', $pingTest['eqLogic']));
            if (!is_object($eqLogic)) {
                continue;
            }
            log::add('alarm', 'debug', 'Test ping pour : ' . $eqLogic->getHumanName());
            if ($eqLogic->getIsEnable() == 1 && method_exists($eqLogic, 'ping')) {
                if (!$eqLogic->ping()) {
                    log::add('alarm', 'debug', 'Ping NOK sur : ' . $eqLogic->getHumanName());
                    $this->setConfiguration('pingState', 0);
                    $this->save();
                    log::add('alarm', 'debug', 'Alert perte ping éxecution des actions');
                    foreach ($this->getConfiguration('ping') as $action) {
                        $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                        if (is_object($cmd)) {
                            try {
                                log::add('alarm', 'debug', 'Exécution de la commande ' . $cmd->getHumanName());
                                $options = array();
                                if (isset($action['options'])) {
                                    $options = $action['options'];
                                }
                                $cmd->execCmd($options);
                            } catch (Exception $e) {
                                log::add('alarm', 'error', 'Erreur lors de l\'éxecution de ' . $cmd->getHumanName() . '. Détails : ' . $e->getMessage());
                            }
                        }
                    }
                    break;
                } else {
                    log::add('alarm', 'debug', 'Ping OK sur : ' . $eqLogic->getHumanName());
                }
            } else {
                log::add('alarm', 'debug', 'Aucune méthode de ping pour : ' . $eqLogic->getHumanName());
            }
        }
    }

    public function execute($_trigger_id, $_value) {
        log::add('alarm', 'debug', 'Lancement de l\'alarme : ' . $this->getHumanName());
        $cmd_armed = cmd::byId($this->getConfiguration('cmd_armed_id'));
        $cmd_state = cmd::byId($this->getConfiguration('cmd_state_id'));
        $cmd_immediatState = cmd::byId($this->getConfiguration('cmd_immediatState_id'));
        if ($cmd_armed->execCmd() == 1 && $cmd_state->execCmd() != 1) {
            log::add('alarm', 'debug', 'Alarme en cours');
            if ($this->getConfiguration('cmd_mode_id') != '') {
                $cmd_mode = cmd::byId($this->getConfiguration('cmd_mode_id'));
                $select_mode = $cmd_mode->execCmd();
                $modes = $this->getConfiguration('modes');
                foreach ($modes as $mode) {
                    if ($mode['name'] == $select_mode) {
                        log::add('alarm', 'debug', 'Mode actif : ' . $select_mode);
                        $zones = $this->getConfiguration('zones');
                        foreach ($zones as $zone) {
                            if ((!is_array($mode['zone']) && $zone['name'] == $mode['zone']) || (is_array($mode['zone']) && in_array($zone['name'], $mode['zone']))) {
                                log::add('alarm', 'debug', 'Vérification de la zone : ' . $zone['name']);
                                foreach ($zone['triggers'] as $trigger) {
                                    if ($trigger['cmd'] == '#' . $_trigger_id . '#') {
                                        if ($_value == 1 || $_value) {
                                            if ($cmd_immediatState->execCmd() != 1) {
                                                $cmd_immediatState->event(1);
                                                foreach ($zone['actionsImmediate'] as $action) {
                                                    $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                                                    if (is_object($cmd)) {
                                                        try {
                                                            log::add('alarm', 'debug', 'Exécution immediate de la commande ' . $cmd->getHumanName());
                                                            $options = array();
                                                            if (isset($action['options'])) {
                                                                $options = $action['options'];
                                                            }
                                                            $cmd->execCmd($options);
                                                        } catch (Exception $e) {
                                                            log::add('alarm', 'error', 'Erreur lors de l\'éxecution de ' . $cmd->getHumanName() . '. Détails : ' . $e->getMessage());
                                                        }
                                                    }
                                                }
                                            }

                                            if (isset($trigger['armedDelay']) && is_numeric($trigger['armedDelay']) && $trigger['armedDelay'] > 0) {
                                                if (strtotime(date('Y-m-d H:i:s')) < strtotime('+' . $trigger['armedDelay'] . ' second' . $cmd_armed->getCollectDate())) {
                                                    log::add('alarm', 'debug', 'Non déclenchement de l\'alarme car hors delay d\'armement');
                                                    return;
                                                }
                                            }
                                            if (isset($trigger['waitDelay']) && is_numeric($trigger['waitDelay']) && $trigger['waitDelay'] > 0) {
                                                log::add('alarm', 'debug', 'Attente de ' . $trigger['waitDelay'] . ' avant déclenchement');
                                                sleep($trigger['waitDelay']);

                                                if ($cmd_armed->execCmd() == 0) {
                                                    log::add('alarm', 'debug', 'L\'alarme a été désarmé avant déclenchement');
                                                    return;
                                                }
                                            }
                                            log::add('alarm', 'debug', 'Déclenchement de l\alarme');
                                            $cmd_state->event(1);
                                            foreach ($zone['actions'] as $action) {
                                                $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                                                if (is_object($cmd)) {
                                                    try {
                                                        log::add('alarm', 'debug', 'Exécution de la commande ' . $cmd->getHumanName());
                                                        $options = array();
                                                        if (isset($action['options'])) {
                                                            $options = $action['options'];
                                                        }
                                                        $cmd->execCmd($options);
                                                    } catch (Exception $e) {
                                                        log::add('alarm', 'error', 'Erreur lors de l\'éxecution de ' . $cmd->getHumanName() . '. Détails : ' . $e->getMessage());
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
            }
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

                $cmd_state = cmd::byId($eqLogic->getConfiguration('cmd_state_id'));
                $cmd_immediateState = cmd::byId($eqLogic->getConfiguration('cmd_immediatState_id'));
                if ($this->getConfiguration('state') == 0) {
                    $cmd_armed->event($this->getConfiguration('state'));

                    /* RaZ */
                    if ($cmd_state->execCmd() == 1) {
                        log::add('alarm', 'debug', 'Remise à zero de l\'alarme');
                        foreach ($eqLogic->getConfiguration('raz') as $raz) {
                            $cmd = cmd::byId(str_replace('#', '', $raz['cmd']));
                            $option = array();
                            if (isset($raz['options'])) {
                                $option = $raz['options'];
                            }
                            log::add('alarm', 'debug', 'Exécution de ' . $cmd->getHumanName() . ' avec les options : ' . print_r($option, true));
                            if (is_object($cmd)) {
                                try {
                                    $cmd->execCmd($option);
                                } catch (Exception $e) {
                                    log::add('alarm', 'error', 'Erreur lors de l\'éxecution de ' . $cmd->getHumanName() . '. Détails : ' . $e->getMessage());
                                }
                            }
                        }
                    }

                    /* RaZ immediate */
                    if ($cmd_immediateState->execCmd() == 1) {
                        log::add('alarm', 'debug', 'Remise à zero immédiate de l\'alarme');
                        foreach ($eqLogic->getConfiguration('razImmediate') as $razImmediate) {
                            $cmd = cmd::byId(str_replace('#', '', $razImmediate['cmd']));
                            $option = array();
                            if (isset($razImmediate['options'])) {
                                $option = $razImmediate['options'];
                            }
                            log::add('alarm', 'debug', 'Exécution de ' . $cmd->getHumanName() . ' avec les options : ' . print_r($option, true));
                            if (is_object($cmd)) {
                                try {
                                    $cmd->execCmd($option);
                                } catch (Exception $e) {
                                    log::add('alarm', 'error', 'Erreur lors de l\'éxecution de ' . $cmd->getHumanName() . '. Détails : ' . $e->getMessage());
                                }
                            }
                        }
                    }
                    $cmd_state->event(0);
                    $cmd_immediateState->event(0);
                    $eqLogic->setConfiguration('pingState', 1);
                    $eqLogic->save();
                } else {
                    $cmd_armed->event($this->getConfiguration('state'));
                    $cmd_mode = cmd::byId($eqLogic->getConfiguration('cmd_mode_id'));
                    $select_mode = $cmd_mode->execCmd();
                    $modes = $eqLogic->getConfiguration('modes');
                    $zones = $eqLogic->getConfiguration('zones');
                    foreach ($modes as $mode) {
                        if ($mode['name'] == $select_mode) {
                            foreach ($zones as $zone) {
                                if ((!is_array($mode['zone']) && $zone['name'] == $mode['zone']) || (is_array($mode['zone']) && in_array($zone['name'], $mode['zone']))) {
                                    log::add('alarm', 'debug', 'Vérification de la zone : ' . $zone['name']);
                                    foreach ($zone['triggers'] as $trigger) {
                                        $cmd = cmd::byId(str_replace('#', '', $trigger['cmd']));
                                        if (is_object($cmd)) {
                                            log::add('alarm', 'debug', 'Vérification de la commande : ' . $cmd->getHumanName());
                                            $result = $cmd->execCmd();
                                            if ($result == 1) {
                                                $eqLogic->launch($cmd->getId(), $result);
                                                return;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }


                    /*                     * *****************Activation reussi***************** */
                    log::add('alarm', 'debug', 'Activation de l\'alarme réussie');
                    foreach ($eqLogic->getConfiguration('activationOk') as $activationOk) {
                        $cmd = cmd::byId(str_replace('#', '', $activationOk['cmd']));
                        $option = array();
                        if (isset($activationOk['options'])) {
                            $option = $activationOk['options'];
                        }
                        log::add('alarm', 'debug', 'Exécution de ' . $cmd->getHumanName() . ' avec les options : ' . print_r($option, true));
                        if (is_object($cmd)) {
                            try {
                                $cmd->execCmd($option);
                            } catch (Exception $e) {
                                log::add('alarm', 'error', 'Erreur lors de l\'éxecution de ' . $cmd->getHumanName() . '. Détails : ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }


        if ($this->getConfiguration('mode') == '1') {
            if ($eqLogic->getConfiguration('cmd_mode_id') != '') {
                $cmd_zone = cmd::byId($eqLogic->getConfiguration('cmd_mode_id'));
                $cmd_zone->event($this->getConfiguration('state'));
            }
        }
    }

    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */
}

?>