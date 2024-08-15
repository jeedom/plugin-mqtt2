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

   public static function cronDaily(){
      self::sendBattery();
   }

   public static function devicesParameters($_device = '') {
      $return = array();
      foreach (ls(__DIR__ . '/../config/devices', '*') as $dir) {
         $path = __DIR__ . '/../config/devices/' . $dir;
         if (!is_dir($path)) {
            continue;
         }
         $files = ls($path, '*.json', false, array('files', 'quiet'));
         foreach ($files as $file) {
            try {
               $content = is_json(file_get_contents($path . '/' . $file), false);
               if ($content != false) {
                  $content['manufacturer'] = ucfirst(trim($dir, '/'));
                  $return[str_replace('.json', '', $file)] = $content;
               }
            } catch (Exception $e) {
            }
         }
      }
      if (isset($_device) && $_device != '') {
         if (isset($return[$_device])) {
            return $return[$_device];
         }
         foreach ($return as $device => $value) {
            if (strtolower($device) == strtolower($_device)) {
               return $value;
            }
         }
         return array();
      }
      return $return;
   }

   public static function dependancy_end() {
      $mode = config::byKey('mode', __CLASS__, 'local');
      if ($mode == 'none') {
         return;
      }
      if ($mode != 'local' && $mode != 'docker') {
         return;
      }
      if ($mode == 'docker' && is_object(eqLogic::byLogicalId('1::mqtt2_mosquitto', 'docker2'))) {
         return;
      }
      self::installMosquitto($mode);
   }

   public static function generateCertificates() {
      $path = __DIR__ . '/../../data/ssl';
      if (!file_exists($path)) {
         mkdir($path);
      }
      shell_exec(system::getCmdSudo() . ' chmod -R 777  ' . $path);
      shell_exec(system::getCmdSudo() . ' chown -R www-data ' . $path);
      if (!file_exists($path . '/ca.key') || filesize($path . '/ca.key') == 0) {
         shell_exec(system::getCmdSudo() . ' openssl genrsa -out ' . $path . '/ca.key 2048');
      }
      if (!file_exists($path . '/ca.crt') || filesize($path . '/ca.crt') == 0) {
         shell_exec(system::getCmdSudo() . ' openssl req -new -x509 -days 9999 -subj "/C=FR/ST=Paris/L=Paris/O=jeedom/CN=jeedom" -key ' . $path . '/ca.key -out ' . $path . '/ca.crt');
      }
      if (!file_exists($path . '/mosquitto.key') || filesize($path . '/mosquitto.key') == 0) {
         shell_exec(system::getCmdSudo() . ' openssl genrsa -out ' . $path . '/mosquitto.key 2048');
      }
      if (!file_exists($path . '/mosquitto.csr') || filesize($path . '/mosquitto.csr') == 0) {
         shell_exec(system::getCmdSudo() . ' openssl req -new -subj "/C=FR/ST=Paris/L=Paris/O=jeedom/CN=jeedom-mosquitto" -key ' . $path . '/mosquitto.key -out ' . $path . '/mosquitto.csr');
      }
      if (!file_exists($path . '/mosquitto.crt') || filesize($path . '/mosquitto.crt') == 0) {
         shell_exec(system::getCmdSudo() . ' openssl x509 -req -in ' . $path . '/mosquitto.csr -CA ' . $path . '/ca.crt -CAkey ' . $path . '/ca.key -CAcreateserial -out ' . $path . '/mosquitto.crt -days 9999 -sha256');
      }
      shell_exec(system::getCmdSudo() . ' chmod -R 777  ' . $path);
      shell_exec(system::getCmdSudo() . ' chown -R www-data ' . $path);
   }

   public static function generateClientCert() {
      $path = realpath(__DIR__ . '/../../data/ssl');
      if (!file_exists($path) || !file_exists($path . '/ca.key') || !file_exists($path . '/ca.crt')) {
         throw new Exception(__('Aucun dossier SSL trouvé, veuillez rétablir les droits sur les dossiers et fichiers depuis la configuration Jeedom (onglet OS/DB) puis cliquez sur le bouton Installer Mosquitto', __FILE__));
      }
      $tmp_folder = jeedom::getTmpFolder(__CLASS__) . '/ssl';
      if (file_exists($tmp_folder)) {
         shell_exec(system::getCmdSudo() . ' rm -rf ' . $tmp_folder);
      }
      mkdir($tmp_folder);
      shell_exec(system::getCmdSudo() . ' openssl genrsa -out ' . $tmp_folder . '/client.key 2048');
      shell_exec(system::getCmdSudo() . ' openssl req -new -subj "/C=FR/ST=Paris/L=Paris/O=jeedom/CN=jeedom-client-' . rand(1111, 9999) . '" -key ' . $tmp_folder . '/client.key -out ' . $tmp_folder . '/client.csr');
      shell_exec(system::getCmdSudo() . ' openssl x509 -req -in ' . $tmp_folder . '/client.csr -CA ' . $path . '/ca.crt -CAkey ' . $path . '/ca.key -CAcreateserial -out ' . $tmp_folder . '/client.crt -days 9999 -sha256');
      shell_exec(system::getCmdSudo() . ' rm ' . $tmp_folder . '/client.csr');
      shell_exec(system::getCmdSudo() . ' cp ' . $path . '/ca.crt ' . $tmp_folder . '/ca.crt');
      shell_exec(system::getCmdSudo() . ' chown -R www-data ' . $tmp_folder);
   }

   public static function setPassword($_mode = 'local') {
      $path = __DIR__ . '/../../data/passwords';
      if (trim(config::byKey('mqtt::password', __CLASS__)) == '') {
         config::save('mqtt::password', "jeedom:" . config::genKey(), __CLASS__);
      }
      unlink($path);
      file_put_contents($path, config::byKey('mqtt::password', __CLASS__));
      if ($_mode == 'docker') {
         shell_exec(system::getCmdSudo() . ' docker run --rm -v ' . $path . ':/passwords eclipse-mosquitto:latest mosquitto_passwd -U /passwords');
      } else {
         shell_exec(system::getCmdSudo() . ' mosquitto_passwd -U ' . $path);
      }
   }

   public static function installLocalMosquitto() {
      if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') == 0) {
         event::add('jeedom::alert', array(
            'level' => 'warning',
            'page' => 'plugin',
            'message' => __('Installation locale du broker Mosquitto en cours', __FILE__),
         ));
         shell_exec(system::getCmdSudo() . ' apt update;' . system::getCmdSudo() . ' apt install -y mosquitto');
      }
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
         $update->setConfiguration('version', 'stable');
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
            throw new Exception(__('Le plugin Docker management doit être activé', __FILE__));
         }
         event::add('jeedom::alert', array(
            'level' => 'warning',
            'page' => 'plugin',
            'ttl' => 250000,
            'message' => __("Pause de 120s le temps de l'installation des dépendances du plugin Docker Management", __FILE__),
         ));
         $i = 0;
         while (system::installPackageInProgress('docker2')) {
            sleep(5);
            $i++;
            if ($i > 50) {
               throw new Exception(__("Délai maximum autorisé pour l'installation des dépendances dépassé", __FILE__));
            }
         }
      }
   }

   public static function installMosquitto($_mode = 'local') {
      if ($_mode == 'remote') {
         return;
      }
      if ($_mode == 'docker') {
         if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') != 0) {
            throw new Exception(__("Un broker Mosquitto est déjà installé en local sur la machine. Veuillez le désinstaller", __FILE__));
         }
         self::installDocker2();
      } else {
         self::installLocalMosquitto();
      }
      self::generateCertificates();
      event::add('jeedom::alert', array(
         'level' => 'warning',
         'page' => 'plugin',
         'ttl' => 1000,
         'message' => __('Génération des certificats', __FILE__),
      ));
      sleep(1);
      self::setPassword($_mode);
      event::add('jeedom::alert', array(
         'level' => 'warning',
         'page' => 'plugin',
         'ttl' => 1000,
         'message' => __('Mise en place des identifiants MQTT', __FILE__),
      ));
      sleep(1);

      if ($_mode == 'docker') {
         event::add('jeedom::alert', array(
            'level' => 'warning',
            'page' => 'plugin',
            'ttl' => 30000,
            'message' => __('Création du conteneur Mosquitto', __FILE__),
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
      }

      if ($_mode == 'local') {
         $replace = array(
            ' /mosquitto/' => ' ' . __DIR__ . '/../../data/',
            '/data/config/ssl/' => '/data/ssl/',
         );
      } else {
         $replace = array(
            ' ' . __DIR__ . '/../../data/'  => ' /mosquitto/',
            '/data/ssl/' => '/data/config/ssl/',
         );
      }
      config::save('mosquitto::parameters', str_replace(array_keys($replace), $replace, config::byKey('mosquitto::parameters', __CLASS__)), __CLASS__);
      //shell_exec(system::getCmdSudo() . ' chmod 777 '.__DIR__ . '/../../data/mosquitto.conf');
      file_put_contents(__DIR__ . '/../../data/mosquitto.conf', str_replace("\r\n", "\n", config::byKey('mosquitto::parameters', __CLASS__)));
      if ($_mode == 'docker') {
         $docker->create();
      } elseif (jeedom::getHardwareName() == 'docker') {
         $service = file_get_contents(__DIR__ . '/../../resources/mosquitto.init');
         file_put_contents('/tmp/mosquitto.init', str_replace("#config_path#", __DIR__ . '/../../data/mosquitto.conf', $service));
         shell_exec(system::getCmdSudo() . ' mv /tmp/mosquitto.init /etc/init.d/mosquitto');
         shell_exec(system::getCmdSudo() . ' chown root: /etc/init.d/mosquitto');
         shell_exec(system::getCmdSudo() . ' chmod uog+x /etc/init.d/mosquitto');
         shell_exec(system::getCmdSudo() . ' service mosquitto stop');
         shell_exec(system::getCmdSudo() . ' service mosquitto start');
      } else {
         $service = file_get_contents(__DIR__ . '/../../resources/mosquitto.service');
         file_put_contents('/tmp/mosquitto.service', str_replace("#config_path#", __DIR__ . '/../../data/mosquitto.conf', $service));
         shell_exec(system::getCmdSudo() . ' mv /tmp/mosquitto.service /lib/systemd/system/mosquitto.service');
         shell_exec(system::getCmdSudo() . ' chown root: /lib/systemd/system/mosquitto.service');
         shell_exec(system::getCmdSudo() . ' systemctl daemon-reload');
         shell_exec(system::getCmdSudo() . ' systemctl enable mosquitto');
         shell_exec(system::getCmdSudo() . ' systemctl stop mosquitto');
         shell_exec(system::getCmdSudo() . ' systemctl start mosquitto');
      }
   }

   public static function uninstallMosquitto() {
      if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') != 0) {
         event::add('jeedom::alert', array(
            'level' => 'warning',
            'page' => 'plugin',
            'message' => __('Désinstallation du broker Mosquitto en cours', __FILE__),
         ));
         self::stopMosquitto();
         shell_exec(system::getCmdSudo() . ' apt remove -y mosquitto');
      } else if (is_object(eqLogic::byLogicalId('1::mqtt2_mosquitto', 'docker2'))) {
         throw new Exception(__("Veuillez vous référer à la documentation pour supprimer le broker Mosquitto géré par le plugin Docker Management", __FILE__));
      } else {
         throw new Exception(__("Aucun broker Mosquitto trouvé", __FILE__));
      }
   }

   public static function restartMosquitto() {
      switch (config::byKey('mode', __CLASS__)) {
         case 'remote':
            throw new Exception(__('Cette action est impossible avec un brocker distant', __FILE__), 1);
            break;
         case 'docker':
            $docker = self::byLogicalId('1::mqtt2_mosquitto', 'docker2');
            if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') != 0) {
               throw new Exception(__('Veuillez désinstaller Mosquitto local', __FILE__));
            } else if (!is_object($docker)) {
               throw new Exception(__('Veuillez installer Mosquitto', __FILE__));
            }
            $docker->restartDocker();
            break;
         default:
            if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') == 0) {
               throw new Exception(__('Veuillez d\'abord installer Mosquitto', __FILE__), 1);
            }
            if (jeedom::getHardwareName() == 'docker') {
               shell_exec(system::getCmdSudo() . ' service mosquitto restart');
            } else {
               shell_exec(system::getCmdSudo() . ' systemctl restart mosquitto');
            }
            break;
      }
   }

   public static function stopMosquitto() {
      switch (config::byKey('mode', __CLASS__)) {
         case 'remote':
            throw new Exception(__('Cette action est impossible avec un brocker distant', __FILE__), 1);
            break;
         case 'docker':
            $docker = self::byLogicalId('1::mqtt2_mosquitto', 'docker2');
            if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') != 0) {
               throw new Exception(__('Veuillez désinstaller Mosquitto local', __FILE__));
            } else if (!is_object($docker)) {
               throw new Exception(__('Veuillez installer Mosquitto', __FILE__));
            }
            $docker->stopDocker();
            break;
         default:
            if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') == 0) {
               throw new Exception(__('Veuillez d\'abord installer Mosquitto', __FILE__), 1);
            }
            if (jeedom::getHardwareName() == 'docker') {
               shell_exec(system::getCmdSudo() . ' service mosquitto stop');
            } else {
               shell_exec(system::getCmdSudo() . ' systemctl stop mosquitto');
            }
            break;
      }
   }

   public static function deamon_info() {
      $return = array();
      $return['log'] = __CLASS__;
      $return['state'] = 'nok';
      $return['launchable'] = 'ok';
      switch (config::byKey('mode', __CLASS__)) {
         case 'remote':
            if (empty(config::byKey('remote::protocol', __CLASS__)) || empty(config::byKey('remote::ip', __CLASS__)) || empty(config::byKey('remote::port', __CLASS__))) {
               $return['launchable'] = 'nok';
               $return['launchable_message'] = __("Veuillez renseigner l'adresse complète du broker", __FILE__);
            }
            break;
         case 'docker':
            if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') != 0) {
               $return['launchable'] = 'nok';
               $return['launchable_message'] = __('Veuillez désinstaller Mosquitto local', __FILE__);
            } else if (!is_object(eqLogic::byLogicalId('1::mqtt2_mosquitto', 'docker2'))) {
               $return['launchable'] = 'nok';
               $return['launchable_message'] = __('Veuillez installer Mosquitto', __FILE__);
            }
            break;
         default:
            $return['launchable'] = 'ok';
            break;
      }

      $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
      if ($return['launchable'] == 'ok' && file_exists($pid_file)) {
         if (trim(file_get_contents($pid_file)) != '' && @posix_getsid((int)trim(file_get_contents($pid_file)))) {
            $return['state'] = 'ok';
         } else {
            if (trim(file_get_contents($pid_file)) != '') {
               shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
            }
         }
      }
      return $return;
   }

   public static function deamon_start() {
      log::remove(__CLASS__ . '_update');
      if (config::byKey('mode', __CLASS__, 'local') == 'local') {
         if (shell_exec(system::getCmdSudo() . ' ps ax | grep mosquitto | grep mqtt2 | grep -v grep | wc -l') == 0) {
            log::add(__CLASS__, 'warning', __('Service mosquitto non lancé, je lance une installation', __FILE__));
            self::installMosquitto(config::byKey('mode', __CLASS__, 'local'));
         }
      }
      self::deamon_stop();
      $deamon_info = self::deamon_info();
      if ($deamon_info['launchable'] != 'ok') {
         throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
      }
      if (config::byKey('mode', __CLASS__) == 'local' || config::byKey('mode', __CLASS__) == 'docker') {
         $path_ssl = realpath(__DIR__ . '/../../data/ssl');
         if (!file_exists($path_ssl . '/client.crt') || !file_exists($path_ssl . '/client.key') || filesize($path_ssl . '/client.crt') == 0  || filesize($path_ssl . '/client.key') == 0) {
            self::generateClientCert();
            shell_exec(system::getCmdSudo() . ' cp ' . jeedom::getTmpFolder(__CLASS__) . '/ssl/client.* ' . $path_ssl . '/');
            shell_exec(system::getCmdSudo() . ' rm -rf ' . jeedom::getTmpFolder(__CLASS__) . '/ssl');
         }
         shell_exec(system::getCmdSudo() . ' chown -R www-data ' . $path_ssl);
      }
      $mqtt2_path = realpath(dirname(__FILE__) . '/../../resources/mqtt2d');
      chdir($mqtt2_path);
      $authentifications = explode(':', explode("\n", config::byKey('mqtt::password', __CLASS__))[0]);
      $cmd = system::getCmdSudo() . ' /usr/bin/node ' . $mqtt2_path . '/mqtt2d.js';
      $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
      $cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__);
      if (config::byKey('mode', __CLASS__) == 'local' || config::byKey('mode', __CLASS__) == 'docker') {
         $cmd .= ' --mqtt_server mqtts://127.0.0.1:8883';
         $cmd .= ' --client_key ' . $path_ssl . '/client.key';
         $cmd .= ' --client_crt ' . $path_ssl . '/client.crt';
         $cmd .= ' --ca ' . $path_ssl . '/ca.crt';
      } else {
         $cmd .= ' --mqtt_server ' . config::byKey('remote::protocol', __CLASS__) . '://' . config::byKey('remote::ip', __CLASS__) . ':' . config::byKey('remote::port', __CLASS__);
      }
      $cmd .= ' --username "' . $authentifications[0] . '"';
      $cmd .= ' --password "' . $authentifications[1] . '"';
      $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'http:127.0.0.1:port:comp') . '/plugins/mqtt2/core/php/jeeMqtt2.php';
      $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
      $cmd .= ' --cycle ' . config::byKey('cycle', __CLASS__);
      $cmd .= ' --root_topic '.config::byKey('root_topic', __CLASS__);
      $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
      log::add(__CLASS__, 'info', __('Démarrage du démon MQTT Manager', __FILE__) . ' : ' . $cmd);
      exec($cmd . ' >> ' . log::getPathToLog('mqtt2d') . ' 2>&1 &');
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
         log::add(__CLASS__, 'error', __('Impossible de démarrer le démon MQTT Manager, vérifiez les logs', __FILE__), 'unableStartDeamon');
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

   public static function removePluginTopicByPlugin($_plugin) {
      $mapping = config::byKey('mapping', __CLASS__, array());
      foreach ($mapping as $topic => $plugin) {
         if ($plugin == $_plugin) {
            unset($mapping[$topic]);
         }
      }
      config::save('mapping', $mapping, __CLASS__);
   }

   /**
    * @return array
    */
   public static function getSubscribed() {
      $mapping = config::byKey('mapping', __CLASS__, array());
      return $mapping;
   }

   public static function getFormatedInfos() {
      $infos = array();
      $authentifications = explode(':', explode("\n", config::byKey('mqtt::password', __CLASS__))[0]);
      if (config::byKey('mode', __CLASS__) == 'local' || config::byKey('mode', __CLASS__) == 'docker') {
         $infos['ip'] = '127.0.0.1';
         $infos['port'] = '1883';
         $infos['protocol'] = 'mqtt';
      } else {
         $infos['ip'] = config::byKey('remote::ip', __CLASS__);
         $infos['port'] = config::byKey('remote::port', __CLASS__);
         $infos['protocol'] = config::byKey('remote::protocol', __CLASS__);
      }
      $infos['user'] = $authentifications[0];
      $infos['password'] = $authentifications[1];
      return $infos;
   }

   public static function handleMqttMessage($_message) {
      log::add(__CLASS__, 'debug', __('Message reçu sans prise en charge par un plugin', __FILE__) . ' : ' . json_encode($_message));
      foreach ($_message as $topic => $message) {
         if ($topic == config::byKey('root_topic', __CLASS__)) {
            if (isset($message['cmd'])) {
               if (isset($message['cmd']['get'])) {
                  foreach ($message['cmd']['get'] as $cmd_id => $options) {
                     $cmd = cmd::byId($cmd_id);
                     if (!is_object($cmd) || $cmd->getType() != 'info') {
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
         if (config::byKey('autodiscovery', 'mqtt2') == 1) {
            if ($topic == 'homeassistant') {
               self::ha_discovery($topic, $message);
               continue;
            }
            if (isset($message['announce']) || isset($message['discovery'])) {
               self::announce($topic, $message);
            }
         }

         $eqlogics = self::byLogicalId($topic, __CLASS__, true);
         if (count($eqlogics) != 0) {
            self::handleMqttSubMessage($eqlogics, $message);
         }
         foreach ($message as $key => $value) {
            $eqlogics = self::byLogicalId($topic . '/' . $key, __CLASS__, true);
            if (count($eqlogics) != 0) {
               self::handleMqttSubMessage($eqlogics, $value);
            }
         }

         if (isset($message['eqLogic']) && isset($message['eqLogic']['battery'])) {
            $eqLogics = self::byLogicalId($_topic.'/cmd','mqtt2',true);
            foreach ($message['eqLogic']['battery'] as $id => $value) {
               if(is_array($eqLogics)){
                  foreach ($eqLogics as $eqLogic) {
                     if($eqLogic->getConfiguration('link::eqLogic::id') != $id){
                        continue;
                     }
                     $eqLogic->batteryStatus($value['battery'], $value['datetime']);
                  }
               }
            }
         }
      }
   }

   public static function handleMqttSubMessage($_eqlogics, $_message) {
      $values = implode_recursive($_message, '/');
      foreach ($_eqlogics as $eqlogic) {
         if ($eqlogic->getConfiguration('enableDiscoverCmd') == 1) {
            $discoverCmd = $eqlogic->getDiscover();
            if (!is_array($discoverCmd)) {
               $discoverCmd = array();
            }
            foreach ($values as $logicalId => $value) {
               $cmd = $eqlogic->getCmd('info', $logicalId);
               if (is_object($cmd)) {
                  continue;
               }
               if (!isset($discoverCmd[$logicalId])) {
                  $discoverCmd[$logicalId] = array();
               }
               $discoverCmd[$logicalId]['value'] = $value;
            }
            $eqlogic->setDiscover($discoverCmd);
         }

         foreach ($eqlogic->getCmd('info') as $cmd) {
            $paths = explode('/', $cmd->getLogicalId());
            $value = $_message;
            foreach ($paths as $path) {
               if (!isset($value[$path])) {
                  continue 2;
               }
               $value = $value[$path];
            }
            if (is_array($value) || is_object($value)) {
               $value = json_encode($cmd);
            }
            log::add(__CLASS__, 'debug', $cmd->getHumanName() . ' ' . __(' mise à jour de  la valeur avec ', __FILE__) . ' : ' . $value);
            $eqlogic->checkAndUpdateCmd($cmd, $value);
         }
      }
   }

   public static function announce($_topic, $_message) {
      log::add(__CLASS__, 'debug', 'Découverte sur : ' . $_topic);
      if (in_array($_topic,explode(',',config::byKey('jeedom::link', 'mqtt2')))) {
        self::jeedom_discovery($_topic,$_message['discovery']);
        return;
      }
      switch ($_topic) {
         case 'shellies':
            if (!isset($_message['announce'])) {
               return;
            }
            if (self::searchEqLogicWithCmd($_topic, $_message['announce']['id'])) {
               return;
            }
            $eqlogics = self::byLogicalId($_topic . '/' . $_message['announce']['id'], __CLASS__, true);
            if (count($eqlogics) > 0) {
               return;
            }
            log::add(__CLASS__, 'debug', __('Nouvel équipement Shelly découvert : ', __FILE__) . $_message['announce']['id'] . __(' type : ', __FILE__) . $_message['announce']['model']);
            $eqLogic = new self();
            $eqLogic->setLogicalId($_topic . '/' . $_message['announce']['id']);
            $eqLogic->setName($_message['announce']['id']);
            $eqLogic->setEqType_name('mqtt2');
            $eqLogic->setIsVisible(1);
            $eqLogic->setIsEnable(1);
            $eqLogic->save();
            try {
               $eqLogic->applyCmdTemplate(array(
                  'template' => 'shelly.' . $_message['announce']['model'],
                  'id' => $_message['announce']['id']
               ));
            } catch (\Throwable $th) {
            }
            break;
         case 'tasmota':
            if (!isset($_message['discovery'])) {
               return;
            }
            foreach ($_message['discovery'] as $discovery) {
               if ($discovery['config']['ft'] != '%topic%/%prefix%/') {
                  log::add(__CLASS__, 'debug', __('Nouvel équipement Tasmota découvert avec mauvaise configuration sur le topic : ', __FILE__) . $discovery['config']['ft'] . __(' au lieu de : %topic%/%prefix%/', __FILE__));
                  continue;
               }
               $eqlogics = self::byLogicalId($discovery['config']['t'], __CLASS__, true);
               if (count($eqlogics) > 0) {
                  continue;
               }
               log::add(__CLASS__, 'debug', __('Nouvel équipement Tasmota découvert : ', __FILE__) . $discovery['config']['t'] . __(' type : ', __FILE__) . $discovery['config']['md']);
               $eqLogic = new self();
               $eqLogic->setLogicalId($discovery['config']['t']);
               $eqLogic->setName($discovery['config']['hn']);
               $eqLogic->setEqType_name('mqtt2');
               $eqLogic->setIsVisible(1);
               $eqLogic->setIsEnable(1);
               $eqLogic->save();
               try {
                  log::add(__CLASS__, 'debug', 'Template : ' . 'tasmota.' . str_replace(' ', '_', $discovery['config']['md']));
                  $eqLogic->applyCmdTemplate(array(
                     'template' => 'tasmota.' . str_replace(' ', '_', $discovery['config']['md'])
                  ));
               } catch (\Throwable $th) {
               }
            }
            break;
      }
   }

   public static function searchEqLogicWithCmd($_topic, $_cmd_preffix) {
      $eqlogics = self::byLogicalId($_topic, __CLASS__, true);
      if (count($eqlogics) == 0) {
         return false;
      }
      foreach ($eqlogics as $eqLogic) {
         foreach ($eqLogic->getCmd() as $cmd) {
            if (strpos($cmd->getLogicalId(), $_cmd_preffix) !== false) {
               return true;
            }
         }
      }
      return false;
   }

   public static function ha_discovery($_topic, $_messages) {
      foreach ($_messages as $type => $devices) {
         foreach ($devices as $id => $device) {
            foreach ($device as $name => $configuration) {
               //log::add(__CLASS__, 'debug', 'HA : ' . print_r($configuration, true));
               if (!is_array($configuration) || !isset($configuration['config']['dev']['mf']) || !isset($configuration['config']['stat_t'])) {
                  continue;
               }
               if (trim($configuration['config']['dev']['mf']) != 'espressif') {
                  continue;
               }
               $root = explode('/', $configuration['config']['stat_t'])[0];
               if (trim($root) == '') {
                  continue;
               }
               $eqlogics = self::byLogicalId($root, __CLASS__, true);
               if (count($eqlogics) == 0) {
                  $eqLogic = new self();
                  $eqLogic->setLogicalId($root);
                  $eqLogic->setName($configuration['config']['dev']['name']);
                  $eqLogic->setEqType_name('mqtt2');
                  $eqLogic->setIsVisible(1);
                  $eqLogic->setIsEnable(1);
                  $eqLogic->setConfiguration('device', 'esphome');
                  $eqLogic->save();
                  $eqlogics = array($eqLogic);
               }
               switch ($type) {
                  case 'sensor':
                     $subtopic = str_replace($root . '/', '', $configuration['config']['stat_t']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name']);
                     $cmd->setType('info');
                     $cmd->setSubType('numeric');
                     if (isset($configuration['config']['unit_of_meas'])) {
                        $cmd->setUnite($configuration['config']['unit_of_meas']);
                     }
                     $cmd->setLogicalId($subtopic);
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->save();
                     break;
                  case 'binary_sensor':
                     $subtopic = str_replace($root . '/', '', $configuration['config']['stat_t']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name']);
                     $cmd->setType('info');
                     $cmd->setSubType('string');
                     if (isset($configuration['config']['unit_of_meas'])) {
                        $cmd->setUnite($configuration['config']['unit_of_meas']);
                     }
                     $cmd->setLogicalId($subtopic);
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->save();
                     break;
                  case 'text':
                     $subtopic = str_replace($root . '/', '', $configuration['config']['stat_t']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name']);
                     $cmd->setType('info');
                     $cmd->setSubType('string');
                     if (isset($configuration['config']['unit_of_meas'])) {
                        $cmd->setUnite($configuration['config']['unit_of_meas']);
                     }
                     $cmd->setLogicalId($subtopic);
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->save();
                     break;
                  case 'button':
                     $subtopic = str_replace($root . '/', '', $configuration['config']['cmd_t']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name']);
                     $cmd->setType('action');
                     $cmd->setSubType('other');
                     $cmd->setLogicalId($subtopic);
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->save();
                     break;
                  case 'switch':
                     $subtopic = str_replace($root . '/', '', $configuration['config']['stat_t']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name'] . ' état');
                     $cmd->setType('info');
                     $cmd->setSubType('binary');
                     $cmd->setLogicalId($subtopic);
                     $cmd->setIsVisible(0);
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->save();
                     $info_id = $cmd->getId();

                     $subtopic = str_replace($root . '/', '', $configuration['config']['cmd_t']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name'] . ' on');
                     $cmd->setType('action');
                     $cmd->setSubType('other');
                     $cmd->setLogicalId($subtopic);
                     $cmd->setConfiguration('message', 'on');
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->setValue($info_id);
                     $cmd->save();
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name'] . ' off');
                     $cmd->setType('action');
                     $cmd->setSubType('other');
                     $cmd->setLogicalId($subtopic);
                     $cmd->setConfiguration('message', 'off');
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->setValue($info_id);
                     $cmd->save();
                     break;
                  case 'number':
                     $subtopic = str_replace($root . '/', '', $configuration['config']['stat_t']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name'] . ' état');
                     $cmd->setType('info');
                     $cmd->setSubType('numeric');
                     $cmd->setLogicalId($subtopic);
                     $cmd->setIsVisible(0);
                     if (isset($configuration['config']['min'])) {
                        $cmd->setConfiguration('minValue', $configuration['config']['min']);
                     }
                     if (isset($configuration['config']['max'])) {
                        $cmd->setConfiguration('maxValue', $configuration['config']['max']);
                     }
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->save();
                     $info_id = $cmd->getId();

                     $subtopic = str_replace($root . '/', '', $configuration['config']['cmd_t']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($configuration['config']['name']);
                     $cmd->setType('action');
                     $cmd->setSubType('slider');
                     $cmd->setLogicalId($subtopic);
                     $cmd->setConfiguration('message', '#slider#');
                     if (isset($configuration['config']['min'])) {
                        $cmd->setConfiguration('minValue', $configuration['config']['min']);
                     }
                     if (isset($configuration['config']['max'])) {
                        $cmd->setConfiguration('maxValue', $configuration['config']['max']);
                     }
                     $cmd->setValue($info_id);
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->save();
                     break;
                  case 'device_automation':
                     $subtopic = str_replace($root . '/', '', $configuration['config']['topic']);
                     if (self::searchEqLogicWithCmd($root, $subtopic)) {
                        continue 2;
                     }
                     $name = (isset($configuration['config']['subtype'])) ? $configuration['config']['subtype'] : $subtopic;
                     $cmd = new mqtt2Cmd();
                     $cmd->setName($name);
                     $cmd->setType('info');
                     $cmd->setSubType('string');
                     $cmd->setLogicalId($subtopic);
                     $cmd->setEqLogic_id($eqlogics[0]->getId());
                     $cmd->save();
                     break;
               }
            }
         }
      }
   }

   public static function publish($_topic, $_message = '', $_options = array()) {
      if (!isset($_options['qos'])) {
         $_options['qos'] = intval(config::byKey('qos::default', 'mqtt2', 0));
      }
      $request_http = new com_http('http://127.0.0.1:' . config::byKey('socketport', __CLASS__) . '/publish?apikey=' . jeedom::getApiKey(__CLASS__));
      $request_http->setHeader(array(
         'Content-Type: application/json'
      ));
      if (is_array($_message) || is_object($_message)) {
         $_message = json_encode($_message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }
      $request_http->setPost(json_encode(array('topic' => $_topic, 'message' => $_message, 'options' => $_options)));
      try{
         $result = json_decode($request_http->exec(60,3), true);
      }catch(Exception $e) {
         sleep(10);
         $result = json_decode($request_http->exec(60,3), true);
      }
      if ($result['state'] != 'ok') {
         throw new Exception(json_encode($result));
      }
      $topics = explode('/', $_topic);
      if ($topics[0] == config::byKey('root_topic', 'mqtt2')) {
         $plugin = mqtt2::getPluginForTopic($topics[0]);
         if (class_exists($plugin) && method_exists($plugin, 'handleMqttMessage')) {
            $data = array();
            $message = &$data;
            foreach ($topics as $topic) {
               $message[$topic] = array();
               $message = &$message[$topic];
            }
            $message = json_decode($_message, true);
            $plugin::handleMqttMessage($data);
         }
      }
   }

   public static function handleEvent($_option) {
      $cmd = cmd::byId($_option['event_id']);
      if (config::byKey('sendEvent', 'mqtt2', 0) == 0 && $cmd->getEqLogic()->getConfiguration('plugin::mqtt2::mqttTranmit', 0) == 0) {
         return;
      }
      if (trim(config::byKey('publish_template', 'mqtt2', '')) != '') {
         $replace = array('#value#' => $_option['value']);
         if (is_object($cmd)) {
            $replace['#id#'] = $cmd->getId();
            $replace['#humanName#'] = $cmd->getHumanName();
            $replace['#unit#'] = $cmd->getUnite();
            $replace['#name#'] = $cmd->getName();
            $replace['#type#'] = $cmd->getType();
            $replace['#subtype#'] = $cmd->getSubType();
         }
         $message = str_replace(array_keys($replace), $replace, config::byKey('publish_template', 'mqtt2', ''));
      } else {
         $message = array('value' => $_option['value']);
         if (is_object($cmd)) {
            $message['humanName'] = $cmd->getHumanName();
            $message['unite'] = $cmd->getUnite();
            $message['name'] = $cmd->getName();
            $message['type'] = $cmd->getType();
            $message['subtype'] = $cmd->getSubType();
         }
      }
      try {
         self::publish(config::byKey('root_topic', __CLASS__) . '/cmd/event/' . $_option['event_id'], $message);
      } catch (\Throwable $th) {
         
      }
   }

   public static function sendBattery() {
      foreach (eqLogic::all() as $eqLogic) {
         if (config::byKey('sendEvent', 'mqtt2', 0) == 0 && $eqLogic->getConfiguration('plugin::mqtt2::mqttTranmit', 0) == 0) {
            continue;
         }
         if ($eqLogic->getStatus('battery', -2) == -2) {
            continue;
         }
         self::publish(config::byKey('root_topic', __CLASS__) . '/eqLogic/battery/' . $eqLogic->getId(), array(
            'battery' => $eqLogic->getStatus('battery', -2),
            'datetime' => $eqLogic->getStatus('batteryDatetime', date('Y-m-d H:i:s')),
            'id' => $eqLogic->getId()
         ));
      }
	}

   public static function sendDiscovery(){
      if (in_array(config::byKey('root_topic', __CLASS__) ,explode(',',config::byKey('jeedom::link', 'mqtt2')))) {
         throw new Exception(__('Le "Topic racine Jeedom" ne peut etre dans "Topic des Jeedom liée"',__FILE__));
      }
      $eqLogics = eqLogic::all();
      foreach ($eqLogics as $eqLogic) {
         if($eqLogic->getEqType_name() == 'mqtt2'){
            continue;
         }
         if (config::byKey('sendEvent', 'mqtt2', 0) == 0 && $eqLogic->getConfiguration('plugin::mqtt2::mqttTranmit', 0) == 0) {
           continue;
         }
         log::add('mqtt2', 'debug', '[Discovery] Send : '.$eqLogic->getName().' - '.$eqLogic->getId());
         $toSend = utils::o2a($eqLogic);
         $toSend['configuration']['real_eqType'] = $eqLogic->getEqType_name();
         $toSend['object_name'] = '';
         $object = $eqLogic->getObject();
         if (is_object($object)) {
            $toSend['object_name'] = $object->getName();
         }
         unset($toSend['html']);
         unset($toSend['cache']);
         
         $toSend['cmds'] = array();
         foreach ($eqLogic->getCmd() as $cmd) {
            $toSend['cmds'][$cmd->getId()] = utils::o2a($cmd);
            $toSend['cmds'][$cmd->getId()]['configuration']['real_eqType'] = $cmd->getEqType_name();
				$toSend['cmds'][$cmd->getId()]['configuration']['real_logicalId'] = $cmd->getLogicalId();
            if(isset($toSend['cmds'][$cmd->getId()]['configuration']['actionCheckCmd'])){
               unset($toSend['cmds'][$cmd->getId()]['configuration']['actionCheckCmd']);
            }
            if(isset($toSend['cmds'][$cmd->getId()]['configuration']['jeedomPostExecCmd'])){
               unset($toSend['cmds'][$cmd->getId()]['configuration']['jeedomPostExecCmd']);
            }
            if(isset($toSend['cmds'][$cmd->getId()]['configuration']['jeedomPreExecCmd'])){
               unset($toSend['cmds'][$cmd->getId()]['configuration']['jeedomPreExecCmd']);
            }
         }
         self::publish(config::byKey('root_topic', __CLASS__) . '/discovery/eqLogic/'.$eqLogic->getId(), $toSend);
      }
      foreach ($eqLogics as $eqLogic) {
         if($eqLogic->getEqType_name() == 'mqtt2'){
            continue;
         }
         if (config::byKey('sendEvent', 'mqtt2', 0) == 0 && $eqLogic->getConfiguration('plugin::mqtt2::mqttTranmit', 0) == 0) {
           continue;
         }
         foreach ($eqLogic->getCmd('info') as $cmd) {
            self::handleEvent(array(
               'event_id' => $cmd->getId(),
               'value' => $cmd->execCmd()
            ));
         }
      }
   }

   public static function jeedom_discovery($_topic,$_discovery) {
      if(!isset($_discovery['eqLogic']) || !is_array($_discovery['eqLogic']) || count($_discovery['eqLogic']) == 0){
         return;
      }
      $eqLogics = self::byLogicalId($_topic.'/cmd','mqtt2',true);
      foreach ($_discovery['eqLogic'] as $id => &$_eqLogic) {
         log::add('mqtt2', 'debug', '[Discovery] Received eqLogic : '.json_encode($_eqLogic));
         $eqLogic = null;
         if(is_array($eqLogics)){
           foreach ($eqLogics as $__eqLogic) {
              if($__eqLogic->getConfiguration('link::eqLogic::id') != $id){
                 continue;
              }
              $eqLogic = $__eqLogic;
           }
         }
         if(!is_object($eqLogic)){
            log::add('mqtt2', 'debug', '[Discovery] EqLogic not exist create it');
            $eqLogic = new self();
            utils::a2o($eqLogic, $_eqLogic);
            $eqLogic->setId('');
			   $eqLogic->setObject_id('');
            if (isset($_eqLogic['object_name']) && $_eqLogic['object_name'] != '') {
               $object = jeeObject::byName($_eqLogic['object_name']);
               if (is_object($object)) {
                  $eqLogic->setObject_id($object->getId());
               }
            }
            $eqLogic->setConfiguration('plugin::mqtt2::mqttTranmit', 0);
         }
         $eqLogic->setConfiguration('real_eqType_name',$eqLogic->getEqType_name());
         $eqLogic->setEqType_name('mqtt2');
         $eqLogic->setConfiguration('link::eqLogic::id', $_eqLogic['id']);
         $eqLogic->setConfiguration('manufacturer','jeedom');
         $eqLogic->setConfiguration('device','mqtt');
         
         $eqLogic->setLogicalId($_topic.'/cmd');
         try {
				$eqLogic->save();
			} catch (Exception $e) {
				$eqLogic->setName($eqLogic->getName() . ' remote ' . rand(0, 9999));
				$eqLogic->save();
			}
			log::add('mqtt2', 'debug', 'EqLogic save, create cmd');
         foreach ($_eqLogic['cmds'] as &$_cmd) {
				if (isset($_cmd['configuration']) && isset($_cmd['configuration']['calculValueOffset'])) {
					unset($_cmd['configuration']['calculValueOffset']);
				}
            if($_cmd['type'] == 'action'){
               $cmd = $eqLogic->getCmd(null, 'set/' . $_cmd['id']);
            }else{
               $cmd = $eqLogic->getCmd(null, 'event/' . $_cmd['id'].'/value');
            }
				if (!is_object($cmd)) {
					$cmd = new mqtt2Cmd();
					utils::a2o($cmd, $_cmd);
					$cmd->setId('');
					$cmd->setValue('');
				}
				$cmd->setEqType('mqtt2');
				$cmd->setEqLogic_id($eqLogic->getId());
				$cmd->setConfiguration('isRefreshCmd', ($_cmd['logicalId'] == 'refresh'));
            if($_cmd['type'] == 'action'){
               $cmd->setLogicalId('set/' . $_cmd['id']);
            }else{
               $cmd->setLogicalId('event/' . $_cmd['id'].'/value');
            }
            if($_cmd['type'] == 'action'){
               switch ($_cmd['subType']) {
                  case 'slider':
                     $cmd->setConfiguration('message','json::{"slider":"#slider#"}');
                     break;
                  case 'message':
                     $cmd->setConfiguration('message','json::{"title":"#title#","message":"#message#"}');
                     break;
                  case 'select':
                     $cmd->setConfiguration('message','json::{"select":"#select#"}');
                     break;
               }
            }
				try {
					$cmd->save();
				} catch (Exception $e) {
					$cmd->setName($cmd->getName() . ' remote ' . rand(0, 9999));
					$cmd->save();
				}
				$map_id[$_cmd['id']] = $cmd->getId();
			}

         if($eqLogic->getConfiguration('real_eqType_name')  == 'virtual' && $_eqLogic['logicalId'] == 'jeedom::monitor'){
            $cmd = $eqLogic->getCmd('info', 'state');
            if (!is_object($cmd)) {
					$cmd = new mqtt2Cmd();
               $cmd->setName('Etat');
               $cmd->setType('info');
               $cmd->setSubType('string');
				}
            $cmd->setEqType('mqtt2');
				$cmd->setEqLogic_id($eqLogic->getId());
            try {
					$cmd->save();
				} catch (Exception $e) {
					$cmd->setName($cmd->getName() . ' remote ' . rand(0, 9999));
            }
         }

			foreach ($_eqLogic['cmds'] as $_cmd) {
				if (!isset($_cmd['value']) || !isset($map_id[$_cmd['value']]) || !isset($map_id[$_cmd['id']])) {
					continue;
				}
				$cmd = cmd::byId($map_id[$_cmd['id']]);
				if (!is_object($cmd)) {
					continue;
				}
				$cmd->setValue($map_id[$_cmd['value']]);
				$cmd->save();
			}
         
      }
   }

   public static function listCmdTemplate($_template = '') {
      $return = array();
      $path = __DIR__ . '/../config/devices';
      foreach (ls($path, '*', false, array('folders', 'quiet')) as $folder) {
         foreach (ls($path . '/' . $folder, '*.json', false, array('files', 'quiet')) as $file) {
            if ($_template != '') {
               if ($file == $_template . '.json') {
                  return is_json(file_get_contents($path . '/' . $folder . '/' . $file), array(), true);
               }
               continue;
            }
            $return[str_replace('.json', '', $file)] = is_json(file_get_contents($path . '/' . $folder . '/' . $file), array());
         }
      }
      return $return;
   }

   public function applyCmdTemplate($_config) {
      if (!is_array($_config)) {
         throw new Exception(__('La configuration d\'un template doit être un tableau', __FILE__));
      }
      if (!isset($_config['template'])) {
         throw new Exception(__('Aucun nom de template trouvé', __FILE__));
      }
      $template = self::listCmdTemplate($_config['template']);
      if (!is_array($template) || count($template) < 1) {
         throw new Exception(__('Template introuvable', __FILE__));
      }
      if (!isset($template['commands']) || count($template['commands']) < 1) {
         return;
      }
      $this->setConfiguration('device', $_config['template']);
      $config = array();
      foreach ($_config as $key => $value) {
         $config['#' . $key . '#'] = $value;
      }
      $cmds_template = json_decode(str_replace(($config), $config, json_encode($template['commands'])), true);
      foreach ($cmds_template as $cmd_template) {
         $cmd = new mqtt2Cmd();
         $cmd->setEqLogic_id($this->getId());
         utils::a2o($cmd, $cmd_template);
         try {
            $cmd->save(true);
            if (isset($cmd_template['value'])) {
               $link_cmds[$cmd->getId()] = $cmd_template['value'];
            }
         } catch (\Throwable $th) {
         }
      }
      if (count($link_cmds) > 0) {
         foreach (($this->getCmd()) as $eqLogic_cmd) {
            foreach ($link_cmds as $cmd_id => $link_cmd) {
               if ($link_cmd == $eqLogic_cmd->getName()) {
                  $cmd = cmd::byId($cmd_id);
                  if (is_object($cmd)) {
                     $cmd->setValue($eqLogic_cmd->getId());
                     $cmd->save(true);
                  }
               }
            }
         }
      }
      $this->save(true);
      return;
   }

   public static function ciGlob($pat) {
      $p = '';
      for ($x = 0; $x < strlen($pat); $x++) {
         $c = substr($pat, $x, 1);
         if (preg_match("/[^A-Za-z]/", $c)) {
            $p .= $c;
            continue;
         }
         $a = strtolower($c);
         $b = strtoupper($c);
         $p .= "[{$a}{$b}]";
      }
      return $p;
   }

   public static function getImgFilePath($_device, $_manufacturer = null) {
      if ($_manufacturer != null) {
         if (file_exists(__DIR__ . '/../config/devices/' . $_manufacturer . '/' . $_device . '.png')) {
            return $_manufacturer . '/' . $_device . '.png';
         }
         if (file_exists(__DIR__ . '/../config/devices/' . mb_strtolower($_manufacturer) . '/' . $_device . '.png')) {
            return mb_strtolower($_manufacturer) . '/' . $_device . '.png';
         }
      }
      if (file_exists(__DIR__ . '/../config/devices/' . $_device . '.png')) {
         return  $_device . '.png';
      }
      $device = self::ciGlob($_device);
      foreach (ls(__DIR__ . '/../config/devices', '*', false, array('folders', 'quiet')) as $folder) {
         foreach (ls(__DIR__ . '/../config/devices/' . $folder, $device . '.{jpg,png}', false, array('files', 'quiet')) as $file) {
            return $folder . $file;
         }
      }
      foreach (ls(__DIR__ . '/../config/devices', '*', false, array('folders', 'quiet')) as $folder) {
         foreach (ls(__DIR__ . '/../config/devices/' . $folder, '*.{jpg,png}', false, array('files', 'quiet')) as $file) {
            if (strtolower($_device) . '.png' == strtolower($file)) {
               return $file;
            }
            if (strtolower($_device) . '.jpg' == strtolower($file)) {
               return $file;
            }
         }
      }
      return '.png';
   }

   /*     * *********************Méthodes d'instance************************* */

   public function getImage() {
      if(method_exists($this,'getCustomImage')){
         $customImage = $this->getCustomImage();
         if($customImage !== null){
            return $customImage;
         }
      }
      if($this->getConfiguration('real_eqType_name') != ''){
         $file = 'plugins/'.$this->getConfiguration('real_eqType_name').'/plugin_info/' . $this->getConfiguration('real_eqType_name').'_icon.png';
         if (file_exists(__DIR__ . '/../../../../' . $file)) {
            return $file;
         }
         return 'plugins/mqtt2/core/config/devices/jeedom/mqtt2.png';
      }
      $file = 'plugins/mqtt2/core/config/devices/' . self::getImgFilePath($this->getConfiguration('device'));
      if (!file_exists(__DIR__ . '/../../../../' . $file)) {
         return 'plugins/mqtt2/plugin_info/mqtt2_icon.png';
      }
      return $file;
   }

   public function setDiscover($_values) {
      if (is_array($_values)) {
         $_values = json_encode($_values);
      }
      $folder = __DIR__ . '/../../data/discover';
      if (!file_exists($folder)) {
         mkdir($folder);
      }
      if (file_exists($folder . '/' . $this->getId() . '.json')) {
         unlink($folder . '/' . $this->getId() . '.json');
      }
      file_put_contents($folder . '/' . $this->getId() . '.json', $_values);
   }

   public function getDiscover() {
      $folder = __DIR__ . '/../../data/discover/';
      if (!file_exists($folder)) {
         mkdir($folder);
      }
      if (!file_exists($folder . '/' . $this->getId() . '.json')) {
         return array();
      }
      $content = file_get_contents($folder . '/' . $this->getId() . '.json');
      return is_json($content, array());
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
         case 'message':
            $value = str_replace('#message#', $_options['message'], $value);
            $value = str_replace('#title#', $_options['title'], $value);
            break;
      }
      $value = jeedom::evaluateExpression($value);
      if (is_string($value)) {
         $prefix = 'json::';
         if (substr($value, 0, strlen($prefix)) == $prefix) {
            $value = substr($value, strlen($prefix));
         }
      }

      $options = array();
      if ($this->getConfiguration('retain') == 1) {
         $options['retain'] = 1;
      }
      mqtt2::publish($eqLogic->getLogicalid() . '/' . $this->getLogicalId(), $value, $options);
   }
}
