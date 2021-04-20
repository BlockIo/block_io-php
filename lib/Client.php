<?php

namespace BlockIo;

// include the external stuff we're using here
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PrivateKeyInterface;
use BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory;
use BitWasp\Bitcoin\Script\P2shScript;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Crypto\Hash;
use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Script\WitnessScript;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Transaction\Factory\SignData;
use BitWasp\Bitcoin\Transaction\SignatureHash\SigHash;

class Client
{
    
    /**
     * Validate the given API key on instantiation
     */
    
    private $api_key;
    private $pin = "";
    private $encryption_key = "";
    private $version;
    private $withdrawal_methods;
    private $sweep_methods;
    
    public function __construct($api_key, $pin, $api_version = 2)
    { // the constructor
        $this->api_key = $api_key;
        $this->pin = $pin;
        $this->version = $api_version;
        
        $this->withdrawal_methods = array("withdraw", "withdraw_from_user", "withdraw_from_users", "withdraw_from_label", "withdraw_from_labels", "withdraw_from_address", "withdraw_from_addresses");
        
        $this->sweep_methods = array("sweep_from_address");
    }
    
    public function __call($name, array $args)
    { // method_missing for PHP
        
        $response = "";
        
        if (empty($args)) { $args = array(); }
        else { $args = $args[0]; }
        
        if ( in_array($name, $this->withdrawal_methods) )
        { // it is a withdrawal method, let's do the client side signing bit
            $response = $this->_internal_withdraw($name, $args);
        }
        elseif (in_array($name, $this->sweep_methods))
        { // it is a sweep method
	     	$response = $this->_internal_sweep($name, $args);
        }
        else
        { // it is not a withdrawal method, let it go to Block.io
            
            $response = $this->_request($name, $args);
        }
        
        return $response;
        
    }
    
    /**
     * cURL GET request driver
     */
    private function _request($path, $args = array(), $method = 'POST')
    {
        // Generate cURL URL
        $url =  str_replace("API_CALL",$path,"https://block.io/api/v" . $this->version . "/API_CALL/?api_key=") . $this->api_key;
        $addedData = "";
        
        foreach ($args as $pkey => $pval)
        {
            
            if (strlen($addedData) > 0) { $addedData .= '&'; }
            
            $addedData .= $pkey . "=" . $pval;
        }
        
        // Initiate cURL and set headers/options
        $ch  = curl_init();
        
        // If we run windows, make sure the needed pem file is used
        if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        	$pemfile = dirname(realpath(__FILE__)) . DIRECTORY_SEPARATOR . 'cacert.pem';
        	if(!file_exists($pemfile)) {
        		throw new Exception("Needed .pem file not found. Please download the .pem file at http://curl.haxx.se/ca/cacert.pem and save it as " . $pemfile);
        	}        	
        	curl_setopt($ch, CURLOPT_CAINFO, $pemfile);
        }
        
