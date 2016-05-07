<?php

require __DIR__ . '/Crimp.php';

$TotalTime = 0.0;
$StartTime = microtime( true );

$Crimp = new Crimp;
$Crimp->CurlOptions[ CURLOPT_FOLLOWLOCATION ] = 1;
$Crimp->Go([
	'https://cloudflare.com',
	'https://news.ycombinator.com',
	'https://www.google.com',
	'https://www.yahoo.com',
], 'CrimpCallback');

$FinalTime = microtime( true ) - $StartTime;

printf( "\nExecution time: %.4f\n", $FinalTime );
printf( "Total cURL time: %.4f\n", $TotalTime );

function CrimpCallback( $Handle, $Data )
{
	preg_match( '/<title[^>]*>(.*?)<\/title>/', $Data, $Title );
	
	global $TotalTime; // hurr durr
	
	$Time = curl_getinfo( $Handle, CURLINFO_TOTAL_TIME );
	$TotalTime += $Time;
	
	printf(
		"%.4f | %-30s | %s\n",
		$Time,
		substr( $Title[ 1 ], 0, 30 ),
		curl_getinfo( $Handle, CURLINFO_EFFECTIVE_URL )
	);
}
