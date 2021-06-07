<?php

use PHPUnit\Framework\TestCase;

class EncryptDecryptTest extends TestCase
{
    private $blockio;
    private $aes_key;
    private $b64_encrypted;
    private $data_to_encrypt;
    
    protected function setUp(): void {
        parent::setUp();
        $this->blockio = new \BlockIo\Client("", "", 2);
        $this->aes_key = $this->blockio->pinToAesKey("deadbeef");
        $this->b64_encrypted = "3wIJtPoC8KO6S7x6LtrN0g==";
        $this->data_to_encrypt = "beadbeef";
        $this->aes_key_500000 = $this->blockio->pinToAesKey("deadbeef", 500000, "922445847c173e90667a19d90729e1fb");
    }
    
    protected function tearDown(): void {
        parent::tearDown();
    }
    
    public function testEncryptAes256Ecb()
    {
        $this->assertEquals($this->blockio->encrypt($this->data_to_encrypt, $this->aes_key)["aes_cipher_text"], $this->b64_encrypted);
    }
    
    public function testDecryptAes256Ecb()
    {
        $this->assertEquals($this->blockio->decrypt($this->b64_encrypted, $this->aes_key), $this->data_to_encrypt);
    }
    
    public function testEncryptAes256Cbc()
    {
        $iv = "11bc22166c8cf8560e5fa7e5c622bb0f";
        $encrypted_data = $this->blockio->encrypt($this->data_to_encrypt, $this->aes_key_500000, "AES-256-CBC", $iv);
        $this->assertEquals($encrypted_data["aes_cipher_text"], "LExu1rUAtIBOekslc328Lw==");
    }
    
    public function testDecryptAes256Cbc()
    {
        $iv = "11bc22166c8cf8560e5fa7e5c622bb0f";
        $this->assertEquals($this->blockio->decrypt("LExu1rUAtIBOekslc328Lw==", $this->aes_key_500000, "AES-256-CBC", $iv), $this->data_to_encrypt);
    }
    
    public function testEncryptAes256Gcm()
    {
        $iv = "a57414b88b67f977829cbdca";
        $encrypted_data = $this->blockio->encrypt($this->data_to_encrypt, $this->aes_key_500000, "AES-256-GCM", $iv, "");
        $this->assertEquals($encrypted_data["aes_cipher_text"], "ELV56Z57KoA=");
        $this->assertEquals($encrypted_data["aes_auth_tag"], "adeb7dfe53027bdda5824dc524d5e55a");
    }
    
   public function testDecryptAes256Gcm()
    {
        $iv = "a57414b88b67f977829cbdca";
        $auth_tag = "adeb7dfe53027bdda5824dc524d5e55a";
        $this->assertEquals($this->blockio->decrypt("ELV56Z57KoA=", $this->aes_key_500000, "AES-256-GCM", $iv, $auth_tag, ""), $this->data_to_encrypt);
    }
    
   public function testDecryptAes256GcmBadAuthTag()
    {
        $iv = "a57414b88b67f977829cbdca";
        $auth_tag = "adeb7dfe53027bdda5824dc524d5e5";

        try {
            $response = $this->blockio->decrypt("ELV56Z57KoA=", $this->aes_key_500000, "AES-256-GCM", $iv, $auth_tag, "");
            throw new \Exception("Test failed.");
        } catch (\Exception $e) {
            $this->assertEquals($e->getMessage(), "Auth tag must be 16 bytes exactly.");
        }

    }
    
    public function testPinToAESKey()
    {
        $this->assertEquals($this->aes_key, "b87ddac3d84865782a0edbc21b5786d56795dd52bab0fe49270b3726372a83fe");
    }

    public function testPinToAesKeyWithSalt()
    {
        $this->assertEquals($this->blockio->pinToAesKey("deadbeef", 500000, "922445847c173e90667a19d90729e1fb", "SHA256", 16, 32),
                            "f206403c6bad20e1c8cb1f3318e17cec5b2da0560ed6c7b26826867452534172");
    }

    public function testDynamicExtractKeyAes256Ecb() {
        $user_key = json_decode('{"encrypted_passphrase":"3wIJtPoC8KO6S7x6LtrN0g==","public_key":"02f87f787bffb30396984cb6b3a9d6830f32d5b656b3e39b0abe4f3b3c35d99323","algorithm":{"pbkdf2_salt":"","pbkdf2_iterations":2048,"pbkdf2_hash_function":"SHA256","pbkdf2_phase1_key_length":16,"pbkdf2_phase2_key_length":32,"aes_iv":null,"aes_cipher":"AES-256-ECB","aes_auth_tag":null,"aes_auth_data":null}}');
        $this->assertEquals($this->blockio->dynamicExtractKey($user_key, "deadbeef")->getPublicKey(), $user_key->public_key);
    }

    public function testDynamicExtractKeyAes256Cbc() {
        $user_key = json_decode('{"encrypted_passphrase":"LExu1rUAtIBOekslc328Lw==","public_key":"02f87f787bffb30396984cb6b3a9d6830f32d5b656b3e39b0abe4f3b3c35d99323","algorithm":{"pbkdf2_salt":"922445847c173e90667a19d90729e1fb","pbkdf2_iterations":500000,"pbkdf2_hash_function":"SHA256","pbkdf2_phase1_key_length":16,"pbkdf2_phase2_key_length":32,"aes_iv":"11bc22166c8cf8560e5fa7e5c622bb0f","aes_cipher":"AES-256-CBC","aes_auth_tag":null,"aes_auth_data":null}}');
        $this->assertEquals($this->blockio->dynamicExtractKey($user_key, "deadbeef")->getPublicKey(), $user_key->public_key);
    }

    public function testDynamicExtractKeyAes256Gcm() {
        $user_key = json_decode('{"encrypted_passphrase":"ELV56Z57KoA=","public_key":"02f87f787bffb30396984cb6b3a9d6830f32d5b656b3e39b0abe4f3b3c35d99323","algorithm":{"pbkdf2_salt":"922445847c173e90667a19d90729e1fb","pbkdf2_iterations":500000,"pbkdf2_hash_function":"SHA256","pbkdf2_phase1_key_length":16,"pbkdf2_phase2_key_length":32,"aes_iv":"a57414b88b67f977829cbdca","aes_cipher":"AES-256-GCM","aes_auth_tag":"adeb7dfe53027bdda5824dc524d5e55a","aes_auth_data":""}}');
        $this->assertEquals($this->blockio->dynamicExtractKey($user_key, "deadbeef")->getPublicKey(), $user_key->public_key);
    }
}

?>
