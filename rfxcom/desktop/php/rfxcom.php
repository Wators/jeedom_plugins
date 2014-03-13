<?php
if (!isConnect('admin')) {
    throw new Exception('Error 401 Unauthorized');
}
sendVarToJS('eqType', 'rfxcom');
?>



<?php
$port = config::byKey('port', 'rfxcom');
if ($port == '' || !file_exists($port)) {
    echo '<div class="row">';
    echo '<div class="col-lg-2"></div>';
    echo '<div class="col-lg-10">';
    echo '<div class="alert alert-danger">Le port du RFXcom est vide ou n\'éxiste pas</div>';
    echo '</div>';
    echo '</div>';
} else {
    if (!rfxcom::deamonRunning()) {
        echo '<div class="row">';
        echo '<div class="col-lg-2"></div>';
        echo '<div class="col-lg-10">';
        echo '<div class="alert alert-danger">Le démon RFXcom ne tourne pas vérifier le port (si vous venez de l\'arreter il redemarrera automatiquement dans 1 minute</div>';
        echo '</div>';
        echo '</div>';
    }
}
?>

<div class="row">
    <div class="col-lg-2">
        <div class="bs-sidebar affix">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav fixnav">
                <?php
                if (rfxcom::deamonRunning()) {
                    echo "<a class='btn btn-success btn-sm tooltips' id='bt_stopRFXcomDemon' title=\"Le démon est démarrer. Forcer l'arret du démon RFXcom\" style='display: inline-block;'><i class='fa fa-stop'></i></a>";
                } else {
                    echo "<a class='btn btn-danger btn-sm tooltips' id='bt_stopRFXcomDemon' title=\"Le démon semble arrêter. Forcer l'arret du démon RFXcom\" style='display: inline-block;'><i class='fa fa-stop'></i></a>";
                }
                ?>
                <li class="nav-header">Liste équipements RFXcom
                    <i class="fa fa-plus-circle pull-right cursor eqLogicAction" data-action="add" style="font-size: 1.5em;margin-bottom: 5px;"></i>
                </li>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="Rechercher" style="width: 100%"/></li>
                <?php
                foreach (eqLogic::byType('rfxcom') as $eqLogic) {
                    echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName() . '</a></li>';
                }
                ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-10 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
        <div class="row">
            <div class="col-lg-6">
                <form class="form-horizontal">
                    <fieldset>
                        <legend>Général</legend>
                        <div class="form-group">
                            <label class="col-lg-3 control-label">Nom de l'équipement RFXcom</label>
                            <div class="col-lg-4">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="Nom de l'équipement RFXcom"/>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-3 control-label">ID</label>
                            <div class="col-lg-4">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="logicalId" placeholder="Logical ID"/>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-3 control-label">Activer</label>
                            <div class="col-lg-1">
                                <input type="checkbox" class="eqLogicAttr form-control" data-l1key="isEnable" checked/>
                            </div>
                            <label class="col-lg-1 control-label">Visible</label>
                            <div class="col-lg-1">
                                <input type="checkbox" class="eqLogicAttr form-control" data-l1key="isVisible" checked/>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-3 control-label" >Objet parent</label>
                            <div class="col-lg-4">
                                <select class="eqLogicAttr form-control" data-l1key="object_id">
                                    <option value="">Aucun</option>
                                    <?php
                                    foreach (object::all() as $object) {
                                        echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-3 control-label">Catégorie</label>
                            <div class="col-lg-9">
                                <?php
                                foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                    echo '<label class="checkbox-inline">';
                                    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                                    echo '</label>';
                                }
                                ?>

                            </div>
                        </div>
                    </fieldset> 
                </form>
            </div>
            <div class="col-lg-6">
                <form class="form-horizontal">
                    <fieldset>
                        <legend>Informations</legend>
                        <div class="form-group">
                            <label class="col-lg-2 control-label">Equipement</label>
                            <div class="col-lg-8">
                                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="device">
                                    <option value="">Aucun</option>
                                    <?php
                                    foreach (rfxcom::devicesParameters() as $packettype => $info) {
                                        foreach ($info['subtype'] as $subtype => $subInfo) {
                                            echo '<option value="' . $packettype . '::' . $subtype . '">' . $info['name'] . ' - ' . $subInfo['name'] . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </fieldset> 
                </form>
            </div>
        </div>

        <legend>PushingBox</legend>


        <a class="btn btn-success btn-sm cmdAction" data-action="add"><i class="fa fa-plus-circle"></i> Ajouter une commande</a><br/><br/>
        <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th style="width: 300px;">Nom</th>
                    <th style="width: 130px;" class="expertModeHidden">Type</th>
                    <th class="expertModeHidden">Logical ID (info) ou Commande brute (action)</th>
                    <th >Paramètres</th>
                    <th style="width: 100px;">Options</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>

            </tbody>
        </table>

        <form class="form-horizontal">
            <fieldset>
                <div class="form-actions">
                    <a class="btn btn-danger eqLogicAction" data-action="remove"><i class="fa fa-minus-circle"></i> Supprimer</a>
                    <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> Sauvegarder</a>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<?php include_file('desktop', 'rfxcom', 'js', 'rfxcom'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>