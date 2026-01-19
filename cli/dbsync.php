<?php

require_once dirname(__DIR__).'/include/init.inc.php';

$hostname = $argv[1];
if(empty($hostname)) {
    _log("PeerSync: Empty hostname");
    exit;
}

$current = Block::current();
$height = $current['height'];

$url = $hostname."/peer.php?q=";
$data = peer_post($url."getDbBlocks", ["height" => $height]);

print_r($data);

$maxheight = $data['maxheight'];

try {
    global $db;
    $db->beginTransaction();

    while($height < $maxheight) {

        $data = peer_post($url."getDbBlocks", ["height" => $height, "limit"=>100]);

        $block = $data['block'];
        if ($block['height'] != $height) {
            throw new Exception("Invalid height to sync");
        }
        $myblock = Block::get($height);
        if ($block['id'] != $myblock['id']) {
            throw new Exception("Invalid start block check");
        }

        $blocks = $data['blocks'];
        foreach ($blocks as $block) {
            $sql = "insert into blocks (id, generator, height, date, nonce, signature, difficulty, transactions, version, argon, 
                            miner, masternode, mn_signature) values (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $res = $db->run($sql, [
                $block['id'],
                $block['generator'],
                $block['height'],
                $block['date'],
                $block['nonce'],
                $block['signature'],
                $block['difficulty'],
                $block['transactions'],
                $block['version'],
                $block['argon'],
                $block['miner'],
                $block['masternode'],
                $block['mn_signature'],
            ], false);
            if (!$res) {
                throw new Exception("Error inserting block at height $height");
            }
            _log("Inserted block " . $block['id'] . " at height $height");
            $height++;
        }

        $transactions = $data['transactions'];
        foreach ($transactions as $index => $transaction) {
            $sql = "insert into transactions (id, block, height, src, dst, val, fee, signature, type, message, date, public_key,data)
            values (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $res = $db->run($sql, [
                $transaction['id'],
                $transaction['block'],
                $transaction['height'],
                $transaction['src'],
                $transaction['dst'],
                $transaction['val'],
                $transaction['fee'],
                $transaction['signature'],
                $transaction['type'],
                $transaction['message'],
                $transaction['date'],
                $transaction['public_key'],
            ], false);
            if (!$res) {
                throw new Exception("Error inserting transactions at height" . $transaction['height']);
            }
            _log("Inserted transaction " . $transaction['id'] . " at height " . $transaction['height']);

            if(!empty($transaction['data'])) {
                $sql="insert into transaction_data (tx_id, data) values (?,?)";
                $res = $db->run($sql, [$transaction['id'], $transaction['data']]);
                if(!$res) {
                    throw new Exception("Error inserting transaction data at height" . $transaction['height']);
                }
            }
        }

        $smart_contracts = $data['smart_contracts'];
        foreach ($smart_contracts as $smart_contract) {
            $sql = "
            insert into smart_contracts (address, height, code, signature, name, description)
            values (?,?,?,?,?,?);";
            $res = $db->run($sql, [
                $smart_contract['address'],
                $smart_contract['height'],
                $smart_contract['code'],
                $smart_contract['signature'],
                $smart_contract['name'],
                $smart_contract['description'],
            ], false);
            if (!$res) {
                throw new Exception("Error inserting smart contact at height" . $smart_contract['height']);
            }
            _log("Inserted smart contract " . $smart_contract['address'] . " at height " . $smart_contract['height']);
        }

        $smart_contract_state = $data['smart_contract_state'];
        foreach ($smart_contract_state as $scs) {
            $sql = "insert into smart_contract_state (sc_address, variable, var_key, var_value, height)
                values (?,?,?,?,?);";
            $res = $db->run($sql, [
                $scs['sc_address'],
                $scs['variable'],
                $scs['var_key'],
                $scs['var_value'],
                $scs['height'],
            ], false);
            if (!$res) {
                throw new Exception("Error inserting smart contact state at height" . $scs['height']);
            }
            _log("Inserted smart contract state " . $scs['sc_address'] . " at height " . $scs['height']);
        }

        _log("Inserted batch up to $height");

    }

    if($height == $maxheight) {
        $accounts = $data['accounts'];
        $sql="delete from accounts";
        $db->run($sql);
        foreach ($accounts as $account) {
            $sql="insert into accounts (id, public_key, block, balance, alias, height)
                    values (?,?,?,?,?,?);";
            $res = $db->run($sql, [
                $account['id'],
                $account['public_key'],
                $account['block'],
                $account['balance'],
                $account['alias'],
                $account['height'],
            ], false);
            if (!$res) {
                throw new Exception("Error inserting account for" . $account['id']);
            }
            _log("Inserted account " . $account['id'] . " at height " . $account['height']);
        }

        $masternodes=$data['masternodes'];
        $sql="delete from masternode";
        $db->run($sql);
        foreach ($masternodes as $mn) {
            $sql="insert into masternode (public_key, id, height, ip, win_height, collateral, verified, signature)
                values (?,?,?,?,?,?,?,?);";
            $res = $db->run($sql, [
                $mn['public_key'],
                $mn['id'],
                $mn['height'],
                $mn['ip'],
                $mn['win_height'],
                $mn['collateral'],
                $mn['verified'],
                $mn['signature'],
            ], false);
            if (!$res) {
                throw new Exception("Error inserting masternode for" . $mn['id']);
            }
            _log("Inserted masternode " . $mn['id'] . " at height " . $mn['height']);
        }
    }



//    $db->rollBack();
    $db->commit();
} catch (Exception $e) {
    _log($e->getMessage());
    $db->rollBack();
}


