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
        $findTotal = false;
        if (!is_object($object)) {
            throw new Exception('Objet non trouvé vérifiez l\'id : ' . $_object_id);
        }
        $intervals = array();
        $return = array(
            'real' => array(
                'power' => 0,
                'consumption' => 0,
            ),
            'history' => array(
                'power' => array(),
                'consumption' => array(),
            ),
            'category' => array(),
            'details' => array()
        );
        $childs = array_merge($object->getEqLogic(), $object->getChilds());
        foreach ($childs as $child) {
            $datas = null;
            $energy = null;
            if (get_class($child) == 'object') {
                $datas = self::getObjectData($child->getId(), $_startDate, $_endDate);
            } else {
                $energy = self::byEqLogic_id($child->getId());
                if (is_object($energy)) {
                    if ($energy->getConfiguration('positionRelative') == 'total') {
                        $findTotal = true;
                        $return['history']['power'] = array();
                    }
                    $datas = $energy->getData($_startDate, $_endDate);
                    if (!isset($return['category'][$energy->getCategory()])) {
                        $return['category'][$energy->getCategory()] = array(
                            'data' => array(
                                'history' => array(
                                    'power' => array(),
                                    'consumption' => array(),
                                ),
                                'real' => array(
                                    'power' => 0,
                                    'consumption' => 0,
                                ),
                            ),
                            'name' => $energy->getCategory(),
                        );
                    }
                    $return['category'][$energy->getCategory()]['data']['real']['power'] += $datas['real']['power'];
                    $return['category'][$energy->getCategory()]['data']['real']['consumption'] += $datas['real']['consumption'];
                }
            }
            if (is_array($datas)) {
                $details = array(
                    'data' => $datas,
                    'name' => $child->getHumanName(),
                    get_class($child) => utils::o2a($child)
                );


                if (is_array($return['history']['power'])) {
                    ksort($datas['history']['power']);
                    $alreadyAdd = array();
                    foreach ($datas['history']['power'] as $datetime => $power) {
                        if (!$findTotal || $energy->getConfiguration('positionRelative') == 'total') {
                            if (!isset($return['history']['power'][$datetime])) {
                                $prevValue = self::searchPrevisous($return['history']['power'], $datetime);
                                if (count($prevValue) == 2) {
                                    if (isset($alreadyAdd[$prevValue[0] / 1000])) {
                                        $return['history']['power'][$datetime] = array($datetime * 1000, $prevValue[1] - $datas['history']['power'][$prevValue[0] / 1000][1]);
                                    } else {
                                        $return['history']['power'][$datetime] = array($datetime * 1000, $prevValue[1]);
                                    }
                                } else {
                                    $return['history']['power'][$datetime] = array($datetime * 1000, 0);
                                }
                            }
                            $return['history']['power'][$datetime][1] += $power[1];
                        }
                        /*                         * **********************Ajout a la catégorie**************************** */
                        if (is_object($energy)) {
                            if (!isset($return['category'][$energy->getCategory()]['data']['history']['power'][$datetime])) {
                                $prevValue = self::searchPrevisous($return['category'][$energy->getCategory()]['data']['history']['power'], $datetime);
                                if (count($prevValue) == 2) {
                                    if (isset($alreadyAdd[$prevValue[0] / 1000])) {
                                        $return['category'][$energy->getCategory()]['data']['history']['power'][$datetime] = array($datetime * 1000, $prevValue[1] - $datas['history']['power'][$prevValue[0] / 1000][1]);
                                    } else {
                                        $return['category'][$energy->getCategory()]['data']['history']['power'][$datetime] = array($datetime * 1000, $prevValue[1]);
                                    }
                                } else {
                                    $return['category'][$energy->getCategory()]['data']['history']['power'][$datetime] = array($datetime * 1000, 0);
                                }
                            }
                            $return['category'][$energy->getCategory()]['data']['history']['power'][$datetime][1] += $power[1];
                        }
                        $intervals[$datetime] = $datetime;
                        $alreadyAdd[$datetime] = $datetime;
                    }
                }


                if (is_array($datas['history']['consumption'])) {
                    ksort($datas['history']['consumption']);
                    $alreadyAdd = array();
                    foreach ($datas['history']['consumption'] as $datetime => $consumption) {
                        if (!isset($return['history']['consumption'][$datetime])) {
                            $prevValue = self::searchPrevisous($return['history']['consumption'], $datetime);
                            if (count($prevValue) == 2) {
                                if (isset($alreadyAdd[$prevValue[0] / 1000])) {
                                    $prevValue[1] = $prevValue[1] - $datas['history']['consumption'][$prevValue[0] / 1000][1];
                                }
                                $return['history']['consumption'][$datetime] = array($datetime * 1000, ($prevValue[1] >= 0) ? $prevValue[1] : 0);
                            } else {
                                $return['history']['consumption'][$datetime] = array($datetime * 1000, 0);
                            }
                        }
                        $return['history']['consumption'][$datetime][1] += $consumption[1];
                        $intervals[$datetime] = $datetime;
                        $alreadyAdd[$datetime] = $datetime;
                    }
                }
                $return['details'][] = $details;
                $return['real']['power'] += $datas['real']['power'];
                $return['real']['consumption'] += $datas['real']['consumption'];

                /*                 * *******************Calcul sur les categorie******************************* */
                if (isset($datas['category'])) {
                    foreach ($datas['category'] as $name => $category) {
                        if (!isset($return['category'][$name])) {
                            $return['category'][$name] = array(
                                'data' => array(
                                    'history' => array(
                                        'power' => array(),
                                        'consumption' => array(),
                                    ),
                                    'real' => array(
                                        'power' => 0,
                                        'consumption' => 0,
                                    ),
                                ),
                                'name' => $name,
                            );
                        }
                        if (is_array($category['data']['history']['power'])) {
                            ksort($category['data']['history']['power']);
                            $alreadyAdd = array();
                            foreach ($category['data']['history']['power'] as $datetime => $power) {
                                if (!isset($return['category'][$name]['data']['history']['power'][$datetime])) {
                                    $prevValue = self::searchPrevisous($return['category'][$name]['data']['history']['power'], $datetime);
                                    if (count($prevValue) == 2) {
                                        if (isset($alreadyAdd[$prevValue[0] / 1000])) {
                                            $return['category'][$name]['data']['history']['power'][$datetime] = array($datetime * 1000, $prevValue[1] - $category['data']['history']['power'][$prevValue[0] / 1000][1]);
                                        } else {
                                            $return['category'][$name]['data']['history']['power'][$datetime] = array($datetime * 1000, $prevValue[1]);
                                        }
                                    } else {
                                        $return['category'][$name]['data']['history']['power'][$datetime] = array($datetime * 1000, 0);
                                    }
                                }
                                $return['category'][$name]['data']['history']['power'][$datetime][1] += $power[1];
                                $intervals[$datetime] = $datetime;
                                $alreadyAdd[$datetime] = $datetime;
                            }
                        }
                        $return['category'][$name]['data']['real']['power'] += $category['data']['real']['power'];
                        $return['category'][$name]['data']['real']['consumption'] += $category['data']['real']['consumption'];
                    }
                }
            }
        }

        foreach ($return['details'] as &$details) {
            if (isset($details['data']['history']['consumption'])) {
                ksort($details['data']['history']['consumption']);
                $details['data']['history']['consumption'] = self::fillHoles($intervals, $details['data']['history']['consumption']);
            }
            if (isset($details['data']['history']['power'])) {
                ksort($details['data']['history']['power']);
                $details['data']['history']['power'] = self::fillHoles($intervals, $details['data']['history']['power']);
            }
        }

        foreach ($return['category'] as &$category) {
            if (isset($category['data']['history']['consumption'])) {
                ksort($category['data']['history']['consumption']);
                $category['data']['history']['consumption'] = self::fillHoles($intervals, $category['data']['history']['consumption']);
            }
            if (isset($category['data']['history']['power'])) {
                ksort($category['data']['history']['power']);
                $category['data']['history']['power'] = self::fillHoles($intervals, $category['data']['history']['power']);
            }
        }

        ksort($return['history']['consumption']);
        ksort($return['history']['power']);
        return $return;
    }

    private static function fillHoles($_intervals, $_datas) {
        $min = reset(array_keys($_datas));
        $max = end(array_keys($_datas));
        foreach ($_intervals as $interval) {
            if ($min <= $interval && $max >= $interval && !isset($_datas[$interval])) {
                $prevValue = self::searchPrevisous($_datas, $interval);
                if (count($prevValue) == 2) {
                    $_datas[$interval] = array($interval * 1000, $prevValue[1]);
                }
            }
        }
        return $_datas;
    }

    private static function searchPrevisous($_datas, $_datetime) {
        $prevDatetime = null;
        foreach ($_datas as $datetime => $value) {
            if ($prevDatetime == null) {
                $prevDatetime = $datetime;
            } else {
                if ($_datetime < $datetime && $_datetime > $prevDatetime) {
                    return $value;
                }
            }
            $prevDatetime = $datetime;
        }
        if (isset($value) && $_datetime > $datetime) {
            return $value;
        }
        return array();
    }

    public static function sanitizeForChart($return) {
        $return['history']['consumption'] = array_values($return['history']['consumption']);
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
        foreach ($return['category'] as &$category) {
            if (isset($category['data']['history']['consumption'])) {
                ksort($category['data']['history']['consumption']);
                $category['data']['history']['consumption'] = array_values($category['data']['history']['consumption']);
            }
            if (isset($category['data']['history']['power'])) {
                ksort($category['data']['history']['power']);
                $category['data']['history']['power'] = array_values($category['data']['history']['power']);
            }
        }
        return $return;
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
                    $prevDatetime = null;
                    $prevValue = 0;
                    foreach ($cmd->getHistory($_startDate, $_endDate) as $history) {
                        if (!isset($cmd_histories[$history->getDatetime()])) {
                            $cmd_histories[$history->getDatetime()] = array();
                        }
                        if (!isset($cmd_histories[$history->getDatetime()]['#' . $cmd_id . '#'])) {
                            if ($prevDatetime != null) {
                                if ((strtotime(date('Y-m-d H:i:s')) - strtotime($history->getDatetime())) < (config::byKey('historyArchiveTime') * 3600)) {
                                    if ((strtotime(date('Y-m-d H:i:s')) - strtotime($prevDatetime)) > (config::byKey('historyArchiveTime') * 3600)) {
                                        $prevDatetime = date('Y-m-d H:00:00', strtotime(date('Y-m-d H:i:s')) - (config::byKey('historyArchiveTime') * 3600));
                                    }
                                    while ((strtotime($history->getDatetime()) - strtotime($prevDatetime)) > 300) {
                                        $prevDatetime = date('Y-m-d H:i:00', strtotime($prevDatetime) + 300);
                                        $cmd_histories[$prevDatetime]['#' . $cmd_id . '#'] = $prevValue;
                                    }
                                } else {
                                    while ((strtotime($history->getDatetime()) - strtotime($prevDatetime)) > 3600) {
                                        $prevDatetime = date('Y-m-d H:00:00', strtotime($prevDatetime) + 3600);
                                        $cmd_histories[$prevDatetime]['#' . $cmd_id . '#'] = 0;
                                    }
                                }
                            }
                            $cmd_histories[$history->getDatetime()]['#' . $cmd_id . '#'] = $history->getValue();
                        }
                        $prevDatetime = $history->getDatetime();
                        $prevValue = $history->getValue();
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
                        $prevDatetime = null;
                        foreach ($cmd->getHistory($_startDate, $_endDate) as $history) {
                            if (!isset($cmd_histories[$history->getDatetime()])) {
                                $cmd_histories[$history->getDatetime()] = array();
                            }
                            if (!isset($cmd_histories[$history->getDatetime()]['#' . $cmd_id . '#'])) {
                                if ($prevDatetime != null) {
                                    if ((strtotime(date('Y-m-d H:i:s')) - strtotime($history->getDatetime())) < (config::byKey('historyArchiveTime') * 3600)) {
                                        if ((strtotime(date('Y-m-d H:i:s')) - strtotime($prevDatetime)) > (config::byKey('historyArchiveTime') * 3600)) {
                                            $prevDatetime = date('Y-m-d H:00:00', strtotime(date('Y-m-d H:i:s')) - (config::byKey('historyArchiveTime') * 3600));
                                        }
                                        while ((strtotime($history->getDatetime()) - strtotime($prevDatetime)) > 300) {
                                            $prevDatetime = date('Y-m-d H:i:00', strtotime($prevDatetime) + 300);
                                            $cmd_histories[$prevDatetime]['#' . $cmd_id . '#'] = $prevValue;
                                        }
                                    } else {
                                        while ((strtotime($history->getDatetime()) - strtotime($prevDatetime)) > (config::byKey('historyArchivePackage') * 3600)) {
                                            $prevDatetime = date('Y-m-d H:00:00', strtotime($prevDatetime) + (config::byKey('historyArchivePackage') * 3600));
                                            $cmd_histories[$prevDatetime]['#' . $cmd_id . '#'] = 0;
                                        }
                                    }
                                }
                                $cmd_histories[$history->getDatetime()]['#' . $cmd_id . '#'] = $history->getValue();
                            }
                            $prevDatetime = $history->getDatetime();
                            $prevValue = $history->getValue();
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

        if (is_array($return['history']['consumption'])) {
            ksort($return['history']['consumption']);
        }
        if (is_array($return['history']['power'])) {
            ksort($return['history']['power']);
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

    public function getCategory() {
        return $this->category;
    }

    public function setCategory($category) {
        $this->category = $category;
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