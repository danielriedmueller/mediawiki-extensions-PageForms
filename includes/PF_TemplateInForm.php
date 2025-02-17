<?php
/**
 * Represents a template in a user-defined form.
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */
class PFTemplateInForm {
	private $mTemplateName;
	private $mLabel;
	private $mIntro;
	private $mAddButtonText;
	private $mDisplay;
	private $mEventTitleField;
	private $mEventDateField;
	private $mEventStartDateField;
	private $mEventEndDateField;
	private $mAllowMultiple;
	private $mStrictParsing;
	private $mMinAllowed;
	private $mMaxAllowed;
	private $mFields = [];
	private $mEmbedInTemplate;
	private $mEmbedInField;
	private $mPlaceholder;
	private $mHeight = '200px';
	// Conceptually, it would make more sense to define and store this
	// parameter per-field, rather than per-template. However, it's a lot
	// easier to handle it in just one place, rather than in every form
	// input class. Also, this allows, in theory, to set the order of the
	// fields being displayed - though that's not being done yet.
	private $mDisplayedFieldsWhenMinimized;

	// These fields are for a specific usage of a form (or more
	// specifically, a template in a form) to edit a particular page.
	// Perhaps they should go in another class.
	private $mSearchTemplateStr;
	private $mPregMatchTemplateStr;
	private $mFullTextInPage;
	private $mValuesFromPage = [];
	private $mValuesFromSubmit = [];
	private $mNumInstancesFromSubmit = 0;
	private $mPageCallsThisTemplate = false;
	private $mInstanceNum = 0;
	private $mAllInstancesPrinted = false;
	private $mGridValues = [];

	static function create( $name, $label = null, $allowMultiple = null, $maxAllowed = null, $formFields = null ) {
		$tif = new PFTemplateInForm();
		$tif->mTemplateName = str_replace( '_', ' ', $name );
		if ( $formFields === null ) {
			$template = PFTemplate::newFromName( $tif->mTemplateName );
			$fields = $template->getTemplateFields();
			foreach ( $fields as $field ) {
				$tif->mFields[] = PFFormField::create( $field );
			}
		} else {
			$tif->mFields = $formFields;
		}
		$tif->mLabel = $label;
		$tif->mAllowMultiple = $allowMultiple;
		$tif->mMaxAllowed = $maxAllowed;
		return $tif;
	}

	public static function newFromFormTag( $tag_components ) {
		global $wgPageFormsEmbeddedTemplates;

		$parser = PFUtils::getParser();

		$tif = new PFTemplateInForm();
		$tif->mTemplateName = str_replace( '_', ' ', trim( $parser->recursiveTagParse( $tag_components[1] ) ) );

		$tif->mAddButtonText = wfMessage( 'pf_formedit_addanother' )->text();

		if ( array_key_exists( $tif->mTemplateName, $wgPageFormsEmbeddedTemplates ) ) {
			list( $tif->mEmbedInTemplate, $tif->mEmbedInField ) =
				$wgPageFormsEmbeddedTemplates[$tif->mTemplateName];
			$tif->mPlaceholder = PFFormPrinter::placeholderFormat( $tif->mEmbedInTemplate, $tif->mEmbedInField );
		}

		// Cycle through the other components.
		for ( $i = 2; $i < count( $tag_components ); $i++ ) {
			$component = $tag_components[$i];
			if ( $component == 'multiple' ) {
				$tif->mAllowMultiple = true;
			} elseif ( $component == 'strict' ) {
				$tif->mStrictParsing = true;
			}
			$sub_components = array_map( 'trim', explode( '=', $component, 2 ) );
			if ( count( $sub_components ) == 2 ) {
				if ( $sub_components[0] == 'label' ) {
					$tif->mLabel = $parser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'intro' ) {
					$tif->mIntro = $sub_components[1];
				} elseif ( $sub_components[0] == 'minimum instances' ) {
					$tif->mMinAllowed = $sub_components[1];
				} elseif ( $sub_components[0] == 'maximum instances' ) {
					$tif->mMaxAllowed = $sub_components[1];
				} elseif ( $sub_components[0] == 'add button text' ) {
					$tif->mAddButtonText = $parser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'embed in field' ) {
					// Placeholder on form template level. Assume that the template form def
					// will have a multiple+placeholder parameters, and get the placeholder value.
					// We expect something like TemplateName[fieldName], and convert it to the
					// TemplateName___fieldName form used internally.
					preg_match( '/\s*(.*)\[(.*)\]\s*/', $sub_components[1], $matches );
					if ( count( $matches ) > 2 ) {
						$tif->mEmbedInTemplate = $matches[1];
						$tif->mEmbedInField = $matches[2];
						$tif->mPlaceholder = PFFormPrinter::placeholderFormat( $tif->mEmbedInTemplate, $tif->mEmbedInField );
					}
				} elseif ( $sub_components[0] == 'display' ) {
					$tif->mDisplay = $sub_components[1];
				} elseif ( $sub_components[0] == 'height' ) {
					$tif->mHeight = $sub_components[1];
				} elseif ( $sub_components[0] == 'displayed fields when minimized' ) {
					$tif->mDisplayedFieldsWhenMinimized = $sub_components[1];
				} elseif ( $sub_components[0] == 'event title field' ) {
					$tif->mEventTitleField = $sub_components[1];
				} elseif ( $sub_components[0] == 'event date field' ) {
					$tif->mEventDateField = $sub_components[1];
				} elseif ( $sub_components[0] == 'event start date field' ) {
					$tif->mEventStartDateField = $sub_components[1];
				} elseif ( $sub_components[0] == 'event end date field' ) {
					$tif->mEventEndDateField = $sub_components[1];
				}
			}
		}

		return $tif;
	}

