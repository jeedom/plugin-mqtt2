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
$eqLogics = eqLogic::all();
?>

<div id="md_mqttEqLogicTransmit" data-modalType="md_mqttEqLogicTransmit">
  <a class="btn btn-success btn-xs pull-right" id="bt_eqLogicMqttTransmitApply"><i class="fas fa-check"></i> {{Valider}}</a>
  <br />

  <table id="table_mqttEqLogicTransmit" class="table table-condensed stickyHead">
    <thead>
      <tr style="margin-top: 20px;">
        <th>{{Nom}}</th>
        <th>{{Plugin}}</th>
        <th data-filter="false" data-type="checkbox"> {{Transmis}}</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $tr = '';
      foreach ($eqLogics as $eqLogic) {
        $tr .= '<tr data-change="0" data-eqLogic_id="' . $eqLogic->getId() . '">';
        $tr .= '<td>';
        $tr .= '<span class="eqLogicAttr" data-l1key="humanName">' . str_replace('<br/>', '', $eqLogic->getHumanName(true, true)) . '</span>';
        $tr .= '<span class="eqLogicAttr" data-l1key="id" style="display:none;">' . $eqLogic->getId() . '</span>';
        $tr .= '</td>';
        $tr .= '<td>';
        $tr .= '<span class="cmdAttr" data-l1key="plugins">' . $eqLogic->getEqType_name() . '</span>';
        $tr .= '</td>';
        $tr .= '<td class="center">';
        $tr .= '<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="plugin::mqtt2::mqttTranmit" ' . (($eqLogic->getConfiguration('plugin::mqtt2::mqttTranmit', 0)) ? 'checked' : '') .  ' />';
        $tr .= '</td>';
        $tr .= '</tr>';
      }
      echo $tr;
      ?>
    </tbody>
  </table>
</div>

<script>
if (!jeeFrontEnd.md_mqttEqLogicTransmit) {
  jeeFrontEnd.md_mqttEqLogicTransmit = {
    vDataTable: null,
    init: function() {
      this.tableConfig = document.getElementById('table_mqttEqLogicTransmit')
      this.modal = this.tableConfig.closest('div.jeeDialogMain')
      this.setConfigTable()
      jeedomUtils.initTooltips(this.tableConfig)
    },
    setConfigTable: function() {
      if (jeeFrontEnd.md_mqttEqLogicTransmit.vDataTable) jeeFrontEnd.md_mqttEqLogicTransmit.vDataTable.destroy()

      jeeFrontEnd.md_mqttEqLogicTransmit.vDataTable = new DataTable(jeeFrontEnd.md_mqttEqLogicTransmit.tableConfig, {
        columns: [
          { select: 0, sort: "asc" }
        ],
        paging: true,
        perPage: 20,
        perPageSelect: [20, 30, 40, 50, 100, 250],
      })
    },
    resetChanges: function() {
      jeeFrontEnd.md_mqttEqLogicTransmit.vDataTable.table.rows.forEach(_row => {
        _row.node.setAttribute('data-change', '0')
      })
    },
    saveConfig: function(event) {
      var eqLogics = []
      if (jeeFrontEnd.md_mqttEqLogicTransmit.vDataTable) {
        jeeFrontEnd.md_mqttEqLogicTransmit.vDataTable.table.rows.forEach(_tr => {
          if (_tr.node.getAttribute('data-change') == '1') {
            eqLogics.push(_tr.node.getJeeValues('.eqLogicAttr')[0])
          }
        })
      } else {
        this.tableConfig.tBodies[0].querySelectorAll('tr').forEach(_tr => {
          if (_tr.getAttribute('data-change') == '1') {
            eqLogics.push(_tr.getJeeValues('.eqLogicAttr')[0])
          }
        })
      }
      if(eqLogics.length == 0){
        return;
      }
      $.ajax({
        type: "POST",
        url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
        data: {
            action: "eqLogicTransmitConfiguration",
            eqLogics: JSON.stringify(eqLogics)
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
            $.fn.showAlert({message: '{{Configuration enregistrée}}',level: 'success',emptyBefore: true})
        }
      })
    },
  }
}

(function() {// Self Isolation!
  var jeeM = jeeFrontEnd.md_mqttEqLogicTransmit
  jeeM.init()

  //Manage events outside parents delegations:
  document.getElementById('bt_eqLogicMqttTransmitApply')?.addEventListener('click', function(event) {
    jeeFrontEnd.md_mqttEqLogicTransmit.saveConfig(event)
  })

  /*Events delegations
  */
  document.getElementById('table_mqttEqLogicTransmit')?.addEventListener('click', function(event) {
    var _target = null
    if (_target = event.target.closest('.eqLogicAttr')) {
      _target.closest('tr').setAttribute('data-change', '1')
    }
  })
})()
</script>