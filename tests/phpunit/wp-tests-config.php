<?php
/**
 * WordPress test suite configuration for wp-env.
 *
 * The suite drops and recreates every table with its prefix, so it uses its
 * own prefix and never touches the site's tables. Database credentials come
 * from the environment variables wp-env sets in its containers.
 *
 * @package Galerio\Tests
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress configuration.

define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ? getenv( 'WORDPRESS_DB_NAME' ) : 'wordpress' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ? getenv( 'WORDPRESS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ? getenv( 'WORDPRESS_DB_PASSWORD' ) : 'password' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ? getenv( 'WORDPRESS_DB_HOST' ) : 'mysql' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'ABSPATH', '/var/www/html/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Galerio Tests' );
define( 'WP_PHP_BINARY', 'php' );
