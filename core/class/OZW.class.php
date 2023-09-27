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

class OZW extends eqLogic
{
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    public function getParent()
    {
        if ($this->getConfiguration('type', '') == 'OZW') {
            $carte = $this;
        } else {
            $carte = OZW::byId($this->getConfiguration('parent'));
            if (!is_object($carte)) {
                throw new \Exception(__('OZW parent eqLogic non trouvé : ', __FILE__) . $this->getConfiguration('parent'));
            }
        }
        return $carte;
    }

    public function https_file_get_contents($url)
    {
        $ctx = stream_context_create(
            array(
                "ssl" => array(
                    "verify_peer"      => false,
                    "verify_peer_name" => false,
                )
            )
        );
        return file_get_contents($url, false, $ctx);
    }



    function OZW_api($_carte, $_api, $_retry_SessionId = true)
    {
        log::add('OZW', 'debug', 'Execute API ' . $_carte->getName() . ' url=' . $_api);

        $SessionId = $_carte->RetrieveSessionId();
        if ($SessionId == '') {
            throw new \Exception(__('Impossible d obtenir un SessionID', __FILE__));
        }

        $statuscmd = $this->getCmd(null, 'status');

        $url_api = 'https://' . $_carte->getConfiguration('ip') . '/api/' . str_replace('%id%', $SessionId, $_api);
        $json = $this->https_file_get_contents($url_api);
        log::add('OZW', 'debug', 'Request ' . $url_api);
        if ($json === false) {
            if (is_object($statuscmd)) {
                $statuscmd->setCollectDate('');
                $statuscmd->event('0');
            }
            throw new \Exception(__('L\'OZW ne repond pas.', __FILE__));
        }
        $obj = json_decode($json, TRUE);
        log::add('OZW', 'debug', 'Detail data : ' . print_r($obj, true));
        if ((isset($obj['Result']['Success']) && $obj['Result']['Success'] !== "false") == false) {
            if (isset($obj['Result']['Error']['Txt'])) {
                // si le session ID est expiré, en récupére un nouveau et retente le call API une seule fois
                if (($obj['Result']['Error']['Txt'] == 'session not valid') && $_retry_SessionId = true) {
                    log::add('OZW', 'debug', __('Session not valid', __FILE__));
                    $_carte->getNewSessionId();
                    $this->OZW_api($_carte, $_api, false);
                } else {
                    log::add('OZW', 'error', __('L\'OZW error : ', __FILE__) . $obj['Result']['Error']['Txt']);
                    throw new \Exception(__('L\'OZW error : ', __FILE__) . $obj['Result']['Error']['Txt']);
                }
            } else {
                if (is_object($statuscmd)) {
                    $statuscmd->setCollectDate('');
                    $statuscmd->event('0');
                }
                log::add('OZW', 'error', __('Erreur de communication avec l\'OZW', __FILE__));
                throw new \Exception(__('Erreur de communication avec l\'OZW', __FILE__));
            }
        }
        return $obj;
    }

    public function RetrieveSessionId()
    {

//        log::add('OZW', 'debug', 'Retrieve SessionId for ID ' . $this->getID() . ' name ' . $this->getName());
        $SessionIdcmd = cmd::byEqLogicIdAndLogicalId($this->getID(), 'SessionID');

        if (!is_object($SessionIdcmd)) {
            throw new \Exception('Pas de commande SessionId pour l EqLogicId ' . $this->id . ' name ' . $this->getName());
        } else {
            $session_life_time = $this->getConfiguration('session_life_time');
            if (!is_numeric($session_life_time)) {
                $session_life_time = '24';
            }
            $now = date('Y-m-d H:i:s');
            $collectDate = $SessionIdcmd->getCollectDate();

            $anciennete = floor(strtotime($now) - strtotime($collectDate));
  //          log::add('OZW', 'debug', 'Sessionid: collect date ' . $SessionIdcmd->getCollectDate() . ' now ' . $now . ' ancienneté ' . $anciennete . ' session_life_time ' . $session_life_time);

            if (floor(strtotime($now) - strtotime($collectDate)) >= (3600 * $session_life_time)) {
                $anciennete = strtotime($now) - strtotime($collectDate);
  //              log::add('OZW', 'info', 'Sessionid expired: collect date ' . $SessionIdcmd->getCollectDate() . ' now ' . $now . ' ancienneté ' . $anciennete . ' session_life_time ' . $session_life_time);
                return $this->getNewSessionId();
            } else {
                return $SessionIdcmd->execCmd();
            }
        }
    }

