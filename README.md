Block.io PHP
===========

**Current Release**: 3.0.0  
**09/10/22**: Support PHP 8.0 and PHP 8.1 only. Simplify dependency to use `doersf/bitcoin-php` package.  
**06/09/21**: Minor. Adds support for dynamic decryption algorithms.  
**06/01/21**: Maintenance release. Fix Windows missing CACERT error.  
**05/27/21**: BREAKING CHANGES. Transaction interfaces have changed. Test thoroughly before use.  

PHP wrapper for [Block.io](https://block.io/) for use with [Dogecoin](http://dogecoin.com/), [Bitcoin](http://bitcoin.org/), and [Litecoin](http://litecoin.org). Simple abstraction layer on top of existing API interfaces, and automatic JSON decoding on response.  

### Requirements

This library requires: gmp, cURL, mbstring, and bcmath extensions. Tested on PHP `8.0`, and PHP `8.1`.

### Warning

Make sure all PHPUnit tests pass before using this library on your system.

### Usage

For PHP `8.0+`, install via [Composer](https://getcomposer.org/):  
```sh
$ composer require block_io-php/block_io-php
```

For PHP `7.2, 7.3, 7.4`, use `v2.0.2` of this library. Install via [Composer](https://getcomposer.org/):  

Your `composer.json` will include the forked `bitcoin-php` repository specification:  
```sh
{
    "require":{
	"block_io-php/block_io-php": "2.0.2",
	"bitwasp/bitcoin": "dev-minimal"
    },
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/doersf/bitcoin-php.git"
        }
    ]
}
```

See examples/ and https://block.io/api/simple/php for basic usage.

Please see [Block.io PHP Docs](https://block.io/api/simple/php) for details on available calls.

