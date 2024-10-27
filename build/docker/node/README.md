This is docker image for PHPCoin node (mainnet and testnet).

On first run container will download install script, install node and restore latest blockchain database

In order to preserve config file with private keys you should create volume and start container with volume:

```
docker volume create phpcoin-config
docker run -itd --name phpcoin -p 81:80 -e EXT_PORT=81 -v phpcoin-config:/var/www/phpcoin/config phpcoin/node
```

For running testnet node run container with other parameters:
```
docker volume create phpcoin-config-test
docker run -itd --name phpcoin-test -p 91:80 -e EXT_PORT=91 -e NETWORK=testnet -v phpcoin-config-test:/var/www/phpcoin/config phpcoin/node
```
