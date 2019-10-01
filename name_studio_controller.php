<?php

require_once(dirname(__FILE__) . DS . "api" . DS . "verisign_name_studio.php");

class NameStudioController extends AppController {
    public function preAction() {
        parent::preAction();
        $this->structure->setDefaultView(APPDIR);
  
        Language::loadLang('name_studio', null, dirname(__FILE__) . DS . 'language' . DS);
        Language::loadLang('domain', null, PLUGINDIR . implode(DS, explode("/", "order/views/templates/standard/language")) . DS);

        // Load plugin settings
        $this->uses(['NameStudio.NameStudioSettings', 'NameStudio.NameStudioUtil', 'PackageGroups']);
        $this->settings = $this->NameStudioSettings->getAll();

        // Override default view directory
        $this->view->view = "default";
    }

    /**
     * Convenience wrapper for outputAsJson for use in child controllers
     *
     * @return boolean This method always returns 'false'
     */
    protected function renderJson() {
      call_user_func_array([$this, "outputAsJson"], func_get_args());
      return false;
    }
}
