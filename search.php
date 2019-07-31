<?php
/** @var \ORCA\OrcaSearch\OrcaSearch $module */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

$module->initializeSmarty();
$module->addTime();

$config = [
    "result_limit" => intval($module->getProjectSetting("search_limit")),
    "has_repeating_forms" => $Proj->hasRepeatingForms(),
    "instance_search" => $module->getProjectSetting("instance_search"),
    "show_instance_badge" => $module->getProjectSetting("show_instance_badge"),
    "auto_numbering" => $Proj->project["auto_inc_set"] === "1",
    "longitudinal" => $Proj->longitudinal,
    "multiple_arms" => $Proj->multiple_arms,
    "new_record_label" => $Proj->table_pk_label,
    "new_record_text" => $lang['data_entry_46'],
    "redcap_images_path" => APP_PATH_IMAGES,
    "module_version" => $module->VERSION,
    "new_record_url" => APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
        "pid" => $module->getPID(),
        "auto" => "1"
    ]),
    "include_dag" => false,
    "user_dag" => null,
    "groups" => [],
    "search_fields" => [],
    "display_fields" => [],
    "forms_and_fields" => [],
    "messages" => [],
    "errors" => []
];

$metadata = [
    "forms" => [],
    "fields" => [],
    "form_statuses" => [
        0 => "Incomplete",
        1 => "Unverified",
        2 => "Complete"
    ],
    "unstructured_field_types" => [
        "text",
        "textarea"
    ],
    "custom_dictionary_values" => [
        "yesno" => [
            "1" => "Yes",
            "0" => "No"
        ],
        "truefalse" => [
            "1" => "True",
            "0" => "False"
        ]
    ]
];

$debug = [];
$records = [];
$results = [];

$recordIds = null;
$recordCount = null;

/*
 * Build the Form/Field Metadata
 * This is necessary for knowing where to find record
 * values (i.e. repeating/non-repeating forms)
 *
 * TODO possibly add a config setting to specify how records across arms should be considered
 *   i.e. same person or not
 * ---
 * TODO achieve arms context (and repeating events)
 *   https://datatables.net/examples/basic_init/complex_header.html
 *   $Proj->longitudinal
 *   $Proj->multiple_arms
 *   $Proj->eventInfo   - events info including arm_num and other arm info
 *   $Proj->eventsForms - events and their forms
 *   $Proj->events      - arms and their events
 *
 */
foreach ($Proj->forms as $form_name => $form_data) {
    foreach ($form_data["fields"]  as $field_name => $field_label) {
        $metadata["fields"][$field_name] = [
            "form" => $form_name
        ];
    }
}
foreach ($Proj->eventsForms as $event_id => $event_forms) {
    foreach ($event_forms as $form_index => $form_name) {
        $metadata["forms"][$form_name][$event_id] = $Proj->eventInfo[$event_id];

        if (array_key_exists($event_id, $Proj->getRepeatingFormsEvents())) {
            if ($Proj->getRepeatingFormsEvents()[$event_id] === "WHOLE") {
                $metadata["forms"][$form_name][$event_id]["repeating"] = "event";
            } else if (array_key_exists($form_name, $Proj->getRepeatingFormsEvents()[$event_id])) {
                $metadata["forms"][$form_name][$event_id]["repeating"] = "form";
            }
        }
    }
}

if (!empty(\REDCap::getUserRights(USERID)[USERID]["group_id"])) {
    $config["user_dag"] = \REDCAP::getGroupNames(true, \REDCap::getUserRights(USERID)[USERID]["group_id"]);
}

