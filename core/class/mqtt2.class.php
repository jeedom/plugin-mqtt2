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
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class mqtt2 extends eqLogic {
   /*     * *************************Attributs****************************** */


   /*     * ***********************Methode static*************************** */

   public static function generateCertificates() {
      $path = __DIR__ . '/../../data/ssl';
      if (!file_exists($path)) {
         mkdir($path);
      }
      if (!file_exists($path . '/mosq-ca.key')) {
         shell_exec('openssl genrsa -out ' . $path . '/mosq-ca.key 2048');
      }
      if (!file_exists($path . '/mosq-ca.crt')) {
         shell_exec('openssl req -new -x509 -days 3650 -subj "/C=FR/ST=Paris/L=Paris/O=jeedom/CN=127.0.0.1" -key ' . $path . '/mosq-ca.key -out ' . $path . '/mosq-ca.crt');
      }
      if (!file_exists($path . '/mosq-serv.key')) {
         shell_exec('openssl genrsa -out ' . $path . '/mosq-serv.key 2048');
      }
      if (!file_exists($path . '/mosq-serv.csr')) {
         shell_exec('openssl req -new -subj "/C=FR/ST=Paris/L=Paris/O=jeedom/CN=127.0.0.1" -key ' . $path . '/mosq-serv.key -out ' . $path . '/mosq-serv.csr');
      }
      if (!file_exists($path . '/mosq-serv.crt')) {
         shell_exec('openssl x509 -req -in ' . $path . '/mosq-serv.csr -CA ' . $path . '/mosq-ca.crt -CAkey ' . $path . '/mosq-ca.key -CAcreateserial -out ' . $path . '/mosq-serv.crt -days 3650 -sha256');
      }
      config::save('ssl::ca', trim(file_get_contents($path . '/mosq-ca.crt')), 'mqtt2');
   }

   public static function setPassword() {
      $path = __DIR__ . '/../../data/passwords';
      if (config::byKey('mqtt::password', 'mqtt2') == '') {
         config::save('mqtt::password', "jeedom:" . config::genKey(), 'mqtt2');
      }
      file_put_contents($path, config::byKey('mqtt::password', 'mqtt2'));
      shell_exec('mosquitto_passwd -U ' . $path);
   }

   public static function installMosquitto() {
      self::setPassword();
      $compose = file_get_contents(__DIR__ . '/../../resources/docker_compose.yaml');
      $compose = str_replace('#jeedom_path#', realpath(__DIR__ . '/../../../../'), $compose);
      $docker = self::byLogicalId('1::mqtt2_mosquitto', 'docker2');
      if (!is_object($docker)) {
         $docker = new docker2();
      }
      $docker->setLogicalId('1::mqtt2_mosquitto');
      $docker->setName('mqtt2_mosquitto');
      $docker->setIsEnable(1);
      $docker->setEqType_name('docker2');
      $docker->setConfiguration('name', 'mqtt2_mosquitto');
      $docker->setConfiguration('docker_number', 1);
      $docker->setConfiguration('create::mode', 'jeedom_compose');
      $docker->setConfiguration('create::compose', $compose);
      $docker->setIsEnable(1);
      $docker->save();
      $docker->rm();
      sleep(5);
      $docker->create();
      docker2::pull();
   }

   public static function deamon_info() {
      $return = array();
      $return['log'] = 'mqtt2';
      $return['state'] = 'nok';
      $pid_file = jeedom::getTmpFolder('mqtt2') . '/deamon.pid';
      if (file_exists($pid_file)) {
         if (@posix_getsid(trim(file_get_contents($pid_file)))) {
            $return['state'] = 'ok';
         } else {
            shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
         }
      }
      $return['launchable'] = 'ok';
      return $return;
   }

   public static function deamon_start() {
      log::remove(__CLASS__ . '_update');
      self::deamon_stop();
      $deamon_info = self::deamon_info();
      if ($deamon_info['launchable'] != 'ok') {
         throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
      }
      $mqtt2_path = realpath(dirname(__FILE__) . '/../../resources/mqtt2d');
      chdir($mqtt2_path);
      $authentifications = explode(':', explode("\n", config::byKey('mqtt::password', 'mqtt2'))[0]);
      $cmd = 'sudo /usr/bin/node ' . $mqtt2_path . '/mqtt2d.js';
      $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('mqtt2'));
      $cmd .= ' --socketport ' . config::byKey('socketport', 'mqtt2');
      if (config::byKey('mode', 'mqtt2') == 'local') {
         $cmd .= ' --mqtt_server mqtt://127.0.0.1:1883';
      } else {
         $cmd .= ' --mqtt_server ' . config::byKey('remote::ip', 'mqtt2');
      }
      $cmd .= ' --username ' . $authentifications[0];
      $cmd .= ' --password ' . $authentifications[1];
      $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/mqtt2/core/php/jeeMqtt2.php';
      $cmd .= ' --apikey ' . jeedom::getApiKey('mqtt2');
      $cmd .= ' --cycle ' . config::byKey('cycle', 'mqtt2');
      $cmd .= ' --pid ' . jeedom::getTmpFolder('mqtt2') . '/deamon.pid';
      log::add('mqtt2', 'info', 'Lancement démon mqtt2 : ' . $cmd);
      $result = exec($cmd . ' >> ' . log::getPathToLog('mqtt2d') . ' 2>&1 &');
      $i = 0;
      while ($i < 30) {
         $deamon_info = self::deamon_info();
         if ($deamon_info['state'] == 'ok') {
            break;
         }
         sleep(1);
         $i++;
      }
      if ($i >= 30) {
         log::add('mqtt2', 'error', 'Impossible de lancer le démon mqtt2d, vérifiez le log', 'unableStartDeamon');
         return false;
      }
      message::removeAll('mqtt2', 'unableStartDeamon');
      return true;
   }

   public static function deamon_stop() {
      $pid_file = jeedom::getTmpFolder('mqtt2') . '/deamon.pid';
      if (file_exists($pid_file)) {
         $pid = intval(trim(file_get_contents($pid_file)));
         system::kill($pid);
      }
      system::kill('mqtt2d.js');
      system::fuserk(config::byKey('socketport', 'mqtt2'));
   }

   public static function getPluginForTopic($_topic) {
      $mapping = config::byKey('mapping', 'mqtt2');
      if (isset($mapping[$_topic])) {
         return $mapping[$_topic];
      }
      return 'mqtt2';
   }

   public static function addPluginTopic($_plugin, $_topic) {
      $mapping = config::byKey('mapping', 'mqtt2');
      $mapping[$_topic] = $_plugin;
      config::save('mapping', $mapping, 'mqtt2');
   }

   public static function removePluginTopic($_topic) {
      $mapping = config::byKey('mapping', 'mqtt2');
      unset($mapping[$_topic]);
      config::save('mapping', $mapping, 'mqtt2');
   }

   public static function handleMqttMessage($_message) {
      log::add('mqtt2', 'debug', 'Received message without plugin handler : ' . json_encode($_message));
      foreach ($_message as $topic => $message) {
         $eqlogics = self::byLogicalId($topic, 'mqtt2', true);
         if (count($eqlogics) == 0) {
            continue;
         }
         $values = implode_recursive($message, '/');
         foreach ($eqlogics as $eqlogic) {
            foreach ($values as $key => $value) {
               $eqlogic->checkAndUpdateCmd($key, $value);
            }
         }
      }
   }

   public static function publish($_topic, $_message) {
      $request_http = new com_http('http://127.0.0.1:' . config::byKey('socketport', 'mqtt2') . '/publish?apikey=' . jeedom::getApiKey('mqtt2'));
      $request_http->setHeader(array(
         'Content-Type: application/json'
      ));
      if (is_array($_message) || is_object($_message)) {
         $_message = json_encode($_message);
      }
      $request_http->setPost(json_encode(array('topic' => $_topic, 'message' => $_message)));
      $result = json_decode($request_http->exec(30), true);
      if ($result['state'] != 'ok') {
         throw new Exception(json_encode($result));
      }
   }


   /*     * *********************Méthodes d'instance************************* */

   /*     * **********************Getteur Setteur*************************** */
}

class mqtt2Cmd extends cmd {
   /*     * *************************Attributs****************************** */


   /*     * ***********************Methode static*************************** */


   /*     * *********************Methode d'instance************************* */


   public function execute($_options = array()) {
      if ($this->getType() != 'action') {
         return;
      }
      $eqLogic = $this->getEqLogic();
      $value = $this->getConfiguration('message');
      switch ($this->getSubType()) {
         case 'slider':
            $value = str_replace('#slider#', $_options['slider'], $value);
            break;
         case 'color':
            $value = str_replace('#color#', $_options['color'], $value);
            break;
         case 'select':
            $value = str_replace('#select#', $_options['select'], $value);
            break;
      }
      mqtt2::publish($eqLogic->getLogicalid() . '/' . $this->getLogicalId(), $value);
   }

   /*     * **********************Getteur Setteur*************************** */
}
