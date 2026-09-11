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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
if (!jeedom::apiAccess(init('apikey'), 'mqtt2')) {
    echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
    die();
}
if (isset($_GET['test'])) {
    echo 'OK';
    die();
}
$results = json_decode(file_get_contents("php://input"), true);
if(is_array($results)){
    $linkedJeedoms = array_filter(array_map('trim', explode(',', config::byKey('jeedom::link', 'mqtt2'))));
    foreach ($results as $key => $value) {
        $plugin = mqtt2::getPluginForTopic($key);

        if ($plugin == 'mqtt2' && in_array($key, $linkedJeedoms, true) && is_array($value) && isset($value['cmd']['event']) && is_array($value['cmd']['event'])) {
            foreach ($value['cmd']['event'] as $remoteCmdId => $event) {
                if (!is_array($event) || !array_key_exists('value', $event) || is_array($event['value']) || is_object($event['value'])) {
                    continue;
                }
                $cmds = cmd::byLogicalId('cmd/event/' . $remoteCmdId . '/value', 'info');
                foreach ($cmds as $cmd) {
                    $eqLogic = $cmd->getEqLogic();
                    if (!is_object($eqLogic) || $eqLogic->getEqType_name() != 'mqtt2' || $eqLogic->getLogicalId() != $key) {
                        continue;
                    }
                    log::add('mqtt2', 'debug', $cmd->getHumanName() . ' ' . __(' mise à jour de  la valeur avec ', __FILE__) . ' : ' . $event['value']);
                    $eqLogic->checkAndUpdateCmd($cmd, $event['value']);
                    unset($value['cmd']['event'][$remoteCmdId]);
                    break;
                }
            }
            if (count($value['cmd']['event']) == 0) {
                unset($value['cmd']['event']);
            }
            if (isset($value['cmd']) && count($value['cmd']) == 0) {
                unset($value['cmd']);
            }
            if (count($value) == 0) {
                continue;
            }
        }

        if (class_exists($plugin) && method_exists($plugin, 'handleMqttMessage')) {
            $plugin::handleMqttMessage(array($key => $value));
        } else {
            mqtt2::removePluginTopicByPlugin($plugin);
        }
    }
}
