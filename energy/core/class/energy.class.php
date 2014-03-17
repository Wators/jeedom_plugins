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
                'consumption' => array(),
            ),
            'details' => array()
        );

        $first = true;
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
                    if ($first) {
                        $return['history']['power'][$datetime] = array($datetime * 1000, $power[1]);
                    } else {
                        if (!isset($return['history']['power'][$datetime])) {
                            $return['history']['power'][$datetime] = array($datetime * 1000, self::searchPrevisous($return['history']['power'], $datetime));
                        }
                        $return['history']['power'][$datetime][1] += $power[1];
                    }
                }

                if (is_array($datas['history']['consumption'])) {
                    foreach ($datas['history']['consumption'] as $datetime => $consumption) {
                        if ($first) {
                            $return['history']['consumption'][$datetime] = array($datetime * 1000, $consumption[1]);
                        } else {
                            if (!isset($return['history']['consumption'][$datetime])) {
                                $return['history']['consumption'][$datetime] = array($datetime * 1000, self::searchPrevisous($return['history']['consumption'], $datetime));
                            }
                            $return['history']['consumption'][$datetime][1] += $consumption[1];
                        }
                    }
                }
                $return['details'][] = $details;
                $return['real']['power'] += $datas['real']['power'];
                $return['real']['consumption'] += $datas['real']['consumption'];
                $first = false;
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
                    $return['history']['power'][$datetime] = array($datetime * 1000, $power[1]);
                } else {
                    $return['history']['power'][$datetime][1] += $power[1];
                }
            }

            if (is_array($datas['history']['consumption'])) {
                foreach ($datas['history']['consumption'] as $datetime => $consumption) {
                    if (!isset($return['history']['consumption'][$datetime])) {
                        $return['history']['consumption'][$datetime] = array($datetime * 1000, $consumption[1]);
                    } else {
                        $return['history']['consumption'][$datetime][1] += $consumption[1];
                    }
                }
            }
            $return['details'][] = $details;
            $return['real']['power'] += $datas['real']['power'];
            $return['real']['consumption'] += $datas['real']['consumption'];
        }

        ksort($return['history']['consumption']);
        $return['history']['consumption'] = array_values($return['history']['consumption']);
        ksort($return['history']['power']);
        $return['history']['power'] = array_values($return['history']['power']);
        foreach ($return['details'] as &$details) {
            if (isset($details['data']['history']['consumption'])) {
                ksort($details['data']['history']['consumption']);
                $details['data']['history']['consumption'] = array_values($details['data']['history']['consumption']);
            }
            if (isset($details['data']['history']['power'])) {
                ksort($details['data']['history']['power']);
                $details['data']['history']['power'] = array_values($details['data']['history']['power']);
            }
        }

        return $return;
    }

    private static function searchPrevisous($_datas, $_datetime) {
        $prevValue = 0;
        $prevDatetime = null;
        foreach ($_datas as $datetime => $value) {
            if ($prevDatetime == null) {
                $prevDatetime = $datetime;
                $prevValue = $value;
            } else {
                if ($datetime > $_datetime && $prevDatetime < $_datetime) {
                    return $value[1];
                }
            }
        }
        if ($_datetime > $datetime) {
            return $value[1];
        }
        return 0;
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
        $nowtime = floatval(strtotime(date('Y-m-d H:i:s') . " UTC"));
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
            $datetime = floatval(strtotime($datetime . " UTC"));
            $calcul = template_replace($cmd_history, $this->getPower());
            try {
                $test = new evaluate();
                $result = floatval($test->Evaluer($calcul));
                if ($this->getConsumption() == '' && count($return['history']['power']) > 0) {
                    $last_datetime = end(array_keys($return['history']['power']));
                    if (($datetime - $last_datetime) > 0) {
                        $last_value = end($return['history']['power']);
                        $return['history']['consumption'][$datetime] = array($datetime, (($last_value[1] * (($datetime - $last_datetime) / 1000)) / 3600));
                        $return['real']['consumption'] += $return['history']['consumption'][$datetime][1];
                    }
                }
                $return['history']['power'][$datetime] = array($datetime * 1000, $result);
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
            $last_datetime = end(array_keys($return['history']['power']));
            $last_value = end($return['history']['power']);
            if (($datetime - $last_datetime) > 0) {
                $return['history']['consumption'][$datetime] = array($datetime * 1000, (($last_value[1] * (($nowtime - $last_datetime) / 1000)) / 3600));
                $return['real']['consumption'] += $return['history']['consumption'][$datetime][1];
            }
        }

        $calcul = cmd::cmdToValue($this->getPower());
        try {
            $test = new evaluate();
            $result = floatval($test->Evaluer($calcul));
            $return['real']['power'] = $result;
            $return['history']['power'][$nowtime] = array($nowtime * 1000, $result);
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
                $datetime = floatval(strtotime($datetime . " UTC"));
                $calcul = template_replace($cmd_history, $this->getConsumption());
                try {
                    $test = new evaluate();
                    $result = floatval($test->Evaluer($calcul));
                    $return['history']['consumption'][$datetime] = array($datetime * 1000, $result);
                } catch (Exception $e) {
                    
                }
            }

            $calcul = cmd::cmdToValue($this->getConsumption());
            try {
                $test = new evaluate();
                $result = floatval($test->Evaluer($calcul));
                $return['real']['consumption'] = $result;
                $return['history']['consumption'][$nowtime] = array($nowtime * 1000, $result);
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