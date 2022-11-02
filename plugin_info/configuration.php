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
        <label class="col-md-4 control-label">{{Port socket interne}}</label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="socketport">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Cycle (s)}}</label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="cycle">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Mode}}</label>
        <div class="col-md-7">
          <select class="configKey form-control" data-l1key="mode">
            <option value="remote">{{Broker distant}}</option>
            <option value="docker">{{Broker local docker}}</option>
            <option value="local">{{Broker local}}</option>
          </select>
        </div>
      </div>
      <div class="form-group mqtt2Mode local docker">
        <label class="col-md-4 control-label"></label>
        <div class="col-md-7">
          <a class="btn btn-warning" id="bt_mqtt2InstallMosquitto">{{Installer mosquitto}}</a>
          <a class="btn btn-primary" target="_blank" href="plugins/mqtt2/core/php/downloadClientSsl.php">{{Télécharger le certificat client}}</a>
        </div>
      </div>
      <div class="form-group mqtt2Mode remote">
        <label class="col-md-4 control-label">{{Adresse du broker}}</label>
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
          <sup><i class="fas fa-question-circle tooltips" title="{{Identifiant et mot de passe de connexion au broker MQTT (séparés par :)}}"></i></sup>
        </label>
        <div class="col-md-7">
          <textarea class="configKey form-control autogrow" data-l1key="mqtt::password"></textarea>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Topic racine Jeedom}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Topic racine pour piloter Jeedom et sur lequel il envoie ses évènements}}"></i></sup>
        </label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="root_topic">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Transmettre tous les évènements}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Cocher la case pour que tous les évènements des commandes soient transmis au broker MQTT}}"></i></sup>
        </label>
        <div class="col-md-7">
          <input type="checkbox" class="configKey" data-l1key="sendEvent">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Template publish}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Template pour l'envoi des event jeedom, tags possibles : #value#, #humanName#, #unit#, #name#, #type#, #subtype#}}"></i></sup>
        </label>
        <div class="col-md-7">
          <input class="configKey form-control" data-l1key="publish_template">
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-4 control-label">{{Plugins abonnés}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Liste des plugins Jeedom utilisant mqtt}}"></i></sup>
        </label>
        <div class="subscribed col-md-7">

        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="form-group mqtt2Mode docker">
        <label class="col-md-4 control-label">{{Paramètres Mosquitto}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Sauvegarder et relancer l'installation de mosquitto pour prendre en compte les modifications de ce champ de configuration}}"></i></sup>
        </label>
        <div class="col-md-7">
          <textarea class="configKey form-control autogrow" data-l1key="mosquitto::parameters"></textarea>
        </div>
      </div>
      <div class="form-group mqtt2Mode docker">
        <label class="col-md-4 control-label">{{Port(s)}}
          <sup><i class="fas fa-question-circle tooltips" title="{{Sauvegarder et relancer l'installation de mosquitto pour prendre en compte les modifications de ce champ de configuration}}"></i></sup>
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
    $('.mqtt2Mode').hide();
    $('.mqtt2Mode.' + $(this).value()).show();
  });

  $('#bt_mqtt2InstallMosquitto').off('click').on('click', function() {
    $.ajax({
      type: "POST",
      url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
      data: {
        action: "installMosquitto"
      },
      dataType: 'json',
      error: function(request, status, error) {
        handleAjaxError(request, status, error);
      },
      success: function(data) {
        if (data.state != 'ok') {
          $('#div_alert').showAlert({
            message: data.result,
            level: 'danger'
          });
          return;
        } else {
          window.toastr.clear()
          $('.pluginDisplayCard[data-plugin_id=' + $('#span_plugin_id').text() + ']').click()
          $('#div_alert').showAlert({
            message: '{{Installation réussie}}',
            level: 'success'
          });

        }
      }
    });
  });
  $.ajax({
    type: "POST",
    url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
    data: {
      action: "getSubscribed"
    },
    dataType: 'json',
    error: function(request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function(data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({
          message: data.result,
          level: 'danger'
        });
        return;
      } else {
        var results = '';
        for (plugin in data.result) {
          results += '<span class="label label-success">' + plugin + ' (' + data.result[plugin] + ') ' + '</span> '
        }
        $('.subscribed').empty().append(results);
      }

    }
  });
</script>