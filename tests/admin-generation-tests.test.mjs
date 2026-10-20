import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname( fileURLToPath( import.meta.url ) );
const source = readFileSync( path.join( root, '../assets/admin-generation-tests.js' ), 'utf8' );

function mount( config, openAbility = 'ai/title-generation', fetchImpl ) {
	const log = [];
	const dialog = {
		open: false,
		querySelector( sel ) { return this._nodes[ sel ] || null; },
		querySelectorAll( sel ) { return this._nodesAll[ sel ] || []; },
		showModal() { this.open = true; log.push( 'showModal' ); },
		close() { this.open = false; log.push( 'close' ); },
		addEventListener() {},
		_nodes: {},
		_nodesAll: {},
	};
	const chipButtons = [];
	const fields = {
		_html: '',
		_inputs: {},
		querySelector( sel ) {
			if ( ! sel ) return null;
			const name = ( sel.match( /name="([^"]+)"/ ) || [] )[1];
			if ( name ) {
				if ( ! this._inputs[ name ] ) this._inputs[ name ] = { value: name === 'content' ? 'hello' : '', focus() { this.focused = true; } };
				return this._inputs[ name ];
			}
			return null;
		},
		querySelectorAll( sel ) {
			if ( sel === '[data-npcink-gen-chip-field]' ) return chipButtons;
			return [];
		},
		get innerHTML() { return this._html; },
		set innerHTML( value ) {
			this._html = value;
			chipButtons.length = 0;
			const re = /data-npcink-gen-chip-field="([^"]+)" data-npcink-gen-chip-index="(\d+)"/g;
			let m;
			while ( ( m = re.exec( value ) ) ) {
				chipButtons.push( {
					field: m[1],
					index: m[2],
					getAttribute( name ) {
						if ( name === 'data-npcink-gen-chip-field' ) return this.field;
						if ( name === 'data-npcink-gen-chip-index' ) return this.index;
						return null;
					},
					addEventListener( type, fn ) { this._click = fn; },
				} );
			}
		},
	};
	const mediaBtn = { hidden: true, addEventListener( type, fn ) { this._click = fn; } };
	const result = { innerHTML: '', hidden: true };
	const status = { textContent: '', hidden: true, className: '' };
	const title = { textContent: '' };
	const runBtn = { disabled: false, textContent: 'Generate', addEventListener( type, fn ) { this._click = fn; } };
	const closeBtn = { addEventListener( type, fn ) { this._click = fn; } };
	const explorer = { href: '', hidden: false };
	dialog._nodes = {
		'[data-npcink-gen-title]': title,
		'[data-npcink-gen-fields]': fields,
		'[data-npcink-gen-result]': result,
		'[data-npcink-gen-status]': status,
		'[data-npcink-gen-run]': runBtn,
		'[data-npcink-gen-explorer]': explorer,
		'[data-npcink-gen-media]': mediaBtn,
	};
	dialog._nodesAll = { '[data-npcink-gen-close]': [ closeBtn ] };
	const openBtn = {
		getAttribute( name ) { return name === 'data-npcink-gen-open' ? openAbility : null; },
		addEventListener( type, fn ) { this._click = fn; },
	};
	const handlers = {};
	const fetches = [];
	const sandbox = {
		window: {
			npcinkCloudGenerationTests: config,
			wp: {
				media() {
					return {
						on( event, fn ) { handlers[ event ] = fn; },
						open() { log.push( 'mediaOpen' ); },
						content: { mode() {} },
						state() {
							return {
								get() {
									return {
										first() {
											return {
												toJSON() {
													return { id: 42, url: 'https://example.test/wp-content/uploads/sample.jpg' };
												},
											};
										},
									};
								},
							};
						},
					};
				},
			},
			setTimeout( fn ) { fn(); return 0; },
		},
		document: {
			getElementById( id ) { return id === 'npcink-cloud-gen-test-dialog' ? dialog : null; },
			querySelectorAll( sel ) { return sel === '[data-npcink-gen-open]' ? [ openBtn ] : []; },
		},
		fetch: fetchImpl || ( ( url, opts ) => {
			fetches.push( { url, opts } );
			return Promise.resolve( { json: async () => ( { success: true, data: { message: 'ok', data: { title: 'Hello' } } } ) } );
		} ),
		FormData: class { constructor() { this.entries = {}; } append( k, v ) { this.entries[ k ] = v; } },
		encodeURIComponent,
	};
	runInContext( source, createContext( sandbox ) );
	return { openBtn, runBtn, dialog, fields, mediaBtn, result, status, fetches, title, explorer, handlers, log, chipButtons };
}