    public function getNewSessionId()
    {
    //    log::add('OZW', 'debug', 'get SessionId for ID ' . $this->getID() . ' name ' . $this->getName());
        $statuscmd = $this->getCmd(null, 'status');
        $SessionIdcmd = cmd::byEqLogicIdAndLogicalId($this->getID(), 'SessionID');

        if (!is_object($SessionIdcmd)) {
            throw new \Exception('Pas de commande SessionId pour l EqLogicId ' . $this->id . ' name ' . $this->getName());
        }

        if ($this->getConfiguration('ip', '') == '') {
            throw new \Exception('Adresse IP non définie pour l EqLogicId ' . $this->id . ' name ' . $this->getName());
        }

        $json  = $this->https_file_get_contents('https://' . $this->getConfiguration('ip') . '/api/auth/login.json?user=' . $this->getConfiguration('username') . '&pwd=' . $this->getConfiguration('password'));
        if ($json === false) {
            throw new \Exception(__('L\'OZW ne repond pas.', __FILE__));
        }
        $obj = json_decode($json, TRUE);
        if (isset($obj['Result']['Success']) && $obj['Result']['Success'] !== "false") {
            if (is_object($statuscmd)) {
                $statuscmd->setCollectDate('');
                $statuscmd->event('1');
            }

            $SessionIdcmd->setCollectDate('');
            $SessionIdcmd->event($obj['SessionId']);

            return $SessionIdcmd->execCmd();
        } else {
            if (is_object($statuscmd)) {
                $statuscmd->setCollectDate('');
                $statuscmd->event('0');
            }

            if (isset($obj['Result']['Error']['Txt'])) {
                throw new \Exception(__('L\'OZW error : ', __FILE__) . $obj['Result']['Error']['Txt']);
            } else {
                throw new \Exception(__('Erreur de communication avec l\'OZW', __FILE__));
            }
            return '';
        }
    }

    public function devices_import()
    {

        if ($this->getIsEnable() == false) {
            throw new \Exception(__('Equipement non activé', __FILE__));
        }

        $obj = OZW::OZW_api($this, 'devicelist/list.json?SessionId=%id%');
        if (isset($obj['Devices'])) {
            foreach ($obj['Devices'] as $item) {
                if (!is_object(self::byLogicalId($item['SerialNr'], 'OZW'))) {
                    log::add('OZW', 'info', 'Creation appareil : ' . $item['Type'] . ' (' .  $item['SerialNr']  . ')');
                    $eqLogic = (new OZW())
                        ->setLogicalId($item['SerialNr'])
                        ->setName(trim($item['Type'] . ' ' . $item['SerialNr']))   // BD 20230927
                        ->setEqType_name('OZW')
                        ->setConfiguration('type', 'appareil')
                        ->setConfiguration('address', $item['Addr'])
                        ->setConfiguration('SerialNr', $item['SerialNr'])
                        ->setConfiguration('parent', $this->getId())
                        ->setIsEnable(1)
                        ->setIsVisible(1);
                    $eqLogic->save();
                } else {
                    log::add('OZW', 'info', 'Appareil déjà créé : ' . $item['Name'] . ' (' .  $item['SerialNr']  . ')');
                }
            }
        }
    }

    public function main_commands_import()
    {

        if ($this->getIsEnable() == false) {
            throw new \Exception(__('Equipement non activé', __FILE__));
        }

        $carte = $this->getParent();
        //      $carte->getSessionId();
        $obj = OZW::OZW_api($carte, 'menutree/device_root.json?SessionId=%id%&SerialNumber=' . $this->getConfiguration('SerialNr') . '&TreeName=Mobile');
        if ($obj['Result']['Success'] == 'true') {
            if (isset($obj['TreeItem']['Id'])) {
                $this->MenuImport($obj['TreeItem']['Id']);
            } else {
                log::add('OZW', 'debug', 'Cannot Find TreeItem : ' . $obj['Result']['Error']['Txt']);
            }
        }
    }

