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
		src varchar(128) null,
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
	$db->run("create index transactions_src_index on transactions (src)");
	$db->run("create index height on transactions (height);");
	$db->run("create index message on transactions (message);");
	$db->run("create index public_key on transactions (public_key);");
	$db->run("create index version on transactions (type);");
	$db->run("create unique index masternode_ip_uindex on masternode (ip)");



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

if($dbversion == 5) {
	$db->run("alter table masternode change last_won win_height int default 0 not null");
	$db->run("alter table masternode add signature varchar(255) null;");
	$db->run("alter table blocks add masternode varchar(128) null;");
	$db->run("alter table blocks add mn_signature varchar(255) null;");
	$dbversion = 6;
}

if($dbversion == 6) {
	$db->run("alter table masternode add id varchar(128) null");
	$db->run("alter table masternode modify win_height int null;");
	$db->run("alter table masternode drop column blacklist;");
	$db->run("alter table masternode drop column fails;");
	$db->run("alter table masternode drop column status;");
	$db->run("alter table masternode drop column vote_key;");
	$db->run("alter table masternode drop column cold_last_won;");
	$db->run("alter table masternode drop column voted;");
	$dbversion = 7;
}

if($dbversion == 7) {
	$db->run("alter table masternode modify ip varchar(30) null");
	$dbversion = 8;
}

if($dbversion == 8) {
	$db->run("alter table peers add miner tinyint(1) default 0 null;");
	$db->run("alter table peers add generator tinyint(1) default 0 null;");
	$db->run("alter table peers add masternode tinyint(1) default 0 null;");
	$db->run("alter table peers add response_cnt int default 0 null;");
	$db->run("alter table peers add response_time decimal(20,8) default 0 null;");
	$dbversion = 9;
}

if($dbversion == 9) {
	$db->run("alter table peers add block_id varchar(128) null;");
	$dbversion = 10;
}

if($dbversion == 10) {
	$db->run("create index blocks_masternode_index on blocks (masternode)");
	$dbversion = 11;
}

if($dbversion == 11) {
	if(!$was_empty) {

		$lock_file = ROOT . "/tmp/db-lock";
//		_log("DB Schema: Check lock file $lock_file");
		if (!mkdir($lock_file, 0700)) {
//			_log("DB Schema: Lock file exists $lock_file");
			$db->rollBack();
			return;
		}
		_log("DB Schema: Lock transactions table");
		$db->run("lock tables transactions write");
		_log("DB Schema: Add src column");
		$db->run("alter table transactions add src varchar(128) null");
		_log("DB Schema: Add index on src column");
		$db->run("create index transactions_src_index on transactions (src)");
		_log("DB Schema: Update src column");
		$db->run("update transactions t set t.src = (
		    select a.id from accounts a where a.public_key = t.public_key
		    )
		where t.type > 0 and t.src is null");
		_log("DB Schema: Unlock transactions table");
		$db->run("unlock tables");
		_log("DB Schema: Update wrong balances");
		$db->run("update (
		    select ac.id, sum(ac.total) as tx_balance, acc.balance
		    from (
		             select a.id, sum(t.val*(-1)) as total
		             from accounts a
		                      join transactions t
		                           on (t.src = a.id)
		             group by a.id
		             union all
		             select a.id, sum(t.val) as total
		             from accounts a
		                      join transactions t
		                           on (t.dst = a.id)
		             group by a.id) as ac
		             left join accounts acc on (ac.id = acc.id)
		    group by ac.id
		    having tx_balance <> balance) as wrong_balances
		    left join accounts a1
		    on (wrong_balances.id = a1.id)
		set a1.balance = wrong_balances.tx_balance
		where a1.balance <> wrong_balances.tx_balance");

		_log("DB Schema: Remove lock file $lock_file");
		@rmdir($lock_file);


	}
	$dbversion = 12;
}

if ($dbversion == 12) {
	$db->run("create table smart_contract_state
	(
	sc_address varchar(128) not null,
	variable varchar(100) not null,
	var_key varchar(128) null,
	var_value varchar(1000) null,
	height int not null
	)");

	$db->run("create table smart_contracts
	(
	address varchar(128) not null,
	height int not null,
	code text not null,
	signature varchar(255) not null
	)");

	$db->run("alter table transactions add data text null");
	$db->run("alter table mempool add data text null");

	$db->run("alter table smart_contract_state
	add constraint smart_contract_state_smart_contracts_address_fk
		foreign key (sc_address) references smart_contracts (address)
			on update cascade on delete cascade");

	$db->run("create unique index smart_contracts_address_uindex
	on smart_contracts (address)");

	$db->run("create unique index smart_contract_state_sc_address_variable_var_key_height_uindex 
    on smart_contract_state (sc_address, variable, var_key, height);");

	$dbversion = 13;
}

if($dbversion == 13) {
	$db->run("alter table peers add dapps_id varchar(128) null");
	$dbversion = 14;
}

if($dbversion == 14) {
	$db->run("create index blocks_masternode_height_index on blocks (masternode, height);");
	$db->run("create index mix on masternode(height, signature, id, public_key);");
	$db->run("drop index version on transactions;");
	$db->run("create index transactions_type_index on transactions (type);");
	$db->run("create index transactions_src_dst_val_fee_index on transactions (src, dst, val, fee);");
	$dbversion = 15;
}

if($dbversion == 15) {
	if(!$was_empty) {
		$db->run("delete from masternode
        where id in (
        select dupl.id from (
	            select m.id, m.ip,
	                   row_number() over (partition by m.ip order by m.id) as rn
	            from masternode m
	            where m.ip is not null
	            order by m.ip, m.id
	        ) as dupl
        where dupl.rn > 1
        )");
		$db->run("create unique index masternode_ip_uindex
            on masternode (ip)");
	}
	$dbversion = 16;
}

if($dbversion == 16) {
	if(!$was_empty) {
		$rows = $db->run("show index from masternode where key_name='masternode_ip_uindex'");
		if (count($rows) !== 1) {
			$db->run("truncate table masternode");
			$res = $db->run("create unique index masternode_ip_uindex on masternode (ip)");
		}
	}
	$dbversion = 17;
}


if($dbversion == 17) {
	if(!$was_empty) {
		$lock_dir = ROOT . "/tmp/db-migrate";
		if (mkdir($lock_dir, 0700)) {
			$db->exec("lock tables masternode write, transactions t write, transactions tr write, blocks b write, accounts a write;");
			$db->exec("delete from masternode;");
			$db->exec("insert into masternode (public_key,height,win_height, id)
        	select public_key,height,win_height, id from (
             select t.dst as id, min(t.height) as height, count(t.id) as created,
                    (select count(tr.id) from transactions tr where tr.src = t.dst and tr.type = 3) as removed,
                    (select max(b.height) from blocks b where b.masternode = t.dst) as win_height,
                    (select a.public_key from accounts a where a.id = t.dst) as public_key
             from transactions t where t.type = 2
             group by t.dst
             having created - removed > 0
             ) as calc_mn");
			$db->exec("unlock tables;");
			@rmdir($lock_dir);
			$dbversion = 18;
		}
	} else {
		$dbversion = 18;
	}
}

// update the db version to the latest one
if ($dbversion != $_config['dbversion']) {
    $db->run("UPDATE config SET val=:val WHERE cfg='dbversion'", [":val" => $dbversion]);
}
$db->commit();

