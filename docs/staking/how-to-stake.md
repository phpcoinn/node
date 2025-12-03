[PHPCoin Docs](../) > [Staking](./) > How to Stake

---

# How to Stake

## Introduction

Staking is the process of holding PHPCoin in your wallet to support the blockchain network. In return for holding coins, you receive rewards in the form of new PHPCoin.

## How Staking Works

PHPcoin uses a Proof-of-Stake (PoS) system to reward coin holders. Unlike miners or block generators, **stakers do not create or validate blocks**. Instead, for each block created, the network automatically chooses a "stake winner" from all eligible participants to receive a reward.

To be eligible for staking rewards, you must meet the network's minimum balance and coin maturity requirements.

## How to Start Staking

To become eligible for staking rewards, you must first send a special "stake" transaction. This is a one-time action that flags your address as a staking participant.

1.  **Open a terminal or command prompt.**
2.  **Navigate to the directory where your PHPcoin wallet is located.**
3.  **Run the following command:**

    ```bash
    php cli/wallet.php send <your-address> 0 "stake"
    ```

    Replace `<your-address>` with your own wallet address. The `0` is the amount to send (this special transaction is free), and `"stake"` is the message that activates your address for staking.

## Staking Requirements

### Current Requirements (Block 1,000,001+)

For the current mainnet (at over 1,250,000 blocks), the requirements are simple and fixed:

*   **Coin Maturity:** **60 blocks**. Your coins must be held for at least 60 blocks before they are eligible.
*   **Minimum Balance:** **160,000 PHPCoin**. You must hold at least this amount to be eligible.

### Historical Requirements

The staking requirements have changed during the blockchain's history.

*   **Before block 290,000:**
    *   Coin Maturity: 600 blocks
    *   Minimum Balance: 100 PHPCoin
*   **Block 290,001 - 1,000,000:**
    *   Coin Maturity: 60 blocks
    *   Minimum Balance: Increased in stages, from 30,000 to 140,000 PHPCoin.

---

## Technical Details

### 1. Activating an Address for Staking

An address is recognized as a staking address after it has been the destination of a transaction with the message `"stake"`.

*   **Code Reference:** The `getAddressTypes` function in `include/class/Block.php`.

### 2. Code References for Requirements

*   **Maturity:** The `getStakingMaturity()` function in `include/class/Blockchain.php` controls this value. The change from 600 to 60 blocks is triggered by the `UPDATE_11_STAKING_MATURITY_REDUCE` constant (value: `290000`) in `include/coinspec.inc.php`.
*   **Minimum Balance:** The `getStakingMinBalance()` function in `include/class/Blockchain.php` controls this value. The change from a fixed 100 PHPCoin to a dynamic value is triggered by the `UPDATE_12_STAKING_DYNAMIC_THRESHOLD` constant (value: `290000`) in `include/coinspec.inc.php`. The specific amounts are derived from the `REWARD_SCHEME` constant in `include/rewards.inc.php`.

### 3. Stake Winner Selection

For each block, a single stake winner is selected from all eligible accounts based on a `weight`.

*   **Code Reference:** The `getStakeWinner` function in `include/class/Account.php` calculates this `weight` using the formula: `(current_block_height - last_transaction_height) * account_balance`. The account with the highest weight wins.

### 4. Staking Rewards

The reward amount is determined by the block height.

*   **Code Reference:** The `reward` function in `include/class/Block.php` reads the reward structure from the `REWARD_SCHEME` constant in `include/rewards.inc.php`.
