<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'dbirqcci2ahgq1' );

/** Database username */
define( 'DB_USER', 'uelr6poexj1ox' );

/** Database password */
define( 'DB_PASSWORD', 'cahlrv2xqr0w' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '>K2{t{-$q*kL6Xx!Q@/Gs7 l$9t2W@^Uscz2f;: ]<~(o3Z+{O0!hbT&Nw.Ny>=u' );
define( 'SECURE_AUTH_KEY',   'QX>Uh@UFr:$k@_Lmc^5.T+0@HtshKBBke77r15[F+`<pU#),+sd+F<Y:.ub1?Z!=' );
define( 'LOGGED_IN_KEY',     'JKMF_+ZDQ{f!x$XGlBy.0W2&JE+lr([N+!.rVcJ1m(yuJ qg@Cr@[;{m)8naRnj]' );
define( 'NONCE_KEY',         '3v421]Iyhztf9msct?]mxvoneA$Sdd,EoiJfYxY5D c*c+YEwGz]YTjir`RaPXXb' );
define( 'AUTH_SALT',         '~,qYI?sweC=yY.U78)_|?wc,;bgT^HKxb}99u9=-W6re~wL!&%79Yy@bI;1`DfvR' );
define( 'SECURE_AUTH_SALT',  '6GI2vIl-h9Hp-mkB,X8LOVd2=(~zoD[%#&<:D[Ub<F`%/e5t(]2{*RkezvT]pna4' );
define( 'LOGGED_IN_SALT',    '&~%tNEH-9>YW&j^T]|]]KQ2)P}NqxfcN_p]Km?^:_*fRqn~C`xrvzzgV[o]-iO|[' );
define( 'NONCE_SALT',        'O<iIe:|K`r7~ D^z&mmm`K}@/][z78_zWcn!n8Z`2;8b:7#f08mUlhytH;%qT7u$' );
define( 'WP_CACHE_KEY_SALT', '6Gt;=x34*LM]OTu;;`/7#q-:UdL B>haqDvV,O=AUz$74lo-}JXa5LuF<a&s_=VL' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wow_';

// Debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);

/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
@include_once('/var/lib/sec/wp-settings-pre.php'); // Added by SiteGround WordPress management system
require_once ABSPATH . 'wp-settings.php';
@include_once('/var/lib/sec/wp-settings.php'); // Added by SiteGround WordPress management system
