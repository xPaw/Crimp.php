<?php
declare(strict_types=1);

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
	public string $UrlPrefix = '';

	/**
	 * @var string String to append to all URLs
	 *
	 * Use this to save memory when sending a lot of requests to same host
	 */
	public string $UrlAppend = '';

	/**
	 * @var int How many concurrent requests should be going at the same time
	 */
	public int $Threads = 10;

	/**
	 * @var bool Set to true to preserve order of passed in $Urls
	 *
	 * array_pop is used for better performance, thus the requests are executed backwards
	 * Setting this to true performs array_reverse on the urls
	 */
	public bool $PreserveOrder = false;

	/**
	 * @var array Links to fetch.
	 *
	 * Links are removed from this array during Go() function's runtime.
	 * A field is used to conserve memory (passing an argument does a copy, and byref is slow).
	 *
	 * If the array contains arrays or objects, 'Url' property will be accessed.
	 * First element in the array is used to determine the type.
	 */
	public array $Urls = [];

	/**
	 * @var callable Callback to be called on every executed url
	 *
	 * Callback
	 */
	public $Callback;

	/**
	 * @var callable Callback to be called on every executed url
	 *
	 * Callback
	 */
	public $NextUrlCallback = null;

	/**
	 * @var array cURL options to be set on each handle
	 * @see https://php.net/curl_setopt
	 */
	public array $CurlOptions =
	[
		CURLOPT_ENCODING       => '',
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => 1,
	];

	/**
	 * Initializes a new instance of the Crimp class
	 *
	 * @param callable $Callback Callback
	 *
	 * @throws RuntimeException
	 */
	public function __construct( callable $Callback )
	{
		$this->Callback = $Callback;
	}

	public function SetNextUrlCallback( callable $Callback ) : void
	{
		$this->NextUrlCallback = $Callback;
	}

	/**
	 * @var string|null
	 */
	private $CurrentType;

	/**
	 * @var array
	 */
	private $CurrentHandles = [];

	/**
	 * Runs the multi curl
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 */
	public function Go() : void
	{
		if( isset( $this->CurlOptions[ CURLOPT_URL ] ) )
		{
			throw new InvalidArgumentException( 'cURL options must not contain CURLOPT_URL, it is set during run time' );
		}

		$Count = count( $this->Urls );

		if( $Count === 0 )
		{
			throw new InvalidArgumentException( 'No URLs to fetch' );
		}

		if( $this->PreserveOrder )
		{
			$this->Urls = array_reverse( $this->Urls );
		}

		$FirstHandle = reset( $this->Urls );

		if( is_a( $FirstHandle, 'CurlHandle' ) )
		{
			// Since PHP8, curl_init returns CurlHandle instead of a resource
			$this->CurrentType = 'resource';
		}
		else
		{
			$this->CurrentType = gettype( $FirstHandle );
		}

		unset( $FirstHandle );

		$Threads = $this->Threads;

		if( $Threads > $Count || $Threads <= 0 )
		{
			$Threads = $Count;
		}

		$MultiHandle = curl_multi_init( );

		while( $Threads-- > 0 )
		{
			$Count--;

			if( $this->CurrentType === 'resource' )
			{
				$this->NextUrl( $MultiHandle );

				continue;
			}

			$Handle = curl_init( );

			curl_setopt_array( $Handle, $this->CurlOptions );

			$this->NextUrl( $MultiHandle, $Handle );
		}

		$Repeats = 0;

		do
		{
			if( curl_multi_exec( $MultiHandle, $Running ) !== CURLM_OK )
			{
				throw new RuntimeException( 'curl_multi_exec failed. error: ' . curl_multi_errno( $MultiHandle ) );
			}

			while( $Done = curl_multi_info_read( $MultiHandle ) )
			{
				$Handle = $Done[ 'handle' ];
				$Data   = curl_multi_getcontent( $Handle );

				call_user_func( $this->Callback, $Handle, $Data, $this->CurrentHandles[ (int)$Handle ] );

				curl_multi_remove_handle( $MultiHandle, $Handle );

				if( $Count > 0 )
				{
					$Running = 1;

					$Count--;

					$this->NextUrl( $MultiHandle, $Handle );
				}
				else
				{
					unset( $this->CurrentHandles[ (int)$Handle ] );
				}
			}

			if( $Running )
			{
				$Descriptors = curl_multi_select( $MultiHandle, 0.1 );

				if( $Descriptors === -1 && curl_multi_errno( $MultiHandle ) !== CURLM_OK )
				{
					throw new RuntimeException( 'curl_multi_select failed. error: ' . curl_multi_errno( $MultiHandle ) );
				}

				// count number of repeated zero numfds
				if( $Descriptors === 0 )
				{
					if( ++$Repeats > 1 )
					{
						usleep( 100 );
					}
				}
				else
				{
					$Repeats = 0;
				}
			}
		}
		while( $Running );

		curl_multi_close( $MultiHandle );
	}

	private function NextUrl( CurlMultiHandle $MultiHandle, CurlHandle $Handle = null ) : void
	{
		$Obj = array_pop( $this->Urls );

		switch( $this->CurrentType )
		{
			case 'resource':
				curl_multi_add_handle( $MultiHandle, $Obj );
				$this->CurrentHandles[ (int)$Obj ] = null;
				return;

			case 'object':
				$Url = $Obj->Url;
				break;

			case 'array':
				$Url = $Obj[ 'Url' ];
				break;

			default:
				$Url = (string)$Obj;
		}

		if( $Handle !== null )
		{
			curl_setopt( $Handle, CURLOPT_URL, $this->UrlPrefix . $Url . $this->UrlAppend );
		}

		if( $this->NextUrlCallback !== null )
		{
			call_user_func( $this->NextUrlCallback, $Handle, $Obj, $Url );
		}

		if( $Handle !== null )
		{
			curl_multi_add_handle( $MultiHandle, $Handle );
		}

		$this->CurrentHandles[ (int)$Handle ] = $Obj;
	}
}
