<?php

if(php_sapi_name() !== 'cli') exit;
require_once dirname(__DIR__).'/include/init.inc.php';


$save = true;

if(true) {
    $main_genesis_file = ROOT . "/dist/genesis.dat";
    $lines = file($main_genesis_file);
    $private_key = trim($lines[1]);
    $public_key = trim($lines[2]);
} else {
    $account = Account::generateAcccount();
    print_r($account);
    $public_key = $account['public_key'];
    $private_key = $account['private_key'];
}


$wallet = COIN."\n".$private_key."\n".$public_key;

//$block_date = time();
$block_date = strtotime("2023-04-01 12:00:00");
$elapsed = 0;

$difficulty = BLOCK_START_DIFFICULTY;
$height = 1;

$generator = Account::getAddress($public_key);
$data = [];

//$msg = 'Marty McFly: If you put your mind to it, you can accomplish anything.';
$msg = 'Doc: Roads? Where were going, we dont need roads.';
$transaction = new Transaction($public_key,$generator,num(GENESIS_REWARD),TX_TYPE_REWARD,$block_date,$msg);
$signature = $transaction->sign($private_key);
$transaction->hash();
$reward_tx = $transaction->toArray();


$data[$reward_tx['id']]=$reward_tx;
ksort($data);

$block=new Block($generator, $generator, $height, $block_date, null, $data, $difficulty, Block::versionCode(), null, "");
$block->argon = $block->calculateArgonHash($block_date, $elapsed);
$block->nonce = $block->calculateNonce($block_date, $elapsed);

$signature = $block->sign($private_key);
$hash = $block->hash();

$genesisData = [
	'signature' => $signature,
	'public_key' => $public_key,
	'argon'=>$block->argon,
	'difficulty'=>$difficulty,
	'nonce'=>$block->nonce,
	'date'=>$block_date,
	'reward_tx'=>json_encode($data),
    "block"=>$hash,
    "address"=>$generator
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

echo $code;

if($save) {
    $wallet_file = ROOT."/dist/genesis.00.dat";
    file_put_contents($wallet_file, $wallet);
    $file = dirname(__DIR__)."/include/genesis.00.inc.php";
    file_put_contents($file, $code);
}
