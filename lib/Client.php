<?php

namespace BlockIo;

// include the external stuff we're using here
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PrivateKeyInterface;
use BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory;
use BitWasp\Bitcoin\Key\Factory\PublicKeyFactory;
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
use BitWasp\Bitcoin\Amount;
use BitWasp\Bitcoin\Signature\TransactionSignature;

class Client
{
    
    /**
     * Validate the given API key on instantiation
     */
    
    private $api_key;
    private $pin;
    private $encryption_key;
    private $version;
    private $sweep_methods;
    private $network;
    private $userKeys;
    
    public function __construct($api_key, $pin = null, $api_version = 2)
    { // the constructor
        $this->api_key = $api_key;
        $this->pin = $pin;
        $this->version = $api_version;
        $this->userKeys = [];
        
        $this->sweep_methods = ["prepare_sweep_transaction"];
    }
    
    public function __call($name, array $args)
    { // method_missing for PHP
        
        $response = "";
        
        if (empty($args)) { $args = array(); }
        else { $args = $args[0]; }
        
        if (in_array($name, $this->sweep_methods))
        { // it is a sweep method
	     	$response = $this->_internal_prepare_sweep_transaction($name, $args);
        }
        else
        { // pass-through method to Block.io
            
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
        
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); // enforce use of TLS >= v1.2
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

        // just give the response back to the user, no exceptions
        return $json_result;

    }
    
