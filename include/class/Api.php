<?php

class Api
{

	static function checkAccess() {
		$ip = Nodeutil::getRemoteAddr();
		$ip = filter_var($ip, FILTER_VALIDATE_IP);
		global $_config;
		if ($_config['public_api'] == false && !in_array($ip, $_config['allowed_hosts'])) {
			api_err("private-api");
		}

	}


}
