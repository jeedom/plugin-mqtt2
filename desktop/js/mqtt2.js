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

$('#bt_disableAutoDiscovery').off('click').on('click',function(){
  jeedom.config.save({
    plugin : 'mqtt2',
    configuration: {
      autodiscovery: 0
    },
    error: function(error) {
      jeedomUtils.showAlert({
        message: error.message,
        level: 'danger'
      })
    },
    success: function() {
      location.reload();
    }
  });
})

$('#bt_enableAutoDiscovery').off('click').on('click',function(){
  jeedom.config.save({
    plugin : 'mqtt2',
    configuration: {
      autodiscovery: 1
    },
    error: function(error) {
      jeedomUtils.showAlert({
        message: error.message,
        level: 'danger'
      })
    },
    success: function() {
      location.reload();
    }
  });
})

$('#bt_mqtt2SendDiscovery').off('click').on('click',function(){
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

$('.cmdAction[data-action=importFromTemplate]').on('click',function(){
  $('#md_modal').dialog({title: "{{Template commande MQTT}}"});
  $("#md_modal").load('index.php?v=d&plugin=mqtt2&modal=cmd.template&eqLogic_id=' + $('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
});

$('.cmdAction[data-action=discover]').on('click',function(){
  $('#md_modal').dialog({title: "{{Découverte commande MQTT}}"});
  $("#md_modal").load('index.php?v=d&plugin=mqtt2&modal=cmd.discover&eqLogic_id=' + $('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=manufacturer]').off('change').on('change', function () {
  $('.eqLogicAttr[data-l1key=configuration][data-l2key=device] option').hide();
  $('.eqLogicAttr[data-l1key=configuration][data-l2key=device] option[data-manufacturer=all]').show();
  if($(this).value() != ''){
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=device] option[data-manufacturer="'+$(this).value()+'"]').show();
  }
  let manufacturer = $('.eqLogicAttr[data-l1key=configuration][data-l2key=device] option:selected').attr('data-manufacturer');
  if(manufacturer && manufacturer != 'all' && manufacturer != $(this).value()){
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=device]').value($('.eqLogicAttr[data-l1key=configuration][data-l2key=device] option:not([hidden]):eq(0)').attr("value"))
  }
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=device]').off('change').on('change', function () {
  let manufacturer = $('.eqLogicAttr[data-l1key=configuration][data-l2key=device] option:selected').attr('data-manufacturer');
  if( manufacturer && manufacturer != 'all' &&$('.eqLogicAttr[data-l1key=configuration][data-l2key=manufacturer]').value() != manufacturer){
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=manufacturer]').value($('.eqLogicAttr[data-l1key=configuration][data-l2key=device] option:selected').attr('data-manufacturer'))
  }
  if($('.li_eqLogic.active').attr('data-eqlogic_id') != '' && $(this).value() != ''){
    let img = $('.eqLogicAttr[data-l1key=configuration][data-l2key=device] option:selected').attr('data-img')
    if(img != undefined){
      $('#img_device').attr("src", 'plugins/mqtt2/core/config/devices/'+img);
    }else{
      $('#img_device').attr("src",'plugins/mqtt2/plugin_info/mqtt2_icon.png');
    }
  }else{
    $('#img_device').attr("src",'plugins/mqtt2/plugin_info/mqtt2_icon.png');
  }
});

$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} }
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
  tr += '<td class="hidden-xs">'
  tr += '<span class="cmdAttr" data-l1key="id"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '<span class="input-group-btn">'
  tr += '<a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a>'
  tr += '</span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande information liée}}">'
  tr += '<option value="">{{Aucune}}</option>'
  tr += '</select>'
  tr += '</td>'
  tr += '<td>'
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
  tr += '</td>'
  tr += '<td >'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="logicalId" placeholder="{{Topic}}" title="{{Topic}}"/> '
  tr += '<input class="cmdAttr form-control input-sm cmdType action" style="margin-top:3px" data-l1key="configuration" data-l2key="message" placeholder="{{Message}}" title="{{Message}}"/> '
  tr += '</td>'
  tr += '<td>'
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked>{{Afficher}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked>{{Historiser}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary">{{Inverser}}</label> '
  tr += '<label class="checkbox-inline cmdType action" ><input type="checkbox" class="cmdAttr" data-l1key="configuration" data-l2key="retain"/>{{Retain}}</label>'
  tr += '<div style="margin-top:7px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de valeur|texte séparé par ;}}" title="{{Liste}}">';
  tr += '</div>'
  tr += '</td>'
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
  }
  tr += ' <i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>'
  tr += '</tr>'
  $('#table_cmd tbody').append(tr)
  var tr = $('#table_cmd tbody tr').last()
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function(error) {
      $.fn.showAlert({ message: error.message, level: 'danger' })
    },
    success: function(result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result)
      tr.setValues(_cmd, '.cmdAttr')
      jeedom.cmd.changeType(tr, init(_cmd.subType))
    }
  })
}

$('#table_cmd').on('change', '.cmdAttr[data-l1key=type]', function() {
  let tr = $(this).closest('tr')
  tr.find('.cmdType').hide()
  if ($(this).value() != '') {
    tr.find('.cmdType.' + $(this).value()).show()
  }
})