	function getTemplateName() {
		return $this->mTemplateName;
	}

	function getHeight() {
		return $this->mHeight;
	}

	function getFields() {
		return $this->mFields;
	}

	function getEmbedInTemplate() {
		return $this->mEmbedInTemplate;
	}

	function getEmbedInField() {
		return $this->mEmbedInField;
	}

	function getLabel() {
		return $this->mLabel;
	}

	function getIntro() {
		return $this->mIntro;
	}

	function getAddButtonText() {
		return $this->mAddButtonText;
	}

	function getDisplay() {
		return $this->mDisplay;
	}

	function getEventTitleField() {
		return $this->mEventTitleField;
	}

	function getEventDateField() {
		return $this->mEventDateField;
	}

	function getEventStartDateField() {
		return $this->mEventStartDateField;
	}

	function getEventEndDateField() {
		return $this->mEventEndDateField;
	}

	function getPlaceholder() {
		return $this->mPlaceholder;
	}

	function getDisplayedFieldsWhenMinimized() {
		return $this->mDisplayedFieldsWhenMinimized;
	}

	function allowsMultiple() {
		return $this->mAllowMultiple;
	}

	function strictParsing() {
		return $this->mStrictParsing;
	}

	function getMinInstancesAllowed() {
		return $this->mMinAllowed;
	}

	function getMaxInstancesAllowed() {
		return $this->mMaxAllowed;
	}

	function createMarkup() {
		$text = "{{{for template|" . $this->mTemplateName;
		if ( $this->mAllowMultiple ) {
			$text .= "|multiple";
		}
		if ( $this->mLabel != '' ) {
			$text .= "|label=" . $this->mLabel;
		}
		$text .= "}}}\n";
		// For now, HTML for templates differs for multiple-instance
		// templates; this may change if handling of form definitions
		// gets more sophisticated.
		if ( !$this->mAllowMultiple ) {
			$text .= "{| class=\"formtable\"\n";
		}
		foreach ( $this->mFields as $i => $field ) {
			$is_last_field = ( $i == count( $this->mFields ) - 1 );
			$text .= $field->createMarkup( $this->mAllowMultiple, $is_last_field );
		}
		if ( !$this->mAllowMultiple ) {
			$text .= "|}\n";
		}
		$text .= "{{{end template}}}\n";
		return $text;
	}

	// The remaining functions here are intended for an instance of a
	// template in a specific form, and perhaps should be moved into
	// another class.

	function getFullTextInPage() {
		return $this->mFullTextInPage;
	}

