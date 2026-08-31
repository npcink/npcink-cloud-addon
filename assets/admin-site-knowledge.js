( function () {
	'use strict';

	const config = window.npcinkCloudSiteKnowledge || {};
	const usageContainer = document.querySelector( '[data-npcink-site-knowledge-usage]' );
	const refreshController = usageContainer || document.querySelector( '[data-npcink-site-knowledge-refresh]' );
	if ( ! refreshController || ! config.ajaxUrl || ! config.action || ! config.nonce ) {
		return;
	}

	const valueLabel = usageContainer ? usageContainer.querySelector( '[data-npcink-site-knowledge-usage-value]' ) : null;
	const statusLabel = usageContainer ? usageContainer.querySelector( '[data-npcink-site-knowledge-usage-status]' ) : null;
	const progress = usageContainer ? usageContainer.querySelector( '[data-npcink-site-knowledge-progress]' ) : null;
	const retry = usageContainer ? usageContainer.querySelector( '[data-npcink-site-knowledge-retry]' ) : null;
	const spinner = usageContainer ? usageContainer.querySelector( '.npcink-cloud-site-knowledge-usage__spinner' ) : null;
	const actions = usageContainer ? usageContainer.querySelector( '[data-npcink-site-knowledge-actions]' ) : null;
	const articleCoverage = document.querySelector( '[data-npcink-site-knowledge-article-coverage]' );
	const articleCoverageRefresh = document.querySelector( '[data-npcink-site-knowledge-coverage-refresh]' );
	const initialState = refreshController.dataset.npcinkSiteKnowledgeState || '';
	const initialValueLabel = valueLabel ? valueLabel.textContent : '';
	let requestInFlight = false;

	if ( ! valueLabel && ! articleCoverage ) {
		return;
	}

	const setLoading = ( loading ) => {
		requestInFlight = loading;
		refreshController.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		if ( retry ) {
			retry.disabled = loading;
		}
		if ( spinner ) {
			spinner.classList.toggle( 'is-active', loading );
		}
		if ( actions ) {
			actions.hidden = ! loading && ( ! retry || retry.hidden );
		}
		if ( articleCoverageRefresh ) {
			articleCoverageRefresh.disabled = loading;
		}
	};

	const updateUsage = ( usage ) => {
		refreshController.dataset.npcinkSiteKnowledgeState = usage.state || 'fresh';
		if ( valueLabel ) {
			valueLabel.textContent = usage.value_label || usage.label || config.failedLabel || '';
		}
		if ( statusLabel ) {
			statusLabel.textContent = usage.status_label || '';
			statusLabel.hidden = '' === statusLabel.textContent;
		}
		if ( usageContainer ) {
			usageContainer.title = usage.tooltip || '';
		}

		if ( progress ) {
			const hasPercent = null !== usage.percent && '' !== usage.percent && Number.isFinite( Number( usage.percent ) );
			progress.hidden = ! usage.available || ! hasPercent;
			progress.classList.remove(
				'npcink-cloud-site-knowledge-progress--ok',
				'npcink-cloud-site-knowledge-progress--warning',
				'npcink-cloud-site-knowledge-progress--error'
			);
			progress.classList.add( 'npcink-cloud-site-knowledge-progress--' + ( usage.severity || 'ok' ) );
			if ( hasPercent ) {
				const percent = Math.max( 0, Math.min( 100, Number( usage.percent ) ) );
				progress.style.setProperty( '--npcink-cloud-progress', percent + '%' );
				progress.setAttribute( 'aria-valuenow', String( percent ) );
			}
		}

	};

	const refresh = async () => {
		if ( requestInFlight ) {
			return;
		}

		setLoading( true );
		if ( retry && 'not_refreshed' === initialState ) {
			retry.hidden = true;
		}

		const body = new URLSearchParams( {
			action: config.action,
			nonce: config.nonce,
		} );

		try {
			const response = await window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			} );
			const payload = await response.json();

			if ( ! response.ok || ! payload.success || ! payload.data || ! payload.data.available ) {
				const errorMessage = payload && payload.data && payload.data.message ? payload.data.message : '';
				throw new Error( errorMessage || 'site_knowledge_usage_refresh_failed' );
			}

			updateUsage( payload.data );
			if ( articleCoverage ) {
				window.location.reload();
				return;
			}
			if ( retry ) {
				retry.hidden = true;
			}
		} catch ( error ) {
			const hasRetainedUsage = 'stale' === initialState && '' !== initialValueLabel;
			const actionableMessage = error && error.message && 'site_knowledge_usage_refresh_failed' !== error.message ? error.message : '';
			if ( valueLabel ) {
				valueLabel.textContent = hasRetainedUsage
					? initialValueLabel
					: ( actionableMessage || config.failedLabel || 'Site Knowledge usage is temporarily unavailable.' );
			}
			if ( statusLabel ) {
				statusLabel.textContent = hasRetainedUsage ? ( actionableMessage || config.updateFailedLabel || 'Update failed' ) : '';
				statusLabel.hidden = '' === statusLabel.textContent;
			}
			refreshController.dataset.npcinkSiteKnowledgeState = 'unavailable';
			if ( retry ) {
				retry.hidden = false;
			}
		} finally {
			setLoading( false );
		}
	};

	if ( retry ) {
		retry.addEventListener( 'click', refresh );
	}
	if ( articleCoverageRefresh ) {
		articleCoverageRefresh.addEventListener( 'click', refresh );
	}

	if ( 'not_refreshed' === initialState || 'stale' === initialState ) {
		refresh();
	}
}() );

