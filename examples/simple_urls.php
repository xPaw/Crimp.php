<?php

require __DIR__ . '/../Crimp.php';

$TotalTime = 0.0;
$StartTime = microtime( true );

$Crimp = new Crimp( 'CrimpCallback' );
$Crimp->CurlOptions[ CURLOPT_FOLLOWLOCATION ] = 1;

$Crimp->Urls =
[
	'https://cloudflare.com',
	'https://news.ycombinator.com',
	'https://www.google.com',
	'https://www.yahoo.com',
];

$Crimp->Go();

$FinalTime = microtime( true ) - $StartTime;

printf( "\nExecution time: %.4f\n", $FinalTime );
printf( "Total cURL time: %.4f\n", $TotalTime );

// $Url here will be the original string that was passed to $Crimp->Urls
function CrimpCallback( CurlHandle $Handle, string $Data, string $Url ) : void
{
	global $TotalTime;

	preg_match( '/<title[^>]*>(.*?)<\/title>/', $Data, $Title );

	$Time = curl_getinfo( $Handle, CURLINFO_TOTAL_TIME );
	$TotalTime += $Time;

	printf(
		"%.4f | %-30s | %s (original: %s)\n",
		$Time,
		substr( $Title[ 1 ] ?? '', 0, 30 ),
		curl_getinfo( $Handle, CURLINFO_EFFECTIVE_URL ),
		$Url
	);
}
