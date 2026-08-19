<?php
/**
 * Security policy for provider HTTP destinations and credentials.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'cbia_provider_allowed_hosts' ) ) {
	function cbia_provider_allowed_hosts(): array {
		return array(
			'openai'   => array( 'api.openai.com' ),
			'google'   => array( 'generativelanguage.googleapis.com' ),
			'deepseek' => array( 'api.deepseek.com' ),
			'anthropic'=> array( 'api.anthropic.com' ),
		);
	}
}

if ( ! function_exists( 'cbia_provider_security_key' ) ) {
	function cbia_provider_security_key( $provider ): string {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $provider ) );
	}
}

if ( ! function_exists( 'cbia_provider_default_base_url' ) ) {
	function cbia_provider_default_base_url( $provider ): string {
		$provider = cbia_provider_security_key( $provider );
		$hosts = cbia_provider_allowed_hosts();
		return isset( $hosts[ $provider ][0] ) ? 'https://' . $hosts[ $provider ][0] : '';
	}
}

if ( ! function_exists( 'cbia_provider_ip_is_public' ) ) {
	function cbia_provider_ip_is_public( $ip ): bool {
		$ip = trim( (string) $ip, " \t\n\r\0\x0B[]" );
		if ( '' === $ip || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) return false;
		return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}
}

if ( ! function_exists( 'cbia_provider_resolve_host_ips' ) ) {
	/**
	 * Resolve every A/AAAA address. The resolver argument exists for deterministic tests.
	 */
	function cbia_provider_resolve_host_ips( $host, $resolver = null ): array {
		$host = strtolower( trim( (string) $host ) );
		if ( false !== filter_var( trim( $host, '[]' ), FILTER_VALIDATE_IP ) ) {
			return array( trim( $host, '[]' ) );
		}

		$records = array();
		if ( null !== $resolver ) {
			if ( ! is_callable( $resolver ) ) return array();
			try {
				$records = call_user_func( $resolver, $host );
			} catch ( Throwable $error ) {
				return array();
			}
		} elseif ( function_exists( 'dns_get_record' ) ) {
			$type = defined( 'DNS_A' ) ? DNS_A : 1;
			if ( defined( 'DNS_AAAA' ) ) $type |= DNS_AAAA;
			$resolved = @dns_get_record( $host, $type );
			$records = is_array( $resolved ) ? $resolved : array();
		}

		$ips = array();
		foreach ( is_array( $records ) ? $records : array() as $record ) {
			if ( is_string( $record ) ) {
				$ips[] = $record;
			} elseif ( is_array( $record ) ) {
				if ( ! empty( $record['ip'] ) ) $ips[] = (string) $record['ip'];
				if ( ! empty( $record['ipv6'] ) ) $ips[] = (string) $record['ipv6'];
			}
		}

		if ( empty( $ips ) && null === $resolver && function_exists( 'gethostbynamel' ) ) {
			$ipv4 = @gethostbynamel( $host );
			if ( is_array( $ipv4 ) ) $ips = array_merge( $ips, $ipv4 );
		}

		return array_values( array_unique( array_filter( array_map( 'trim', $ips ) ) ) );
	}
}

if ( ! function_exists( 'cbia_validate_provider_url' ) ) {
	function cbia_validate_provider_url( $provider, $url, $base_only = false, $resolver = null, $resolve_dns = true ): array {
		$provider = cbia_provider_security_key( $provider );
		$url = is_string( $url ) ? trim( $url ) : '';
		$invalid = static function ( $code ) {
			return array( 'valid' => false, 'code' => (string) $code, 'url' => '' );
		};

		$allowed = cbia_provider_allowed_hosts();
		if ( '' === $provider || empty( $allowed[ $provider ] ) ) return $invalid( 'invalid_provider' );
		if ( '' === $url || preg_match( '/[\x00-\x20\x7f]/', $url ) ) return $invalid( 'invalid_url' );
		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) return $invalid( 'invalid_url' );

		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) ) return $invalid( 'invalid_url' );
		if ( 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) return $invalid( 'https_required' );
		if ( array_key_exists( 'user', $parts ) || array_key_exists( 'pass', $parts ) ) return $invalid( 'userinfo_forbidden' );
		if ( empty( $parts['host'] ) ) return $invalid( 'missing_host' );
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) return $invalid( 'port_forbidden' );
		if ( ! empty( $parts['fragment'] ) ) return $invalid( 'fragment_forbidden' );
		if ( $base_only ) {
			$path = (string) ( $parts['path'] ?? '' );
			if ( '' !== $path && '/' !== $path ) return $invalid( 'base_path_forbidden' );
			if ( array_key_exists( 'query', $parts ) ) return $invalid( 'base_query_forbidden' );
		}

		$host = strtolower( (string) $parts['host'] );
		if ( ! in_array( $host, $allowed[ $provider ], true ) ) return $invalid( 'host_forbidden' );

		if ( $resolve_dns ) {
			$ips = cbia_provider_resolve_host_ips( $host, $resolver );
			if ( empty( $ips ) ) return $invalid( 'dns_unresolved' );
			foreach ( $ips as $ip ) {
				if ( ! cbia_provider_ip_is_public( $ip ) ) return $invalid( 'non_public_ip' );
			}
		}

		$normalized = $base_only ? 'https://' . $host : $url;
		return array( 'valid' => true, 'code' => 'allowed', 'url' => $normalized );
	}
}

