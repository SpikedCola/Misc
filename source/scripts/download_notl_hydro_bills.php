<?php declare(strict_types=1);

$cookie = 'JSESSIONID='; // fill this in and set year to start below

$url = 'https://myaccount.notlhydro.com/CC/ViewEBill?billDate=';

for ($year = 2020; $year > 2000; $year--) {
	for ($month = 12; $month > 0; $month--) {
		for ($day = 31; $day > 0; $day--) {
			sleep(1); // be nice
			$ymd = sprintf("%04d-%02d-%02d", $year, $month, $day);
			echo "try {$ymd} ...";
			$response = \Request::Get($url.$ymd, [], [ CURLOPT_COOKIE => $cookie ]);
			$code = $response->getResponseCode();
			if (302 === $code &&  "/CC/static/FileNotFound.html" === $response->responseHeaders['location']) {
				// didnt get it, try next date
				echo " no\n";
			}
			elseif (200 === $code) {
				echo " got it!";
				$disp = $response->responseHeaders['content-disposition'];
				$filename = str_replace('inline; filename=', '', $disp);
				if (!$filename) {
					echo " failed to get filename, disp was {$disp}\n";
					var_dump($response);
					die;
				}
				echo " save file {$filename} ...";
				file_put_contents($filename, $response->content);
				echo " ok, go to next month\n\n";
				// stop procesing days, move to the next month
				continue 2;
			}
			else {
				echo " didnt get 200, or 302 to FileNotFound:\n";
				var_dump($response); 
				die;
			}			
		}
	}
}

// copied these, could be included and split out
abstract class Request {
	static $cacheDir = __DIR__.'/../request_cache/';
	
	static function FilenameFromUrl(string $url) : string {
		return static::$cacheDir.md5($url);
	}
	
	/**
	 * Check cache dir for $url. If found, check expiry. If expired, unlink.
	 * 
	 * Returns unserialized cached response if found and valid, otherwise null.
	 * 
	 * @param string $url
	 * @param int $cacheExpiry
	 * @return mixed Response object unserialized, or null if file doesnt exist or was too old.
	 * @throws RuntimeException
	 */
	static function CheckCacheForUrl(string $url, int $cacheExpiry = 86400) {
		static::CheckMakeCacheDir();
		$filename = static::FilenameFromUrl($url);
		if (file_exists($filename)) {
			$mtime = filemtime($filename);
			if (false === $mtime) {
				throw new \RuntimeException("false === mtime({$filename})");
			}
			if (time() - $mtime >= $cacheExpiry) {
				// purge url from cache. new entry will be created below
				unlink($filename);
			}
			else {
				// use file from cache
				$response = unserialize(file_get_contents($filename));
				// flag as cached
				$response->cached = true;
				return $response;
			}
		}
		else {
			// file doesnt exist, will be created below
		}
		
		return null;
	}
	
	/**
	 * Perform cached POST
	 * 
	 * @param string $url
	 * @param array $postData
	 * @param array $extraCurlOptions
	 * @return \Response
	 */
	static function Post(string $url, $postData, array $headers = [], array $extraCurlOptions = []) : Response {
		return static::Get($url, $headers, [
		   CURLOPT_POSTFIELDS => $postData
		] + $extraCurlOptions);
	}
	
	/**
	 * Perform cached GET
	 * 
	 * @param string $url
	 * @param int $cacheExpiry
	 * @param array $extraCurlOptions
	 * 
	 * Used to return \Response but this conflicted with TicketMasterRequest::GetCached() returning TicketMasterResponse...
	 */
	static function GetCached(string $url, int $cacheExpiry = 86400, array $headers = [], array $extraCurlOptions = []) {
		$filename = basename(static::FilenameFromUrl($url)); // helpful for debugging
		if ($cachedResponse = static::CheckCacheForUrl($url, $cacheExpiry)) {
			echo "[d] cache hit ({$filename})\n";
			return $cachedResponse;
		}
		echo "[d] cache miss ({$filename})\n";

		$r = static::Get($url, $headers, $extraCurlOptions);
		
		// should probably only cache if its a 200 response
		if (200 === $r->info['http_code']) {
			file_put_contents(static::FilenameFromUrl($url), serialize($r));
		}
		
		return $r;
	}
	
