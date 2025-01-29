<?php

require __DIR__ . '/../Crimp.php';

$Crimp = new Crimp( 'CrimpCallback' );
$Crimp->CurlOptions[ CURLOPT_FOLLOWLOCATION ] = 1;

$Crimp->Add( [
	'Url' => 'https://example.com/?v=1',
	'Count' => 1,
] );

$Crimp->Go();

// $Url here will be the original array that was passed to Add()
function CrimpCallback( CurlHandle $Handle, string $Data, array $OriginalArray ) : void
{
	global $Crimp;

	echo curl_getinfo( $Handle, CURLINFO_EFFECTIVE_URL ) . PHP_EOL;

	// Queue again
	if( $OriginalArray[ 'Count' ] < 4 )
	{
		$NewCount = $OriginalArray[ 'Count' ] + 1;

		$Crimp->Add( [
			'Url' => 'https://example.com/?v=' . $NewCount,
			'Count' => $NewCount,
		] );
	}
}
