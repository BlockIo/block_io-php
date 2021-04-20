<?php

use PHPUnit\Framework\TestCase;

class BasicWithPassphraseTest extends TestCase
{
    private $blockio;
    private $data_to_sign;
    private $key;
    
    protected function setUp(): void {
        parent::setUp();
        $this->blockio = new \BlockIo\Client("", "", 2);
        $this->data_to_sign = "e76f0f78b7e7474f04cc14ad1343e4cc28f450399a79457d1240511a054afd63";
        $this->key = $this->blockio->initKey()->fromPassphrase("deadbeef");
    }
    
    protected function tearDown(): void {
        parent::tearDown();
    }
    
    private function generateNonce($counter)
    {
        
        $extra_entropy = implode(array_reverse(str_split(sprintf("%064s", dechex($counter)),2)));
        $nonce = $this->key->deterministicGenerateK($this->data_to_sign, $this->key->getPrivateKey(), $extra_entropy);
        
        return $nonce;
    }
    
    public function testRFC6979EntropyNone()
    {
        
        $nonce = $this->key->deterministicGenerateK($this->data_to_sign, $this->key->getPrivateKey(), "");
        
        $this->assertEquals($nonce, "b13fa787e16b878c9a7815c8b508eb9e6a401432a15f340dd3fcde25e5c494b8");
        
    }
    
    public function testRFC6979Entropy1()
    {
        
        $this->assertEquals($this->generateNonce(1), "b69b1e880b537aca72b7235506ba04a676bdd2d663e4e1eb7d8c567f48ab0646");
        
    }
    
    public function testRFC6979Entropy2()
    {
        
        $this->assertEquals($this->generateNonce(2), "e0b71534de1cf4f5019b0bc4e10d655d0e625b531e4911daf44cf2d065dcedd3");
        
    }
    
    public function testRFC6979Entropy3()
    {
        
        $this->assertEquals($this->generateNonce(3), "faed0d38abb73e5f909cc989d967e3c4abb873ad177fe72bc35dc8ba42452fc0");
        
    }
    
    public function testRFC6979Entropy4()
    {
        
        $this->assertEquals($this->generateNonce(4), "96db9090ce1eb13ae91fb15129838d73ba382cfeb48f6d1cf1a1296a3ce94c49");
        
    }
    
    public function testRFC6979Entropy16()
    {
        
        $this->assertEquals($this->generateNonce(16), "d4985f135357c3885c55c3dff3e9f98bccb0264fb348259f8160660e41f5ce65");
        
    }
    
    public function testRFC6979Entropy17()
    {
        
        $this->assertEquals($this->generateNonce(17), "1affb74f0ecffa9b1996670ba47c6366dd76b484f7af977e4cd32d16c5545e0d");
        
    }
    
    public function testRFC6979Entropy255()
    {
        
        $this->assertEquals($this->generateNonce(255), "d72decc0d526ece67755680556b8700ccfdd2fd7beba87f709ec4037f7a0771f");
        
    }
    
    public function testRFC6979Entropy256()
    {
        
        $this->assertEquals($this->generateNonce(256), "5ff357395dc803f98967276a49a0802cc5b44b52db395242926bd2c4a6ac062f");
        
    }
    
    public function testKeyFromPassphrase()
    {
        $this->assertEquals($this->key->getPublicKey(), "02953b9dfcec241eec348c12b1db813d3cd5ec9d93923c04d2fa3832208b8c0f84");
        $this->assertEquals($this->key->signHash($this->data_to_sign), "304402204ac97a4cdad5f842e745e27c3ffbe08b3704900baafab602277a5a196c3a4a3202202bacdf06afaf58032383447a9f3e9a42bfaeabf6dbcf9ab275d8f24171d272cf");
        
    }
    
}

?>
