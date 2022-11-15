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

  if (init('action') == 'installMosquitto') {
    mqtt2::installMosquitto(config::byKey('mode', 'mqtt2'));
    ajax::success();
  }

  if (init('action') == 'uninstallMosquitto') {
    if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') != 0) {
      event::add('jeedom::alert', array(
        'level' => 'warning',
        'page' => 'plugin',
        'message' => __('Désinstallation du broker Mosquitto en cours', __FILE__),
      ));
      shell_exec(system::getCmdSudo() . ' apt remove -y mosquitto');
    } else if (is_object(eqLogic::byLogicalId('1::mqtt2_mosquitto', 'docker2'))) {
      throw new Exception(__("Veuillez vous référer à la documentation pour supprimer le broker Mosquitto géré par le plugin Docker Management", __FILE__));
    } else {
      throw new Exception(__("Aucun broker Mosquitto trouvé", __FILE__));
    }
    ajax::success();
  }

  if (init('action') == 'downloadClientCert') {
    mqtt2::generateClientCert();
    shell_exec('tar czvf ' . __DIR__ . '/../../data/mqtt-client-ssl.tar.gz ' . jeedom::getTmpFolder('mqtt2') . '/ssl;sudo rm -rf ' . jeedom::getTmpFolder('mqtt2') . '/ssl');
    ajax::success();
  }

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
} catch (Exception $e) {
  ajax::error(displayException($e), $e->getCode());
}
