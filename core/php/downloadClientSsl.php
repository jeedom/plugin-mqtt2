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
require_once __DIR__  . '/../../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

mqtt2::generateClientCert();
if (!file_exists(jeedom::getTmpFolder('mqtt2') . '/ssl')) {
    throw new Exception(__('Erreur lors de la génération des certificat client', __FILE__));
}
shell_exec('cd ' . jeedom::getTmpFolder('mqtt2') . '/ssl;tar czvf ' . __DIR__ . '/../../data/mqtt-client-ssl.tar.gz ca.crt client.crt client.key;sudo rm -rf ' . jeedom::getTmpFolder('mqtt2') . '/ssl');
$pathfile = __DIR__ . '/../../data/mqtt-client-ssl.tar.gz';
$size = filesize($pathfile);
header("Content-Length: \".$size.\"");
$path_parts = pathinfo($pathfile);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . $path_parts['basename']);
readfile($pathfile);
if (file_exists($pathfile)) {
    shell_exec('sudo rm -f ' . $pathfile);
}
exit;
