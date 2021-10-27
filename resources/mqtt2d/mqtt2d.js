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
var Jeedom = require('./jeedom/jeedom.js');
const fs = require('fs');

const args = Jeedom.getArgs()
if(typeof args.loglevel == 'undefined'){
  args.loglevel = 'debug';
}
Jeedom.log.setLevel(args.loglevel)

Jeedom.log.info('Start dysond')
Jeedom.log.info('Log level on  : '+args.loglevel)
Jeedom.log.info('Socket port : '+args.socketport)
Jeedom.log.info('CA file : '+args.mqtt_ca)
Jeedom.log.info('MQTT : '+args.mqtt_server)
Jeedom.log.info('Username : '+args.username)
Jeedom.log.info('Password : '+args.password)
Jeedom.log.info('PID file : '+args.pid)
Jeedom.log.info('Apikey : '+args.apikey)
Jeedom.log.info('Callback : '+args.callback)
Jeedom.log.info('Cycle : '+args.cycle)

Jeedom.write_pid(args.pid)
Jeedom.com.config(args.apikey,args.callback,args.cycle)
Jeedom.com.test();

var mqtt = require('mqtt')
var client  = mqtt.connect(args.mqtt_server,{
  clientId:"mqtt-jeedom",
  rejectUnauthorized: false,
  username: args.username,
  password: args.password
})

Jeedom.log.info('Connect to mqtt server')

client.on('error', function (error) {
  Jeedom.log.error('Error on connection to mqtt server : '+error)
});

client.on('connect', function () {
  Jeedom.log.info('Connection to mqtt server successfull')
})


Jeedom.http.config(args.socketport,args.apikey)