	function pageCallsThisTemplate() {
		return $this->mPageCallsThisTemplate;
	}

	function hasValueFromPageForField( $field_name ) {
		return array_key_exists( $field_name, $this->mValuesFromPage );
	}

	function getAndRemoveValueFromPageForField( $field_name ) {
		$value = $this->mValuesFromPage[$field_name];
		unset( $this->mValuesFromPage[$field_name] );
		return $value;
	}

	function getValuesFromPage() {
		return $this->mValuesFromPage;
	}

	function getInstanceNum() {
		return $this->mInstanceNum;
	}

	function getGridValues() {
		return $this->mGridValues;
	}

	function incrementInstanceNum() {
		$this->mInstanceNum++;
	}

	function allInstancesPrinted() {
		return $this->mAllInstancesPrinted;
	}

	function addGridValue( $field_name, $cur_value ) {
		if ( !array_key_exists( $this->mInstanceNum, $this->mGridValues ) ) {
			$this->mGridValues[$this->mInstanceNum] = [];
		}
		$this->mGridValues[$this->mInstanceNum][$field_name] = $cur_value;
	}

	function addField( $form_field ) {
		$this->mFields[] = $form_field;
	}

	// this makes it possible for += and -= to modify values based on existing values.
	function changeFieldValues( $field_name, $new_value, $modifier = null ) {
		$this->mValuesFromPage[$field_name] = $new_value;
		if ( $modifier !== null && array_key_exists( $field_name . $modifier, $this->mValuesFromPage ) ) {
			// clean up old values with + or - in them from the array
			unset( $this->mValuesFromPage[$field_name . $modifier] );
		}
	}

	function setFieldValuesFromSubmit() {
		global $wgRequest;

		// Reset values for every new instance, if this is a
		// multiple-instance template.
		if ( $this->mInstanceNum > 0 ) {
			$this->mValuesFromSubmit = [];
		}

		$query_template_name = str_replace( ' ', '_', $this->mTemplateName );
		// Also replace periods with underlines, since that's what
		// POST does to strings anyway.
		$query_template_name = str_replace( '.', '_', $query_template_name );
		// ...and escape apostrophes.
		//  (Or don't.)
		// $query_template_name = str_replace( "'", "\'", $query_template_name );

		$allValuesFromSubmit = $wgRequest->getArray( $query_template_name );
		if ( $allValuesFromSubmit === null ) {
			return;
		}
		// If this is a multiple-instance template, get the values for
		// this instance of the template.
		if ( $this->mAllowMultiple ) {
			// If this data came from a spreadsheet, unescape some characters.
			$spreadsheetTemplates = $wgRequest->getArray( 'spreadsheet_templates' );
			if ( is_array( $spreadsheetTemplates ) && array_key_exists( $query_template_name, $spreadsheetTemplates ) ) {
				foreach ( $allValuesFromSubmit as &$rowValues ) {
					foreach ( $rowValues as &$curValue ) {
						$curValue = str_replace( [ '&lt;', '&gt;' ], [ '<', '>' ], $curValue );
					}
				}
			}
			$valuesFromSubmitKeys = [];
			foreach ( array_keys( $allValuesFromSubmit ) as $key ) {
				if ( $key != 'num' ) {
					$valuesFromSubmitKeys[] = $key;
				}
			}
			$this->mNumInstancesFromSubmit = count( $valuesFromSubmitKeys );
			if ( $this->mNumInstancesFromSubmit > $this->mInstanceNum ) {
				$instanceKey = $valuesFromSubmitKeys[$this->mInstanceNum];
				$this->mValuesFromSubmit = $allValuesFromSubmit[$instanceKey];
			}
		} else {
			$this->mValuesFromSubmit = $allValuesFromSubmit;
		}
	}

	function getValuesFromSubmit() {
		return $this->mValuesFromSubmit;
	}

