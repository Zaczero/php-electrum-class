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

	public function getbalance() : float {
		$response = $this->curl("getbalance", []);

		if (!key_exists("confirmed", $response)) return 0;

		return $response["confirmed"];
	}

	public function history(int $min_confirmations = 1, int $from_height = 1, &$last_height = null) : array {
		$result = [];
		$response = json_decode($this->curl("history", [
			"show_addresses" => true,
			"show_fiat" => true,
			"show_fees" => true,
			"from_height" => $from_height,
		]), true);

		$last_height = $from_height;

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

	public function payto(string $destination, float $amount) : string {
		if ($amount <= 0) return "";

		$response = $this->curl("payto", [
			"destination" => $destination,
			"amount" => $amount,
		]);

		return $response["hex"];
	}

	public function payto_max(string $destination) : string {
		$response = $this->curl("payto", [
			"destination" => $destination,
			"amount" => "!",
		]);

		return $response["hex"];
	}

	public function validateaddress(string $address) : bool {
		$response = $this->curl("validateaddress", [
			"address" => $address,
		]);

		return $response;
	}

}