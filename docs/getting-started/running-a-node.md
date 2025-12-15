[PHPCoin Docs](../) > [Getting Started](./) > Running a Node


---

# Running a Node

Once you have installed your PHPcoin node, it will be running as a web application. You can access the web interface by navigating to the URL provided at the end of the installation process.

## Web Interface

The web interface provides a user-friendly way to interact with your node. It includes the following features:

*   **Block Explorer:** Allows you to browse the blockchain, view blocks, and inspect transactions.
*   **Wallet:** Provides a simple and secure way to manage your PHPcoin wallet, send and receive coins, and view your transaction history.
*   **Network Information:** Displays information about the current state of the network, including the number of connected peers and the current block height.

## Command-Line Interface (CLI)

In addition to the web interface, PHPcoin provides a command-line interface (CLI) for more advanced users. The CLI scripts are located in the `cli/` directory and can be used to perform various tasks, such as:

*   **Running the P2P Server:** The `server.php` script is used to run the node's peer-to-peer (P2P) server, which is responsible for communicating with other nodes on the network. To run the server, execute the following command from the root of your PHPcoin installation:

    ```bash
    php cli/server.php
    ```

*   **Synchronizing the Blockchain:** The `sync.php` script is used to manually trigger the synchronization of the blockchain. This is useful if your node has been offline for a while and needs to catch up with the rest of the network.

    ```bash
    php cli/sync.php
    ```

*   **Running Cron Jobs:** The `cron.php` script is designed to be run periodically to perform maintenance tasks, such as cleaning up old data and updating the list of peers.

    ```bash
    php cli/cron.php
    ```

*   **Mining:** The `miner.php` script can be used to mine PHPcoin from the command line.

    ```bash
    php cli/miner.php
    ```

For most users, the web interface will be sufficient for interacting with the PHPcoin network. However, the CLI provides a powerful set of tools for those who want more control over their node.
