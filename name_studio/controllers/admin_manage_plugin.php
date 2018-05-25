<?php

class AdminManagePlugin extends AppController
{
    /**
     * Performs necessary initialization
     */
    private function init() {
        // Require login
        $this->parent->requireLogin();

        Language::loadLang('name_studio', null, PLUGINDIR . 'name_studio' . DS . 'language' . DS);
        $this->view->setView(null, 'NameStudio.default');

        // Set the page title
        $this->parent->structure->set(
            'page_title',
            Language::_(
                'NameStudio.admin_' . Loader::fromCamelCase($this->action ? $this->action : 'index') . '.page_title',
                true
            )
        );
    }

    /**
     * Returns the view to be rendered when managing this plugin
     */
    public function index() {
        $this->uses(['NameStudio.NameStudioSettings']);
        $this->init();

        if (!empty($this->post)) {
            $update = [
                'api_key'          => $this->post['api_key'],
                'enabled_tlds'     => $this->post['tld'],
                'max_length'       => $this->post['max_length'],
                'max_results'      => $this->post['max_results'],
                'use_dashes'       => $this->post['use_dashes'],
                'send_ip'          => $this->Html->ifSet($this->post['send_ip']) == "on" ? "yes" : "no",
                'use_numbers'      => $this->Html->ifSet($this->post['use_numbers']) == "on" ? "yes" : "no",
                'filter_sensitive' => $this->Html->ifSet($this->post['filter_sensitive']) == "on" ? "yes" : "no",
            ];
            $this->NameStudioSettings->update($update);

            if (($errors = $this->NameStudioSettings->errors())) {
                $this->parent->setMessage('error', $errors);
            } else {
                $this->parent->setMessage('message', Language::_('NameStudio.settings_updated', true));
            }
        }

        $settings = $this->NameStudioSettings->getAll();
        $plugin_id = $this->get[0];
        $api_key  = $settings->api_key;
        $enabled_tlds = $settings->enabled_tlds;
        
        // TODO: Confirm which TLDs can be returned with an availability status!
        // The documentation on this topic is kinda flakey :(
        $available_tlds = ["com", "net", "info", "cc", "tv", "name", "コム", "大拿", "点看", "닷넷", "닷컴"];

        // Set the view to render for all actions under this controller
        return $this->partial(
            'admin_manage_plugin',
             array_merge(compact(['plugin_id', 'api_key', 'enabled_tlds', 'available_tlds']), json_decode(json_encode($settings), TRUE))
        );
    }

}
