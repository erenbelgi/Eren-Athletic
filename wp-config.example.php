<?php
/**
 * Yerel geliÅŸtirme iÃ§in GÄ°ZLÄ° BÄ°LGÄ° Ä°Ã‡ERMEYEN Ã–RNEK.
 * KopyalayÄ±p wp-config.php olarak kullanmadan Ã¶nce tÃ¼m yer tutucularÄ± deÄŸiÅŸtir.
 * Bu dosyaya gerÃ§ek DB parolasÄ± veya gerÃ§ek anahtar koyup Git'e gÃ¶nderme.
 */
define( 'DB_NAME', 'your_local_database_name' );
define( 'DB_USER', 'your_local_database_user' );
define( 'DB_PASSWORD', 'replace_this_locally' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'generate-a-unique-local-secret' );
define( 'SECURE_AUTH_KEY',  'generate-a-unique-local-secret' );
define( 'LOGGED_IN_KEY',    'generate-a-unique-local-secret' );
define( 'NONCE_KEY',        'generate-a-unique-local-secret' );
define( 'AUTH_SALT',        'generate-a-unique-local-secret' );
define( 'SECURE_AUTH_SALT', 'generate-a-unique-local-secret' );
define( 'LOGGED_IN_SALT',   'generate-a-unique-local-secret' );
define( 'NONCE_SALT',       'generate-a-unique-local-secret' );

$table_prefix = 'wp_';
define( 'WP_DEBUG', false );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
