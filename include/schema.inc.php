<?php
global $_config, $db;
// when db schema modifications are done, this function is run.
$dbversion = intval(@$_config['dbversion']);


function migrate_with_lock(&$dbversion, $callback) {
    $lock_dir = ROOT . "/tmp/db-migrate-".($dbversion+1);
    if (mkdir($lock_dir, 0700)) {
        call_user_func($callback);
        @rmdir($lock_dir);
        $dbversion++;
    }
}


$db->beginTransaction();
$was_empty = false;
if (empty($dbversion)) {
	$was_empty = true;
    $dbversion=36;
    migrate_with_lock($dbversion, function(){
        global $db;
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
            masternode varchar(128) null,
            mn_signature varchar(255) null,
            schash varchar(128) null,
            constraint height
                unique (height)
        )");

        $db->run("create index blocks_masternode_height_index on blocks (masternode, height);");
        $db->run("create index blocks_masternode_index on blocks (masternode)");

        $db->run("create table accounts
        (
            id varchar(128) not null
                primary key,
            public_key varchar(255) not null,
            block varchar(128) not null,
            balance decimal(20,8) not null,
            alias varchar(32) null,
            height int(11) null,
            constraint accounts
                foreign key (block) references blocks (id)
                    on delete cascade
        )");

        $db->run("create index alias on accounts (alias);");

        $db->run("create table config
        (
            cfg varchar(30) not null
                primary key,
            val varchar(200) not null
        )");

        $db->run("create table masternode
        (
            public_key varchar(255) not null
                primary key,
                id varchar(128) null,
            height int not null,
            ip varchar(30) null,
            win_height int null,
            collateral int default 10000 not null,
            verified int default 0 not null,
            signature varchar(255) null
        )");

        $db->run("create index height on masternode (height);");
        $db->run("create index win_height on masternode (win_height);");
        $db->run("create unique index masternode_ip_uindex on masternode (ip)");
        $db->run("create index mix on masternode(height, signature, id, public_key);");

        $db->run("create table mempool
        (
            id varchar(128) not null
                primary key,
            height int not null,
            src varchar(128) not null,
            dst varchar(128) null,
            val decimal(20,8) not null,
            fee decimal(20,8) not null,
            signature varchar(255) not null,
            type tinyint not null,
            message varchar(255) default '' null,
            public_key varchar(255) not null,
            date bigint not null,
            peer varchar(64) null,
            data text null
        )");

        $db->run("create index height on mempool (height);");
        $db->run("create index peer on mempool (peer);");
        $db->run("create index src on mempool (src);");
        $db->run("create index val on mempool (val);");

        $db->run("create table peers
        (
            id ".DB::autoInc().",
            hostname varchar(128) not null,
            blacklisted int default 0 not null,
            ping int not null,
            reserve tinyint default 1 not null,
            ip varchar(45) not null,
            fails int default 0 not null,
            stuckfail tinyint default 0 not null,
            height int,
            appshash varchar(250),
            score int,
            blacklist_reason varchar(100),
            dappshash varchar(250) null,
            miner varchar(128) null,
            generator varchar(128) null,
            masternode varchar(128) null,
            dapps_id varchar(128) null,
            block_id varchar(128) null,
            response_time decimal(20,8) default 0 null,
            response_cnt int default 0 null,
            version varchar(20) null,
            constraint hostname
                unique (hostname),
            constraint ip
                unique (ip)
        )");

        $db->run("create index blacklisted on peers (blacklisted);");
        $db->run("create index ping on peers (ping);");
        $db->run("create index reserve on peers (reserve);");
        $db->run("create index stuckfail on peers (stuckfail);");

        $db->run("create table transactions
        (
            id varchar(128) not null
                primary key,
            block varchar(128) not null,
            height int not null,
            src varchar(128) null,
            dst varchar(128) null,
            val decimal(20,8) not null,
            fee decimal(20,8) not null,
            signature varchar(255) not null,
            type tinyint not null,
            message varchar(255) default '' null,
            date int not null,
            public_key varchar(255) not null,
            data text null,
            constraint block_id
                foreign key (block) references blocks (id)
                    on delete cascade
        )");

        $db->run("create index dst on transactions (dst);");
        $db->run("create index transactions_src_index on transactions (src)");
        $db->run("create index height on transactions (height);");
        $db->run("create index message on transactions (message);");
        $db->run("create index public_key on transactions (public_key);");
        $db->run("create index transactions_src_dst_val_fee_index on transactions (src, dst, val, fee);");
        $db->run("create index transactions_type_index on transactions (type);");

        $db->run('create table minepool 
        (
            miner varchar(255) null,
            address varchar(128) not null,
            height int not null,
            iphash varchar(128) not null
        );');

        $db->run("create index val on minepool (iphash);");
        $db->run("create unique index minepool_iphash_uindex on minepool (iphash);");

        $db->run("create table smart_contract_state
        (
        sc_address varchar(128) not null,
        variable varchar(100) not null,
        var_key varchar(128) null,
        var_value varchar(1000) null,
        height int not null
        )");

        $db->run("create unique index smart_contract_state_sc_address_variable_var_key_height_uindex 
        on smart_contract_state (sc_address, variable, var_key, height);");

        $db->run("create table smart_contracts
        (
        address varchar(128) not null,
        height int not null,
	code mediumtext not null,
        signature varchar(255) not null,
        name varchar(255) null,
	description varchar(1000) null,
	metadata json null
        )");

        $db->run("create unique index smart_contracts_address_uindex
        on smart_contracts (address)");

        $db->run("alter table smart_contract_state
        add constraint smart_contract_state_smart_contracts_address_fk
        foreign key (sc_address) references smart_contracts (address)
        on update cascade on delete cascade");

        $db->run("alter table smart_contracts
        add constraint smart_contracts_pk
            primary key (address)");

        $db->run("INSERT INTO `config` (`cfg`, `val`) VALUES ('hostname', '');");
        $db->run("INSERT INTO `config` (`cfg`, `val`) VALUES ('dbversion', '1');");

        $db->run("INSERT INTO `config` (`cfg`, `val`) VALUES ('sync_last', '0');");
        $db->run("INSERT INTO `config` (`cfg`, `val`) VALUES ('sync', '0');");
    });
}

if($dbversion <= 37) {
    migrate_with_lock($dbversion, function() {
        global $db;
        $db->run("alter table smart_contracts add metadata json null");
        $db->run("alter table smart_contracts modify code MEDIUMTEXT not null");
    });
}

// update the db version to the latest one
if ($dbversion != @$_config['dbversion']) {
    $db->run("UPDATE config SET val=:val WHERE cfg='dbversion'", [":val" => $dbversion]);
}
if($db->inTransaction()) {
	$db->commit();
}

