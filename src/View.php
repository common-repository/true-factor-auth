<?php

namespace TrueFactor;

use Mustache_Engine;

/**
 * This simple class is responsible for output.
 *
 * @package TrueFactor
 */
class View {

	static $notices = null;

	/**
	 * Output the template.
	 *
	 * @param  string  $name  Template name.
	 * @param  array  $_  Template variables.
	 *
	 * @return bool
	 */
	static function render( $name, $_ = [] ) {
		// Make sure not to interfere with template variables.
		$___template_name = $name;
		extract( $_, EXTR_REFS );
		include self::locateTemplate( $___template_name . '.php' );

		return true;
	}

	/**
	 * Render the template and return output as string.
	 *
	 * @param  string  $name  Template name.
	 * @param  array  $_  Template data.
	 *
	 * @return string
	 */
	static function returnRender( $name, $_ = [] ) {
		ob_start();
		self::render( $name, $_ );

		return ob_get_clean();
	}

	/**
	 * Return JSON response and exit.
	 *
	 * @param  array  $data
	 */
	static function sendJson( $data = [] ) {
		wp_send_json( $data );
	}

	/**
	 * Sends a CSV file to client.
	 *
	 * @param  string[]  $rows
	 * @param  string[]  $header_row
	 * @param  string  $file_name
	 * @param  string  $delimiter
	 * @param  string  $enclosure
	 * @param  string  $escape_char
	 */
	public static function sendCsv( $rows, $header_row, $file_name, $delimiter = ",", $enclosure = '"', $escape_char = "\\" ) {
		if ( substr( $file_name, - 4 ) != '.csv' ) {
			$file_name .= '.csv';
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $file_name );

		$handle = fopen( 'php://output', 'r+' );
		if ( $header_row ) {
			fputcsv( $handle, $header_row, $delimiter, $enclosure, $escape_char );
		}
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, $delimiter, $enclosure, $escape_char );
		}
		fclose( $handle );
		exit;
	}

	static function isAjaxRequest() {
		return ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] )
		         && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' );
	}

	/**
	 * Load notices previously saved in session.
	 */
	static function loadNotices() {
		if ( self::$notices === null ) {
			if ( session_status() != PHP_SESSION_ACTIVE ) {
				session_start();
			}
			if ( empty( $_SESSION['tfa_notices'] ) ) {
				$_SESSION['tfa_notices'] = [];
			}
			self::$notices =& $_SESSION['tfa_notices'];
		}
	}

	static function addNotice( $message, $class = '' ) {
		self::loadNotices();
		self::$notices[] = [
			'message' => $message,
			'class'   => $class,
		];
	}

	/**
	 * Displays notices and clears current stack of notices.
	 *
	 * @param  string  $baseClass
	 */
	static function showNotices( $baseClass = 'notice tfa-notice' ) {
		self::loadNotices();
		while ( $notice = array_shift( self::$notices ) ) { ?>
            <div class="<?php echo $baseClass . ( $notice['class'] ? " notice-{$notice['class']} {$notice['class']}" : '' ) ?>">
                <p><?php echo tf_auth__( $notice['message'] ); ?></p>
            </div>
		<?php }
	}

	static function getNotices( $clear = true ) {
		self::loadNotices();
		if ( ! $clear ) {
			return self::$notices;
		}
		$notices       = self::$notices;
		self::$notices = [];

		return $notices;
	}

	static function ajaxRedirect( $url ) {
		printf( "<script>location.href = '%s';</script>", $url );
	}

	/**
	 * Shortcode Wrapper.
	 *
	 * @param  string|callable  $function  Callback function.
	 * @param  array  $attrs  Attributes. Default to empty array.
	 *
	 * @return string
	 */
	static function returnOutput( $function, ...$attrs ) {
		ob_start();
		call_user_func( $function, ...$attrs );

		return ob_get_clean();
	}

	static function locateTemplate( $template_name ) {
		$default_path = apply_filters( 'tfa_template_path', TRUE_FACTOR_PLUGIN_DIR . '/templates/' );

		// Look within passed path within the theme - this is priority
		$template = locate_template( 'true-factor-auth/' . $template_name );

		// Add support for third party plugin
		$template = apply_filters( 'tfa_locate_template', $template, $template_name, $default_path );
		// Get default template
		if ( ! $template ) {
			$template = $default_path . $template_name;
		}

		return $template;
	}

	public static function mustache_render( $tpl, $values = [] ) {
		return self::mustache()->render( $tpl, $values );
	}

	/** @return Mustache_Engine */
	public static function mustache() {
		static $mustache;

		return $mustache ?: $mustache = new Mustache_Engine( [ 'entity_flags' => ENT_QUOTES ] );
	}

}