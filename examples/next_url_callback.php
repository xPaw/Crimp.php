<?php

require __DIR__ . '/../Crimp.php';

$Crimp = new Crimp( 'CrimpCallback' );

// This callback is called on every executed url just before it is executed
$Crimp->NextUrlCallback = 'NextUrlCallback';

$Crimp->Add( '1' );
$Crimp->Add( '2' );
$Crimp->Add( '3' );
$Crimp->Add( '4' );

$Crimp->Go();

function CrimpCallback( CurlHandle $Handle, string $Data, string $Request ) : void
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

function NextUrlCallback( CurlHandle $Handle, string $Request ) : void
{
	// Use this callback to set any unique options for the request, for example when rotating proxies
	// Do keep in mind that because cURL handles are rotated, if one option is set,
	// it must be (re)set for every request

	if( $Request === '3' )
	{
		curl_setopt( $Handle, CURLOPT_URL, 'https://example.com/test/path' );
	}
	else
	{
		curl_setopt( $Handle, CURLOPT_URL, 'https://example.com/?v=' . $Request );
	}
}