    public function MenuImport($menu_id)
    {

        log::add('OZW', 'debug', __('MenuImport ', __FILE__) . $this->name . ' Menu ' . $menu_id);

        $carte = $this->getParent();
        //     $carte->getSessionId();

        $obj = OZW::OZW_api($carte, 'menutree/list.json?SessionId=%id%&Id=' . $menu_id);

        if (isset($obj['DatapointItems'])) {
            foreach ($obj['DatapointItems'] as $item) {
                $this->create_command($item['Id'], 'X', '', '');
            }
            foreach ($obj['WidgetItems'] as $item) {
                $this->MenuImport($item['Id']);
            }
            foreach ($obj['MenuItems'] as $item) {
                $this->MenuImport($item['Id']);
            }
        }
    }

    public function create_command($id_commande, $info, $action, $refresh)
    {
        log::add('OZW', 'info', __('create_command', __FILE__) . ' ' . $this->name . ' Commande ' . $id_commande . ' Info ' . $info . ' Action ' . $action . ' Refresh ' . $refresh);
        $carte = $this->getParent();
        //    $carte->getSessionId();
        if ($info != '') {
            $this->create_info_command($carte, $id_commande);
        }
        if ($action != '') {
            $this->create_action_command($carte, $id_commande);
        }
        if ($refresh != '') {
            $this->create_refresh_command($carte, $id_commande);
        }
    }

    private function create_info_command($carte, $item_id)
    // crée la commande type info
    {
        if (is_object(cmd::byEqLogicIdAndLogicalId($this->id, $item_id))) {
            log::add('OZW', 'info', __('create_info_command ', __FILE__) . $this->name . '  commande déjà créée ' . $item_id);
            return '0';
        }

        // lit la description du datapoint
        $obj_detail = OZW::OZW_api($carte, 'menutree/datapoint_desc.json?SessionId=%id%&Id=' . $item_id);
        $type = $obj_detail['Description']['Type'];

        if (isset($obj_detail['Result']['Success']) && $obj_detail['Result']['Success'] !== "false") {

            $name = str_replace(array('&', '#', ']', '[', '%', "'"), ' ', $obj_detail['Description']['Name']);
            if ($name == '') {
                $name = $item_id;
            }

            $cmd = new OZWCmd();

            // BD: pour éviter les problèmes de conversion par exemple quand le nom contient le caractere /
            $cmd->setName($name);
            $name = $cmd->getName();

            // teste si le nom de la commande est déjà attribué
            // si oui, ajoute à la fin un numéro afin d'avoir un nom unique
            if (is_object(cmd::byEqLogicIdCmdName($this->id, $name))) {
                $count = 1;
                while (is_object(cmd::byEqLogicIdCmdName($this->id, substr($name, 0, 40) . "..." . $count))) {
                    $count++;
                }
                $cmd->setName(substr($name, 0, 40) . "..." . $count);
                log::add('OZW', 'info', 'Rename as ' . substr($name, 0, 40) . "..." . $count);
            } else {
                $cmd->setName($name);
            }

            // crée la commande de type INFO
            $cmd->setEqLogic_id($this->getId());
            $cmd->setLogicalId($item_id);   // le logical id est égal à l'id du datapoint
            $cmd->setIsVisible(1);
            $cmd->setConfiguration('isPrincipale', '0');
            $cmd->setOrder(time());
            $cmd->setConfiguration('isCollected', '1');
            $cmd->setConfiguration('internal_type', $type);
            log::add('OZW', 'debug', 'Type : ' . $type);

            switch ($type) {
                case "DateTime":
                    $cmd->setType('info');
                    $cmd->setSubType('string');
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    $cmd->save();
                    break;
                case "Enumeration":
                    $cmd->setType('info');
                    $cmd->setSubType('string');
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    /*   PAS UTILE BD 20230927
                    foreach ($obj_detail['Description']['Enums'] as $item_enum) {
                        $cmd->setConfiguration('internal_label_' . $item_enum['Value'], $item_enum['Text']);
                    }
                    */
                    $cmd->save();
                    break;
                case "Numeric":
                case "TimeOfDay":
                    $cmd->setType('info');
                    $cmd->setSubType('numeric');
                    if (isset($obj_detail['Description']['Min'])) {
                        $cmd->setConfiguration('minValue', $obj_detail['Description']['Min']);
                    }
                    if (isset($obj_detail['Description']['Max'])) {
                        $cmd->setConfiguration('maxValue', $obj_detail['Description']['Max']);
                    }
                    if (isset($obj_detail['Description']['DecimalDigits'])) {
                        $cmd->setConfiguration('historizeRound', $obj_detail['Description']['DecimalDigits']);
                    }
                    $cmd->setUnite($obj_detail['Description']['Unit']);
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    $cmd->save();
                    break;
                case "Scheduler":
                    $cmd->setType('info');
                    $cmd->setSubType('string');
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    $cmd->save();
                    break;
                case "RadioButton":
                    $cmd->setType('info');
                    $cmd->setSubType('binary');
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    $cmd->save();
                    break;
                case "CheckBox":
                    $cmd->setType('info');
                    $cmd->setSubType('binary');
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    $cmd->save();
                    $item['WriteAccess'] = "false";
                    break;
                case "String":
                    $cmd->setType('info');
                    $cmd->setSubType('string');
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    $cmd->save();
                    break;
                case "AlarmInfo":
                    $cmd->setType('info');
                    $cmd->setSubType('string');
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    $cmd->save();
                    break;
                case "TimeOfDay":
                    $cmd->setType('info');
                    $cmd->setSubType('binary');
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    $item['WriteAccess'] = "false";
                    #die;
                    #$cmd->save();
                    break;
                case "Calendar":
                    $item['WriteAccess'] = "false";
                    break;
                default:
                    log::add('OZW', 'error', 'Type inconnu reply : ' . print_r($obj_detail, true));
                    die;
                    break;
            }
        }
    }


