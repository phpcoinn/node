<?php
global $_config, $db;
// when db schema modifications are done, this function is run.
// database will be only initialized on installation / restore
$dbversion = intval(@$_config['dbversion']);

_log("Check db schema current_version=$dbversion check_version=".DB_SCHEMA_VERSION, 2);

if ($dbversion < DB_SCHEMA_VERSION) {
    Nodeutil::checkDBSchema(false);
}