if ( ! function_exists( 'cbia_validate_provider_base_url' ) ) {
	function cbia_validate_provider_base_url( $provider, $url, $resolver = null, $resolve_dns = true ): array {
		return cbia_validate_provider_url( $provider, $url, true, $resolver, $resolve_dns );
	}
}

if ( ! function_exists( 'cbia_provider_request_url_is_allowed' ) ) {
	function cbia_provider_request_url_is_allowed( $provider, $url, $resolver = null ): array {
		return cbia_validate_provider_url( $provider, $url, false, $resolver, true );
	}
}

if ( ! function_exists( 'cbia_provider_security_wp_error' ) ) {
	function cbia_provider_security_wp_error( $code ) {
		$message = 'The provider destination is not allowed. Review the provider configuration.';
		if ( function_exists( '__' ) ) {
			$message = __( 'The provider destination is not allowed. Review the provider configuration.', 'cbiastudio-blogflow-ai' );
		}
		return new WP_Error( 'cbia_provider_destination_blocked', $message, array( 'reason' => (string) $code ) );
	}
}

if ( ! function_exists( 'cbia_provider_credential_headers' ) ) {
	function cbia_provider_credential_headers( $provider, $api_key ): array {
		$provider = cbia_provider_security_key( $provider );
		$api_key = (string) $api_key;
		if ( 'openai' === $provider ) {
			return function_exists( 'cbia_http_headers_openai' )
				? cbia_http_headers_openai( $api_key )
				: array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key );
		}
		if ( 'google' === $provider ) return array( 'x-goog-api-key' => $api_key );
		if ( 'deepseek' === $provider ) return array( 'Authorization' => 'Bearer ' . $api_key );
		if ( 'anthropic' === $provider ) {
			return function_exists( 'cbia_anthropic_headers' )
				? cbia_anthropic_headers( $api_key )
				: array( 'Content-Type' => 'application/json', 'x-api-key' => $api_key, 'anthropic-version' => '2023-06-01' );
		}
		return array();
	}
}

if ( ! function_exists( 'cbia_provider_safe_remote_request' ) ) {
	/**
	 * Validate first, attach the credential second, then invoke the safe transport.
	 */
	function cbia_provider_safe_remote_request( $provider, $url, $args, $api_key, $resolver = null, $transport = null ) {
		$validation = cbia_provider_request_url_is_allowed( $provider, $url, $resolver );
		if ( empty( $validation['valid'] ) ) return cbia_provider_security_wp_error( $validation['code'] ?? 'invalid_url' );
		if ( '' === (string) $api_key ) return cbia_provider_security_wp_error( 'missing_credential' );

		$args = is_array( $args ) ? $args : array();
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$sensitive = array( 'authorization', 'x-goog-api-key', 'x-api-key', 'api-key' );
		foreach ( array_keys( $headers ) as $name ) {
			if ( in_array( strtolower( (string) $name ), $sensitive, true ) ) unset( $headers[ $name ] );
		}
		$args['headers'] = array_merge( $headers, cbia_provider_credential_headers( $provider, $api_key ) );
		$args['sslverify'] = true;
		$args['redirection'] = 0;
		$args['reject_unsafe_urls'] = true;

		if ( null !== $transport ) {
			if ( ! is_callable( $transport ) ) return cbia_provider_security_wp_error( 'invalid_transport' );
			return call_user_func( $transport, (string) $validation['url'], $args );
		}
		if ( ! function_exists( 'wp_safe_remote_request' ) ) return cbia_provider_security_wp_error( 'safe_transport_unavailable' );
		return wp_safe_remote_request( (string) $validation['url'], $args );
	}
}

if ( ! function_exists( 'cbia_provider_safe_remote_get' ) ) {
	function cbia_provider_safe_remote_get( $provider, $url, $args, $api_key, $resolver = null, $transport = null ) {
		$args = is_array( $args ) ? $args : array();
		$args['method'] = 'GET';
		return cbia_provider_safe_remote_request( $provider, $url, $args, $api_key, $resolver, $transport );
	}
}

if ( ! function_exists( 'cbia_provider_safe_remote_post' ) ) {
	function cbia_provider_safe_remote_post( $provider, $url, $args, $api_key, $resolver = null, $transport = null ) {
		$args = is_array( $args ) ? $args : array();
		$args['method'] = 'POST';
		return cbia_provider_safe_remote_request( $provider, $url, $args, $api_key, $resolver, $transport );
	}
}
