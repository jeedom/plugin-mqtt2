<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('mqtt2');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

$manufacturers = array();
foreach (mqtt2::devicesParameters() as $id => &$info) {
	if (!isset($info['manufacturer'])) {
		$info['manufacturer'] = __('Aucun', __FILE__);
	}
	if (!isset($manufacturers[$info['manufacturer']])) {
		$manufacturers[$info['manufacturer']] = array();
	}
	$manufacturers[$info['manufacturer']][$id] = $info;
}
ksort($manufacturers);

function sortDevice($a, $b) {
	if ($a['name'] == $b['name']) {
		return 0;
	}
	return ($a['name'] < $b['name']) ? -1 : 1;
}

foreach ($manufacturers as &$array) {
	uasort($array, "sortDevice");
}
?>

<div class="row row-overflow">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br>
				<span>{{Ajouter}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
			<?php if (config::byKey('autodiscovery', 'mqtt2') == 1) { ?>
				<div class="cursor eqLogicAction logoSecondary" id="bt_disableAutoDiscovery">
					<i class="fas fa-times"></i>
					<br>
					<span>{{Désactiver auto-decouverte}}</span>
				</div>
			<?php } else { ?>
				<div class="cursor eqLogicAction logoSecondary" id="bt_enableAutoDiscovery">
					<i class="fas fa-check"></i>
					<br>
					<span>{{Activer auto-decouverte}}</span>
				</div>
			<?php } ?>
		</div>
		<legend><i class="fas fa-project-diagram"></i> {{Mes MQTT}}</legend>
		<?php
		if (count($eqLogics) == 0) {
			echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement MQTT trouvé, cliquer sur "Ajouter" pour commencer}}</div>';
		} else {
			echo '<div class="input-group" style="margin:5px;">';
			echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
			echo '<div class="input-group-btn">';
			echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
			echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
			echo '</div>';
			echo '</div>';
			echo '<div class="eqLogicThumbnailContainer">';
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
				echo '<img src="' . $eqLogic->getImage() . '"/>';
				echo '<br>';
				echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
				echo '<span class="hiddenAsCard displayTableRight hidden">';
				echo ($eqLogic->getLogicalId() != '') ? '<span class="label label-info">' . $eqLogic->getLogicalId() . '</span>' : '';
				echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
				echo '</span>';
				echo '</div>';
			}
			echo '</div>';
		}
		?>
	</div>

	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
				</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>

		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<form class="form-horizontal">
					<fieldset>
						<div class="col-lg-6">
							<legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
								<div class="col-sm-6">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Objet parent}}</label>
								<div class="col-sm-6">
									<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
										$options = '';
										foreach ((jeeObject::buildTree(null, false)) as $object) {
											$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
										}
										echo $options;
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Catégorie}}</label>
								<div class="col-sm-6">
									<?php
									foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
										echo '<label class="checkbox-inline">';
										echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" >' . $value['name'];
										echo '</label>';
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Options}}</label>
								<div class="col-sm-6">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
								</div>
							</div>

							<legend><i class="fas fa-cogs"></i> {{Paramètres spécifiques}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Topic racine}}</label>
								<div class="col-sm-6">
									<input type="text" class="eqLogicAttr form-control" data-l1key="logicalId">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Activer l'analyse des valeurs pour la création simplifiée des commandes (attention cela consomme plus de ressources)}}</label>
								<div class="col-sm-6">
									<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="enableDiscoverCmd">
								</div>
							</div>
						</div>

						<div class="col-lg-6">
							<legend><i class="fas fa-info"></i> {{Informations}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Description}}</label>
								<div class="col-sm-7">
									<textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"></textarea>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Fabricant}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Sélectionner le fabricant du module}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="manufacturer">
										<option value="">{{Aucun}}</option>
										<?php
										foreach ($manufacturers as $manufacturer => $devices) {
											echo '<option value="' . $manufacturer . '">' . $manufacturer . '</option>';
										}
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Equipement}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Sélectionner le type d'équipement}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="device">
										<option value="" data-manufacturer="all">{{Inconnu}}</option>
										<?php
										$options = '';
										foreach ($manufacturers as $manufacturer => $devices) {
											if (!is_array($devices) || count($devices) == 0) {
												continue;
											}

											foreach ($devices as $id => $info) {
												if (!isset($info['name'])) {
													continue;
												}
												$name = (isset($info['ref'])) ?  $info['name'] . ' [' . $info['ref'] . '] ' : $info['name'];
												if (isset($info['instruction'])) {
													$options .= '<option data-manufacturer="' . $manufacturer . '" value="' . $id . '" data-img="' . mqtt2::getImgFilePath($id, $manufacturer) . '" data-instruction="' . $info['instruction'] . '" style="display:none;">' . $name . '</option>';
												} else {
													$options .= '<option data-manufacturer="' . $manufacturer . '" value="' . $id . '" data-img="' . mqtt2::getImgFilePath($id, $manufacturer) . '" style="display:none;">' . $name . '</option>';
												}
											}
										}
										echo $options;
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label"></label>
								<div class="col-sm-7">
									<div id="div_instruction"></div>
									<div style="height:220px;display:flex;justify-content:center;align-items:center;">
										<img src="plugins/mqtt2/plugin_info/mqtt2_icon.png" data-original=".jpg" id="img_device" class="img-responsive" style="max-height:200px;max-width:200px;" onerror="this.src='plugins/mqtt2/plugin_info/mqtt2_icon.png'" />
									</div>
								</div>
							</div>
						</div>
					</fieldset>
				</form>
			</div>

			<div role="tabpanel" class="tab-pane" id="commandtab">
				<div class="input-group pull-right" style="display:inline-flex">
					<span class="input-group-btn">
						<a class="btn btn-info btn-sm cmdAction roundedLeft" data-action="discover"><i class="fas fa-search"></i> {{Découverte}}</a><a class="btn btn-default btn-sm cmdAction" data-action="importFromTemplate"><i class="fas fa-file"></i> {{Templates}}</a><a class="btn btn-success btn-sm cmdAction roundedRight" data-action="add"><i class="fas fa-plus-circle"></i> {{Commandes}}</a>
					</span>
				</div>
				<br><br>
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th class="hidden-xs" style="min-width:50px;width:70px;"> ID</th>
								<th style="min-width:150px;width:300px;">{{Nom}}</th>
								<th style="width:130px;">{{Type}}</th>
								<th>{{Paramètres}}</th>
								<th>{{Etat}}</th>
								<th style="min-width:260px;width:400px;">{{Options}}</th>
								<th style="min-width:80px;width:180px;">{{Actions}}</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div>

		</div>
	</div>
</div>

<?php
include_file('desktop', 'mqtt2', 'js', 'mqtt2');
include_file('core', 'plugin.template', 'js');
?>