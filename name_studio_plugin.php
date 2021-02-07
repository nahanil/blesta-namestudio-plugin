<?php

/**
 *  Blesta Name Studio domain suggestion plugin
 *  
 * @todo
 *   [NICE TO HAVE]
 *   - Confirm API Key is valid on settings change in /admin by making test request
 *   - Tidy up NameSpinnerController (most of it can probably be gutted and moved to ./models. Fat controllers smell)
 **/
class NameStudioPlugin extends Plugin {

    public function __construct() {
        $this->loadConfig(dirname(__FILE__) . DS . "config.json");
        Language::loadLang('name_studio', null, dirname(__FILE__) . DS . 'language' . DS);
        Language::loadLang('domain', null, PLUGINDIR . implode(DS, explode("/", "order/views/templates/standard/language")) . DS);
        Loader::loadComponents($this, array("Input", "Record"));
        Loader::loadModels($this, ['NameStudio.NameStudioUtil']);
    }

    public function getEvents() {
        return array(
            array(
                'event' => "Appcontroller.structure",
                'callback' => array("this", "injectHtml")
            )
        );
    }

    public function install($plugin_id) {
        Loader::loadModels($this, ['NameStudio.NameStudioSettings']);

        // Create a DB table for storing configuration settings
        try {
            $this->Record->
                setField('company_id', ['type' => 'int', 'size' => 10, 'unsigned' => true])->
                setField('key', ['type' => 'varchar', 'size' => 128])->
                setField('value', ['type' => 'text'])->
                setKey(['company_id', 'key'], 'primary')->
                create(NameStudioSettings::TABLE_SETTINGS, true);
        } catch (Exception $e) {
            // Error adding... no permission?
            $this->Input->setErrors(['db'=> ['create'=>$e->getMessage()]]);
            return;
        }

        // Insert default settings
        $settings = [
            'api_key' => '',
            'enabled_tlds' => 'com,net',
            'max_length'   => 63,
            'max_results'  => 20,
            'use_dashes'   => "auto",
            'use_numbers'  => "yes",
            'send_ip'      => "no",
            'filter_sensitive' => "no"
        ];

        $this->NameStudioSettings->update($settings);
    }

    public function upgrade($current_version, $plugin_id) {
        // Ensure new version is greater than installed version
        if (version_compare($this->config->version, $current_version) < 0) {
            $this->Input->setErrors(array(
                'version' => array(
                    'invalid' => "Downgrades are not allowed."
                )
            ));
            return;
        }
    }

    public function uninstall($plugin_id, $last_instance) {
        Loader::loadModels($this, ['NameStudio.NameStudioSettings']);
        
        if ($last_instance) {
            try {
                $this->Record->drop(NameStudioSettings::TABLE_SETTINGS);
            } catch (Exception $e) {
                // Error dropping... no permission?
                $this->Input->setErrors(['db'=> ['create'=>$e->getMessage()]]);
                return;
            }
        }
    }
    
    /**
     * Insert our preconfig_form.pdt to the domain ordering 'preconfig' page
     *
     * @param \Blesta\Core\Util\Events\Common\EventInterface $evt
     * @return void
     */
    public function injectHtml($event) {
        $params = $event->getParams();

        $isPreconfig  = ($params['controller'] == "config" && $params['action'] == "preconfig" && $params['portal'] == "client");
        $searchdomain = !empty($_POST['domain']) ? NameStudioUtil::getSld($_POST['domain']) : false;

        // Probably isn't the search domain selection page, let's do nothing further
        if (!($isPreconfig && $searchdomain)) {
            return;
        }

        // Don't run for domain transfers
        if (isset($_POST['transfer']) && $_POST['transfer']) {
            return;
        }

        $tlds = !empty($_POST['tlds']) ? $_POST['tlds'] : false;

        $view = new View("preconfig_form", "default");
        Loader::loadHelpers($view, ['Form']);
        $view->setDefaultView('plugins' . DS . 'name_studio' . DS);
        $view->set('searchdomain', $searchdomain);
        $view->set('tlds', $tlds);
        $view->set('orderform', NameStudioUtil::getOrderFormLabel());

        // Don't clobber `body_end`
        $rval = $event->getReturnValue();
        if (!isset($rval['body_end'])) {
            $rval['body_end'] = [];
        }
        $rval['body_end'][] = $view->fetch();
        return $event->setReturnValue($rval);
    }
}
