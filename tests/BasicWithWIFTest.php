<?php

use PHPUnit\Framework\TestCase;

class BasicWithWIFTest extends TestCase
{
    private $blockio;
    private $data_to_sign;
    private $key;
    
    protected function setUp(): void {
        parent::setUp();
        $this->blockio = new \BlockIo\Client("", "", 2);
        $this->data_to_sign = "e76f0f78b7e7474f04cc14ad1343e4cc28f450399a79457d1240511a054afd63";
        $this->key = $this->blockio->initKey()->fromWIF("L1cq4uDmSKMiViT4DuR8jqJv8AiiSZ9VeJr82yau5nfVQYaAgDdr");
    }
    
    protected function tearDown(): void {
        parent::tearDown();
    }
    
    public function testKeyFromWIF()
    {
        $this->assertEquals($this->key->getPublicKey(), "024988bae7e0ade83cb1b6eb0fd81e6161f6657ad5dd91d216fbeab22aea3b61a0");
        $this->assertEquals($this->key->signHash($this->data_to_sign), "3044022061753424b6936ca4cfcc81b883dab55f16d84d3eaf9d5da77c1e25f54fda963802200d3db78e8f5aac62909c2a89ab1b2b413c00c0860926e824f37a19fa140c79f4");
    }
    
}

?>
