<?php

use PHPUnit\Framework\TestCase;

class BasicCurlTest extends TestCase
{
    private $blockio;
    
    protected function setUp(): void {
        parent::setUp();
        $this->blockio = new \BlockIo\Client("");
    }
    
    protected function tearDown(): void {
        parent::tearDown();
    }
    
    public function testBadAPIKey()
    { // check that we're getting the appropriate data from Block.io via cURL
        
        $expected_response = json_decode('{"status":"fail","data":{"error_message":"Invalid API Key provided for this API version."}}');
        
        $response = $this->blockio->get_balance();

        $this->assertEquals($expected_response, $response);

    }
    
}

?>
