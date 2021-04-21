/* This example script does the following:
   
   1. Get available balance in your account for Dogecoin, or Litecoin, or Bitcoin, etc.
   2. Create an address labeled 'shibetime1' on the account if it does not already exist
   3. Withdraw 1% of total available balance in your account, and send it to the address labeled 'shibetime1'
   
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

echo "*** Getting account balance\n";

$getBalanceInfo = "";

$getBalanceInfo = $block_io->get_balance();
    
echo "!!! Using Network: " . $getBalanceInfo->data->network . PHP_EOL;
echo "Available Amount: " . $getBalanceInfo->data->available_balance . " " . $getBalanceInfo->data->network . PHP_EOL;

echo "*** Create new address" . PHP_EOL;

$getNewAddressInfo = $block_io->get_new_address(array('label' => 'shibetime1'));

echo "New Address: " . $getNewAddressInfo->data->address . PHP_EOL;
echo "Label: " . $getNewAddressInfo->data->label . PHP_EOL;

echo "Getting address for Label='shibetime1'" . PHP_EOL;
$getAddressInfo = $block_io->get_address_by_label(array('label' => 'shibetime1'));
echo "Status: " . $getAddressInfo->status . PHP_EOL;

echo "Label has Address: " . $block_io->get_address_by_label(array('label' => 'shibetime1'))->data->address . PHP_EOL;

echo "***Send 1% of coins on my account to the address labeled 'shibetime1'" . PHP_EOL;

// Use high decimal precision for any math on coins. They can be 8 decimal places at most, or the system will reject them as invalid amounts.
$sendAmount = bcmul($getBalanceInfo->data->available_balance, '0.01', 8); 

echo "Available Amount: " . $getBalanceInfo->data->available_balance . " " . $getBalanceInfo->data->network . PHP_EOL;

# detour: let's get an estimate of the network fee we'll need to pay for this transaction
# use the same parameters you will provide to the withdrawal method get an accurate response
$estNetworkFee = $block_io->get_network_fee_estimate(array('to_address' => $getAddressInfo->data->address, 'amount' => $sendAmount));

echo "Estimated Network Fee: " . $estNetworkFee->data->estimated_network_fee . " " . $estNetworkFee->data->network . PHP_EOL;

echo "Withdrawing 1% of Available Amount: " . $sendAmount . " " . $getBalanceInfo->data->network . PHP_EOL;

// prepare the transaction
// this response will contain instructions on how to create the transaction you want
// inspect it and make sure everything's as you expect
// network fee = sum of inputs - sum of outputs
// if there's any Block.io fee, it will be in the outputs with category='blockio-fee'
$prepare_transaction_response = $block_io->prepare_transaction(array('to_address' => $getAddressInfo->data->address, 'amount' => $sendAmount));

// once satisfied, create the transaction and sign it
// this response will contain the transaction payload that you want Block.io to sign,
// and the signatures you want to append to the transaction
// make sure the payload is what you want it to be
$create_and_sign_transaction_response = $block_io->create_and_sign_transaction($prepare_transaction_response);

// once satisfied, submit the transaction to Block.io so Block.io can append its signatures and broadcast the transaction to the peer-to-peer network
$submit_transaction_response = $block_io->submit_transaction(array('transaction_data' => $create_and_sign_transaction_response));

echo "Status: " . $submit_transaction_response->status . PHP_EOL;

echo "Executed Transaction ID: " . $submit_transaction_response->data->txid . PHP_EOL;

?>
