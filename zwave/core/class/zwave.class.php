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

class zwave extends eqLogic {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    public static function start() {
        foreach (self::byType('zwave') as $eqLogic) {
            $eqLogic->forceUpdate();
        }
    }

    public static function pull() {
        $cache = cache::byKey('zwave::lastUpdate');
        $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Data/' . $cache->getValue(strtotime(date('Y-m-d H:i:s')) - 86400));
        $results = json_decode(self::handleError($http->exec()), true);
        if (is_array($results)) {
            foreach ($results as $key => $result) {
                switch ($key) {
                    case 'controller.data.controllerState':
                        nodejs::pushUpdate('zwave::' . $key, $result['value']);
                        break;
                    case 'controller.data.lastExcludedDevice' :
                        if ($result['value'] != null) {
                            nodejs::pushNotification('Razberry', 'Un périphérique Z-Wave vient d\'être exclu. Logical ID : ' . $result['value']);
                            self::syncEqLogicWithRazberry();
                        }
                        break;
                    case 'controller.data.lastIncludedDevice' :
                        if ($result['value'] != null) {
                            nodejs::pushNotification('Razberry', 'Un périphérique Z-Wave vient d\'être inclu. Logical ID : ' . $result['value']);
                            self::syncEqLogicWithRazberry();
                        }
                        break;
                    default:
                        $explodeKey = explode('.', $key);
                        if (count($explodeKey) > 5) {
                            $nodeId = intval($explodeKey[1]);
                            $instanceId = intval($explodeKey[3]);
                            $class = intval($explodeKey[5]);
                            for ($i = 0; $i < 6; $i++) {
                                array_shift($explodeKey);
                            }
                            $attribut = implode('.', $explodeKey);
                            foreach (self::byLogicalId($nodeId, 'zwave') as $eqLogic) {
                                foreach ($eqLogic->getCmd() as $cmd) {
                                    if ($cmd->getConfiguration('instanceId') == $instanceId && $cmd->getConfiguration('class') == '0x' . dechex($class)) {
                                        $configurationValue = $cmd->getConfiguration('value');
                                        if (strpos($configurationValue, '[') !== false && strpos($configurationValue, ']') !== false) {
                                            $configurationValue = str_replace(']', '', str_replace('[', '.', $configurationValue));
                                        }
                                        if (strpos($configurationValue, $attribut) !== false) {
                                            if (isset($result['val'])) {
                                                $value = zwaveCmd::handleResult($result['val']);
                                            } else if (isset($result['level'])) {
                                                $value = zwaveCmd::handleResult($result['level']);
                                            } else {
                                                $value = zwaveCmd::handleResult($result);
                                            }
                                            if ($value === '') {
                                                log::add('zwave', 'info', 'Event sur ' . $cmd->getId() . ' / ' . $cmd->getName() . ' mais aucun valeur trouvée. Event result :' . print_r($result, true));
                                                $value = $cmd->execute();
                                            }
                                            $cmd->event($value);
                                        }
                                    }
                                }
                            }
                        }
                        break;
                }
            }
        }
        if (isset($results['updateTime']) && is_numeric($results['updateTime']) && $results['updateTime'] > $cache->getValue(0)) {
            cache::set('zwave::lastUpdate', $results['updateTime'], 0);
        }
    }