    private function create_action_command($carte, $item_id)
    // crée la commande type action
    {

        if (is_object(cmd::byEqLogicIdAndLogicalId($this->id, 'A_' . $item_id))) {
            log::add('OZW', 'info', __('create_action_command ', __FILE__) . $this->name . '  commande action déjà créée ' . 'A_' . $item_id);
            return '0';
        }

        // lit la description du datapoint
        $obj_detail = OZW::OZW_api($carte, 'menutree/datapoint_desc.json?SessionId=%id%&Id=' . $item_id);

        $type = $obj_detail['Description']['Type'];
        if (isset($obj_detail['Result']['Success']) && $obj_detail['Result']['Success'] !== "false") {

            $name = substr(str_replace(array('&', '#', ']', '[', '%', "'"), ' ', $obj_detail['Description']['Name']), 0, 38);
            if ($name == '') {
                $name = $item_id;
            }
            $name = "Action " . $name;

            $cmd = new OZWCmd();

            // BD: pour éviter les problèmes de conversion par exemple quand le nom contient le caractere /
            $cmd->setName($name);
            $name = $cmd->getName();

            // teste si le nom de la commande est déjà attribué    
            // si oui, ajoute à la fin un numéro afin d'avoir un nom unique
            if (is_object(cmd::byEqLogicIdCmdName($this->id, $name))) {
                $count = 1;
                while (is_object(cmd::byEqLogicIdCmdName($this->id, substr($name, 0, 40) . "..." . $count))) {
                    $count++;
                }
                $cmd->setName(substr($name, 0, 40) . "..." . $count);
                log::add('OZW', 'info', 'Rename as ' . substr($name, 0, 40) . "..." . $count);
            } else {
                $cmd->setName($name);
            }
            $cmd->setEqLogic_id($this->getId());
            $cmd->setLogicalId('A_' . $item_id);   // le logical id est égal à 'A_' plus l'id du datapoint
            $cmd->setConfiguration('infoId', $item_id);
            $cmd->setIsVisible(1);
            $cmd_info = cmd::byEqLogicIdAndLogicalId($this->id, $item_id);
            if (is_object($cmd_info)) {
                $cmd->setValue($cmd_info->getID()); // cmmande info liée
            }
            $cmd->setOrder(time());
            $cmd->setConfiguration('internal_type', $type);

            switch ($obj_detail['Description']['Type']) {
                case "DateTime":
                case "TimeOfDay":
                case "Scheduler":
                    break;
                case "RadioButton":
                    $cmd->setType('action');
                    $cmd->setSubType('select');
                    $list_value = array();
                    $count      = 0;
                    while (isset($obj_detail['Description']['Buttons']['TextOpt' . $count])) {
                        if ($obj_detail['Description']['Buttons']['Significance'] == $count) {
                            array_push($list_value, $count . '|' . $obj_detail['Description']['Buttons']['TextOpt' . $count]);
                            if ($count > 3) {
                                die;
                            }
                        }
                        $count++;
                    }
                    $cmd->setConfiguration('listValue', join(";", $list_value));
                    $cmd->save();
                    break;
                case "Enumeration":
                    $cmd->setType('action');
                    $cmd->setSubType('select');
                    $list_value = array();
                    foreach ($obj_detail['Description']['Enums'] as $item_enum) {
                        array_push($list_value, $item_enum['Value'] . '|' . $item_enum['Text']);
                    }
                    $cmd->setConfiguration('listValue', join(";", $list_value));
                    $cmd->save();
                    break;
                case "Numeric":
                    $cmd->setType('action');
                    $cmd->setSubType('slider');
                    $cmd->setConfiguration('minValue', $obj_detail['Description']['Min']);
                    $cmd->setConfiguration('maxValue', $obj_detail['Description']['Max']);
                    $cmd->save();
                    break;
                case "String":
                    $cmd->setType('action');
                    $cmd->setSubType('message');
                    $cmd->setDisplay('title_disable', 1);
                    $cmd->save();
                    break;
                default:
                    log::add('OZW', 'info', 'Error creation action : ' . $item_id . ' (' . $item['Text']['Long'] . ' : ' . $item['WriteAccess'] . ')');
                    die;
                    break;
            }
        }
    }

