<?php
/**
 * Shared outbound request policy for Cloud Addon transports.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Outbound_Policy' ) ) {
	/**
	 * Validates Cloud targets and applies bounded WordPress HTTP defaults.
	 */
	final class Npcink_Cloud_Outbound_Policy {
		public const MAX_JSON_RESPONSE_BYTES = 1048576;
		public const MAX_AUTH_RESPONSE_BYTES = 65536;
		public const MAX_RAW_RESPONSE_BYTES  = 26214400;

		/**
		 * Normalizes a configured Cloud base URL without performing DNS I/O.
		 *
		 * @param string $base_url Raw base URL.
		 * @return string
		 */
		public static function normalize_base_url( string $base_url ): string {
			$base_url = untrailingslashit( esc_url_raw( trim( $base_url ) ) );
			if ( '' === $base_url ) {
				return '';
			}

			$parts = wp_parse_url( $base_url );
			if ( ! is_array( $parts ) ) {
				return '';
			}

			$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
			$host   = self::normalize_host( (string) ( $parts['host'] ?? '' ) );
			$path   = (string) ( $parts['path'] ?? '' );
			if (
				'' === $host
				|| isset( $parts['user'] )
				|| isset( $parts['pass'] )
				|| isset( $parts['query'] )
				|| isset( $parts['fragment'] )
				|| ( '' !== $path && '/' !== $path )
			) {
				return '';
			}
			if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! self::is_loopback_host( $host ) && ! self::is_public_ip( $host ) ) {
				return '';
			}

			if ( 'https' === $scheme && ! self::is_loopback_host( $host ) ) {
				return $base_url;
			}

			if ( in_array( $scheme, array( 'http', 'https' ), true ) && self::is_loopback_host( $host ) && self::local_requests_allowed() ) {
				return $base_url;
			}

			return '';
		}

		/**
		 * Returns whether exact loopback requests are explicitly allowed.
		 *
		 * @return bool
		 */
		public static function local_requests_allowed(): bool {
			if ( defined( 'NPCINK_CLOUD_ADDON_ALLOW_LOCAL_REQUESTS' ) && true === NPCINK_CLOUD_ADDON_ALLOW_LOCAL_REQUESTS ) {
				return true;
			}

			return function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type();
		}

		/**
		 * Dispatches a bounded JSON request.
		 *
		 * @param string              $url URL.
		 * @param array<string,mixed> $args WordPress HTTP arguments.
		 * @param int                 $max_bytes Maximum response bytes.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function request_json( string $url, array $args, int $max_bytes = self::MAX_JSON_RESPONSE_BYTES ) {
			$response = self::request( $url, $args, $max_bytes );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$content_type = strtolower( trim( explode( ';', self::response_header( $response, 'content-type' ), 2 )[0] ) );
			if ( 'application/json' !== $content_type && 1 !== preg_match( '#^application/[a-z0-9.+_-]+\+json$#', $content_type ) ) {
				return new WP_Error(
					'cloud_outbound_response_type_invalid',
					__( 'Cloud returned an unexpected response type.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return $response;
		}

		/**
		 * Dispatches a bounded raw-byte request.
		 *
		 * @param string              $url URL.
		 * @param array<string,mixed> $args WordPress HTTP arguments.
		 * @param int                 $max_bytes Maximum response bytes.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function request_raw( string $url, array $args, int $max_bytes = self::MAX_RAW_RESPONSE_BYTES ) {
			return self::request( $url, $args, $max_bytes );
		}

		/**
		 * Applies URL and response bounds before and after dispatch.
		 *
		 * @param string              $url URL.
		 * @param array<string,mixed> $args WordPress HTTP arguments.
		 * @param int                 $max_bytes Maximum response bytes.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function request( string $url, array $args, int $max_bytes ) {
			$target = self::validate_request_target( $url );
			if ( is_wp_error( $target ) ) {
				return $target;
			}

			$max_bytes = max( 1, $max_bytes );
			$is_local  = ! empty( $target['local'] );
			$args['redirection']         = 0;
			$args['sslverify']           = true;
			$args['reject_unsafe_urls']  = ! $is_local;
			// Read one byte past the accepted limit so WordPress transports cannot
			// silently turn an oversized response into an apparently valid one.
			$args['limit_response_size'] = $max_bytes + 1;

			$response = $is_local
				? wp_remote_request( $url, $args )
				: wp_safe_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body           = wp_remote_retrieve_body( $response );
			$content_length = absint( self::response_header( $response, 'content-length' ) );
			if ( $content_length > $max_bytes || strlen( is_string( $body ) ? $body : '' ) > $max_bytes ) {
				return new WP_Error(
					'cloud_outbound_response_too_large',
					__( 'Cloud response exceeds the local size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			return $response;
		}

		/**
		 * Validates one outbound URL and its resolved target.
		 *
		 * @param string $url URL.
		 * @return array{local:bool}|WP_Error
		 */
		private static function validate_request_target( string $url ) {
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) ) {
				return self::target_error();
			}

			$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
			$host   = self::normalize_host( (string) ( $parts['host'] ?? '' ) );
			if ( '' === $host || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) {
				return self::target_error();
			}

			if ( self::is_loopback_host( $host ) ) {
				if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! self::local_requests_allowed() ) {
					return self::target_error();
				}

				return array( 'local' => true );
			}

			if ( 'https' !== $scheme || ! self::host_resolves_publicly( $host ) ) {
				return self::target_error();
			}

			return array( 'local' => false );
		}

		/**
		 * Returns whether a host is a public IP or resolves exclusively to public IPs.
		 *
		 * Residual risk (accepted, see docs/cloud-addon-boundary.md): this check
		 * and the later HTTP dispatch resolve the host independently, so DNS
		 * rebinding between the two is only mitigated by HTTPS-only transport,
		 * disabled redirects, and wp_safe_remote_request — not fully closed.
		 *
		 * @param string $host Host.
		 * @return bool
		 */
		private static function host_resolves_publicly( string $host ): bool {
			if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
				return self::is_public_ip( $host );
			}
			if ( 1 !== preg_match( '/^[a-z0-9.-]+$/i', $host ) ) {
				return false;
			}

			static $resolved_cache = array();
			if ( ! array_key_exists( $host, $resolved_cache ) ) {
				$ips = array();
				if ( function_exists( 'dns_get_record' ) ) {
					$dns_type = ( defined( 'DNS_A' ) ? DNS_A : 0 ) | ( defined( 'DNS_AAAA' ) ? DNS_AAAA : 0 );
					$records  = $dns_type > 0 ? @dns_get_record( $host, $dns_type ) : false;
					if ( is_array( $records ) ) {
						foreach ( $records as $record ) {
							$ip = (string) ( $record['ip'] ?? $record['ipv6'] ?? '' );
							if ( '' !== $ip ) {
								$ips[] = $ip;
							}
						}
					}
				}
				if ( empty( $ips ) && function_exists( 'gethostbynamel' ) ) {
					$fallback = @gethostbynamel( $host );
					$ips      = is_array( $fallback ) ? $fallback : array();
				}
				$resolved_cache[ $host ] = array_values( array_unique( array_filter( array_map( 'strval', $ips ) ) ) );
			}

			/**
			 * Filters resolved Cloud target IPs in explicit local development only.
			 *
			 * Every filtered value is still required to be a public IP address.
			 *
			 * @param array<int,string> $ips Resolved IP addresses.
			 * @param string            $host Normalized hostname.
			 */
			$filtered = self::local_requests_allowed()
				? apply_filters( 'npcink_cloud_addon_resolved_host_ips', $resolved_cache[ $host ], $host )
				: $resolved_cache[ $host ];
			$ips      = is_array( $filtered ) ? array_values( array_unique( array_filter( array_map( 'strval', $filtered ) ) ) ) : array();
			if ( empty( $ips ) ) {
				return false;
			}

			foreach ( $ips as $ip ) {
				if ( ! self::is_public_ip( $ip ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Returns whether an IP is globally routable.
		 *
		 * @param string $ip IP address.
		 * @return bool
		 */
		private static function is_public_ip( string $ip ): bool {
			if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}

			// PHP's reserved-range filter intentionally does not cover every IANA
			// special-purpose range. Keep a conservative connector-specific deny
			// list so Cloud URLs cannot target documentation, benchmark, carrier
			// NAT, transition, discard, multicast, or other non-global networks.
			$non_global_networks = array(
				'0.0.0.0/8',
				'10.0.0.0/8',
				'100.64.0.0/10',
				'127.0.0.0/8',
				'169.254.0.0/16',
				'172.16.0.0/12',
				'192.0.0.0/24',
				'192.0.2.0/24',
				'192.88.99.0/24',
				'192.168.0.0/16',
				'198.18.0.0/15',
				'198.51.100.0/24',
				'203.0.113.0/24',
				'224.0.0.0/4',
				'240.0.0.0/4',
				'::/128',
				'::1/128',
				'::ffff:0:0/96',
				'64:ff9b::/96',
				'64:ff9b:1::/48',
				'100::/64',
				'2001::/23',
				'2001:db8::/32',
				'2002::/16',
				'fc00::/7',
				'fe80::/10',
				'ff00::/8',
			);

			foreach ( $non_global_networks as $network ) {
				if ( self::ip_is_in_network( $ip, $network ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Returns whether an IP belongs to an IPv4 or IPv6 CIDR network.
		 *
		 * @param string $ip IP address.
		 * @param string $network CIDR network.
		 * @return bool
		 */
		private static function ip_is_in_network( string $ip, string $network ): bool {
			$parts = explode( '/', $network, 2 );
			if ( 2 !== count( $parts ) ) {
				return false;
			}

			$ip_binary      = @inet_pton( $ip );
			$network_binary = @inet_pton( $parts[0] );
			$prefix_length  = ctype_digit( $parts[1] ) ? (int) $parts[1] : -1;
			if (
				false === $ip_binary
				|| false === $network_binary
				|| strlen( $ip_binary ) !== strlen( $network_binary )
				|| $prefix_length < 0
				|| $prefix_length > ( strlen( $ip_binary ) * 8 )
			) {
				return false;
			}

			$whole_bytes = intdiv( $prefix_length, 8 );
			if ( $whole_bytes > 0 && substr( $ip_binary, 0, $whole_bytes ) !== substr( $network_binary, 0, $whole_bytes ) ) {
				return false;
			}

			$remaining_bits = $prefix_length % 8;
			if ( 0 === $remaining_bits ) {
				return true;
			}

			$mask = ( 0xff << ( 8 - $remaining_bits ) ) & 0xff;

			return ( ord( $ip_binary[ $whole_bytes ] ) & $mask ) === ( ord( $network_binary[ $whole_bytes ] ) & $mask );
		}

		/**
		 * Normalizes a parsed host.
		 *
		 * @param string $host Host.
		 * @return string
		 */
		private static function normalize_host( string $host ): string {
			$host = rtrim( trim( $host ), '.' );
			if ( str_starts_with( $host, '[' ) && str_ends_with( $host, ']' ) ) {
				$host = substr( $host, 1, -1 );
			}

			return strtolower( $host );
		}

		/**
		 * Returns whether the host is an exact loopback name or address.
		 *
		 * @param string $host Host.
		 * @return bool
		 */
		private static function is_loopback_host( string $host ): bool {
			return in_array( self::normalize_host( $host ), array( 'localhost', '127.0.0.1', '::1' ), true );
		}

		/**
		 * Reads one response header from WordPress or a pure-PHP test response.
		 *
		 * @param array<string,mixed> $response Response.
		 * @param string              $name Header name.
		 * @return string
		 */
		private static function response_header( array $response, string $name ): string {
			if ( function_exists( 'wp_remote_retrieve_header' ) ) {
				$value = wp_remote_retrieve_header( $response, $name );
				if ( is_scalar( $value ) ) {
					return trim( (string) $value );
				}
			}

			$headers = is_array( $response['headers'] ?? null ) ? $response['headers'] : array();
			foreach ( $headers as $key => $value ) {
				if ( strtolower( (string) $key ) === strtolower( $name ) && is_scalar( $value ) ) {
					return trim( (string) $value );
				}
			}

			return '';
		}

		/**
		 * Returns a non-secret target validation error.
		 *
		 * @return WP_Error
		 */
		private static function target_error(): WP_Error {
			return new WP_Error(
				'cloud_outbound_target_not_allowed',
				__( 'Cloud request target is not allowed by the local connector policy.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}
	}
}
