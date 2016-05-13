<?php

class Crimp
{
	/**
	 * @var string String to prepend to all URLs
	 *
	 * Use this to save memory when sending a lot of requests to same host
	 */
	public $UrlPrefix = '';
	
	/**
	 * @var int How many concurrent requests should be going at the same time
	 */
	public $Threads = 10;
	
	/**
	 * @var bool Set to true to preserve order of passed in $Urls
	 *
	 * array_pop is used for better performance, thus the requests are executed backwards
	 * Setting this to true performs array_reverse on the urls
	 */
	public $PreserveOrder = false;
	
	/**
	 * @var array Links to fetch
	 *
	 * Links are removed from this array during Go() function's runtime
	 * A field is used to conserve memory (passing an argument does a copy, and byref is slow)
	 */
	public $Urls = [];
	
	/**
	 * @var callable Callback to be called on every executed url
	 *
	 * Callback 
	 */
	public $Callback;
	
	/**
	 * @var array cURL options to be set on each handle
	 * @see https://php.net/curl_setopt
	 */
	public $CurlOptions =
	[
		CURLOPT_ENCODING       => 'gzip',
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => 1,
	];
	
	/**
	 * Initializes a new instance of the Crimp class
	 *
	 * @param callback $Callback Callback
	 */
	public function __construct( callable $Callback )
	{
		$this->Callback = $Callback;
	}
	
	/**
	 * Runs the multi curl
	 */
	public function Go( )
	{
		if( isset( $this->CurlOptions[ CURLOPT_URL ] ) )
		{
			throw new \InvalidArgumentException( 'cURL options must not contain CURLOPT_URL, it is set during run time' );
		}
		
		if( $this->PreserveOrder )
		{
			$this->Urls = array_reverse( $this->Urls );
		}
		
		$Count = count( $this->Urls );
		$Threads = $this->Threads;
		
		if( $Threads > $Count )
		{
			$Threads = $Count;
		}
		
		$Master = curl_multi_init( );
		
		while( $Threads-- > 0 )
		{
			$Count--;
			
			$Handle = curl_init( );
			
			curl_setopt_array( $Handle, $this->CurlOptions );
			curl_setopt( $Handle, CURLOPT_URL, $this->UrlPrefix . array_pop( $this->Urls ) );
			curl_multi_add_handle( $Master, $Handle );
		}
		
		do
		{
			curl_multi_exec( $Master, $Running );
			
			while( $Done = curl_multi_info_read( $Master ) )
			{
				$Handle = $Done[ 'handle' ];
				$Data   = curl_multi_getcontent( $Handle );
				
				call_user_func( $this->Callback, $Handle, $Data );
				
				curl_multi_remove_handle( $Master, $Handle );
				
				if( $Count > 0 )
				{
					$Running = true;
					
					$Count--;
					
					curl_setopt( $Handle, CURLOPT_URL, $this->UrlPrefix . array_pop( $this->Urls ) );
					curl_multi_add_handle( $Master, $Handle );
				}
				else
				{
					curl_close( $Handle );
				}
				
				if( $Running )
				{
					curl_multi_exec( $Master, $Running );
					curl_multi_select( $Master, 0 );
				}
			}
			
			if( $Running )
			{
				while( curl_multi_select( $Master, 0.1 ) === 0 );
			}
		}
		while( $Running );
		
		curl_multi_close( $Master );
	}
}
