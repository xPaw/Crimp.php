<?php

class Crimp
{
	public $UrlPrefix = '';
	public $Threads = 10;
	public $PreserveOrder = false;
	public $CurlOptions =
	[
		CURLOPT_ENCODING       => 'gzip',
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => 1,
	];
	
	public function Go( array $Urls, callable $Callback )
	{
		if( isset( $this->CurlOptions[ CURLOPT_URL ] ) )
		{
			throw new \InvalidArgumentException( 'cURL options must not contain CURLOPT_URL, it is set during run time' );
		}
		
		if( $this->PreserveOrder )
		{
			$Urls = array_reverse( $Urls );
		}
		
		$Master = curl_multi_init( );
		
		$Count = count( $Urls );
		
		if( $this->Threads > $Count )
		{
			$this->Threads = $Count;
		}
		
		for( $i = 0; $i < $this->Threads; $i++ )
		{
			$Count--;
			
			$Handle = curl_init( );
			
			curl_setopt_array( $Handle, $this->CurlOptions );
			curl_setopt( $Handle, CURLOPT_URL, $this->UrlPrefix . array_pop( $Urls ) );
			curl_multi_add_handle( $Master, $Handle );
		}
		
		//curl_multi_setopt( $Master, CURLMOPT_PIPELINING, 1 );
		// todo review
		curl_multi_setopt( $Master, CURLMOPT_MAXCONNECTS, $this->Threads * 2 );
		
		do
		{
			while( ( $Exec = curl_multi_exec( $Master, $Running ) ) === CURLM_CALL_MULTI_PERFORM );
			
			if( $Exec !== CURLM_OK )
			{
				throw new \RuntimeException( 'curl_multi_exec failed: ' . $Exec . ' - ' . curl_multi_strerror( $Exec ) );
			}
			
			while( $Done = curl_multi_info_read( $Master ) )
			{
				$Handle = $Done[ 'handle' ];
				$Data   = curl_multi_getcontent( $Handle );
				
				call_user_func( $Callback, $Handle, $Data );
				
				curl_multi_remove_handle( $Master, $Handle );
				
				if( $Count > 0 )
				{
					$Count--;
					
					curl_setopt( $Handle, CURLOPT_URL, $this->UrlPrefix . array_pop( $Urls ) );
					curl_multi_add_handle( $Master, $Handle );
				}
				else
				{
					curl_close( $Handle );
				}
			}
			
			// todo review
			if( !$Running )
			{
				$Running = $Count > 0;
			}
			
			if( $Running )
			{
				if( curl_multi_select( $Master, 1.0 ) === -1 )
				{
					usleep( 500 );
				}
			}
		}
		while( $Running );
		
		curl_multi_close( $Master );
	}
}
