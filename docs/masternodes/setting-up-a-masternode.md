[PHPCoin Docs](../) > [Masternodes](./) > Setting up a Masternode


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

---

## Advanced Masternode Setup

### Cold Masternode

A "cold" masternode setup allows you to keep the wallet containing your masternode collateral offline and secure, while the masternode itself runs on a separate server. This is the recommended setup for most users, as it significantly improves security.

The `masternode-create` command shown above is designed for a cold setup. The wallet that runs this command is the "control" wallet, and it can be kept offline after the masternode is created. The masternode itself runs on a separate server, and its wallet does not need to contain any funds.

### Security Best Practices

*   **Firewall:** Use a firewall to restrict access to your masternode server. Only allow incoming connections on the necessary ports (e.g., the P2P port and the web interface port).
*   **SSH Keys:** Use SSH keys instead of passwords to access your server. This is much more secure than using a password, which can be vulnerable to brute-force attacks.
*   **Regular Updates:** Keep your server's operating system and all software up to date. This will ensure that you have the latest security patches and bug fixes.

### Monitoring Your Masternode

It is important to monitor your masternode to ensure that it is running correctly and that you are receiving rewards. Here are some ways to monitor your masternode:

*   **API Endpoint:** Use the `/api.php?q=getMasternode` API endpoint to check the status of your masternode.
*   **Block Explorer:** Use a block explorer to check that your masternode is receiving rewards. You can find your masternode's reward address in the output of the `getMasternode` API call.
*   **Third-Party Monitoring Services:** There are several third-party services that can monitor your masternode for you and send you an alert if it goes offline.
