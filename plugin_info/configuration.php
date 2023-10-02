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
          <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionner le mode de connexion au broker}}"></i></sup>
        </label>
        <div class="col-md-7">
          <select class="configKey form-control" data-l1key="mode">
            <option value="none">{{A configurer}}</option>
            <option value="local">{{Broker local}}</option>
            <option value="docker">{{Broker local docker}}</option>
            <option value="remote">{{Broker distant}}</option>
          </select>
        </div>
      </div>

      <div class="form-group mqtt2Mode local docker">
        <label class="col-md-4 control-label">{{Broker Mosquitto}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Installer, désinstaller ou télécharger le certificat client du broker Mosquitto}}"></i></sup>
        </label>
        <div class="col-md-7">
          <a class="btn btn-xs btn-warning" id="bt_mqtt2RestartMosquitto"><i class="fas fa-minus-square"></i> {{Redémarrer Mosquitto}}</a>
          <a class="btn btn-xs btn-warning" id="bt_mqtt2InstallMosquitto"><i class="fas fa-plus-square"></i> {{Installer Mosquitto}}</a>
          <a class="btn btn-xs btn-danger" id="bt_mqtt2UninstallMosquitto"><i class="fas fa-minus-square"></i> {{Désinstaller Mosquitto}}</a>
          <a class="btn btn-sm btn-primary pull-right" target="_blank" href="plugins/mqtt2/core/php/downloadClientSsl.php"><i class="fas fa-key"></i> {{Télécharger le certificat client}}</a>
        </div>
      </div>

      <div class="form-group mqtt2Mode local docker">
        <label class="col-md-4 control-label">{{Etat Broker Mosquitto}}</label>
        <div class="col-md-7">
          <?php 
              $state = shell_exec(system::getCmdSudo() . ' ps ax | grep mosquitto | grep mqtt2 | wc -l');
              if($state == 0){
                  echo '<span class="label label-danger">{{NOK}}</span>';
              }else{
                  echo '<span class="label label-success">{{OK}}</span>';
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
          <sup><i class="fas fa-question-circle tooltips" title="{{Modification dangeureuse}}"></i></sup>
        </label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="socketport">
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
          <label class="checkbox-inline pull-right"><input type="checkbox" class="configKey" data-l1key="sendEvent">{{Transmettre tous les évènements}}
            <sup><i class="fas fa-question-circle tooltips" title="{{Cocher la case pour que tous les évènements des commandes soient transmis au broker MQTT}}"></i></sup>
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Template de publication}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Template de publication des évènements Jeedom}} (tags : #value#, #humanName#, #unit#, #name#, #type#, #subtype#)"></i></sup>
        </label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="publish_template">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{QOS par defaut}}
          <sup><i class="fas fa-question-circle tooltips"></i></sup>
        </label>
         <div class="col-md-7">
          <select class="configKey form-control" data-l1key="qos::default">
            <option value="0">{{QOS 0 (défaut)}}</option>
            <option value="1">{{QOS 1}}</option>
            <option value="2">{{QOS 2}}</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Plugins abonnés}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Liste des plugins Jeedom abonnés au plugin MQTT Manager [topic ( plugin id)]}}"></i></sup>
        </label>
        <div class="subscribed col-md-7">
          <?php foreach (mqtt2::getSubscribed() as $plugin => $subscribed) { ?>
            <span class="label label-success"><?= $plugin ?> (<?= $subscribed ?>)</span>
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
  $('.configKey[data-l1key=mode]').off('change').on('change', function() {
    $('.mqtt2Mode').hide()
    $('.mqtt2Mode.' + $(this).value()).show()
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
        $.fn.showAlert({
          message: error.message,
          level: 'danger'
        })
      },
      success: function(data) {
        if (data.state != 'ok') {
          $.fn.showAlert({
            message: data.result,
            level: 'danger'
          })
          return
        } else {
          $('.pluginDisplayCard[data-plugin_id=' + $('#span_plugin_id').text() + ']').click()
          $.fn.showAlert({
            message: '{{Redemarrage réussie}}',
            level: 'success',
            emptyBefore: true
          })
        }
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
        $.fn.showAlert({
          message: error.message,
          level: 'danger'
        })
      },
      success: function(data) {
        if (data.state != 'ok') {
          $.fn.showAlert({
            message: data.result,
            level: 'danger'
          })
          return
        } else {
          $('.pluginDisplayCard[data-plugin_id=' + $('#span_plugin_id').text() + ']').click()
          $.fn.showAlert({
            message: '{{Installation réussie}}',
            level: 'success',
            emptyBefore: true
          })

        }
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
            $.fn.showAlert({
              message: error.message,
              level: 'danger'
            })
          },
          success: function(data) {
            if (data.state != 'ok') {
              $.fn.showAlert({
                message: data.result,
                level: 'danger'
              })
              return
            } else {
              $.fn.showAlert({
                message: '{{Désinstallation réussie}}',
                level: 'success',
                emptyBefore: true
              })

            }
          }
        })
      }
    })
  })
</script>