    public function create_and_sign_transaction($data, $keys = [])
    {
        // takes input from prepare_transaction, prepare_dtrust_transaction, prepare_sweep_transaction
        // creates the transaction and signs it
        // if transaction has all required signatures, serializes the signed transaction
        // returns transaction hex and remaining signatures to append, if any

        // set the proper network if it isn't already set yet
        if (is_null($this->network) &&
            property_exists($data, 'status') &&
            $data->status == "success" &&
            property_exists($data, 'data') &&
            property_exists($data->data, 'network')) {
            $this->network = $this->getNetwork($data->data->network);
        }
        
        $inputs = &$data->data->inputs;
        $outputs = &$data->data->outputs;

        $unsigned = new TxBuilder();
        
        // create the transaction given inputs and outputs
        foreach ($inputs as &$curInput) {
            // create the inputs

            $outpoint = new OutPoint(Buffer::hex($curInput->previous_txid), $curInput->previous_output_index);
            $unsigned->spendOutPoint($outpoint);
        
        }

        foreach ($outputs as &$curOutput) {
            // create the outputs
            
            $unsigned->output((new Amount)->toSatoshis($curOutput->output_value),
                              (new AddressCreator())->fromString($curOutput->receiving_address, $this->network)->getScriptPubKey());
            
        }

        // parse input address data
        $addressSignData = [];
        
        // we need address pubkeys to make the appropriate signatures
        $addressPubKeys = [];
        
        foreach($data->data->input_address_data as &$curAddressData) {
            // create the SignData for each address

            $curAddressType = &$curAddressData->address_type;
            $signData = null;
            
            if ($curAddressType == "P2WSH-over-P2SH" || $curAddressType == "WITNESS_V0" || $curAddressType == "P2SH") {
                // multisig address

                $curPubKeys = [];

                foreach($curAddressData->public_keys as &$curPubKey) {
                    array_push($curPubKeys, (new PublicKeyFactory)->fromHex($curPubKey));
                }
                
                $multisig = ScriptFactory::scriptPubKey()->multisig($curAddressData->required_signatures, $curPubKeys, false); // don't sort

                if ($curAddressType == "P2SH") {
                    $signData = (new SignData())->p2sh(new P2shScript($multisig));
                } elseif ($curAddressType == "P2WSH-over-P2SH") {
                    $signData = (new SignData())->p2sh(new P2shScript(new WitnessScript($multisig)))->p2wsh(new WitnessScript($multisig));
                } else {
                    // WITNESS_V0
                    $signData = (new SignData())->p2wsh(new WitnessScript($multisig));
                }

            } elseif ($curAddressType == "P2PKH" || $curAddressType == "P2WPKH-over-P2SH" || $curAddressType == "P2WPKH") {

                $curPubKey = (new PublicKeyFactory())->fromHex($curAddressData->public_keys[0]);
                $curPubKeyHash = $curPubKey->getPubKeyHash();

                // for P2PKH and P2WPKH, the library handles the signData itself, so leave it null
                    
                if ($curAddressType == "P2WPKH-over-P2SH") {
                    $signData = (new SignData())->p2sh(ScriptFactory::scriptPubKey()->p2wkh($curPubKeyHash));
                }
                                
            } else {
                throw new \Exception("Unrecognized address type: " . $curAddressType);
            }

            // record the SignData for later use
            
            $addressSignData[$curAddressData->address] = $signData;
            $addressPubKeys[$curAddressData->address] = &$curAddressData->public_keys;
            
        }

        // extract the private key from encrypted_passphrase if it's provided
        // append it to the keys provided

        if (!is_null($this->pin)) {
            // user provided a pin, so let's try to decrypt stuff

            // get our encryption key ready
            if (is_null($this->encryption_key))
            {
                $this->encryption_key = $this->pinToAesKey($this->pin);
            }
            
            if (property_exists($data->data, 'user_key') && !array_key_exists($data->data->user_key->public_key, $this->userKeys)) {
                // the encrypted key is provided in the response, and we don't have the decrypted key yet

                // decrypt the data
                $passphrase = $this->decrypt($data->data->user_key->encrypted_passphrase, $this->encryption_key);
                
                // extract the key
                $key = $this->initKey();
                $key->fromPassphrase($passphrase);
                
                // is this the right public key?
                if ($key->getPublicKey() != $data->data->user_key->public_key) { throw new \Exception('Fail: Invalid Secret PIN provided.'); }
                
                $this->userKeys[$key->getPublicKey()] = $key; // $key is \BlockIo\BlockKey object
            }

        }
        
        // add the user supplied private keys
        foreach($keys as &$curKey) {
            $key = $this->initKey();
            $key->fromHex($curKey);

            $this->userKeys[$key->getPublicKey()] = $key; // $key is \BlockIo\BlockKey object
        }

        $signer = new Signer($unsigned->get());
        $signatures = []; // the signature we will return to Block.io unless the transaction is already complete
        $readyToGo = true;
        
        // sign the transaction with whatever we have
        foreach($inputs as &$curInput) {
            
            $curSignData = $addressSignData[$curInput->spending_address];

            $txOut = new TransactionOutput((new Amount())->toSatoshis($curInput->input_value),
                                           (new AddressCreator())->fromString($curInput->spending_address, $this->network)->getScriptPubKey());

            $signerInput = $signer->input($curInput->input_index, $txOut, $curSignData);
            $curSigHash = $signerInput->getSigHash(SigHash::ALL)->getHex(); // sighash in hex form

            $curPubKeys = $addressPubKeys[$curInput->spending_address];

            $curPubKeyIndex = 0;
            $ecAdapter = \BitWasp\Bitcoin\Bitcoin::getEcAdapter(); // we need this to add our own low-S and low-R signatures

            foreach($curPubKeys as &$curPubKey) {
                if (array_key_exists($curPubKey, $this->userKeys)) {
                    // we have the key for this public key
                    // use it to sign this input
                    // PHPECC does not use low R signatures yet, so we'll generate our own and append them
                    
                    // get GMPr GMPs from BlockKey
                    // uses low S and low R
                    $points = $this->userKeys[$curPubKey]->getSignatureHashPoints($curSigHash);

                    $ecadapter_signature_signature = new \BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Signature\Signature($ecAdapter, gmp_init($points['R'], 16), gmp_init($points['S'], 16));
                    $transaction_signature = new TransactionSignature($ecAdapter, $ecadapter_signature_signature, SigHash::ALL);

                    if (count($signerInput->getSteps()) != 1) {
                        throw new \Exception("Unexpected number of steps: " . count($signerInput->getSteps()) . ". Please report this error to support@block.io.");
                    }

                    if ($signerInput->step(0) instanceof \BitWasp\Bitcoin\Transaction\Factory\CheckSig) {
                        // we can modify Checksig objects
                        // they're the ones that contain the appropriate pubkeys and signatures for a script
                        $signerInput->step(0)->setSignature($curPubKeyIndex, $transaction_signature);
                        
                        if ($signerInput->step(0)->getType() === \BitWasp\Bitcoin\Script\ScriptType::P2PKH) {
                            // https://github.com/Bit-Wasp/bitcoin-php/blob/1.0/src/Transaction/Factory/InputSigner.php#L977
                            $signerInput->step(0)->setKey($curPubKeyIndex, (new PublicKeyFactory())->fromHex($this->userKeys[$curPubKey]->getPublicKey()));
                        }

                        array_push($signatures,
                                   array("input_index" => $curInput->input_index,
                                         "public_key" => $curPubKey,
                                         "signature" => $signerInput->getSignatures()[$curPubKeyIndex]->getSignature()->getHex()));
                        
                    } else {
                        throw new \Exception("Current step is not a Checksig. Please report this error to support@block.io.");
                    }
                    
                }

                $curPubKeyIndex += 1;
            }

            // is this input fully signed?
            $readyToGo = ($readyToGo && $signerInput->isFullySigned());
        }

        $response = array("tx_type" => $data->data->tx_type, "tx_hex" => null, "signatures" => null);
        
        if ($readyToGo) {
            // no signatures to append
            $response["tx_hex"] = $signer->get()->getHex();
        } else {
            $response["signatures"] = $signatures;
            $response["tx_hex"] = $unsigned->get()->getHex();
        }
        
        return $response;
        
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

    private function getNetwork($n)
    { // returns the appropriate BitWasp Network object

        $nf = (new NetworkFactory());
        
        if ($n == "BTCTEST") { $nf = $nf->bitcoinTestnet(); }
        elseif ($n == "LTCTEST") { $nf = $nf->litecoinTestnet(); }
        elseif ($n == "DOGETEST") { $nf = $nf->dogecoinTestnet(); }
        elseif ($n == "BTC") { $nf = $nf->bitcoin(); }
        elseif ($n == "LTC") { $nf = $nf->litecoin(); }
        elseif ($n == "DOGE") { $nf = $nf->dogecoin(); }
        else { throw new \Exception("Invalid network found: " . $n); }

        return $nf;
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
