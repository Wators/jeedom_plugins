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
            log::add('rfxcom', 'info', 'Sous-type non trouvé : ' . print_r($_def, true) . ' dans : ' . print_r($device, true));
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
        if (date('H:i') == '00:00') {
            foreach (self::byType('rfxcom') as $eqLogic) {
                foreach ($eqLogic->getCmd() as $cmd) {
                    if ($cmd->getConfiguration('logicalId') == 'battery') {
                        $battery = $cmd->execCmd();
                        if (is_numeric($battery) && $battery !== '') {
                            $eqLogic->batteryStatus($battery * 10);
                        }
                    }
                }
            }
        }
        if (!self::deamonRunning()) {
            $port = config::byKey('port', 'rfxcom');
            if ($port != '') {
                self::runDeamon();
            }
        }
    }

    public static function runDeamon() {
        log::add('rfxcom', 'info', 'Lancement du démon RFXcom');
        $port = config::byKey('port', 'rfxcom');
        if (!file_exists($port)) {
            config::save('port', '', 'rfxcom');
            throw new Exception('Le port : ' . $port . ' n\'éxiste pas');
        }
        $rfxcom_path = realpath(dirname(__FILE__) . '/../../ressources/rfxcmd');
        $trigger = file_get_contents($rfxcom_path . '/trigger_tmpl.xml');
        $config = file_get_contents($rfxcom_path . '/config_tmpl.xml');
        $pid_file = realpath(dirname(__FILE__) . '/../../../../tmp') . '/rfxcom.pid';
        if (file_exists($rfxcom_path . '/trigger.xml')) {
            unlink($rfxcom_path . '/trigger.xml');
        }
        if (file_exists($rfxcom_path . '/config.xml')) {
            unlink($rfxcom_path . '/config.xml');
        }
        file_put_contents($rfxcom_path . '/trigger.xml', str_replace('#path#', $rfxcom_path . '/../../core/php/jeeRfxcom.php', $trigger));
        $config = str_replace('#log_path#', log::getPathToLog('rfxcmd'), str_replace('#trigger_path#', $rfxcom_path . '/trigger.xml', $config));
        file_put_contents($rfxcom_path . '/config.xml', $config);
        chmod($rfxcom_path . '/trigger.xml', 0777);
        chmod($rfxcom_path . '/config.xml', 0777);

        $cmd = '/usr/bin/python ' . $rfxcom_path . '/rfxcmd.py -z -d ' . $port;
        $cmd .= ' -o ' . $rfxcom_path . '/config.xml --pidfile=' . $pid_file;
        $result = exec('nohup ' . $cmd . ' >> /dev/null 2>&1 &');
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            log::add('rfxcom', 'error', $result);
            return false;
        }
        if (!self::deamonRunning()) {
            sleep(20);
            if (!self::deamonRunning()) {
                log::add('rfxcom', 'info', 'Impossible de lancer le démon RFXcom');
                return false;
            }
        }
        log::add('rfxcom', 'info', 'Démon RFXcom lancé');
    }

    public static function deamonRunning() {
        $pid_file = realpath(dirname(__FILE__) . '/../../../../tmp/rfxcom.pid');
        if (!file_exists($pid_file)) {
            $pid = jeedom::retrievePidThread('rfxcmd.py');
            if ($pid != '' && is_numeric($pid)) {
                exec('kill -9 ' . $pid);
            }
            return false;
        }
        $pid = trim(file_get_contents($pid_file));
        if ($pid == '' || !is_numeric($pid)) {
            $pid = jeedom::retrievePidThread('rfxcmd.py');
            if ($pid != '' && is_numeric($pid)) {
                exec('kill -9 ' . $pid);
            }
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
        $this->save();
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

    public function execute($_options = null) {
        $value = $this->getConfiguration('value');
        switch ($this->getType()) {
            case 'action' :
                switch ($this->getSubType()) {
                    case 'slider':
                        $value = str_replace('#slider#', $_options['slider'], $value);
                        break;
                    case 'color':
                        $value = str_replace('#color#', $_options['color'], $value);
                        break;
                }
                break;
        }
        $rfxcom_path = realpath(dirname(__FILE__) . '/../../ressources/rfxcmd');
        $result = shell_exec('/usr/bin/python ' . $rfxcom_path . '/rfxsend.py -s localhost -p 55000 -r ' . $rfxcom_path . ' 2>&1');
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            throw new Exception('Erreur sur l\'éxecution de la commande : ' . $this->getHumanName() . ' : ' . $result);
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
