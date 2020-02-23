# php-electrum-class

![release](https://img.shields.io/github/release/Zaczero/php-electrum-class.svg)
![license](https://img.shields.io/github/license/Zaczero/php-electrum-class.svg)

A simple, yet powerful Electrum class for PHP which allows you to receive cryptocurrency payments without any third party integrations nor KYC verification. Works with Linux, Windows and OSX installations. Latest SegWit address format is supported as well.

## 🌤️ Installation

### Install with composer (recommended)

`composer require zaczero/php-electrum-class`

*[Get composer here - getcomposer.org](https://getcomposer.org)*

### Install manually

[Browse latest GitHub release](https://github.com/Zaczero/php-electrum-class/releases/latest)

## 🏁 Getting started

### Installing Electrum

Let's start with setting up an electrum installation on your machine. Please follow the instructions at the [electrum.org/#download](https://electrum.org/#download). Alternatively if you are using Linux you can execute the following list of commands. Make sure to change the download URL to match the latest version of electrum.

```bash
# Install dependencies
sudo apt-get install python3-pyqt5

# Download package
wget https://download.electrum.org/3.3.8/Electrum-3.3.8.tar.gz

# Extract package
tar -xvf Electrum-3.3.8.tar.gz

# Install electrum command
sudo ln -s $(pwd)/Electrum-3.3.8/run_electrum /usr/local/bin/electrum

# Check if everything works properly
electrum help
```

### Configuring RPC

Now you have to set the username, password and port for the RPC connection. You can do that by running those commands. Make sure that the password is hard to guess and that the port is unreachable from behind the firewall.

```bash
electrum setconfig rpcuser "user"
electrum setconfig rpcpassword "S3CR3T_password"
electrum setconfig rpcport 7777
```

#### [Testnet] Configuring RPC

```bash
electrum setconfig rpcuser "user" --testnet
electrum setconfig rpcpassword "S3CR3T_password" --testnet
electrum setconfig rpcport 7777 --testnet
```

### Creating wallet

To create the wallet execute the command:

* SegWit wallet

```bash
electrum create --segwit
```

* Legacy wallet

```bash
electrum create
```

#### [Testnet] Creating wallet

```bash
electrum create --segwit --testnet
# or
electrum create --testnet
```

### Starting Electrum in daemon mode

There are two commands you have to run in order to have our electrum daemon function properly.

```bash
# Start the daemon
electrum daemon start

# Load the wallet
electrum daemon load_wallet
```

Please note that you will have to load the wallet every time you start the daemon. The same applies for the autostart procedure.

#### [Testnet] Starting Electrum in daemon mode

```bash
# Start the daemon
electrum daemon start --testnet

# Load the wallet
electrum daemon load_wallet --testnet
```

### (Optional linux-only) Create autostart entry

The last step would be to make electrum daemon autostart itself on the system boot. You can achieve that by adding a `@reboot` entry to the cron service. To edit the cron tasks execute the following command.

```bash
sudo crontab -e
```

Then simply create a reboot entry in a new line:

```bash
@reboot electrum daemon start; electrum daemon load_wallet
```

#### [Testnet] Create autostart entry

```bash
@reboot electrum daemon start --testnet; electrum daemon load_wallet --testnet
```

## 🎡 Using PHP Electrum class

First of all make sure to `require` the php-electrum-class file. Then you can initialize the class with the default constructor which requires *rpcuser* and *rpcpassword* variables. If you are planning to use the testnet please provide the testnet connection settings. Optionally you can also pass a custom *rpchost* and *rpcport* values *(by default it's localhost:7777)*.

```php
require_once "electrum.php";

$rpcuser = "user";
$rpcpass = "CHANGE_ME_PASSWORD";

$electrum = new Electrum($rpcuser, $rpcpass);
var_dump($electrum->getbalance());
```

### 📚 Class documentation

* **btc2sat(float $btc) : float**
  * $btc - bitcoin value
  * return - satoshi value

Converts bitcoin to satoshi unit.

---

* **sat2btc(float $sat) : float**
  * $sat - satoshi value
  * return - bitcoin value

Converts satoshi to bitcoin unit.

---

* **broadcast(string $tx) : string**
  * $tx - hex-encoded transaction
  * return - transaction hash (txid)

Broadcasts the hex-encoded transaction to the network.

---

* **getfeerate(float $fee_level = 0.5) : string**
  * $fee_level - transaction priority *(range from 0.0 to 1.0)*
  * return - sat/byte fee rate

Returns recommended sat/byte fee rate for chosen priority. $fee_level = 0 means that you don't care when the transaction will be going through. You just want to save as much as possible on the fee. $fee_level = 1 means that you want to process the transaction as soon as possible.

---

* **createnewaddress() : string**
  * return - new receiving address

Generates a new receiving address.

---

* **getbalance(bool $confirmed_only = false) : float**
  * $confirmed_only - include only confirmed transactions
  * return - confirmed account balance

This one is obvious. However please keep in mind that if you decide to include only confirmed transactions even if you send some amount from the wallet you will not see a change until the outgoing transaction gets confirmed.

---

* **history(int $min_confirmations = 1, int $from_height = 1, &$last_height) : array**
  * $min_confirmations - only include transaction with X confirmations or above
  * $from_height - only include transaction from block X or above
  * &$last_height - returns lastly processed block height
  * return - an array of receiving addresses and total transactions value

Iterates through all of the transactions which met the provided criteria and returns an array of addresses and total transaction value. Addresses are receiving addresses (not sending). Those are the same which got generated using `createnewaddress()` function.

---

* **ismine(string $address) : bool**
  * $address - address to check
  * return - true or false

Checks if provided address is owned by the local wallet.

---

* **payto(string $destination, float $amount, float $amount_fee = 0.0) : string**
  * $destination - destination address to send to
  * $amount - amount to send in bitcoin unit
  * $amount_fee - fee amount in bitcoin unit *(0 is dynamic)*
  * return - hex-encoded transaction ready to broadcast

Generates and signs a new transaction with provided parameters.

---

* **payto_max(string $destination, float $amount_fee = 0.0) : string**
  * $destination - destination address to send to
  * $amount_fee - fee amount in bitcoin unit *(0 is dynamic)*
  * return - hex-encoded transaction ready to broadcast

Generates and signs a new transaction with provided parameters. Sends all funds which are available.

---

* **validateaddress(string $address) : bool**
  * $address - address which should be validated
  * return - true or false

Checks if provided address is valid or not.

## 🏫 Example usage

### Creating a new receiving address

Simply use a createnewaddress() function to generate the address and save it in the database alongside with the payment amount and the customer ID for later processing. A new address shall be generated for each payment request.

```php
require_once "electrum.php";

$rpcuser = "user";
$rpcpass = "CHANGE_ME_PASSWORD";

$electrum = new Electrum($rpcuser, $rpcpass);

$receive_address = $electrum->createnewaddress();
$price = 0.001;

// pseudo code
db_save_smart($receive_address, $price, $user_id);
render_view();
```

### Processing payments (cron task)

Iterate through receive_address->amount dictionary returned by history() function which contains all newly received payments. Then fetch the payment data by querying the database provided the receive_address. Add the amount to total received and then finalize the payment if total received is greater or equal than the required amount.

Finally remember to save the $last_height returned by history() function so we don't process the same block twice or more. Please note that if there are no transactions returned then the $last_height will remain the same as provided initially.

```php
require_once "electrum.php";

$rpcuser = "user";
$rpcpass = "CHANGE_ME_PASSWORD";

$electrum = new Electrum($rpcuser, $rpcpass);

$min_confirmations = 1;
$last_height = db_load("last_height");
$from_height = $last_height + 1;

// iterate through all receive transactions
foreach ($electrum->history(
    $min_confrimations,
    $from_height,
    $last_height) as $receive_address => $amount) {

    // fetch data by receive_address as a unique key
    db_where("receive_address", $receive_address);

    $price = db_load("price");
    $user_id = db_load("user_id");
    $amount_paid = db_load("amount_paid");
    $completed = db_load("completed");

    $amount_paid += $amount;

    // check if user paid the total amount
    if ($amount_paid >= $price && !$completed) {
        deliver_product($user_id);

        db_where("receive_address", $receive_address);
        db_save("amount_paid", $amount_paid);
        db_save("completed", true);
    }
    else {
        // wait for more money or already delivered
        db_where("receive_address", $receive_address);
        db_save("amount_paid", $amount_paid);
    }
}

// we have to store the last_height to make sure
// we don't process the same transaction twice
db_save($last_height);
```

### Sending all funds to selected address

```php
require_once "electrum.php";

$rpcuser = "user";
$rpcpass = "CHANGE_ME_PASSWORD";

$electrum = new Electrum($rpcuser, $rpcpass);

// generate transaction for sending all available funds
$tx = $electrum->payto_max("BTC_ADDRESS");
// and broadcast it to the network
$txid = $electrum->broadcast($tx);

// browse the transaction on blockchair.com
$redirect_url = "https://blockchair.com/bitcoin/transaction/".$txid;
header("Location: ".$redirect_url);
```

### Sending funds to selected address with custom fee

```php
require_once "electrum.php";

$rpcuser = "user";
$rpcpass = "CHANGE_ME_PASSWORD";

$electrum = new Electrum($rpcuser, $rpcpass);

// get recommended fee rate as sat/byte
// you can hard-code this value (eg. 1 will be 1 sat/byte)
$fee_rate = $electrum->getfeerate(0.3);
// generate a temporary transaction to estaminate the size
// we don't broadcast it, just use for calculations
$tx_tmp = $electrum->payto("BTC_ADDRESS", 0.2);
// calculate a fee rate in bitcoin for given transaction
// we divide by two because tx is hex-encoded this means 2 chars = 1 byte
$fee = $electrum->sat2btc($fee_rate * strlen($tx_tmp) / 2);

// send 0.2 BTC and include the bitcoin fee as a third parameter
$tx = $electrum->payto("BTC_ADDRESS", 0.2, $fee);
$txid = $electrum->broadcast($tx);

// browse the transaction on blockchair.com
$redirect_url = "https://blockchair.com/bitcoin/transaction/".$txid;
header("Location: ".$redirect_url);
```

## Footer

### 📧 Contact

* Email: [kamil@monicz.pl](mailto:kamil@monicz.pl)

### 📃 License

* [Zaczero/php-electrum-class](https://github.com/Zaczero/php-electrum-class/blob/master/LICENSE)