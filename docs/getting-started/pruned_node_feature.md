# PHP Coin – Pruned Node Feature

## Overview

As the PHP Coin blockchain grows, storing the full history of blocks and transactions becomes increasingly expensive in terms of disk space and sync time. To address this, PHP Coin introduces **Pruned Nodes**.

A pruned node keeps the **current blockchain state** while discarding old, non-critical historical data. This dramatically reduces database size and installation time, while keeping the node fully functional for everyday use.

---

## What Is a Pruned Node?

A **pruned node** stores:
- Full blockchain **state** (accounts, balances, masternodes, staking, tokens, contracts)
- All blocks and transactions **after a configured pruned height**
- Only **important historical transactions** required for state reconstruction

It does **not** store:
- Full transaction history before the pruned height
- Old blocks that are no longer required for validation

---

## Why Pruned Nodes?

Pruned nodes provide major benefits:

- ✅ **Much smaller database** (~950 MB vs 12+ GB on testnet)
- ✅ **Very fast setup** (minutes instead of hours)
- ✅ Lower disk and I/O requirements
- ✅ Easier for new users to run nodes
- ✅ Better long-term scalability

This allows PHP Coin to grow without forcing every node operator to store the entire blockchain history.

---

## What Still Works on a Pruned Node?

Pruned nodes are **fully functional** for most users.

Supported features:
- Block validation
- Transaction broadcasting
- Wallet operations
- Mining
- Staking
- Masternodes
- Smart contract execution
- Token operations
- API access **for blocks and transactions after the pruned height**

The node independently validates new blocks using its local state.

---

## Limitations of Pruned Nodes

Pruned nodes have intentional limitations:

- ❌ No access to blocks or transactions **before the pruned height**
- ❌ Cannot serve full blockchain explorers
- ❌ Not suitable for historical analytics or audits

For these use cases, **full nodes** are required.

---

## Full Nodes vs Pruned Nodes

| Feature | Full Node | Pruned Node |
|------|---------|------------|
| Full history | ✅ Yes | ❌ No |
| Database size | Large | Small |
| Fast install | ❌ No | ✅ Yes |
| Explorer hosting | ✅ Yes | ❌ No |
| API (recent data) | ✅ Yes | ✅ Yes |
| Staking / Masternodes | ✅ Yes | ✅ Yes |

---

## Pruned Height

The **pruned height** defines the oldest block that is kept in the database.

Example:
```
pruned_height = 1,800,000
```

This means:
- Blocks and transactions **before** height `1,800,000` are removed
- Blocks and transactions **after** this height are fully available via API

---

## Installation (Recommended)

The recommended way to run a pruned node is to **restore a pre-pruned database snapshot**.

```bash
curl -s https://phpcoin.net/scripts/restore_db.php?download -o restore_db.php && php restore_db.php
```

This method:
- Is fast
- Avoids long pruning operations
- Produces a clean pruned database

Pruned mode is the **default for new testnet installs**.

---

## Converting an Existing Node (Not Recommended)

You can convert an existing full node to a pruned node:

```bash
php cli/util.php config-set pruned_height 1800000
php cli/util.php prune-node --log
```

⚠️ This process can take **~30 minutes or more** and is intended mainly for testing.

---

## Testnet Results

Testnet measurements:

**Full node:**
- Blocks: ~1.9M
- Transactions: ~7.5M
- Database size: ~12.9 GB
- Install time: ~60 minutes

**Pruned node:**
- Stored blocks: ~128k
- Stored transactions: ~674k
- Database size: ~950 MB
- Install + restore time: ~5 minutes

---

## Network Strategy

- Pruned nodes are intended for **most users**
- Full nodes are intended for **infrastructure providers**:
  - explorers
  - public APIs
  - historical data
  - snapshot distribution

A healthy PHP Coin network will consist of:
- Many pruned nodes
- A smaller number of well-maintained full nodes

---

## Future Plans

- Extended documentation
- Installer options (pruned vs full)
- Full-node incentives (subdomains, explorer hosting)
- Mainnet rollout after extended testnet monitoring

---

## Summary

Pruned nodes are a major step forward for PHP Coin scalability.

They make running a node:
- faster
- cheaper
- easier

while preserving full decentralization through optional full nodes.

This approach ensures PHP Coin can grow sustainably for the long term.

