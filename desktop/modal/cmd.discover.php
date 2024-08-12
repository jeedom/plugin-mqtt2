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
$eqLogic = eqLogic::byId(init('eqLogic_id'));
if (!is_object($eqLogic)) {
    throw new Exception('{{Equipement introuvable}} : ' . init('eqLogic_id'));
}
if ($eqLogic->getEqType_name() != 'mqtt2') {
    throw new Exception('{{Equipement pas de type mqtt2}}');
}
$discoverCmds = $eqLogic->getDiscover();
if (count($discoverCmds) == 0) {
    throw new Exception('{{Aucune commande découverte pour cet équipement}}');
}
global $JEEDOM_INTERNAL_CONFIG;
?>
<div style="display: none;" id="md_cmdDiscoverAlert"></div>
<a class="btn btn-success pull-right" id="bt_saveDiscover"><i class="fa fa-check"></i> {{Sauvegarder}}</a>
<table class="table table-bordered table-condensed tablesorter" id="table_mqttDiscover">
    <thead>
        <tr>
            <th data-sorter="false" data-filter="false"></th>
            <th>{{Topic}}</th>
            <th>{{Valeur}}</th>
            <th>{{Nom}}</th>
            <th>{{Sous type}}</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($discoverCmds as $key => $value) {
            echo '<tr class="discover">';
            echo '<td>';
            echo '<input type="checkbox" class="discoverAttr" data-l1key="create" />';
            echo '</td>';
            echo '<td>';
            echo '<span class="discoverAttr" data-l1key="logicalId">' . $key . '</span>';
            echo '</td>';
            echo '<td>';
            if (is_bool($value['value'])) {
                $value['value'] = $value['value'] ? 1 : 0;
            }
            echo $value['value'];
            echo '</td>';
            echo '<td>';
            echo '<input class="form-control discoverAttr" data-l1key="name" value="' . $key . '" />';
            echo '</td>';
            echo '<td>';
            echo '<select class="form-control discoverAttr" data-l1key="subType">';
            $subtype = 'string';
            if (is_numeric($value['value'])) {
                $subtype = 'numeric';
                if ($value['value'] == 0 || $value['value'] == 1) {
                    $subtype = 'binary';
                }
            }
            foreach ($JEEDOM_INTERNAL_CONFIG['cmd']['type']['info']['subtype'] as $k => $v) {
                $selected = ($k == $subtype) ? 'selected' : '';
                echo '<option value="' . $k . '" ' . $selected . '>' . $v['name'] . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }
        ?>
    </tbody>
</table>

<script>
    jeedomUtils.initTableSorter()
    $('#bt_saveDiscover').off('click').on('click', function() {
        let discovers = $('#table_mqttDiscover tbody .discover').getValues('.discoverAttr')
        $.ajax({
            type: "POST",
            url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
            data: {
                action: "createDiscoverCmd",
                eqLogic_id: <?php echo init('eqLogic_id'); ?>,
                discover: json_encode(discovers)
            },
            dataType: 'json',
            error: function(request, status, error) {
                handleAjaxError(request, status, error, $('#md_cmdTemplateAlert'));
            },
            success: function(data) {
                if (data.state != 'ok') {
                    $('#md_cmdDiscoverAlert').showAlert({
                        message: data.result,
                        level: 'danger'
                    });
                    return;
                }
                $('#md_cmdDiscoverAlert').showAlert({
                    message: '{{Création réussie}}',
                    level: 'success'
                });
                $('.eqLogicDisplayCard[data-eqLogic_id=<?php echo init('eqLogic_id'); ?>]').click();
            }
        });
    });
</script>
