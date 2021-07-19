# node

The PHPCoin (PHP) cryptocurrency node.

## Install

**Hardware Requirements:**
```
2GB RAM
1 CPU Core
50GB DISK
```
**Requirements:**

- PHP 7.2
  - PDO extension
  - GMP extension
  - BCMath extension
- MySQL/MariaDB

1. Install MySQL or MariaDB and create a database and a user.
2. Rename `include/config-sample.inc.php` to  `config/config.inc.php` and set the DB login data
3. Change permissions to tmp and `tmp/db-update` to 777 (`chmod 777 tmp -R`)
4. Access the http://ip-or-domain and refresh once

## Usage

This app should only be run in the main directory of the domain/subdomain, ex: http://111.111.111.111

The node should have a public IP and be accessible over internet.

## Links

- Official website: https://phpcoin.net
- Block explorer: https://explorer.phpcoin.net
- Forums: https://forum.phpcoin.net

## Development Fund

Coin | Address
---- | --------
[PHP]: | 5WuRMXGM7Pf8NqEArVz1NxgSBptkimSpvuSaYC79g1yo3RDQc8TjVtGH5chQWQV7CHbJEuq9DmW5fbmCEW4AghQr
[LTC]: | LWgqzbXGeucKaMmJEvwaAWPFrAgKiJ4Y4m
[BTC]: | 1LdoMmYitb4C3pXoGNLL1VRj7xk3smGXoU
[ETH]: | 0x4B904bDf071E9b98441d25316c824D7b7E447527
[BCH]: | qrtkqrl3mxzdzl66nchkgdv73uu3rf7jdy7el2vduw

If you'd like to support the PHPCoin development, you can donate to the addresses listed above.

[php]: https://phpcoin.net
[ltc]: https://litecoin.org
[btc]: https://bitcoin.org
[eth]: https://ethereum.org
[bch]: https://www.bitcoincash.org
