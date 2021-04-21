<?php

use PHPUnit\Framework\TestCase;

class PrepareTransactionTest extends TestCase
{
    private $blockio;
    
    protected function setUp(): void {
        parent::setUp();
        $this->blockio = new \BlockIo\Client("", "d1650160bd8d2bb32bebd139d0063eb6063ffa2f9e4501ad", 2);
    }
    
    protected function tearDown(): void {
        parent::tearDown();
    }

    public function testCreateAndSignTransaction() {

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_transaction_response.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response);
        $this->assertEquals($create_and_sign_transaction_response, $response);

    }
    
}

?>
