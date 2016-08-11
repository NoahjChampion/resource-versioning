<?php
/*
	Plugin Name: Resource Versioning
	Description: Turn Query String Parameters into file revision numbers.
	Version: 0.1.2
	Author: Viktor Szépe
	License: GNU General Public License (GPL) version 2
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
	GitHub Plugin URI: https://github.com/szepeviktor/resource-versioning
	Options: O1_REMOVE_ALL_QARGS
*/
/**
 * Trigger fail2ban
 */
if ( ! function_exists( 'add_filter' ) ) {
    error_log( 'Break-in attempt detected: revving_direct_access '
        . addslashes( @$_SERVER['REQUEST_URI'] )
    );
    ob_get_level() && ob_end_clean();
    if ( ! headers_sent() ) {
        header( 'Status: 403 Forbidden' );
        header( 'HTTP/1.1 403 Forbidden', true, 403 );
        header( 'Connection: Close' );
    }
    exit;
}
// ----------------------------------------------------------------------------------------------------
// Register Activation Hook
// ----------------------------------------------------------------------------------------------------
register_activation_hook(__FILE__, 'ResourceVersioning_activation_hook');
// ----------------------------------------------------------------------------------------------------
// If the user's Htaccess file is not editable...
//  Check if the htaccess file is writeable and if not deactivate and give the admin a notice that their .htaccess is not writeable.
// ----------------------------------------------------------------------------------------------------
$htaccess_file = ABSPATH . '.htaccess';
if (!is_writeable($htaccess_file)) {
	// ----------------------------------------------------------------------------------------------------
	// Only run on our needed page (ie: the plugins page)
	// ----------------------------------------------------------------------------------------------------
	global $current_screen;
	$base = $current_screen->base;
	$id = $current_screen->id;
	if ($base == 'plugins' || $id == 'plugins') {
		add_action('admin_notices', 'ResourceVersioning_is_writable_htaccess_admin_notice');
		return;
	}
}
function ResourceVersioning_is_writable_htaccess_admin_notice() {
	// ----------------------------------------------------------------------------------------------------
	// Only run on our needed page (ie: the plugins page)
	// ----------------------------------------------------------------------------------------------------
	global $current_screen;
	$base = $current_screen->base;
	$id = $current_screen->id;
	if ($base == 'plugins' || $id == 'plugins') {
		echo "<div class='error'>File Query Converter to File Versions requires your .htaccess file to be writeable. Please make the file writeable bt changing it FILE PERMISSIONS or find out why your .htaccess file is not writeable, and reactivate the plugin (File Query Converter to File Versions...). Apache Superman has been deactivated in the meantime. Please contact your hosting provider about this or reach out to us for support @ http://custom-theme.com</div>";
	}
}
// ----------------------------------------------------------------------------------------------------
// Htaccess File Edits
// -- If the .htaccess file is writable then proceed to insert our .htaccess file edits
// ----------------------------------------------------------------------------------------------------
function ResourceVersioning_activation_hook() {
	// ----------------------------------------------------------------------------------------------------
	// Only run on our needed page (ie: the plugins page)
	// ----------------------------------------------------------------------------------------------------
	global $current_screen;
	$base = $current_screen->base;
	$id = $current_screen->id;
	if ($base == 'plugins' || $id == 'plugins') {
		// ----------------------------------------------------------------------------------------------------
		// If the htaccess file is not writeable, return
		// ----------------------------------------------------------------------------------------------------
		$htaccess_file = ABSPATH . '.htaccess';
		if (!is_writeable($htaccess_file)) {
			return;
		}
		// ----------------------------------------------------------------------------------------------------
		// If the function doesn't exist, require it
		// ----------------------------------------------------------------------------------------------------
		if (!function_exists('insert_with_markers')) {
			require_once (ABSPATH . 'wp-admin/includes/misc.php');
		}
		// ----------------------------------------------------------------------------------------------------
		// Insert our Apache Superman .htaccess file content
		// ----------------------------------------------------------------------------------------------------
		$insertion = '
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)\.\d\d+\.(js|css|png|jpg|jpeg|gif|ico)$ $1.$2 [NC,L]
';
		// ----------------------------------------------------------------------------------------------------
		// Explode, because we are using an array
		// ----------------------------------------------------------------------------------------------------
		$insertion = explode("\n", $insertion);
		insert_with_markers($htaccess_file, '> File Query Converter to File Versions.', $insertion);
	}
}
// ----------------------------------------------------------------------------------------------------
// @ViktorSzépe Code
// ----------------------------------------------------------------------------------------------------
/**
 * Filter script and style source URL-s.
 */
