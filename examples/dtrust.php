/* This example script does the following:

   Demonstration script for:
   1. Creating a 3 of 4 MultiSig address (actually 4 of 5 due to Block.io's key)
   2. Deposit coins into the new MultiSig address
   3. Withdraw coins from MultiSig address back into the sending address in (2)   

   IMPORTANT! Specify your own API Key and Secret PIN in this code. Keep your Secret PIN safe at all times.

   Contact support@block.io for any help with this.
*/

<?php
require __DIR__ . "/../vendor/autoload.php";

/* Replace the $apiKey with the API Key from your Block.io Wallet. A different API key exists for Dogecoin, Dogecoin Testnet, Litecoin, Litecoin Testnet, etc. */
$apiKey = getenv("API_KEY");
$pin = getenv("PIN");
$version = 2; // the API version

$block_io = new \BlockIo\Client($apiKey, $pin, $version);

// create 4 keys for a 3 of 4 MultiSig address (1 key is Block.io's, added automatically by Block.io)

// generate these yourself: $key = $block_io->initKey(); $key->generateRandomPrivateKey();
// store $key->getPrivateKey() and $key->getPublicKey() yourself before using them anywhere
// below are EXAMPLE keys, INSECURE! DO NOT USE!
$keys = [
    $block_io->initKey()->fromHex("b515fd806a662e061b488e78e5d0c2ff46df80083a79818e166300666385c0a2"),
    $block_io->initKey()->fromHex("001584b821c62ecdc554e185222591720d6fe651ed1b820d83f92cdc45c5e21f"),
    $block_io->initKey()->fromHex("2f9090b8aa4ddb32c3b0b8371db1b50e19084c720c30db1d6bb9fcd3a0f78e61"),
    $block_io->initKey()->fromHex("06c1cefdfd9187b36b36c3698c1362642083dcc1941dc76d751481d3aa29ca65")
];

// create an address with label 'dTrust1' that requires 3 of 4 signatures from the above keys

$pubKeyStr = $keys[0]->getPublicKey() . "," . $keys[1]->getPublicKey() . "," . $keys[2]->getPublicKey() . "," . $keys[3]->getPublicKey();

$dTrustAddress = "";

echo "*** Creating Address with 4 Signers, and 3 Required Signatures: " . "\n";

try {
    $response = $block_io->get_new_dtrust_address(array('label' => 'dTrust1', 'public_keys' => $pubKeyStr, 'required_signatures' => 3 ));
    $dTrustAddress = $response->data->address;
} catch (Exception $e) {

    // print the exception below for debugging
    // echo "Exception: " . $e->getMessage() . "\n";
    
    // the label must exist, let's get its address then
    $dTrustAddress = $block_io->get_dtrust_address_by_label(array('label' => 'dTrust1'))->data->address;
}

echo "*** Address with Label=dTrust1: " . $dTrustAddress . "\n";

// let's deposit some testnet coins into this address

try {
    $response = $block_io->withdraw(array('amounts' => '5.1', 'to_addresses' => $dTrustAddress));
    echo "*** Deposit Proof (Tx ID): " . $response->data->txid . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// let's get our dtrust address' balance

$response = $block_io->get_dtrust_address_balance(array('label' => 'dTrust1'));

echo "*** dTrust1 Available Balance: " . $response->data->available_balance . " " . $response->data->network . "\n\n";

echo "*** Beginning Withdrawal from dTrust1 to Testnet Default Address: " . "\n";

// let's withdraw coins from dTrust1 and send to the non-dTrust address labeled 'default'

$destAddress = $block_io->get_address_by_label(array('label' => 'default'))->data->address;

echo "**** Destination Address: " . $destAddress . "\n";

$response = $block_io->withdraw_from_dtrust_address(array('from_labels' => 'dTrust1', 'to_addresses' => $destAddress, 'amounts' => '2.0'));

echo "*** Inputs to sign? " . count($response->data->inputs) . "\n";

$refid = $response->data->reference_id;

$counter = 0;

// let's sign all the inputs we can, one key at a time
foreach ($keys as &$key) {

	foreach ($response->data->inputs as &$input) {
		// iterate over all the inputs
	
		$dataToSign = $input->data_to_sign; // the script sig we need to sign

		foreach ($input->signers as &$signer) {
			// iterate over all the signers for this input

			// find the key that can sign for the signer_public_key
			if ($key->getPublicKey() == $signer->signer_public_key)
			{ // we found the key, let's sign the data
			  
			  $signer->signed_data = $key->signHash($dataToSign);
			  
			  echo "* Data Signed By " . $key->getPublicKey() . "\n";
			}
		}
	}

	// all the data's signed for this public key, let's give it to Block.io
	$json_string = json_encode($response->data);

    	$r1 = $block_io->sign_transaction(array('signature_data' => $json_string));

	echo "* Send signatures for " . $key->getPublicKey() . "? " . $r1->status . "\n";

	$counter += 1; // we've signed using an additional key, let's note that down 

	// let's just use 3 signatures, since we created an address that required 3 of 4 signatures
	if ($counter == 3) { break; }
}

try {
    // now that everyone's signed the transaction, let's finalize (broadcast) it

    $response = $block_io->finalize_transaction(array('reference_id' => $refid));

    echo "*** dTrust Withdrawal Proof (Tx ID): " . $response->data->txid . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

?>
