<?php

require __DIR__ . '/../Crimp.php';

// Any object can be passed, but it must have a `Url` property which the library will use.
// Objects are particularly useful when you need to track extra information for each request.
class RequestUrl
{
	public string $Url;

	public function __construct( string $url )
	{
		$this->Url = $url;
	}
}

$Crimp = new Crimp( 'CrimpCallback' );
$Crimp->CurlOptions[ CURLOPT_FOLLOWLOCATION ] = 1;

$Crimp->Add( new RequestUrl( 'https://cloudflare.com' ) );
$Crimp->Add( new RequestUrl( 'https://news.ycombinator.com' ) );
$Crimp->Add( new RequestUrl( 'https://www.google.com' ) );
$Crimp->Add( new RequestUrl( 'https://www.yahoo.com' ) );

$Crimp->Go();

// $Url here will be the RequestUrl object that was queued
function CrimpCallback( CurlHandle $Handle, string $Data, RequestUrl $Request ) : void
{
	preg_match( '/<title[^>]*>(.*?)<\/title>/', $Data, $Title );
	
	$Time = curl_getinfo( $Handle, CURLINFO_TOTAL_TIME );

	printf(
		"%.4f | %-30s | %s (original: %s)\n",
		$Time,
		substr( $Title[ 1 ], 0, 30 ),
		curl_getinfo( $Handle, CURLINFO_EFFECTIVE_URL ),
		$Request->Url
	);
}
