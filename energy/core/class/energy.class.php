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

    /*     * *********************Methode d'instance************************* */

    public function preSave() {
        
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