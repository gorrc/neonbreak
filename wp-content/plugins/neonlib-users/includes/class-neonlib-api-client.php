<?php

defined( 'ABSPATH' ) || exit;

final class NeonLib_Api_Client {
	private string $base_url;
	private string $client_id;
	private string $client_secret;
	private string $site_id;

	public function __construct() {
		$this->base_url     = defined( 'NEONLIB_API_URL' ) ? untrailingslashit( (string) NEONLIB_API_URL ) : '';
		$this->client_id    = defined( 'NEONLIB_API_CLIENT_ID' ) ? (string) NEONLIB_API_CLIENT_ID : '';
		$this->client_secret = defined( 'NEONLIB_API_CLIENT_SECRET' ) ? (string) NEONLIB_API_CLIENT_SECRET : '';
		$this->site_id      = defined( 'NEONLIB_API_SITE_ID' ) ? (string) NEONLIB_API_SITE_ID : '';
	}

	public function is_configured(): bool {
		return '' !== $this->base_url && '' !== $this->client_id && '' !== $this->client_secret && '' !== $this->site_id;
	}

	public function link_account( int $user_id ): array|WP_Error {
		return $this->request(
			'POST',
			'/api/v1/accounts/link',
			array( 'wordpress_site_id' => $this->site_id, 'wordpress_user_id' => (string) $user_id, 'email_verified' => true )
		);
	}

	public function get_account_link( int $user_id ): array|WP_Error {
		return $this->request( 'GET', '/api/v1/accounts/link', null, $user_id );
	}

	public function unlink_account( int $user_id ): true|WP_Error {
		$result = $this->request( 'DELETE', '/api/v1/accounts/link', null, $user_id );
		return is_wp_error( $result ) ? $result : true;
	}

	public function subscriptions( int $user_id ): array|WP_Error {
		$result = $this->request( 'GET', '/api/v1/account/subscriptions', null, $user_id );
		return is_wp_error( $result ) ? $result : (array) ( $result['data'] ?? array() );
	}

	public function publisher( int $user_id ): array|WP_Error {
		$result = $this->request( 'GET', '/api/v1/account/publisher', null, $user_id );
		return is_wp_error( $result ) ? $result : (array) ( $result['data'] ?? array() );
	}

	public function update_publisher( int $user_id, string $display_name ): array|WP_Error {
		$result = $this->request( 'PUT', '/api/v1/account/publisher', array( 'display_name' => $display_name ), $user_id );
		return is_wp_error( $result ) ? $result : (array) ( $result['data'] ?? array() );
	}

	public function create_subscription( int $user_id, array $subscription ): array|WP_Error {
		return $this->request( 'POST', '/api/v1/account/subscriptions', $subscription, $user_id );
	}

	public function update_subscription( int $user_id, string $package_id, array $changes ): array|WP_Error {
		return $this->request( 'PATCH', '/api/v1/account/subscriptions/' . rawurlencode( $package_id ), $changes, $user_id );
	}

	public function delete_subscription( int $user_id, string $package_id ): true|WP_Error {
		$result = $this->request( 'DELETE', '/api/v1/account/subscriptions/' . rawurlencode( $package_id ), null, $user_id );
		return is_wp_error( $result ) ? $result : true;
	}

	public function publish_version( int $user_id, string $package_id, array $documents ): array|WP_Error {
		return $this->request(
			'POST',
			'/api/v1/account/subscriptions/' . rawurlencode( $package_id ) . '/versions',
			array( 'documents' => $documents ),
			$user_id
		);
	}

	public function versions( int $user_id, string $package_id ): array|WP_Error {
		$result = $this->request( 'GET', '/api/v1/account/subscriptions/' . rawurlencode( $package_id ) . '/versions', null, $user_id );
		return is_wp_error( $result ) ? $result : (array) ( $result['data'] ?? array() );
	}

	public function version( int $user_id, string $package_id, int $version ): array|WP_Error {
		$result = $this->request( 'GET', '/api/v1/account/subscriptions/' . rawurlencode( $package_id ) . '/versions/' . $version, null, $user_id );
		return is_wp_error( $result ) ? $result : (array) ( $result['data'] ?? array() );
	}

	private function request( string $method, string $path, ?array $payload = null, ?int $subject = null ): array|WP_Error {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'neonlib_api_not_configured', __( 'NeonLib API nije konfiguriran.', 'neonlib-users' ) );
		}

		$body      = null === $payload ? '' : wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$timestamp = (string) time();
		$nonce     = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$canonical = strtoupper( $method ) . "\n{$path}\n{$timestamp}\n{$nonce}\n" . hash( 'sha256', $body );
		if ( null !== $subject ) {
			$canonical .= "\n" . $subject;
		}

		$headers = array(
			'Accept'                   => 'application/json',
			'Content-Type'             => 'application/json',
			'X-NeonLib-Client-Id'      => $this->client_id,
			'X-NeonLib-Timestamp'      => $timestamp,
			'X-NeonLib-Nonce'          => $nonce,
			'X-NeonLib-Signature'      => 'v1=' . hash_hmac( 'sha256', $canonical, $this->client_secret ),
		);
		if ( null !== $subject ) {
			$headers['X-NeonLib-Subject'] = (string) $subject;
		}

		$response = wp_remote_request(
			$this->base_url . $path,
			array( 'method' => strtoupper( $method ), 'headers' => $headers, 'body' => $body, 'timeout' => 20, 'redirection' => 0 )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		if ( 204 === $status ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'neonlib_api_invalid_response', __( 'NeonLib API vratio je neispravan odgovor.', 'neonlib-users' ), array( 'status' => $status ) );
		}
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				sanitize_key( (string) ( $decoded['error']['code'] ?? 'neonlib_api_error' ) ),
				(string) ( $decoded['error']['message'] ?? __( 'NeonLib API zahtjev nije uspio.', 'neonlib-users' ) ),
				array( 'status' => $status, 'request_id' => $decoded['requestId'] ?? null )
			);
		}

		return $decoded;
	}
}
