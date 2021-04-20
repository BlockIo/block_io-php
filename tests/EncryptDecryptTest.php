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
    }

    protected function tearDown(): void {
	parent::tearDown();
    }

    public function testEncrypt()
    {
	$this->assertEquals($this->blockio->encrypt($this->data_to_encrypt, $this->aes_key), $this->b64_encrypted);
    }

    public function testDecrypt()
    {
	$this->assertEquals($this->blockio->decrypt($this->b64_encrypted, $this->aes_key), $this->data_to_encrypt);
    }
    
    public function testPinToAESKey()
    {
	$this->assertEquals($this->aes_key, "b87ddac3d84865782a0edbc21b5786d56795dd52bab0fe49270b3726372a83fe");
    }
    
}

?>
