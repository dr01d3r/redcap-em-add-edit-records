<?php
// Set the namespace defined in your config file
namespace ORCA\OrcaSearch;

// The next 2 lines should always be included and be the same in every module
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

require_once 'vendor/autoload.php';
require_once 'traits/REDCapUtils.php';

/**
 * Class OrcaSearch
 * @package ORCA\OrcaSearch
 */
class OrcaSearch extends AbstractExternalModule {
    use \ORCA\OrcaSearch\REDCapUtils;

    private static $smarty;
    public $timers = [];

    public function __construct()
    {
        parent::__construct();
        define("MODULE_DOCROOT", $this->getModulePath());
    }

    public function redcap_module_link_check_display($project_id, $link) {
        return true;
    }

    public function redcap_every_page_top($project_id) {
        global $lang;
        if ($this->getProjectSetting("replace_add_edit") === true) {
            ?>
            <script type='text/javascript'>
                if(typeof OrcaSearch === 'undefined') {
                    var OrcaSearch = {
                        addEditLinkText: "<?=$lang['bottom_62']?>",
                        orcaSearchURL: "<?=$this->getUrl('search.php')?>",
                        moduleLinkType: "add_edit_replace"
                    };
                }
            </script>
            <?php
            echo "<link rel='stylesheet' type='text/css' href='" . $this->getUrl('css/orca_search.css') . "' />";
            echo "<script src='" . $this->getUrl('js/orca_search.js') . "'></script>";
        }
    }

    public function initializeSmarty() {
        self::$smarty = new \Smarty();
        self::$smarty->setTemplateDir(MODULE_DOCROOT . 'templates');
        self::$smarty->setCompileDir(MODULE_DOCROOT . 'templates_c');
        self::$smarty->setConfigDir(MODULE_DOCROOT . 'configs');
        self::$smarty->setCacheDir(MODULE_DOCROOT . 'cache');
    }

    public function setTemplateVariable($key, $value) {
        self::$smarty->assign($key, $value);
    }

    public function displayTemplate($template) {
        self::$smarty->display($template);
    }

    public function addTime($key = null) {
        if ($key == null) {
            $key = "STEP " . count($this->timers);
        }
        $this->timers[] = [
            "label" => $key,
            "value" => microtime(true)
        ];
    }

    public function outputTimerInfo($showAll = false) {
        $initTime = null;
        $preTime = null;
        $curTime = null;
        foreach ($this->timers as $index => $timeInfo) {
            $curTime = $timeInfo;
            if ($preTime == null) {
                $initTime = $timeInfo;
            } else {
                $calcTime = round($curTime["value"] - $preTime["value"], 4);
                if ($showAll) {
                    echo "<p><i>{$timeInfo["label"]}: {$calcTime}</i></p>";
                }
            }
            $preTime = $curTime;
        }
        $calcTime = round($curTime["value"] - $initTime["value"], 4);
        echo "<p><i>Total Processing Time: {$calcTime} seconds</i></p>";
    }
}