    private function create_refresh_command($carte, $item_id)
    // crée la commande type refresh
    {

        if (is_object(cmd::byEqLogicIdAndLogicalId($this->id, 'R_' . $item_id))) {
            log::add('OZW', 'info', __('create_refresh_command ', __FILE__) . $this->name . '  commande refresh déjà créée ' . 'R_' . $item_id);
            return '0';
        }

        // lit la description du datapoint
        $obj_detail = OZW::OZW_api($carte, 'menutree/datapoint_desc.json?SessionId=%id%&Id=' . $item_id);

        $type = $obj_detail['Description']['Type'];
        if (isset($obj_detail['Result']['Success']) && $obj_detail['Result']['Success'] !== "false") {

            $name = substr(str_replace(array('&', '#', ']', '[', '%', "'"), ' ', $obj_detail['Description']['Name']), 0, 38);
            if ($name == '') {
                $name = $item_id;
            }
            $name = "Refresh " . $name;
            $cmd = new OZWCmd();

            // BD: pour éviter les problèmes de conversion par exemple quand le nom contient le caractere /
            $cmd->setName($name);
            $name = $cmd->getName();

            // teste si le nom de la commande est déjà attribué    
            // si oui, ajoute à la fin un numéro afin d'avoir un nom unique
            if (is_object(cmd::byEqLogicIdCmdName($this->id, $name))) {
                $count = 1;
                while (is_object(cmd::byEqLogicIdCmdName($this->id, substr($name, 0, 40) . "..." . $count))) {
                    $count++;
                }
                $cmd->setName(substr($name, 0, 40) . "..." . $count);
                log::add('OZW', 'info', 'Rename as ' . substr($name, 0, 40) . "..." . $count);
            } else {
                $cmd->setName($name);
            }
            $cmd->setEqLogic_id($this->getId());
            $cmd->setLogicalId('R_' . $item_id);   // le logical id est égal à 'R_' plus l'id du datapoint
            $cmd->setConfiguration('infoId', $item_id);
            $cmd->setIsVisible(1);
            $cmd->setOrder(time());
            $cmd->setConfiguration('internal_type', $type);
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setConfiguration('minValue', '0');
            $cmd->setConfiguration('maxValue', '60');
            $cmd->save();
        }
    }

