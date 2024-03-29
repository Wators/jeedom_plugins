<?php
if (!isConnect()) {
    throw new Exception('401 - Unauthorized access to page');
}
if (init('object_id') == '') {
    $_GET['object_id'] = $_SESSION['user']->getOptions('defaultDashboardObject', 'global');
}
$object = object::byId(init('object_id'));
if (!is_object($object)) {
    $object = object::rootObject();
}
if (!is_object($object)) {
    throw new Exception('Aucun objet racine trouvé');
}
$energy = energy::getObjectData($object->getId());

sendVarToJs('datas', energy::sanitizeForChart($energy));
?>

<div class="row">
    <div class="col-lg-2">
        <div class="bs-sidebar affix">
            <ul id="ul_object" class="nav nav-list bs-sidenav">
                <li class="nav-header">Liste objects </li>
                <li class="filter" style="margin-bottom: 5px;"><input class="form-control" class="filter form-control" placeholder="Rechercher" style="width: 100%"/></li>
                <?php
                if (init('object_id') == 'global') {
                    echo '<li class="cursor li_object active"><a href="index.php?v=d&m=energy&p=panel&object_id=global">Global</a></li>';
                } else {
                    echo '<li class="cursor li_object"><a href="index.php?v=d&m=energy&p=panel&object_id=global">Global</a></li>';
                }
                $allObject = object::buildTree();
                foreach ($allObject as $object_li) {
                    $margin = 15 * $object_li->parentNumber();
                    if ($object_li->getId() == init('object_id')) {
                        echo '<li class="cursor li_object active" ><a href="index.php?v=d&m=energy&p=panel&object_id=' . $object_li->getId() . '" style="position:relative;left:' . $margin . 'px;">' . $object_li->getName() . '</a></li>';
                    } else {
                        echo '<li class="cursor li_object" ><a href="index.php?v=d&m=energy&p=panel&object_id=' . $object_li->getId() . '" style="position:relative;left:' . $margin . 'px;">' . $object_li->getName() . '</a></li>';
                    }
                }
                ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-10">
        <div class="row">
            <div class="col-lg-6">
                <legend>Actuellement sur <?php echo $object->getName() ?></legend>
                <form class="form-horizontal">
                    <fieldset>
                        <div class="form-group">
                            <label class="col-lg-3 control-label" style="font-size: 1.3em;">Puissance</label>
                            <div class="col-lg-3">
                                <span class='label label-success' style="font-size: 1.3em;"><?php echo round($energy['real']['power'], 2) ?> W</span>
                            </div>
                        </div>

                        <?php
                        foreach ($energy['category'] as $category) {
                            echo '<div class="form-group">';
                            echo '<label class="col-lg-3 control-label">' . $category['name'] . '</label>';
                            echo '<div class="col-lg-3">';
                            echo '<span class="label label-success">' . round($category['data']['real']['power'], 2) . ' W</span>';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                        <div class="form-group">
                            <label class="col-lg-3 control-label" style="font-size: 1.3em;">Consommation</label>
                            <div class="col-lg-3">
                                <span class='label label-primary' style="font-size: 1.3em;"><?php echo round($energy['real']['consumption'], 2) ?> kWh</span>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="col-lg-6">
                <legend>Historique sur <?php echo $object->getName() ?></legend>
                <div id='div_graphGlobalPower'></div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <legend>Consommation par objet sur <?php echo $object->getName() ?></legend>
                <div id='div_graphDetailConsumptionByObject'></div>
            </div>
            <div class="col-lg-6">
                <legend>Puissance par objet sur <?php echo $object->getName() ?></legend>
                <div id='div_graphDetailPowerByObject'></div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <legend>Consommation par catégorie sur <?php echo $object->getName() ?></legend>
                <div id='div_graphDetailConsumptionByCategorie'></div>
            </div>
            <div class="col-lg-6">
                <legend>Puissance par catégorie sur <?php echo $object->getName() ?></legend>
                <div id='div_graphDetailPowerByCategorie'></div>
            </div>
        </div>
    </div>

</div>

<?php include_file('desktop', 'panel', 'js', 'energy'); ?>