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
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<a class="btn btn-primary pull-right" id="bt_refreshMosquittoLog"><i class="fas fa-sync"></i></a>
<pre><?php echo shell_exec(system::getCmdSudo().' cat /var/log/mosquitto/mosquitto.log'); ?></pre>

<script>
$('#bt_refreshMosquittoLog').off('click').on('click', function() {
    jeeDialog.dialog({
      id: 'jee_MqttModal',
      title: '{{Log mosquitto}}',
      width: '85vw',
      height: '51vw',
      top: '8vh',
      contentUrl: 'index.php?v=d&plugin=mqtt2&modal=mosquitto.log'
    }) 
  });
</script>