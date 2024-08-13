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
  $listener = listener::byClassAndFunction('mqtt2', 'handleEvent');
  if (!is_object($listener)) {
    $listener = new listener();
  }
  $listener->setClass('mqtt2');
  $listener->setFunction('handleEvent');
  $listener->emptyEvent();
  $listener->addEvent('*');
  $listener->setOption(array('background' => false));
  $listener->save();
}

function mqtt2_update() {
  $listeners = listener::searchClassFunctionOption('mqtt2', 'handleEvent');
  if(count($listeners) > 1){
    foreach($listeners as $listener){
        $listener->remove();
      }
  }
  $listener = listener::byClassAndFunction('mqtt2', 'handleEvent');
  if (!is_object($listener)) {
    $listener = new listener();
  }
  $listener->setClass('mqtt2');
  $listener->setFunction('handleEvent');
  $listener->emptyEvent();
  $listener->addEvent('*');
  $listener->setOption(array('background' => false));
  $listener->save();
}

function mqtt2_remove() {
  $listener = listener::byClassAndFunction('mqtt2', 'handleEvent');
  if (is_object($listener)) {
    $listener->remove();
  }
}
