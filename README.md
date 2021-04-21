Block.io PHP
===========

**Current Release**: 2.0.0  
**08/21/21**: BREAKING CHANGES. Interfaces have changed, and API responses no longer throw exceptions. Test thoroughly before use. You will manage exceptions yourself.  

PHP wrapper for [Block.io](https://block.io/) for use with [Dogecoin](http://dogecoin.com/), [Bitcoin](http://bitcoin.org/), and [Litecoin](http://litecoin.org). Simple abstraction layer on top of existing API interfaces, and automatic JSON decoding on response.  

### Requirements

This library requires: gmp, cURL, mbstring, and bcmath extensions. PHP7.2, PHP7.3, PHP7.4, or PHP8.0. 

### Warning

Make sure all PHPUnit tests pass before using this library on your system.

### Usage

Install via [Composer](https://getcomposer.org/)

```sh
composer require block_io-php/block_io-php
```

See examples/ and https://block.io/api/simple/php for basic usage.

**Note:** This library will *not* throw exceptions on API call failures. Implement your own error checking logic.

Please see [Block.io PHP Docs](https://block.io/api/simple/php) for details on available calls.

