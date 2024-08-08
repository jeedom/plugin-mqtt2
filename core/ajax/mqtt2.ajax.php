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

try {
  require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
  include_file('core', 'authentification', 'php');

  if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
  }

  ajax::init();

  if (init('action') == 'sendDiscovery') {
    mqtt2::sendDiscovery();
    ajax::success();
  }

  if (init('action') == 'eqLogicTransmitConfiguration') {
    $eqLogics = json_decode(init('eqLogics'),true);
    foreach ($eqLogics as $_eqLogic) {
      $eqLogic = eqLogic::byId($_eqLogic['id']);
      if(!is_object($eqLogic)){
        continue;
      }
      $eqLogic->setConfiguration('plugin::mqtt2::mqttTranmit',$_eqLogic['configuration']['plugin::mqtt2::mqttTranmit']);
      $eqLogic->save(true);
    }
    ajax::success();
  }
  

  if (init('action') == 'installMosquitto') {
    mqtt2::installMosquitto(config::byKey('mode', 'mqtt2'));
    ajax::success();
  }

  if (init('action') == 'restartMosquitto') {
    mqtt2::restartMosquitto();
    ajax::success();
  }

  if (init('action') == 'removePluginTopic') {
    mqtt2::removePluginTopic(init('topic'));
    ajax::success();
  }

  if (init('action') == 'stopMosquitto') {
    mqtt2::stopMosquitto();
    ajax::success();
  }

  if (init('action') == 'uninstallMosquitto') {
    mqtt2::uninstallMosquitto();
    ajax::success();
  }

  if (init('action') == 'downloadClientCert') {
    mqtt2::generateClientCert();
    shell_exec('tar czvf ' . __DIR__ . '/../../data/mqtt-client-ssl.tar.gz ' . jeedom::getTmpFolder('mqtt2') . '/ssl;sudo rm -rf ' . jeedom::getTmpFolder('mqtt2') . '/ssl');
    ajax::success();
  }

  if (init('action') == 'createFromTemplate') {
    $eqLogic = eqLogic::byId(init('eqLogic_id'));
    if (!is_object($eqLogic)) {
      throw new Exception('{{Equipement introuvable}} : ' . init('eqLogic_id'));
    }
    if ($eqLogic->getEqType_name() != 'mqtt2') {
      throw new Exception('{{Equipement pas de type MQTT}}');
    }
    $eqLogic->applyCmdTemplate(json_decode(init('config'), true));
    ajax::success();
  }

  if (init('action') == 'createDiscoverCmd') {
    $eqLogic = eqLogic::byId(init('eqLogic_id'));
    if (!is_object($eqLogic)) {
      throw new Exception('{{Equipement introuvable}} : ' . init('eqLogic_id'));
    }
    if ($eqLogic->getEqType_name() != 'mqtt2') {
      throw new Exception('{{Equipement pas de type MQTT}}');
    }
    $discoverCmd = $eqLogic->getDiscover();
    $discovers = json_decode(init('discover'), true);
    foreach ($discovers as $discover) {
      if ($discover['create'] == 0) {
        continue;
      }
      $cmd = new mqtt2Cmd();
      $cmd->setName($discover['name']);
      $cmd->setEqLogic_id($eqLogic->getId());
      $cmd->setLogicalId($discover['logicalId']);
      $cmd->setType('info');
      $cmd->setSubtype($discover['subType']);
      $cmd->save();
      if (isset($discoverCmd[$discover['logicalId']])) {
        unset($discoverCmd[$discover['logicalId']]);
      }
    }
    $eqLogic->setDiscover($discoverCmd);
    ajax::success();
  }

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
} catch (Exception $e) {
  ajax::error(displayException($e), $e->getCode());
}
