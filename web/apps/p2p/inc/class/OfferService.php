<?php

class OfferService
{

    const OFFER_WAIT_TIME = 60*2*60;

    const STATUS_CREATED = 'created';
    const STATUS_EXPIRED = 'expired';
    const STATUS_DEPOSITING = 'depositing';
    const STATUS_OPEN = 'open';
    const STATUS_CANCELED = 'canceled';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_TRANSFERRING = 'transferring';
    const STATUS_TRANSFERRED = 'transferred';
    const STATUS_PAYING = 'paying';
    const STATUS_CLOSED = 'closed';

    const TYPE_SELL = 'sell';
    const TYPE_BUY = 'buy';

    static function createOffer($offer) {
        DBService::runInTransaction(function() use ($offer, &$offer_id) {
            global $db;
            $res = self::exec('insert into p2p_offers (type, status, base_amount, base_price, user_created, market_id, quote_receive_address, base_receive_address, 
                    expires_at,base_dust_amount,quote_dust_amount) 
            values (?,?,?,?,?,?,?,?,?,?,?)' , [
                $offer['type'],OfferService::STATUS_CREATED,$offer['base_amount'],$offer['base_price'], $offer['user_created'],
                $offer['market_id'], $offer['quote_receive_address'], $offer['base_receive_address'],
                $offer['expires_at'], $offer['base_dust_amount'], $offer['quote_dust_amount']
            ]);
            if(!$res) {
                return false;
            }
            $offer_id = $db->lastInsertId();
            self::saveOfferLog($offer_id);
        });
        return $offer_id;
    }


    static function getOffer($id) {
        global $db;
        $sql="select o.*, ba.symbol as base, qa.symbol as quote, ba.service as base_service, qa.service as quote_service,
            ba.id as base_asset_id, qa.id as quote_asset_id
        from p2p_offers o
         left join p2p_markets m on o.market_id = m.id
           left join p2p_assets ba on m.base_asset_id = ba.id
           left join p2p_assets qa on m.quote_asset_id = qa.id
         where o.id=?";
        return $db->row($sql, [$id], false);
    }

    public static function getExpiredCreatedOffers()
    {
        $sql="select * from p2p_offers where status=? and created_at < ?";
        $time = time() - OfferService::OFFER_WAIT_TIME;
        return self::rows($sql, [OfferService::STATUS_CREATED, date('Y-m-d H:i:s',$time)]);
    }
    public static function getExpiredOpenOffers()
    {
        $sql="select * from p2p_offers where status=? and expires_at < now()";
        return self::rows($sql, [OfferService::STATUS_OPEN]);
    }
    public static function setOfferExpired(mixed $id)
    {
        DBService::runInTransaction(function() use ($id) {
            self::exec("update p2p_offers set status=? where id=?",[OfferService::STATUS_EXPIRED,$id]);
            self::saveOfferLog($id);
        });

    }


    private static function rows($sql, $params=[]) {
        global $db;
        return $db->run($sql, $params, false);
    }

    private static function exec($sql, $params) {
        global $db;
        $pdostmt = $db->prepare($sql);
        $pdostmt->execute($params);
        $res = $pdostmt->rowCount();
        return $res;
    }


    private static function row($sql, $params) {
        global $db;
        return $db->row($sql, $params, false);
    }

    public static function getOfferByReceiveTx($tx_id)
    {
        return self::row('select * from p2p_offers where (deposit_tx_id = ? or accept_tx_id = ?)',
            [$tx_id, $tx_id]);
    }

    public static function setOfferDepositing($id, $deposit_tx_id)
    {
        DBService::runInTransaction(function() use ($id, $deposit_tx_id) {
            self::exec("update p2p_offers set status=?, deposit_tx_id = ? where id=?",[OfferService::STATUS_DEPOSITING,$deposit_tx_id, $id]);
            self::saveOfferLog($id);
            self::saveDepositTransferLog($id, $deposit_tx_id);
        });
    }

    public static function setOfferOpen($offer_id)
    {
        DBService::runInTransaction(function() use ($offer_id) {
            self::exec('update p2p_offers set status=? where id=?',[OfferService::STATUS_OPEN,$offer_id]);
            self::saveOfferLog($offer_id);
        });
    }

    public static function acceptSellOffer($offer_id, $base_receive_address, $user_accepted, $quote_dust_amount)
    {
        return DBService::runInTransaction(function() use ($offer_id, $quote_dust_amount, $user_accepted, $base_receive_address) {
            $sql= "update p2p_offers set status=? , base_receive_address =? , accepted_at=now(), user_accepted =?, quote_dust_amount =? where id=?";
            self::exec($sql, [OfferService::STATUS_ACCEPTED, $base_receive_address, $user_accepted, $quote_dust_amount, $offer_id]);
            OfferService::saveOfferLog($offer_id);
            return true;
        });
    }
    public static function acceptBuyOffer($offer_id, $quote_receive_address, $user_accepted, $base_dust_amount)
    {
        $sql= "update p2p_offers set status=? , quote_receive_address =? , accepted_at=now(), user_accepted =?, base_dust_amount =?  where id=?";
        return self::exec($sql, [OfferService::STATUS_ACCEPTED, $quote_receive_address, $user_accepted, $base_dust_amount, $offer_id]);
    }

    static function userAddress() {
        return $_SESSION['account']['address'] ?? null;
    }

    public static function cancelAcceptedOffer($offer)
    {
        DBService::runInTransaction(function() use ($offer) {
            if($offer['type']==OfferService::TYPE_SELL) {
                OfferService::cancelAcceptedSellOffer($offer['id']);
            } else {
                OfferService::cancelAcceptedBuyOffer($offer['id']);
            }
            OfferService::saveOfferLog($offer['id']);
        });
    }

    public static function cancelAcceptedSellOffer($offer_id)
    {
        $sql = "update p2p_offers set status=?, base_receive_address=null, accepted_at=null, user_accepted=null where id=?";
        self::exec($sql, [OfferService::STATUS_OPEN, $offer_id]);
    }
    public static function cancelAcceptedBuyOffer($offer_id)
    {
        $sql = "update p2p_offers set status=?, quote_receive_address=null, accepted_at=null, user_accepted=null where id=?";
        self::exec($sql, [OfferService::STATUS_OPEN, $offer_id]);
    }


    public static function getExpiredAcceoptedOffers()
    {
        $sql="select * from p2p_offers where status=? and accepted_at < ?";
        $time = time() - OfferService::OFFER_WAIT_TIME;
        return self::rows($sql, ['accepted', date('Y-m-d H:i:s',$time)]);
    }

    public static function getAcceptedOfferByAmount($amount, $asset_id)
    {
        return self::rows("select o.* from p2p_offers o
         left join p2p_markets m on o.market_id = m.id
         where o.status = ? and ((o.type = 'sell' and o.base_amount * o.base_price + o.quote_dust_amount = ? and m.quote_asset_id = ?) 
                                   or (o.type = 'buy' and o.base_amount + o.base_dust_amount = ? and m.base_asset_id = ?))",
            [OfferService::STATUS_ACCEPTED,$amount, $asset_id, $amount, $asset_id]);
    }
    public static function getOfferByDepositAmount($amount, $asset_id)
    {
        return self::rows("select o.* from p2p_offers o
         left join p2p_markets m on o.market_id = m.id
            where o.status = ? 
           and ((type = 'sell' and o.base_amount + o.base_dust_amount = ? and m.base_asset_id = ?) 
                    or (type = 'buy' and o.base_amount * o.base_price + o.quote_dust_amount = ? and m.quote_asset_id = ?)) ",
            [OfferService::STATUS_CREATED,$amount,$asset_id, $amount,$asset_id]);
    }

    public static function setAcceptedOfferTransferring(mixed $offer_id, $accept_tx_id)
    {
        DBService::runInTransaction(function() use ($offer_id, $accept_tx_id) {
            self::exec("update p2p_offers set status=?, accept_tx_id=? where id=?",
                [OfferService::STATUS_TRANSFERRING,$accept_tx_id,$offer_id]);
            OfferService::saveOfferLog($offer_id);
            OfferService::saveAcceptTransferLog($offer_id, $accept_tx_id);
        });

    }

    public static function setAcceptedOfferTransferred(mixed $offer_id)
    {

        DBService::runInTransaction(function() use ($offer_id) {
            self::exec("update p2p_offers set status=? where id=?",
                [OfferService::STATUS_TRANSFERRED,$offer_id]);
            self::saveOfferLog($offer_id);
        });

    }

    public static function getOffersByReceiveTxId(mixed $tx_id)
    {
        return self::row('select * from p2p_offers where accept_tx_id = ? or deposit_tx_id = ?',
            [$tx_id, $tx_id]);
    }

    static function cancelCreatedOffer($id) {
        DBService::runInTransaction(function() use ($id) {
            self::exec('update p2p_offers set status=?, canceled_at = now() where id=?',[OfferService::STATUS_CANCELED,$id]);
            OfferService::saveOfferLog($id);
        });
    }

    static function cancelOpenOffer($offer, $status = OfferService::STATUS_CANCELED) {
        _log("Returning funds to offer creator");
        $market = OfferService::getMarket($offer['market_id']);
        if($offer['type']==OfferService::TYPE_SELL) {
            $service = OfferService::getService($market['base_service']);
        } else {
            $service = OfferService::getService($market['quote_service']);
        }
        $depositTx = $service->findTransaction($offer['deposit_tx_id']);
        if(!$depositTx) {
            throw new \Exception("Cannot find deposit transaction");
        }
        $amount = $depositTx['amount'];
        $toAddress = $depositTx['from'];
        _log("Return deposited amount=".$amount." to src: ".$toAddress);
        $return_tx_id = $service->createPayment($amount,$toAddress,$offer);
        if(!$return_tx_id) {
            throw new \Exception("Cannot create return transaction");
        }
        DBService::runInTransaction(function() use ($status,$return_tx_id,$offer,&$res) {
            $res = self::exec('update p2p_offers set status=?, return_tx_id =?, canceled_at=now() where id=?',
                [$status,$return_tx_id,$offer['id']]);
            OfferService::saveOfferLog($offer['id']);
            OfferService::saveReturnTransferLog($offer['id'],$return_tx_id);
        });
        if(!$res) {
            _log("Cannot update offer status");
        } else {
            _log("Offer #".$offer['id']." updated status to: ".$status);
        }
    }

    public static function getTransferredOffers()
    {
        return self::rows('select * from p2p_offers where status = ? order by created_at desc',[OfferService::STATUS_TRANSFERRED]);
    }

    public static function setOfferPaying(mixed $offer_id)
    {
        return DBService::runInTransaction(function() use ($offer_id) {
            self::exec('update p2p_offers set status=? where id=? and base_transfer_tx_id is not null and quote_transfer_tx_id is not null',
                [OfferService::STATUS_PAYING, $offer_id]);
            OfferService::saveOfferLog($offer_id);
            return true;
        });
    }

    public static function getPayingOffers()
    {
        return self::rows('select * from p2p_offers where status = ? order by created_at desc',[OfferService::STATUS_PAYING]);
    }

    public static function setOfferClosed(mixed $offer)
    {
        DBService::runInTransaction(function() use ($offer) {
            self::exec('update p2p_offers set status=?, closed_at=now() where id=?',[OfferService::STATUS_CLOSED,$offer['id']]);
            OfferService::saveOfferLog($offer['id']);
        });

    }

    static function saveOfferLog($id)
    {
        if($id == null) {
            $a=1;
        }
        $offer = self::getOfferRow($id);
        $sql='insert into p2p_log(user, action, message, data, offer_id) values(?,?,?,?,?)';
        return self::exec($sql,[self::userAddress(), "offer", $offer['status'], json_encode($offer), $offer['id']]);
    }

    static function getOfferRow($id) {
        return self::row('select * from p2p_offers where id = ?', [$id]);
    }

    public static function label(int|string $key)
    {
        $labels = [
            'id' => 'Offer ID',
            'user_created' => 'User Created',
            'created_at' => 'Created At',
            'base_amount' => 'Base Coin Amount',
            'base_price' =>'Base Coin Price',
            'type' => 'Type',
            'expires_at' => 'Expires At',
            'quote_receive_address' => 'Quote asset Receive Address',
            'base_receive_address' => 'Base asset Receive Address',
            'status' => 'Status',
            'deposit_tx_id' => 'Deposit Tx ID',
            'return_tx_id' => 'Return Tx ID',
            'accepted_at' => 'Accepted At',
            'user_accepted' => 'User Accepted',
            'accept_tx_id' => 'Accept Tx ID',
            'base_transfer_tx_id' => 'Base asset Transfer Tx ID',
            'quote_transfer_tx_id' => 'Quote asset Transfer Tx ID',
            'closed_at' => 'Closed At',
            'canceled_at' => 'Canceled At',
            'updated_at' => 'Updated At',
            'base_dust_amount' => 'Base asset Coin Dust Amount',
            'quote_dust_amount'=>'Quote asset Dust Amount',
            'base'=>'Base asset',
            'quote'=>'Quote asset',
        ];
        if(isset($labels[$key])) {
            return $labels[$key];
        } else {
            return $key;
        }
    }

    public static function checkQuoteDustAmount($quote_total, AssetService $service, $market_id)
    {
        $limit = 100;
        $counter = 0;
        $quote_dust_amount = 0;
        while(true) {
            $counter++;
            if($counter >= $limit) {
                return false;
            }
            $sql='select * from p2p_offers where ((status = ? and type = ?) or (status = ? and type = ?)) 
                       and base_amount*base_price + quote_dust_amount = ? and market_id = ?';
            $total = $quote_total + $quote_dust_amount;
            $total = round($total, $service->getDecimals());
            $offers = self::rows($sql, [
                OfferService::STATUS_CREATED, OfferService::TYPE_BUY,
                OfferService::STATUS_ACCEPTED, OfferService::TYPE_SELL,
                $total, $market_id]);
            if(count($offers)==0) {
                return $quote_dust_amount;
            } else if (count($offers)>0) {
                $quote_dust_amount = rand(1, 99) / pow(10, $service->getDecimals());
            }
        }
    }

    public static function checkBaseDustAmount($base_amount, AssetService $service, $market_id)
    {
        $limit = 100;
        $counter = 0;
        $base_dust_amount = 0;
        while(true) {
            $counter++;
            if($counter >= $limit) {
                return false;
            }
            $sql='select * from p2p_offers where ((status = ? and type = ?) or (status = ? and type = ?)) 
                       and base_amount + base_dust_amount = ? and market_id = ?';
            $total = $base_amount + $base_dust_amount;
            $offers = self::rows($sql, [
                OfferService::STATUS_CREATED, OfferService::TYPE_SELL,
                OfferService::STATUS_ACCEPTED, OfferService::TYPE_BUY,
                $total, $market_id]);
            if(count($offers)==0) {
                return $base_dust_amount;
            } else if (count($offers)>0) {
                $base_dust_amount = rand(1, 99) / pow(10, $service->getDecimals());
            }
        }
    }

    public static function setBaseTransferTxId($base_transfer_tx_id, mixed $offer_id, $asset_id)
    {
        return DBService::runInTransaction(function() use ($offer_id, $base_transfer_tx_id, $asset_id) {
            self::exec('update p2p_offers set base_transfer_tx_id =? where id=?',
                [$base_transfer_tx_id, $offer_id]);
            OfferService::saveOfferLog($offer_id);
            OfferService::savePayemntTransferLog($offer_id, $base_transfer_tx_id, $asset_id, 'base');
            return true;
        });
    }
    public static function setQuoteTransferTxId($quote_transfer_tx_id, mixed $offer_id, $asset_id)
    {
        return DBService::runInTransaction(function() use ($quote_transfer_tx_id, $offer_id, $asset_id) {
            self::exec('update p2p_offers set quote_transfer_tx_id =? where id=?',
                [$quote_transfer_tx_id, $offer_id]);
            self::saveOfferLog($offer_id);
            OfferService::savePayemntTransferLog($offer_id, $quote_transfer_tx_id, $asset_id, 'quote');
            return true;
        });

    }

    static function getOpenffers($market_id) {
        return self::rows('select * from p2p_offers where status = ? and market_id =? order by type desc, base_price desc, created_at',
            [OfferService::STATUS_OPEN, $market_id]);
    }

    public static function getTradeHistory($market_id)
    {
        return self::rows('select * from p2p_offers where status = ? and market_id = ? order by closed_at desc',
            [OfferService::STATUS_CLOSED, $market_id]);
    }

    public static function getMyOpenOffers($market_id)
    {
        $user_id = self::userAddress();
        $sql="select * from p2p_offers where (user_created = ? or user_accepted = ?) and status = ? and market_id =? order by created_at desc";
        return self::rows($sql, [$user_id,$user_id, OfferService::STATUS_OPEN,$market_id]);
    }
    public static function getMyOffersHistory($market_id)
    {
        $user_id = self::userAddress();
        $sql="select * from p2p_offers where (user_created = ? or user_accepted = ?) and status != ? and market_id = ? order by COALESCE(updated_at, created_at) desc";
        return self::rows($sql, [$user_id,$user_id, OfferService::STATUS_OPEN, $market_id]);
    }
    public static function getMyCompletedOffers($market_id)
    {
        $user_id = self::userAddress();
        $sql="select * from p2p_offers where (user_created = ? or user_accepted = ?) and status = ? and market_id = ? order by created_at desc";
        return self::rows($sql, [$user_id,$user_id,OfferService::STATUS_CLOSED, $market_id]);
    }

    public static function getMinSellOffer($market_id)
    {
        $sql='select * from p2p_offers where status = ? and type = ? and market_id =? order by base_price, created_at desc limit 1';
        return self::row($sql, [OfferService::STATUS_OPEN, OfferService::TYPE_SELL,$market_id]);
    }

    public static function getMaxBuyOffer($market_id)
    {
        $sql='select * from p2p_offers where status = ? and type = ? and market_id =? order by base_price desc, created_at desc limit 1';
        return self::row($sql, [OfferService::STATUS_OPEN, OfferService::TYPE_BUY, $market_id]);
    }

    public static function storeUserCoinAddress(mixed $userAddress, string $symbol, $address)
    {
        $asset = self::getAssetBySymbol($symbol);
        $sql = 'replace into p2p_wallets (user_address, asset_id, address) values (?, ?, ?)';
        self::exec($sql, [$userAddress, $asset['id'], $address]);
    }

    public static function getUserCoinAddress(mixed $userAddress, string $symbol)
    {
        $asset  =self::getAssetBySymbol($symbol);
        $sql='select * from p2p_wallets where user_address = ? and asset_id = ?';
        $row =  self::row($sql, [$userAddress, $asset['id']]);
        return $row['address'];
    }

    static function getUserAddresses($user) {
        $sql= 'select w.*, a.name, a.symbol from p2p_wallets w
            left join p2p_assets a on w.asset_id = a.id
         where user_address = ?';
        return self::rows($sql, [$user]);
    }

    public static function getOpenBaseAmount($market_id)
    {
        $sql='select sum(base_amount) as total from p2p_offers where status = ? and type = ? and market_id = ?';
        $row =  self::row($sql, [OfferService::STATUS_OPEN, OfferService::TYPE_SELL, $market_id]);
        return $row['total'];
    }
    public static function getOpenQuoteAmount($market_id)
    {
        $sql='select sum(base_amount*base_price) as total from p2p_offers where status = ? and type = ? and market_id = ?';
        $row =  self::row($sql, [OfferService::STATUS_OPEN, OfferService::TYPE_BUY, $market_id]);
        return $row['total'];
    }

    public static function getChartData($params, $market_id)
    {
        $sql = "SELECT UNIX_TIMESTAMP(closed_at) as time, max(base_price) as base_price
            FROM p2p_offers where status = ? and market_id = ?
                        GROUP BY closed_at
                        ORDER BY closed_at";
        $rows = self::rows($sql, [OfferService::STATUS_CLOSED, $market_id]);
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = [
                'time' => intval($row['time']),
                'value' => floatval($row['base_price'])
            ];
        }

        $interval = $params['interval'];
        $interval = match ($interval) {
            '1d' => 60 * 60 * 24,
            '5m' => 60 * 5,
            default => 60 * 60,
        };

        $sql = "SELECT FLOOR(UNIX_TIMESTAMP(closed_at) / $interval) * $interval AS time_group,
            MIN(base_price) as low,
            MAX(base_price) as high,
            SUBSTRING_INDEX(GROUP_CONCAT(base_price ORDER BY closed_at), ',', 1) as open,
            SUBSTRING_INDEX(GROUP_CONCAT(base_price ORDER BY closed_at DESC), ',', 1) as close
          FROM p2p_offers where status = ? and market_id = ?
          GROUP BY time_group
          ORDER BY time_group";

        $rows = self::rows($sql, [OfferService::STATUS_CLOSED, $market_id]);
        $candles = [];
        foreach($rows as $row) {
            $candles[] = [
                'time' => intval($row['time_group']),
                'open' => floatval($row['open']),
                'high' => floatval($row['high']),
                'low' => floatval($row['low']),
                'close' => floatval($row['close']),
            ];
        }
        return [
            'lines' => $lines,"candles" => $candles
        ];
    }

    static function getMarketStat($market_id) {
        $sql='select base_price
            from p2p_offers
            where status = ? and market_id = ?
            order by closed_at desc
            limit 1';
        $row = self::row($sql, [OfferService::STATUS_CLOSED, $market_id]);
        $lastPrice = floatval($row['base_price']);

        $sql='select base_price
            from p2p_offers
            where status = ? and market_id = ?
            and closed_at > date_sub(now(), interval 1 day)
            order by closed_at desc
            limit 1;';
        $row = self::row($sql, [OfferService::STATUS_CLOSED, $market_id]);
        $prev24hPrice = floatval($row['base_price']);
        $change24h = $lastPrice - $prev24hPrice;
        $change24hPerc = $prev24hPrice == 0 ? 0 : $change24h * 100 / $prev24hPrice;

        $sql='select max(base_price) as max_price, min(base_price) as min_price,
            sum(base_amount) as base_volume, sum(base_amount * base_price) as quote_volume
            from p2p_offers
            where status = ? and market_id = ?
            and closed_at > date_sub(now(), interval 1 day)
            order by closed_at desc
            limit 1;';
        $row = self::row($sql, [OfferService::STATUS_CLOSED, $market_id]);
        $maxPrice = floatval($row['max_price']);
        $minPrice = floatval($row['min_price']);
        $baseVolume = floatval($row['base_volume']);
        $quoteVolume = floatval($row['quote_volume']);

        return [
            'change24h' => $change24h,
            'change24hPerc' => $change24hPerc,
            'maxPrice' => $maxPrice,
            'minPrice' => $minPrice,
            'baseVolume' => $baseVolume,
            'quoteVolume' => $quoteVolume
        ];
    }

    public static function getMarket(mixed $market_id)
    {
        $sql ='select m.market_name, ba.symbol as base, qa.symbol as quote, ba.service as base_service, qa.service as quote_service,
            m.image, ba.id as base_asset_id, qa.id as quote_asset_id
            from p2p_markets m 
          left join p2p_assets ba on ba.id = m.base_asset_id
          left join p2p_assets qa on qa.id = m.quote_asset_id
          where m.id = ?';
        $row = self::row($sql, [$market_id]);
        return $row;
    }

    public static function getAssetBySymbol(string $symbol)
    {
        $sql="select * from p2p_assets where symbol = ?";
        $row = self::row($sql, [$symbol]);
        return $row;
    }

    public static function getMarkets()
    {
        $sql="select mm.*,
       if(mm.prev_price = 0, 0, round((mm.last_price - mm.prev_price) * 100 / mm.prev_price, 2)) as price_change
from (select m.id,
             m.market_name,
             (select o.base_price
              from p2p_offers o
              where o.status = 'closed'
                and o.market_id = 1
              order by o.closed_at desc
              limit 1) as last_price,
             (select base_price
              from p2p_offers
              where status = 'closed'
                and market_id = 1
                and closed_at > date_sub(now(), interval 1 day)
              order by closed_at desc
              limit 1) as prev_price

      from p2p_markets m
      where m.active = 1) as mm;";
        return self::rows($sql,[]);
    }

    /**
     * @param mixed $className
     * @return AssetService
     */
    public static function getService(mixed $className)
    {
        if(class_exists($className)) {
            return new $className();
        }
    }

    public static function getAssets()
    {
        return self::rows("select * from p2p_assets");
    }

    public static function getMinCoinTrade()
    {
        return 1;
    }

    public static function getMarketByName(mixed $market)
    {
        $sql='select * from p2p_markets where market_name = ?';
        $row = self::row($sql, [$market]);
        return $row;
    }

    static function saveTransferLog($transferLog) {
        $sql = 'insert into p2p_transfer_log (type, height, src, dst, asset_id, amount,tx_id, offer_id) 
                values (?,?,?,?,?,?,?,?) ';
        self::exec($sql, [
            $transferLog['type'],
            $transferLog['height'],
            $transferLog['src'],
            $transferLog['dst'],
            $transferLog['asset_id'],
            $transferLog['amount'],
            $transferLog['tx_id'],
            $transferLog['offer_id']
        ]);
    }

    public static function saveDepositTransferLog($offer_id, $deposit_tx_id)
    {
        $offer = self::getOffer($offer_id);
        if($offer['type']==OfferService::TYPE_SELL) {
            $service = OfferService::getService($offer['base_service']);
            $asset_id = $offer['base_asset_id'];
        } else {
            $service = OfferService::getService($offer['quote_service']);
            $asset_id = $offer['quote_asset_id'];
        }
        $depositTx = $service->findTransaction($deposit_tx_id);
        $log = [
            'type' => 'deposit',
            'height' => @$depositTx['height'],
            'src' => @$depositTx['from'],
            'dst' => @$depositTx['to'],
            'asset_id' => $asset_id,
            'amount' => @$depositTx['amount'],
            'tx_id' => $deposit_tx_id,
            'offer_id' => $offer_id,
        ];
        self::saveTransferLog($log);
    }

    public static function saveReturnTransferLog($offer_id, $return_tx_id)
    {
        $offer = self::getOffer($offer_id);
        if($offer['type']==OfferService::TYPE_SELL) {
            $service = OfferService::getService($offer['base_service']);
            $asset_id = $offer['base_asset_id'];
        } else {
            $service = OfferService::getService($offer['quote_service']);
            $asset_id = $offer['quote_asset_id'];
        }
        $returnTx = $service->findTransaction($return_tx_id);
        $log = [
            'type' => 'return',
            'height' => @$returnTx['height'],
            'src' => @$returnTx['from'],
            'dst' => @$returnTx['to'],
            'asset_id' => $asset_id,
            'amount' => @$returnTx['amount'],
            'tx_id' => $return_tx_id,
            'offer_id' => $offer_id,
        ];
        self::saveTransferLog($log);
    }

    public static function saveAcceptTransferLog($offer_id, $transfer_tx_id)
    {
        $offer = self::getOffer($offer_id);
        if($offer['type']==OfferService::TYPE_SELL) {
            $service = OfferService::getService($offer['quote_service']);
            $asset_id = $offer['quote_asset_id'];
        } else {
            $service = OfferService::getService($offer['base_service']);
            $asset_id = $offer['base_asset_id'];
        }
        $transferTx = $service->findTransaction($transfer_tx_id);
        $log = [
            'type' => 'transfer',
            'height' => @$transferTx['height'],
            'src' => @$transferTx['from'],
            'dst' => @$transferTx['to'],
            'asset_id' => $asset_id,
            'amount' => @$transferTx['amount'],
            'tx_id' => $transfer_tx_id,
            'offer_id' => $offer_id,
        ];
        self::saveTransferLog($log);
    }

    public static function savePayemntTransferLog($offer_id, $payment_tx_id, $asset_id, $side)
    {
        $offer = self::getOffer($offer_id);
        if($side == 'base') {
            $service = OfferService::getService($offer['base_service']);
        } else {
            $service = OfferService::getService($offer['quote_service']);
        }
        $paymentTx = $service->findTransaction($payment_tx_id);
        $log = [
            'type' => 'payment',
            'height' => @$paymentTx['height'],
            'src' => @$paymentTx['from'],
            'dst' => @$paymentTx['to'],
            'asset_id' => $asset_id,
            'amount' => @$paymentTx['amount'],
            'tx_id' => $payment_tx_id,
            'offer_id' => $offer_id,
        ];
        self::saveTransferLog($log);
    }

    public static function getIncompleteTransferLogs()
    {
        $sql= 'select * from p2p_transfer_log tl where tl.height is null';
        $rows = self::rows($sql);
        return $rows;
    }

    public static function getAssetById(mixed $asset_id)
    {
        return self::row('select * from p2p_assets a where a.id = ?', [$asset_id]);
    }

    public static function updateTransferLogWithTransaction(mixed $id, $tx)
    {
        $sql='update p2p_transfer_log l set l.height = ?, l.src = ?, l.dst =? , l.amount = ? where l.id = ?';
        self::exec($sql, [$tx['height'], $tx['from'], $tx['to'], $tx['amount'], $id]);
    }

    public static function getLastTransferHeight(mixed $type, mixed $asset_id)
    {
        $sql= 'select max(l.height) as max from p2p_transfer_log l where l.type = ? and l.asset_id = ?';
        $row = self::row($sql, [$type, $asset_id]);
        return $row['max'];
    }

    public static function getOfferHistory(mixed $id)
    {
        $sql='select * from p2p_log l where l.offer_id = ? and l.action = ? order by l.created_at';
        $rows = self::rows($sql, [$id, 'offer']);
        return $rows;
    }

}