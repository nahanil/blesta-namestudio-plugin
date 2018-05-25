<?php

class NameStudioSettings extends NameStudioModel {

    const TABLE_SETTINGS = "namestudio_settings";
    
    /**
     * Returns all settings stored for the current company
     *
     * @return stdClass A stdClass object with member variables that as setting names and values as setting values
     */
    public function getAll() {
        $settings = new stdClass();
                
        $fields = ['namestudio_settings.key', 'namestudio_settings.value'];
        $results = $this->Record->select($fields)->from(self::TABLE_SETTINGS)
                ->where('namestudio_settings.company_id', '=', Configure::get('Blesta.company_id'))
                ->fetchAll();

        foreach ($results as $result) {
            // Munge some data before returning
            switch ($result->key) {
                // Arrays
                case 'enabled_tlds':
                    $settings->{$result->key} = explode(",", $result->value);
                    break;

                // Encrypted Values
                case 'api_key' : 
                    $settings->{$result->key} = $this->systemDecrypt($result->value);
                    break;

                // Booleans
                case 'send_ip' :
                case 'use_numbers' :
                case 'filter_sensitive' :
                    $settings->{$result->key} = $result->value == "yes" ? true : false;
                    break;
                
                // Everything else
                default :
                  $settings->{$result->key} = $result->value;
            }
        }
        return $settings;
    }

    /**
     * Updates a set of settings
     *
     * @param array $vars A key/value paired array of settings to update
     */
    public function update(array $vars) {
        $rules = [ ];

        $this->Input->setRules($rules);

        if ($this->Input->validates($vars)) {
            foreach ($vars as $key => $value) {
                // Munge some data before storage
                switch ($key) {
                    case "api_key" :
                        $value = $this->systemEncrypt($value);  
                    break;

                    default:
                        if (is_bool($value))
                            $value = $value ? "yes" : "no";

                        if (is_array($value))
                            $value = implode(",", $value);
                    break;
                }

                $fields = [
                    'key'   => $key,
                    'value' => $value,
                    'company_id' => Configure::get('Blesta.company_id')
                ];
                $res = $this->Record->duplicate('value', '=', $fields['value'])
                        ->insert(self::TABLE_SETTINGS, $fields);
            }
        }
    }

}
