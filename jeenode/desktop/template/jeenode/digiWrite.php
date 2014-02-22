<?php
require_once(dirname(__FILE__) . '/../../../../../core/php/core.inc.php');
include_file('core', 'authentification', 'php');

if (!isConnect()) {
    throw new Exception('401 Unauthorized');
}
?>

<div portNumber="#portNumber#">
    <div class="form-group cmd digiWrite set1">
        <input type="text" class="cmdAttr form-control" key="id" value="" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="eventOnly" value="0" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="unite" value="" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="isHistorized" value="0" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="type" value="action" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="subType" value="binary" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="configuration" subkey="mode" value="!" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="configuration" subkey="type" value="d" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="configuration" subkey="value" value="1" style="display: none;"/>

        <label class="col-lg-2 control-label" >Nom écriture "1"</label>
        <div class="col-lg-2">
            <input type="text" class="cmdAttr form-control" key="name" value="On"/>
        </div>
    </div>
    <div class="form-group cmd digiWrite set0">
        <input type="text" class="cmdAttr form-control" key="id" value="" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="eventOnly" value="0" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="unite" value="" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="isHistorized" value="0" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="type" value="action" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="subType" value="binary" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="configuration" subkey="mode" value="!" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="configuration" subkey="type" value="d" style="display: none;"/>
        <input type="text" class="cmdAttr form-control" key="configuration" subkey="value" value="0" style="display: none;"/>

        <label class="col-lg-2 control-label" >Nom écriture "0"</label>
        <div class="col-lg-2">
            <input type="text" class="cmdAttr form-control" key="name" value="Off"/>
        </div>
    </div>
</div>



<script>
    var cmdData = ('#cmdData#' != '') ? jQuery.parseJSON('#cmdData#') : null;
    if (cmdData != null) {
        for (var i in cmdData) {
            var cmd = null;
            if (cmdData[i].configuration.type == 'd') {
                if (cmdData[i].configuration.mode == '!') {
                    if (cmdData[i].configuration.value == '1') {
                        cmd = $('div[portNumber=#portNumber#] .digiWrite.cmd.set1');
                    }
                    if (cmdData[i].configuration.value == '0') {
                        cmd = $('div[portNumber=#portNumber#] .digiWrite.cmd.set0');
                    }
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