const baseStrings = {
	generate: 'Generate',
	generating: 'Generating…',
	generated: 'Generated',
	success: 'Generated',
	viewRaw: 'View raw response',
	error: 'Generation failed. Try again, or open Ability Explorer for details.',
	errorValidation: 'Check the form fields and try again.',
	testText: 'Test text',
	testVision: 'Test vision',
	testImage: 'Test image',
	content: 'Content',
	defaultContent: 'Sample',
	prompt: 'Prompt',
	defaultPrompt: 'A prompt',
	imageUrl: 'Image URL',
	imageUrlHelp: 'Public image URL or data URI.',
	context: 'Context',
	selectMedia: 'Select from media library',
	mediaFrameTitle: 'Select an image',
	mediaFrameButton: 'Use this image',
	chipAltConciseLabel: 'Concise',
	chipAltConciseValue: 'Keep the alt text concise and specific.',
	chipAltSubjectLabel: 'Main subject',
	chipAltSubjectValue: 'Focus on the main subject and skip minor background details.',
	chipAltScreenReaderLabel: 'Screen reader',
	chipAltScreenReaderValue: 'Write the alt text for screen reader users.',
	chipAltNoDecorLabel: 'Not decorative',
	chipAltNoDecorValue: 'Treat this as informative, not decorative.',
	chipImageSimpleLabel: 'Simple scene',
	chipImageSimpleValue: 'A simple, clean illustration with a plain background.',
	chipImagePhotoLabel: 'Photo style',
	chipImagePhotoValue: 'A realistic photograph with natural lighting.',
	chipImageNoTextLabel: 'No text',
	chipImageNoTextValue: 'Do not include any text or logos in the image.',
};

test( 'generation tests open modal and invoke explorer AJAX', async () => {
	const { openBtn, runBtn, dialog, fields, fetches, title, explorer, status, result } = mount( {
		ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
		nonce: 'nonce-1',
		explorerUrl: 'https://example.test/wp-admin/tools.php?page=ai-abilities-explorer',
		strings: baseStrings,
	} );
	openBtn._click();
	assert.equal( dialog.open, true );
	assert.equal( title.textContent, 'Test text' );
	assert.match( fields.innerHTML, /name="content"/ );
	assert.match( fields.innerHTML, /aria-label="Content"/ );
	assert.doesNotMatch( fields.innerHTML, /<label[^>]*>Content<\/label>/ );
	assert.match( explorer.href, /ability=ai%2Ftitle-generation/ );
	runBtn._click();
	await new Promise( ( r ) => setTimeout( r, 0 ) );
	assert.equal( fetches.length, 1 );
	assert.equal( fetches[0].opts.body.entries.action, 'ai_ability_explorer_invoke' );
	assert.equal( status.textContent, 'Generated' );
	assert.match( result.innerHTML, /Hello/ );
	assert.match( result.innerHTML, /View raw response/ );
} );

test( 'generation tests show plain failure without dumping JSON', async () => {
	const { openBtn, runBtn, status, result } = mount(
		{
			ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
			nonce: 'n',
			explorerUrl: 'https://example.test/wp-admin/tools.php?page=ai-abilities-explorer',
			strings: baseStrings,
		},
		'ai/title-generation',
		() => Promise.resolve( {
			json: async () => ( { success: false, data: { message: 'Input validation failed.', errors: [ 'Required field "content" is missing' ] } } ),
		} )
	);
	openBtn._click();
	runBtn._click();
	await new Promise( ( r ) => setTimeout( r, 0 ) );
	assert.equal( status.textContent, 'Check the form fields and try again.' );
	assert.match( result.innerHTML, /View raw response/ );
} );

test( 'media picker fills image url and keeps attachment id internal', async () => {
	let capturedInput = null;
	const { openBtn, runBtn, dialog, fields, mediaBtn, handlers, log } = mount(
		{
			ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
			nonce: 'n',
			explorerUrl: 'https://example.test/wp-admin/tools.php?page=ai-abilities-explorer',
			strings: baseStrings,
		},
		'ai/alt-text-generation',
		( url, opts ) => {
			capturedInput = JSON.parse( opts.body.entries.input );
			return Promise.resolve( { json: async () => ( { success: true, data: { data: { alt_text: 'ok' } } } ) } );
		}
	);
	openBtn._click();
	assert.equal( mediaBtn.hidden, false );
	assert.doesNotMatch( fields.innerHTML, /attachment_id/ );
	assert.match( fields.innerHTML, /name="image_url"/ );
	mediaBtn._click();
	assert.deepEqual( log.slice( 0, 3 ), [ 'showModal', 'close', 'mediaOpen' ] );
	handlers.select();
	handlers.close();
	assert.equal( dialog.open, true );
	assert.equal( fields._inputs.image_url.value, 'https://example.test/wp-content/uploads/sample.jpg' );
	assert.equal( fields._inputs.attachment_id, undefined );
	runBtn._click();
	await new Promise( ( r ) => setTimeout( r, 0 ) );
	assert.equal( capturedInput.attachment_id, 42 );
	assert.equal( capturedInput.image_url, 'https://example.test/wp-content/uploads/sample.jpg' );
} );

test( 'prompt chips write into context and stay editable', async () => {
	const { openBtn, fields, chipButtons } = mount(
		{
			ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
			nonce: 'n',
			explorerUrl: 'https://example.test/wp-admin/tools.php?page=ai-abilities-explorer',
			strings: baseStrings,
		},
		'ai/alt-text-generation'
	);
	openBtn._click();
	assert.match( fields.innerHTML, /npcink-cloud-gen-test-dialog__chips/ );
	assert.match( fields.innerHTML, /Concise/ );
	assert.ok( chipButtons.length >= 1 );
	chipButtons[0]._click();
	assert.equal( fields._inputs.context.value, 'Keep the alt text concise and specific.' );
	fields._inputs.context.value += ' (edited)';
	assert.equal( fields._inputs.context.value, 'Keep the alt text concise and specific. (edited)' );
	assert.equal( fields._inputs.context.focused, true );
} );
