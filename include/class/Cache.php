<?php

class Cache
{

	public static $path;
	private static $enabled = false;

	static function init() {
		self::$path = ROOT . "/tmp/cache";
		if(!file_exists(self::$path)) {
			self::$enabled = mkdir(self::$path, 0777);
			chown(self::$path, "www-data");
			chgrp(self::$path, "www-data");
		} else {
			self::$enabled = true;
		}
		_log("Cache: Init caching enabled=".self::$enabled, 5);
	}

	static function getCacheFile($key){
		return self::$path . "/" . $key;
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
		$res = file_put_contents($cache_file, $value);
		_log("Cache:set $key file=$cache_file value=$value res=$res", 5);
	}

	static function exists ($key) {
		$cache_file = self::getCacheFile($key);
		return file_exists($cache_file);
	}

	static function remove($key) {
		$cache_file = self::getCacheFile($key);
		if(file_exists($cache_file)) {
			unlink($cache_file);
		}
	}

	public static function clearOldFiles()
	{
		$cmd = "find ".self::$path." -mtime +1 -exec ls -al {} +";
		shell_exec($cmd);
	}

	public static function resetCache() {
		$cmd = "rm -rf ".self::$path = ROOT . "/tmp/cache";
		shell_exec($cmd);
	}

}

Cache::init();
