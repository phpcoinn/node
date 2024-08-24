#!/bin/bash


NETWORK="${NETWORK:-mainnet}"
ADDRESS="${ADDRESS:-PtD756PoeBfw6KgCLqjpD4sYdZsaFu536F}"
CPU="${CPU:-100}"
NUM_THREADS=$(nproc --all)
THREADS="${THREADS:-$NUM_THREADS}"

MINING_NODE=https://m1.phpcoin.net
if [ "$NETWORK" = "testnet" ]
then
  MINING_NODE=https://miner1.phpcoin.net
fi
NODE="${NODE:-$MINING_NODE}"

php /phpcoin/utils/miner.php $NODE $ADDRESS $CPU --threads=$THREADS
