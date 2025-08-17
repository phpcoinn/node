<?php

use Web3\Web3;

class UsdtPolygonService implements AssetService
{

    const USDT_CONTRACT_ADDRESS = "0xc2132d05d31c914a87c6611c10748aeb04b58e8f";
    const USDT_DECIMALS = 6;
    const CHAIN_ID = 137;
//        const RPC_URL = "https://polygon-rpc.com";
//    const RPC_URL = "https://polygon-pokt.nodies.app";
//    const RPC_URL = "https://rpc.ankr.com/polygon/217527b53b677b944df33f9a8d4230626a849f170ed5daf5b297772d2d8fe96e";
    const RPC_URL = "https://polygon-mainnet.infura.io/v3/7fe3e0cd4aeb461aa2004466caeabce2";
//    const RPC_URL = "https://go.getblock.io/0d0beedb875942f8818097eab0fa1f49";
//    const RPC_URL = "https://cosmopolitan-green-arrow.matic.quiknode.pro/239ffdf84f76e4f555730cf74c59166c408d628f/";
    const COIN_ESCROW_ADDRESS = "0x9fcfa25924b60b7e80c90f932c4b4f562dcdc39c";

    public function getEscrowPrivateKey()
    {
        global $_config;
        return $_config["usdt_pol_escrow"]["private_key"];
    }

    public function createCancelTx($offer_id, $dst, $val)
    {
        $this->transferToken($val,$dst);
    }

    public function getStartBlockNumber()
    {
        return 75285389;
    }

    public function findTransfers($block_number)
    {
        $coinContractAddress = self::USDT_CONTRACT_ADDRESS;
        $decimals = self::USDT_DECIMALS;
        $rpcUrl = self::RPC_URL;
        $web3 = new Web3($rpcUrl, 30);
        $eth = $web3->eth;
        // Set block range (adjust as needed)
        $fromBlock = "0x" . dechex(intval($block_number)); // Starting block (hex format)
        $toBlock = "latest"; // Latest block

        // Keccak-256 hash of Transfer event
        $transferEventSignature = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef";
        $to = self::COIN_ESCROW_ADDRESS;

        // Query logs
        $filter = [
            "fromBlock" => $fromBlock,
            "toBlock" => $toBlock,
            "address" => $coinContractAddress,
            "topics" => [
                $transferEventSignature,
                null, // 'from' address
                "0x" . str_pad(substr($to, 2), 64, "0", STR_PAD_LEFT) // 'to' address
            ]
        ];
        try {
            $found = [];
            $eth->getLogs($filter, function ($err, $logs) use (&$found, $decimals) {
                if ($err !== null) {
                    echo "Error: " . $err->getMessage();
                    return false;
                }
                foreach ($logs as $log) {
                    $amount = hexdec($log->data) / 10**$decimals;
                    $txHash = $log->transactionHash;
                    $found[]=$txHash;
                }
            });
            return $found;
        } catch (Throwable $t) {
            echo $t->getMessage();
            return false;
        }
    }

    public function findTransaction(mixed $id)
    {
        $coinContractAddress = self::USDT_CONTRACT_ADDRESS;
        $decimals = self::USDT_DECIMALS;
        $rpcUrl = self::RPC_URL;
        $web3 = new Web3(new \Web3\Providers\HttpProvider($rpcUrl, 5));
        $eth = $web3->eth;
        $eth->getTransactionReceipt($id, function ($err, $tx) use (&$txData) {
            if($err) {
                _log("findTransaction: Error: " . $err->getMessage());
                return false;
            }
            $txData = $tx;
        });
        if(!$txData) {
            _log("findTransaction: Not found buy transaction $id");
            return false;
        }
        foreach ($txData->logs as $log) {
            if (strtolower($log->address) === strtolower($coinContractAddress)) {
                $topics = $log->topics;
                $data = hexdec($log->data); // Transfer amount in smallest unit (6 decimals)
                $from = "0x" . substr($topics[1], 26); // Extract 'from' address
                $to = "0x" . substr($topics[2], 26);   // Extract 'to' address
                $amount = ($data / 10 ** $decimals);
                return[
                    'id' => $id,
                    'from' => $from,
                    'to' => $to,
                    'amount' => $amount,
                    'height' => hexdec($txData->blockNumber),
                ];
            }
        }
    }

    public function getConfirmations(string $type)
    {
        return match ($type) {
            default => 100,
        };
    }

    public function createPayment(mixed $amount, mixed $toAddress, $offer)
    {
        return $this->transferToken($amount,$toAddress);
    }

    public function addressLink(string $address)
    {
        return "https://polygonscan.com/address/$address";
    }

    public function txLink(mixed $txId)
    {
        return "https://polygonscan.com/tx/$txId";
    }

    public function getMaxTradeFee()
    {
        return 100 / pow(10, self::USDT_DECIMALS);
    }

    public function depositFromWallet(mixed $amount, $offer)
    {
        $amount = round($amount, $this->getDecimals());
        Pajax::executeScript('closeOfferModal');
        Pajax::executeScript('transferWithMetamask', $amount, self::USDT_CONTRACT_ADDRESS, self::COIN_ESCROW_ADDRESS, 'depositFromWalletCallback');

    }

