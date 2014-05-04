<?php
/**
 * ias-forms class
 * This is the abstract class which all other forms use for generation
 * Includes inline-css and js for use with the specific form.
 */

abstract class ias_forms {
	// Set up return variable
	public $html = NULL;
	// Set up protected variables (used to generate the HTML)
	protected $recaptchaKey = '6LeKivISAAAAAEQWD7n5Szjf1FuDc3dvibxGszvV';
	// https://developers.google.com/recaptcha/docs/php
	protected $id = NULL;
	protected $atts = array(
		'useChosen' => FALSE,		// Use Chosen Styling
		'useValidate' => TRUE,		// Use jQuery Validate plugin
		'useCaptcha' => FALSE,
		'responsiveSize' => 'sm',	// Class to use for col- divs for responsive styling
	);
	protected $form_head = array(
		'html' => NULL,
		'css' => NULL,
		'js' => NULL,
	);
	protected $form_foot = array(
		'html' => NULL,
		'css' => NULL,
		'js' => NULL,
	);
	protected $form_attr = array(
		'role' => 'form',
		'action' => NULL,
		'method' => 'POST',
		'accept-charset' => 'UTF-8',
		'autocomplete' => 'off',
		'enctype' => 'application/x-www-form-urlencoded',
		'name' => NULL,
		'id' => NULL,
		'target' => '_self',
	);
	protected $fieldTypes = array(
		'button',
		'checkbox',
		'date',
		'datetime',
		'datetime-local',
		'email',
		'hidden',
		'month',
		'number',
		'password',
		'radio',
		'range',
		'search',
		'submit',
		'tel',
		'text',
		'time',
		'url',
		'week',
		'select',
		'textarea',
	);
	protected $fields = array();
	protected $layout = array();
	protected $validationRules = array();
	protected $validatedionMessages = array();
	
	// Set up core functions
	private function gen_field_html( $field ) {
		if(!isset($this->fields) || !is_array($this->fields)) {
			return NULL;
		}
		$field_info = $this->fields[$field];
		$html = '';
		$html .= '<div class="form-group">' . "\r\n";
		$html .= '	<label for="' . $this->id . '_' . $field_info['name'] . '">' . $this->id . '_' . $field_info['label'] . '</label>' . "\r\n";
		switch ($field_info['type']) {
			case 'select':
				$html .= '<select class="form-control" name="' . $field_info['name'] . '" id="' . $this->id . '_' . $field_info['name'] . ' "';
				if(!isset($field_info['placeholder']) || !is_null($field_info['placeholder']) || strlen($field_info['placeholder']) != 0 ) {
					$html .= 'data-placeholder="' . $field_info['placeholder'] . '" ';
				}
				foreach ($field_info['attributes'] as $key => $value) {
					$html .= $key . '="' . $value . '" ';
				}
				$html .= '>' . "\r\n";
				if(is_array($field_info['value'])) {
					foreach ($field_info['value'] as $key => $option) {
						if(is_numeric($key) || !isset($option['value'])) {
							$val = $key;
							if(is_array($option)) {
								$text = __($option['text'],IAS_TEXTDOMAIN);
							} else {
								$text = __($option,IAS_TEXTDOMAIN);
							}
						} else {
							$val = $option['value'];
							if( !isset( $option['text'] ) ) {
								$text = __($option['value'],IAS_TEXTDOMAIN);
							} else {
								$text = __($option['text'],IAS_TEXTDOMAIN);
							}
						}
						$html .= '<option value="' . $val . '" ';
						foreach ($option as $attKey => $attval) {
							if($attKey !== 'value' && $attKey !== 'text') {
								$html .= $attKey . '="' . $attval . '" ';
							}
						}
						$html .'>' . $text . '</option>' . "\r\n";
					}
				}
				$html .= '</select>' . "\r\n";
				break;
			
			default:
				$html .= '<input type="' . $field_info['type'] . '" class="form-control" name="' . $field_info['name'] . '" id="' . $this->id . '_' . $field_info['name'] . ' "';
				if(!isset($field_info['placeholder']) || !is_null($field_info['placeholder']) || strlen($field_info['placeholder']) != 0 ) {
					$html .= 'placeholder="' . $field_info['placeholder'] . '" ';
				}
				foreach ($field_info['attributes'] as $key => $value) {
					$html .= $key . '="' . $value . '" ';
				}
				$html .= '/>' . "\r\n";
				break;
		}
		$html .= '</div>' . "\r\n";
		if($field_info['validate'] !== FALSE) {
			array_push($this->validationRules, $field_info['validate']['rules']);
			array_push($this->validatedionMessages, $field_info['validate']['messages']);
		}
		return $html;
	}

