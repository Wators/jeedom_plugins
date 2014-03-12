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

class script extends eqLogic {

    public static function shareOnMarket(&$market) {
        $cibDir = dirname(__FILE__) . '/../../../../' . config::byKey('userScriptDir', 'script') . '/' . $market->getLogicalId();
        if (!file_exists($cibDir)) {
            throw new Exception('Impossible de trouver le script  :' . $cibDir);
        }
        $tmp = dirname(__FILE__) . '/../../../../tmp/' . $market->getLogicalId() . '.zip';
        if (!create_zip($cibDir, $tmp)) {
            throw new Exception('Echec de création du zip. Répertoire source : ' . $cibDir . ' / Répertoire cible : ' . $tmp);
        }
        return $tmp;
    }

    public static function getFromMarket(&$market, $_path) {
        $cibDir = realpath(dirname(__FILE__) . '/../../../../' . config::byKey('userScriptDir', 'script'));
        if (!file_exists($cibDir)) {
            throw new Exception('Impossible d\'installer le script le repertoire n\éxiste pas : ' . $cibDir);
        }
        $zip = new ZipArchive;
        if ($zip->open($_path) === TRUE) {
            $zip->extractTo($cibDir . '/');
            $zip->close();
        } else {
            throw new Exception('Impossible de décompresser le zip : ' . $_path);
        }
        $scriptPath = realpath(dirname(__FILE__) . '/../../../../' . config::byKey('userScriptDir', 'script') . '/' . $market->getLogicalId());
        if (!file_exists($scriptPath)) {
            throw new Exception('Echec de l\'installation. Impossible de trouver le script ' . $scriptPath);
        }
    }

    public static function removeFromMarket(&$market) {
        $scriptPath = realpath(dirname(__FILE__) . '/../../../../' . config::byKey('userScriptDir', 'script') . '/' . $market->getLogicalId());
        if (!file_exists($scriptPath)) {
            throw new Exception('Echec de la désinstallation. Impossible de trouver le script ' . $scriptPath);
        }
        unlink($scriptPath);
        if (!file_exists($scriptPath)) {
            throw new Exception('Echec de la désinstallation. Impossible de supprimer le script ' . $scriptPath);
        }
    }

}

class scriptCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    public function preSave() {
        if ($this->getConfiguration('request') == '') {
            throw new Exception('Le champs requête ne peut etre vide');
        }
        if ($this->getConfiguration('requestType') == '') {
            throw new Exception('Le champs requête type ne peut etre vide');
        }
    }

    public function execute($_options = null) {
        $request = str_replace('#API#', config::byKey('api'), $this->getConfiguration('request'));

        if ($_options != null) {
            switch ($this->getType()) {
                case 'action' :
                    switch ($this->getSubType()) {
                        case 'slider':
                            $request = str_replace('#slider#', $_options['slider'], $request);
                            break;
                        case 'color':
                            $request = str_replace('#color#', $_options['color'], $request);
                            break;
                        case 'message':
                            $replace = array('#title#', '#message#');
                            $replaceBy = array($_options['title'], $_options['message']);
                            if ($_options['message'] == '' || $_options['title'] == '') {
                                throw new Exception('[Script] Le message et le sujet ne peuvent être vide');
                            }
                            $request = str_replace($replace, $replaceBy, $request);
                            break;
                    }
                    break;
            }
        }

        switch ($this->getConfiguration('requestType')) {
            case 'http' :
                $request_http = new com_http($request);
                log::add('script', 'info', 'Execution http de "' . $request . '"');
                return $request_http->exec();
                break;
            case 'script' :
                $pathinfo = pathinfo($request);
                switch ($pathinfo['extension']) {
                    case 'php':
                        $request_shell = new com_shell('php ' . $request);
                        break;
                    case 'rb':
                        $request_shell = new com_shell('ruby ' . $request);
                        break;
                    case 'py':
                        $request_shell = new com_shell('python ' . $request);
                        break;
                    case 'pl':
                        $request_shell = new com_shell('perl ' . $request);
                        break;
                    default:
                        $request_shell = new com_shell($request);
                        break;
                }
                log::add('script', 'info', 'Execution shell de "' . $request . '"');
                return $request_shell->exec();
                break;
        }
        return false;
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
