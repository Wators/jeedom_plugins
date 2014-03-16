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

class energy {
    /*     * *************************Attributs****************************** */

    private $id;
    private $eqLogic_id;
    private $category = '';
    private $consumption = '';
    private $power = '';
    private $options = null;

    /*     * ***********************Methode static*************************** */

    public static function byId($_id) {
        $values = array(
            'id' => $_id
        );
        $sql = 'SELECT ' . DB::buildField(__CLASS__) . '
                FROM energy
                WHERE id=:id';
        return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
    }

    public static function byEqLogic_id($_eqLogic_id) {
        $values = array(
            'eqLogic_id' => $_eqLogic_id
        );
        $sql = 'SELECT ' . DB::buildField(__CLASS__) . '
                FROM energy
                WHERE eqLogic_id=:eqLogic_id';
        return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
    }

    public static function getObjectData($_object_id, $_startDate = null, $_endDate = null) {
        $object = object::byId($_object_id);
        if (!is_object($object)) {
            throw new Exception('Objet non trouvé vérifiez l\'id : ' . $_object_id);
        }
        $return = array(
            'real' => array(
                'power' => 0,
                'consumption' => 0,
            ),
            'history' => array(
                'power' => array(),
                'consumption' => null,
            ),
            'details' => array()
        );
        foreach ($object->getEqLogic() as $eqLogic) {
            $energy = self::byEqLogic_id($eqLogic->getId());
            if (is_object($energy)) {
                $datas = $energy->getData($_startDate, $_endDate);
                $details = array(
                    'data' => $datas,
                    'name' => $eqLogic->getHumanName(),
                    'type' => 'eqLogic',
                    'eqLogic' => utils::o2a($eqLogic)
                );
                foreach ($datas['history']['power'] as $datetime => $power) {
                    if (!isset($return['history']['power'][$datetime])) {
                        $return['history']['power'][$datetime] = $power;
                    } else {
                        $return['history']['power'][$datetime] += $power;
                    }
                }
                if (is_array($datas['history']['consumption'])) {
                    foreach ($datas['history']['consumption'] as $datetime => $consumption) {
                        if (!isset($return['history']['consumption'][$datetime])) {
                            $return['history']['consumption'][$datetime] = $consumption;
                        } else {
                            $return['history']['consumption'][$datetime] += $consumption;
                        }
                    }
                }
                $return['details'][] = $details;
                $return['real']['power'] += $datas['real']['power'];
                $return['real']['consumption'] += $datas['real']['consumption'];
            }
        }

        foreach ($object->getChilds() as $child) {
            $datas = self::getObjectData($child->getId(), $_startDate, $_endDate);
            $details = array(
                'data' => $datas,
                'name' => $child->getName(),
                'type' => 'object',
                'object' => utils::o2a($child)
            );
            foreach ($datas['history']['power'] as $datetime => $power) {
                if (!isset($return['history']['power'][$datetime])) {
                    $return['history']['power'][$datetime] = $power;
                } else {
                    $return['history']['power'][$datetime] += $power;
                }
            }

            if (is_array($datas['history']['consumption'])) {
                foreach ($datas['history']['consumption'] as $datetime => $consumption) {
                    if (!isset($return['history']['consumption'][$datetime])) {
                        $return['history']['consumption'][$datetime] = $consumption;
                    } else {
                        $return['history']['consumption'][$datetime] += $consumption;
                    }
                }
            }
            $return['details'][] = $details;
            $return['real']['power'] += $datas['real']['power'];
            $return['real']['consumption'] += $datas['real']['consumption'];
        }

        return $return;
    }

    private static function convertDataForHightCharts($datas) {
        if (is_array($datas)) {
            ksort($datas);
            $return = array();
            foreach ($datas as $datetime => $value) {
                $info_history = array();
                $info_history[] = $datetime;
                $info_history[] = $value;
                $return[] = $info_history;
            }
            return $return;
        }
        return $datas;
        print_r($datas);
    }

    public static function convertForHightCharts($datas) {
        $datas['history']['consumption'] = self::convertDataForHightCharts($datas['history']['consumption']);
        $datas['history']['power'] = self::convertDataForHightCharts($datas['history']['power']);
        foreach ($datas['details'] as &$details) {
            $details['data']['history']['consumption'] = self::convertDataForHightCharts($details['data']['history']['consumption']);
            $details['data']['history']['power'] = self::convertDataForHightCharts($details['data']['history']['power']);
        }
        return $datas;
    }

    /*     * *********************Methode d'instance************************* */

    public function preSave() {
        preg_match_all("/#([0-9]*)#/", $this->getPower(), $matches);
        foreach ($matches[1] as $cmd_id) {
            if (is_numeric($cmd_id)) {
                $cmd = cmd::byId($cmd_id);
                if (is_object($cmd) && $cmd->getIsHistorized() != 1) {
                    $cmd->setIsHistorized(1);
                    $cmd->save();
                }
            }
        }
        preg_match_all("/#([0-9]*)#/", $this->getConsumption(), $matches);
        foreach ($matches[1] as $cmd_id) {
            if (is_numeric($cmd_id)) {
                $cmd = cmd::byId($cmd_id);
                if (is_object($cmd) && $cmd->getIsHistorized() != 1) {
                    $cmd->setIsHistorized(1);
                    $cmd->save();
                }
            }
        }
    }

    public function save() {
        DB::save($this);
    }

    public function preRemove() {
        if ($this->getEqLogic_id() == -1) {
            throw new Exception('Vous ne pouvez supprimer cette équipement d\énergie');
        }
    }

    public function remove() {
        DB::remove($this);
    }

