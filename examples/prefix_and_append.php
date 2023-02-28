<?php

require __DIR__ . '/../Crimp.php';

$Crimp = new Crimp( 'CrimpCallback' );
$Crimp->CurlOptions[ CURLOPT_FOLLOWLOCATION ] = 1;

// String to prepend to all URLs
$Crimp->UrlPrefix = 'https://example.com/?v=';

// String to append to all URLs
$Crimp->UrlAppend = '&another=value';

// These two properties all saving memory when queueing many requests
$Crimp->Add( '1' );
$Crimp->Add( '2' );
$Crimp->Add( '3' );
$Crimp->Add( '4' );

$Crimp->Go();

function CrimpCallback( CurlHandle $Handle, string $Data ) : void
{
	preg_match( '/<title[^>]*>(.*?)<\/title>/', $Data, $Title );
	
	$Time = curl_getinfo( $Handle, CURLINFO_TOTAL_TIME );

	printf(
		"%.4f | %-30s | %s\n",
		$Time,
		substr( $Title[ 1 ], 0, 30 ),
		curl_getinfo( $Handle, CURLINFO_EFFECTIVE_URL )
	);
}