    public function depositFromWalletCallback(mixed $offer, $data)
    {
        $hash = $data['hash'];
        OfferService::setOfferDepositing($offer['id'], $hash);
        Pajax::executeScript('focusOffer', $offer['id']);
    }

    public function transferFromWallet(mixed $amount, mixed $offer)
    {
        $amount = round($amount, $this->getDecimals());
        Pajax::executeScript('closeOfferModal');
        Pajax::executeScript('transferWithMetamask', $amount, self::USDT_CONTRACT_ADDRESS, self::COIN_ESCROW_ADDRESS, 'transferFromWalletCallback');

    }

    public function transferFromWalletCallback(mixed $offer, $data)
    {
        $hash = $data['hash'];
        OfferService::setAcceptedOfferTransferring($offer['id'], $hash);
        $_SESSION['offer_id'] = $offer['id'];
        Pajax::redirect(AppView::BASE_URL);
    }

    public function getEscrowAddress()
    {
        return self::COIN_ESCROW_ADDRESS;
    }

    private function transferToken($amount, $toAddress, &$err=null) {
        $coinContractAddress = self::USDT_CONTRACT_ADDRESS;
        $decimals = self::USDT_DECIMALS;
        $chainId = self::CHAIN_ID;
        $rpcUrl = self::RPC_URL;
        $methodId = '0xa9059cbb';
        $privateKey = self::getEscrowPrivateKey();
        $fromAddress = $this->getEscrowAddress();
        $amountHex = '0x' . dechex(intval($amount * 10**$decimals));
        // Encode function call: transfer(address, uint256)
        $toPadded = str_pad(substr($toAddress, 2), 64, "0", STR_PAD_LEFT);
        $amountPadded = str_pad(substr($amountHex, 2), 64, "0", STR_PAD_LEFT);
        $data =  $methodId . $toPadded . $amountPadded;

        $web3 = new Web3($rpcUrl, 10);
        $eth = $web3->eth;

        // Get nonce
        $eth->getTransactionCount($fromAddress, "latest", function ($err, $nonce) use (&$txNonce) {
            if ($err === null) {
                $txNonce = $nonce->toString();
            }
        });
        _log("UsdtPolygonService: transferCoin fromAddress=$fromAddress txNonce=$txNonce");

        if($txNonce == null) {
            $err="Error getting transaction nonce";
            _log("UsdtPolygonService: $err");
            return false;
        }

        $eth->gasPrice(function ($err, $gasPriceResult) use (&$gasPrice) {
            if ($err === null) {
                $gasPrice = $gasPriceResult->toString();
            }
        });

        _log("UsdtPolygonService: transferCoin gasPrice=$gasPrice");

        if(empty($gasPrice)) {
            $err="Error getting transaction price";
            _log("UsdtPolygonService: $err");
            return false;
        }

        $transactionParams = [
            'nonce' => "0x" . dechex($txNonce),
            'from' => $fromAddress,
            'to' => $coinContractAddress,
            'value' => '0x0',
            'data' => $data
        ];
        _log("UsdtPolygonService: Coin transfer to seller from=$fromAddress to=$toAddress and amount=$amount");
        $eth->estimateGas($transactionParams, function ($err, $gas) use (&$gasLimit, &$error) {
            if ($err === null) {
                $gasLimit = $gas->toString();
            } else {
                $error = $err;
            }
        });

        if(empty($gasLimit)) {
            $err="Error getting transaction gas limit: $error";
            _log("UsdtPolygonService: $err");
            return false;
        }

        _log("UsdtPolygonService: transferCoin gasLimit=$gasLimit");

        $transactionParams['gas']='0x' .dechex($gasLimit);
        $transactionParams['gasPrice']='0x' . dechex($gasPrice);
        $transactionParams['chainId'] = $chainId;

        $tx = new \Web3p\EthereumTx\Transaction($transactionParams);
        $signedTx = '0x' . $tx->sign($privateKey);

        $eth->sendRawTransaction($signedTx, function ($err, $txResult) use (&$txHash) {
            if ($err === null) {
                $txHash = $txResult;
            } else {
                _log("UsdtPolygonService: Error sending transaction: " . $err->getMessage());
            }
        });

        if(empty($txHash)) {
            $err="Error getting transaction hash";
            _log("UsdtPolygonService: $err");
            return false;
        }

        return $txHash;
    }

    public function getDecimals()
    {
        return self::USDT_DECIMALS;
    }

    public function checkAddress(mixed $address)
    {
        return \Web3\Utils::isAddress($address);
    }

    public function getLastHeight()
    {
        $rpcUrl = self::RPC_URL;
        $web3 = new Web3(new \Web3\Providers\HttpProvider($rpcUrl, 5));
        $eth = $web3->eth;
        $eth->blockNumber(function ($err, $blockNumber) use (&$ret) {
            if ($err !== null) {
                $ret = null;
                return;
            }
            $ret = intval($blockNumber->toString());
        });
        return $ret;
    }

    public function checkTransaction($tx)
    {
        $amount = $tx['amount'];
        if($amount < 0.1) {
            return false;
        }
        return true;
    }

    public function resendTx(string $txId)
    {
        //$this->transferToken(0.11, "0x4d08ac79236b2a1d2784644743726290ebe4564a");
    }
}