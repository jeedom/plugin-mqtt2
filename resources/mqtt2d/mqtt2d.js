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
var Jeedom = require('./jeedom/jeedom.js')
const fs = require('fs')
var LAST_SEND_TOPIC = {}

const args = Jeedom.getArgs()
if (typeof args.loglevel == 'undefined') {
  args.loglevel = 'debug'
}
Jeedom.log.setLevel(args.loglevel)

Jeedom.log.info('Start mqtt2d')
Jeedom.log.info('Log level on  : ' + args.loglevel)
Jeedom.log.info('Socket port : ' + args.socketport)
Jeedom.log.info('MQTT : ' + args.mqtt_server)
Jeedom.log.info('Username : ' + args.username)
Jeedom.log.info('Password : ' + args.password)
Jeedom.log.info('Root topic : ' + args.root_topic)
Jeedom.log.info('PID file : ' + args.pid)
Jeedom.log.info('Apikey : ' + args.apikey)
Jeedom.log.info('Callback : ' + args.callback)
Jeedom.log.info('Cycle : ' + args.cycle)

Jeedom.log.info('Client key : ' + args.client_key)
Jeedom.log.info('Client crt : ' + args.client_crt)
Jeedom.log.info('CA : ' + args.ca)

Jeedom.write_pid(args.pid)
Jeedom.com.config(args.apikey, args.callback, args.cycle)
Jeedom.com.test()

var mqtt = require('mqtt')
if (args.ca) {
  var client = mqtt.connect(args.mqtt_server, {
    clientId: "mqtt-jeedom_"+Math.random().toString(16).substring(0, 8),
    rejectUnauthorized: false,
    key: fs.readFileSync(args.client_key),
    cert: fs.readFileSync(args.client_crt),
    username: args.username,
    password: args.password,
    will:{
      topic : args.root_topic+'/state',
      payload: 'offline',
      retain : true,
      properties : {
        willDelayInterval : 30
      }
    }
  })
} else {
  var client = mqtt.connect(args.mqtt_server, {
    clientId: "mqtt-jeedom_"+Math.random().toString(16).substring(0, 8),
    rejectUnauthorized: false,
    username: args.username,
    password: args.password,
    will:{
      topic : args.root_topic+'/state',
      payload: 'offline',
      retain : true,
      properties : {
        willDelayInterval : 30
      }
    }
  })
}


Jeedom.log.info('Connect to mqtt server')

client.on('error', function(error) {
  Jeedom.log.error('Error on connection to mqtt server : ' + error)
  process.exit()
})

client.on('reconnect', function() {
  Jeedom.log.error('Reconnection to mqtt server')
})

client.on('connect', function() {
  Jeedom.log.info('Connection to mqtt server successfull')
  Jeedom.log.info('Subscription to all topics')
  client.publish(args.root_topic+'/state','online',{retain : true})
  client.subscribe('#', function(err) {
    if (err) {
      Jeedom.log.error('Error on Subscription : ' + err)
      process.exit()
    }
    Jeedom.log.info('Subscription to all topics succesfull')
  })
})

client.on('message', function(topic, message) {
  if (LAST_SEND_TOPIC[topic]) {
    let time = LAST_SEND_TOPIC[topic]
    delete LAST_SEND_TOPIC[topic]
    if ((time + 5000) > (new Date().getTime())) {
      return
    }
  }
  Jeedom.log.debug('Received message on topic : ' + topic + ' => ' + message.toString())
  if (isValidJSONString(message.toString())) {
    Jeedom.com.add_changes(topic.replace(/\//g, '::'), JSON.parse(message.toString()))
  } else {
    Jeedom.com.add_changes(topic.replace(/\//g, '::'), message.toString())
  }
})

function isValidJSONString(str) {
  try {
    JSON.parse(str)
  } catch (e) {
    return false
  }
  return true
}

Jeedom.http.config(args.socketport, args.apikey)

Jeedom.http.app.post('/publish', function(req, res) {
  try {
    if (!Jeedom.http.checkApikey(req)) {
      res.setHeader('Content-Type', 'application/json')
      res.send({ state: "nok", result: 'Invalid apikey' })
      return
    }
    let options = {};
    if(req.body.options){
      if(req.body.options.retain){
        options.retain = req.body.options.retain
      }
      if(req.body.options.qos){
        options.qos = req.body.options.qos
      }
      if(req.body.options.dup){
        options.dup = req.body.options.dup
      }
    }
    Jeedom.log.debug('Publish message on topic : ' + req.body.topic + ' => ' + String(req.body.message)+' with options : '+JSON.stringify(options))
    client.publish(req.body.topic, String(req.body.message),options, function(err) {
      if (err) {
        Jeedom.log.debug('Error on message publish : ' + err)
        res.setHeader('Content-Type', 'application/json')
        res.send({ state: "nok", result: JSON.stringify(err) })
        return
      }
      LAST_SEND_TOPIC[req.body.topic] = (new Date().getTime())
      res.setHeader('Content-Type', 'application/json')
      res.send({ state: "ok" })
      return
    })
  } catch (error) {
    Jeedom.log.debug('Error on message publish : ' + error)
    res.setHeader('Content-Type', 'application/json')
    res.send({ state: "nok", result: JSON.stringify(error) })
  }
})