    public static function syncEqLogicWithRazberry() {
        $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Data/0');
        $results = json_decode(self::handleError($http->exec()), true);
        foreach ($results['devices'] as $nodeId => $result) {
            if ($nodeId != 1) {
                $data = $result['data'];
                if (count(self::byLogicalId($nodeId, 'zwave')) == 0 || $nodeId == 2) {
                    $eqLogic = new eqLogic();
                    $eqLogic->setEqType_name('zwave');
                    $eqLogic->setIsEnable(1);
                    $eqLogic->setName('Device ' . $nodeId);
                    $eqLogic->setLogicalId($nodeId);
                    $eqLogic->setIsVisible(1);
                    $eqLogic->save();

                    /* Demande du niveau de batterie */
                    try {
                        $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $eqLogic->getLogicalId() . '].instances[0].commandClasses[0x80].Get()');
                        $http->exec();
                    } catch (Exception $exc) {
                        
                    }

                    /* Reconnaissance du module */
                    foreach (self::devicesParameters() as $device_id => $device) {
                        if ($device['manufacturerId'] == $data['manufacturerId']['value'] && $device['manufacturerProductType'] == $data['manufacturerProductType']['value'] && $device['manufacturerProductId'] == $data['manufacturerProductId']['value']) {
                            $eqLogic->setConfiguration('device', $device_id);
                            $eqLogic->save();
                            $eqLogic->applyModuleConfiguration();
                            break;
                        }
                    }
                }
            }
        }
    }

    public static function changeIncludeState($_mode, $_state) {
        if ($_mode == 1) {
            $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/controller.AddNodeToNetwork(' . $_state . ')');
        } else {
            $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/controller.RemoveNodeFromNetwork(' . $_state . ')');
        }
        self::handleError($http->exec());
    }

    public static function makeBaseUrl() {
        return 'http://' . config::byKey('zwaveAddr', 'zwave') . ':8083';
    }

    public static function getCommandClassInfo($_class) {
        global $listClassCommand;
        include_file('core', 'class.command', 'config', 'zwave');
        if (isset($listClassCommand[$_class])) {
            return $listClassCommand[$_class];
        }
        return array();
    }

    public static function handleError($_result) {
        if (strpos($_result, 'Error 500: Internal Server Error') === 0) {
            throw new Exception('Echec de la commande : ' . $_result);
        }
        return $_result;
    }

    public static function cron() {
        //Rafraichissement des valeurs des modules
        foreach (eqLogic::byType('zwave') as $eqLogic) {
            $scheduler = $eqLogic->getConfiguration('refreshDelay', '');
            if ($scheduler != '') {
                try {
                    $c = new Cron\CronExpression($scheduler, new Cron\FieldFactory);
                    if ($c->isDue()) {
                        try {
                            foreach ($eqLogic->getCmd() as $cmd) {
                                $cmd->forceUpdate();
                            }
                        } catch (Exception $exc) {
                            log::add('zwave', 'error', 'Erreur pour ' . $eqLogic->getHumanName() . ' : ' . $exc->getMessage());
                        }
                    }
                } catch (Exception $exc) {
                    log::add('zwave', 'error', 'Expression cron non valide pour ' . $eqLogic->getHumanName() . ' : ' . $scheduler);
                }
            }
        }

        //Verification des piles une fois par jour
        if (date('H:i') == '00:00') {
            foreach (zwave::byType('zwave') as $eqLogic) {
                $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $eqLogic->getLogicalId() . '].instances[0].commandClasses[0x80].Get()');
                try {
                    $http->exec();
                } catch (Exception $exc) {
                    
                }
                $info = $eqLogic->getInfo();
                if (isset($info['state']) && $info['state'] == 'Réveillé') {
                    continue;
                }
                if (isset($info['battery']) && $info['battery'] !== '') {
                    $eqLogic->batteryStatus($info['battery']['value']);
                }
            }
        }
    }

    public static function inspectQueue() {
        $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/InspectQueue');
        $results = json_decode(self::handleError($http->exec()), true);
        $return = array();
        foreach ($results as $result) {
            $queue = array();
            $queue['timeout'] = $result[0];
            $queue['id'] = $result[2];
            $eqLogic = zwave::byLogicalId($queue['id'], 'zwave');
            if (is_object($eqLogic[0])) {
                $queue['name'] = $eqLogic[0]->getHumanName();
            } else {
                $queue['name'] = '';
            }
            $queue['description'] = $result[3];
            $queue['status'] = $result[4];
            if ($queue['status'] == null) {
                $queue['status'] = '';
            }
            $status = $result[1];
            if ($status[1] == 1) {
                $queue['status'] .= ' [Wait wakeup]';
            }
            $queue['sendCount'] = $status[0];
            $return[] = $queue;
        }
        return $return;
    }

    public static function getRoutingTable() {
        $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Data/0');
        $results = json_decode(self::handleError($http->exec()), true);
        $return = array();
        foreach ($results['devices'] as $id => $device) {
            $return[$id] = $device;
            if ($id == 1) {
                $return[$id]['name'] = 'Razberry';
            } else {
                $eqLogic = zwave::byLogicalId($id, 'zwave');
                if (is_object($eqLogic[0])) {
                    $return[$id]['name'] = $eqLogic[0]->getHumanName();
                } else {
                    $return[$id]['name'] = '';
                }
            }
            $return[$id]['data']['neighbours']['datetime'] = date('Y-m-d H:i:s', $return[$id]['data']['neighbours']['updateTime']);
        }
        return $return;
    }

    public static function updateRoute() {
        $url = self::makeBaseUrl() . '/ZWaveAPI/Run/';
        $http = new com_http($url . 'controller.RequestNetworkUpdate()');
        self::handleError($http->exec());
        foreach (eqLogic::byType('zwave') as $eqLogic) {
            $http = new com_http($url . 'devices[' . $eqLogic->getLogicalId() . '].RequestNodeNeighbourUpdate()');
            self::handleError($http->exec());
        }
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
            $return = array_merge($return, $deviceConfiguration);
        }
        if (isset($_device) && $_device != '') {
            if (isset($return[$_device])) {
                return $return[$_device];
            }
            return array();
        }
        return $return;
    }

    public static function shareOnMarket(&$market) {
        $moduleFile = dirname(__FILE__) . '/../config/devices/' . $market->getLogicalId() . '.php';
        if (!file_exists($moduleFile)) {
            throw new Exception('Impossible de trouver le widget ' . $moduleFile);
        }
        $tmp = dirname(__FILE__) . '/../../../../tmp/' . $market->getLogicalId() . '.zip';
        if (!create_zip($moduleFile, $tmp)) {
            throw new Exception('Echec de création du zip. Répertoire source : ' . $moduleFile . ' / Répertoire cible : ' . $tmp);
        }
        return $tmp;
    }

    public static function getFromMarket(&$market, $_path) {
        $cibDir = dirname(__FILE__) . '/../config/devices/';
        if (!file_exists($cibDir)) {
            throw new Exception('Impossible d\'installer la configuration du module le repertoire n\éxiste pas : ' . $cibDir);
        }
        $zip = new ZipArchive;
        if ($zip->open($_path) === TRUE) {
            $zip->extractTo($cibDir . '/');
            $zip->close();
        } else {
            throw new Exception('Impossible de décompresser le zip : ' . $_path);
        }
        $moduleFile = dirname(__FILE__) . '/../config/devices/' . $market->getLogicalId() . '.php';
        if (!file_exists($moduleFile)) {
            throw new Exception('Echec de l\'installation. Impossible de trouver le module ' . $moduleFile);
        }

        foreach (eqLogic::byTypeAndSearhConfiguration('zwave', $market->getLogicalId()) as $eqLogic) {
            $eqLogic->applyModuleConfiguration();
        }
    }

    public static function removeFromMarket(&$market) {
        $moduleFile = dirname(__FILE__) . '/../config/devices/' . $market->getLogicalId() . '.php';
        if (!file_exists($moduleFile)) {
            throw new Exception('Echec lors de la suppression. Impossible de trouver le module ' . $moduleFile);
        }
        unlink($moduleFile);
    }

    /*     * *********************Methode d'instance************************* */

    public function forceUpdate() {
        foreach ($this->getCmd() as $cmd) {
            try {
                $cmd->forceUpdate();
            } catch (Exception $e) {
                
            }
        }
    }

    public function postSave() {
        if ($this->getConfiguration('applyDevice') != $this->getConfiguration('device')) {
            $this->applyModuleConfiguration();
        }
    }

    public function getAvailableCommandClass() {
        $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getLogicalId() . '].commandClasses');
        $results = json_decode(self::handleError($http->exec()), true);
        $return = array();
        foreach ($results as $class => $value) {
            $return[] = '0x' . dechex(intval($class));
        }
        return $return;
    }

    public function ping() {
        $info = $this->getInfo();
        if ($info['state']['value'] == 'Réveillé') {
            $cmds = $this->getCmd();
            $cmds[0]->forceUpdate();
            if ($this->getStatus('lastCommunication', date('Y-m-d H:i:s')) < date('Y-m-d H:i:s', strtotime('-2 minutes' . date('Y-m-d H:i:s')))) {
                sleep(5);
            }
            if ($this->getStatus('lastCommunication', date('Y-m-d H:i:s')) < date('Y-m-d H:i:s', strtotime('-2 minutes' . date('Y-m-d H:i:s')))) {
                return false;
            }
        } else {
            if ($this->getStatus('lastCommunication', date('Y-m-d H:i:s')) < date('Y-m-d H:i:s', strtotime('-' . $this->getTimeout() . ' minutes' . date('Y-m-d H:i:s')))) {
                return false;
            }
        }
        return true;
    }

    public function getInfo() {
        $return = array();
        if (!is_numeric($this->getLogicalId())) {
            return $return;
        }
        $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getLogicalId() . ']');
        $results = json_decode(self::handleError($http->exec()), true);
        if (isset($results['instances'])) {
            if (isset($results['instances'][0])) {
                if (isset($results['instances'][0]['commandClasses'])) {
                    if (isset($results['instances'][0]['commandClasses'][128])) {
                        $return['battery'] = array(
                            'value' => $results['instances'][0]['commandClasses'][128]['data']['last']['value'],
                            'datetime' => date('Y-m-d H:i:s', $results['instances'][0]['commandClasses'][128]['data']['last']['updateTime']),
                            'unite' => '%',
                        );
                    }
                }
            }
        }
        if (isset($results['data'])) {
            if (isset($results['data']['isAwake'])) {
                $return['state'] = array(
                    'value' => ($results['data']['isAwake']['value']) ? 'Réveillé' : 'Endormi',
                    'datetime' => date('Y-m-d H:i:s', $results['data']['isAwake']['updateTime']),
                );
            }
            if (isset($results['data']['vendorString'])) {
                $return['brand'] = array(
                    'value' => $results['data']['vendorString']['value'],
                    'datetime' => date('Y-m-d H:i:s', $results['data']['vendorString']['updateTime']),
                );
            }
            if (isset($results['data']['lastReceived'])) {
                $return['lastReceived'] = array(
                    'value' => date('Y-m-d H:i:s', $results['data']['lastReceived']['updateTime']),
                    'datetime' => date('Y-m-d H:i:s', $results['data']['lastReceived']['updateTime']),
                );
            }
        }
        return $return;
    }

    public function getDeviceConfiguration($_forcedRefresh = false, $_parameters_id = null) {
        if ($_parameters_id == null) {
            $device = zwave::devicesParameters($this->getConfiguration('device'));
            if (!is_array($device) || count($device) == 0) {
                throw new Exception('Equipement inconnu : ' . $this->getConfiguration('device'));
            }
        } else {
            $device = array(
                'parameters' => array(
                    $_parameters_id => array()
                ),
            );
        }
        $needRefresh = false;
        if ($_forcedRefresh) {
            $needRefresh = true;
            foreach ($device['parameters'] as $id => $parameter) {
                $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getLogicalId() . '].commandClasses[0x70].Get(' . $id . ')');
                self::handleError($http->exec());
            }
            sleep(1);
        }

        $return = array();
        $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getLogicalId() . '].commandClasses[0x70].data');
        $data = json_decode(self::handleError($http->exec()), true);
        foreach ($device['parameters'] as $id => $parameter) {
            if (isset($data[$id])) {
                $return[$id] = array();
                $return[$id]['value'] = $data[$id]['val']['value'];
                $return[$id]['datetime'] = date('Y-m-d H:i:s', $data[$id]['val']['updateTime']);
                $return[$id]['size'] = $data[$id]['size']['value'];
            } else {
                $needRefresh = true;
                try {
                    $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getLogicalId() . '].commandClasses[0x70].Get(' . $id . ')');
                    self::handleError($http->exec());
                } catch (Exception $e) {
                    
                }
            }
        }
        if ($needRefresh) {
            sleep(1);
            $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getLogicalId() . '].commandClasses[0x70].data');
            $data = json_decode(self::handleError($http->exec()), true);
            foreach ($device['parameters'] as $id => $parameter) {
                if (isset($data[$id])) {
                    $return[$id] = array();
                    $return[$id]['value'] = $data[$id]['val']['value'];
                    $return[$id]['datetime'] = date('Y-m-d H:i:s', $data[$id]['val']['updateTime']);
                    $return[$id]['size'] = $data[$id]['size']['value'];
                }
            }
        }

        return $return;
    }

    public function setDeviceConfiguration($_configurations) {
        $url = self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getLogicalId() . '].commandClasses[0x70].Set(';
        foreach ($_configurations as $id => $configuration) {
            if (isset($configuration['size']) && isset($configuration['value']) && is_numeric($configuration['size']) && is_numeric($configuration['value'])) {
                $http = new com_http($url . $id . ',' . $configuration['value'] . ',' . $configuration['size'] . ')');
                self::handleError($http->exec());
                $http = new com_http(self::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getLogicalId() . '].commandClasses[0x70].Get(' . $id . ')');
                self::handleError($http->exec());
            }
        }
        return true;
    }

    public function applyModuleConfiguration() {
        if ($this->getConfiguration('device') == '') {
            return true;
        }
        $device = self::devicesParameters($this->getConfiguration('device'));
        if (!is_array($device) || !isset($device['commands'])) {
            return true;
        }
        if (isset($device['configuration'])) {
            foreach ($device['configuration'] as $key => $value) {
                $this->setConfiguration($key, $value);
            }
        }
        $this->setConfiguration('applyDevice', $this->getConfiguration('device'));

        $cmd_order = 0;
        $link_cmds = array();
        foreach ($device['commands'] as $command) {
            if (!isset($command['configuration']['instanceId'])) {
                $command['configuration']['instanceId'] = 0;
            }
            $cmd = null;
            foreach ($this->getCmd() as $liste_cmd) {
                if ($liste_cmd->getConfiguration('instanceId', 0) == $command['configuration']['instanceId'] &&
                        $liste_cmd->getConfiguration('class') == $command['configuration']['class'] &&
                        $liste_cmd->getConfiguration('value') == $command['configuration']['value']) {
                    $cmd = $liste_cmd;
                    break;
                }
            }

            try {
                if ($cmd == null || !is_object($cmd)) {
                    $cmd = new zwaveCmd();
                    $cmd->setOrder($cmd_order);
                    $cmd->setEqLogic_id($this->getId());
                } else {
                    $command['name'] = $cmd->getName();
                }
                utils::a2o($cmd, $command);
                if (isset($command['value'])) {
                    $cmd->setValue(null);
                }
                $cmd->save();
                if (isset($command['value'])) {
                    $link_cmds[$cmd->getId()] = $command['value'];
                }
                $cmd_order++;
            } catch (Exception $exc) {
                error_log($exc->getMessage());
            }
        }
        if (count($link_cmds) > 0) {
            foreach ($this->getCmd() as $eqLogic_cmd) {
                foreach ($link_cmds as $cmd_id => $link_cmd) {
                    if ($link_cmd == $eqLogic_cmd->getName()) {
                        $cmd = cmd::byId($cmd_id);
                        if (is_object($cmd)) {
                            $cmd->setValue($eqLogic_cmd->getId());
                            $cmd->save();
                        }
                    }
                }
            }
        }

        $this->save();
    }

    /*     * **********************Getteur Setteur*************************** */
}

class zwaveCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    public static function handleResult($_val) {
        if (!is_array($_val)) {
            return '';
        }
        if (!isset($_val['value'])) {
            return '';
        }
        $value = $_val['value'];
        switch ($_val['type']) {
            case 'float':
                $value = round(floatval($value), 1);
                break;
            case 'int':
                $value = intval($value);
                break;
            case 'bool':
                if ($value === true || $value == 'true') {
                    $value = 1;
                } else {
                    $value = 0;
                }
                break;
            default:
                break;
        }
        return $value;
    }

    /*     * *********************Methode d'instance************************* */

    public function setRGBColor($_color) {
        if ($_color == '') {
            throw new Exception('Couleur non défini');
        }
        $request = zwave::makeBaseUrl() . '/ZWaveAPI/Run/';
        $request .= 'devices[' . $this->getEqLogic()->getLogicalId() . ']';

        $hex = str_replace("#", "", $_color);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        //Convertion pour sur une echelle de 0-99
        $r = ($r / 255) * 99;
        $g = ($g / 255) * 99;
        $b = ($b / 255) * 99;

        /* Set RED color */
        $http = new com_http($request . '.instances[2].commandClasses[0x26].Set(' . $r . ')');
        zwave::handleError($http->exec());

        /* Set GREEN color */
        $http = new com_http($request . '.instances[3].commandClasses[0x26].Set(' . $g . ')');
        zwave::handleError($http->exec());

        /* Set BLUE color */
        $http = new com_http($request . '.instances[4].commandClasses[0x26].Set(' . $b . ')');
        zwave::handleError($http->exec());

        return true;
    }

    public function postSave() {
        try {
            $this->forceUpdate();
        } catch (Exception $exc) {
            
        }
    }

    public function forceUpdate() {
        $url = zwave::makeBaseUrl() . '/ZWaveAPI/Run/devices[' . $this->getEqLogic()->getLogicalId() . ']';
        $http = new com_http($url . '.instances[' . $this->getConfiguration('instanceId', 0) . '].commandClasses[' . $this->getConfiguration('class') . '].Get()');
        zwave::handleError($http->exec());
    }

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
                        return $this->setRGBColor($value);
                        break;
                }
                break;
        }

        $request = zwave::makeBaseUrl() . '/ZWaveAPI/Run/';
        $request .= 'devices[' . $this->getEqLogic()->getLogicalId() . ']';
        if ($this->getConfiguration('instanceId') != '') {
            $request .= '.instances[' . $this->getConfiguration('instanceId') . ']';
        }
        $request .= '.commandClasses[' . $this->getConfiguration('class') . ']';
        $request .= '.' . $value;
        $http = new com_http($request);
        $result = zwave::handleError($http->exec(1, 3, true));
        if (is_json($result)) {
            $result = json_decode($result, true);
            $value = self::handleResult($result);
            if (isset($result['updateTime'])) {
                $this->setCollectDate(date('Y-m-d H:i:s', $result['updateTime']));
            }
        } else {
            $value = $result;
            if ($value === true || $value == 'true') {
                return 1;
            }
            if ($value === false || $value == 'false') {
                return 0;
            }
            if (is_numeric($value)) {
                return round($value, 1);
            }
        }
        return $value;
    }

    /*     * **********************Getteur Setteur*************************** */
}
