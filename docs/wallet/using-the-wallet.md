[PHPCoin Docs](../) > [Wallet](./) > Using the Wallet


---

# Using the Wallet

PHPcoin provides two ways to manage your wallet:

*   **Command-Line Wallet:** A command-line interface (CLI) for advanced users.
*   **Web-Based Wallet:** A user-friendly web interface for managing your wallet.

## Command-Line Wallet

The command-line wallet is a powerful tool that allows you to perform all wallet-related operations from the terminal.

### Creating a Wallet

To create a new wallet, simply run the `wallet.php` script from the `utils/` directory:

```bash
php utils/wallet.php
```

If no wallet file is found, a new one will be created. You will be prompted to encrypt the wallet with a password.

### Wallet Commands

The command-line wallet supports a variety of commands:

*   `balance [address]`: Check the balance of your wallet or a specific address.
*   `export`: Display your wallet's public and private keys.
*   `block`: Get information about the current block.
*   `encrypt`: Encrypt your wallet with a password.
*   `decrypt`: Decrypt your wallet.
*   `transactions`: List the latest transactions for your wallet.
*   `transaction <id>`: Get information about a specific transaction.
*   `send <address> <amount> [message]`: Send coins to another address.
*   `masternode-create <address> <reward_address>`: Create a masternode.
*   `masternode-remove <payout_address> [address]`: Remove a masternode.
*   `sign <message>`: Sign a message with your wallet's private key.
*   `smart-contract-create <address> <file>`: Create a smart contract.
*   `smart-contract-exec <address> <method>`: Execute a smart contract.
*   `smart-contract-send <address> <method>`: Send coins from a smart contract.

To get a list of all available commands, run:

```bash
php utils/wallet.php help
```

## Web-Based Wallet

The web-based wallet is part of the PHPcoin web interface. It provides a user-friendly way to manage your wallet, send and receive coins, and view your transaction history.

To access the web-based wallet, open your PHPcoin node's URL in your web browser and navigate to the "Wallet" section.
