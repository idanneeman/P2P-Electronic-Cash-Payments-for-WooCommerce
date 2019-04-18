<?php
defined( 'ABSPATH' ) or die( 'Bitcoin is for all!' );

// APIs to retrieve data from the blockchain
// such as balance
// All APIs should extend from BlockchainAPI
// Should implement the following:
// * get_supported_variants
// * get_funds_received
// If the API needs an API key, then the
// following should also be overriden:
// * __construct
// * is_active
abstract class BlockchainAPI {

	protected $variant_in_use;

	public function __construct( $variant_in_use ) {
		$this->variant_in_use = strtolower( $variant_in_use );
		$this->api_timeout    = ecp__get_settings()['blockchain_api_timeout_secs'];
	}

	abstract protected function get_supported_variants();

	protected function is_variant_supported() {
		 return in_array( $this->variant_in_use, $this->get_supported_variants() );
	}

	public function is_active() {
		if ( $this->is_variant_supported() ) {
			return true;
		}
		return false;
	}

	abstract public function get_funds_received( $address);
}

class BlockdozerAPI extends BlockchainAPI {

	protected function get_supported_variants() {
		return array( 'bch' );
	}

	public function get_funds_received( $address ) {
		$funds_received = ECP__file_get_contents( 'http://blockdozer.com/insight-api/addr/' . $address['btc_address'] . '/totalReceived', $this->api_timeout );

		if ( ! is_numeric( $funds_received ) ) {
			return false;
		}
		return $funds_received;
	}
}

class BlockExplorerAPI extends BlockchainAPI {

	protected function get_supported_variants() {
		return array( 'bch' );
	}

	public function get_funds_received( $address ) {
		$funds_received = ECP__file_get_contents( 'https://bitcoincash.blockexplorer.com/api/addr/' . $address['btc_address'] . '/totalReceived', $this->api_timeout );

		if ( ! is_numeric( $funds_received ) ) {
			return false;
		}
		return $funds_received;
	}
}

class TokenViewAPI extends BlockchainAPI {

	protected function get_supported_variants() {
		return array( 'bch', 'bsv' );
	}

	private function get_network() {
		switch ( $this->variant_in_use ) {
			case 'bch':
				return 'BCH';
				break;
			case 'bsv':
				return 'BCHSV';
				break;
			default:
				return 'none';
				break;
		}
	}

	private function extract_funds_received( $json_val, $address ) {
		// 404 is not found, since address is generated by us
		// we know it is correct so, assume address has 0 transactions
		if ( $json_val && $json_val['code'] == 404 ) {
			return 0;
		}

		if ( ! ( $json_val && $json_val['data'] ) ) {
			return false;
		}

		$data = $json_val['data'];

		$network = $this->get_network();
		foreach ( $data as $coin ) {
			if ( $coin['type'] != 'address' ||
				$coin['network'] != $network ||
				$coin['hash'] != $address ) {
				continue;
			}
			return $coin['receive'];
		}
		// address doesn't show up for the selected network
		return 0;
	}

	public function get_funds_received( $address ) {
		$funds_received = $this->extract_funds_received( @json_decode( trim( @ECP__file_get_contents( 'http://www.tokenview.com:8088/search/' . $address['btc_address'], $this->api_timeout ) ), true ), $address['btc_address'] );

		if ( ! is_numeric( $funds_received ) ) {
			return false;
		}
		return $funds_received;
	}
}

class BTCComAPI extends BlockchainAPI {

	protected function get_supported_variants() {
		return array( 'bch', 'bsv' );
	}

	private function extract_funds_received( $json_val ) {
		// 404 is not found, since address is generated by us
		// we know it is correct so, assume address has 0 transactions
		if ( $json_val && $json_val['err_no'] != 0 ) {
			return false;
		}

		if ( ! ( $json_val && $json_val['data'] ) ) {
			return 0;
		}

		$data = $json_val['data'];

		if ($data['received']) {
			return $data['received'];
		}
		return 0;
	}

	public function get_funds_received( $address ) {
		// https://bsv-chain.api.btc.com/v3/address/15urYnyeJe3gwbGJ74wcX89Tz7ZtsFDVew
		// https://bch-chain.api.btc.com/v3/address/15urYnyeJe3gwbGJ74wcX89Tz7ZtsFDVew
		$funds_received = $this->extract_funds_received( @json_decode( trim( @ECP__file_get_contents(
				'https://' . $this->variant_in_use .'-chain.api.btc.com/v3/address/' . $address['btc_address'],
				$this->api_timeout )
		    ), true ));

		if ( ! is_numeric( $funds_received ) ) {
			return false;
		}
		return $funds_received;
	}
}

class BCHSVExplorer extends BlockchainAPI {

	protected function get_supported_variants() {
		return array( 'bsv' );
	}

	private function extract_funds_received( $json_val ) {
		// 404 is not found, since address is generated by us
		// we know it is correct so, assume address has 0 transactions
		if ( ! $json_val ) {
			return false;
		}

		return $json_val['totalReceivedSat'];
	}

	public function get_funds_received( $address ) {
		$funds_received = $this->extract_funds_received( @json_decode( trim( @ECP__file_get_contents(
				'https://bchsvexplorer.com/api/addr/' . $address['btc_address'],
				$this->api_timeout )
		    ), true ));

		if ( ! is_numeric( $funds_received ) ) {
			return false;
		}
		return $funds_received;
	}
}

