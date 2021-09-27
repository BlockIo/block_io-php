<?php

use PHPUnit\Framework\TestCase;

class PrepareTransactionTest extends TestCase
{
    private $blockio;
    private $dtrust_keys;
    private $sweep_key;
    
    protected function setUp(): void {
        parent::setUp();
        $this->blockio = new \BlockIo\Client("", "d1650160bd8d2bb32bebd139d0063eb6063ffa2f9e4501ad", 2);
        $this->dtrust_keys = [
            "b515fd806a662e061b488e78e5d0c2ff46df80083a79818e166300666385c0a2",
            "1584b821c62ecdc554e185222591720d6fe651ed1b820d83f92cdc45c5e21f",
            "2f9090b8aa4ddb32c3b0b8371db1b50e19084c720c30db1d6bb9fcd3a0f78e61",
            "6c1cefdfd9187b36b36c3698c1362642083dcc1941dc76d751481d3aa29ca65"
        ];

        $key = $this->blockio->initKey();
        $key->fromWif("cTj8Ydq9LhZgttMpxb7YjYSqsZ2ZfmyzVprQgjEzAzQ28frQi4ML");

        $this->sweep_key = $key->getPrivateKey(); // in hex
    }
    
    protected function tearDown(): void {
        parent::tearDown();
    }

    public function testSummarizePreparedTransaction() {
        // tests summarize_prepared_transaction response

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_transaction_response_with_blockio_fee_and_expected_unsigned_txid.json"), false);
        $summarize_prepared_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/summarize_prepared_transaction_response_with_blockio_fee_and_expected_unsigned_txid.json"), true);

        $response = $this->blockio->summarize_prepared_transaction($prepare_transaction_response);

        $this->assertEquals($summarize_prepared_transaction_response, $response);
        
    }
    
    public function testUseOfExpectedUnsignedTxid() {
        // tests whether library uses expected_unsigned_txid properly

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_transaction_response_with_blockio_fee_and_expected_unsigned_txid.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_with_blockio_fee_and_expected_unsigned_txid.json"), true);

        // this should succeed
        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response);
        $this->assertEquals($create_and_sign_transaction_response, $response);

        // this should fail: the expected unsigned transaction ID won't match
        $prepare_transaction_response->data->expected_unsigned_txid .= 'x';

        try {
            $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response);
            $this->assertEquals(true, false); // fails test
        } catch (\Exception $e) {
            $this->assertEquals($e->getMessage(), "Expected unsigned transaction ID mismatch. Please report this error to support@block.io.");
        }
    }
    
    public function testDTrustP2SH3of5UnorderedKeys() {
        // test for partial signatures (unordered) (P2SH)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_dtrust_transaction_response_p2sh.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_dtrust_p2sh_3_of_5_keys.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, [$this->dtrust_keys[1], $this->dtrust_keys[2], $this->dtrust_keys[0]]);
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testDTrustP2SH3of5Keys() {
        // test for partial signatures (P2SH)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_dtrust_transaction_response_p2sh.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_dtrust_p2sh_3_of_5_keys.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, array_slice($this->dtrust_keys, 0, 3));
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testDTrustP2SH4of5Keys() {
        // test for full signatures (P2SH)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_dtrust_transaction_response_p2sh.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_dtrust_p2sh_4_of_5_keys.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, $this->dtrust_keys);
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testDTrustP2WSHOverP2SH3of5Keys() {
        // test for partial signatures (P2WSH-over-P2SH)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_dtrust_transaction_response_p2wsh_over_p2sh.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_dtrust_p2wsh_over_p2sh_3_of_5_keys.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, array_slice($this->dtrust_keys, 0, 3));
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testDTrustP2WSHOverP2SH4of5Keys() {
        // test for full signatures (P2WSH-over-P2SH)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_dtrust_transaction_response_p2wsh_over_p2sh.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_dtrust_p2wsh_over_p2sh_4_of_5_keys.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, $this->dtrust_keys);
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testDTrustWitnessV03of5Keys() {
        // test for partial signatures (WITNESS_V0)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_dtrust_transaction_response_witness_v0.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_dtrust_witness_v0_3_of_5_keys.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, array_slice($this->dtrust_keys, 0, 3));
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testDTrustWitnessV04of5Keys() {
        // test for full signatures (WITNESS_V0)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_dtrust_transaction_response_witness_v0.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_dtrust_witness_v0_4_of_5_keys.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, $this->dtrust_keys);
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testSweepP2PKH() {
        // test for full signatures (P2PKH)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_sweep_transaction_response_p2pkh.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_sweep_p2pkh.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, [$this->sweep_key]);
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testSweepP2WPKHOverP2SH() {
        // test for full signatures (P2WPKH-over-P2SH)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_sweep_transaction_response_p2wpkh_over_p2sh.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_sweep_p2wpkh_over_p2sh.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, [$this->sweep_key]);
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testSweepP2WPKH() {
        // test for full signatures (P2WPKH)

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_sweep_transaction_response_p2wpkh.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_sweep_p2wpkh.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response, [$this->sweep_key]);
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
    
    public function testCreateAndSignTransaction() {
        // includes P2SH, P2WSH-over-P2SH, and WITNESS_V0 inputs
        
        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_transaction_response.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response.json"), true);

        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response);
        $this->assertEquals($create_and_sign_transaction_response, $response);

    }

    public function testWitnessV1Output() {
        // test for witness_v1 output

        $prepare_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/prepare_transaction_response_witness_v1_output.json"), false);
        $create_and_sign_transaction_response = json_decode(file_get_contents(__DIR__ . "/Data/json/create_and_sign_transaction_response_witness_v1_output.json"), true);
        
        $response = $this->blockio->create_and_sign_transaction($prepare_transaction_response);
        $this->assertEquals($create_and_sign_transaction_response, $response);
        
    }
}

?>
