( function ( $ ) {
	'use strict';

	function toggleTemplate() {
		$( '.tves-template-row' ).toggle( 'custom' === $( '#tves-title-format' ).val() );
	}

	function pollSyncStatus() {
		var $card = $( '#tves-sync-card' );
		if ( ! $card.length || 'undefined' === typeof tvesAdmin ) {
			return;
		}

		$.ajax( {
			url: tvesAdmin.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'tves_sync_status',
				nonce: tvesAdmin.syncNonce
			}
		} ).done( function ( response ) {
			if ( ! response.success || ! response.data ) {
				return;
			}

			var data = response.data;
			$card.attr( 'data-running', data.running ? '1' : '0' );
			$( '#tves-sync-status' ).text( data.status_label );
			$( '#tves-last-sync' ).text( data.last_sync );
			$( '#tves-next-sync' ).text( data.next_sync );
			$( '#tves-last-activity' ).text( data.last_activity );
			$( '#tves-progress-label' ).text( data.progress_label );
			$( '#tves-exported-count' ).text( data.exported_items );
			$( '#tves-recovery-count' ).text( data.recovery_count + ' / ' + data.total_retries );
			$( '.tves-progress' ).attr( 'aria-valuenow', data.percent );
			$( '#tves-progress-bar' ).css( 'width', data.percent + '%' );
			$( '#tves-v3-catalog-status' ).text( data.v3_catalog );
			$( '#tves-v3-last-access' ).text( data.v3_last_access );

			window.setTimeout( pollSyncStatus, data.running ? ( Number( tvesAdmin.pollInterval ) || 3000 ) : 30000 );
		} ).fail( function () {
			$( '#tves-progress-label' ).text( tvesAdmin.progressError );
			window.setTimeout( pollSyncStatus, 30000 );
		} );
	}

	var logRequest = null;

	function getLogUrlState() {
		var url = new window.URL( window.location.href );
		return {
			status: url.searchParams.get( 'status' ) || '',
			paged: Math.max( 1, parseInt( url.searchParams.get( 'paged' ), 10 ) || 1 )
		};
	}

	function setLogHistory( status, paged ) {
		var url = new window.URL( window.location.href );
		if ( status ) {
			url.searchParams.set( 'status', status );
		} else {
			url.searchParams.delete( 'status' );
		}
		if ( paged > 1 ) {
			url.searchParams.set( 'paged', paged );
		} else {
			url.searchParams.delete( 'paged' );
		}
		window.history.pushState( { tvesLogs: true, status: status, paged: paged }, '', url.toString() );
	}

	function updateLogControls( data ) {
		var status = data.status || '';
		$( '#tves-log-results' ).attr( 'data-status', status ).attr( 'data-paged', data.paged || 1 );
		$( '#tves-status-filter' ).val( status );
		$( '.tves-log-export input[name="status"]' ).val( status );
		$( '.tves-log-stat' ).removeClass( 'is-active' ).filter( function () {
			return String( $( this ).data( 'status' ) || '' ) === status;
		} ).addClass( 'is-active' );

		if ( data.counts ) {
			$.each( data.counts, function ( key, value ) {
				$( '[data-log-count="' + key + '"]' ).text( Number( value ).toLocaleString() );
			} );
		}
	}

	function setLogLoading( loading ) {
		var $results = $( '#tves-log-results' );
		$results.toggleClass( 'is-loading', loading ).attr( 'aria-busy', loading ? 'true' : 'false' );
		$( '#tves-refresh-logs' ).prop( 'disabled', loading ).toggleClass( 'is-loading', loading );
		$( '.tves-log-filter :input' ).prop( 'disabled', loading );
	}

	function showLogError( message ) {
		$( '#tves-log-error' ).text( message ).prop( 'hidden', false );
	}

	function loadLogs( status, paged, options ) {
		var $results = $( '#tves-log-results' );
		options = options || {};
		if ( ! $results.length || 'undefined' === typeof tvesAdmin ) {
			return;
		}

		status = status || '';
		paged = Math.max( 1, parseInt( paged, 10 ) || 1 );
		if ( logRequest ) {
			logRequest.abort();
		}

		$( '#tves-log-error' ).prop( 'hidden', true ).empty();
		setLogLoading( true );
		logRequest = $.ajax( {
			url: tvesAdmin.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'tves_load_logs',
				nonce: tvesAdmin.logsNonce,
				status: status,
				paged: paged
			}
		} ).done( function ( response ) {
			if ( ! response.success || ! response.data || 'undefined' === typeof response.data.html ) {
				showLogError( tvesAdmin.logsError );
				return;
			}

			$( '#tves-log-results-content' ).html( response.data.html );
			updateLogControls( response.data );
			if ( options.history ) {
				setLogHistory( response.data.status, response.data.paged );
			}
			if ( options.scroll && $results.length ) {
				$results.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} ).fail( function ( xhr, textStatus ) {
			if ( 'abort' !== textStatus ) {
				showLogError( tvesAdmin.logsError );
			}
		} ).always( function ( xhr, textStatus ) {
			if ( 'abort' !== textStatus ) {
				setLogLoading( false );
			}
			logRequest = null;
		} );
	}

	$( function () {
		toggleTemplate();
		$( '#tves-title-format' ).on( 'change', toggleTemplate );
		$( '.tves-manual-sync' ).on( 'submit', function () {
			return window.confirm( tvesAdmin.confirmSync );
		} );

		if ( $( '#tves-sync-card' ).length ) {
			pollSyncStatus();
		}

		$( '.tves-log-summary' ).on( 'click', '.tves-log-stat', function ( event ) {
			event.preventDefault();
			loadLogs( String( $( this ).data( 'status' ) || '' ), 1, { history: true } );
		} );

		$( '.tves-log-filter' ).on( 'submit', function ( event ) {
			event.preventDefault();
			loadLogs( String( $( '#tves-status-filter' ).val() || '' ), 1, { history: true } );
		} );

		$( '#tves-status-filter' ).on( 'change', function () {
			loadLogs( String( $( this ).val() || '' ), 1, { history: true } );
		} );

		$( '#tves-refresh-logs' ).on( 'click', function () {
			var $results = $( '#tves-log-results' );
			loadLogs( String( $results.attr( 'data-status' ) || '' ), $results.attr( 'data-paged' ) || 1, { history: false } );
		} );

		$( '#tves-log-results' ).on( 'click', '.tves-log-pagination a', function ( event ) {
			var url;
			event.preventDefault();
			url = new window.URL( this.href, window.location.href );
			loadLogs( String( $( '#tves-log-results' ).attr( 'data-status' ) || '' ), url.searchParams.get( 'paged' ) || 1, { history: true, scroll: true } );
		} );

		$( window ).on( 'popstate', function () {
			var state;
			if ( ! $( '#tves-log-results' ).length ) {
				return;
			}
			state = getLogUrlState();
			loadLogs( state.status, state.paged, { history: false } );
		} );
	} );
}( jQuery ) );
