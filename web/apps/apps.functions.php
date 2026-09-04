<?php

global $_config;

function truncate_hash($hash, $digits = 8) {
	if(empty($hash)) {
		return null;
	}
	$thash = substr($hash, 0, $digits) . "..." . substr($hash, -$digits);
	return '<span data-bs-toggle="tooltip" title="'.h($hash).'">' . h($thash) . '</span>';
}

function explorer_address_link2($address, $short= false) {
	$text = $short ? truncate_hash($address) : h($address);
	return '<a href="/apps/explorer/address.php?address='.urlencode($address).'">'.$text.'</a>';
}
