/* This example script does the following:

   Demonstration script for:
   1. Creating a 3 of 4 MultiSig address (actually 4 of 5 due to Block.io's key)
   2. Deposit coins into the new MultiSig address
   3. Withdraw coins from MultiSig address back into the sending address in (2)   

   IMPORTANT! Specify your own API Key and Secret PIN in this code. Keep your Secret PIN safe at all times.
   IMPORTANT! You will perform your own error checking for API calls.

   Contact support@block.io for any help with this.
*/

<?php
require __DIR__ . "/../vendor/autoload.php";

// Replace the $apiKey with the LTCTEST API Key from your Block.io Wallet. If not using LTCTEST in this example, you will need to modify the amounts used below. 
$apiKey = getenv("API_KEY");
$pin = getenv("PIN");
$version = 2; // the API version

$block_io = new \BlockIo\Client($apiKey, $pin, $version);

// create 4 keys for a 3 of 4 MultiSig address (1 key is Block.io's, added automatically by Block.io)

// generate these yourself: $key = $block_io->initKey(); $key->generateRandomPrivateKey();
// store $key->getPrivateKey() and $key->getPublicKey() yourself before using them anywhere
// below are EXAMPLE keys, INSECURE! DO NOT USE!
$keys = [
    "b515fd806a662e061b488e78e5d0c2ff46df80083a79818e166300666385c0a2",
    "001584b821c62ecdc554e185222591720d6fe651ed1b820d83f92cdc45c5e21f",
    "2f9090b8aa4ddb32c3b0b8371db1b50e19084c720c30db1d6bb9fcd3a0f78e61",
    "06c1cefdfd9187b36b36c3698c1362642083dcc1941dc76d751481d3aa29ca65"
];

// create an address with label 'dTrust1' that requires 3 of 4 signatures from the above keys
// calculate the public keys
// the order of these public keys matters, so make sure the array above is in the correct order of your choosing first
$pubKeys = [];

foreach($keys as &$curKey) {
    array_push($pubKeys, $block_io->initKey()->fromHex($curKey)->getPublicKey());
}

echo "*** Creating Address with 4 Signers, and 3 Required Signatures: " . PHP_EOL;

$response = "";
$dTrustAddress = null;

try {
    $response = $block_io->get_new_dtrust_address(array('label' => 'dTrust1', 'public_keys' => implode(",", $pubKeys), 'required_signatures' => 3 ));

    // we created the address successfully
    $dTrustAddress = $response->data->address;

} catch (\BlockIo\APIException $e) {
    print $e->getMessage() . PHP_EOL;
    print json_encode($e->getRawData()) . PHP_EOL;
    
    // no address retrieved
    // the label must exist, let's get its address then
    $dTrustAddress = $block_io->get_dtrust_address_by_label(array('label' => 'dTrust1'))->data->address;
}

echo "*** Address with Label=dTrust1: " . $dTrustAddress . PHP_EOL;

// let's deposit some testnet coins into this address
// IMPORTANT: see notes in examples/basic.php for these steps and what they mean
$prepare_transaction_response = $block_io->prepare_transaction(array('amounts' => '0.001', 'to_address' => $dTrustAddress));
$create_and_sign_transaction_response = $block_io->create_and_sign_transaction($prepare_transaction_response);
$submit_transaction_response = $block_io->submit_transaction(array('transaction_data' => $create_and_sign_transaction_response));
echo "*** Deposit Proof (Tx ID): " . $submit_transaction_response->data->txid . PHP_EOL;

// let's get our dtrust address' balance

$response = $block_io->get_dtrust_address_balance(array('label' => 'dTrust1'));

echo "*** dTrust1 Available Balance: " . $response->data->available_balance . " " . $response->data->network . PHP_EOL;

echo "*** Beginning Withdrawal from dTrust1 to Testnet Default Address: " . PHP_EOL;

// let's withdraw coins from dTrust1 and send to the non-dTrust address labeled 'default'

$destAddress = $block_io->get_address_by_label(array('label' => 'default'))->data->address;

echo "**** Destination Address: " . $destAddress . PHP_EOL;

// let's withdraw coins from the dTrust address into the $destAddress
// note that for dTrust, the endpoint if prepare_dtrust_transaction, not prepare_transaction
$prepare_transaction_response = $block_io->prepare_dtrust_transaction(array('from_labels' => 'dTrust1', 'to_address' => $destAddress, 'amount' => '0.0009'));

// we're going to sign with just 3 of our keys, and then Block.io will sign with its key
// alternatively, you can sign with all 4 of your keys and then either broadcast the transaction through Block.io or anywhere else you prefer
$create_and_sign_transaction_response = $block_io->create_and_sign_transaction($prepare_transaction_response, array_slice($keys, 0, 3)); // sign with only 3 of our keys

// now submit the transaction payload, and signatures left to append (if any) to Block.io
// if the transaction is not final, Block.io will append its own key's signature to this payload
// otherwise it will just broadcast the final payload to the peer-to-peer network
$submit_transaction_response = $block_io->submit_transaction(array('transaction_data' => $create_and_sign_transaction_response));
echo "*** dTrust Withdrawal Proof (Tx ID): " . $submit_transaction_response->data->txid . PHP_EOL;

?>
