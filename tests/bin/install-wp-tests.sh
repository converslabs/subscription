#!/usr/bin/env bash
#
# Bootstrap WordPress PHPUnit test library for WPSubscription compatibility tests.

set -euo pipefail

DB_NAME=${1:-wordpress_test}
DB_USER=${2:-root}
DB_PASS=${3:-''}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}
WP_TESTS_DIR=${6:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${7:-/tmp/wordpress}

download() {
	local url="$1"
	local dest="$2"

	if command -v curl >/dev/null 2>&1; then
		curl -sSL "$url" -o "$dest"
	elif command -v wget >/dev/null 2>&1; then
		wget -q "$url" -O "$dest"
	else
		echo "Error: curl or wget is required to download files." >&2
		exit 1
	fi

	if [ ! -s "$dest" ]; then
		echo "Error: failed to download $url" >&2
		return 1
	fi

	return 0
}

install_wp() {
	rm -rf "$WP_CORE_DIR"
	mkdir -p "$WP_CORE_DIR"

	local archive
	archive=$(mktemp "/tmp/wordpress-${WP_VERSION}.XXXXXX")

	if [ "$WP_VERSION" = "nightly" ] || [ "$WP_VERSION" = "trunk" ]; then
		local wp_url="https://wordpress.org/nightly-builds/wordpress-latest.zip"
		archive="${archive}.zip"
		if ! download "$wp_url" "$archive"; then
			echo "Error: unable to download WordPress nightly build." >&2
			exit 1
		fi
		local extract_dir
		extract_dir=$(mktemp -d)
		unzip -q "$archive" -d "$extract_dir"
		cp -R "$extract_dir/wordpress/." "$WP_CORE_DIR"
		rm -rf "$extract_dir"
	else
		local wp_url="https://wordpress.org/latest.tar.gz"
		if [[ "$WP_VERSION" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
			wp_url="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
		fi
		archive="${archive}.tar.gz"
		if ! download "$wp_url" "$archive"; then
			echo "Error: unable to download WordPress from $wp_url." >&2
			exit 1
		fi
		local extract_dir
		extract_dir=$(mktemp -d)
		tar -xzf "$archive" -C "$extract_dir"
		cp -R "$extract_dir/wordpress/." "$WP_CORE_DIR"
		rm -rf "$extract_dir"
	fi

	rm -f "$archive"
}

install_test_suite() {
	rm -rf "$WP_TESTS_DIR"
	mkdir -p "$WP_TESTS_DIR"

	local archive
	archive=$(mktemp "/tmp/wordpress-tests-${WP_VERSION}.XXXXXX.tar.gz")

	local tests_url="https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz"
	if [[ "$WP_VERSION" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
		tests_url="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_VERSION}.tar.gz"
	fi

	if ! download "$tests_url" "$archive"; then
		echo "Error: unable to download WordPress tests from $tests_url." >&2
		exit 1
	fi

	local extract_dir
	extract_dir=$(mktemp -d)
	tar -xzf "$archive" -C "$extract_dir"

	local source_dir
	source_dir=$(find "$extract_dir" -maxdepth 1 -type d -name "wordpress-develop*" | head -n 1)

	if [ -z "$source_dir" ]; then
		echo "Error: could not locate extracted WordPress develop directory." >&2
		rm -rf "$extract_dir" "$archive"
		exit 1
	fi

	cp -R "$source_dir/tests/phpunit/." "$WP_TESTS_DIR"

	rm -rf "$extract_dir" "$archive"

	cat <<EOF > "$WP_TESTS_DIR/wp-tests-config.php"
<?php
define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASS' );
define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_DEBUG', true );
define( 'ABSPATH', '$WP_CORE_DIR/' );
\$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'WP Subscription Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
EOF
}

install_db() {
	local args=("--user=$DB_USER" "--password=$DB_PASS")

	if [[ "$DB_HOST" == *:* ]]; then
		local host_part="${DB_HOST%%:*}"
		local socket_part="${DB_HOST##*:}"
		if [[ "$DB_HOST" == *":/"* ]]; then
			args+=("--socket=$socket_part")
		elif [[ "$DB_HOST" == *":"* ]]; then
			args+=("--host=$host_part" "--port=$socket_part")
		fi
	else
		args+=("--host=$DB_HOST")
	fi

	mysqladmin create "$DB_NAME" "${args[@]}" >/dev/null 2>&1 || true
}

install_wp
install_test_suite
install_db

