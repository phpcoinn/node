<?php

class Config
{

	static function isSync() {
		global $db;
		$res = $db->single("select val from config where cfg='sync'");
		return intval($res);
	}

	static function setSync($val) {
		global $db;
		$db->run("UPDATE config SET val=:val WHERE cfg='sync'", ["val"=>$val]);
	}


}
