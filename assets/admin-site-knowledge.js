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
	let retry = document.querySelector( '[data-npcink-site-knowledge-status-retry]' );
	const initialWaitingLabel = document.querySelector( '[data-npcink-site-knowledge-waiting]' );
	const initialState = refreshController.dataset.npcinkSiteKnowledgeState || '';
	let retainedValueLabel = valueLabel ? valueLabel.textContent : '';
	let hasRetainedUsage = 'stale' === initialState && '' !== retainedValueLabel;
	let requestInFlight = false;

	if ( ! valueLabel && ! initialWaitingLabel ) {
		return;
	}

	const setLoading = ( loading ) => {
		requestInFlight = loading;
		refreshController.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		if ( retry ) {
			retry.disabled = loading;
		}
	};

	const showReadFailure = ( message, code = '' ) => {
		const status = document.querySelector( '[data-npcink-site-knowledge-read-status]' );
		const diagnostics = document.querySelector( '[data-npcink-site-knowledge-read-diagnostics]' );
		if ( status ) {
			status.textContent = message;
			status.hidden = ! message;
		}
		if ( diagnostics ) {
			diagnostics.querySelector( 'code' ).textContent = code;
			diagnostics.hidden = ! code;
			if ( ! code ) {
				diagnostics.open = false;
			}
		}
		if ( retry ) {
			retry.hidden = ! message;
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

	const updateWaitingLabel = ( usage ) => {
		// Re-query because the server-rendered summary may be replaced after a refresh.
		const waitingLabel = document.querySelector( '[data-npcink-site-knowledge-waiting]' );
		if ( ! waitingLabel ) {
			return;
		}
		const label = usage && usage.waiting_label ? String( usage.waiting_label ) : '';
		waitingLabel.textContent = label;
		// Routine buffering stays quiet even after an asynchronous read.
		waitingLabel.hidden = true;
		const metaRow = waitingLabel.closest( '.npcink-cloud-site-knowledge-summary__meta' );
		if ( metaRow ) {
			const lastUpdated = metaRow.querySelector( '[data-npcink-site-knowledge-last-updated]' );
			const metaSep = metaRow.querySelector( '[data-npcink-site-knowledge-meta-sep]' );
			const hasLastUpdated = !!( lastUpdated && ! lastUpdated.hidden && '' !== lastUpdated.textContent.trim() );
			if ( metaSep ) {
				metaSep.hidden = waitingLabel.hidden || ! hasLastUpdated;
			}
			// Ignore the separator when deciding whether the row has visible copy.
			const hasVisibleContent = Array.from( metaRow.querySelectorAll( 'span' ) ).some( ( span ) => {
				if ( span.hasAttribute( 'data-npcink-site-knowledge-meta-sep' ) ) {
					return false;
				}
				return ! span.hidden && '' !== span.textContent.trim();
			} );
			metaRow.hidden = ! hasVisibleContent;
		}
	};

	const refresh = async () => {
		if ( requestInFlight ) {
			return;
		}

		setLoading( true );

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
				const serverError = new Error( errorMessage || 'site_knowledge_usage_refresh_failed' );
				serverError.fromServer = true;
				serverError.code = payload?.data?.error_code || '';
				throw serverError;
			}

			updateUsage( payload.data );
			retainedValueLabel = valueLabel ? valueLabel.textContent : '';
			hasRetainedUsage = '' !== retainedValueLabel;
			// Reuse the escaped server renderer so counts, warnings and recovery
			// controls describe the same snapshot after an asynchronous refresh.
			const fragments = payload.data.status_fragments || {};
			try {
				if ( typeof window.npcinkCloudApplyStatusFragments === 'function' ) {
					window.npcinkCloudApplyStatusFragments( fragments );
				}
				const knowledge = document.querySelector( '.npcink-cloud-site-knowledge-summary' );
				if ( knowledge && typeof fragments.knowledge === 'string' ) {
					const parsed = new DOMParser().parseFromString( fragments.knowledge, 'text/html' );
					const replacement = parsed.querySelector( '.npcink-cloud-site-knowledge-summary' );
					if ( replacement ) {
						const timeDetail = knowledge.querySelector( '.npcink-cloud-delivery-time' );
						const nextTimeDetail = replacement.querySelector( '.npcink-cloud-delivery-time' );
						const timeFocused = timeDetail && timeDetail.querySelector( 'summary' ) === document.activeElement;
						if ( timeDetail && nextTimeDetail ) {
							nextTimeDetail.open = timeDetail.open;
						}
						knowledge.replaceWith( replacement );
						if ( timeFocused && nextTimeDetail ) {
							nextTimeDetail.querySelector( 'summary' ).focus( { preventScroll: true } );
						}
						const statusRetry = replacement.querySelector( '[data-npcink-site-knowledge-status-retry]' );
						if ( statusRetry ) {
							retry = statusRetry;
							statusRetry.addEventListener( 'click', refresh );
						}
					}
				}
			} catch ( renderError ) {
				// A valid signed read stays successful if the replaced status
				// fragment cannot render; the next refresh re-reads the summary.
			}
			showReadFailure( '' );
			updateWaitingLabel( payload.data );
		} catch ( error ) {
			// A failed refresh cannot confirm the waiting count; drop the
			// stale value instead of showing it next to an error status.
			updateWaitingLabel( null );
			// Only server-provided messages are display-ready; browser network
			// error text must fall back to the translated config labels below.
			const actionableMessage = error && error.fromServer && error.message && 'site_knowledge_usage_refresh_failed' !== error.message ? error.message : '';
			const fallbackLabel = config.failedLabel || 'Site Knowledge usage is temporarily unavailable.';
			showReadFailure( actionableMessage || fallbackLabel, error?.fromServer ? error.code : '' );
			// The English literals below are a last-resort fallback for a broken
			// server injection; normal rendering always uses config labels.
			if ( valueLabel ) {
				valueLabel.textContent = hasRetainedUsage
					? retainedValueLabel
					: ( actionableMessage || fallbackLabel );
			}
			if ( statusLabel ) {
				statusLabel.textContent = hasRetainedUsage ? ( config.usageFailedLabel || 'Usage could not be refreshed. Showing the last available figures.' ) : '';
				statusLabel.hidden = '' === statusLabel.textContent;
			}
			refreshController.dataset.npcinkSiteKnowledgeState = 'unavailable';
		} finally {
			setLoading( false );
		}
	};

	if ( retry ) {
		retry.addEventListener( 'click', refresh );
	}
	if ( 'not_refreshed' === initialState || 'stale' === initialState ) {
		refresh();
	}
}() );