	/**
	 * Remove all the bits that should not be parsed - those
	 * contained in <pre> tags, etc. - and place them in an array,
	 * so that they can be added back in later. This will prevent
	 * the brackets, curly braces and pipes within those bits from
	 * interfering with the parsing we need to do.
	 *
	 * @param string $str
	 * @param string[] &$replacements
	 * @return string
	 */
	static function removeUnparsedText( $str, &$replacements ) {
		$startAndEndTags = [
			[ '<pre', 'pre>' ],
			[ '<syntaxhighlight', 'syntaxhighlight>' ],
			[ '<source', 'source>' ],
			[ '<ref', 'ref>' ],
			[ '<nowiki', 'nowiki>' ]
		];
		foreach ( $startAndEndTags as $tags ) {
			list( $startTag, $endTag ) = $tags;

			$startTagLoc = -1;
			while ( ( $startTagLoc + strlen( $startTag ) < strlen( $str ) ) && // Avoid PHP warnings
				( ( $startTagLoc = strpos( $str, $startTag, $startTagLoc + strlen( $startTag ) ) ) !== false ) ) {
				// Ignore "singleton" tags, like '<ref name="abc" />'.
				$possibleSingletonTagEnd = strpos( $str, '/>', $startTagLoc );
				if ( $possibleSingletonTagEnd !== false && $possibleSingletonTagEnd < strpos( $str, '>', $startTagLoc ) ) {
					continue;
				}
				$endTagLoc = strpos( $str, $endTag, $startTagLoc + strlen( $startTag ) );
				// Also ignore unclosed tags.
				if ( $endTagLoc === false ) {
					continue;
				}
				$fullTagTextLength = $endTagLoc + strlen( $endTag ) - $startTagLoc;
				$replacements[] = substr( $str, $startTagLoc, $fullTagTextLength );
				$replacementNum = count( $replacements ) - 1;
				$str = substr_replace( $str, "\1" . $replacementNum . "\2", $startTagLoc, $fullTagTextLength );
			}
		}
		return $str;
	}

	/**
	 * @param string $str
	 * @param string[] $replacements
	 * @return string
	 */
	static function restoreUnparsedText( $str, $replacements ) {
		foreach ( $replacements as $i => $fullTagText ) {
			$str = str_replace( "\1" . $i . "\2", $fullTagText, $str );
		}
		return $str;
	}