	private function gen_row_html( $row ) {
		$cellCount = count($row);
		$cell_class_number = floor(12 / $cellCount);
		$html = '<div class="row">';
		foreach ( $row as $field ) {
			$html .= '	<div class="col=' . $this->atts['responsiveSize'] . '-' . $cell_class_number . '">';
			$html .= $this->gen_field_html( $field );
			$html .= '	</div>';
		}
		$html .= '</div>';
		return $html;
	}

	protected function get_form_html() {
		$unique_name = substr( md5( time() . ip() . rand() ) , 0 , 10 );
		$this->form_attr['id'] = $unique_name;
		$this->form_attr['name'] = $unique_name;
		$this->id = $unique_name;
		$html = '';
		# Put the Header styles, html and then JS
		if(!is_null($this->form_head['css'])) {
			$html .= '	' . '<style type="text/css">' . "\r\n";
			$html .= '	' . $this->form_head['css'] . "\r\n";
			$html .= '	' . '</style>' . "\r\n";
		}
		if(!is_null($this->form_head['html'])) {
			$html .= '	' . $this->form_head['html'] . "\r\n";
		}
		if(!is_null($this->form_head['js'])) {
			$html .= '	' . '<script type="text/javascript">' . "\r\n";
			$html .= '	' . $this->form_head['js'] . "\r\n";
			$html .= '	' . '</script>' . "\r\n";
		}
		# Start Laying down the fields
		$html .= '<form ';
		foreach ($this->form_attr as $key => $value) {
			$html .= $key . '="' . $value .'" ';
		}
		$html .= '>' . "\r\n";
		$html .= '	' . '	<input type="hidden" name="form_id" value="' . $this->id . '" />' . "\r\n";
		$html .= '	' . wp_nonce_field( get_class() , 'form_' . $this->id . '_nonce' ) . "\r\n";
		foreach ($this->layout as $row) {
			$html .= $this->gen_row_html( $row );
		}
		$html .= '</form>';
		# Put the Footer styles, html and then JS
		if(!is_null($this->form_foot['css'])) {
			$html .= '	' . '<style type="text/css">' . "\r\n";
			$html .= '	' . $this->form_foot['css'] . "\r\n";
			$html .= '	' . '</style>' . "\r\n";
		}
		if(!is_null($this->form_foot['html'])) {
			$html .= $this->form_foot['html'] . "\r\n";
		}
		if(!is_null($this->form_foot['js'])) {
			$html .= '	' . '<script type="text/javascript">' . "\r\n";
			$html .= '	' . $this->form_foot['js'] . "\r\n";
			$html .= '	' . '</script>' . "\r\n";
		}
		if( $this->atts['useChosen'] == TRUE ) {
			$html .= '	' .'<script type="text/javascript">' . "\r\n";
			$html .= '	' .'		jQuery(function() {' . "\r\n";
			$html .= '	' .'			jQuery("#' . $this->id . ' select").chosen({enable_split_word_search:true,no_results_text:"' . __('No Match Found',IAS_TEXTDOMAIN) . '",search_contains:true,width:"100%"});' . "\r\n";
			$html .= '	' .'		});' . "\r\n";
			$html .= '	' .'</script>' . "\r\n";
		}
		if( $this->atts['useValidate'] == TRUE ) {
			$html .= '	' .'<script type="text/javascript">' . "\r\n";
			$html .= '	' .'		jQuery(function() {' . "\r\n";
			$html .= '	' .'			jQuery("#' . $this->id . '").validate(';
			$html .= json_encode(array(
					'rules' => $this->validationRules,
					'messages' => $this->validatedionMessages,
				));
			$html .= ')' . "\r\n";
			$html .= '	' .'		});' . "\r\n";
			$html .= '	' .'</script>' . "\r\n";
		}
		$this->html = $html;
	}
	
	// Set up modification functions
	public function regenerate() {
		$this->get_form_html();
	}
	
	public function use_chosen( $value = TRUE ) {
		do_action('ias_form_set_use_chosen',$value);
		if( $value == TRUE || $value == FALSE ) {
			$this->atts['useChosen'] = $value;
			return true;
		} else {
			return FALSE;
		}
	}

	public function use_validate( $value = TRUE ) {
		do_action('ias_form_set_use_validate',$value);
		if( $value == TRUE || $value == FALSE ) {
			$this->atts['useValidate'] = $value;
			return true;
		} else {
			return FALSE;
		}
	}

	public function use_captcha( $value = TRUE ) {
		do_action('ias_form_set_use_captcha',$value);
		if( $value == TRUE || $value == FALSE ) {
			$this->atts['useCaptcha'] = $value;
			return true;
		} else {
			return FALSE;
		}
	}

