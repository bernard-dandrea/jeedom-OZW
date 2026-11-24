<?php

// Last Modified : 2026/08/22 18:45:11

/*
 * Copyright (C) 2026 Bernard Dandrea
 * SPDX-License-Identifier: GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.html
 */

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    ajax::init();

    if (init('action') == 'devices_import') {

        $eqLogic = OZW::byId(init('id'));
        if (!is_object($eqLogic)) {
            throw new \Exception(__('OZW eqLogic non trouvé : ', __FILE__) . init('id'));
        }
        $OZW = $eqLogic->devices_import();
        ajax::success($OZW);
    }

    if (init('action') == 'main_commands_import') {

        $eqLogic = OZW::byId(init('id'));
        if (!is_object($eqLogic)) {
            throw new \Exception(__('OZW eqLogic non trouvé : ', __FILE__) . init('id'));
        }

		$OZW  = $eqLogic->main_commands_import();
        ajax::success($OZW);
    }

    if (init('action') == 'MenuImport') {

        $eqLogic = OZW::byId(init('id'));
        if (!is_object($eqLogic)) {
            throw new \Exception(__('OZW eqLogic non trouvé : ', __FILE__) . init('id'));
        }

        $idmenu = init('idmenu');
        $OZW = $eqLogic->MenuImport($idmenu);
        ajax::success($OZW);
    }

    if (init('action') == 'create_command') {

        $eqLogic = OZW::byId(init('id'));
        if (!is_object($eqLogic)) {
            throw new \Exception(__('OZW eqLogic non trouvé : ', __FILE__) . init('id'));
        }
        
        $id_commande = init('id_commande');
        $_info = init('_info');
        $_action = init('_action');
        $_refresh = init('_refresh');
        $OZW = $eqLogic->create_command($id_commande, $_info,$_action,$_refresh);
        ajax::success($OZW);
    }

    if (init('action') == 'enable_cron') {
        OZW::enable_cron(init('enable'));
        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}