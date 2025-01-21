<?php

require __DIR__ . '/../Crimp.php';

$Crimp = new Crimp( 'CrimpCallback' );
$Crimp->CurlOptions[ CURLOPT_FOLLOWLOCATION ] = 1;

// When queueing arrays, they must contain a `Url` key
$Crimp->Add( [ 'Url' => 'https://example.com' ] );

$Crimp->Go();

/** @param array{Url: string} $Request */
function CrimpCallback( CurlHandle $Handle, string $Data, array $Request ) : void
{
	preg_match( '/<title[^>]*>(.*?)<\/title>/', $Data, $Title );
	
	$Time = curl_getinfo( $Handle, CURLINFO_TOTAL_TIME );

	printf(
		"%.4f | %-30s | %s (original: %s)\n",
		$Time,
		substr( $Title[ 1 ] ?? '', 0, 30 ),
		curl_getinfo( $Handle, CURLINFO_EFFECTIVE_URL ),
		$Request[ 'Url' ]
	);
}
