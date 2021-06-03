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
        
        $expected_error_message = "API Key invalid or you have not enabled API access for this machine's IP address(es). Check that your API Keys are correct, and that you have enabled API access for this machine's IP Address(es) on your account's Settings page.";

        try {
            $response = $this->blockio->get_balance();
            throw new \Exception("Test failed.");
        } catch (\BlockIo\APIException $e) {
            $this->assertEquals($e->getRawData()->data->error_message, $expected_error_message);
        }
    }
    
}

?>
