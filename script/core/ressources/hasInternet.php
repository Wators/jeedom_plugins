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


//Script qui renvoit 1 si il y a internet 0 sinon

require_once dirname(__FILE__) . "/checkApiKey.php";


$shellOutput = trim(shell_exec('ping -s 1 -c 1 192.168.1.1 > /dev/null; echo $?'));
if ($shellOutput != 0) {
    echo 0;
    exit();
}

$shellOutput1 = trim(shell_exec('ping -s 1 -c 1 www.google.fr > /dev/null; echo $?'));
$shellOutput2 = trim(shell_exec('ping -s 1 -c 1 8.8.8.8 > /dev/null; echo $?'));
if (($shellOutput1 != 0) && ($shellOutput2 != 0)) {
    echo 0;
    exit();
}

echo 1;
exit();
?>
