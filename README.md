# Crimp [![Packagist](https://img.shields.io/packagist/dt/xpaw/crimp.svg)](https://packagist.org/packages/xpaw/crimp)

A simple multi curl implementation, optimized for high concurrency.

This is practically a bare bones implemention.
Retrying, HTTP code checking and other stuff is up to the user.

Usage:

```php
$Crimp = new Crimp( function( CurlHandle $Handle, string $Data, $Request ) : void
{
	// $Handle is the cURL handle
	// $Data is the content of a cURL handle
	// $Request is whatever was queued
} );

// How many concurrent threads to use
$Crimp->Threads = 10;

// Set any curl option that are needed
$Crimp->CurlOptions[ CURLOPT_FOLLOWLOCATION ] = 1;

// Queue urls
$Crimp->Add( 'https://example.com/?v=1' );
$Crimp->Add( 'https://example.com/?v=2' );

// Queue an array, it must contain a `Url` key
$Crimp->Add( [ 'Url' => 'https://example.com/?v=3' ] );

// Queue an object, it must contain a `Url` property
class RequestUrl { public string $Url; }
$request = new RequestUrl();
$request->Url = 'https://example.com/?v=4';
$Crimp->Add( $request );

// Execute the requests
$Crimp->Go();
```

`CURLOPT_RETURNTRANSFER` is enabled by default. See [examples](examples/) folder for more.

If you need a fully featured multi cURL implemention, take a look at
[Zebra_cURL](https://github.com/stefangabos/Zebra_cURL) or [Guzzle](https://github.com/guzzle/guzzle) instead.
