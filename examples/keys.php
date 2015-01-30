/* This example script does the following:

   This script converts a given string into a Private Key for a specific address.
   Useful for extracting Private Keys in Wallet Import Format when using Distributed Trust,
   and mop-py to sweep coins out of the Distributed Trust address without going through Block.io.

   IMPORTANT! Specify your own API Key here.
   The network to use for the Wallet Import Format is determined from the API Key used.

   Contact support@block.io for any help with this.
*/

<?php
require_once 'path/to/block_io.php';

/* Replace the $apiKey with the API Key from your Block.io Wallet. */
$apiKey = 'YOUR API KEY';
$pin = 'PIN - NOT NEEDED';
$version = 2; // the API version

$block_io = new BlockIo($apiKey, $pin, $version);
$network = $block_io->get_balance()->data->network; // get our current network off Block.io

$passphrase = strToHex('alpha1alpha2alpha3alpha4');
$key = $block_io->initKey()->fromPassphrase($passphrase);

echo "Current Network: " . $network . "\n";
echo "Private Key: " . $key->toWif($network) . "\n"; // print out the private key for the given network

?>
