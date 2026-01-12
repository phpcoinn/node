<?php
global $_config, $db;
// when db schema modifications are done, this function is run.
// database will be only initialized on installation / restore
$dbversion = intval(@$_config['dbversion']);

_log("Check db schema current_version=$dbversion check_version=".DB_SCHEMA_VERSION, 2);

if ($dbversion < DB_SCHEMA_VERSION) {

    _log("DB schema check start");

    $pruned = Config::isPruned();
    $schema_file = ROOT . "/include/schema/".NETWORK.($pruned ? "-pruned":"").".sql";
    if(!file_exists($schema_file)) {
        _log("Schema file not found: ".$schema_file);
        return;
    }

    _log("Checking schema file: ".$schema_file);

    touch(ROOT."/maintenance");
    $lock_dir = ROOT . "/tmp/db-migrate";
    if (mkdir($lock_dir, 0700, true)) {

        $config_file = tempnam(sys_get_temp_dir(), "db_updater") . ".json";
        $db_updater_config = [
            'database' => [
                'dsn' => $_config['db_connect'],
                'username' => $_config['db_user'],
                'password' => $_config['db_pass'],
            ],
        ];
        file_put_contents($config_file, json_encode($db_updater_config, JSON_PRETTY_PRINT));
        $cmd="php " .ROOT . "/utils/db_updater.phar ".escapeshellarg($schema_file)." --json --config=".escapeshellarg($config_file);
        _log("DB updater started ...");
        $res = shell_exec($cmd);
        _log("DB updater finished ...");
        $res = json_decode($res, true);
        if($res['success'] === true) {
            _log("DB updater: ",$res['message']);
            unlink(ROOT."/maintenance");
            Config::setVal('dbversion', DB_SCHEMA_VERSION);
        } else {
            _log("Error executing db update: " . $res['error']);
        }
        unlink($config_file);
        @rmdir($lock_dir);
    }

    _log("DB schema check complete");
}


return;

// Keep old for reference
function migrate_with_lock(&$dbversion, $callback) {
    $lock_dir = ROOT . "/tmp/db-migrate-".($dbversion+1);
    if (mkdir($lock_dir, 0700, true)) {
        call_user_func($callback);
        @rmdir($lock_dir);
        $dbversion++;
    }
}

function create_views() {
    global $db;
    $db->run("create or replace view tokens as
        select sc.address, sc.metadata, json_unquote(json_extract(sc.metadata, '$.name')) as name
                , json_unquote(json_extract(sc.metadata, '$.description')) as description
                , json_unquote(json_extract(sc.metadata, '$.symbol')) as symbol
                , json_extract(sc.metadata, '$.initialSupply') as initialSupply
                , json_extract(sc.metadata, '$.decimals') as decimals,
                  sc.height
            from smart_contracts sc
        where json_extract(sc.metadata, '$.class') = 'ERC-20';");

    $db->run("create or replace view token_txs as
        select txs.id,
               txs.height,
               txs.block,
               txs.date,
               txs.dst as token,
               txs.method,
               case when txs.method = 'transfer' then txs.src
                   when txs.method = 'mint' then null
                   when txs.method = 'burn' then txs.src
                   else p1 end as src,
               case when txs.method = 'transfer' then p1
                    when txs.method = 'mint' then txs.src
                    when txs.method = 'burn' then null
                   else p2 end      as dst,
               case when txs.method = 'transfer' then p2
                    when txs.method = 'mint' then p1
                    when txs.method = 'burn' then p1
                   else p3 end      as amount
        from (select t.*,
                     json_unquote(json_extract(from_base64(t.message), '$.method'))    as method,
                     json_unquote(json_extract(from_base64(t.message), '$.params[0]')) as p1,
                     json_unquote(json_extract(from_base64(t.message), '$.params[1]')) as p2,
                     json_unquote(json_extract(from_base64(t.message), '$.params[2]')) as p3
              from transactions t
              where t.type = 6
                and exists (select 1 from tokens tt where tt.address = t.dst)) as txs
        where txs.method in ('transfer', 'transferFrom', 'mint', 'burn');");

    $db->run("create or replace view token_mempool_txs as
        select txs.id,
               txs.height,
               txs.date,
               txs.dst as token,
               txs.method,
               case when txs.method = 'transfer' then txs.src
                    when txs.method = 'mint' then null
                    when txs.method = 'burn' then txs.src
                    else p1 end as src,
               case when txs.method = 'transfer' then p1
                    when txs.method = 'mint' then txs.src
                    when txs.method = 'burn' then null
                    else p2 end      as dst,
               case when txs.method = 'transfer' then p2
                    when txs.method = 'mint' then p1
                    when txs.method = 'burn' then p1
                    else p3 end      as amount
        from (select t.*,
                     json_unquote(json_extract(from_base64(t.message), '$.method'))    as method,
                     json_unquote(json_extract(from_base64(t.message), '$.params[0]')) as p1,
                     json_unquote(json_extract(from_base64(t.message), '$.params[1]')) as p2,
                     json_unquote(json_extract(from_base64(t.message), '$.params[2]')) as p3
              from mempool t
              where t.type = 6
                and exists (select 1 from tokens tt where tt.address = t.dst)) as txs
        where txs.method in ('transfer', 'transferFrom', 'mint', 'burn');");

    $db->run("create or replace view token_balances as
        select b.token, b.address,
               FORMAT(b.var_value / POW(10, b.decimals), b.decimals) as balance
        from (
        select ss.sc_address as token, ss.var_key as address, ss.var_value,
               row_number() over (partition by ss.sc_address, ss.var_key order by ss.height desc) as rn,
               tt.decimals
            from smart_contract_state ss
            join tokens tt on (ss.sc_address = tt.address)
        where ss.variable = 'balances') as b
        where b.rn =1;");

}

$db->beginTransaction();
$was_empty = false;
if (empty($dbversion)) {
	$was_empty = true;
    $dbversion=44;
    _log("Initializing database");
    migrate_with_lock($dbversion, function() {
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
        $db->run("create index idx_src_height on transactions (src, height, val);");
        $db->run("create index idx_dst_height on transactions (dst, height, val);");

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

        create_views();
        _log("Initialize databaase complete");

    });
}

if($dbversion <= 44) {
    migrate_with_lock($dbversion, function() {
        create_views();
    });
}

// update the db version to the latest one
if ($dbversion != @$_config['dbversion']) {
    $db->run("UPDATE config SET val=:val WHERE cfg='dbversion'", [":val" => $dbversion]);
}
if($db->inTransaction()) {
	$db->commit();
}

