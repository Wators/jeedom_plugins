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
require_once dirname(__FILE__) . '/../../core/php/mail.inc.php';

class mail extends eqLogic {

    public function preSave() {
        $this->setIsVisible(0);
    }

}

class mailCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    public function preSave() {
        $this->setType('action');
        $this->setSubType('message');
        if ($this->getConfiguration('recipient') == '') {
            throw new Exception('L\'adresse mail ne peut etre vide');
        }
        if (filter_var($this->getConfiguration('recipient'), FILTER_VALIDATE_EMAIL) === false) {
            throw new Exception('L\'adresse mail n\'est pas valide');
        }
    }

    public function execute($_options = null) {
        $eqLogic = $this->getEqLogic();
        if ($_options === null) {
            throw new Exception('[Mail] Les options de la fonction ne peuvent etre null');
        }

        if ($_options['message'] == '' && $_options['title'] == '') {
            throw new Exception('[Mail] Le message et le sujet ne peuvent être vide');
            return false;
        }

        if ($_options['title'] == '') {
            $_options['title'] = '[Jeedom] - Notification';
        }

        $mail = new PHPMailer(true);  //PHPMailer instance with exceptions enabled
        $mail->CharSet = 'utf-8';
        switch ($eqLogic->getConfiguration('sendMode', 'mail')) {
            case 'smtp':
                $mail->isSMTP();
                $mail->Host = $eqLogic->getConfiguration('smtp::server');
                $mail->Port = (integer) $eqLogic->getConfiguration('smtp::port');
                $mail->SMTPSecure = $eqLogic->getConfiguration('smtp::security');
                if ($this->getConfiguration('smtp::username') != '') {
                    $mail->SMTPAuth = true;
                    $mail->Username = $eqLogic->getConfiguration('smtp::username'); // SMTP account username
                    $mail->Password = $eqLogic->getConfiguration('smtp::password'); // SMTP account password
                }
                break;
            case 'mail':
                $mail->isMail();
                break;
            case 'sendmail':
                $mail->isSendmail();
            case 'qmail':
                $mail->isQmail();
                break;
            default:
                throw new Exception('Mode d\'envoi non reconnu');
        }
        if ($eqLogic->getConfiguration('fromName') != '') {
            $mail->addReplyTo($eqLogic->getConfiguration('fromMail'), $eqLogic->getConfiguration('fromName'));
            $mail->FromName = $eqLogic->getConfiguration('fromName');
        } else {
            $mail->addReplyTo($eqLogic->getConfiguration('fromMail'));
            $mail->FromName = $eqLogic->getConfiguration('fromMail');
        }
        $mail->From = $eqLogic->getConfiguration('fromMail');
        
        
        $mail->addAddress($this->getConfiguration('recipient'));
        $mail->Subject = $_options['title'];
        $mail->msgHTML(htmlentities($_options['message']), dirname(__FILE__), true);
        return $mail->send();
    }

    /*     * **********************Getteur Setteur*************************** */
}