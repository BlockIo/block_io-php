/* This example script sweeps coin from the 'from_address' to the 'to_address'.
   Must specify the 'private_key' in Wallet Import Format.
   
   IMPORTANT! Specify your own API Key. Your Private Key never goes to Block.io.

   Contact support@block.io for any help with this.
*/

<?php
require_once '/home/anazir/blockio-libs/php/lib/block_io.php';

$apiKey = 'YOUR API KEY';
$pin = 'NONE'; // Not Needed
$version = 2; // the API version

$block_io = new BlockIo($apiKey, $pin, $version);

$from_address = 'FROM ADDRESS';
$to_address = 'TO ADDRESS';
$private_key = 'PRIVATE KEY OF FROM_ADDRESS IN WALLET IMPORT FORMAT';

// let's sweep the coins from the From Address to the To Address

try {

    $sweepInfo = $block_io->sweep_from_address(array('from_address' => $from_address, 'to_address' => $to_address, 'private_key' => $private_key));

    echo "Status: ".$sweepInfo->status."\n";

    echo "Executed Transaction ID: ".$sweepInfo->data->txid."\n";
    echo "Network Fee Charged: ".$sweepInfo->data->network_fee." ".$sweepInfo->data->network."\n";

} catch (Exception $e) {
   echo $e->getMessage() . "\n";
}

?>
