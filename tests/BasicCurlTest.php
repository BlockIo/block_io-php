<?php

use PHPUnit\Framework\TestCase;

class BasicCurlTest extends TestCase
{
    private $blockio;

    protected function setUp(): void {
	parent::setUp();
	$this->blockio = new \BlockIo\Client("", "", 2);
    }

    protected function tearDown(): void {
	parent::tearDown();
    }

    public function testBadAPIKey()
    { // check that we're getting the appropriate data from Block.io via cURL

	$this->expectException(\Exception::class);
	$this->expectExceptionMessage("Failed: Invalid API Key provided for this API version.");

	$this->blockio->get_balance();
	
    }
    
}

?>
