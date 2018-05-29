{* Smarty *}
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.12/css/dataTables.bootstrap.min.css">
<style>
    #{$config["table_id"]} > thead > tr > th {
        text-overflow: ellipsis;
    }

    #{$config["table_id"]} > tbody > tr,
    #{$config["table_id"]} > tbody > tr > td {
        position: relative;
        vertical-align: middle;
    }

    #{$config["table_id"]} {
        display: none;
    }

    #{$config["table_id"]}_ph {
        font-size: 20px;
        font-style: italic;
        padding: 35px;
    }

    .dataTables_wrapper > div.row:first-child {
        padding: 10px 10px 0px 10px !important;
    }

    .dataTables_wrapper > div.row:last-child {
        padding: 0px 10px 10px 10px !important;
    }

    #{$config["table_id"]} .jqbuttonmed {
        white-space: nowrap;
    }

    .orca-search-content {
        font-weight: bold;
        color: blue;
    }

    #search-value, .orca-search-field-select {
        display: none;
    }

    .alert {
        border: 1px solid transparent !important;
        /*margin-bottom: 10px;*/
    }
    .alert-danger {
        border-color: #ebccd1 !important;
    }
    .alert-warning {
        border-color: #faebcc !important;
    }
    .alert-info {
        border-color: #bce8f1 !important;
    }
    .alert-success {
        border-color: #d6e9c6 !important;
    }
</style>
<script src="https://cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.12/js/dataTables.bootstrap.min.js"></script>

{foreach from=$config["messages"] item=message}
    <div class="alert alert-info alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <b>Info:</b> {$message}
    </div>
{/foreach}

{foreach from=$config["errors"] item=error}
    <div class="alert alert-danger alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <b>Error:</b> {$error}
    </div>
{/foreach}

<div class="panel panel-default">
    <!-- Default panel contents -->
    <div class="panel-heading">
        <form method="post" action="">
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="search-field">Select Search Field</label><br/>
                    <select name="search-field" id="search-field" class="form-control">
                        {foreach from=$config["search_fields"] key=field_name item=field_data}
                            {if $field_name == $search_info["search-field"]}
                                <option selected="true" value="{$field_name}">{$field_data["value"]}</option>
                            {else}
                                <option value="{$field_name}">{$field_data["value"]}</option>
                            {/if}
                        {/foreach}
                    </select>
                </div>
                {if $config["auto_numbering"]}
                    <div class="form-group col-md-6">
                        <label>New Record</label><br/>
                        <button id="orca-search-new-record" type="button" class="btn btn-default form-control">{$config["new_record_text"]}</button>
                    </div>
                {else}
                    <div class="col-md-6">
                        <label>New Record</label><br/>
                        <div class="input-group">
                            <input id="orca-search-new-record-id" type="text" class="form-control" placeholder="New {$config["new_record_label"]}" />
                            <span class="input-group-btn">
                                <button id="orca-search-new-record" type="button" class="btn btn-default">{$config["new_record_text"]}</button>
                            </span>
                        </div>
                    </div>
                {/if}
            </div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="search-field">Search Text</label><br/>
                    {if !empty($search_info["search-value"])}
                        <input id="search-value" name="search-value" type="text" class="form-control" value="{$search_info["search-value"]}" />
                    {else}
                        <input id="search-value" name="search-value" type="text" class="form-control" />
                    {/if}
                    {foreach from=$config["search_fields"] key=field_name item=field_data}
                        {if isset($field_data["dictionary_values"])}
                            <select class="form-control orca-search-field-select" id="{$field_name}">
                                {foreach from=$field_data["dictionary_values"] key=dd_key item=dd_value}
                                    {if $field_name == $search_info["search-field"] && $search_info["search-value"] == $dd_key}
                                        <option selected="true" value="{$dd_key}">{$dd_value}</option>
                                    {else}
                                        <option value="{$dd_key}">{$dd_value}</option>
                                    {/if}
                                {/foreach}
                            </select>
                        {/if}
                    {/foreach}
                </div>
                <div class="form-group col-md-6">
                    {if $config["result_limit"] > 0}
                        <label>Limit: <i style="font-weight: normal;">{$config["result_limit"]}</i></label>
                    {else}
                        <label>&nbsp;</label><br/>
                    {/if}
                    <button id="orca-search" type="button" class="btn btn-info form-control">Search</button>
                </div>
            </div>
        </form>
        {if $config["has_repeating_forms"]}
            {if $config["instance_search"] === "LATEST"}
                <b>Note:</b> <i>Search will only return matches that occur within the <b>latest</b> instance of a form.</i>
            {else}
                <b>Note:</b> <i>Search will return matches that occur in <b>any</b> instance of a form.</i>
            {/if}
        {/if}
        {if !empty($config["user_dag"])}
            <b>Note:</b> <i>Search results will be limited to the <b>{$config["groups"][$config["user_dag"]]}</b> Data Access Group.</i>
        {/if}
    </div>
    <div>
        <div id="{$config["table_id"]}_ph">
            Loading data. Please wait...
        </div>
        <div class="table-responsive">
            <table id="{$config["table_id"]}" class="table table-bordered table-condensed table-hover">
                <thead>
                <tr>
                    {foreach from=$config["display_fields"] key=col_name item=col_value}
                        <th class="header">{$col_value["label"]}</th>
                    {/foreach}
                    <th class="header">Record Home</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$data key=record_id item=record}
                    <tr>
                        {foreach from=$config["display_fields"] key=col_name item=col_value}
                            <td{if $record[$col_name]["__SORT__"]} data-sort="{$record["__SORT__"]}"{/if}>{$record[$col_name]}</td>
                        {/foreach}
                        <td>
                            <a href="{$record["dashboard_url"]}" class="jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only" role="button">
                                <span class="ui-button-text">
                                    <span class="ui-button-text">
                                        <span class="glyphicon glyphicon-edit"></span>&nbsp;Open
                                    </span>
                                </span>
                            </a>
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>

