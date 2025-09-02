<?php
/**
 * GF Choice Inventory Add-On
 *
 * @package GFCI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GFForms' ) ) {
	return;
}

if ( ! class_exists( 'GFAddOn' ) ) {
	require_once GF_PLUGIN_DIR . '/includes/addons/class-gf-addon.php';
}

final class GF_Choice_Inventory_AddOn extends GFAddOn {

	protected $_version = GFCI_VERSION;
	protected $_min_gravityforms_version = '2.6';
	protected $_slug = 'gf-choice-inventory';
	protected $_path = 'gf-choice-inventory/gravityforms-choice-inventory.php';
	protected $_full_path = GFCI_FILE;
	protected $_title = 'Gravity Forms - Choice Inventory';
	protected $_short_title = 'Choice Inventory';

	/** @var GF_Choice_Inventory_AddOn */
	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function init() {
		parent::init();

		// Field settings UI block in the editor.
		add_action( 'gform_field_standard_settings', [ $this, 'field_settings_block' ], 25, 2 );

		// Core flows to apply limits (render/validation/process).
		add_filter( 'gform_pre_render', [ $this, 'apply_limits' ] );
		add_filter( 'gform_pre_validation', [ $this, 'apply_limits' ] );
		add_filter( 'gform_pre_process', [ $this, 'apply_limits' ] );

		// Select markup tweaks (always append label text; disable when not allowed).
		add_filter( 'gform_field_choice_markup_pre_render', [ $this, 'filter_select_choice_markup' ], 10, 4 );

		// Validation (skipped when allow_soldout_submissions is enabled).
		add_filter( 'gform_validation', [ $this, 'validate_submission' ] );

		// Entry tagging + summary column.
		add_filter( 'gform_entry_post_save', [ $this, 'tag_entry_soldout' ], 10, 2 );
		add_filter( 'gform_entry_meta', [ $this, 'register_entry_meta' ], 10, 2 );
		add_filter( 'gform_entry_list_columns', [ $this, 'add_entries_list_column' ], 10, 2 );
		add_filter( 'gform_entries_field_value', [ $this, 'render_entries_list_column' ], 10, 4 );

		// Per-field status writer for conditionals (hidden field inputName = "gfci_{FIELD_ID}_status").
		add_filter( 'gform_entry_post_save', [ $this, 'write_per_field_status' ], 10, 2 );
	}

	public function init_admin() {
		parent::init_admin();
		// Localize strings for the editor JS when on the form editor.
		add_action( 'admin_enqueue_scripts', function () {
			if ( rgget( 'page' ) === 'gf_edit_forms' ) {
				wp_localize_script( 'gfci-editor', 'gfciL10n', [
					'noChoices' => __( 'No choices found. Add choices and they will appear here automatically.', 'gfci' ),
				] );
			}
		} );
	}

	public function scripts() {
		return [
			[
				'handle'  => 'gfci-editor',
				'src'     => GFCI_URL . 'assets/editor.js',
				'version' => GFCI_VERSION,
				'deps'    => [ 'jquery', 'gform_form_editor' ],
				'enqueue' => [
					[ 'admin_page' => [ 'form_editor' ] ],
				],
			],
		];
	}

	public function styles() {
		return [
			[
				'handle'  => 'gfci-editor',
				'src'     => GFCI_URL . 'assets/editor.css',
				'version' => GFCI_VERSION,
				'enqueue' => [
					[ 'admin_page' => [ 'form_editor' ] ],
				],
			],
		];
	}

	/**──────────────────────────────────────────────────────────────────────
	 * Field Settings Block (Editor UI)
	 * Mirrors the MU plugin's HTML structure; CSS moved to assets/editor.css
	 * and behavior to assets/editor.js.
	 *---------------------------------------------------------------------*/
	public function field_settings_block( $position, $form_id ) {
		if ( (int) $position !== 25 ) {
			return;
		}
		?>
		<li class="gfci_block field_setting">
			<label class="section_label"><?php esc_html_e( 'Choice Inventory', 'gfci' ); ?></label>

			<div class="gfci_toggle gfci_field_row">
				<input type="checkbox" id="gfci_enabled"
					onchange="SetFieldProperty('gfci_enabled', this.checked); jQuery('.gfci_wrap')[ this.checked ? 'show' : 'hide' ]();" />
				<label for="gfci_enabled" style="margin:0; cursor:pointer;"><?php esc_html_e( 'Enable inventory limits for this field', 'gfci' ); ?></label>
			</div>

			<div class="gfci_wrap" style="display:none;">
				<div class="gfci_field_row gfci_field_msg">
					<label for="gfci_message"><?php esc_html_e( 'Sold-out message', 'gfci' ); ?></label>
					<input type="text" id="gfci_message" placeholder="<?php esc_attr_e( 'Sold Out', 'gfci' ); ?>"
						oninput="SetFieldProperty('gfci_message', this.value)" />
				</div>

				<div class="gfci_toggle gfci_field_row">
					<input type="checkbox" id="gfci_allow"
						onchange="SetFieldProperty('gfci_allow_soldout_submissions', this.checked)" />
					<label for="gfci_allow" style="margin:0; cursor:pointer;"><?php esc_html_e( 'Allow submission with sold-out choices', 'gfci' ); ?></label>
				</div>

				<div class="gfci_table">
					<table>
						<thead>
							<tr>
								<th style="width:70%"><?php esc_html_e( 'Choice (label)', 'gfci' ); ?></th>
								<th style="width:30%"><?php esc_html_e( 'Limit', 'gfci' ); ?></th>
							</tr>
						</thead>
						<tbody id="gfci_rows">
							<tr><td colspan="2" class="gfci_muted"><?php esc_html_e( 'No choices found. Add choices and they will appear here automatically.', 'gfci' ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<p class="gfci_hint"><?php esc_html_e( 'Blank limit = unlimited. Limit ≤ 0 = immediately sold out.', 'gfci' ); ?></p>
			</div>
		</li>
		<?php
	}

	/**──────────────────────────────────────────────────────────────────────
	 * Helpers
	 *---------------------------------------------------------------------*/
	private static function get_field_message( $field ) {
		$msg = rgar( $field, 'gfci_message' );
		return ( $msg !== '' && $msg !== null ) ? (string) $msg : __( 'Sold Out', 'gfci' );
	}

	private static function get_limits_map( $field ) {
		$raw = rgar( $field, 'gfci_limits_map' );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return [];
	}

	private static function get_limit_for_choice( $field, $choice_value, $choice = null ) {
		if ( $choice && isset( $choice['gfci_limit'] ) && $choice['gfci_limit'] !== '' ) {
			return $choice['gfci_limit'];
		}
		$map = self::get_limits_map( $field );
		return isset( $map[ $choice_value ] ) && $map[ $choice_value ] !== '' ? $map[ $choice_value ] : '';
	}

	private static function is_choice_sold_out( $form_id, $field, $choice_value, $limit ) {
		if ( $limit === '' || $limit === null ) {
			return false;
		}
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			return true;
		}

		$args = [ 'status' => 'active', 'field_filters' => [] ];
		$input_type = GFFormsModel::get_input_type( $field );

		if ( $input_type === 'checkbox' ) {
			foreach ( (array) $field->inputs as $input ) {
				$args['field_filters'][] = [ 'key' => (string) $input['id'], 'value' => (string) $choice_value ];
			}
			$args['field_filters']['mode'] = 'any';
		} else {
			$args['field_filters'][] = [ 'key' => (string) $field->id, 'value' => (string) $choice_value ];
		}

		$count = GFAPI::count_entries( $form_id, $args );
		$count = is_wp_error( $count ) ? 0 : (int) $count;

		return $count >= $limit;
	}

	private static function append_message( $text, $message ) {
		$text_stripped = wp_strip_all_tags( (string) $text );
		$msg_stripped  = wp_strip_all_tags( (string) $message );
		if ( stripos( $text_stripped, $msg_stripped ) !== false ) {
			return $text;
		}
		return $text . ' — ' . esc_html( $message );
	}

	/**──────────────────────────────────────────────────────────────────────
	 * Apply Limits (render / validation / process)
	 *---------------------------------------------------------------------*/
	public function apply_limits( $form ) {
		foreach ( $form['fields'] as &$field ) {
			if ( empty( $field->gfci_enabled ) ) {
				continue;
			}
			$input_type = GFFormsModel::get_input_type( $field );
			if ( ! in_array( $input_type, [ 'radio', 'checkbox', 'select' ], true ) ) {
				continue;
			}
			if ( empty( $field->choices ) ) {
				continue;
			}

			$message      = self::get_field_message( $field );
			$allow_submit  = (bool) rgar( $field, 'gfci_allow_soldout_submissions' );

			foreach ( $field->choices as &$choice ) {
				$val = isset( $choice['value'] ) ? (string) $choice['value'] : '';
				if ( $val === '' ) {
					continue;
				}

				$limit = self::get_limit_for_choice( $field, $val, $choice );
				if ( $limit === '' ) { // unlimited
					continue;
				}

				if ( self::is_choice_sold_out( $form['id'], $field, $val, $limit ) ) {
					if ( ! isset( $choice['gfci_original_text'] ) ) {
						$choice['gfci_original_text'] = rgar( $choice, 'text' );
					}
					$choice['text'] = self::append_message( $choice['text'], $message );
					if ( ! $allow_submit ) {
						$choice['isDisabled'] = true;
					}
				}
			}
		}
		return $form;
	}

	public function filter_select_choice_markup( $choice_markup, $choice, $field, $value ) {
		if ( empty( $field->gfci_enabled ) ) {
			return $choice_markup;
		}
		if ( GFFormsModel::get_input_type( $field ) !== 'select' ) {
			return $choice_markup;
		}

		$val = isset( $choice['value'] ) ? (string) $choice['value'] : '';
		if ( $val === '' ) {
			return $choice_markup;
		}

		$limit  = self::get_limit_for_choice( $field, $val, $choice );
		if ( $limit === '' ) {
			return $choice_markup;
		}

		$message      = self::get_field_message( $field );
		$allow_submit = (bool) rgar( $field, 'gfci_allow_soldout_submissions' );

		if ( self::is_choice_sold_out( $field->formId, $field, $val, $limit ) ) {
			if ( ! $allow_submit && strpos( $choice_markup, ' disabled' ) === false ) {
				$choice_markup = preg_replace( '/(<option\b[^>]*)(>)/i', '$1 disabled="disabled"$2', $choice_markup, 1 );
			}
			$choice_markup = preg_replace_callback(
				'/(>)([^<]*)(<)/',
				function ( $m ) use ( $message ) {
					$txt = trim( $m[2] );
					if ( stripos( $txt, $message ) !== false ) {
						return $m[0];
					}
					return $m[1] . esc_html( $txt . ' — ' . $message ) . $m[3];
				},
				$choice_markup,
				1
			);
		}
		return $choice_markup;
	}

	public function validate_submission( $result ) {
		$form = $result['form'];

		foreach ( $form['fields'] as &$field ) {
			if ( empty( $field->gfci_enabled ) ) {
				continue;
			}
			$input_type = GFFormsModel::get_input_type( $field );
			if ( ! in_array( $input_type, [ 'radio', 'checkbox', 'select' ], true ) ) {
				continue;
			}
			if ( empty( $field->choices ) ) {
				continue;
			}

			$message      = self::get_field_message( $field );
			$allow_submit = (bool) rgar( $field, 'gfci_allow_soldout_submissions' );
			if ( $allow_submit ) {
				continue;
			}

			$submitted = [];
			if ( $input_type === 'checkbox' ) {
				foreach ( (array) $field->inputs as $input ) {
					$raw = rgpost( 'input_' . str_replace( '.', '_', $input['id'] ) );
					if ( $raw !== null && $raw !== '' ) {
						$submitted[] = $raw;
					}
				}
			} else {
				$raw = rgpost( 'input_' . $field->id );
				if ( $raw !== null && $raw !== '' ) {
					$submitted[] = $raw;
				}
			}
			if ( ! $submitted ) {
				continue;
			}

			foreach ( $submitted as $val ) {
				$limit = '';
				$label = $val;
				foreach ( $field->choices as $c ) {
					if ( (string) rgar( $c, 'value' ) === (string) $val ) {
						$limit = self::get_limit_for_choice( $field, $val, $c );
						$label = isset( $c['gfci_original_text'] ) ? $c['gfci_original_text'] : rgar( $c, 'text', $val );
						$label = preg_replace( '/\s+—\s+.+$/', '', (string) $label );
						break;
					}
				}
				if ( $limit === '' ) {
					continue;
				}
				if ( self::is_choice_sold_out( $form['id'], $field, $val, $limit ) ) {
					$field->failed_validation  = true;
					$field->validation_message = sprintf( __( '“%s” is %s.', 'gfci' ), esc_html( $label ), esc_html( $message ) );
					$result['is_valid'] = false;
					break;
				}
			}
		}
		$result['form'] = $form;
		return $result;
	}

	/**──────────────────────────────────────────────────────────────────────
	 * Entry Tagging & List Column
	 *---------------------------------------------------------------------*/
	public function tag_entry_soldout( $entry, $form ) {
		$items = []; // each: [fieldId, fieldLabel, choiceValue, choiceLabel, limit, prev_count, was_sold_out]

		foreach ( $form['fields'] as $field ) {
			if ( empty( $field->gfci_enabled ) ) {
				continue;
			}
			$input_type = GFFormsModel::get_input_type( $field );
			if ( ! in_array( $input_type, [ 'radio', 'checkbox', 'select' ], true ) ) {
				continue;
			}

			// collect submitted values for this field
			$submitted = [];
			if ( $input_type === 'checkbox' ) {
				foreach ( (array) $field->inputs as $input ) {
					$key = (string) $input['id'];
					$raw = rgar( $entry, $key );
					if ( $raw !== null && $raw !== '' ) {
						$submitted[] = $raw;
					}
				}
			} else {
				$raw = rgar( $entry, (string) $field->id );
				if ( $raw !== null && $raw !== '' ) {
					$submitted[] = $raw;
				}
			}
			if ( ! $submitted ) {
				continue;
			}

			foreach ( $submitted as $val ) {
				$limit = self::get_limit_for_choice( $field, $val ); // from JSON map / choice
				if ( $limit === '' ) {
					continue;
				}

				// Count INCLUDING this entry…
				$args = [ 'status' => 'active', 'field_filters' => [] ];
				if ( $input_type === 'checkbox' ) {
					foreach ( (array) $field->inputs as $input ) {
						$args['field_filters'][] = [ 'key' => (string) $input['id'], 'value' => (string) $val ];
					}
					$args['field_filters']['mode'] = 'any';
				} else {
					$args['field_filters'][] = [ 'key' => (string) $field->id, 'value' => (string) $val ];
				}
				$current = GFAPI::count_entries( $form['id'], $args );
				$current = is_wp_error( $current ) ? 0 : (int) $current;

				// …so "previous" is minus 1 for this just-saved entry (cannot go below 0).
				$prev = max( 0, $current - 1 );
				$was_sold_out = ( (int) $limit <= 0 ) || ( $prev >= (int) $limit );

				// Find label (prefer preserved original)
				$label = $val;
				foreach ( $field->choices as $c ) {
					if ( (string) rgar( $c, 'value' ) === (string) $val ) {
						$label = isset( $c['gfci_original_text'] ) ? $c['gfci_original_text'] : rgar( $c, 'text', $val );
						$label = preg_replace( '/\s+—\s+.+$/', '', (string) $label );
						break;
					}
				}

				$items[] = [
					'fieldId'      => $field->id,
					'fieldLabel'   => GFCommon::get_label( $field ),
					'choiceValue'  => $val,
					'choiceLabel'  => $label,
					'limit'        => (int) $limit,
					'prev_count'   => $prev,
					'was_sold_out' => (bool) $was_sold_out,
				];
			}
		}

		$json    = wp_json_encode( $items );
		$summary = 'None';
		$hits    = array_filter( $items, function ( $i ) { return ! empty( $i['was_sold_out'] ); } );
		if ( $hits ) {
			$list    = array_map( function ( $i ) { return sprintf( '%s → %s', $i['fieldLabel'], $i['choiceLabel'] ); }, $hits );
			$summary = implode( ', ', $list );
		}

		gform_update_meta( $entry['id'], 'gfci_soldout_json', $json );
		gform_update_meta( $entry['id'], 'gfci_soldout_summary', $summary );

		return $entry;
	}

	public function register_entry_meta( $entry_meta, $form_id ) {
		$entry_meta['gfci_soldout_summary'] = [
			'label'             => __( 'Sold-out picks at submit time', 'gfci' ),
			'is_numeric'        => false,
			'is_default_column' => false,
			'update_callback'   => null,
			'filter'            => [ 'operators' => [ 'is', 'isnot', 'contains' ] ],
		];
		return $entry_meta;
	}

	public function add_entries_list_column( $columns, $form_id ) {
		$columns['gfci_soldout_summary'] = __( 'Sold-out picks', 'gfci' );
		return $columns;
	}

	public function render_entries_list_column( $value, $form_id, $field_id, $entry ) {
		if ( $field_id === 'gfci_soldout_summary' ) {
			$summary = gform_get_meta( rgar( $entry, 'id' ), 'gfci_soldout_summary' );
			return $summary ? esc_html( $summary ) : 'None';
		}
		return $value;
	}

	/**──────────────────────────────────────────────────────────────────────
	 * Per-field status writer for conditionals
	 * Hidden field with inputName = "gfci_{FIELD_ID}_status" will get
	 * either "soldout" or "available" at submission time.
	 *---------------------------------------------------------------------*/
	public function write_per_field_status( $entry, $form ) {
		// Build a quick lookup of destination hidden fields by expected inputName.
		$dest_by_name = [];
		foreach ( $form['fields'] as $f ) {
			if ( in_array( $f->type, [ 'hidden', 'text' ], true ) ) {
				$name = rgar( $f, 'inputName' );
				if ( $name ) {
					$dest_by_name[ $name ] = $f->id; // store field id
				}
			}
		}

		foreach ( $form['fields'] as $field ) {
			if ( empty( $field->gfci_enabled ) ) {
				continue;
			}
			$input_type = GFFormsModel::get_input_type( $field );
			if ( ! in_array( $input_type, [ 'radio', 'checkbox', 'select' ], true ) ) {
				continue;
			}

			// Collect submitted values for this field
			$submitted = [];
			if ( $input_type === 'checkbox' ) {
				foreach ( (array) $field->inputs as $input ) {
					$key = (string) $input['id'];
					$raw = rgar( $entry, $key );
					if ( $raw !== null && $raw !== '' ) {
						$submitted[] = $raw;
					}
				}
			} else {
				$raw = rgar( $entry, (string) $field->id );
				if ( $raw !== null && $raw !== '' ) {
					$submitted[] = $raw;
				}
			}
			if ( ! $submitted ) {
				continue;
			}

			// Determine if ANY picked choice was sold-out at submit time
			$any_sold_out = false;
			foreach ( $submitted as $val ) {
				$limit = self::get_limit_for_choice( $field, $val );
				if ( $limit === '' ) {
					continue; // unlimited
				}

				// Count INCLUDING this new entry; subtract 1 to get previous count
				$args = [ 'status' => 'active', 'field_filters' => [] ];
				if ( $input_type === 'checkbox' ) {
					foreach ( (array) $field->inputs as $input ) {
						$args['field_filters'][] = [ 'key' => (string) $input['id'], 'value' => (string) $val ];
					}
					$args['field_filters']['mode'] = 'any';
				} else {
					$args['field_filters'][] = [ 'key' => (string) $field->id, 'value' => (string) $val ];
				}
				$current = GFAPI::count_entries( $form['id'], $args );
				$current = is_wp_error( $current ) ? 0 : (int) $current;
				$prev    = max( 0, $current - 1 );

				if ( (int) $limit <= 0 || $prev >= (int) $limit ) {
					$any_sold_out = true;
					break;
				}
			}

			// If there is a matching destination field, write the status
			$expected_name = 'gfci_' . $field->id . '_status';
			if ( isset( $dest_by_name[ $expected_name ] ) ) {
				$dest_id = $dest_by_name[ $expected_name ];
				GFAPI::update_entry_field( $entry['id'], $dest_id, $any_sold_out ? 'soldout' : 'available' );
			}
		}

		return $entry;
	}
}
