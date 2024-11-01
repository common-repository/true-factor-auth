<?php

namespace TrueFactor\Module;

use TrueFactor\AbstractModule;
use TrueFactor\Admin\ModuleList;
use TrueFactor\Helper\Form;
use TrueFactor\Helper\Str;
use TrueFactor\Options;
use TrueFactor\View;

class AdminSettingsModule extends AbstractModule {

	protected function __construct() {
		add_action( 'admin_menu', [ $this, 'create_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/** @var AbstractModule[] */
	protected $modules;

	function register_module( AbstractModule $module ) {
		$this->modules[ $module::get_module_id() ] = $module;
	}

	function get_modules() {
		return $this->modules;
	}

	protected $admin_pages = [];

	/**
	 * Create admin menus.
	 */
	public function create_menu() {

		$root_slug         = 'true-factor-auth';
		$menu_plugin_title = TRUE_FACTOR_PLUGIN_NAME;

		// Create new top-level menu.
		add_menu_page(
			'Modules',
			$menu_plugin_title,
			'administrator',
			$root_slug,
			null,
			'dashicons-shield'
		);

		$hook_dashboard = add_submenu_page(
			$root_slug,
			'Modules',
			'Modules',
			'manage_options',
			$root_slug,
			[ $this, 'action_dashboard' ],
			- 100
		);

		add_action( "load-{$hook_dashboard}", [ $this, 'load_dashboard' ] );

		$this->admin_pages = apply_filters( 'tfa_admin_pages', [] );

		uasort( $this->admin_pages, function ( $a, $b ) {
			$d = ( $b['position'] ?? 10 ) - ( $a['position'] ?? 10 );

			return $d > 0 ? - 1 : ( $d < 0 );
		} );

		foreach ( $this->admin_pages as $page_id => $page_config ) {

			// Add submenu item.
			$hook = add_submenu_page(
				$root_slug,
				$page_config['title'],
				$page_config['menu_title'] ?? $page_config['title'],
				'manage_options',
				$page_id,
				[ $this, "action_settings_{$page_id}" ],
				$page_config['position'] ?? null
			);

			add_action( "load-{$hook}", [ $this, "load_settings_{$page_id}" ] );
		}
	}

	/** @var ModuleList */
	protected $modules_list;

	/**
	 * Admin Dashboard Page.
	 */
	function action_dashboard() {
		View::render( 'admin/module-list', [
			'table' => $this->modules_list,
		] );
	}

	/**
	 * Admin Dashboard Loader.
	 */
	function load_dashboard() {
		$option = 'per_page';
		$args   = [
			'label'   => 'Number of items per page:',
			'default' => 15,
			'option'  => 'user_actions_per_page',
		];
		add_screen_option( $option, $args );

		$this->modules_list = new ModuleList();
		$this->modules_list->prepare_items();
	}

	function enable_module( AbstractModule $module ) {
		Options::set_bool( 'module_' . $module::get_module_id(), true );
	}

	function disable_module( AbstractModule $module ) {
		Options::set_bool( 'module_' . $module::get_module_id(), false );
	}

	/** @var Form */
	protected $settings_form;

	function __call( $name, $arguments ) {
		if ( substr( $name, 0, 14 ) == 'load_settings_' ) {
			return $this->load_settings( substr( $name, 14 ) );
		}
		if ( substr( $name, 0, 16 ) == 'action_settings_' ) {
			return $this->action_settings( substr( $name, 16 ) );
		}
	}

	protected $sections = [];

	/**
	 * Settings Page.
	 *
	 * @param $page_id
	 */
	function action_settings( $page_id ) {
		View::render( 'admin/settings-form', [
			'form'        => $this->settings_form,
			'sections'    => $this->sections,
			'admin_page'  => $page_id,
			'admin_pages' => $this->admin_pages,
		] );
	}

	function load_settings( $page_id ) {
		$this->sections = apply_filters( "tfa_admin_page_settings_{$page_id}", $this->sections );

		$form = new Form();

		foreach ( $this->sections as $section_id => $section ) {
			add_settings_section(
				$section_id, // ID
				empty( $section['title'] ) ? Str::toTitle( str_replace( 'tfa_', '', $section_id ) ) : $section['title'],
				function () use ( $section, $section_id ) {
					echo '<div id="tfa-admin-settings-section-' . $section_id . '" data-section-id="' . $section_id . '"></div>';
					if ( ! empty( $section['intro'] ) ) {
						echo '<div>' . $section['intro'] . '</div>';
					}
				},
				$page_id
			);

			if ( empty( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field_name => $field_config ) {
				register_setting( $page_id, Options::PREFIX . $field_name, $field_config );

				add_settings_field(
					Options::PREFIX . $field_name,
					$field_config['label'] ?? Str::toTitle( $field_name ), // Title
					null,
					$page_id, // Page
					$section_id // Section
				);

				$attrs = $field_config['attrs'] ?? [];
				if ( empty( $attrs['label'] ) ) {
					$attrs['label'] = $field_config['label'] ?? Str::toTitle( $field_name );
				}
				if ( empty( $attrs['_hint'] ) ) {
					$attrs['_hint'] = $field_config['_hint'] ?? null;
				}

				if ( empty( $attrs['type'] ) ) {
					switch ( $field_config['type'] ?? 'string' ) {
						case 'boolean':
							$attrs['type']  = 'checkbox';
							$attrs['value'] = 'yes';
							break;
						case 'integer':
							$attrs['type'] = 'number';
							break;
						case 'string':
							$attrs['type'] = 'text';
							break;
						default:
							$attrs['type'] = 'textarea';
							break;
					}
				}

				$value = Options::get( $field_name ) ?: ( $field_config['default'] ?? null );
				if ( $attrs['type'] == 'checkbox' ) {
					$value = ( $value == 'yes' );
				}

				$form->addField( $field_name, $attrs, $field_config['_rules'] ?? [] );
				$form->set( $field_name, $value );
			}
		}

		$this->settings_form = $form;

		if ( ! $_POST || ! wp_verify_nonce( $_POST['tfau_settings_nonce'], 'tfau_settings' ) ) {
			return;
		}

		try {
			$this->settings_form->setInputData( wp_unslash( $_POST ) );
		} catch ( \Exception $e ) {
			View::addNotice( 'Invalid input', 'error' );

			return;
		}

		foreach ( $this->settings_form->getData() as $k => $v ) {
			Options::set( $k, $v );
		}

	}


	function enqueue_scripts() {
		wp_enqueue_style( 'tfa_admin_styles', TRUE_FACTOR_CSS_URI . '/admin.css', [], filemtime( TRUE_FACTOR_PLUGIN_DIR . '/assets/css/admin.css' ) );
		wp_enqueue_script( 'tfa_admin_script', TRUE_FACTOR_JS_URI . '/admin.js', [ 'jquery' ], filemtime( TRUE_FACTOR_PLUGIN_DIR . '/assets/js/admin.js' ), true );

		wp_enqueue_style( 'tfa_admin_prism', TRUE_FACTOR_CSS_URI . '/prism.css', [], filemtime( TRUE_FACTOR_PLUGIN_DIR . '/assets/css/prism.css' ) );
		wp_enqueue_script( 'tfa_admin_prism', TRUE_FACTOR_JS_URI . '/prism.js', [], filemtime( TRUE_FACTOR_PLUGIN_DIR . '/assets/js/prism.js' ), true );
	}

	// Settings config.

	function show_notice( $message, $key = null, $dismissible = false ) { ?>
        <div class="notice error <?php echo $dismissible ? 'is-dismissible' : '' ?>" data-key="<?php echo $key ?>">
            <p>
				<?php echo $message ?>
            </p>
        </div>
		<?php
	}

}
