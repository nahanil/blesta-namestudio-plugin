<?php

/**
 * Class NameSpinner
 */
class NameSpinner extends NameStudioController
{
    public function index()
    {
        $this->sld = !empty($this->post['searchdomain'])
            ? NameStudioUtil::getSld($this->post['searchdomain'])
            : false;

        $this->order_form_name = !empty($this->post['orderform']) ? $this->post['orderform'] : false;

        // Verify we have the data we need
        if (empty($this->post) || !$this->sld || !$this->order_form_name) {
            //header
            return $this->renderJson([
                'ok'  => false,
                'error' => [
                    'code' => 400,
                    'message' => "Invalid request"
                ]
            ]);
        }

        // Suggest default TLDs if the user didn't select one in the order form
        $tlds = !empty($this->post['tlds'])
            ? array_merge($this->post['tlds'], $this->settings->enabled_tlds)
            : $this->settings->enabled_tlds;

        // Perform the API request
        # TODO: This _could_ be cached for some short period of time..
        $api = new VerisignNameStudio($this->settings->api_key);
        $reply = $api->suggest($this->sld, [
            'tlds' => implode(",", $tlds),
            'sensitive-content-filter' => $this->settings->filter_sensitive,
            'use-numbers' => $this->settings->use_numbers,
            'use-dashes'  => $this->settings->use_dashes,
            'max-length'  => $this->settings->max_length,
            'max-results' => $this->settings->max_results,
            'ip-address'  => $this->settings->send_ip ? $_SERVER['REMOTE_ADDR'] : null,
            // TODO:
            //'use-idns'    => true,
            //'lang'        => "eng",  // eng/spa/ita/jpn/tur/chi/ger/por/fre/kor/vie/dut
            //'lat-lng'     => null,   // Optional
            //'include-registered' => false,
            //'include-suggestion-type'  => false,
        ]);

        // Check if the API request was successful
        if (!$reply->ok()) {
            return $this->renderJson([
                "ok" => false,
                "error" => $response->error()
            ]);
        }

        $response = $reply->response();

        // Don't include suggestions that match what Blesta already searched for
        $blesta_searched = [];
        if (!empty($this->post['tlds'])) {
            foreach ($this->post['tlds'] as $tld) {
                $blesta_searched[] = $this->sld . $tld;
            }
        }

        // Format our response
        $result = [];
        foreach ($response->results as $suggestion) {
            // The API sometimes returns 'punyName' for unicode domain/tlds
            $name = isset($suggestion->punyName)
                ? strtolower($suggestion->punyName)
                : strtolower($suggestion->name);

            // Don't suggest names that were in the main Blesta search
            if (in_array($name, $blesta_searched)) {
                continue;
            }

            // Don't suggest unavailable names
            if ($suggestion->availability != "available") {
                continue;
            }

            // Punycode handling is fkn ugly :(
            $result[] = [
                'name'        => $name,
                'displayName' => $suggestion->name,
                'available'   => ($suggestion->availability == "available"),
                'terms'       => $this->getPricingTermsForDomain($name),
            ];
        }

        // Send response
        $order_form = $this->getOrderForm();
        return $this->renderJson([
            'ok'      => true,
            'results' => $result,
            'groupId' => $order_form->meta->domain_group
        ]);
    }

    private function getPricingTerms()
    {
        if (isset($this->pricing_terms)) {
            return $this->pricing_terms;
        }

        $tlds = $this->getTldsForForm();

        // Load currency
        $cart_name =  Configure::get('Blesta.company_id') . '-' . $this->order_form_name;
        $this->components(['SessionCart' => [$cart_name, $this->Session]]);
        $currency = $this->SessionCart->getData('currency');

        $pricing = [];
        foreach (array_keys($tlds) as $tld) {
            //if (!isset($tlds["." . $tld])) { continue; }
            $pack = $tlds[$tld]; ////$tlds["." . $tld];

            if ($pack) {
                $pack[0] = $this->updatePackagePricing($pack[0], $currency);

                $pricing[$tld] = new stdClass();
                $pricing[$tld]->package = $pack[0];
                $pricing[$tld]->group = $pack[1];
            }
        }

        $this->pricing_terms = $pricing;
        return $pricing;
    }

    protected function getPricingTermsForDomain($domain)
    {
        $periods = $this->getPricingPeriods();
        $terms   = $this->getPricingTerms();

        // Get the TLD
        $tld = NameStudioUtil::getTld($domain, true);

        if (!isset($terms[$tld])) {
            return null;
        }

        $pack = $terms[$tld];

        $prices = [];
        foreach ($pack->package->pricing as $price) {
            $prices[] = [
                $price->id,
                Language::_(
                    'Domain.lookup.term',
                    true,
                    $price->term,
                    (
                        $price->term == 1
                            ? $this->Html->ifSet($periods[$price->period])
                            : $this->Html->ifSet($periods[$price->period . '_plural'])
                    ),
                    $this->CurrencyFormat->format($price->price, $price->currency)
                )
            ];
        }

        return $prices;
    }