    public function getData($_startDate = null, $_endDate = null) {
        $return = array(
            'history' => array(
                'power' => array(),
                'consumption' => null,
            ),
            'stats' => array(
                'minPower' => null,
                'maxPower' => null,
            ),
            'real' => array(
                'power' => 0,
                'consumption' => 0,
            ),
        );
        $cmd_histories = array();
        preg_match_all("/#([0-9]*)#/", $this->getPower(), $matches);
        foreach ($matches[1] as $cmd_id) {
            if (is_numeric($cmd_id)) {
                $cmd = cmd::byId($cmd_id);
                if (is_object($cmd) && $cmd->getIsHistorized() == 1) {
                    foreach ($cmd->getHistory($_startDate, $_endDate) as $history) {
                        if (!isset($cmd_histories[$history->getDatetime()])) {
                            $cmd_histories[$history->getDatetime()] = array();
                        }
                        if (!isset($cmd_histories[$history->getDatetime()]['#' . $cmd_id . '#'])) {
                            $cmd_histories[$history->getDatetime()]['#' . $cmd_id . '#'] = $history->getValue();
                        }
                    }
                }
            }
        }

        foreach ($cmd_histories as $datetime => $cmd_history) {
            $datetime = floatval(strtotime($datetime . " UTC")) * 1000;
            $calcul = template_replace($cmd_history, $this->getPower());
            try {
                $test = new evaluate();
                $result = floatval($test->Evaluer($calcul));
                if ($this->getConsumption() == '' && count($return['history']['power']) > 0) {
                    $last_datetime = end(array_keys($return['history']['power']));
                    $last_value = end($return['history']['power']);
                    $return['history']['consumption'][$datetime] = (($last_value * (($datetime - $last_datetime) / 1000)) / 3600) / 1000;
                    $return['real']['consumption'] += $return['history']['consumption'][$datetime];
                }
                $return['history']['power'][$datetime] = $result;
                if ($return['stats']['minPower'] === null || $return['stats']['minPower'] > $result) {
                    $return['stats']['minPower'] = $result;
                }
                if ($return['stats']['maxPower'] === null || $return['stats']['maxPower'] < $result) {
                    $return['stats']['maxPower'] = $result;
                }
            } catch (Exception $e) {
                
            }
        }

        if ($this->getConsumption() == '' && count($return['history']['power']) > 0) {
            $datetime = floatval(strtotime(date('Y-m-d H:i:s'))) * 1000;
            $last_datetime = end(array_keys($return['history']['power']));
            $last_value = end($return['history']['power']);
            $return['history']['consumption'][$datetime] = (($last_value * (($datetime - $last_datetime) / 1000)) / 3600) / 1000;
            $return['real']['consumption'] += $return['history']['consumption'][$datetime];
        }

        $calcul = cmd::cmdToValue($this->getPower());
        try {
            $test = new evaluate();
            $result = floatval($test->Evaluer($calcul));
            $return['real']['power'] = $result;
        } catch (Exception $e) {
            
        }

        if ($this->getConsumption() != '') {
            $cmd_histories = array();
            preg_match_all("/#([0-9]*)#/", $this->getConsumption(), $matches);
            foreach ($matches[1] as $cmd_id) {
                if (is_numeric($cmd_id)) {
                    $cmd = cmd::byId($cmd_id);
                    if (is_object($cmd) && $cmd->getIsHistorized() == 1) {
                        foreach ($cmd->getHistory($_startDate, $_endDate) as $history) {
                            if (!isset($cmd_histories[$history->getDatetime()])) {
                                $cmd_histories[$history->getDatetime()] = array();
                            }
                            if (!isset($cmd_histories[$history->getDatetime()]['#' . $cmd_id . '#'])) {
                                $cmd_histories[$history->getDatetime()]['#' . $cmd_id . '#'] = $history->getValue();
                            }
                        }
                    }
                }
            }

            foreach ($cmd_histories as $datetime => $cmd_history) {
                $datetime = floatval(strtotime(date('Y-m-d H:i:s'))) * 1000;
                $calcul = template_replace($cmd_history, $this->getConsumption());
                try {
                    $test = new evaluate();
                    $result = floatval($test->Evaluer($calcul));
                    $return['history']['consumption'][$datetime] = $result;
                } catch (Exception $e) {
                    
                }
            }

            $calcul = cmd::cmdToValue($this->getConsumption());
            try {
                $test = new evaluate();
                $result = floatval($test->Evaluer($calcul));
                $return['real']['consumption'] = $result;
            } catch (Exception $e) {
                
            }
        }
        return $return;
    }

    /*     * **********************Getteur Setteur*************************** */

    public function getEqLogic_id() {
        return $this->eqLogic_id;
    }

    public function getConsumption() {
        return $this->consumption;
    }

    public function getPower() {
        return $this->power;
    }

    public function setEqLogic_id($eqLogic_id) {
        $this->eqLogic_id = $eqLogic_id;
    }

    public function getCategory($_key = '', $_default = '') {
        return utils::getJsonAttr($this->category, $_key, $_default);
    }

    public function setCategory($_key, $_value) {
        $this->category = utils::setJsonAttr($this->category, $_key, $_value);
    }

    public function setConsumption($consumption) {
        $this->consumption = $consumption;
    }

    public function getOptions($_key = '', $_default = '') {
        return utils::getJsonAttr($this->options, $_key, $_default);
    }

    public function setOptions($_key, $_value) {
        $this->options = utils::setJsonAttr($this->options, $_key, $_value);
    }

    public function setPower($power) {
        $this->power = $power;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

}

?>