Block.io PHP
===========

**Requirement**: PHP 5.5.0 or greater  

**Current Release**: 1.0.4

**10/08/14**: Added 3 of 4 MultiSig example.  
**09/28/14**: Updated for v2 handling.

PHP wrapper for [Block.io](https://block.io/) for use with [Dogecoin](http://dogecoin.com/), [Bitcoin](http://bitcoin.org/), and [Litecoin](http://litecoin.org). API key validation on instantiation, simple abstraction layer on top of existing API interfaces, and automatic JSON decoding on response.

### Requirements

This library requires the 'mcrypt' and 'gmp' extensions for PHP. To enable these extensions, see:
   
   [mCrypt Installation Guide](http://php.net/manual/en/mcrypt.installation.php)

   [GMP Installation Guide](http://php.net/manual/en/gmp.installation.php)


### Usage

First, sign up for an account at [Block.io](https://block.io/) and take note of your API key under Account > Dashboard.

Download and include the block_io.php class:


	 require_once 'path/to/block_io.php';


Or preferably install via [Composer](https://getcomposer.org/)


   	 "block_io-php/block_io-php": "1.0.0"


Instantiate the class and set your API key. If the API key is valid the set function will return true otherwise false.


	 $apiKey = "YOUR API KEY FOR DOGECOIN, BITCOIN, OR LITECOIN";
	 $pin = "YOUR SECRET PIN";
	 $version = 2; // the API version to use

   	 $block_io = new BlockIo($apiKey, $pin, $version);

	 echo "Confirmed Balance: " . $block_io->get_balance()->data->available_balance . "\n";


The wrapper abstracts all methods listed at https://block.io/api/php using the same interface names. For example, to get your current account balance:

         $balance = $block_io->get_balance(array('label' => 'default'));
         echo $balance->data->available_balance . "\n";


To make requests that require parameters (eg. an address label or address to withdraw to), pass through each parameter in an associative array. For example, the request below will withdraw 50 DOGE to the wallet you specify in place of `WALLET-ADDRESS-HERE`:


         $withdraw = $block_io->withdraw(array('amount' => '50.0', 'to_address' => 'WALLET-ADDRESS-HERE'));


**Note:** This library throws Exceptions when calls fail. Implement try/catch blocks, and retrieve the Exception message to see details.


Please see [Block.io PHP Docs](https://block.io/api/simple/php) for details on available calls.