    private function getPricingPeriods()
    {
        if (isset($this->pricingPeriods)) {
            return $this->pricingPeriods;
        }

        if (!isset($this->Packages)) {
            Loader::loadModels($this, ['Packages']);
        }

        // Set language for periods
        $periods = $this->Packages->getPricingPeriods();
        foreach ($this->Packages->getPricingPeriods(true) AS $period => $lang) {
            $periods[$period . '_plural'] = $lang;
        }

        $this->pricingPeriods = $periods;
        return $periods;
    }

    private function getOrderForm()
    {
        if (isset($this->_order_form)) {
            return $this->_order_form;
        }

        $order_form = $this->Record->select()
            ->from("order_forms")
            ->where("company_id", "=", Configure::get('Blesta.company_id'))
            ->where("label", "=", $this->order_form_name)
            ->limit(1)
            ->fetch();

        if (!$order_form) {
            return;
        }

        // Load order form meta
        $meta_rows = $this->Record->select()
        ->from("order_form_meta")
        ->where("order_form_id", "=", $order_form->id)
        ->fetchAll();

        $order_form->meta = new stdClass;
        foreach ($meta_rows as $row) {
            $order_form->meta->{$row->key} = $row->value;
        }

        $this->_order_form = $order_form;
        return $this->_order_form;
    }

    /**
     * @todo Don't load _all_ available TLDs, only those that have suggestions
     * @return void
     */
    private function getTldsForForm()
    {
        if (isset($this->tlds)) {
            return $this->tlds;
        }

        if (!isset($this->Record)) {
            Loader::loadModels($this, ['Record']);
        }

        if (!isset($this->Packages)) {
            Loader::loadModels($this, ['Packages']);
        }

        // Load order form details
        $order_form = $this->getOrderForm();

        if (!$order_form) {
            return null;
        }

        $tlds = [];

        $group = new stdClass();
        $group->order_form_id = $order_form->id;
        $group->package_group_id = $order_form->meta->domain_group;

        // Fetch all packages for this group
        $packages[$group->package_group_id]
            = $this->Packages->getAllPackagesByGroup($group->package_group_id);

        foreach ($packages[$group->package_group_id] as $package) {
            $package = $this->Packages->get($package->id);

            if ($package && $package->status == 'active' && isset($package->meta->tlds)) {
                foreach ($package->meta->tlds as $tld) {
                    if (isset($tlds[$tld])) {
                        continue;
                    }

                    $tlds[$tld] = [$package, $group];
                }
            }
        }

        // Sort the TLDs by length so that longer TLDs come before shorter ones
        // e.g. '.com.co' before '.co'
        // This aids in package pricing being determined properly by TLD comparison
        array_multisort(
            array_map(
                function ($tld) {
                    return strlen($tld);
                },
                array_keys($tlds)
            ),
            SORT_DESC,
            $tlds,
            array_keys($tlds),
            SORT_ASC
        );

        $this->tlds = $tlds;
        return $tlds;
    }

    protected function updatePackagePricing($packages, $currency)
    {
        if (!isset($this->Companies)) {
            Loader::loadModels($this, ['Companies']);
        }

        $multi_currency_pricing = $this->Companies->getSetting($this->company_id, 'multi_currency_pricing');
        $allow_conversion = true;

        if ($multi_currency_pricing->value == 'package') {
            $allow_conversion = false;
        }

        if (is_object($packages)) {
            $packages = $this->convertPackagePrice($packages, $currency, $allow_conversion);
        } else {
            foreach ($packages as &$package) {
                $package = $this->convertPackagePrice($package, $currency, $allow_conversion);
            }
        }

        return $packages;
    }

    /**
     * Convert pricing for the given package and currency
     *
     * @param stdClass $package A stdClass object representing a package
     * @param string $currency The ISO 4217 currency code to update to
     * @param bool $allow_conversion True to allow conversion, false otherwise
     * @return stdClass A stdClass object representing a package
     */
    protected function convertPackagePrice($package, $currency, $allow_conversion)
    {
        if (!isset($this->Packages)) {
            Loader::loadModels($this, ['Companies']);
        }

        $all_pricing = [];
        foreach ($package->pricing as $pricing) {
            $converted = false;
            if ($pricing->currency != $currency) {
                $converted = true;
            }

            $pricing = $this->Packages->convertPricing($pricing, $currency, $allow_conversion);
            if ($pricing) {
                if (!$converted) {
                    $all_pricing[$pricing->term . $pricing->period] = $pricing;
                } elseif (!array_key_exists($pricing->term . $pricing->period, $all_pricing)) {
                    $all_pricing[$pricing->term . $pricing->period] = $pricing;
                }
            }
        }

        $package->pricing = array_values($all_pricing);
        return $package;
    }
}
