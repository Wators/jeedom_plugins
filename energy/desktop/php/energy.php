<?php
if (!isConnect('admin')) {
    throw new Exception('Error 401 Unauthorized');
}
?>
<div class="row">
    <div class="col-lg-2">
        <div class="bs-sidebar affix">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav fixnav">
                <li class="nav-header">Liste des équipements énergetiques</li>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="Rechercher" style="width: 100%"/></li>
                <?php
                foreach (eqLogic::byCategorie('energy') as $eqLogic) {
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
                    <label class="col-lg-2 control-label">Nom de l'équipement</label>
                    <div class="col-lg-3">
                        <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                        <input type="text" class="eqLogicAttr form-control" data-l1key="object_id" style="display : none;" />
                        <input type="text" class="energyAttr form-control" data-l1key="eqLogic_id" style="display : none;" />
                        <input type="text" class="energyAttr form-control" data-l1key="id" style="display : none;" />
                        <input type="text" class="eqLogicAttr form-control input-sm" data-l1key="name" disabled />
                    </div>
                </div>


                <div class="form-group">
                    <label class="col-lg-2 control-label">Catégorie énergétique</label>
                    <div class="col-lg-8">
                        <label class="checkbox-inline">
                            <input type="checkbox" class="energyAttr" data-l1key="category" data-l2key="hifi" /> Hifi son vidéo
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" class="energyAttr" data-l1key="category" data-l2key="heating" /> Chauffage
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" class="energyAttr" data-l1key="category" data-l2key="appliances" /> Électroménager
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" class="energyAttr" data-l1key="category" data-l2key="light" /> Lumière
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-lg-2 control-label">Position par raport à l'objet parent</label>
                    <div class="col-lg-3">
                        <select class="energyAttr form-control input-sm" data-l1key="options" data-l2key="positionRelative">
                            <option value='partial'>Partiel</option>
                            <option value='total'>Total</option>
                        </select>
                    </div>
                </div>

                <legend>Puissance</legend>
                <div class='alert alert-warning'>La/les donnée(s) utilisée(s) pour le calcul doivent être historisée(s)</div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">Formule</label>
                    <div class="col-lg-5">
                        <input type="text" class="energyAttr form-control input-sm" data-l1key="power" />
                    </div>
                    <div class="col-lg-1">
                        <a class="btn btn-default btn-sm listCmdInfo" data-type="power"><i class="fa fa-list-alt"></i></a>
                    </div>
                </div>

                <legend>Consommation</legend>
                <div class="form-group">
                    <label class="col-lg-2 control-label">Formule</label>
                    <div class="col-lg-5">
                        <input type="text" class="energyAttr form-control input-sm" data-l1key="consumption" />
                    </div>
                    <div class="col-lg-1">
                        <a class="btn btn-default btn-sm listCmdInfo" data-type="consumption"><i class="fa fa-list-alt"></i></a>
                    </div>
                </div>

            </fieldset> 
        </form>

        <form class="form-horizontal">
            <fieldset>
                <div class="form-actions">
                    <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> Sauvegarder</a>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<?php include_file('desktop', 'energy', 'js', 'energy'); ?>