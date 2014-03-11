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

if (php_sapi_name() != 'cli' || isset($_SERVER['REQUEST_METHOD']) || !isset($_SERVER['argc'])) {
    header("Status: 404 Not Found");
    header('HTTP/1.0 404 Not Found');
    $_SERVER['REDIRECT_STATUS'] = 404;
    echo "<h1>404 Not Found</h1>";
    echo "The page that you have requested could not be found.";
    exit();
}

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (isset($argv)) {
    foreach ($argv as $arg) {
        $argList = explode('=', $arg);
        if (isset($argList[0]) && isset($argList[1])) {
            $_GET[$argList[0]] = $argList[1];
        }
    }
}
$message = '';
foreach ($_GET as $key => $value) {
    $message .= $key . '=>' . $value . ' ';
}
log::add('rfxcom', 'event', 'Evenement : ' . $message);

if (!isset($_GET['id'])) {
    return;
}
$rfxcom = rfxcom::byLogicalId($_GET['id'], 'rfxcom');
if (count($rfxcom) > 0) {
    $rfxcom = $rfxcom[0];
}
if (!is_object($rfxcom)) {
    rfxcom::createFromDef($_GET);
    $rfxcom = rfxcom::byLogicalId($_GET['id'], 'rfxcom');
    if (count($rfxcom) > 0) {
        $rfxcom = $rfxcom[0];
    }
    if (!is_object($rfxcom)) {
        return;
    }
}


foreach ($rfxcom->getCmd() as $cmd) {
    $logicalId = $cmd->getConfiguration('logicalId');
    if (isset($_GET[$logicalId])) {
        $cmd->event($_GET[$logicalId]);
    }
}
