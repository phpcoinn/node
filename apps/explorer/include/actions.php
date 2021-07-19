<?php

if(!defined("PAGE")) exit;

if(isset($_GET['search'])) {
	$search = $_GET['search'];
	if(is_numeric($search)) {
		$block = Block::getAtHeight($search);
		if($block) {
			header("location: /apps/explorer/block.php?height=".$search);
			exit;
		}
	}
	if(Account::valid($search)) {
		header("location: /apps/explorer/address.php?address=".$search);
		exit;
	}
	$block = Block::getById($search);
	if($block) {
		header("location: /apps/explorer/block.php?id=".$search);
		exit;
	}
	$tx = Transaction::getById($search);
	if($tx) {
		header("location: /apps/explorer/tx.php?id=".$search);
		exit;
	}
	$pubkey = $search;
	$acc = new Account();
	$address = Account::getAddress($pubkey);
	if(Account::valid($address)) {
		$pubkeyCheck = Account::publicKey($address);
		if($pubkeyCheck == $pubkey) {
			header("location: /apps/explorer/address.php?pubkey=".$pubkey);
			exit;
		}
	}
}