foreach ($module->getSubSettings("search_fields") as $search_field) {
    if (empty($search_field["search_field_name"])) continue;

    $field_name = $search_field["search_field_name"];
    $field_type = $Proj->metadata[$field_name]["element_type"];
    $form_name = $Proj->metadata[$field_name]["form_name"];

    $config["forms_and_fields"][$form_name][$field_name]["search"] = true;

    if ($Proj->isFormStatus($field_name)) {
        $config["search_fields"][$field_name] = [
            "wildcard" => false,
            "value" => $Proj->forms[$form_name]["menu"] . " Status",
            "dictionary_values" => $metadata["form_statuses"]
        ];
    } else {
        $config["search_fields"][$field_name] = [
            "value" => $module->getDictionaryLabelFor($field_name)
        ];
        // override wildcard config in certain cases; otherwise, take what the user specified
        switch ($field_type) {
            case "select":
            case "radio":
                $config["search_fields"][$field_name]["wildcard"] = false;
                break;
            case "checkbox":
                $config["search_fields"][$field_name]["wildcard"] = true;
                break;
            default:
                $config["search_fields"][$field_name]["wildcard"] = $search_field["search_field_name_wildcard"];
                break;
        }
        // set structured values for display in search options
        switch ($field_type) {
            case "select":
            case "radio":
            case "checkbox":
                $config["search_fields"][$field_name]["dictionary_values"] =
                    $module->getDictionaryValuesFor($field_name);
                break;
            case "yesno":
            case "truefalse":
                $config["search_fields"][$field_name]["dictionary_values"] =
                    $metadata["custom_dictionary_values"][$field_type];
                break;
            default: break;
        }
    }
}

foreach ($module->getSubSettings("display_fields") as $display_field) {
    if (empty($display_field["display_field_name"])) continue;

    $field_name = $display_field["display_field_name"];
    $form_name = $Proj->metadata[$field_name]["form_name"];

    $config["forms_and_fields"][$form_name][$field_name]["display"] = true;

    if ($Proj->isFormStatus($field_name)) {
        $config["display_fields"][$field_name] = [
            "is_form_status" => true,
            "date_format" => false,
            "label" => $Proj->forms[$form_name]["menu"] . " Status"
        ];
    } else {
        $config["display_fields"][$field_name] = [
            "date_format" => $module->getDateFormatFromREDCapValidationType($field_name),
            "label" => $module->getDictionaryLabelFor($field_name),
        ];
    }
}

if ($module->getProjectSetting("include_dag_if_exists") === true && count($Proj->getGroups()) > 0) {
    $config["include_dag"] = true;
    $config["display_fields"]["redcap_data_access_group"] = [
        "label" => "Data Access Group"
    ];
    $config["groups"] = array_combine($Proj->getUniqueGroupNames(), $Proj->getGroups());
}

if ($config["auto_numbering"]) {
    // TODO this takes a long time and makes the module load slowly
    $config["new_record_auto_id"] = getAutoId();
}

$fieldValues = null;
if (isset($_POST["search-field"]) && isset($_POST["search-value"])) {
    $search_field = $_POST["search-field"];
    $search_value = $_POST["search-value"];
    if ($config["search_fields"][$search_field]["wildcard"] === true) {
        $search_value = "$search_value%";
    }
    $fieldValues[$search_field] = $search_value;

    if (empty($config["instance_search"])) {
        $config["instance_search"] = "LATEST";
        if ($config["has_repeating_forms"]) {
            // TODO this is set to only look at the first entry in the array, since the module doesn't yet support multiple search fields
            // TODO this is broken now - event needs to be added after form name
//            $search_field_key = key($fieldValues);
//            if ($metadata["forms"][$metadata["fields"][$search_field_key]["form"]]["repeating"] === true) {
//                $config["warnings"][] = "<b>" . $config["search_fields"][$search_field_key]["value"] . "</b> is on a repeating instrument, and the config setting <b>Which instances to search through</b> has not been set.  Using a default value of <b>Latest</b>.";
//            }
        }
    }

    $recordIds = [];
    try {
        $recordIds = $module->getProjectRecordIds($fieldValues, $config["instance_search"]);
    } catch (Exception $ex) {
        \REDCap::logEvent($ex->getMessage(), "", "", null, null, $module->getPID());
        $config["errors"][] = $ex->getMessage();
    }
    $recordCount = count($recordIds);
}

if ($recordCount === 0) {
    $config["messages"][] = "Search yielded no results.";
} else if ($recordCount != null && !empty($config["result_limit"]) && $recordCount > $config["result_limit"]) {
    $config["errors"][] = "Too many results found ($recordCount).  Please be more specific (limit {$config["result_limit"]}).";
} else if ($recordCount > 0) {
    $records = \REDCap::getData($module->getPID(), 'array', $recordIds, array_keys($config["display_fields"]), null, $config["user_dag"], false, $config["include_dag"]);
}

