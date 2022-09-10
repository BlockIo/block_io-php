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
    
    public function __call($name, $args = array())
    { // method_missing for PHP

        if (!is_array($args)) {
            throw new \Exception("Must specify arguments as an associative array.");
        }

        $response = "";

        // extract the arguments provided
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
     * cURL POST request driver
     */
    private function _request($path, $args = array())
    {
        // Generate cURL URL
        $url =  str_replace("API_CALL",$path,"https://block.io/api/v" . $this->version . "/API_CALL/?api_key=") . $this->api_key;

        // Initiate cURL and set headers/options
        $ch  = curl_init();
        
        // If we run windows, make sure the needed pem file is used
        if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        	$pemfile = dirname(realpath(__FILE__)) . DIRECTORY_SEPARATOR . 'cacert.pem';
        	if(!file_exists($pemfile)) {
        		throw new \Exception("Needed .pem file not found. Please download the .pem file at https://curl.haxx.se/ca/cacert.pem and save it as " . $pemfile);
        	}        	
        	curl_setopt($ch, CURLOPT_CAINFO, $pemfile);
        }
        
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); // enforce use of TLS >= v1.2
        curl_setopt($ch, CURLOPT_URL, $url);
        
        // this was a POST method
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
        
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: php:block_io:2.0.x'
        );
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Execute the cURL request
        $result = curl_exec($ch);
        curl_close($ch);

        $json_result = json_decode($result);

        if ($json_result->status != 'success') {
            $e = new APIException("Failed: " . $json_result->data->error_message);
            $e->setRawData($json_result);
            throw $e;
        }

        // Spit back the response object or fail
        return $result ? $json_result : false;
        
    }

    public function summarize_prepared_transaction($data)
    {
        // takes input from prepare_transaction, prepare_dtrust_transaction, prepare_sweep_transaction
        // returns information: blockio_fee, network_fee, total_amount_to_send

        $input_sum = "0.00000000";
        $blockio_fee = "0.00000000";
        $network_fee = "0.00000000";
        $change_amount = "0.00000000";
        $output_sum = "0.00000000";
        
        foreach ($data->data->inputs as $curInput) {
            // sum the input values
            $input_sum = bcadd($input_sum, $curInput->input_value, 8);
        }

        foreach ($data->data->outputs as $curOutput) {
            // sum the output values

            if ($curOutput->output_category == "change") {
                $change_amount = bcadd($change_amount, $curOutput->output_value, 8);
            } elseif ($curOutput->output_category == "blockio-fee") {
                $blockio_fee = bcadd($blockio_fee, $curOutput->output_value, 8);
            } else {
                // user-specified
                $output_sum = bcadd($output_sum, $curOutput->output_value, 8);
            }
            
        }

        $response = array(
            'network' => $data->data->network,
            'network_fee' => bcsub(bcsub(bcsub($input_sum, $blockio_fee, 8), $change_amount, 8), $output_sum, 8),
            'blockio_fee' => $blockio_fee,
            'total_amount_to_send' => $output_sum
        );

        return $response;
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

        if (property_exists($data->data, 'expected_unsigned_txid') && !is_null($data->data->expected_unsigned_txid) && $data->data->expected_unsigned_txid != $unsigned->get()->getTxId()->getHex()) {
            // some protection against misbeahving machines/code for tx serialization
            throw new \Exception("Expected unsigned transaction ID mismatch. Please report this error to support@block.io.");
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

            if (property_exists($data->data, 'user_key') && !array_key_exists($data->data->user_key->public_key, $this->userKeys)) {
                // the encrypted key is provided in the response, and we don't have the decrypted key yet

                $key = $this->dynamicExtractKey($data->data->user_key, $this->pin);

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

        // reset $this->userKeys once we're done signing stuff
        $this->userKeys = [];
        
        return $response;
        
    }
    
    private function _internal_prepare_sweep_transaction($name, $args = array())
    { // sweep method to be called by __call
        
        $key = $this->initKey()->fromWif($args['private_key']);

        unset($args['private_key']); // remove the key so we don't send it to anyone outside
        
        $args['public_key'] = $key->getPublicKey();

        // store this key for later use
        $this->userKeys[$key->getPublicKey()] = $key;
        
        $response = $this->_request($name,$args);

        return $response;
    }

    public function dynamicExtractKey($user_key, $pin)
    { // extracts user key by using the appropriate algorithm

        $algorithm = json_decode("{\"pbkdf2_salt\":\"\",\"pbkdf2_iterations\":2048,\"pbkdf2_hash_function\":\"SHA256\",\"pbkdf2_phase1_key_length\":16,\"pbkdf2_phase2_key_length\":32,\"aes_iv\":null,\"aes_cipher\":\"AES-256-ECB\",\"aes_auth_tag\":null,\"aes_auth_data\":null}");

        if (property_exists($user_key, 'algorithm')) {
            $algorithm = $user_key->algorithm;
        }

        // get our encryption key ready
        // pinToAesKey($pin, $iterations = 2048, $salt = "", $hash_function = "SHA256", $pbkdf2_phase1_key_length = 16, $pbkdf2_phase2_key_length = 32)
        $encryption_key = $this->pinToAesKey($pin,
                                             $algorithm->pbkdf2_iterations,
                                             $algorithm->pbkdf2_salt,
                                             $algorithm->pbkdf2_hash_function,
                                             $algorithm->pbkdf2_phase1_key_length,
                                             $algorithm->pbkdf2_phase2_key_length);
        
        // decrypt the data
        // decrypt($b64ciphertext, $key, $cipher_type = "AES-256-ECB", $iv = NULL, $auth_tag = NULL, $auth_data = NULL)
        $passphrase = $this->decrypt($user_key->encrypted_passphrase,
                                     $encryption_key,
                                     $algorithm->aes_cipher,
                                     $algorithm->aes_iv,
                                     $algorithm->aes_auth_tag,
                                     $algorithm->aes_auth_data);
        
        // extract the key
        $key = $this->initKey();
        $key->fromPassphrase($passphrase);
        
        return $key;
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
    
    public function encrypt($data, $key, $cipher_type = "AES-256-ECB", $iv = NULL, $auth_data = null)
    {
        # encryption using AES-256-ECB, AES-256-CBC, AES-256-GCM
        # data is string, key is hex string
        
        $key = hex2bin($key); // convert the hex into binary
        
        if (strlen($data) % 8 != 0) {
            throw new \Exception("Invalid data length: " . strlen($data));
        }

        $response = [
            "aes_iv" => $iv,
            "aes_cipher_text" => null,
            "aes_auth_tag" => null,
            "aes_auth_data" => $auth_data,
            "aes_cipher" => $cipher_type
        ];
        
        $ciphertext = null;

        if ($cipher_type == "AES-256-ECB") {
            // ECB takes no IV
            $response["aes_cipher_text"] = openssl_encrypt($data, strtolower($cipher_type), $key, OPENSSL_RAW_DATA, hex2bin(""));
        } elseif ($cipher_type == "AES-256-CBC") {
            $response["aes_cipher_text"] = openssl_encrypt($data, strtolower($cipher_type), $key, OPENSSL_RAW_DATA, hex2bin($iv));
        } elseif ($cipher_type == "AES-256-GCM") {
            $response["aes_cipher_text"] = openssl_encrypt($data, strtolower($cipher_type), $key, OPENSSL_NO_PADDING, hex2bin($iv), $response["aes_auth_tag"], $auth_data);
            $response["aes_auth_tag"] = bin2hex($response["aes_auth_tag"]);
        } else {
            throw new \Exception("Unsupported cipher " . $cipher_type);
        }

        $response["aes_cipher_text"] = base64_encode($response["aes_cipher_text"]);

        return $response;
    }
    
    
    public function pinToAesKey($pin, $iterations = 2048, $salt = "", $hash_function = "SHA256", $pbkdf2_phase1_key_length = 16, $pbkdf2_phase2_key_length = 32)
    { // converts the given Secret PIN to an Encryption Key

        $enc_key_16 = hash_pbkdf2(strtolower($hash_function), $pin, $salt, $iterations/2, $pbkdf2_phase1_key_length * 2, false);
        $enc_key_32 = hash_pbkdf2(strtolower($hash_function), $enc_key_16, $salt, $iterations/2, $pbkdf2_phase2_key_length * 2, false);
        
        return $enc_key_32;
    }   
    
    public function decrypt($b64ciphertext, $key, $cipher_type = "AES-256-ECB", $iv = NULL, $auth_tag = NULL, $auth_data = NULL)
    {
        # data must be in base64 string, $key is binary of hashed pincode
        
        $key = hex2bin($key); // convert the hex into binary
        
        $ciphertext_dec = base64_decode($b64ciphertext);

        $data_dec = null;

        if ($cipher_type == "AES-256-ECB") {
            // ECB takes no IV
            $data_dec = openssl_decrypt($ciphertext_dec, strtolower($cipher_type), $key, OPENSSL_RAW_DATA, hex2bin(""));
        } elseif ($cipher_type == "AES-256-CBC") {
            $data_dec = openssl_decrypt($ciphertext_dec, strtolower($cipher_type), $key, OPENSSL_RAW_DATA, hex2bin($iv));            
        } elseif ($cipher_type == "AES-256-GCM") {

            if (strlen($auth_tag) != 32) {
                throw new \Exception("Auth tag must be 16 bytes exactly.");
            }
            
            $data_dec = openssl_decrypt($ciphertext_dec, strtolower($cipher_type), $key, OPENSSL_NO_PADDING, hex2bin($iv), hex2bin($auth_tag), $auth_data);
        } else {
            throw new \Exception("Unsupported cipher " . $cipher_type);
        }
        
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
