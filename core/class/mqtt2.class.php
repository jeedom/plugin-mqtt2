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

   public static function dependancy_end() {
      if (config::byKey('mode', __CLASS__) != 'local') {
         return;
      }
      $docker = self::byLogicalId('1::mqtt2_mosquitto', 'docker2');
      if (is_object($docker)) {
         return;
      }
      self::installMosquitto();
   }

   public static function generateCertificates() {
      $path = __DIR__ . '/../../data/ssl';
      if (!file_exists($path)) {
         mkdir($path);
      }
      shell_exec('sudo chmod -R 777  ' . $path);
      shell_exec('sudo chown -R www-data ' . $path);
      if (!file_exists($path . '/ca.key')) {
         shell_exec('sudo openssl genrsa -out ' . $path . '/ca.key 2048');
      }
      if (!file_exists($path . '/ca.crt')) {
         shell_exec('sudo openssl req -new -x509 -days 9999 -subj "/C=FR/ST=Paris/L=Paris/O=jeedom/CN=jeedom" -key ' . $path . '/ca.key -out ' . $path . '/ca.crt');
      }
      if (!file_exists($path . '/mosquitto.key')) {
         shell_exec('sudo openssl genrsa -out ' . $path . '/mosquitto.key 2048');
      }
      if (!file_exists($path . '/mosquitto.csr')) {
         shell_exec('sudo openssl req -new -subj "/C=FR/ST=Paris/L=Paris/O=jeedom/CN=jeedom-mosquitto" -key ' . $path . '/mosquitto.key -out ' . $path . '/mosquitto.csr');
      }
      if (!file_exists($path . '/mosquitto.crt')) {
         shell_exec('sudo openssl x509 -req -in ' . $path . '/mosquitto.csr -CA ' . $path . '/ca.crt -CAkey ' . $path . '/ca.key -CAcreateserial -out ' . $path . '/mosquitto.crt -days 9999 -sha256');
      }
      shell_exec('sudo chmod -R 777  ' . $path);
      shell_exec('sudo chown -R www-data ' . $path);
   }

   public static function generateClientCert() {
      $path = realpath(__DIR__ . '/../../data/ssl');
      if (!file_exists($path) || !file_exists($path . '/ca.key') || !file_exists($path . '/ca.crt')) {
         throw new Exception(__('Aucun dossier SSL trouvé, avez vous installé Mosquitto d\'abord ?', __FILE__));
      }
      $tmp_folder = jeedom::getTmpFolder(__CLASS__) . '/ssl';
      if (file_exists($tmp_folder)) {
         shell_exec('sudo rm -rf ' . $tmp_folder);
      }
      mkdir($tmp_folder);
      shell_exec('sudo openssl genrsa -out ' . $tmp_folder . '/client.key 2048');
      shell_exec('sudo openssl req -new -subj "/C=FR/ST=Paris/L=Paris/O=jeedom/CN=jeedom-client-' . rand(1111, 9999) . '" -key ' . $tmp_folder . '/client.key -out ' . $tmp_folder . '/client.csr');
      shell_exec('sudo openssl x509 -req -in ' . $tmp_folder . '/client.csr -CA ' . $path . '/ca.crt -CAkey ' . $path . '/ca.key -CAcreateserial -out ' . $tmp_folder . '/client.crt -days 9999 -sha256');
      shell_exec('sudo rm ' . $tmp_folder . '/client.csr');
      shell_exec('sudo cp ' . $path . '/ca.crt ' . $tmp_folder . '/ca.crt');
      shell_exec('sudo chown -R www-data ' . $tmp_folder);
   }

   public static function setPassword() {
      $path = __DIR__ . '/../../data/passwords';
      if (trim(config::byKey('mqtt::password', __CLASS__)) == '') {
         config::save('mqtt::password', "jeedom:" . config::genKey(), __CLASS__);
      }
      unlink($path);
      file_put_contents($path, config::byKey('mqtt::password', __CLASS__));
      shell_exec('sudo docker run --rm -v ' . $path . ':/passwords eclipse-mosquitto:latest mosquitto_passwd -U /passwords');
   }

   public static function installDocker2() {
      try {
         plugin::byId('docker2');
      } catch (Exception $e) {
         event::add('jeedom::alert', array(
            'level' => 'warning',
            'page' => 'plugin',
            'message' => __('Installation du plugin Docker Management', __FILE__),
         ));
         $update = update::byLogicalId('docker2');
         if (!is_object($update)) {
            $update = new update();
         }
         $update->setLogicalId('docker2');
         $update->setSource('market');
         $update->setConfiguration('version', 'beta');
         $update->save();
         $update->doUpdate();
         $plugin = plugin::byId('docker2');

         if (!is_object($plugin)) {
            throw new Exception(__('Le plugin Docker management doit être installé', __FILE__));
         }
         if (!$plugin->isActive()) {
            $plugin->setIsEnable(1);
            $plugin->dependancy_install();
         }
         if (!$plugin->isActive()) {
            throw new Exception(__('Le plugin Docker management doit être actif', __FILE__));
         }
         event::add('jeedom::alert', array(
            'level' => 'warning',
            'page' => 'plugin',
            'ttl' => 250000,
            'message' => __('Pause de 120s le temps de l\'installation des dépendances du plugin Docker Management', __FILE__),
         ));
         $i = 0;
         while (system::installPackageInProgress('docker2')) {
            sleep(5);
            $i++;
            if ($i > 50) {
               throw new Exception(__('Delai maximum autorisé pour l\'installation des dépendances dépassé', __FILE__));
            }
         }
      }
   }

   public static function installMosquitto() {
      if (shell_exec('sudo which mosquitto | wc -l') != 0) {
         throw new Exception(__('Mosquitto installé en local sur la machine, merci de le supprimer avant l\'installation du container Mosquitto : sudo apt remove mosquitto', __FILE__));
      }
      self::installDocker2();
      self::generateCertificates();
      event::add('jeedom::alert', array(
         'level' => 'warning',
         'page' => 'plugin',
         'ttl' => 1000,
         'message' => __('Génération des certificats', __FILE__),
      ));
      sleep(1);
      self::setPassword();
      event::add('jeedom::alert', array(
         'level' => 'warning',
         'page' => 'plugin',
         'ttl' => 1000,
         'message' => __('Mise en place des identifiants MQTT', __FILE__),
      ));
      sleep(1);
      event::add('jeedom::alert', array(
         'level' => 'warning',
         'page' => 'plugin',
         'ttl' => 30000,
         'message' => __('Création du container Mosquitto', __FILE__),
      ));
      $compose = file_get_contents(__DIR__ . '/../../resources/docker_compose.yaml');
      $compose = str_replace('#jeedom_path#', realpath(__DIR__ . '/../../../../'), $compose);
      $ports = '';
      foreach (explode("\n", config::byKey('mosquitto::ports', __CLASS__)) as $line) {
         $ports .= '      - ' . $line . "\n";
      }
      $compose = str_replace('#ports#', $ports, $compose);
      if (!class_exists('docker2')) {
         include_file('core', 'docker2', 'class', 'docker2');
      }
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
      $docker->save();
      try {
         $docker->rm();
         sleep(5);
      } catch (\Throwable $th) {
      }
      unlink(__DIR__ . '/../../data/mosquitto.conf');
      file_put_contents(__DIR__ . '/../../data/mosquitto.conf', str_replace("\r\n", "\n", config::byKey('mosquitto::parameters', __CLASS__)));
      $docker->create();
      config::save('mode', 'local', __CLASS__);
   }

   public static function deamon_info() {
      $return = array();
      $return['log'] = __CLASS__;
      $return['state'] = 'nok';
      $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
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
      if (config::byKey('mode', __CLASS__) == 'local') {
         $path_ssl = realpath(__DIR__ . '/../../data/ssl');
         if (!file_exists($path_ssl . '/client.crt') || !file_exists($path_ssl . '/client.key')) {
            self::generateClientCert();
            shell_exec('sudo cp ' . jeedom::getTmpFolder(__CLASS__) . '/ssl/client.* ' . $path_ssl . '/');
            shell_exec('sudo rm -rf ' . jeedom::getTmpFolder(__CLASS__) . '/ssl');
         }
         shell_exec('sudo chown -R www-data ' . $path_ssl);
      }
      $mqtt2_path = realpath(dirname(__FILE__) . '/../../resources/mqtt2d');
      chdir($mqtt2_path);
      $authentifications = explode(':', explode("\n", config::byKey('mqtt::password', __CLASS__))[0]);
      $cmd = 'sudo /usr/bin/node ' . $mqtt2_path . '/mqtt2d.js';
      $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
      $cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__);
      if (config::byKey('mode', __CLASS__) == 'local') {
         $cmd .= ' --mqtt_server mqtts://127.0.0.1:8883';
         $cmd .= ' --client_key ' . $path_ssl . '/client.key';
         $cmd .= ' --client_crt ' . $path_ssl . '/client.crt';
         $cmd .= ' --ca ' . $path_ssl . '/ca.crt';
      } else {
         $cmd .= ' --mqtt_server ' . config::byKey('remote::protocol', __CLASS__) . '://' . config::byKey('remote::ip', __CLASS__) . ':' . config::byKey('remote::port', __CLASS__);
      }
      $cmd .= ' --username ' . $authentifications[0];
      $cmd .= ' --password ' . $authentifications[1];
      $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/mqtt2/core/php/jeeMqtt2.php';
      $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
      $cmd .= ' --cycle ' . config::byKey('cycle', __CLASS__);
      $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
      log::add(__CLASS__, 'info', 'Lancement démon mqtt2 : ' . $cmd);
      exec($cmd . ' >> ' . log::getPathToLog('mqtt2d') . ' 2>&1 &');
      $i = 0;
      while ($i < 10) {
         $deamon_info = self::deamon_info();
         if ($deamon_info['state'] == 'ok') {
            break;
         }
         sleep(1);
         $i++;
      }
      if ($i >= 30) {
         log::add(__CLASS__, 'error', 'Impossible de lancer le démon mqtt2d, vérifiez le log', 'unableStartDeamon');
         return false;
      }
      message::removeAll(__CLASS__, 'unableStartDeamon');
      return true;
   }

   public static function deamon_stop() {
      $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
      if (file_exists($pid_file)) {
         $pid = intval(trim(file_get_contents($pid_file)));
         system::kill($pid);
      }
      system::kill('mqtt2d.js');
      system::fuserk(config::byKey('socketport', __CLASS__));
   }

   public static function getPluginForTopic($_topic) {
      $mapping = config::byKey('mapping', __CLASS__);
      if (isset($mapping[$_topic])) {
         return $mapping[$_topic];
      }
      return __CLASS__;
   }

   public static function addPluginTopic($_plugin, $_topic) {
      $mapping = config::byKey('mapping', __CLASS__, array());
      $mapping[$_topic] = $_plugin;
      config::save('mapping', $mapping, __CLASS__);
   }

   public static function removePluginTopic($_topic) {
      $mapping = config::byKey('mapping', __CLASS__, array());
      unset($mapping[$_topic]);
      config::save('mapping', $mapping, __CLASS__);
   }
   
   public static function getSubscribed() {
      $mapping = config::byKey('mapping', __CLASS__, array());
      return $mapping;
   }
   
   public static function getFormatedInfos() {
      $infos = array();
      $authentifications = explode(':', explode("\n", config::byKey('mqtt::password', __CLASS__))[0]);
      if (config::byKey('mode', __CLASS__) == 'local') {
        $infos['ip'] = '127.0.0.1';
        $infos['port'] = '1883';
      } else {
        $infos['ip'] = config::byKey('remote::ip', __CLASS__);
        $infos['port'] = config::byKey('remote::port', __CLASS__);
      }
      $infos['user'] = $authentifications[0];
      $infos['password'] = $authentifications[1];
      return $infos;
   }

   public static function handleMqttMessage($_message) {
      log::add(__CLASS__, 'debug', 'Received message without plugin handler : ' . json_encode($_message));
      foreach ($_message as $topic => $message) {
         if ($topic == config::byKey('root_topic', __CLASS__)) {
            if (isset($message['cmd'])) {
               if (isset($message['cmd']['get'])) {
                  foreach ($message['cmd']['get'] as $cmd_id => $options) {
                     $cmd = cmd::byId($cmd_id);
                     if (!is_object($cmd) && $cmd->getType() == 'info') {
                        continue;
                     }
                     self::publish(config::byKey('root_topic', __CLASS__) . '/cmd/value/' . $cmd_id, (string) $cmd->execCmd());
                  }
               }
               if (isset($message['cmd']['set'])) {
                  foreach ($message['cmd']['set'] as $cmd_id => &$options) {
                     $cmd = cmd::byId($cmd_id);
                     if (!is_object($cmd)) {
                        continue;
                     }
                     $options = is_json($options, $options);
                     if ($cmd->getType() == 'action') {
                        $cmd->execCmd(json_decode($options, true));
                     } else {
                        if (!is_array($options)) {
                           $cmd->event($options);
                        } else {
                           if (!isset($options['datetime'])) {
                              $options['datetime'] = null;
                           }
                           $cmd->event($options['value'], $options['datetime']);
                        }
                     }
                  }
               }
            }
            continue;
         }
         $eqlogics = self::byLogicalId($topic, __CLASS__, true);
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
      $request_http = new com_http('http://127.0.0.1:' . config::byKey('socketport', __CLASS__) . '/publish?apikey=' . jeedom::getApiKey(__CLASS__));
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


   public static function handleEvent($_option) {
      $message = array('value' => $_option['value']);
      $cmd = cmd::byId($_option['event_id']);
      if (is_object($cmd)) {
         $message['humanName'] = $cmd->getHumanName();
         $message['unite'] = $cmd->getUnite();
         $message['name'] = $cmd->getName();
         $message['type'] = $cmd->getType();
         $message['subtype'] = $cmd->getSubType();
      }
      self::publish(config::byKey('root_topic', __CLASS__) . '/cmd/event/' . $_option['event_id'], $message);
   }

   public static function postConfig_sendEvent($_value) {
      if ($_value == 0) {
         $listener = listener::byClassAndFunction(__CLASS__, 'handleEvent');
         if (is_object($listener)) {
            $listener->remove();
         }
      } else {
         $listener = listener::byClassAndFunction(__CLASS__, 'handleEvent');
         if (!is_object($listener)) {
            $listener = new listener();
         }
         $listener->setClass(__CLASS__);
         $listener->setFunction('handleEvent');
         $listener->emptyEvent();
         $listener->addEvent('*');
         $listener->save();
      }
   }
}

class mqtt2Cmd extends cmd {

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
}
