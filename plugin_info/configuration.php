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
* along with Jeedom. If not, see <http://www.gnu.org/licenses>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="col-lg-6">
      <div class="form-group">
        <label class="col-md-4 control-label">{{Mode}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionner le mode d'installation du broker (voir documentation)}}"></i></sup>
        </label>
        <div class="col-md-7">
          <select class="configKey form-control" data-l1key="mode">
            <option value="none" disabled>{{A configurer}}</option>
            <option value="local">{{Broker local (par défaut)}}</option>
            <option value="docker">{{Broker local docker}}</option>
            <option value="remote">{{Broker distant}}</option>
          </select>
        </div>
      </div>

      <div class="form-group mqtt2Mode local docker">
        <label class="col-md-4 control-label">{{Broker Mosquitto}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Installer, désinstaller ou télécharger le certificat client du broker Mosquitto}}"></i></sup>
        </label>
        <div class="col-md-8">
          <a class="btn btn-xs btn-warning" id="bt_mqtt2RestartMosquitto"><i class="fas fa-play"></i> {{(Re)Démarrer}}</a>
          <a class="btn btn-xs btn-danger" id="bt_mqtt2StopMosquitto"><i class="fas fa-stop"></i> {{Arrêter}}</a>
          <a class="btn btn-xs btn-warning" id="bt_mqtt2InstallMosquitto"><i class="fas fa-plus-square"></i> {{(Ré)Installer}}</a>
          <a class="btn btn-xs btn-danger" id="bt_mqtt2UninstallMosquitto"><i class="fas fa-minus-square"></i> {{Désinstaller}}</a>
          <a class="btn btn-xs btn-primary" id="bt_mqtt2LogMosquitto"><i class="far fa-file"></i> {{Log}}</a>
          <a class="btn btn-xs btn-primary" target="_blank" href="plugins/mqtt2/core/php/downloadClientSsl.php"><i class="fas fa-key"></i> {{Télécharger le certificat client}}</a>
        </div>
      </div>

      <div class="form-group mqtt2Mode local docker">
        <label class="col-md-4 control-label">{{Etat Broker Mosquitto}}</label>
        <div class="col-md-7">
          <?php
          if (config::byKey('mode', 'mqtt2') == 'local') {
            $state = shell_exec(system::getCmdSudo() . ' ps ax | grep mosquitto | grep mqtt2 | grep -v grep | wc -l');
            if ($state == 0) {
              echo '<span class="label label-danger">{{NOK}}</span>';
            } else {
              echo '<span class="label label-success">{{OK}}</span>';
            }
          } else {
            echo '<span class="label label-info">{{N/A}}</span>';
          }
          ?>
        </div>
      </div>

      <div class="form-group mqtt2Mode remote">
        <label class="col-md-4 control-label">{{Adresse du broker}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Renseigner l'adresse du broker}}"></i></sup>
        </label>
        <div class="col-md-7 input-group">
          <span class="input-group-btn">
            <select class="form-control configKey roundedLeft" data-l1key="remote::protocol" style="width:80px;">
              <option value="mqtt">mqtt</option>
              <option value="mqtts">mqtts</option>
            </select>
          </span>
          <span class="input-group-addon">://</span>
          <input class="configKey form-control" data-l1key="remote::ip" placeholder="{{IP}}">
          <span class="input-group-addon">:</span>
          <input class="configKey form-control roundedRight" data-l1key="remote::port" placeholder="{{Port}}">
        </div>
      </div>

      <div class="form-group">
        <label class="col-md-4 control-label">{{Authentification}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Identifiant et mot de passe de connexion au broker MQTT séparés par deux-points}} (:)"></i></sup>
        </label>
        <div class="col-md-7">
          <textarea class="configKey form-control autogrow" data-l1key="mqtt::password"></textarea>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Port socket interne}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Modification dangereuse}}"></i></sup>
        </label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="socketport" placeholder="55035">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Cycle}} <sub>({{secondes}})</sub>
          <sup><i class="fas fa-question-circle tooltips" title="{{Fréquence de rafraichissement en secondes}}"></i></sup>
        </label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="cycle">
        </div>
      </div>

      <br>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Topic racine Jeedom}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Topic racine pour piloter Jeedom et sur lequel il envoie ses évènements}}"></i></sup>
        </label>
        <div class="col-md-7 form-inline">
          <input class="configKey form-control" data-l1key="root_topic">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Transmission des équipements}}</label>
        <div class="col-md-7 form-inline">
          <a class="btn btn-success" id="bt_mqtt2SendDiscoveryConfiguration"><i class="far fa-paper-plane"></i> {{Envoyer la découverte}}</a>
          <a class="btn btn-primary" id="bt_mqtt2DisplayTransmitDevice"><i class="fas fa-cog"></i> {{Gérer les équipements transmis}}</a>
          <label class="checkbox-inline"><input type="checkbox" class="configKey" data-l1key="sendEvent">{{Transmettre tous les équipements}}
            <sup><i class="fas fa-question-circle tooltips" title="{{Cocher la case pour transmettre tous les événements au broker MQTT. Vous pouvez également le faire par équipement (pour ne pas tout transmettre) dans la configuration avancée de l’équipement que vous voulez transmettre ou par le bouton Gérer les équipements transmis}}"></i></sup>
          </label>
          
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Template de publication}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Template de publication des événements Jeedom}} (tags : #value#, #humanName#, #unit#, #name#, #type#, #subtype#)"></i></sup>
        </label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="publish_template">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">QoS
          <sup><i class="fas fa-question-circle tooltips" title="QoS 0: {{Le message est envoyé une seule fois}}<br>QoS 1: {{Le message est envoyé au moins une fois}}<br>QoS 2: {{Le message est distribué une seule fois}}"></i></sup>
        </label>
        <div class="col-md-7">
          <select class="configKey form-control" data-l1key="qos::default">
            <option value="0">QoS 0 ({{par défaut}})</option>
            <option value="1">QoS 1</option>
            <option value="2">QoS 2</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Topic des Jeedoms liés}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Topic des autres jeedom qu'il faut écouter (séparé par des ,)}}"></i></sup>
        </label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="jeedom::link">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Plugins abonnés}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Liste des plugins Jeedom abonnés au plugin}} MQTT Manager [topic (plugin_id)]"></i></sup>
        </label>
        <div class="subscribed col-md-7">
          <?php foreach (mqtt2::getSubscribed() as $subscribed => $plugin) { ?>
            <span class="label label-success"><?= $plugin ?> (<?= $subscribed ?>) <i class="fas fa-times cursor bt_removePluginTopic" data-topic="<?= $subscribed ?>"></i></span>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="form-group mqtt2Mode docker local">
        <label class="col-md-4 control-label">{{Paramètres Mosquitto}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Sauvegarder et relancer l'installation de Mosquitto pour prendre en compte les modifications de ce champ de configuration}}"></i></sup>
        </label>
        <div class="col-md-7">
          <textarea class="configKey form-control autogrow" data-l1key="mosquitto::parameters"></textarea>
        </div>
      </div>
      <div class="form-group mqtt2Mode docker">
        <label class="col-md-4 control-label">{{Port(s)}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Sauvegarder et relancer l'installation de Mosquitto pour prendre en compte les modifications de ce champ de configuration}}"></i></sup>
        </label>
        <div class="col-md-7">
          <textarea class="configKey form-control autogrow" data-l1key="mosquitto::ports"></textarea>
        </div>
      </div>
    </div>
  </fieldset>
</form>
<script>
            
  $('#bt_mqtt2DisplayTransmitDevice').off('click').on('click', function() {
    jeeDialog.dialog({
      id: 'jee_MqttModal',
      title: '{{Equipement MQTT transmis}}',
      width: '85vw',
      height: '51vw',
      top: '8vh',
      contentUrl: 'index.php?v=d&plugin=mqtt2&modal=eqLogic.transmit'
    }) 
  });

  $('#bt_mqtt2LogMosquitto').off('click').on('click', function() {
    jeeDialog.dialog({
      id: 'jee_MqttModal',
      title: '{{Log mosquitto}}',
      width: '85vw',
      height: '51vw',
      top: '8vh',
      contentUrl: 'index.php?v=d&plugin=mqtt2&modal=mosquitto.log'
    }) 
  });

  $('.configKey[data-l1key=sendEvent]').off('click').on('click', function() {
    if($(this).value() == 1){
      $('#bt_mqtt2DisplayTransmitDevice').hide()
    }else{
      $('#bt_mqtt2DisplayTransmitDevice').show()
    }
  })

  $('.configKey[data-l1key=mode]').off('change').on('change', function() {
    $('.mqtt2Mode').hide()
    $('.mqtt2Mode.' + $(this).value()).show()
  })

  $('.bt_removePluginTopic').off('click').on('click', function() {
    let topic = $(this).attr('data-topic')
    let span = $(this).parent();
    bootbox.confirm('{{Confirmez-vous suppression de l\'abonnement : }}'+topic+'?', function(result) {
      if (result) {
          $.ajax({
          type: "POST",
          url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
          data: {
            action: "removePluginTopic",
            topic: topic
          },
          dataType: 'json',
          error: function(error) {
            $.fn.showAlert({message: error.message,level: 'danger'})
          },
          success: function(data) {
            if (data.state != 'ok') {
              $.fn.showAlert({message: data.result,level: 'danger'})
              return
            }
            $.fn.showAlert({message: '{{Suppression réussie}}',level: 'success',emptyBefore: true})
            span.remove();
          }
        })
      }
    })
  })

  $('#bt_mqtt2SendDiscoveryConfiguration').off('click').on('click', function() {
    $.ajax({
      type: "POST",
      url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
      data: {
        action: "sendDiscovery"
      },
      dataType: 'json',
      error: function(error) {
        $.fn.showAlert({message: error.message,level: 'danger'})
      },
      success: function(data) {
        if (data.state != 'ok') {
          $.fn.showAlert({message: data.result,level: 'danger'})
          return
        }
        $.fn.showAlert({message: '{{Envoi de la découverte réussi}}',level: 'success',emptyBefore: true})
      }
    })
  })

  $('#bt_mqtt2RestartMosquitto').off('click').on('click', function() {
    $.ajax({
      type: "POST",
      url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
      data: {
        action: "restartMosquitto"
      },
      dataType: 'json',
      error: function(error) {
        $.fn.showAlert({message: error.message,level: 'danger'})
      },
      success: function(data) {
        if (data.state != 'ok') {
          $.fn.showAlert({message: data.result,level: 'danger'})
          return
        }
        $('.pluginDisplayCard[data-plugin_id=' + $('#span_plugin_id').text() + ']').click()
        $.fn.showAlert({message: '{{Redémarrage réussi}}',level: 'success', emptyBefore: true})
      }
    })
  })

  $('#bt_mqtt2StopMosquitto').off('click').on('click', function() {
    $.ajax({
      type: "POST",
      url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
      data: {
        action: "stopMosquitto"
      },
      dataType: 'json',
      error: function(error) {
        $.fn.showAlert({message: error.message,level: 'danger'})
      },
      success: function(data) {
        if (data.state != 'ok') {
          $.fn.showAlert({message: data.result,level: 'danger'})
          return
        }
        $('.pluginDisplayCard[data-plugin_id=' + $('#span_plugin_id').text() + ']').click()
        $.fn.showAlert({message: '{{Arrêt réussi}}',level: 'success',emptyBefore: true})
      }
    })
  })

  $('#bt_mqtt2InstallMosquitto').off('click').on('click', function() {
    $.ajax({
      type: "POST",
      url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
      data: {
        action: "installMosquitto"
      },
      dataType: 'json',
      error: function(error) {
        $.fn.showAlert({message: error.message,level: 'danger'})
      },
      success: function(data) {
        if (data.state != 'ok') {
          $.fn.showAlert({message: data.result,level: 'danger'})
          return
        }
        $('.pluginDisplayCard[data-plugin_id=' + $('#span_plugin_id').text() + ']').click()
        $.fn.showAlert({message: '{{Installation réussie}}',level: 'success',emptyBefore: true})
      }
    })
  })

  $('#bt_mqtt2UninstallMosquitto').off('click').on('click', function() {
    bootbox.confirm('{{Confirmez-vous la désinstallation du broker Mosquitto local?}}', function(result) {
      if (result) {
        $.ajax({
          type: "POST",
          url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
          data: {
            action: "uninstallMosquitto"
          },
          dataType: 'json',
          error: function(error) {
            $.fn.showAlert({message: error.message,level: 'danger'})
          },
          success: function(data) {
            if (data.state != 'ok') {
              $.fn.showAlert({message: data.result,level: 'danger'})
              return
            }
            $.fn.showAlert({message: '{{Désinstallation réussie}}',level: 'success',emptyBefore: true})
          }
        })
      }
    })
  })
</script>
