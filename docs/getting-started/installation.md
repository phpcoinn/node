[PHPCoin](../../README.md) > [Docs](../README.md) > [Getting Started](README.md) > Installation

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
