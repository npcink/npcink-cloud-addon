import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname( fileURLToPath( import.meta.url ) );
const source = readFileSync( path.join( root, '../assets/admin-generation-tests.js' ), 'utf8' );

function mount( html, config ) {
	const listeners = new Map();
	const dialog = {
		querySelector( sel ) {
			return this._nodes[ sel ] || null;
		},
		querySelectorAll( sel ) {
			return this._nodesAll[ sel ] || [];
		},
		showModal() {
			this.open = true;
		},
		close() {
			this.open = false;
		},
		addEventListener( type, fn ) {
			listeners.set( type, fn );
		},
		_nodes: {},
		_nodesAll: {},
		open: false,
	};
	const fields = { innerHTML: '', querySelector() { return { value: 'hello', focus() {} }; } };
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
	};
	dialog._nodesAll = {
		'[data-npcink-gen-close]': [ closeBtn ],
	};

	const openBtn = {
		getAttribute( name ) {
			return name === 'data-npcink-gen-open' ? 'ai/title-generation' : null;
		},
		addEventListener( type, fn ) {
			this._click = fn;
		},
	};

	const document = {
		getElementById( id ) {
			return id === 'npcink-cloud-gen-test-dialog' ? dialog : null;
		},
		querySelectorAll( sel ) {
			return sel === '[data-npcink-gen-open]' ? [ openBtn ] : [];
		},
	};

	const fetches = [];
	const sandbox = {
		window: { npcinkCloudGenerationTests: config },
		document,
		fetch( url, opts ) {
			fetches.push( { url, opts } );
			return Promise.resolve( {
				json: async () => ( { success: true, data: { message: 'ok', data: { title: 'Hello' } } } ),
			} );
		},
		FormData: class {
			constructor() { this.entries = {}; }
			append( k, v ) { this.entries[ k ] = v; }
		},
		encodeURIComponent,
	};
	runInContext( source, createContext( sandbox ) );
	return { openBtn, runBtn, dialog, fields, result, status, fetches, title, explorer };
}

