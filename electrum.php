<?php
class Electrum {

	private $_rpcurl;
	private $_rpcuser;
	private $_rpcpass;
	private $_rpcport;
	private $_rpchost;

	/**
	 * Initializes electrum class
	 *
	 * @param string $rpcuser User used for RPC connection
	 * @param string $rpcpass Password used for RPC connection
	 * @param string $rpchost (optional) Host used for RPC connection
	 * @param int $rpcport (optional) Port used for RPC connection
	 *
	 * @return void
	 */
	public function __construct(string $rpcuser, string $rpcpass, string $rpchost = "localhost", int $rpcport = 7777) {
		$this->_rpcurl = "http://$rpcuser:$rpcpass@$rpchost:$rpcport";
		$this->_rpcuser = $rpcuser;
		$this->_rpcpass = $rpcpass;
		$this->_rpcport = $rpcport;
		$this->_rpchost = $rpchost;
	}

	/**
	 * Executes electrum command using RPC connection.
	 *
	 * @param string $method Command to be executed
	 * @param array $params (optional) Command parameters
	 *
	 * @throws Exception If curl execution fails
	 * @return mixed JSON-decoded response (as array) from electrum
	 */
	public function curl(string $method, array $params = []) {
		$data = [
			"id" => "curltext",
			"method" => $method,
			"params" => $params,
		];

		$ch = curl_init($this->_rpcurl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type: application/json"]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);

		if (curl_error($ch)) {
			throw new Exception(curl_error($ch));
		}

		curl_close($ch);
		return json_decode($response, true)["result"];
	}

	/**
	 * Converts bitcoin to satoshi unit.
	 *
	 * @param float $btc Amount in bitcoin
	 *
	 * @return float Amount in satoshi
	 */
	public static function btc2sat(float $btc) : float {
		return $btc * 100000000;
	}

	/**
	 * Coverts satoshi to bitcoin unit.
	 *
	 * @param float $sat Amount in satoshi
	 *
	 * @return float Amount in bitcoin
	 */
	public static function sat2btc(float $sat) : float {
		return $sat / 100000000;
	}

	/**
	 * Broadcasts the hex-encoded transaction (TX) to the network.
	 *
	 * @param string $tx Hex-encoded transaction (TX)
	 *
	 * @return string Transaction hash (TXID)
	 */
	public function broadcast(string $tx) : string {
		$response = $this->curl("broadcast", [
			"tx" => $tx,
		]);

		return $response;
	}

	/**
	 * Generates a new receiving address.
	 *
	 * @return string Generated bitcoin address
	 */
	public function createnewaddress() : string {
		$response = $this->curl("createnewaddress", []);

		return $response;
	}

	/**
	 * Gets the wallet balance.
	 *
	 * @param bool $confirmed_only (optional) Should we include only confirmed funds
	 *
	 * @return float Wallet balance
	 */
	public function getbalance(bool $confirmed_only = false) : float {
		$response = $this->curl("getbalance", []);

		$total = 0.0;

		if (!$confirmed_only && key_exists("unconfirmed", $response)) $total += $response["unconfirmed"];
		if (key_exists("confirmed", $response)) $total += $response["confirmed"];

		return $total;
	}

	/**
	 * Returns recommended sat/byte fee rate for selected priority.
	 *
	 * @param float $fee_level (optional) Priority of the transaction where 0.0 is the lowest and 1.0 is the highest
	 *
	 * @return float Estimated sat/byte fee rate
	 */
	public function getfeerate(float $fee_level = 0.5) : float {
		if ($fee_level < 0.0 || $fee_level > 1.0) throw new Exception("fee_level must be between 0.0 and 1.0");

		$response = $this->curl("getfeerate", [
			"fee_level" => $fee_level,
		]);

		return floatval($response) / 1000;
	}

	/**
	 * Iterates through all of the transactions which met the provided criteria and returns an array of addresses and total transaction value.
	 *
	 * @param int $min_confirmations (optional) Only include transaction with X confirmations or more
	 * @param int $from_height (optional) Only include transaction since block X (inclusive)
	 * @param int|null $last_height (optional) Returns last processed block height (if any)
	 *
	 * @return array An array of receiving addresses and total transactions value
	 */
	public function history(int $min_confirmations = 1, int $from_height = 1, &$last_height) : array {
		$result = [];
		$response = json_decode($this->curl("history", [
			"show_addresses" => true,
			"show_fiat" => true,
			"show_fees" => true,
			"from_height" => $from_height,
		]), true);

		foreach ($response["transactions"] as $transaction) {
			if ($transaction["incoming"] !== true || $transaction["height"] === 0) continue;
			if ($transaction["confirmations"] < $min_confirmations) break;

			foreach ($transaction["outputs"] as $output)
				if ($this->ismine($output["address"]))
					$result[$output["address"]] += floatval($output["value"]);

			$last_height = $transaction["height"];
		}

		return $result;
	}

	/**
	 * Checks if provided address is owned by the current wallet.
	 *
	 * @param string $address Address to check
	 *
	 * @return bool
	 */
	public function ismine(string $address) : bool {
		$response = $this->curl("ismine", [
			"address" => $address,
		]);

		return $response;
	}

	/**
	 * Generates and signs a new transaction with provided parameters.
	 *
	 * @param string $destination Destination address to send to
	 * @param float $amount Amount to send in bitcoin unit
	 * @param float $amount_fee (optional) Fee amount in bitcoin unit (0.0 for dynamic)
	 *
	 * @return string Hex-encoded transaction (TX) ready to broadcast
	 */
	public function payto(string $destination, float $amount, float $amount_fee = 0.0) : string {
		if ($amount <= 0) return "";
		if ($amount_fee >= 0.01) return "";

		$param = [
			"destination" => $destination,
			"amount" => $amount,
		];

		if ($amount_fee > 0.0) {
			$param["fee"] = $amount_fee;
		}

		$response = $this->curl("payto", $param);

		return $response["hex"];
	}

	/**
	 * Generates and signs a new transaction with provided parameters. Sends all funds available.
	 *
	 * @param string $destination Destination address to send to
	 * @param float $amount_fee (optional) Fee amount in bitcoin unit (0.0 for dynamic)
	 *
	 * @return string Hex-encoded transaction (TX) ready to broadcast
	 */
	public function payto_max(string $destination, float $amount_fee = 0.0) : string {
		if ($amount_fee >= 0.01) return "";

		$param = [
			"destination" => $destination,
			"amount" => "!",
		];

		if ($amount_fee > 0.0) {
			$param["fee"] = $amount_fee;
		}

		$response = $this->curl("payto", $param);

		return $response["hex"];
	}

	/**
	 * Checks if provided address is valid or not.
	 *
	 * @param string $address Address to validate
	 *
	 * @return bool
	 */
	public function validateaddress(string $address) : bool {
		$response = $this->curl("validateaddress", [
			"address" => $address,
		]);

		return $response;
	}

}