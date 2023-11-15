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
//log::add('mqtt2', 'debug', json_encode($results));

foreach ($results as $key => $value) {
    $plugin = mqtt2::getPluginForTopic($key);
    if (class_exists($plugin) && method_exists($plugin, 'handleMqttMessage')) {
        $plugin::handleMqttMessage(array($key => $value));
    } else {
        mqtt2::removePluginTopic($key);
    }
    if ($key == config::byKey('root_topic', 'mqtt2')) {
        mqtt2::handleMqttMessage(array($key => $value));
    }
}