add_filter( 'script_loader_src', 'ResourceVersioning_src' );
add_filter( 'style_loader_src', 'ResourceVersioning_src' );
/**
 * Insert version into filename from query string.
 *
 * @param string $src  Original URL
 *
 * @return string      Versioned URL
 */
function ResourceVersioning_src( $src ) {
    // Check for external or admin URL
    $siteurl_noscheme = str_replace( array( 'http:', 'https:' ), '', site_url() );
    $contenturl_noscheme = str_replace( array( 'http:', 'https:' ), '', WP_CONTENT_URL );
    if ( is_admin()
        || ( ! ResourceVersioning_start( $src, site_url() )
            && ! ResourceVersioning_start( $src, $siteurl_noscheme )
            && ! ResourceVersioning_start( $src, WP_CONTENT_URL )
            && ! ResourceVersioning_start( $src, $contenturl_noscheme )
        )
    ) {
        return $src;
    }
    // Separate query string from the URL
    $parts = preg_split( '/\?/', $src, 2 );
    // Find version in query
    parse_str( $parts[1], $kwargs );
    if ( empty( $kwargs['ver'] ) ) {
        return $src;
    }
    // Sanitize version
    $ver = preg_replace( '/[^0-9]/', '', $kwargs['ver'] );
    // We need at least two digits for the rewrite rule
    if ( strlen( $ver ) < 2 ) {
        $ver = '0' . $ver;
    }
    // Find where to insert version
    $pos = strrpos( $parts[0], '.' );
    // No dot in URL
    if ( false === $pos ) {
        return $src;
    }
    // Remove all query arguments
    if ( defined( 'O1_REMOVE_ALL_QARGS' ) && O1_REMOVE_ALL_QARGS ) {
        $new_query = '';
    } else {
        unset( $kwargs['ver'] );
        $new_query = build_query( $kwargs );
    }
    // Return the new URL
    return sprintf( '%s.%s.%s%s',
        substr( $parts[0], 0, $pos ),
        $ver,
        substr( $parts[0], $pos + 1 ),
        $new_query ? '?' . $new_query : ''
    );
}
/**
 * Return if haystack starts with needle.
 *
 * @param string $haystack The haystack.
 * @param string $needle   The needle.
 *
 * @return boolean Whether starts with or not.
 */
function ResourceVersioning_start( $haystack, $needle ) {
     $length = strlen( $needle );
     return ( $needle === substr( $haystack, 0, $length ) );
}
// ----------------------------------------------------------------------------------------------------
// Register Deactivation Hook
// ----------------------------------------------------------------------------------------------------
register_deactivation_hook(__FILE__, 'ResourceVersioning_deactivation_hook');
// ----------------------------------------------------------------------------------------------------
// Deactivation Cleanup
// -- If we are being deactivated then proceed to remove our .htaccess file edits
// ----------------------------------------------------------------------------------------------------
function ResourceVersioning_deactivation_hook() {
	// ----------------------------------------------------------------------------------------------------
	// Only run on our needed page (ie: the plugins page)
	// ----------------------------------------------------------------------------------------------------
	global $current_screen;
	$base = $current_screen->base;
	$id = $current_screen->id;
	if ($base == 'plugins' || $id == 'plugins') {
		// ----------------------------------------------------------------------------------------------------
		// If htaccess file is not writeable, return
		// ----------------------------------------------------------------------------------------------------
		$htaccess_file = ABSPATH . '.htaccess';
		if (!is_writeable($htaccess_file)) {
			return;
		}
		// ----------------------------------------------------------------------------------------------------
		// If the function doesn't exist, require it
		// ----------------------------------------------------------------------------------------------------
		if (!function_exists('insert_with_markers')) {
			require_once (ABSPATH . 'wp-admin/includes/misc.php');
		}
		// ----------------------------------------------------------------------------------------------------
		// Remove the rows
		// ----------------------------------------------------------------------------------------------------
		insert_with_markers($htaccess_file, '> File Query Converter to File Versions.', '');
	}
}
