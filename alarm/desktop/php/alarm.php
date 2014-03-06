<?php
if (!isConnect('admin')) {
    throw new Exception('Error 401 Unauthorized');
}
sendVarToJS('eqType', 'alarm');
?>
<div class="row">
    <div class="col-lg-2">
        <div class="bs-sidebar affix">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav fixnav">
                <li class="nav-header">Liste des alarmes
                    <i class="fa fa-plus-circle pull-right cursor eqLogicAction" data-action="add" style="font-size: 1.5em;margin-bottom: 5px;"></i>
                </li>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="Rechercher" style="width: 100%"/></li>
                <?php
                foreach (eqLogic::byType('alarm') as $eqLogic) {
                    echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName() . '</a></li>';
                }
                ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-10 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
        <form class="form-horizontal">
            <fieldset>
                <legend>Général</legend>
                <div class="form-group">
                    <label class="col-lg-2 control-label">Nom de l'alarme</label>
                    <div class="col-lg-3">
                        <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                        <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="Nom de la zone"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label" >Objet parent</label>
                    <div class="col-lg-3">
                        <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
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
                    <label class="col-lg-2 control-label">Catégorie</label>
                    <div class="col-lg-3">
                        <?php
                        foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                            echo '<label class="checkbox-inline">';
                            echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                            echo '</label>';
                        }
                        ?>

                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">Activer</label>
                    <div class="col-lg-1">
                        <input type="checkbox" class="eqLogicAttr form-control" data-l1key="isEnable" checked/>
                    </div>
                    <label class="col-lg-2 control-label">Visible</label>
                    <div class="col-lg-1">
                        <input type="checkbox" class="eqLogicAttr form-control" data-l1key="isVisible" checked/>
                    </div>
                </div>

                <div class="form-group expertModeHidden">
                    <label class="col-lg-2 control-label">Actif en permanance</label>
                    <div class="col-lg-1">
                        <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="always_active"/>
                    </div>
                    <label class="col-lg-2 control-label">Armement visible</label>
                    <div class="col-lg-1">
                        <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="armed_visible" checked/>
                    </div>
                    <label class="col-lg-2 control-label">Libération visible</label>
                    <div class="col-lg-1">
                        <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="free_visible" checked/>
                    </div>
                    <label class="col-lg-2 control-label">Status immédiat visible</label>
                    <div class="col-lg-1">
                        <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="immediateState_visible"/>
                    </div>
                </div>
            </fieldset> 
        </form>

        <ul class="nav nav-tabs" id="tab_alarm">
            <li class="active"><a href="#tab_zones">Zones</a></li>
            <li><a href="#tab_modes">Modes</a></li>
            <li><a href="#tab_raz">Remise à zéro</a></li>
            <li><a href="#tab_ping">Pertes ping</a></li>
            <li><a href="#tab_activeOk">Activation OK</a></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane active" id="tab_zones">
                <a class="btn btn-success btn-xs pull-right" id="bt_addZone" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> Ajouter zone</a>
                <br/><br/>
                <div id="div_zones"></div> 
            </div>

            <div class="tab-pane" id="tab_modes">
                <a class="btn btn-success btn-xs pull-right" id="bt_addMode" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> Ajouter mode</a>
                <br/><br/>
                <div id="div_modes"></div> 
            </div>

            <div class="tab-pane" id="tab_raz">
                <a class='btn btn-success btn-xs pull-right' id="btn_addRazAlarm" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> Ajouter RaZ</a>
                <a class='btn btn-warning btn-xs pull-right' id="btn_addRazImmediateAlarm" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> Ajouter RaZ immédiat</a>
                <br/><br/>
                <form class="form-horizontal">
                    <div id="div_razAlarm"></div>
                </form>
                <hr/>
                <br/>
                <form class="form-horizontal">
                    <div id="div_razImmediateAlarm"></div>
                </form>
            </div>

            <div class="tab-pane" id="tab_ping">
                <a class='btn btn-success btn-xs pull-right' id="btn_addPingAction" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> Ajouter action perte de ping</a>
                <br/><br/>
                <form class="form-horizontal">
                    <div id="div_actionsPing"></div>
                </form>
            </div>

            <div class="tab-pane" id="tab_activeOk">
                <a class='btn btn-success btn-xs pull-right' id="btn_addActionActivationOk" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> Ajouter action lors de lactivation OK</a>
                <br/><br/>
                <form class="form-horizontal">
                    <div id="div_activationOk"></div>
                </form>
            </div>
        </div>

        <br/><br/>
        <hr/>
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


<div class="modal fade" id="md_addZoneMode">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title">Ajouter zone</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-lg-4 control-label" >Zone</label>
                        <div class="col-lg-8" id="md_addZoneModeSelect">

                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <a class="btn btn-danger" data-dismiss="modal"><i class="fa fa-minus-circle"></i> Annuler</a>
                <a class="btn btn-success" id="bt_addZoneModeOk"><i class="fa fa-check-circle"></i> Valider</a>
            </div>
        </div>
    </div>
</div>

<?php include_file('desktop', 'alarm', 'js', 'alarm'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>