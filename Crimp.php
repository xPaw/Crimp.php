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
	 * @var array<string|array|object> Links to fetch.
	 *
	 * Links are removed from this array during Go() function's runtime.
	 * A field is used to conserve memory (passing an argument does a copy, and byref is slow).
	 *
	 * If the array contains arrays or objects, 'Url' property will be accessed.
	 * First element in the array is used to determine the type.
	 */
	public array $Urls = [];

	/**
	 * @var callable Callback to be called with the data of executed request
	 *
	 * Callback
	 */
	public $Callback;

	/**
	 * @var ?callable Callback to be called on every executed url
	 *
	 * Callback
	 */
	public $NextUrlCallback;

	/**
	 * @var array cURL options to be set on each handle
	 *
	 * @see https://php.net/curl_setopt
	 */
	public array $CurlOptions =
	[
		CURLOPT_ENCODING       => '',
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => 1,
	];

	/** @var SplQueue<string|array|object> */
	private SplQueue $Queue;

	/** @var array<int, string|array|object> */
	private array $CurrentHandles = [];

	/**
	 * Initializes a new instance of the Crimp class.
	 */
	public function __construct( callable $Callback )
	{
		$this->Callback = $Callback;
		$this->Queue = new SplQueue();
	}

	public function SetNextUrlCallback( callable $Callback ) : void
	{
		$this->NextUrlCallback = $Callback;
	}

	public function Add( string|array|object $Url ) : void
	{
		$this->Queue->enqueue( $Url );
	}

	/**
	 * Runs the multi curl.
	 */
	public function Go() : void
	{
		if( isset( $this->CurlOptions[ CURLOPT_URL ] ) )
		{
			throw new InvalidArgumentException( 'cURL options must not contain CURLOPT_URL, it is set during run time' );
		}

		// Legacy?
		foreach( $this->Urls as $Url )
		{
			$this->Queue->enqueue( $Url );
		}

		$this->Urls = [];

		$Count = $this->Queue->count();

		if( $Count === 0 )
		{
			throw new InvalidArgumentException( 'No URLs to fetch' );
		}

		$Threads = $this->Threads;

		if( $Threads > $Count || $Threads <= 0 )
		{
			$Threads = $Count;
		}

		$MultiHandle = curl_multi_init( );

		while( $Threads-- > 0 )
		{
			$Handle = curl_init( );

			curl_setopt_array( $Handle, $this->CurlOptions );

			$this->NextUrl( $MultiHandle, $Handle );
		}

		$Repeats = 0;

		do
		{
			curl_multi_exec( $MultiHandle, $Running ); // libcurl = curl_multi_perform

			while( $Done = curl_multi_info_read( $MultiHandle ) )
			{
				$Handle = $Done[ 'handle' ];
				$Data   = curl_multi_getcontent( $Handle );

				call_user_func( $this->Callback, $Handle, $Data, $this->CurrentHandles[ (int)$Handle ] );

				curl_multi_remove_handle( $MultiHandle, $Handle );

				if( !$this->Queue->isEmpty() )
				{
					$this->NextUrl( $MultiHandle, $Handle );
					$Running = 1;
				}
				else
				{
					unset( $this->CurrentHandles[ (int)$Handle ] );
				}
			}

			if( $Running )
			{
				$Descriptors = curl_multi_select( $MultiHandle, 0.1 ); // libcurl = curl_multi_wait

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
	}

	private function NextUrl( CurlMultiHandle $MultiHandle, CurlHandle $Handle ) : void
	{
		$Obj = $this->Queue->dequeue();
		$Url = null;

		switch( gettype( $Obj ) )
		{
			case 'array':
				/** @var array $Obj */
				$Url = $Obj[ 'Url' ];
				break;

			case 'object':
				if( is_a( $Obj, 'CurlHandle' ) )
				{
					/** @var CurlHandle $Obj */
					$Handle = $Obj;
				}
				else
				{
					/** @var object $Obj */
					$Url = $Obj->Url;
				}

				break;

			default:
				$Url = $Obj;
		}

		if( $Url !== null )
		{
			curl_setopt( $Handle, CURLOPT_URL, $this->UrlPrefix . $Url . $this->UrlAppend );
		}

		if( $this->NextUrlCallback !== null )
		{
			call_user_func( $this->NextUrlCallback, $Handle, $Obj );
		}

		curl_multi_add_handle( $MultiHandle, $Handle );

		$this->CurrentHandles[ (int)$Handle ] = $Obj;
	}
}