test( 'generation tests open modal and invoke explorer AJAX', async () => {
	const { openBtn, runBtn, dialog, fields, fetches, title, explorer, status, result } = mount( '', {
		ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
		nonce: 'nonce-1',
		explorerUrl: 'https://example.test/wp-admin/tools.php?page=ai-abilities-explorer',
		strings: {
			generate: 'Generate',
			generating: 'Generating…',
			generated: 'Generated',
			success: 'Generated',
			viewRaw: 'View raw response',
			error: 'Generation failed. Try again, or open Ability Explorer for details.',
			testText: 'Test text',
			content: 'Content',
			defaultContent: 'Sample',
		},
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
	assert.equal( fetches[0].url, 'https://example.test/wp-admin/admin-ajax.php' );
	assert.equal( fetches[0].opts.body.entries.action, 'ai_ability_explorer_invoke' );
	assert.equal( fetches[0].opts.body.entries.ability, 'ai/title-generation' );
	assert.equal( fetches[0].opts.body.entries.nonce, 'nonce-1' );
	assert.equal( JSON.parse( fetches[0].opts.body.entries.input ).content, 'hello' );
	assert.equal( status.textContent, 'Generated' );
	assert.match( result.innerHTML, /npcink-cloud-gen-test-dialog__readable/ );
	assert.match( result.innerHTML, /Hello/ );
	assert.match( result.innerHTML, /npcink-cloud-gen-test-dialog__raw/ );
	assert.match( result.innerHTML, /View raw response/ );
	assert.doesNotMatch( result.innerHTML, /^<pre>/ );
} );

test( 'generation tests show plain failure without dumping JSON', async () => {
	const listeners = new Map();
	const dialog = {
		querySelector( sel ) { return this._nodes[ sel ] || null; },
		querySelectorAll( sel ) { return this._nodesAll[ sel ] || []; },
		showModal() { this.open = true; },
		close() { this.open = false; },
		addEventListener( type, fn ) { listeners.set( type, fn ); },
		_nodes: {},
		_nodesAll: {},
		open: false,
	};
	const fields = { innerHTML: '', querySelector() { return { value: '', focus() {} }; } };
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
	};
	dialog._nodesAll = { '[data-npcink-gen-close]': [ closeBtn ] };
	const openBtn = {
		getAttribute( name ) { return name === 'data-npcink-gen-open' ? 'ai/title-generation' : null; },
		addEventListener( type, fn ) { this._click = fn; },
	};
	const document = {
		getElementById( id ) { return id === 'npcink-cloud-gen-test-dialog' ? dialog : null; },
		querySelectorAll( sel ) { return sel === '[data-npcink-gen-open]' ? [ openBtn ] : []; },
	};
	const source = readFileSync( path.join( root, '../assets/admin-generation-tests.js' ), 'utf8' );
	const sandbox = {
		window: {
			npcinkCloudGenerationTests: {
				ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
				nonce: 'n',
				explorerUrl: 'https://example.test/wp-admin/tools.php?page=ai-abilities-explorer',
				strings: {
					generate: 'Generate',
					generating: 'Generating…',
					generated: 'Generated',
					viewRaw: 'View raw response',
					error: 'Generation failed. Try again, or open Ability Explorer for details.',
					errorValidation: 'Check the form fields and try again.',
					testText: 'Test text',
					content: 'Content',
					defaultContent: 'Sample',
				},
			},
		},
		document,
		fetch() {
			return Promise.resolve( {
				json: async () => ( { success: false, data: { message: 'Input validation failed.', errors: [ 'Required field "content" is missing' ] } } ),
			} );
		},
		FormData: class { constructor() { this.entries = {}; } append( k, v ) { this.entries[ k ] = v; } },
		encodeURIComponent,
	};
	runInContext( source, createContext( sandbox ) );
	openBtn._click();
	runBtn._click();
	await new Promise( ( r ) => setTimeout( r, 0 ) );
	assert.equal( status.textContent, 'Check the form fields and try again.' );
	assert.match( result.innerHTML, /View raw response/ );
	assert.doesNotMatch( result.innerHTML, /npcink-cloud-gen-test-dialog__readable/ );
} );


test( 'media picker fills attachment id and url for vision', async () => {
	const dialog = {
		querySelector( sel ) { return this._nodes[ sel ] || null; },
		querySelectorAll( sel ) { return this._nodesAll[ sel ] || []; },
		showModal() { this.open = true; },
		close() { this.open = false; },
		addEventListener() {},
		_nodes: {},
		_nodesAll: {},
		open: false,
	};
	const fields = {
		innerHTML: '',
		querySelector( sel ) {
			if ( sel === '[data-npcink-gen-media]' ) {
				return this._mediaBtn;
			}
			const name = ( sel.match( /name="([^"]+)"/ ) || [] )[1];
			if ( ! name ) return null;
			if ( ! this._inputs[ name ] ) {
				this._inputs[ name ] = { value: '' };
			}
			return this._inputs[ name ];
		},
		_inputs: {},
		_mediaBtn: null,
	};
	const result = { innerHTML: '', hidden: true };
	const status = { textContent: '', hidden: true, className: '' };
	const title = { textContent: '' };
	const runBtn = { disabled: false, textContent: 'Generate', addEventListener( type, fn ) { this._click = fn; } };
	const closeBtn = { addEventListener() {} };
	const explorer = { href: '', hidden: false };
	dialog._nodes = {
		'[data-npcink-gen-title]': title,
		'[data-npcink-gen-fields]': fields,
		'[data-npcink-gen-result]': result,
		'[data-npcink-gen-status]': status,
		'[data-npcink-gen-run]': runBtn,
		'[data-npcink-gen-explorer]': explorer,
	};
	dialog._nodesAll = { '[data-npcink-gen-close]': [ closeBtn ] };
	const openBtn = {
		getAttribute( name ) { return name === 'data-npcink-gen-open' ? 'ai/alt-text-generation' : null; },
		addEventListener( type, fn ) { this._click = fn; },
	};
	const document = {
		getElementById( id ) { return id === 'npcink-cloud-gen-test-dialog' ? dialog : null; },
		querySelectorAll( sel ) { return sel === '[data-npcink-gen-open]' ? [ openBtn ] : []; },
	};
	let mediaSelect = null;
	const sandbox = {
		window: {
			npcinkCloudGenerationTests: {
				ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
				nonce: 'n',
				explorerUrl: 'https://example.test/wp-admin/tools.php?page=ai-abilities-explorer',
				strings: {
					generate: 'Generate',
					generating: 'Generating…',
					generated: 'Generated',
					viewRaw: 'View raw response',
					testVision: 'Test vision',
					attachmentId: 'Attachment ID',
					imageUrl: 'Image URL',
					context: 'Context',
					selectMedia: 'Select from media library',
					mediaFrameTitle: 'Select an image',
					mediaFrameButton: 'Use this image',
				},
			},
			wp: {
				media() {
					return {
						on( event, fn ) { if ( event === 'select' ) mediaSelect = fn; },
						open() {},
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
		},
		document,
		fetch() { return Promise.resolve( { json: async () => ( { success: true, data: { data: { alt_text: 'x' } } } ) } ); },
		FormData: class { constructor() { this.entries = {}; } append( k, v ) { this.entries[ k ] = v; } },
		encodeURIComponent,
	};
	const source = readFileSync( path.join( root, '../assets/admin-generation-tests.js' ), 'utf8' );
	// Capture media button listener when innerHTML is set
	Object.defineProperty( fields, 'innerHTML', {
		get() { return this._html || ''; },
		set( value ) {
			this._html = value;
			this._mediaBtn = {
				addEventListener( type, fn ) { this._click = fn; },
			};
		},
	} );
	runInContext( source, createContext( sandbox ) );
	openBtn._click();
	assert.match( fields._html, /Select from media library/ );
	assert.ok( fields._mediaBtn && fields._mediaBtn._click );
	fields._mediaBtn._click();
	assert.ok( mediaSelect );
	mediaSelect();
	assert.equal( fields._inputs.attachment_id.value, '42' );
	assert.equal( fields._inputs.image_url.value, 'https://example.test/wp-content/uploads/sample.jpg' );
} );
