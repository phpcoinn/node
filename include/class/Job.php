<?php

class Job
{

	static function runJobs() {
		$time = date("H:i");
		_log("Job: Run job at time ".$time, 4);

		$hour = date("H");
		$min = date("i");

		if($hour == "00") {
			Nodeutil::runSingleProcess("php ".ROOT."cli/util.php correct-accounts");
		}
	}



}
