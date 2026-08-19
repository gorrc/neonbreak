<?php

defined( 'ABSPATH' ) || exit;

final class NeonLib_Admin_Api_Client {
	private string $base_url;
	private string $token;

	public function __construct() {
		$this->base_url = defined( 'NEONLIB_API_URL' ) ? untrailingslashit( (string) NEONLIB_API_URL ) : '';
		$this->token    = defined( 'NEONLIB_ADMIN_API_TOKEN' ) ? (string) NEONLIB_ADMIN_API_TOKEN : '';
	}

	public function is_configured(): bool {
		return '' !== $this->base_url && (bool) preg_match( '/^nlat_[A-Za-z0-9_-]{43}$/', $this->token );
	}

	public function accounts( array $filters = array() ): array|WP_Error {
		return $this->request( 'GET', '/api/v1/admin/accounts', null, $filters );
	}

	public function account( string $account_id ): array|WP_Error {
		return $this->request( 'GET', '/api/v1/admin/accounts/' . rawurlencode( $account_id ) );
	}

	public function update_account( string $account_id, string $status ): array|WP_Error {
		return $this->request( 'PATCH', '/api/v1/admin/accounts/' . rawurlencode( $account_id ), array( 'status' => $status ) );
	}

	public function subscriptions( array $filters = array() ): array|WP_Error {
		return $this->request( 'GET', '/api/v1/admin/subscriptions', null, $filters );
	}

	public function subscription( string $package_id ): array|WP_Error {
		return $this->request( 'GET', '/api/v1/admin/subscriptions/' . rawurlencode( $package_id ) );
	}

	public function update_subscription( string $package_id, array $changes ): array|WP_Error {
		return $this->request( 'PATCH', '/api/v1/admin/subscriptions/' . rawurlencode( $package_id ), $changes );
	}

	public function health(): array|WP_Error {
		if ( '' === $this->base_url ) {
			return new WP_Error( 'neonlib_admin_not_configured', __( 'NeonLib API URL nije konfiguriran.', 'neonlib-admin' ) );
		}
		$response = wp_remote_get( $this->base_url . '/api/v1/health', array( 'timeout' => 10, 'redirection' => 0 ) );
		return $this->decode_response( $response );
	}

	private function request( string $method, string $path, ?array $payload = null, array $query = array() ): array|WP_Error {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'neonlib_admin_not_configured', __( 'NeonLib admin API token nije konfiguriran.', 'neonlib-admin' ) );
		}
		$url = $this->base_url . $path;
		$query = array_filter( $query, static fn( $value ): bool => '' !== (string) $value );
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		$body = null === $payload ? '' : wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$response = wp_remote_request(
			$url,
			array(
				'method'      => strtoupper( $method ),
				'headers'     => array( 'Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->token ),
				'body'        => $body,
				'timeout'     => 20,
				'redirection' => 0,
			)
		);
		return $this->decode_response( $response );
	}

	private function decode_response( array|WP_Error $response ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status  = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'neonlib_admin_invalid_response', __( 'NeonLib API vratio je neispravan odgovor.', 'neonlib-admin' ), array( 'status' => $status ) );
		}
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				sanitize_key( (string) ( $decoded['error']['code'] ?? 'neonlib_admin_api_error' ) ),
				(string) ( $decoded['error']['message'] ?? __( 'NeonLib admin zahtjev nije uspio.', 'neonlib-admin' ) ),
				array( 'status' => $status, 'request_id' => $decoded['requestId'] ?? null )
			);
		}
		return $decoded;
	}
}