    public function preInsert()
    {
        if ($this->getConfiguration('type', '') == "") {
            $this->setConfiguration('type', 'OZW');
        }
    }

    public function preUpdate()
    {
        if ($this->getIsEnable()) {
            //    return $this->getSessionId();
        }
    }

    public function preSave()
    {
        if ($this->getIsEnable()) {
            //    return $this->getSessionId();
        }
    }

    public function preRemove()
    {
        // ne permet pas la suppression si il existe des devices liés
        if ($this->getConfiguration('type', '') == 'OZW') {
            foreach (eqLogic::byTypeAndSearchConfiguration('OZW', '"parent":"' . $this->getID() . '"') as $eqLogic) {
                if ($eqLogic->getConfiguration('type', '') != 'OZW') {
                    throw new \Exception(__('Suppression interdite car il existe un device lié', __FILE__));
                    return false;
                }
            }
        }
        return true;
    }


    public function postInsert()
    {
        $this->postUpdate();
    }

    public function postUpdate()
    {

        if ($this->getConfiguration('type', '') == 'OZW') {

            $cmd = $this->getCmd(null, 'status');
            if (!is_object($cmd)) {
                $cmd = new OZWCmd();
                $cmd->setName('Etat');
                $cmd->setEqLogic_id($this->getId());
                $cmd->setType('info');
                $cmd->setSubType('binary');
                $cmd->setLogicalId('status');
                $cmd->setIsVisible(1);
                $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                $cmd->save();
            }

            $cmd = $this->getCmd(null, 'SessionId');
            if (!is_object($cmd)) {
                $cmd = new OZWCmd();
                $cmd->setName('SessionId');
                $cmd->setEqLogic_id($this->getId());
                $cmd->setType('info');
                $cmd->setSubType('string');
                $cmd->setLogicalId('SessionId');
                $cmd->setIsVisible(0);
                $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                $cmd->save();
            }
        } else {

            $cmd = $this->getCmd(null, 'updatetime');
            if (!is_object($cmd)) {
                $cmd = new OZWCmd();
                $cmd->setName('Dernier refresh');
                $cmd->setEqLogic_id($this->getId());
                $cmd->setLogicalId('updatetime');
                $cmd->setUnite('');
                $cmd->setType('info');
                $cmd->setSubType('string');
                $cmd->setIsHistorized(0);
                $cmd->save();
            }
        }
    }



    public function cron()
    {
        log::add('OZW', 'info', 'Lancement de cron');
        OZW::cron_update(__FUNCTION__);
    }
    public function cron5()
    {
        sleep(5);
        log::add('OZW', 'info', 'Lancement de cron5');
        OZW::cron_update(__FUNCTION__);
    }
    public function cron10()
    {
        sleep(10);
        log::add('OZW', 'info', 'Lancement de cron10');
        OZW::cron_update(__FUNCTION__);
    }
    public function cron15()
    {
        sleep(15);
        log::add('OZW', 'info', 'Lancement de cron15');
        OZW::cron_update(__FUNCTION__);
    }
    public function cron30()
    {
        sleep(20);
        log::add('OZW', 'info', 'Lancement de cron30');
        OZW::cron_update(__FUNCTION__);
    }

    public function cronHourly()
    {
        sleep(25);
        log::add('OZW', 'info', 'Lancement de cronHourly');
        OZW::cron_update(__FUNCTION__);
    }

