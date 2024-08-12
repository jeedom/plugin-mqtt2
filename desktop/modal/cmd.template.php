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
$templates = mqtt2::listCmdTemplate();
?>
<div style="display: none;" id="md_cmdTemplateAlert"></div>
<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-sm-2 control-label">{{Template}}</label>
            <div class="col-sm-3">
                <select class="form-control cmdTemplateAttr" data-l1key="templateName">
                    <option value="">{{Choisir un template}}</option>
                    <?php
                    foreach ($templates as $key => $value) {
                        echo '<option value="' . $key . '">' . $value['name'] . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-sm-3">
                <a class="btn btn-success" id="bt_cmdTemplateCreate">{{Appliquer}}</a>
            </div>
        </div>
    </fieldset>
</form>

<?php
foreach ($templates as $id => $config) {
    echo '<form class="form-horizontal templateForm ' . str_replace('.', '-', $id) . '" style="display:none;">';
    echo '<fieldset>';
    foreach ($config['parameters'] as $key => $parameter) {
        echo '<div class="form-group">';
        echo '<label class="col-sm-2 control-label">' . $parameter['name'] . '</label>';
        echo '<div class="col-sm-3">';
        switch ($parameter['type']) {
            case 'color':
                echo '<input type="color" class="cmdTemplateAttr form-control" data-l1key="' . $parameter['key'] . '"/>';
                break;
            case 'input':
                echo '<input class="cmdTemplateAttr form-control" data-l1key="' . $parameter['key'] . '"/>';
                break;
            case 'number':
                echo '<input type="number" class="cmdTemplateAttr form-control" data-l1key="' . $parameter['key'] . '"/>';
                break;
        }
        echo '</div>';
        if (isset($parameter['description'])) {
            echo '<div class="col-sm-3 alert alert-info">';
            echo $parameter['description'];
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</fieldset>';
    echo '</form>';
}
?>


<script>
    $('.cmdTemplateAttr[data-l1key=templateName]').on('change', function() {
        $('.templateForm').hide();
        $('.templateForm.' + $(this).value().replace('.', '-')).show();
    });

    $('#bt_cmdTemplateCreate').on('click', function() {
        if ($('.cmdTemplateAttr[data-l1key=templateName]').value() == '') {
            $('#md_cmdTemplateAlert').showAlert({
                message: '{{Vous devez choisir un template}}',
                level: 'danger'
            });
            return;
        }
        var config = $('.templateForm.' + $('.cmdTemplateAttr[data-l1key=templateName]').value().replace('.', '-')).getValues('.cmdTemplateAttr')[0];
        config.template = $('.cmdTemplateAttr[data-l1key=templateName]').value();
        $.ajax({
            type: "POST",
            url: "plugins/mqtt2/core/ajax/mqtt2.ajax.php",
            data: {
                action: "createFromTemplate",
                eqLogic_id: <?php echo init('eqLogic_id'); ?>,
                config: json_encode(config)
            },
            dataType: 'json',
            error: function(request, status, error) {
                handleAjaxError(request, status, error, $('#md_cmdTemplateAlert'));
            },
            success: function(data) {
                if (data.state != 'ok') {
                    $('#md_cmdTemplateAlert').showAlert({
                        message: data.result,
                        level: 'danger'
                    });
                    return;
                }
                $('#md_cmdTemplateAlert').showAlert({
                    message: '{{Création réussie}}',
                    level: 'success'
                });
                $('.eqLogicDisplayCard[data-eqLogic_id=<?php echo init('eqLogic_id'); ?>]').click();
            }
        });
    });
</script>
