/* This example script does the following:

   1. This script requires a 'to_address' command line argument that will expect payments.
   2. This script requires an 'amount' command line argument -- i.e., the expected amount.
   3. This script requires a 'min_confidence' argument -- anything >= min_confidence is accepted as a valid payment.
   4. This script checks for the amount's presence every second, and exits when the payment's been received.
   
   IMPORTANT! Specify your own API Key in this code.

   The to_address does not need to belong to your own account -- only requirement: it needs to be a valid network
   address.

   We recommend using the Bitcoin Testnet here. Please note that the Bitcoin Testnet (due to its small size)
   will reach ~0.8 confidence max. Live network for Bitcoin, Dogecoin, Litecoin will reach 0.99 confidence.

   Contact support@block.io for any help with this.
*/

<?php
require_once '../lib/block_io.php';

/* Parse command line arguments */
parse_str(implode('&', array_slice($argv, 1)), $_GET);

/* Our account's credentials for Bitcoin Testnet */
$apiKey = 'YOUR API KEY';
$pin = 'notNeeded';
$version = 2; // the API version

$block_io = new BlockIo($apiKey, $pin, $version);

$toAddress = $_GET['to_address'];
$paymentExpected = strval($_GET['amount']);
$confidenceThreshold = floatval($_GET['min_confidence']);

print "Monitoring Address for Payments: " . $toAddress . "\n";
print "Amount Expected: " . $paymentExpected . "\n";

/* Look for a new incoming transaction, and let us know when done.
   Assumption: The $toAddress is new, and does not have previously received transactions */

while (true) {
      // Keep checking for a new transaction, end when there is at least one transaction and its confidence has reached 0.90.
      $txs = $block_io->get_transactions(array('addresses' => $toAddress, 'type' => 'received'));
      $paymentReceived = "0.0"; // using strings for high precision monetary stuff

//      print_r($txs);

      $txs = $txs->data->txs;

      // iterate over all transactions, check their confidence
      foreach($txs as $tx) {
      	  foreach($tx->amounts_received as $amountReceived) 
	  {
		if ($amountReceived->recipient == $toAddress) {
		   print "Amount: " . $amountReceived->amount . " Confidence: " . $tx->confidence . "\n";
		   
		   if ($tx->confidence > $confidenceThreshold) {
		      $paymentReceived = bcadd($amountReceived->amount, $paymentReceived, 8);
		   }
		}    			     
    	  }	
      }

      if (bccomp($paymentReceived,$paymentExpected,8) >= 0) {
      	 print "Payment confirmed: " . $paymentReceived . "\n";
         break;
      } else {
         sleep(1); // sleep for one second and try again
      }
	    
}

?>
