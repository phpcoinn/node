[PHPCoin](../../README.md) > [Docs](../README.md) > [Mining](README.md) > How to Mine

---

# How to Mine

PHPcoin uses a Proof-of-Work (PoW) consensus algorithm, which means that new coins are created by solving complex mathematical problems. This process is called mining.

There are two ways to mine PHPcoin:

*   **Command-Line Mining:** Using the command-line interface (CLI) miner.
*   **Web-Based Mining:** Using a web-based miner that connects to a PHPcoin node.

## Command-Line Mining

The command-line miner is ideal for users who are comfortable with the terminal and want to have more control over the mining process.

### Configuration

Before you can start mining, you need to configure your miner by editing the `config/config.inc.php` file. You will need to set the following options:

*   `miner`: Set this to `true` to enable the command-line miner.
*   `miner_public_key`: Your PHPcoin public key. This is where the mining rewards will be sent.
*   `miner_private_key`: Your PHPcoin private key. This is used to sign the blocks you mine.
*   `miner_cpu`: The percentage of CPU you want to dedicate to mining. For example, `25` for 25% CPU usage.

### Running the Miner

To start the command-line miner, run the following command from the root of your PHPcoin installation:

```bash
php cli/miner.php
```

The miner will then start hashing and searching for new blocks.

## Web-Based Mining

The web-based miner is a more user-friendly option that allows you to mine PHPcoin directly from your web browser.

### Configuration

To enable the web-based miner, you need to configure your node by editing the `config/config.inc.php` file. You will need to set the following options:

*   `generator`: Set this to `true` to enable the web-based miner.
*   `generator_public_key`: The public key of the node.
*   `generator_private_key`: The private key of the node.
*   `allowed_hosts`: A list of IP addresses that are allowed to connect to the web miner. You can set this to `['*']` to allow all hosts.

### Running the Miner

Once you have configured your node, you can use a web-based miner to connect to it and start mining. You can find a list of compatible web miners on the PHPcoin website or community forums.
