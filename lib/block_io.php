<?php

/**
 * Block.io Wallet API Wrapper
 * 
 * Ported from Jackson Palmer's PHP library.
 *
 * Requirements: cURL
 * 
 * @author Atif Nazir <a@block.io>
 */
 
class BlockIo
{
    
    /**
     * Validate the given API key on instantiation
     */
     
    private $api_key;
    private $valid_key = false;
    /**
     * cURL GET request driver
     */

    private function _request($method, $path, $args = array())
    {
        // Generate cURL URL
        $url =  str_replace("API_CALL",$path,"https://block.io/api/v1/API_CALL/?api_key=") . $this->api_key;

        // Check for args and build query string
        if (!empty($args)) {
            $url .= '&' . http_build_query($args);
        }

        // Initiate cURL and set headers/options
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL request
        $result = curl_exec($ch);
        curl_close($ch);

        // Spit back the response object or fail
        return $result ? json_decode($result) : false;        
    }

    /**
    * Block.io API Key Set, Get, and Validation
    */
    public function set_key($key)
    {
        $this->api_key = $key;
        return $this->validate_key();
    }

    public function get_key($key)
    {
        return $this->api_key;
    }

    private function validate_key()
    {
        // Test if the key is valid by doing a simple balance check
        $validate = $this->_request('GET', 'get_balance');
        
        // Return true/false if key is valid
        if ($validate->{'status'} != "success")
            $this->valid_key = false;
        else
            $this->valid_key = true;
        return $this->valid_key;
    }

    /**
     * Public methods (Block.io abstraction layer)
     */

    // get_balance
    public function get_balance()
    {
        return $this->_request('GET', 'get_balance');
    }

    // withdraw
    public function withdraw($args = array())
    {
        return $this->_request('GET', 'withdraw', $args);
    }

    // get_new_address
    public function get_new_address($args = array())
    {
        return $this->_request('GET', 'get_new_address', $args);
    }

    // get_my_addresses
    public function get_my_addresses()
    {
        return $this->_request('GET', 'get_my_addresses');
    }

    // get_address_received
    public function get_address_received($args = array())
    {
        return $this->_request('GET', 'get_address_received', $args);
    }

    // get_address_by_label
    public function get_address_by_label($args = array())
    {
        return $this->_request('GET', 'get_address_by_label', $args);
    }

    // get_address_balance
    public function get_address_balance($args = array())
    {
        return $this->_request('GET', 'get_address_balance', $args);
    } 

    // create_user
    public function create_user($args = array())
    {
        return $this->_request('GET', 'create_user', $args);
    } 

    // get_users
    public function get_users($args = array())
    {
        return $this->_request('GET', 'get_users', $args);
    } 

    // get_user_balance
    public function get_user_balance($args = array())
    {
        return $this->_request('GET', 'get_user_balance', $args);
    } 

    // get_user_address
    public function get_user_address($args = array())
    {
        return $this->_request('GET', 'get_user_address', $args);
    } 

    // get_user_received
    public function get_user_received($args = array())
    {
        return $this->_request('GET', 'get_user_received', $args);
    } 

    // withdraw_from_user
    public function withdraw_from_user($args = array())
    {
        return $this->_request('GET', 'withdraw_from_user', $args);
    } 

    // get_current_price
    public function get_current_price($args = array())
    {
        return $this->_request('GET', 'get_current_price', $args);
    } 
    
}
