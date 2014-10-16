/* This example script does the following:

   Demonstration script for:
   1. Creating a 3 of 4 MultiSig address (actually 4 of 5 due to Block.io's key)
   2. Deposit coins into the new MultiSig address
   3. Withdraw coins from MultiSig address back into the sending address in (2)   

   IMPORTANT! Specify your own API Key and Secret PIN in this code. Keep your Secret PIN safe at all times.

   Contact support@block.io for any help with this.
*/

<?php
require_once '/path/to/block_io.php';

/* Replace the $apiKey with the API Key from your Block.io Wallet. A different API key exists for Dogecoin, Dogecoin Testnet, Litecoin, Litecoin Testnet, etc. */
$apiKey = 'DogecoinTestnetAPIKey';
$pin = 'SecretPin';
$version = 2; // the API version

$block_io = new BlockIo($apiKey, $pin, $version);

// create 4 keys for a 3 of 4 MultiSig address (1 key is Block.io's, added automatically by Block.io)

$passphrases = [ strToHex('alpha1alpha2alpha3alpha4'), strToHex('alpha2alpha3alpha4alpha1'), strToHex('alpha3alpha4alpha1alpha2'), strToHex('alpha4alpha1alpha2alpha3') ];

$keys = [ $block_io->initKey()->fromPassphrase($passphrases[0]), $block_io->initKey()->fromPassphrase($passphrases[1]), $block_io->initKey()->fromPassphrase($passphrases[2]), $block_io->initKey()->fromPassphrase($passphrases[3]) ];

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