( function () {
	'use strict';

	const config = window.npcinkCloudSiteKnowledge || {};
	const table = document.querySelector( '[data-npcink-site-media-status]' );
	if ( ! table || 'processing' !== table.dataset.state || ! config.ajaxUrl || ! config.mediaAction || ! config.mediaNonce ) {
		return;
	}

	const images = table.querySelector( '[data-npcink-site-media-images]' );
	const progress = table.querySelector( '[data-npcink-site-media-progress]' );
	const progressLabel = table.querySelector( '[data-npcink-site-media-progress-label]' );
	const evidence = document.querySelector( '[data-npcink-site-media-evidence]' );
	const outcomes = document.querySelector( '[data-npcink-site-media-outcomes]' );
	const speed = document.querySelector( '[data-npcink-site-media-speed]' );
	const eta = document.querySelector( '[data-npcink-site-media-eta]' );
	const stateLabel = document.querySelector( '[data-npcink-site-media-state-label]' );
	const pollError = document.querySelector( '[data-npcink-site-media-poll-error]' );
	const pollInterval = Math.max( 5000, Number( config.mediaPollInterval ) || 10000 );
	let requestInFlight = false;
	let stopped = false;

	const schedule = () => {
		if ( ! stopped ) {
			window.setTimeout( poll, pollInterval );
		}
	};

	const formatEta = ( value ) => {
		if ( ! value ) {
			return config.estimatingLabel || '';
		}
		const parsed = new Date( value );
		return Number.isNaN( parsed.getTime() ) ? ( config.estimatingLabel || '' ) : parsed.toLocaleString();
	};

	const update = ( status ) => {
		const processed = Math.max( 0, Number( status.eligible_processed ) || 0 );
		const total = Math.max( 0, Number( status.eligible_total ) || 0 );
		const percent = Math.max( 0, Math.min( 100, Number( status.display_percent ) || 0 ) );
		const rate = Math.max( 0, Number( status.items_per_minute ) || 0 );

		table.dataset.state = status.state || 'processing';
		if ( images && total > 0 ) {
			images.textContent = processed + ' / ' + total + ' ' + ( config.imagesLabel || 'images' );
		}
		if ( progress ) {
			if ( percent > 0 ) {
				progress.style.setProperty( '--npcink-cloud-progress', percent + '%' );
				progress.setAttribute( 'aria-valuenow', String( percent ) );
				progress.classList.remove( 'npcink-cloud-progress--indeterminate' );
			} else {
				progress.style.setProperty( '--npcink-cloud-progress', '0%' );
				progress.setAttribute( 'aria-valuenow', '0' );
				progress.classList.add( 'npcink-cloud-progress--indeterminate' );
			}
		}
		if ( progressLabel ) {
			progressLabel.textContent = percent > 0 ? percent + '%' : ( config.processingLabel || '' );
		}
		if ( evidence ) {
			evidence.textContent = String( Math.max( 0, Number( status.evidence ) || 0 ) );
		}
		if ( outcomes ) {
			outcomes.textContent = Math.max( 0, Number( status.successful ) || 0 ) + ' / ' + Math.max( 0, Number( status.failed ) || 0 );
		}
		if ( speed ) {
			speed.textContent = 'complete' === status.state || 'partial' === status.state
				? ( config.completedSpeedLabel || '' )
				: ( rate > 0 ? rate.toFixed( 1 ) + ' ' + ( config.imagesPerMinuteLabel || '' ) : ( config.estimatingLabel || '' ) );
		}
		if ( eta ) {
			eta.textContent = formatEta( status.eta_at );
		}
		if ( stateLabel ) {
			stateLabel.textContent = config.processingLabel || stateLabel.textContent;
		}
	};

	async function poll() {
		if ( stopped || requestInFlight ) {
			return;
		}
		requestInFlight = true;
		table.setAttribute( 'aria-busy', 'true' );
		const body = new URLSearchParams( {
			action: config.mediaAction,
			nonce: config.mediaNonce,
		} );

		try {
			const response = await window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			} );
			const payload = await response.json();
			if ( ! response.ok || ! payload.success || ! payload.data ) {
				throw new Error( payload && payload.data && payload.data.message ? payload.data.message : '' );
			}

			if ( pollError ) {
				pollError.hidden = true;
				pollError.textContent = '';
			}
			update( payload.data );
			if ( 'processing' !== payload.data.state ) {
				stopped = true;
				window.location.reload();
				return;
			}
		} catch ( error ) {
			if ( pollError ) {
				pollError.hidden = false;
				pollError.textContent = error && error.message ? error.message : ( config.mediaPollFailedLabel || '' );
			}
		} finally {
			requestInFlight = false;
			table.setAttribute( 'aria-busy', 'false' );
			schedule();
		}
	}

	schedule();
}() );
