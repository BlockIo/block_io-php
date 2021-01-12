<?php
require_once '../lib/block_io.php';

/* Parse command line arguments */
parse_str(implode('&', array_slice($argv, 1)), $_GET);

/* Our account's credentials for Bitcoin Testnet */
$apiKey = 'YOUR API KEY';
$pin = 'notNeeded';
$version = 2; // the API version
$proxy = 'socks5://user:password@localhost:12345';

$block_io = new BlockIo($apiKey, $pin, $version, $proxy);

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
