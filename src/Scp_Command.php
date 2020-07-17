<?php

use WP_CLI\Configurator;
use WP_CLI\Utils;

class Scp_Command extends WP_CLI_Command {

	/**
	 * @var string $alias_regex Regex pattern used to define an alias
	 */
	private static $alias_regex;

	/**
	 * OpenSSH secure file copy
	 *
	 * 'wp scp' copies files between hosts on a network.
	 *
	 * The <source> and <target> may be specified as a local pathname or a remote
	 * host with optional path in the form @alias:[path]. Local file names can be
	 * made explicit using absolute or relative pathnames to avoid 'wp scp'
	 * treating file names containing ‘:’ as host specifiers.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Local pathname or a remote host with optional path.
	 *
	 * <target>
	 * : Local pathname or a remote host with optional path.
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		self::$alias_regex = preg_replace( '/\$$/', '', Configurator::ALIAS_REGEX );

		foreach ( $args as &$arg ) {
			$arg = $this->maybe_convert_alias_to_host( $arg );
		}

		list( $source, $target ) = $args;

		passthru( "scp $source $target", $exit_code );
		if ( 255 === $exit_code ) {
			WP_CLI::error( 'Cannot copy over SCP using provided configuration.', 255 );
		} else {
			exit( $exit_code );
		}
	}

	/**
	 * Maybe convert source or target started by alias
	 *
	 * @param  string $source_or_target Source or target.
	 * @return string
	 */
	private function maybe_convert_alias_to_host( $source_or_target ) {
		if ( preg_match( '/^@[^$]+$/', $source_or_target ) ) {
			$configurator = \WP_CLI::get_configurator();
			$aliases      = $configurator->get_aliases();
			preg_match( '#' . self::$alias_regex . '#', $source_or_target, $output );
			list( $alias ) = $output;
			if ( array_key_exists( $alias, $aliases ) ) {
				$bits = $aliases[ $alias ];
				$path = ltrim( preg_replace( '#^' . $alias . '#', '', $source_or_target ), ':' );
				$source_or_target = $this->merge_ssh_bits_and_path( $bits, $path );
			} else {
				WP_CLI::error( "No alias found with key '$alias'." );
			}
		} else {
			$source_or_target = escapeshellarg( $source_or_target );
		}
		return $source_or_target;
	}

	/**
	 * Merge host, port, and path into SSH.
	 *
	 * @param array  $bits Parsed connection string.
	 * @param array  $path Remote path.
	 * @return string
	 */
	private function merge_ssh_bits_and_path( $bits, $path ) {
		$escaped_formated = '';

		// Set default values.
		foreach ( array( 'scheme', 'user', 'host', 'port', 'path', 'key' ) as $bit ) {
			if ( ! isset( $bits[ $bit ] ) ) {
				$bits[ $bit ] = null;
			}

			WP_CLI::debug( 'SSH ' . $bit . ': ' . $bits[ $bit ], 'bootstrap' );
		}

		// Default scheme is SSH.
		if ( 'ssh' === $bits['scheme'] || null === $bits['scheme'] ) {
			if ( $bits['ssh'] ) {
				$bits['host'] = $bits['ssh'];
			} elseif ( $bits['user'] ) {
				$bits['host'] = $bits['user'] . '@' . $bits['host'];
			}

			if ( ! $bits['port'] && preg_match( '/:\d+$/', $bits['host'] ) ) {
				preg_match( '/\d+$/', $bits['host'], $output );
				$bits['port'] = current( $output );
				$bits['host'] = preg_replace( '/:\d+$/', '', $bits['host'] );
			}

			$bits['path'] = rtrim( $bits['path'], '/' );

			$path = ltrim( $path, '/' );

			if ( ! empty( $path ) ) {
				$path = $bits['path'] . '/' . $path;
			} else {
				$path = $bits['path'];
			}

			$escaped_formated = escapeshellarg(
				sprintf(
					'%s%s%s%s',
					$bits['port'] ? 'scp://' : '',
					$bits['host'],
					$bits['port'] ? ':' . (int) $bits['port'] : '',
					$path ? ':' . $path : ''
				)
			);
		}

		return $escaped_formated;
	}
}