	/**
	 * Perform cached GET using an external curl application. Windows only.
	 * 
	 * @param string $url
	 * @param int $cacheExpiry
	 * @param array $extraCurlOptions
	 * 
	 * Used to return \Response but this conflicted with TicketMasterRequest::GetCached() returning TicketMasterResponse...
	 */
	static function GetCachedWithExternalCurlImpersonate(string $url, int $cacheExpiry = 86400, array $headers = [], array $extraCurlOptions = []) {
		$filename = basename(static::FilenameFromUrl($url)); // helpful for debugging
		if ($cachedResponse = static::CheckCacheForUrl($url, $cacheExpiry)) {
			echo "[d] cache hit ({$filename})\n";
			return $cachedResponse;
		}
		echo "[d] cache miss ({$filename})\n";

		$cwd = __DIR__.'/../../curl-impersonate/curl-impersonate-win/'; 
		$p = new Symfony\Component\Process\Process([
		    'curl_chrome110.bat',
		    $url
		], $cwd);
		$statusCode = $p->run();
		$output = $p->getOutput();
		if (0 === $statusCode) {
			$httpCode = 200; // assume it was ok
		}
		else {
			$httpCode = 0;
		}
		$r = new Response($output, [], '', [ 'http_code' => $httpCode ]);
		
		// should probably only cache if its a 200 response
		if (200 === $r->info['http_code']) {
			file_put_contents(static::FilenameFromUrl($url), serialize($r));
		}
		
		return $r;
	}
	
	/**
	 * Perform GET.
	 * 
	 * Removed return type here, want to allow extension classes
	 * to return a different kind of response by calling Get. They cant
	 * if we explicitly define return type here.
	 * 
	 * @param string $url
	 * @param array $extraCurlOptions
	 * @return \Response
	 */
	static function Get(string $url, array $headers = [], array $extraCurlOptions = []) {
		$ch = curl_init($url);
		$responseHeaders = [];
		
		// convert "key" => "value" to "key: value"
		$httpHeaders = [];
		foreach ($headers as $k => $v) {
			$httpHeaders[] = "{$k}: {$v}";
		}
		
		curl_setopt_array($ch, [
		    CURLOPT_ENCODING => 'gzip',
		    CURLOPT_RETURNTRANSFER => true,
		    CURLOPT_HTTPHEADER => $httpHeaders,
		    CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$responseHeaders, $url) {
			    $len = strlen($header);
			    $headerParts = explode(':', $header, 2);
			    if (count($headerParts) < 2) {
				    // ignore invalid headers
				    return $len;
			    }

			    $key = strtolower(trim($headerParts[0]));
			    $value = trim($headerParts[1]);

			    // single header values are stored as "key => value".
			    // if we find more than one value for a key,  
			    // convert to "key => [ value, ... ]", but only 
			    // if its not a duplicate. probably some better way
			    // to do this, would be nice if it wasnt an anon function...
			    // maybe change from abstract to singleton and non-static.
			    if (isset($responseHeaders[$key])) {
				    if (is_array($responseHeaders[$key])) {
					    if (!in_array($value, $responseHeaders[$key])) {
						$responseHeaders[$key][] = $value;
						// curiosity
						// have seen this w/ 'connection' header, values [keep-alive,Transfer-Encoding]
						//trigger_error("response header has multiple values for '{$url}' '{$key}': ".print_r($responseHeaders, true), E_USER_NOTICE);
					    }
				    }
				    else {
					    $previousValue = $responseHeaders[$key];
					    if ($value !== $previousValue) {
						$responseHeaders[$key] = [
						    $previousValue,
						    $value
						];
						// curiosity
						//trigger_error("response header has multiple values for '{$url}' '{$key}': ".print_r($responseHeaders, true), E_USER_NOTICE);
					    }
				    }
			    }
			    else {
				    $responseHeaders[$key] = $value;
			    }

			    return $len;
		    }
		] + $extraCurlOptions);

		$content = curl_exec($ch);
		$error = curl_error($ch);
		$info = curl_getinfo($ch);
		
		// tack post data onto $info if it was set (helpful for debugging)
		if (isset($extraCurlOptions[CURLOPT_POSTFIELDS])) {
			$info['post_data'] = $extraCurlOptions[CURLOPT_POSTFIELDS];
		}
		if ($httpHeaders) {
			$info['request_headers'] = $httpHeaders;
		}
		
		curl_close($ch);
		
		return new Response($content, $responseHeaders, $error, $info);
	}
	
	static function CheckMakeCacheDir() {
		if (!is_dir(static::$cacheDir)) {
			mkdir(static::$cacheDir, 0777, true);
			if (!is_dir(static::$cacheDir)) {
				throw new \RuntimeException('cachedir doesnt exist and couldnt be created: '.static::$cacheDir);
			}
		}
		
	}
	
}


class Response {
	public $content = null;
	
	public $responseHeaders = [];
	
	public $error = null;
	
	public $info = [];
	
	public $cached = false;
	
	public function __construct($content, array $responseHeaders, string $error, array $info, bool $cached = false) {
		$this->content = $content;
		$this->responseHeaders = $responseHeaders;
		$this->error = $error;
		$this->info = $info;
		$this->cached = $cached;
	}
	
	public function getResponseCode() : ?int{
		if (isset($this->info['http_code'])) {
			return (int)$this->info['http_code'];
		}
		return null;			
	}
}