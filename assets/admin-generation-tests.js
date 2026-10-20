( function () {
	'use strict';

	var cfg = window.npcinkCloudGenerationTests;
	if ( ! cfg || ! cfg.ajaxUrl || ! cfg.nonce ) {
		return;
	}

	var s = cfg.strings || {};
	var explorerBase = cfg.explorerUrl || '';
	function explorerTestUrl( ability ) {
		if ( ! explorerBase ) {
			return '';
		}
		var join = explorerBase.indexOf( '?' ) >= 0 ? '&' : '?';
		return explorerBase + join + 'action=test&ability=' + encodeURIComponent( ability );
	}
	cfg.abilities = cfg.abilities || {
		'ai/title-generation': {
			title: s.testText || 'Test text',
			explorerUrl: explorerTestUrl( 'ai/title-generation' ),
			fields: [
				{ key: 'content', type: 'textarea', rows: 6, required: true, hideLabel: true, label: s.content || 'Content', default: s.defaultContent || '' }
			]
		},
		'ai/image-generation': {
			title: s.testImage || 'Test image',
			explorerUrl: explorerTestUrl( 'ai/image-generation' ),
			mediaPicker: true,
			fields: [
				{
					key: 'prompt',
					type: 'textarea',
					rows: 4,
					required: true,
					label: s.prompt || 'Prompt',
					default: s.defaultPrompt || '',
					chips: [
						{ label: s.chipImageSimpleLabel || 'Simple scene', value: s.chipImageSimpleValue || 'A simple, clean illustration with a plain background.' },
						{ label: s.chipImagePhotoLabel || 'Photo style', value: s.chipImagePhotoValue || 'A realistic photograph with natural lighting.' },
						{ label: s.chipImageNoTextLabel || 'No text', value: s.chipImageNoTextValue || 'Do not include any text or logos in the image.' }
					]
				},
				{ key: 'image_url', type: 'url', label: s.imageUrl || 'Image URL', help: s.imageUrlHelp || '' }
			]
		},
		'ai/alt-text-generation': {
			title: s.testVision || 'Test vision',
			explorerUrl: explorerTestUrl( 'ai/alt-text-generation' ),
			mediaPicker: true,
			fields: [
				{ key: 'image_url', type: 'url', label: s.imageUrl || 'Image URL', help: s.imageUrlHelp || '' },
				{
					key: 'context',
					type: 'textarea',
					rows: 3,
					label: s.context || 'Context',
					chips: [
						{ label: s.chipAltConciseLabel || 'Concise', value: s.chipAltConciseValue || 'Keep the alt text concise and specific.' },
						{ label: s.chipAltSubjectLabel || 'Main subject', value: s.chipAltSubjectValue || 'Focus on the main subject and skip minor background details.' },
						{ label: s.chipAltScreenReaderLabel || 'Screen reader', value: s.chipAltScreenReaderValue || 'Write the alt text for screen reader users.' },
						{ label: s.chipAltNoDecorLabel || 'Not decorative', value: s.chipAltNoDecorValue || 'Treat this as informative, not decorative.' }
					]
				}
			]
		}
	};

	var dialog = document.getElementById( 'npcink-cloud-gen-test-dialog' );
	if ( ! dialog ) {
		return;
	}

	var titleEl = dialog.querySelector( '[data-npcink-gen-title]' );
	var fieldsEl = dialog.querySelector( '[data-npcink-gen-fields]' );
	var resultEl = dialog.querySelector( '[data-npcink-gen-result]' );
	var statusEl = dialog.querySelector( '[data-npcink-gen-status]' );
	var runBtn = dialog.querySelector( '[data-npcink-gen-run]' );
	var closeBtns = dialog.querySelectorAll( '[data-npcink-gen-close]' );
	var explorerLink = dialog.querySelector( '[data-npcink-gen-explorer]' );
	var currentAbility = '';
	var selectedAttachmentId = null;
	var mediaBtn = dialog.querySelector( '[data-npcink-gen-media]' );

	function escapeHtml( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function abilityConfig( slug ) {
		return ( cfg.abilities && cfg.abilities[ slug ] ) || null;
	}

	function renderChips( field ) {
		var chips = field.chips || [];
		if ( ! chips.length ) {
			return '';
		}
		var html = '<span class="npcink-cloud-gen-test-dialog__chips" role="group" aria-label="' + escapeHtml( field.label || field.key ) + '">';
		chips.forEach( function ( chip, index ) {
			html +=
				'<button type="button" class="npcink-cloud-gen-test-dialog__chip" data-npcink-gen-chip-field="' +
				escapeHtml( field.key ) +
				'" data-npcink-gen-chip-index="' +
				String( index ) +
				'">' +
				escapeHtml( chip.label || chip.value || '' ) +
				'</button>';
		} );
		html += '</span>';
		return html;
	}

	function renderFields( ability ) {
		var fields = ability.fields || [];
		var html = '';
		fields.forEach( function ( field ) {
			var id = 'npcink-gen-field-' + field.key;
			var label = field.label || field.key;
			var aria = ' aria-label="' + escapeHtml( label ) + '"';
			html += '<p class="npcink-cloud-gen-test-dialog__field">';
			if ( ! field.hideLabel || ( field.chips && field.chips.length ) ) {
				html += '<span class="npcink-cloud-gen-test-dialog__field-head">';
				if ( ! field.hideLabel ) {
					html += '<label for="' + escapeHtml( id ) + '">' + escapeHtml( label ) + '</label>';
				}
				html += renderChips( field );
				html += '</span>';
			}
			if ( 'textarea' === field.type ) {
				html +=
					'<textarea id="' +
					escapeHtml( id ) +
					'" name="' +
					escapeHtml( field.key ) +
					'" rows="' +
					( field.rows || 5 ) +
					'"' +
					aria +
					( field.required ? ' required' : '' ) +
					'>' +
					escapeHtml( field.default || '' ) +
					'</textarea>';
			} else {
				html +=
					'<input id="' +
					escapeHtml( id ) +
					'" name="' +
					escapeHtml( field.key ) +
					'" type="' +
					escapeHtml( field.type || 'text' ) +
					'" value="' +
					escapeHtml( field.default || '' ) +
					'"' +
					aria +
					( field.required ? ' required' : '' ) +
					' />';
			}
			if ( field.help ) {
				html += '<span class="description">' + escapeHtml( field.help ) + '</span>';
			}
			html += '</p>';
		} );
		fieldsEl.innerHTML = html;
		bindPromptChips( ability );
	}

	function bindPromptChips( ability ) {
		var chipMap = {};
		( ability.fields || [] ).forEach( function ( field ) {
			if ( field.chips && field.chips.length ) {
				chipMap[ field.key ] = field.chips;
			}
		} );
		if ( ! fieldsEl || typeof fieldsEl.querySelectorAll !== 'function' ) {
			return;
		}
		fieldsEl.querySelectorAll( '[data-npcink-gen-chip-field]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var fieldKey = button.getAttribute( 'data-npcink-gen-chip-field' ) || '';
				var index = parseInt( button.getAttribute( 'data-npcink-gen-chip-index' ) || '-1', 10 );
				var chips = chipMap[ fieldKey ] || [];
				var chip = chips[ index ];
				if ( ! chip ) {
					return;
				}
				setFieldValue( fieldKey, chip.value || '' );
				var el = fieldsEl.querySelector( '[name="' + fieldKey + '"]' );
				if ( el && typeof el.focus === 'function' ) {
					el.focus();
				}
			} );
		} );
	}

	function setFieldValue( name, value ) {
		var el = fieldsEl.querySelector( '[name="' + name + '"]' );
		if ( el ) {
			el.value = value == null ? '' : String( value );
		}
	}

	function revealDialog() {
		if ( typeof dialog.showModal === 'function' ) {
			if ( ! dialog.open ) {
				dialog.showModal();
			}
			return;
		}
		dialog.setAttribute( 'open', 'open' );
	}

	function openMediaLibrary( onSelect ) {
		if ( ! window.wp || ! window.wp.media ) {
			if ( statusEl ) {
				statusEl.hidden = false;
				statusEl.className = 'npcink-cloud-gen-test-dialog__status is-error';
				statusEl.textContent = s.mediaUnavailable || 'Media library is unavailable on this screen.';
			}
			return;
		}
		var frame = window.wp.media( {
			title: s.mediaFrameTitle || 'Select an image',
			button: { text: s.mediaFrameButton || 'Use this image' },
			library: { type: 'image' },
			multiple: false,
		} );
		// wp.media can emit close before select. Capture on select; finalize on close
		// after a tick so select has run; if select arrives after close, still fill.
		var selectedAttachment = null;
		var settled = false;
		function applySelection() {
			if ( selectedAttachment && typeof onSelect === 'function' ) {
				onSelect( selectedAttachment );
			}
		}
		function restoreDialog() {
			if ( settled ) {
				return;
			}
			settled = true;
			revealDialog();
			applySelection();
		}
		frame.on( 'select', function () {
			var selection = frame.state().get( 'selection' );
			selectedAttachment = selection && selection.first ? selection.first().toJSON() : null;
			if ( settled ) {
				applySelection();
			}
		} );
		frame.on( 'close', function () {
			window.setTimeout( restoreDialog, 0 );
		} );
		// Native <dialog> top-layer blocks wp.media; close first, then open the frame.
		closeDialog();
		frame.on( 'open', function () {
			// Prefer the existing library grid over the empty uploader pane.
			if ( frame.content && typeof frame.content.mode === 'function' ) {
				frame.content.mode( 'browse' );
			}
		} );
		window.setTimeout( function () {
			frame.open();
		}, 0 );
	}

	function syncMediaButton( ability ) {
		if ( ! mediaBtn ) {
			return;
		}
		mediaBtn.hidden = ! ( ability && ability.mediaPicker );
	}

	function bindMediaPicker() {
		if ( ! mediaBtn ) {
			return;
		}
		mediaBtn.addEventListener( 'click', function () {
			openMediaLibrary( function ( attachment ) {
				var id = attachment.id || attachment.ID || '';
				var url = attachment.url || '';
				if ( ! url && attachment.sizes && attachment.sizes.full && attachment.sizes.full.url ) {
					url = attachment.sizes.full.url;
				}
				selectedAttachmentId = id ? parseInt( id, 10 ) || id : null;
				setFieldValue( 'image_url', url );
			} );
		} );
	}

	function collectInput( ability ) {
		var input = {};
		( ability.fields || [] ).forEach( function ( field ) {
			var el = fieldsEl.querySelector( '[name="' + field.key + '"]' );
			if ( ! el ) {
				return;
			}
			var raw = el.value;
			if ( 'number' === field.type || 'integer' === field.valueType ) {
				if ( '' === String( raw ).trim() ) {
					return;
				}
				input[ field.key ] = parseInt( raw, 10 );
				return;
			}
			if ( '' === String( raw ).trim() && ! field.required ) {
				return;
			}
			input[ field.key ] = raw;
		} );
		if ( 'ai/image-generation' === currentAbility ) {
			var imageInput = {};
			if ( input.prompt ) {
				imageInput.prompt = input.prompt;
			}
			return imageInput;
		}
		if ( 'ai/alt-text-generation' === currentAbility && selectedAttachmentId ) {
			input.attachment_id = selectedAttachmentId;
		}
		return input;
	}

	function extractResultData( payload ) {
		if ( ! payload || 'object' !== typeof payload ) {
			return payload;
		}
		if ( Object.prototype.hasOwnProperty.call( payload, 'data' ) ) {
			return payload.data;
		}
		return payload;
	}

	function readableParts( data ) {
		var parts = [];
		if ( ! data || 'object' !== typeof data ) {
			return parts;
		}
		if ( data.image && data.image.data ) {
			var src = String( data.image.data );
			if ( 0 !== src.indexOf( 'data:' ) ) {
				src = 'data:image/png;base64,' + src;
			}
			parts.push( '<p class="npcink-cloud-gen-test-dialog__preview"><img alt="" src="' + escapeHtml( src ) + '" /></p>' );
		}
		if ( typeof data.title === 'string' && data.title ) {
			parts.push( '<p class="npcink-cloud-gen-test-dialog__readable"><strong>' + escapeHtml( data.title ) + '</strong></p>' );
		}
		if ( typeof data.alt_text === 'string' && data.alt_text ) {
			parts.push( '<p class="npcink-cloud-gen-test-dialog__readable">' + escapeHtml( data.alt_text ) + '</p>' );
		}
		if ( typeof data.text === 'string' && data.text ) {
			parts.push( '<p class="npcink-cloud-gen-test-dialog__readable">' + escapeHtml( data.text ) + '</p>' );
		}
		if ( typeof data.summary === 'string' && data.summary ) {
			parts.push( '<p class="npcink-cloud-gen-test-dialog__readable">' + escapeHtml( data.summary ) + '</p>' );
		}
		return parts;
	}

	function foldRaw( payload ) {
		return (
			'<details class="npcink-cloud-gen-test-dialog__raw">' +
			'<summary>' +
			escapeHtml( s.viewRaw || 'View raw response' ) +
			'</summary>' +
			'<pre>' +
			escapeHtml( JSON.stringify( payload, null, 2 ) ) +
			'</pre>' +
			'</details>'
		);
	}

	function formatSuccess( payload ) {
		var data = extractResultData( payload );
		var parts = readableParts( data );
		if ( ! parts.length && data != null && data !== '' ) {
			if ( 'string' === typeof data || 'number' === typeof data || 'boolean' === typeof data ) {
				parts.push( '<p class="npcink-cloud-gen-test-dialog__readable">' + escapeHtml( String( data ) ) + '</p>' );
			}
		}
		if ( ! parts.length ) {
			parts.push( '<p class="npcink-cloud-gen-test-dialog__readable">' + escapeHtml( s.noReadable || 'Generated, but no plain text result was returned.' ) + '</p>' );
		}
		parts.push( foldRaw( payload ) );
		return parts.join( '' );
	}

	function plainError( json ) {
		var data = json && json.data;
		var message = '';
		if ( data && typeof data.message === 'string' ) {
			message = data.message;
		} else if ( data && data.errors && data.errors.length ) {
			message = data.errors.join( '; ' );
		}
		var lower = String( message ).toLowerCase();
		if ( ! message ) {
			return s.error || 'Generation failed.';
		}
		if ( lower.indexOf( 'insufficient permissions' ) >= 0 ) {
			return s.errorPermission || 'You need administrator access to run this test.';
		}
		if ( lower.indexOf( 'ability not found' ) >= 0 ) {
			return s.errorMissingAbility || 'This generation ability is not available. Open Ability Explorer or check the WordPress AI plugin.';
		}
		if ( lower.indexOf( 'input validation failed' ) >= 0 || lower.indexOf( 'required field' ) >= 0 ) {
			return s.errorValidation || 'Check the form fields and try again.';
		}
		if ( lower.indexOf( 'invalid json' ) >= 0 ) {
			return s.errorValidation || 'Check the form fields and try again.';
		}
		if ( lower.indexOf( 'nonce' ) >= 0 || lower.indexOf( 'expired' ) >= 0 ) {
			return s.errorSession || 'This page expired. Refresh and try again.';
		}
		// Prefer host message when it already reads as a short sentence; otherwise fallback.
		if ( message.length <= 180 && message.indexOf( '{' ) < 0 ) {
			return message;
		}
		return s.error || 'Generation failed.';
	}

	function setBusy( busy ) {
		if ( ! runBtn ) {
			return;
		}
		runBtn.disabled = !! busy;
		runBtn.textContent = busy ? cfg.strings.generating : cfg.strings.generate;
	}

	function openFor( slug ) {
		var ability = abilityConfig( slug );
		if ( ! ability ) {
			return;
		}
		currentAbility = slug;
		selectedAttachmentId = null;
		if ( titleEl ) {
			titleEl.textContent = ability.title || slug;
		}
		renderFields( ability );
		syncMediaButton( ability );
		if ( resultEl ) {
			resultEl.innerHTML = '';
			resultEl.hidden = true;
		}
		if ( statusEl ) {
			statusEl.textContent = '';
			statusEl.hidden = true;
			statusEl.className = 'npcink-cloud-gen-test-dialog__status';
		}
		if ( explorerLink && ability.explorerUrl ) {
			explorerLink.href = ability.explorerUrl;
			explorerLink.hidden = false;
		} else if ( explorerLink ) {
			explorerLink.hidden = ! cfg.explorerUrl;
			if ( cfg.explorerUrl ) {
				explorerLink.href = cfg.explorerUrl;
			}
		}
		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', 'open' );
		}
		var first = fieldsEl.querySelector( 'textarea, input' );
		if ( first ) {
			first.focus();
		}
	}

	function closeDialog() {
		if ( typeof dialog.close === 'function' ) {
			dialog.close();
		} else {
			dialog.removeAttribute( 'open' );
		}
	}

	function runGenerate() {
		var ability = abilityConfig( currentAbility );
		if ( ! ability ) {
			return;
		}
		var input = collectInput( ability );
		setBusy( true );
		if ( statusEl ) {
			statusEl.hidden = false;
			statusEl.className = 'npcink-cloud-gen-test-dialog__status is-pending';
			statusEl.textContent = cfg.strings.generating;
		}
		if ( resultEl ) {
			resultEl.hidden = true;
			resultEl.innerHTML = '';
		}
		var body = new FormData();
		body.append( 'action', 'ai_ability_explorer_invoke' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'ability', currentAbility );
		body.append( 'input', JSON.stringify( input ) );
		fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					if ( statusEl ) {
						statusEl.className = 'npcink-cloud-gen-test-dialog__status is-ok';
						statusEl.textContent = s.generated || s.success || 'Generated';
					}
					if ( resultEl ) {
						resultEl.hidden = false;
						resultEl.innerHTML = formatSuccess( json.data || {} );
					}
					return;
				}
				var message = plainError( json );
				if ( statusEl ) {
					statusEl.className = 'npcink-cloud-gen-test-dialog__status is-error';
					statusEl.textContent = message;
				}
				if ( resultEl && json && json.data ) {
					resultEl.hidden = false;
					resultEl.innerHTML = foldRaw( json.data );
				}
			} )
			.catch( function () {
				if ( statusEl ) {
					statusEl.className = 'npcink-cloud-gen-test-dialog__status is-error';
					statusEl.textContent = s.errorNetwork || s.error || 'Generation failed.';
				}
				if ( resultEl ) {
					resultEl.hidden = true;
					resultEl.innerHTML = '';
				}
			} )
			.finally( function () {
				setBusy( false );
			} );
	}

	document.querySelectorAll( '[data-npcink-gen-open]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			openFor( button.getAttribute( 'data-npcink-gen-open' ) || '' );
		} );
	} );
	closeBtns.forEach( function ( button ) {
		button.addEventListener( 'click', closeDialog );
	} );
	bindMediaPicker();
	if ( runBtn ) {
		runBtn.addEventListener( 'click', runGenerate );
	}
	dialog.addEventListener( 'cancel', function ( event ) {
		event.preventDefault();
		closeDialog();
	} );
}() );
