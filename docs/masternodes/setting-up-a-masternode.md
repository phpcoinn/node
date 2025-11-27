[PHPCoin](../../README.md) > [Docs](../README.md) > [Masternodes](README.md) > Setting up a Masternode

---

# Setting up a Masternode

A masternode is a special type of node that provides additional services to the PHPcoin network. In return for these services, masternode owners receive rewards in the form of PHPcoin.

## Requirements

To set up a masternode, you will need:

*   A running PHPcoin node.
*   Enough PHPcoin to cover the masternode collateral. You can check the current collateral amount by calling the `/api.php?q=getCollateral` API endpoint.
*   A separate PHPcoin address for the masternode. This address will be used to identify the masternode on the network.
*   Optionally, a separate PHPcoin address to receive the masternode rewards. This is known as a "cold" masternode setup.

## Setting up a Masternode

To set up a masternode, you will use the command-line wallet.

### 1. Create a Masternode Address

First, you need to create a new PHPcoin address that will be used for your masternode. You can do this by running the `wallet.php` script from the `utils/` directory:

```bash
php utils/wallet.php
```

This will create a new `phpcoin.dat` file in the current directory. Make sure to back up this file in a safe place.

### 2. Fund the Masternode Address

Next, you need to send the exact collateral amount to the masternode address you just created. You can do this from another wallet or an exchange.

### 3. Create the Masternode

Once the collateral transaction has been confirmed, you can create the masternode by running the `masternode-create` command from your main wallet (not the masternode wallet):

```bash
php utils/wallet.php masternode-create <masternode_address> [reward_address]
```

*   `<masternode_address>`: The address of your masternode.
*   `[reward_address]` (optional): The address where you want to receive the masternode rewards. If you don't specify a reward address, the rewards will be sent to the masternode address.

This command will create and send a special transaction to the network that will register your masternode.

### 4. Verifying Your Masternode

You can verify that your masternode is running correctly by calling the `/api.php?q=getMasternode` API endpoint:

```
/api.php?q=getMasternode&address=<masternode_address>
```

If your masternode is set up correctly, this will return information about your masternode.
