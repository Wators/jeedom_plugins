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

class rfxcom extends eqLogic {
    /*     * *************************Attributs****************************** */




    /*     * ***********************Methode static*************************** */

    public static function createFromDef($_def) {
        if (!isset($_def['packettype']) || !isset($_def['subtype']) || !isset($_def['id'])) {
            log::add('rfxcom', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($_def, true));
            return;
        }
        $device = self::devicesParameters($_def['packettype']);
        if (!isset($device['subtype'][$_def['subtype']])) {
            log::add('rfxcom', 'error', 'Sous-type non trouvé : ' . print_r($_def, true) . ' dans : ' . print_r($device, true));
            return;
        }
        $rfxcom = rfxcom::byLogicalId($_def['id'], 'rfxcom');
        if (count($rfxcom) > 0) {
            $rfxcom = $rfxcom[0];
        }
        if (!is_object($rfxcom)) {
            $eqLogic = new rfxcom();
            $eqLogic->setName($_def['id']);
        }
        $eqLogic->setLogicalId($_def['id']);
        $eqLogic->setEqType_name('rfxcom');
        $eqLogic->setIsEnable(1);
        $eqLogic->setIsVisible(1);
        $eqLogic->setConfiguration('device', $_def['packettype'] . '::' . $_def['subtype']);
        $eqLogic->save();
        $eqLogic->applyModuleConfiguration();
    }

    public static function devicesParameters($_device = '') {
        $path = dirname(__FILE__) . '/../config/devices';
        if (isset($_device) && $_device != '') {
            $files = ls($path, $_device . '.php', false, array('files', 'quiet'));
            if (count($files) == 1) {
                global $deviceConfiguration;
                require_once($path . '/' . $files[0]);
                return $deviceConfiguration[$_device];
            }
        }
        $files = ls($path, '*.php', false, array('files', 'quiet'));
        $return = array();
        foreach ($files as $file) {
            global $deviceConfiguration;
            require_once($path . '/' . $file);
            $return = $return + $deviceConfiguration;
        }
        if (isset($_device) && $_device != '') {
            if (isset($return[$_device])) {
                return $return[$_device];
            }
            return array();
        }
        return $return;
    }

    public static function cron() {
        if (self::deamonRunning()) {
            return;
        }
        $port = config::byKey('port', 'rfxcom');
        if ($port == '') {
            return;
        }
        if (!file_exists($port)) {
            log::add('rfxcom', 'error', 'Le port : ' . $port . ' n\'éxiste pas');
            config::save('port', '', 'rfxcom');
        }
        $path = realpath(dirname(__FILE__) . '/../php/jeeRfxcom.php');
        $rfxcom_path = realpath(dirname(__FILE__) . '/../../ressources/rfxcmd');
        $trigger = file_get_contents($rfxcom_path . '/trigger_tmpl.xml');
        $pid_file = realpath(dirname(__FILE__) . '/../../../../tmp') . '/rfxcom.pid';
        if (file_exists($rfxcom_path . '/trigger.xml')) {
            unlink($rfxcom_path . '/trigger.xml');
        }
        file_put_contents($rfxcom_path . '/trigger.xml', str_replace('#path#', $path, $trigger));
        chmod($rfxcom_path . '/trigger.xml', 0777);
        log::add('rfxcom', 'info', '/usr/bin/python ' . $rfxcom_path . '/rfxcmd.py -z -d ' . $port . ' --pidfile=' . $pid_file);
        $result = shell_exec('/usr/bin/python ' . $rfxcom_path . '/rfxcmd.py -z -d ' . $port . ' --pidfile=' . $pid_file);
        if (strpos(strtolower($result), 'error') !== false) {
            log::add('rfxcom', 'error', $result);
        }
        echo $result;
    }

    public static function deamonRunning() {
        $pid_file = realpath(dirname(__FILE__) . '/../../../../tmp/rfxcom.pid');
        if (!file_exists($pid_file)) {
            return false;
        }
        $pid = trim(file_get_contents($pid_file));
        if ($pid == '' || !is_numeric($pid)) {
            return false;
        }
        $result = exec('ps -p' . $pid . ' e | grep "rfxcmd" | wc -l');
        if ($result == 0) {
            unlink($pid_file);
            return false;
        }
        return true;
    }

    public static function stopDeamon() {
        if (!self::deamonRunning()) {
            return true;
        }
        $pid_file = dirname(__FILE__) . '/../../../../tmp/rfxcom.pid';
        if (!file_exists($pid_file)) {
            return true;
        }
        $pid = file_get_contents($pid_file);
        exec('kill ' . $pid);
        $check = self::deamonRunning();
        $retry = 0;
        while ($check) {
            $check = self::deamonRunning();
            $retry++;
            if ($retry > 10) {
                $check = false;
            } else {
                sleep(1);
            }
        }
        exec('kill -9 ' . $pid);
        $check = self::deamonRunning();
        $retry = 0;
        while ($check) {
            $check = self::deamonRunning();
            $retry++;
            if ($retry > 10) {
                $check = false;
            } else {
                sleep(1);
            }
        }

        return self::deamonRunning();
    }

    /*     * *********************Methode d'instance************************* */

    public function postSave() {
        if ($this->getConfiguration('applyDevice') != $this->getConfiguration('device')) {
            $this->applyModuleConfiguration();
        }
    }

    public function applyModuleConfiguration() {
        if ($this->getConfiguration('device') == '') {
            return true;
        }
        $this->setConfiguration('applyDevice', $this->getConfiguration('device'));
        $device_type = explode('::', $this->getConfiguration('device'));
        $packettype = $device_type[0];
        $subtype = $device_type[1];
        $device = self::devicesParameters($packettype);
        if (!is_array($device) || !isset($device['subtype'][$subtype])) {
            return true;
        } else {
            $device = $device['subtype'][$subtype];
        }
        if (isset($device['configuration'])) {
            foreach ($device['configuration'] as $key => $value) {
                $this->setConfiguration($key, $value);
            }
        }

        $cmd_order = 0;
        foreach ($device['commands'] as $command) {
            $cmd = null;
            foreach ($this->getCmd() as $liste_cmd) {
                if ($liste_cmd->getConfiguration('logicalId', '') == $command['configuration']['logicalId']) {
                    $cmd = $liste_cmd;
                    break;
                }
            }
            try {
                if ($cmd == null || !is_object($cmd)) {
                    $cmd = new rfxcomCmd();
                    $cmd->setOrder($cmd_order);
                    $cmd->setEqLogic_id($this->getId());
                } else {
                    $command['name'] = $cmd->getName();
                }
                utils::a2o($cmd, $command);
                $cmd->save();
                $cmd_order++;
            } catch (Exception $exc) {
                
            }
        }

        $this->save();
    }

    /*     * **********************Getteur Setteur*************************** */
}

class rfxcomCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */

    /*     * **********************Getteur Setteur*************************** */
}

?>
