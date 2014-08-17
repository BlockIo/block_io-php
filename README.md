Block.io PHP
===========

**Current Release**: 0.2.1  
**17/08/14**: get_balance() now accepts arguments, such as user_id and label.
**12/08/14**: Fixed issues with Composer (thanks radical_pi@Freenode!). Added withdraw_from_labels, withdraw_from_addresses.


PHP wrapper for [Block.io](https://block.io/) for use with [Dogecoin](http://dogecoin.com/), [Bitcoin](http://bitcoin.org/), and [Litecoin](http://litecoin.org). API key validation on instantiation, simple abstraction layer on top of existing API interfaces, and automatic JSON decoding on response.

Pull requests accepted and encouraged. The original code, including this README, of which this is a port was written by Jackson Palmer.

### Usage

First, sign up for an account at [Block.io](https://block.io/) and take note of your API key under Account > Dashboard.

Download and include the block_io.php class:


	 require_once 'path/to/block_io.php';


Or preferably install via [Composer](https://getcomposer.org/)


   	 "block_io-php/block_io-php": "0.2"


Instantiate the class and set your API key. If the API key is valid the set function will return true otherwise false.


	 $apiKey = "YOUR API KEY FOR DOGECOIN, BITCOIN, OR LITECOIN";

   	 $block_io = new BlockIo();

   	 $validKey = $block_io->set_key($apiKey);

   	 if($validKey) {
	      echo "Yay, it's a valid API key\n\n";
	      $balance = $block_io->get_balance();
	      $addresses = $block_io->get_my_addresses();
	      echo "Your available balance is " . $balance->data->available_balance . $balance->data->network . "\n";
   	 } else {
     	      echo "The API Key (" . $block_io->get_key() . ") is not a valid API key";
   	 }


The wrapper abstracts all methods listed at https://block.io/api/php using the same interface names. For example, to get your current account balance:

         $balance =  $block_io->get_balance();
         echo $balance->data->available_balance;


To make requests that require parameters (eg. an address label or address to withdraw to), pass through each parameter in an associative array. For example, the request below will withdraw 50 DOGE to the wallet you specify in place of `WALLET-ADDRESS-HERE`:


         $withdraw = $block_io->withdraw(array('amount' => '50.0', 'payment_address' => 'WALLET-ADDRESS-HERE', 'pin' => 'YOUR SECRET PIN'));


**Note:** Enforce your own error checking by making status $response->{'status'} == 'success' for every API call.

### Other Examples

#### Set Current API Key

Set the current API key being used. The key is also validated and the result of this validation is returned.


        $validKey = $doge->set_key($apiKey);
    	if($validKey) {
	      echo "Yay, it's a valid API key\n\n";
    	} else {
              echo "The API Key (" . $block_io->get_key() . ") is not a valid API key";
    	}


#### Get Current API Key

Print the current API key being used


        echo $block_io->get_key();


#### Get Balance

Print the current balance of your account.


        echo $block_io->get_balance()->{'data'}->{'available_balance'};


#### Get My Addresses

Print an array of wallet addresses associated with your account:


        $addresses = $block_io->get_my_addresses();
    	print_r($addresses);


