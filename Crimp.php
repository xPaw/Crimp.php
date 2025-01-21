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
	 * @var array<string|int|object|array{Url: string}> Links to fetch.
	 *
	 * Any value in this array will be queued when `Go()` is called.
	 * This array is a left over for simplicity. Consider using `Add()` method.
	 */
	public array $Urls = [];

	/**
	 * @var callable(CurlHandle, string, string|int|object|array{Url: string}): void Callback to be called with the data of executed request
	 *
	 * Callback to be called with the data of executed request.
	 */
	public $Callback;

	/**
	 * @var null|callable(CurlHandle, string|int|object|array{Url: string}): void Callback to be called on every executed url
	 *
	 * Callback to be called on every executed url.
	 */
	public $NextUrlCallback;

	/**
	 * @var array<int, mixed> cURL options to be set on each handle
	 *
	 * @see https://php.net/curl_setopt
	 */
	public array $CurlOptions =
	[
		CURLOPT_RETURNTRANSFER => 1, // Always return a string instead of directly outputting.
		CURLOPT_ENCODING       => '', // Empty string tells cURL to send a header containing all supported encoding types.
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_CONNECTTIMEOUT => 10,
	];

	/** @var SplQueue<string|int|object|array{Url: string}> */
	private readonly SplQueue $Queue;

	/** @var WeakMap<object, string|int|object|array{Url: string}> */
	private WeakMap $CurrentHandles;

	/**
	 * Initializes a new instance of the Crimp class.
	 *
	 * @param callable(CurlHandle, string, string|int|object|array): void $Callback
	 */
	public function __construct( callable $Callback )
	{
		$this->Callback = $Callback;
		$this->CurrentHandles = new WeakMap();
		$this->Queue = new SplQueue();
	}

	/** @param callable(CurlHandle, string|int|object|array): void $Callback */
	public function SetNextUrlCallback( callable $Callback ) : void
	{
		$this->NextUrlCallback = $Callback;
	}

	/** @param string|int|object|array{Url: string} $Url */
	public function Add( string|int|array|object $Url ) : void
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

		$ShareHandle = curl_share_init();
		curl_share_setopt( $ShareHandle, CURLSHOPT_SHARE, CURL_LOCK_DATA_PSL );
		curl_share_setopt( $ShareHandle, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS );

		$MultiHandle = curl_multi_init( );

		while( $Threads-- > 0 )
		{
			$Handle = curl_init( );

			curl_setopt( $Handle, CURLOPT_SHARE, $ShareHandle );
			curl_setopt_array( $Handle, $this->CurlOptions );

			$this->NextUrl( $MultiHandle, $Handle );

			// Move things along while creating handles, otherwise with many threads there may be issues with SSL connections
			if( $Threads % 20 === 0 )
			{
				curl_multi_exec( $MultiHandle, $Running );
			}
		}

		unset( $Threads, $Count, $Url );

		$Repeats = 0;

		do
		{
			curl_multi_exec( $MultiHandle, $Running ); // libcurl = curl_multi_perform

			while( $Done = curl_multi_info_read( $MultiHandle ) )
			{
				/** @var CurlHandle $Handle */
				$Handle = $Done[ 'handle' ];
				$Data   = curl_multi_getcontent( $Handle );

				call_user_func( $this->Callback, $Handle, $Data, $this->CurrentHandles[ $Handle ] );

				curl_multi_remove_handle( $MultiHandle, $Handle );

				if( !$this->Queue->isEmpty() )
				{
					$this->NextUrl( $MultiHandle, $Handle );
					$Running = 1;
				}
				else
				{
					unset( $this->CurrentHandles[ $Handle ] );
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
				/** @var array{Url: string} $Obj */
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
					/** @var object{Url: string} $Obj */
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

		$this->CurrentHandles[ $Handle ] = $Obj;
	}
}
