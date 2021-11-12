<?php

if(php_sapi_name() !== 'cli') exit;
require_once dirname(__DIR__).'/include/init.inc.php';
$account = Account::generateAcccount();

print_r($account);

$public_key = $account['public_key'];
$private_key = $account['private_key'];
$wallet = COIN."\n".$private_key."\n".$public_key;

$wallet_file = ROOT."/dist/genesis.dat";

file_put_contents($wallet_file, $wallet);

$block_date = time();
$elapsed = 0;

$difficulty = BLOCK_START_DIFFICULTY;
$height = 1;

$generator = Account::getAddress($public_key);
$data = [];

$msg = 'Marty McFly: If you put your mind to it, you can accomplish anything.';
$transaction = new Transaction($public_key,$generator,num(GENESIS_REWARD),TX_TYPE_REWARD,$block_date,$msg);
$signature = $transaction->sign($private_key);
$transaction->hash();
$reward_tx = $transaction->toArray();


$data[$reward_tx['id']]=$reward_tx;
ksort($data);

$block=new Block($generator, $generator, $height, $block_date, null, $data, $difficulty, Block::versionCode(), null, "");
$block->calculateNonce($block_date, $elapsed);

$signature = $block->sign($private_key);

$genesisData = [
	'signature' => $signature,
	'public_key' => $public_key,
	'argon'=>$block->argon,
	'difficulty'=>$difficulty,
	'nonce'=>$block->nonce,
	'date'=>$block_date,
	'reward_tx'=>json_encode($data),
];

$lines = [];
$lines[]='<?php';
$lines[]='const GENESIS_DATA = [';
foreach ($genesisData as $key=>$val) {
	$lines[]='"'.$key.'" => \''.$val.'\',';
}
$lines[]='];';
$lines[]='const GENESIS_TIME = '.$block_date.';';

$code = implode(PHP_EOL, $lines);

$file = dirname(__DIR__)."/include/genesis.inc.php";

echo $file;
file_put_contents($file, $code);

echo $code;
