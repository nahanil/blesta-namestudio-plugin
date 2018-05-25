<?php

/**
 * NameStudioUtil
 *
 * @author Jarrod Linahan <jarrod@linahan.id.au>
 * @copyright (c) 2018, Jarrod Linahan
 */
class NameStudioUtil extends NameStudioModel {
    
    /**
     * Get the order form "label" from the current URL
     * 
     * @param string $uri If not specified will use URI of current page
     * @return string Order for label
     */
    public static function getOrderFormLabel($uri=null) {
        if (!isset($uri)) {
            $uri = $_SERVER['REQUEST_URI'];
        }
        
        preg_match("/\/preconfig\/([^\/?]+)/", $uri, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    /**
     * Extract the SLD of a given domain name (ie, the part before .com)
     * 
     * @param string $domain Domain name to extract SLD from
     * @return string SLD of given domain
     */
    public static function getSld($domain) {
        $parts = explode(".", $domain);
        if (count($parts) == 1) {
          return $parts[0];
        }

        return $parts[0] == "www" ? $parts[1] : $parts[0];
    }
    
    /**
     * Extract TLD of a given domain name (.com)
     * 
     * @param string $domain Domain to extract TLD from
     * @param boolean $initial_dot Optional. Include leading ".". Defaults to false
     * @return string TLD of given domain
     */
    public static function getTld($domain, $initial_dot = false) {
        $tld = explode(".", $domain);
        $splice_offset = 1;
        
        if ($tld[0] == "www") {
            $splice_offset = 2;
        }
        
        return ($initial_dot ? "." : "") . implode(".", array_splice($tld, $splice_offset));
    }
}