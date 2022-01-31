<?php
global $_config, $db;
// when db schema modifications are done, this function is run.
$dbversion = intval($_config['dbversion']);

$db->beginTransaction();
$was_empty = false;
if (empty($dbversion)) {
	$was_empty = true;
	$db->run("create table blocks
	(
		id varchar(128) not null
			primary key,
		generator varchar(128) not null,
		height int not null,
		date int not null,
		nonce varchar(128) not null,
		signature varchar(255) not null,
		difficulty varchar(64) not null,
		transactions int not null,
		version varchar(10) default '010000' null,
		argon varchar(128) not null,
		miner varchar(128) null,
		constraint height
			unique (height)
	)");

	$db->run("create table accounts
	(
		id varchar(128) not null
			primary key,
		public_key varchar(255) not null,
		block varchar(128) not null,
		balance decimal(20,8) not null,
		alias varchar(32) null,
		constraint accounts
			foreign key (block) references blocks (id)
				on delete cascade
	)");

	$db->run("create table config
	(
		cfg varchar(30) not null
			primary key,
		val varchar(200) not null
	)");

	$db->run("create table logs
	(
		id ".DB::autoInc().",
		`transaction` varchar(128) null,
		block varchar(128) null,
		json text null
	);");

	$db->run("create table masternode
	(
		public_key varchar(255) not null
			primary key,
		height int not null,
		ip varchar(16) not null,
		last_won int default 0 not null,
		blacklist int default 0 not null,
		fails int default 0 not null,
		status tinyint default 1 not null,
		vote_key varchar(128) null,
		cold_last_won int default 0 not null,
		voted tinyint default 0 not null
	)");

	$db->run("create table mempool
	(
		id varchar(128) not null
			primary key,
		height int not null,
		src varchar(128) not null,
		dst varchar(128) not null,
		val decimal(20,8) not null,
		fee decimal(20,8) not null,
		signature varchar(255) not null,
		type tinyint not null,
		message varchar(255) default '' null,
		public_key varchar(255) not null,
		date bigint not null,
		peer varchar(64) null
	)");

	$db->run("create table peers
	(
		id ".DB::autoInc().",
		hostname varchar(128) not null,
		blacklisted int default 0 not null,
		ping int not null,
		reserve tinyint default 1 not null,
		ip varchar(45) not null,
		fails tinyint default 0 not null,
		stuckfail tinyint default 0 not null,
		height int,
		appshash varchar(250),
		score int,
		blacklist_reason varchar(100),
		constraint hostname
			unique (hostname),
		constraint ip
			unique (ip)
	)");

	$db->run("create table transactions
	(
		id varchar(128) not null
			primary key,
		block varchar(128) not null,
		height int not null,
		dst varchar(128) not null,
		val decimal(20,8) not null,
		fee decimal(20,8) not null,
		signature varchar(255) not null,
		type tinyint not null,
		message varchar(255) default '' null,
		date int not null,
		public_key varchar(255) not null,
		constraint block_id
			foreign key (block) references blocks (id)
				on delete cascade
	)");

	$db->run("create index alias on accounts (alias);");

	$db->run("create index block on logs (block);");
	$db->run("create index `transaction` on logs (`transaction`);");

	$db->run("create index blacklist on masternode (blacklist);");
	$db->run("create index cold_last_won on masternode (cold_last_won);");
	$db->run("create index height on masternode (height);");
	$db->run("create index last_won on masternode (last_won);");
	$db->run("create index status on masternode (status);");
	$db->run("create index vote_key on masternode (vote_key);");
	$db->run("create index voted on masternode (voted);");

	$db->run("create index height on mempool (height);");
	$db->run("create index peer on mempool (peer);");
	$db->run("create index src on mempool (src);");
	$db->run("create index val on mempool (val);");

	$db->run("create index blacklisted on peers (blacklisted);");
	$db->run("create index ping on peers (ping);");
	$db->run("create index reserve on peers (reserve);");
	$db->run("create index stuckfail on peers (stuckfail);");

	$db->run("create index dst on transactions (dst);");
	$db->run("create index height on transactions (height);");
	$db->run("create index message on transactions (message);");
	$db->run("create index public_key on transactions (public_key);");
	$db->run("create index version on transactions (type);");

	$db->run("INSERT INTO `config` (`cfg`, `val`) VALUES ('hostname', '');");
	$db->run("INSERT INTO `config` (`cfg`, `val`) VALUES ('dbversion', '1');");

	$db->run("INSERT INTO `config` (`cfg`, `val`) VALUES ('sync_last', '0');");
	$db->run("INSERT INTO `config` (`cfg`, `val`) VALUES ('sync', '0');");
	$dbversion = 1;
}

if($dbversion == 1) {
	$db->run('create table minepool 
	(
		miner varchar(255) null,
		address varchar(128) not null,
		height int not null,
		iphash varchar(128) not null
	);');

	$db->run("create index val on minepool (iphash);");
	$db->run("create unique index minepool_iphash_uindex on minepool (iphash);");

	$dbversion = 2;
}

if($dbversion == 2) {
	$db->run('alter table peers add version varchar(20) null;');
	$dbversion = 3;
}

if($dbversion == 3) {
	if(!$was_empty) {
		$db->run("alter table accounts modify public_key varchar(255) not null;");
		$db->run("alter table blocks modify signature varchar(255) not null;");
		$db->run("alter table masternode modify public_key varchar(255) not null;");
		$db->run("alter table mempool modify signature varchar(255) not null;");
		$db->run("alter table mempool modify message varchar(255) default '' null;");
		$db->run("alter table mempool modify public_key varchar(255) not null;");
		$db->run("alter table transactions modify signature varchar(255) not null;");
		$db->run("alter table transactions modify message varchar(255) default '' null;");
		$db->run("alter table transactions modify public_key varchar(255) not null;");
	}
	$dbversion = 4;
}

if($dbversion == 4) {
	$db->run("alter table masternode modify ip varchar(16) null");
	$dbversion = 5;
}

// update the db version to the latest one
if ($dbversion != $_config['dbversion']) {
    $db->run("UPDATE config SET val=:val WHERE cfg='dbversion'", [":val" => $dbversion]);
}
$db->commit();

