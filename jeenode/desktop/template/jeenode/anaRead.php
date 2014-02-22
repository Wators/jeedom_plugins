<?php
require_once(dirname(__FILE__) . '/../../../../../core/php/core.inc.php');
include_file('core', 'authentification', 'php');

if (!isConnect()) {
    throw new Exception('401 Unauthorized');
}
?>

<div portNumber="#portNumber#">
    <div class="cmd anaRead">
        <input type="text" class="cmdAttr form-control" key="id" value="" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="type" value="info" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="subType" value="other" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="configuration" subkey="mode" value="?" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="configuration" subkey="type" value="a" style="display: none;"/>
        <div class="form-group">
            <label class="col-lg1 control-label" >Nom</label>
            <div class="col-lg-2">
                <input type="text" class="cmdAttr form-control" key="name" value="Analogique"/>
            </div>
            <label class="col-lg-1 control-label" >Historiser</label>
            <div class="col-lg-1">
                <input class="cmdAttr form-control" key="isHistorized" type="checkbox" /> 
            </div>
            <label class="col-lg-1 control-label" >Evenement seulement</label>
            <div class="col-lg-1">
                <input class="cmdAttr form-control" key="eventOnly" type="checkbox" />
            </div>

            <label class="col-lg-1 control-label" >Formule de calcul</label>
            <div class="col-lg-2">
                <input type="text" class="cmdAttr form-control" key="configuration" subkey="calcul" value="#V#" />
            </div>
            <label class="col-lg-1 control-label" >Unité</label>
            <div class="col-lg-1">
                <input type="text" class="cmdAttr form-control" key="unite" value=""/>
            </div>

            <label class="col-lg-1 control-label" >Valeur minimum</label>
            <div class="col-lg-1">
                <input type="text" class="cmdAttr form-control" key="display" subkey="minValue" value="0"/>
            </div>
            <label class="col-lg-1 control-label" >Valeur maximum</label>
            <div class="col-lg-1">
                <input type="text" class="cmdAttr form-control" key="display" subkey="maxValue" value="255"/>
            </div>
        </div>
    </div>

    <script>
        var cmdData = ('#cmdData#' != '') ? jQuery.parseJSON('#cmdData#') : null;
        if (cmdData != null) {
            for (var i in cmdData) {
                var cmd = null;
                if (cmdData[i].configuration.type == 'a') {
                    if (cmdData[i].configuration.mode == '?') {
                        cmd = $('div[portNumber=#portNumber#] .anaRead.cmd');
                    }
                }
                if (cmd != null) {
                    cmd.attr('enable', 1);
                    for (var key in cmdData[i]) {
                        if (is_array(cmdData[i][key]) || is_object(cmdData[i][key])) {
                            for (var subkey in cmdData[i][key]) {
                                cmd.find('.cmdAttr[key="' + key + '"][subkey="' + subkey + '"]').value(cmdData[i][key][subkey]);
                            }
                        } else {
                            cmd.find('.cmdAttr[key="' + key + '"]').value(cmdData[i][key]);
                        }
                    }
                }
            }
        }
    </script>

</div>