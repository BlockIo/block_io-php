/* This example script sweeps coin from the given private key (WIF) to the 'to_address'.
   Must specify the 'private_key' in Wallet Import Format (WIF).
   
   IMPORTANT! Specify your own API Key. Your Private Key never goes to Block.io.
   IMPORTANT! You will perform your own error checking for API calls.
   
   Contact support@block.io for any help with this.
*/

<?php
require __DIR__ . "/../vendor/autoload.php";

$apiKey = getenv("API_KEY");
$pin = null; // not needed
$version = 2; // the API version

$block_io = new \BlockIo\Client($apiKey, $pin, $version);

$to_address = getenv("TO_ADDRESS");
$private_key = getenv("PRIVATE_KEY"); // Wallet Import Format (WIF)

// let's sweep the coins to the To Address

$prepare_sweep_transaction_response = $block_io->prepare_sweep_transaction(array('to_address' => $to_address, 'private_key' => $private_key));

// inspect the above response. those are instructions on how to create the sweep transaction
// network fee = sum of inputs minus sum of outputs
// once we approve of the transaction, create it and sign it with the above private key
$create_and_sign_transaction_response = $block_io->create_and_sign_transaction($prepare_sweep_transaction_response);

// inspect the above response, it contains the final transaction you want to broadcast to the network
// once you're sure everything's as you expect it, submit the transaction to Block.io to submit to the peer-to-peer network
$response = $block_io->submit_transaction(array('transaction_data' => $create_and_sign_transaction_response));

// if we got here, we succeeded
// otherwise handle errors yourself from the responses
echo "Status: " . $response->status . PHP_EOL;
echo "Executed Transaction ID: " . $response->data->txid . PHP_EOL;

?>
