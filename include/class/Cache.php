<?php

class Cache
{

	private static $path;
	private static $enabled = false;

	static function init() {
		self::$path = ROOT . "/tmp/cache";
		if(!file_exists(self::$path)) {
			self::$enabled = mkdir(self::$path, 0777);
		} else {
			self::$enabled = true;
		}
		_log("Cache: Init caching enabled=".self::$enabled, 5);
	}

	static function getCacheFile($key){
		return self::$path . "/" . base64_encode($key);
	}


	static function get($key, $default = null) {
		$cache_file = self::getCacheFile($key);
		_log("Cache:get $key file=$cache_file exists=".file_exists($cache_file)." callable=".is_callable($default), 5);
		if(file_exists($cache_file)) {
			$content = file_get_contents($cache_file);
			$res = json_decode($content, true);
			if($res) {
				return $res;
			}
		}
		if(is_callable($default)) {
			$res = call_user_func($default);
			return $res;
		} else {
			return $default;
		}
	}

	static function set($key, $value) {
		$cache_file = self::getCacheFile($key);
		$value = json_encode($value);
		_log("Cache:set $key file=$cache_file value=$value", 5);
		file_put_contents($cache_file, $value);
	}

	public static function clearOldFiles()
	{
		$cmd = "find ".self::$path." -mtime +1 -exec ls -al {} +";
		shell_exec($cmd);
	}

}

Cache::init();