	function setFieldValuesFromPage( $existing_page_content ) {
		$unparsedTextReplacements = [];
		$existing_page_content = self::removeUnparsedText( $existing_page_content, $unparsedTextReplacements );
		$matches = [];
		$search_pattern = '/{{' . $this->mPregMatchTemplateStr . '\s*[\|}]/i';
		$content_str = str_replace( '_', ' ', $existing_page_content );
		preg_match( $search_pattern, $content_str, $matches, PREG_OFFSET_CAPTURE );
		// is this check necessary?
		if ( array_key_exists( 0, $matches ) && array_key_exists( 1, $matches[0] ) ) {
			$start_char = $matches[0][1];
			$fields_start_char = $start_char + 2 + strlen( $this->mSearchTemplateStr );
			// Skip ahead to the first real character.
			while ( in_array( $existing_page_content[$fields_start_char], [ ' ', '\n' ] ) ) {
				$fields_start_char++;
			}
			// If the next character is a pipe, skip that too.
			if ( $existing_page_content[$fields_start_char] == '|' ) {
				$fields_start_char++;
			}
			$this->mValuesFromPage = [ '0' => '' ];
			// Cycle through template call, splitting it up by pipes ('|'),
			// except when that pipe is part of a piped link.
			$field = "";
			$uncompleted_square_brackets = 0;
			$uncompleted_curly_brackets = 2;
			$template_ended = false;
			for ( $i = $fields_start_char; !$template_ended && ( $i < strlen( $existing_page_content ) ); $i++ ) {
				$c = $existing_page_content[$i];
				if ( $i + 1 < strlen( $existing_page_content ) ) {
					$nextc = $existing_page_content[$i + 1];
				} else {
					$nextc = null;
				}
				if ( $i > 0 ) {
					$prevc = $existing_page_content[$i - 1];
				} else {
					$prevc = null;
				}
				if ( $c == '[' && ( $nextc == '[' || $prevc == '[' ) ) {
					$uncompleted_square_brackets++;
				} elseif ( $c == ']' && ( $nextc == ']' || $prevc == ']' ) && $uncompleted_square_brackets > 0 ) {
					$uncompleted_square_brackets--;
				} elseif ( $c == '{' && ( $nextc == '{' || $prevc == '{' ) ) {
					$uncompleted_curly_brackets++;
				} elseif ( $c == '}' && ( $nextc == '}' || $prevc == '}' ) && $uncompleted_curly_brackets > 0 ) {
					$uncompleted_curly_brackets--;
				}
				// handle an end to a field and/or template declaration
				$template_ended = ( $uncompleted_curly_brackets == 0 && $uncompleted_square_brackets == 0 );
				$field_ended = ( $c == '|' && $uncompleted_square_brackets == 0 && $uncompleted_curly_brackets <= 2 );
				if ( $template_ended || $field_ended ) {
					// If this was the last character in the template, remove
					// the closing curly brackets.
					if ( $template_ended ) {
						$field = substr( $field, 0, -1 );
					}
					$field = self::restoreUnparsedText( $field, $unparsedTextReplacements );
					// Either there's an equals sign near the beginning or not -
					// handling is similar in either way; if there's no equals
					// sign, the index of this field becomes the key.
					$sub_fields = explode( '=', $field, 2 );
					if ( count( $sub_fields ) > 1 ) {
						$this->mValuesFromPage[trim( $sub_fields[0] )] = trim( $sub_fields[1] );
					} else {
						$this->mValuesFromPage[] = trim( $sub_fields[0] );
					}
					$field = '';
				} else {
					$field .= $c;
				}
			}

			// If there are uncompleted opening brackets, the whole form will get messed up -
			// throw an exception.
			// (If there are too many *closing* brackets, some template stuff will end up in
			// the "free text" field - which is bad, but it's harder for the code to detect
			// the problem - though hopefully, easier for users.)
			if ( $uncompleted_curly_brackets > 0 || $uncompleted_square_brackets > 0 ) {
				throw new MWException( "PageFormsMismatchedBrackets" );
			}

			$fullText = substr( $existing_page_content, $start_char, $i - $start_char );
			$this->mFullTextInPage = self::restoreUnparsedText( $fullText, $unparsedTextReplacements );
		}
	}

	/**
	 * Set some vars based on the current contents of the page being
	 * edited - or at least vars that only need to be set if there's
	 * an existing page.
	 * @param string $existing_page_content
	 */
	function setPageRelatedInfo( $existing_page_content ) {
		// Replace underlines with spaces in template name, to allow for
		// searching on either.
		$this->mSearchTemplateStr = str_replace( '_', ' ', $this->mTemplateName );
		$this->mPregMatchTemplateStr = str_replace(
			[ '/', '(', ')', '^' ],
			[ '\/', '\(', '\)', '\^' ],
			$this->mSearchTemplateStr );
		$this->mPageCallsThisTemplate = preg_match( '/{{' . $this->mPregMatchTemplateStr . '\s*[\|}]/i', str_replace( '_', ' ', $existing_page_content ) );
	}

	function checkIfAllInstancesPrinted( $form_submitted, $source_is_page ) {
		// Find additional instances of this template in the page
		// (if it's an existing page) or the query string (if it's a
		// new page).
		// If there's at least one, re-parse this section of the
		// definition form for the subsequent template instance;
		// if there's none, don't include fields at all.
		// @TODO - There has to be a more efficient way to handle
		// multiple instances of templates, one that doesn't involve
		// re-parsing the same tags, but I don't know what it is.
		// (Also add additional, blank instances if there's a minimum
		// number required in this form, and we haven't reached it yet.)
		if ( !$this->mAllowMultiple ) {
			return;
		}
		if ( $form_submitted && $this->mInstanceNum < $this->mNumInstancesFromSubmit ) {
			return;
		}
		if ( !$form_submitted && $this->mInstanceNum < $this->mMinAllowed ) {
			return;
		}
		if ( !$form_submitted && $source_is_page && $this->mPageCallsThisTemplate ) {
			return;
		}
		if ( !$form_submitted && !$source_is_page && $this->mValuesFromSubmit != null ) {
			return;
		}
		$this->mAllInstancesPrinted = true;
	}

}
