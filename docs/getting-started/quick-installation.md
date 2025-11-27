[PHPCoin Docs](../) > [Getting Started](./) > Installation


---

# Installation

This guide will walk you through the process of installing a PHPcoin node on a Debian-based system (e.g., Ubuntu). The easiest way to install PHPcoin is by using the provided installation script, which automates the entire process.

## Prerequisites

Before you begin, you will need:

*   A server running a Debian-based Linux distribution (e.g., Ubuntu 20.04, 22.04).
*   Root or `sudo` privileges on the server.

## Quick Installation

The following one-liner command will download and execute the installation script, setting up a PHPcoin node for the **mainnet**:

```bash
curl -s https://phpcoin.net/scripts/install_node.sh | bash
```

To install a node for the **testnet**, use the following command:

```bash
curl -s https://phpcoin.net/scripts/install_node.sh | bash -s -- --network testnet
```

## What the Script Does

The installation script performs the following steps:

1.  **Updates System:** Updates the package manager and installs essential packages, including Nginx, PHP, and MariaDB.
2.  **Creates Database:** Sets up a new database and user for the PHPcoin node.
3.  **Downloads Node:** Clones the latest version of the PHPcoin source code from the official Git repository.
4.  **Configures Web Server:** Configures Nginx to serve the PHPcoin web interface.
5.  **Initializes Configuration:** Creates the necessary configuration files for the node.
6.  **Imports Blockchain:** Downloads and imports a recent snapshot of the blockchain to accelerate the initial synchronization process.
7.  **Starts Services:** Starts the Nginx and MariaDB services.

Once the installation is complete, you can access your PHPcoin node by opening the provided URL in your web browser.

<!-- Screenshot placeholder: A screenshot of the web interface after a successful installation -->

---

## Troubleshooting

If you encounter any issues during the installation, here are some common problems and their solutions:

*   **Script Fails to Run:** Ensure that you have `curl` installed and that you are running the command with `sudo` or as the root user.
*   **Port Conflict:** If another service is using port 80 (for mainnet) or 81 (for testnet), the Nginx service may fail to start. You can check for port conflicts using the `sudo netstat -tulpn | grep LISTEN` command.
*   **Database Connection Issues:** Verify that the MariaDB service is running and that the database credentials in `config/config.inc.php` are correct.
*   **Firewall Issues:** If you are unable to access the web interface, ensure that your server's firewall is configured to allow traffic on the appropriate port (80 or 81).
