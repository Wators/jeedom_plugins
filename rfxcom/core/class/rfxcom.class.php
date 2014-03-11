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
        $path = dirname(__FILE__) . '/../php/jeeRfxcom.php';
        $rfxcom_path = dirname(__FILE__) . '/../../ressources/rfxcmd';
        $trigger = file_get_contents($rfxcom_path . '/trigger_tmpl.xml');
        if (file_exists($rfxcom_path . '/trigger.xml')) {
            unlink($rfxcom_path . '/trigger.xml');
        }
        file_put_contents($rfxcom_path . '/trigger.xml', str_replace('#path#', $path, $trigger));
        log::add('rfxcom', 'info', $rfxcom_path . '/rfxcmd.py -z -d ' . $port . ' --pidfile=' . dirname(__FILE__) . '/../../../../tmp/rfxcom.pid');
        shell_exec('python ' . $rfxcom_path . '/rfxcmd.py -z -d ' . $port . ' --pidfile=' . dirname(__FILE__) . '/../../../../tmp/rfxcom.pid');
    }

    public static function deamonRunning() {
        $pid_file = dirname(__FILE__) . '/../../../../tmp/rfxcom.pid';
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

    /*     * **********************Getteur Setteur*************************** */
}

class rfxcomCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    /*     * **********************Getteur Setteur*************************** */
}

?>
