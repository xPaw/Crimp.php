<?php
/**
 * A dead simple multi curl implementation.
 *
 * GitHub: {@link https://github.com/xPaw/Crimp.php}
 * Website: {@link https://xpaw.me}
 *
 * @author Pavel Djundik
 * @license MIT
 */
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
	 * @var array Links to fetch.
	 *
	 * Links are removed from this array during Go() function's runtime.
	 * A field is used to conserve memory (passing an argument does a copy, and byref is slow).
	 *
	 * If the array contains arrays or objects, 'Url' property will be accessed.
	 * First element in the array is used to determine the type.
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
	
	private $CurrentType;
	private $CurrentHandles = [];
	
	/**
	 * Runs the multi curl
	 */
	public function Go( )
	{
		if( isset( $this->CurlOptions[ CURLOPT_URL ] ) )
		{
			throw new \InvalidArgumentException( 'cURL options must not contain CURLOPT_URL, it is set during run time' );
		}
		
		$Count = count( $this->Urls );
		
		if( $Count === 0 )
		{
			throw new \InvalidArgumentException( 'No URLs to fetch' );
		}
		
		if( $this->PreserveOrder )
		{
			$this->Urls = array_reverse( $this->Urls );
		}
		
		$this->CurrentType = gettype( reset( $this->Urls ) );
		$Threads = $this->Threads;
		
		if( $Threads > $Count || $Threads <= 0 )
		{
			$Threads = $Count;
		}
		
		$Master = curl_multi_init( );
		
		while( $Threads-- > 0 )
		{
			$Count--;
			
			$Handle = curl_init( );
			
			curl_setopt_array( $Handle, $this->CurlOptions );
			
			$this->NextUrl( $Master, $Handle );
		}
		
		do
		{
			if( curl_multi_exec( $Master, $Running ) !== CURLM_OK )
			{
				throw new \RuntimeException( 'curl_multi_exec failed. error: ' .
					( function_exists( 'curl_multi_errno' ) ? curl_multi_errno() : '???' ) );
			}
			
			while( $Done = curl_multi_info_read( $Master ) )
			{
				$Handle = $Done[ 'handle' ];
				$Data   = curl_multi_getcontent( $Handle );
				
				call_user_func( $this->Callback, $Handle, $Data, $this->CurrentHandles[ (int)$Handle ] );
				
				curl_multi_remove_handle( $Master, $Handle );
				
				if( $Count > 0 )
				{
					$Running = true;
					
					$Count--;
					
					$this->NextUrl( $Master, $Handle );
				}
				else
				{
					curl_close( $Handle );
					
					unset( $this->CurrentHandles[ (int)$Handle ] );
				}
			}
			
			if( $Running && curl_multi_select( $Master, 0.1 ) === -1 )
			{
				usleep( 100 );
			}
		}
		while( $Running );
		
		curl_multi_close( $Master );
	}
	
	private function NextUrl( $Master, $Handle )
	{
		$Obj = array_pop( $this->Urls );
		
		switch( $this->CurrentType )
		{
			case 'object':
				$Url = $Obj->Url;
				break;
			
			case 'array':
				$Url = $Obj[ 'Url' ];
				break;
			
			default:
				$Url = (string)$Obj;
		}
		
		curl_setopt( $Handle, CURLOPT_URL, $this->UrlPrefix . $Url );
		curl_multi_add_handle( $Master, $Handle );
		
		$this->CurrentHandles[ (int)$Handle ] = $Obj;
	}
}
