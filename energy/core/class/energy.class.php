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

    private $eqLogic_id;
    private $category = '';
    private $consumption = '';
    private $power = '';
    private $options = null;

    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */

    public function preSave() {
        if ($this->getEqLogic_id() == -1) {
            $this->setCategory('Générale');
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

    /*     * **********************Getteur Setteur*************************** */

    public function getEqLogic_id() {
        return $this->eqLogic_id;
    }

    public function getCategory() {
        return $this->category;
    }

    public function getConsumption() {
        return $this->consumption;
    }

    public function getPower() {
        return $this->power;
    }

    public function getOptions() {
        return $this->options;
    }

    public function setEqLogic_id($eqLogic_id) {
        $this->eqLogic_id = $eqLogic_id;
    }

    public function setCategory($category) {
        $this->category = $category;
    }

    public function setConsumption($consumption) {
        $this->consumption = $consumption;
    }

    public function setPower($power) {
        $this->power = $power;
    }

    public function setOptions($options) {
        $this->options = $options;
    }

}

?>