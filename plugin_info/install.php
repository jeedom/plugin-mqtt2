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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function mqtt2_install() {
  if (shell_exec(system::getCmdSudo() . ' which mosquitto | wc -l') != 0) {
    config::save('mode', "none", 'mqtt2');
  }
}

function mqtt2_update() {
  shell_exec(system::getCmdSudo().' rm -rf '.__DIR__.'/../resources/mqtt2d/node_modules');
  if (config::byKey('mode', 'mqtt2', '') == 'remote') {
    if (strpos(config::byKey('remote::ip', 'mqtt2', ''), ':') !== false) {
      $remoteAddr = explode(":", config::byKey('remote::ip', 'mqtt2'));
      if (count($remoteAddr) == 3) {
        config::save('remote::protocol', $remoteAddr[0], 'mqtt2');
        config::save('remote::ip', str_replace('//', '', $remoteAddr[1]), 'mqtt2');
        config::save('remote::port', $remoteAddr[2], 'mqtt2');
      }
    }
  }
}

function mqtt2_remove() {
  $listener = listener::byClassAndFunction('mqtt2', 'handleEvent');
  if (is_object($listener)) {
    $listener->remove();
  }
}