if (empty($config["search_fields"])) {
    $config["errors"][] = "Search fields not yet been configured.  Please go to the <b>" . $lang["global_142"] . "</b> area in the project sidebar to configure them.";
}
if (empty($config["display_fields"])) {
    $config["errors"][] = "Display fields not yet been configured.  Please go to the <b>" . $lang["global_142"] . "</b> area in the project sidebar to configure them.";
}

/*
 * Record Processing
 */
foreach ($records as $record_id => $record) { // Record
    $record_info = [
        "arms" => [],
        "links" => [],
        "data_full" => []
    ];
    // TODO new implementation of DAGs must be verified
    $redcap_data_access_group = null;
    foreach ($config["display_fields"] as $field_name => $field_info) {
        // don't handle DAG directly, it will be set in process of the first non-DAG field
        if ($field_name === "redcap_data_access_group") continue;

        // prep some form info
        $field_form_name = $metadata["fields"][$field_name]["form"];

        // initialize some helper variables/arrays
        $field_type = $Proj->metadata[$field_name]["element_type"];
        $field_value = null;

        foreach ($metadata["forms"][$field_form_name] as $event_id => $event_info) {
            $arm_num = $metadata["forms"][$field_form_name][$event_id]["arm_num"];
            $arm = $metadata["forms"][$field_form_name][$event_id]["arm_name"];

            // set the form_values array with the data we want to look at
            $repeat_key = null;
            switch ($event_info["repeating"]) {
                case "form": // individual, go to form key
                    $repeat_key = $field_form_name;
                case "event": // entire event, go to null key
                    foreach ($record["repeat_instances"][$event_id][$repeat_key] as $instance_key => $instance_info) {
                        // TODO we dont want to just pull all the latest values, we need to keep context of the entire form
                        //    maybe we can group display fields by form
                        if (isset($instance_info[$field_name])) {
                            $field_value = $instance_info[$field_name];
                            $redcap_data_access_group = $instance_info["redcap_data_access_group"];

                            if ($config["show_instance_badge"] === true) {
                                $record_info[$field_name]["badge"] = $instance_key;
                            }

//                            $record_info["data_full"][$arm_num]["repeat_instances"][$event_id][$repeat_key][$instance_key]["link"] =
//                                APP_PATH_WEBROOT . "DataEntry/index.php?" . http_build_query([
//                                    "pid" => $module->getPID(),
//                                    "id" => $record_id,
//                                    "event_id" => $event_id,
//                                    "page" => $field_form_name,
//                                    "instance" => $instance_key,
//                                    "arm" => $arm_num
//                                ]);
                            $record_info["data_full"][$arm_num]["repeat_instances"][$event_id][$repeat_key][$instance_key]["fields"][$field_name]["value"] = $field_value;
                            $debug["log"][$record_id][$arm][] = "$event_id || $field_form_name ($instance_key) || $field_name || $field_value";
                        }
                    }
                    break;
                default:  // non-repeating
                    if (isset($record[$event_id][$field_name])) {
                        $field_value = $record[$event_id][$field_name];
                        $redcap_data_access_group = $record[$event_id]["redcap_data_access_group"];

//                        $record_info["data_full"][$arm_num][$event_id]["fields"][$field_name]["value"] =
//                            APP_PATH_WEBROOT . "DataEntry/index.php?" . http_build_query([
//                                "pid" => $module->getPID(),
//                                "id" => $record_id,
//                                "event_id" => $event_id,
//                                "page" => $field_form_name,
//                                "arm" => $arm_num
//                            ]);
                        $record_info["data_full"][$arm_num][$event_id]["fields"][$field_name]["value"] = $field_value;

                        $debug["log"][$record_id][$arm][] = "$event_id || $field_form_name || $field_name || $field_value";
                    }
                    break;
            }

            if ($field_value !== null) {
                $record_info["arms"][$arm_num] = $arm;
            }
        }

        // special handling for dag
        if ($config["include_dag"] === true && !isset($record_info["redcap_data_access_group"])) {
            $record_info["redcap_data_access_group"]["value"] = $config["groups"][$redcap_data_access_group];
        }

        if ($field_name === $Proj->table_pk) {
            $parts = explode("-", $field_value);
            if (count($parts) > 1) {
                $record_info[$field_name]["__SORT__"] = implode(".", [$parts[0], str_pad($parts[1], 10, "0", STR_PAD_LEFT)]);
            } else {
                $record_info[$field_name]["__SORT__"] = $field_value;
            }
        }

        if ($field_info["is_form_status"] === true) {
            // special value handling for form statuses
            $field_value = $metadata["form_statuses"][$field_value];
        } else if ($field_info["date_format"] !== false) {
            // the database value for date works well for sorting
            // so apply a sort value before further formatting a date string
            $record_info[$field_name]["__SORT__"] = $field_value;
            $field_value = $module->getFormattedDateString($field_value, $field_info["date_format"]);
        } else if (!in_array($field_type, $metadata["unstructured_field_types"])) {
            switch ($field_type) {
                case "select":
                case "radio":
                    $field_value = $module->getDictionaryValuesFor($field_name)[$field_value];
                    break;
                case "checkbox":
                    $temp_field_array = [];
                    $field_value_dd = $module->getDictionaryValuesFor($field_name);
                    foreach ($field_value as $field_value_key => $field_value_value) {
                        if ($field_value_value === "1") {
                            $temp_field_array[$field_value_key] = $field_value_dd[$field_value_key];
                        }
                    }
                    $field_value = $temp_field_array;
                    break;
                case "yesno":
                case "truefalse":
                    $field_value = $metadata["custom_dictionary_values"][$Proj->metadata[$field_name]["element_type"]][$field_value];
                    break;
                default: break;
            }
        }

        // additional formatting for date display


        /*
         * Highlighting
         * - selected search field
         * - is a field type that is unstructured
         * - was selected as a wildcard in the config
         */
        if ($field_name === $_POST["search-field"] && in_array($field_type, $metadata["unstructured_field_types"]) && $config["search_fields"][$field_name]["wildcard"]) {
            $match_index = strpos(strtolower($field_value), strtolower($_POST["search-value"]));
            $match_value = substr($field_value, $match_index, strlen($_POST["search-value"]));
            if ($match_index !== false) {
                $field_value = str_replace($match_value, "<span class='orca-search-content'>{$match_value}</span>", $field_value);
            } else {
                // TODO some way to indicate to the user that the matching content is not on the latest instance of this value
                // TODO we only get here if it is unstructured, wildcarded, and not found...this is not a catch all for the above statment
            }
        }

        $record_info[$field_name]["value"] = $field_value;
    }

    // create dashboard links based on the number of arms where data exists
    foreach ($record_info["arms"] as $arm_num => $arm_name) {
        $link_label = "Arm " . $arm_num . ": " . $arm_name;
        $record_info["links"][$link_label] = APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
            "pid" => $module->getPID(),
            "id" => $record_id,
            "arm" => $arm_num
        ]);
    }

    // add record data to the full dataset
    $results[$record_id] = $record_info;
}

/*
 * Push all the results to Smarty templates for rendering
 */
if (true) { // TODO this will be replaced with an 'enable debugging' setting
//    $debug["config"] = $config;
//    $debug["metadata"] = $metadata;
//    $debug["records"] = $records;
    $debug["results"] = $results;
    if ((isset($debug) && !empty($debug))) {
        $module->setTemplateVariable("debug", print_r($debug, true));
    }
}

$module->setTemplateVariable("config", $config);

if (!empty($_POST)) {
    $module->setTemplateVariable("search_info", $_POST);
}

$module->setTemplateVariable("data", $results);

echo "<link rel='stylesheet' type='text/css' href='" . $module->getUrl('css/orca_search.css') . "' />";

if (version_compare(REDCAP_VERSION, "8.7.0", ">=")) {
    $module->displayTemplate('bs4/orca_search.tpl');
} else {
    $module->displayTemplate('bs3/orca_search.tpl');
}

$module->addTime();
$module->outputTimerInfo();

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';