	public function set_response_size( $size = 'sm' ) {
		do_action('ias_form_set_response_size',$size);
		if( $size == 'xs' || $size == 'sm' || $size == 'md' || $size == 'lg' ) {
			$this->atts['responsiveSize'] = $size;
			return true;
		} else {
			return FALSE;
		}
	}
	
	public function update_form_head_html( $html = NULL , $overwrite = FALSE ) {
		if(!is_null( $this->form_head['html'] )) {
			if( $overwrite == FALSE ) {
				return FALSE;
			}
		}
		do_action('ias_update_form_head_html',$html);
		$this->form_head['html'] = $html;
		return TRUE;
	}

	public function update_form_head_css( $css = NULL , $overwrite = FALSE ) {
		if(!is_null( $this->form_head['css'] )) {
			if( $overwrite == FALSE ) {
				return FALSE;
			}
		}
		do_action('ias_update_form_head_css',$css);
		$this->form_head['css'] = $css;
		return TRUE;
	}

	public function update_form_head_js( $js = NULL , $overwrite = FALSE ) {
		if(!is_null( $this->form_head['js'] )) {
			if( $overwrite == FALSE ) {
				return FALSE;
			}
		}
		do_action('ias_update_form_head_js',$js);
		$this->form_head['js'] = $js;
		return TRUE;
	}

	public function update_form_foot_html( $html = NULL , $overwrite = FALSE ) {
		if(!is_null( $this->form_foot['html'] )) {
			if( $overwrite == FALSE ) {
				return FALSE;
			}
		}
		do_action('ias_update_form_foot_html',$html);
		$this->form_foot['html'] = $html;
		return TRUE;
	}

	public function update_form_foot_css( $css = NULL , $overwrite = FALSE ) {
		if(!is_null( $this->form_foot['css'] )) {
			if( $overwrite == FALSE ) {
				return FALSE;
			}
		}
		do_action('ias_update_form_foot_css',$css);
		$this->form_foot['css'] = $css;
		return TRUE;
	}

	public function update_form_foot_js( $js = NULL , $overwrite = FALSE ) {
		if(!is_null( $this->form_foot['js'] )) {
			if( $overwrite == FALSE ) {
				return FALSE;
			}
		}
		do_action('ias_update_form_foot_js',$js);
		$this->form_foot['js'] = $js;
		return TRUE;
	}

	public function set_form_action( $input = NULL ) {
		do_action('ias_set_form_action',$input);
		$this->form_attr['action'] = $input;
		return true;
	}

	public function set_form_method( $input = 'POST' ) {
		do_action('ias_set_form_method',$input);
		$this->form_attr['method'] = $input;
		return true;
	}

	public function set_form_charset( $input = 'UTF-8' ) {
		do_action('ias_set_form_charset',$input);
		$this->form_attr['accept-charset'] = $input;
		return true;
	}

	public function set_form_autocomplete( $input = TRUE ) {
		do_action('ias_set_form_autocomplete',$input);
		if($input == TRUE) {
			$this->form_attr['autocomplete'] = 'ON';
		} else {
			$this->form_attr['autocomplete'] = 'OFF';
		}
		return true;
	}

	public function set_form_enctype( $input = 'application/x-www-form-urlencoded' ) {
		do_action('ias_set_form_enctype',$input);
		$this->form_attr['enctype'] = $input;
		return true;
	}

	public function set_form_target( $input = '_self' ) {
		do_action('ias_set_form_target',$input);
		$this->form_attr['target'] = $input;
		return true;
	}

	public function add_form_attributes( $atts ) {
		do_action('ias_add_form_attributes',$atts);
		if(!is_array($atts)) {
			return FALSE;
		}
		foreach ($atts as $key => $value) {
			if(is_array($value)) {
				foreach ($value as $r_key => $r_value) {
					$this->form_attr[$r_key] = $r_value;
				}
			} else {
				$this->form_attr[$key] = $value;
			}
		}
	}

	public function update_form_fields( $fields , $overwrite = FALSE) {
		do_action('ias_form_update_fields', $fields );
		if( $this->fields !== array() && $overwrite == FALSE ) {
			return FALSE;
		}
		$this->fields = $fields;
		return TRUE;
	}

	public function update_form_layout( $layout , $overwrite = FALSE) {
		do_action('ias_form_update_layout' , $layout , $overwrite );
		if( $this->layout !== array() && $overwrite == FALSE ) {
			return FALSE;
		}
		$this->layout = $layout;
		return TRUE;
	}

} // end of ias_forms class
?>