        // it's a GET method
        if ($method == 'GET') { $url .= '&' . $addedData; }
        
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); // enforce use of TLSv1.2
        curl_setopt($ch, CURLOPT_URL, $url);
        
        if ($method == 'POST')
        { // this was a POST method
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $addedData);
        }
        
        $headers = array(
            'Accept: application/json',
            'User-Agent: php:block_io:2.0.x'
        );
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Execute the cURL request
        $result = curl_exec($ch);
        curl_close($ch);
        
        $json_result = json_decode($result);
        
        if ($json_result->status != 'success') { throw new \Exception('Failed: ' . $json_result->data->error_message); }
        
        // Spit back the response object or fail
        return $result ? $json_result : false;        
    }
    
    
    private function _internal_withdraw($name, $args = array())
    { // withdraw method to be called by __call
        
        unset ($args['pin']); // make sure no inadvertent passing of pin occurs
        
        $response = $this->_request($name,$args);
        
        if ($response->status == 'success' && property_exists($response->data, 'reference_id'))
        { // we have signatures to append
            
            // get our encryption key ready
            if (strlen($this->encryption_key) == 0)
            {
                $this->encryption_key = $this->pinToAesKey($this->pin);
            }
            
            // decrypt the data
            $passphrase = $this->decrypt($response->data->encrypted_passphrase->passphrase, $this->encryption_key);
            
            // extract the key
            $key = $this->initKey();
            $key->fromPassphrase($passphrase);
            
            // is this the right public key?
            if ($key->getPublicKey() != $response->data->encrypted_passphrase->signer_public_key) { throw new \Exception('Fail: Invalid Secret PIN provided.'); }
            
            // grab inputs
            $inputs = &$response->data->inputs;
            
            // data to sign
            foreach ($inputs as &$curInput)
            { // for each input
                
                $data_to_sign = &$curInput->data_to_sign;
                
                foreach ($curInput->signers as &$signer)
                { // for each signer
                    
                    if ($key->getPublicKey() == $signer->signer_public_key)
                    {
                        $signer->signed_data = $key->signHash($data_to_sign);
                    }		
                    
                }
                
            }
            
            $json_string = json_encode($response->data);
            
            // let's send the signed data back to Block.io
            $response = $this->_request('sign_and_finalize_withdrawal', array('signature_data' => $json_string));
            
        }
        
        return $response;
    }
    
    private function _internal_sweep($name, $args = array())
    { // sweep method to be called by __call
        
        $key = $this->initKey()->fromWif($args['private_key']);
        
        unset($args['private_key']); // remove the key so we don't send it to anyone outside
        
        $args['public_key'] = $key->getPublicKey();
        
        $response = $this->_request($name,$args);
        
        if ($response->status == 'success' && property_exists($response->data, 'reference_id'))
        { // we have signatures to append
            
            // grab inputs
            $inputs = &$response->data->inputs;
            
            // data to sign
            foreach ($inputs as &$curInput)
            { // for each input
                
                $data_to_sign = &$curInput->data_to_sign;
                
                foreach ($curInput->signers as &$signer)
                { // for each signer
                    
                    if ($key->getPublicKey() == $signer->signer_public_key)
                    {
                        $signer->signed_data = $key->signHash($data_to_sign);
                    }		
                    
                }
                
            }
            
            $json_string = json_encode($response->data);
            
            // let's send the signed data back to Block.io
            $response = $this->_request('sign_and_finalize_sweep', array('signature_data' => $json_string));
            
        }
        
        return $response;
    }
    
    public function initKey()
    { // grants a new Key object
        return new BlockKey();
    }
    
    private function pbkdf2($password, $key_length, $salt = "", $rounds = 1024, $a = 'sha256') 
    { // PBKDF2 function adaptation for Block.io
        
        // Derived key 
        $dk = '';
        
        // Create key 
        for ($block=1; $block<=$key_length; $block++) 
        { 
            // Initial hash for this block 
            $ib = $h = hash_hmac($a, $salt . pack('N', $block), $password, true); 
            
            // Perform block iterations 
            for ($i=1; $i<$rounds; $i++) 
            { 
                // XOR each iteration
                $ib ^= ($h = hash_hmac($a, $h, $password, true)); 
            } 
            
            // Append iterated block 
            $dk .= $ib;
        } 
        
        // Return derived key of correct length 
        $key = substr($dk, 0, $key_length);
        return bin2hex($key);
    }
    
    
    public function encrypt($data, $key)
    { 
        # encrypt using aes256ecb
        # data is string, key is hex string (pbkdf2 with 2,048 iterations)
        
        $key = hex2bin($key); // convert the hex into binary
        
        if (strlen($data) % 8 != 0) {
            throw new \Exception("Invalid data length: " . strlen($data));
        }
        
        $ciphertext = openssl_encrypt($data, 'AES-256-ECB', $key, true);
        
        $ciphertext_base64 = base64_encode($ciphertext);
        
        return $ciphertext_base64;
    }
    
    
    public function pinToAesKey($pin)
    { // converts the given Secret PIN to an Encryption Key
        
        $enc_key_16 = $this->pbkdf2($pin,16);
        $enc_key_32 = $this->pbkdf2($enc_key_16,32);
        
        return $enc_key_32;
    }   
    
    public function decrypt($b64ciphertext, $key)
    {
        # data must be in base64 string, $key is binary of hashed pincode
        
        $key = hex2bin($key); // convert the hex into binary
        
        $ciphertext_dec = base64_decode($b64ciphertext);
        
        $data_dec = openssl_decrypt($ciphertext_dec, 'AES-256-ECB', $key, OPENSSL_RAW_DATA, NULL);
        
        return $data_dec; // plain text
        
    }

    public function strToHex($string)
    {
        $hex='';
        for ($i=0; $i < strlen($string); $i++)
        {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }
    
}

?>