    public function cronDaily()
    {
        sleep(30);
        log::add('OZW', 'info', 'Lancement de cronDaily');
        OZW::cron_update(__FUNCTION__);
    }
    public function cron_update($_cron)
    {
        foreach (eqLogic::byTypeAndSearchConfiguration('OZW', '"type":"appareil"') as $eqLogic) {
            if ($eqLogic->getIsEnable()) {

                log::add('OZW', 'info', 'cron Refresh Info Appareil : ' . $eqLogic->name);

                $carte = $eqLogic->getParent();

                foreach ($eqLogic->getCmd() as $cmd) {
                    if (is_numeric($cmd->getLogicalId()) && $cmd->getConfiguration('isCollected') == 1 && $cmd->getConfiguration('cron') == $_cron) {
                        if ($eqLogic->refresh_info_cmd($carte, $cmd) == true) {
                            $eqLogic_refresh_cmd = $eqLogic->getCmd(null, 'updatetime');
                            $eqLogic_refresh_cmd->event(date("d/m/Y H:i", (time())));
                        }
                    }
                }
            }
        }
    }
    function refresh_info_cmd($_carte, $_cmd)
    {
        log::add('OZW', 'debug', 'Read datapoint ' . $_cmd->getLogicalId() . ' ' . $_cmd->getName());
        $obj = OZW::OZW_api($_carte, 'menutree/read_datapoint.json?&SessionId=%id%&Id=' . $_cmd->getLogicalId());
        if (isset($obj['Result']['Success']) && $obj['Result']['Success'] !== "false") {
            log::add('OZW', 'info', 'Read de ' . $_cmd->getLogicalId() . ' ' . $_cmd->getName() . ' --> ' . $obj['Data']['Value']);
            $_cmd->event($obj['Data']['Value']);
            return true;
        } else {
            return false;
        }
    }
}

class OZWCmd extends cmd
{

    public function execute($_options = null)
    {
        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
            throw new \Exception(__('Equipement desactivé impossible d\éxecuter la commande : ' . $this->getHumanName(), __FILE__));
        }
        $carte = $eqLogic->getParent();
        //       $carte->getSessionId();

        // Commande action
        if (substr($this->getLogicalId(), 0, 2) == 'A_') {
            $internalid = substr($this->getLogicalId(), 2);  // remove 'A_'
            switch ($this->getConfiguration('internal_type')) {
                case "DateTime":
                case "TimeOfDay":
                case "Scheduler":
                    break;
                case "RadioButton":
                    break;
                case "Enumeration":
                    $url = 'menutree/write_datapoint.json?SessionId=%id%&Id=' . $internalid . '&Type=Enumeration&Value=' . $_options['select'];
                    break;
                case "Numeric":
                    $url =  'menutree/write_datapoint.json?SessionId=%id%&Id=' . $internalid . '&Type=Numeric&Value=' . $_options['slider'];
                    break;
                case "String":
                    $url = 'menutree/write_datapoint.json?SessionId=%id%&Id=' . $internalid . '&Type=String&Value=' . $_options['message'];
                    break;
                default:
                    log::add('OZW', 'info', 'Error creation action : ' . $item['Id'] . ' (' . $item['Text']['Long'] . ' : ' . $item['WriteAccess'] . ')');
                    die;
                    break;
            }
            if (isset($url)) {
                $obj = $carte->OZW_api($carte, $url);
                if (!isset($obj['Result']['Success']) || $obj['Result']['Success'] !== "true") {
                    log::add('OZW', 'error', $obj['Result']['Error']['Txt']);
                    return false;
                }
            }
            return true;
        }

        // Commande refresh
        if (substr($this->getLogicalId(), 0, 2) == 'R_') {
            $internalid = substr($this->getLogicalId(), 2);  // remove 'R_'

            $cmd = cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), $internalid);
            if (!is_object($cmd)) {
                log::add('OZW', 'debug', 'Commande non trouvée ' . $internalid);
                return false;
            }
            return $eqLogic->refresh_info_cmd($carte, $cmd);
        }
    }


    public function dontRemoveCmd()
    {
        $eqLogic = $this->getEqLogic();
        if (is_object($eqLogic)) {

            if ($eqLogic->getConfiguration('type', '') == 'OZW') {
                if ($this->getLogicalId() == 'status' || $this->getLogicalId() == 'SessionId') {
                    return true;
                }
            } else {
                if ($this->getLogicalId() == 'updatetime') {
                    return true;
                }
            }
            return false;
        }
    }
}
