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
	let retry = usageContainer ? usageContainer.querySelector( '[data-npcink-site-knowledge-retry]' ) : document.querySelector( '[data-npcink-site-knowledge-status-retry]' );
	const spinner = usageContainer ? usageContainer.querySelector( '.npcink-cloud-site-knowledge-usage__spinner' ) : null;
	const actions = usageContainer ? usageContainer.querySelector( '[data-npcink-site-knowledge-actions]' ) : null;
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
		if ( spinner ) {
			spinner.classList.toggle( 'is-active', loading );
		}
		if ( actions ) {
			actions.hidden = ! loading && ( ! retry || retry.hidden );
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
		waitingLabel.hidden = '' === label;
		const metaRow = waitingLabel.closest( '.npcink-cloud-site-knowledge-summary__meta' );
		if ( metaRow ) {
			// The waiting label itself is a span in this row, so one check
			// covers it together with any server-rendered siblings.
			const hasVisibleContent = Array.from( metaRow.querySelectorAll( 'span' ) ).some( ( span ) => ! span.hidden && '' !== span.textContent.trim() );
			metaRow.hidden = ! hasVisibleContent;
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
				const serverError = new Error( errorMessage || 'site_knowledge_usage_refresh_failed' );
				serverError.fromServer = true;
				throw serverError;
			}

			updateUsage( payload.data );
			retainedValueLabel = valueLabel ? valueLabel.textContent : '';
			hasRetainedUsage = '' !== retainedValueLabel;
			updateWaitingLabel( payload.data );
			// Reuse the escaped server renderer so counts, warnings and recovery
			// controls describe the same snapshot after an asynchronous refresh.
			const fragments = payload.data.status_fragments || {};
			if ( typeof window.npcinkCloudApplyStatusFragments === 'function' ) {
				window.npcinkCloudApplyStatusFragments( fragments );
			}
			const knowledge = document.querySelector( '.npcink-cloud-site-knowledge-summary' );
			if ( knowledge && typeof fragments.knowledge === 'string' ) {
				const parsed = new DOMParser().parseFromString( fragments.knowledge, 'text/html' );
				const replacement = parsed.querySelector( '.npcink-cloud-site-knowledge-summary' );
				if ( replacement ) {
					knowledge.replaceWith( replacement );
					const statusRetry = replacement.querySelector( '[data-npcink-site-knowledge-status-retry]' );
					if ( statusRetry ) {
						retry = statusRetry;
						statusRetry.addEventListener( 'click', refresh );
					}
				}
			}
			if ( retry ) {
				retry.hidden = true;
			}
		} catch ( error ) {
			// A failed refresh cannot confirm the waiting count; drop the
			// stale value instead of showing it next to an error status.
			updateWaitingLabel( null );
			// Only server-provided messages are display-ready; browser network
			// error text must fall back to the translated config labels below.
			const actionableMessage = error && error.fromServer && error.message && 'site_knowledge_usage_refresh_failed' !== error.message ? error.message : '';
			const fallbackLabel = config.failedLabel || 'Site Knowledge usage is temporarily unavailable.';
			const readStatus = document.querySelector( '[data-npcink-site-knowledge-read-status]' );
			const statusRetry = document.querySelector( '[data-npcink-site-knowledge-status-retry]' );
			if ( readStatus ) {
				readStatus.textContent = actionableMessage || fallbackLabel;
				readStatus.hidden = false;
			}
			if ( statusRetry ) {
				statusRetry.hidden = false;
			}
			// The English literals below are a last-resort fallback for a broken
			// server injection; normal rendering always uses config labels.
			if ( valueLabel ) {
				valueLabel.textContent = hasRetainedUsage
					? retainedValueLabel
					: ( actionableMessage || fallbackLabel );
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
	if ( 'not_refreshed' === initialState || 'stale' === initialState ) {
		refresh();
	}
}() );