{if $debug}
    <pre>{$debug}</pre>
{/if}

<script type="text/javascript">
    $(function () {
        function setSearchControl(clearValue) {
            $(".orca-search-field-select").hide();
            $("#search-value").hide();

            if (clearValue === true) {
                $("#search-value").val('');
            }

            var val = $("#search-field").val();
            if ($("#" + val).length > 0) {
                $("#" + val).show().change();
            } else {
                $("#search-value").show();
            }
        }
        var table = $("#{$config["table_id"]}").DataTable({
            pageLength: 50,
            initComplete: function () {
                $("#{$config["table_id"]}").css('width', '100%').show();
                $("#{$config["table_id"]}_ph").hide();
            }
        });

        $("input[type='search']").on("keydown keypress", function (event) {
            if (event.which === 8) {
                table.draw();
                event.stopPropagation();
            }
        });

        if ($("#search-value").val().length == 0) {
            $("#search-value").focus();
        }

        $("body").on("click", "#orca-search-new-record", function() {
            {if $config["auto_numbering"]}
                window.location.href = '{$config["new_record_url"]}' + '&id=' + '{$config["new_record_auto_id"]}' + addGoogTrans();
            {else}
                var refocus = false;
                var idval = trim($('#orca-search-new-record-id').val());
                if (idval.length < 1) {
                    return;
                }
                if (idval.length > 100) {
                    refocus = true;
                    alert('The value entered must be 100 characters or less in length');
                }
                if (refocus) {
                    setTimeout(function(){ document.getElementById('orca-search-new-record-id').focus(); },10);
                } else {
                    $('#orca-search-new-record-id').val(idval);
                    idval = $('#orca-search-new-record-id').val();
                    idval = idval.replace(/&quot;/g,''); // HTML char code of double quote
                    var validRecordName = recordNameValid(idval);
                    if (validRecordName !== true) {
                        $('#orca-search-new-record-id').val('');
                        alert(validRecordName);
                        $('#orca-search-new-record-id').focus();
                        return false;
                    }
                    window.location.href = '{$config["new_record_url"]}' + '&id=' + idval + addGoogTrans();
                }
            {/if}
        });

        $("body").on("click", "#orca-search", function() {
            document.forms[0].submit();
        });
        $("body").on("keypress", "#search-value", function(e) {
            if (e.which == 13) {
                document.forms[0].submit();
            }
        });

        $("#search-field").change(function() {
            setSearchControl(true);
        });

        $(".orca-search-field-select").change(function() {
            $("#search-value").val($(this).val());
        });

        // disable form resubmit on refresh/back
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }

        setSearchControl(false);
    });
</script>