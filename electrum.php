<?php
class Electrum {

	private $_rpcurl;
	private $_rpcuser;
	private $_rpcpass;
	private $_rpcport;
	private $_rpchost;

	public function __construct(string $rpcuser, string $rpcpass, string $rpchost = "localhost", int $rpcport = 7777) {
		$this->_rpcurl = "http://$rpcuser:$rpcpass@$rpchost:$rpcport";
		$this->_rpcuser = $rpcuser;
		$this->_rpcpass = $rpcpass;
		$this->_rpcport = $rpcport;
		$this->_rpchost = $rpchost;
	}

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

	public static function btc2sat(float $btc) : float {
		return $btc * 100000000;
	}

	public static function sat2btc(float $sat) : float {
		return $sat / 100000000;
	}

	public function broadcast(string $tx) : string {
		$response = $this->curl("broadcast", [
			"tx" => $tx,
		]);

		return $response;
	}

	public function createnewaddress() : string {
		$response = $this->curl("createnewaddress", []);

		return $response;
	}

	public function getbalance(bool $confirmed_only = false) : float {
		$response = $this->curl("getbalance", []);

		$total = 0.0;

		if (!$confirmed_only && key_exists("unconfirmed", $response)) $total += $response["unconfirmed"];
		if (key_exists("confirmed", $response)) $total += $response["confirmed"];

		return $total;
	}

	public function getfeerate(float $fee_level = 0.5) : float {
		if ($fee_level < 0.0 || $fee_level > 1.0) throw new Exception("fee_level must be between 0.0 and 1.0");

		$response = $this->curl("getfeerate", [
			"fee_level" => $fee_level,
		]);

		return floatval($response) / 1000;
	}

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

	public function ismine(string $address) : bool {
		$response = $this->curl("ismine", [
			"address" => $address,
		]);

		return $response;
	}

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

	public function validateaddress(string $address) : bool {
		$response = $this->curl("validateaddress", [
			"address" => $address,
		]);

		return $response;
	}

}