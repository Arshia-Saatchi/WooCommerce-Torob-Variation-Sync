( function ( $ ) {
	'use strict';

	var dashboardRequest = null;
	var logRequest = null;
	var syncTimer = null;

	function toggleTemplate() {
		$( '.tves-template-row' ).toggle( 'custom' === $( '#tves-title-format' ).val() );
	}

	function scheduleSyncPoll( delay ) {
		window.clearTimeout( syncTimer );
		if ( $( '#tves-sync-card' ).length ) {
			syncTimer = window.setTimeout( pollSyncStatus, delay );
		}
	}

	function pollSyncStatus() {
		var $card = $( '#tves-sync-card' );
		if ( ! $card.length || 'undefined' === typeof tvesAdmin ) {
			return;
		}
		$.post( tvesAdmin.ajaxUrl, { action: 'tves_sync_status', nonce: tvesAdmin.syncNonce }, null, 'json' ).done( function ( response ) {
			if ( ! response.success || ! response.data || ! $( '#tves-sync-card' ).length ) {
				return;
			}
			var data = response.data;
			$( '#tves-sync-card' ).attr( 'data-running', data.running ? '1' : '0' );
			$( '#tves-sync-signal' ).toggleClass( 'is-running', Boolean( data.running ) ).toggleClass( 'is-ready', ! data.running );
			$( '#tves-sync-status' ).text( data.status_label );
			$( '#tves-live-label' ).text( data.running ? tvesAdmin.syncLive : tvesAdmin.syncReady );
			$( '#tves-last-sync' ).text( data.last_sync );
			$( '#tves-next-sync' ).text( data.next_sync );
			$( '#tves-last-activity' ).text( data.last_activity );
			$( '#tves-progress-label' ).text( data.progress_label );
			$( '#tves-exported-count' ).text( Number( data.exported_items ).toLocaleString() );
			$( '#tves-recovery-count' ).text( data.recovery_count + ' / ' + data.total_retries );
			$( '.tves-progress' ).attr( 'aria-valuenow', data.percent );
			$( '#tves-progress-bar' ).css( 'width', data.percent + '%' );
			$( '#tves-v3-catalog-status' ).text( data.v3_catalog );
			$( '#tves-v3-last-access' ).text( data.v3_last_access );
			scheduleSyncPoll( data.running ? ( Number( tvesAdmin.pollInterval ) || 3000 ) : 30000 );
		} ).fail( function () {
			$( '#tves-progress-label' ).text( tvesAdmin.progressError );
			scheduleSyncPoll( 30000 );
		} );
	}

	function copyEndpoint( button ) {
		var $button = $( button );
		var target = document.getElementById( String( $button.data( 'copy-target' ) || '' ) );
		var text = target ? target.textContent.trim() : '';
		var $label = $button.find( 'span' ).last();
		var original = String( $button.data( 'label' ) || $label.text() );
		if ( ! text || ! window.navigator.clipboard ) { return; }
		window.navigator.clipboard.writeText( text ).then( function () {
			$button.addClass( 'is-copied' );
			$label.text( String( $button.data( 'success' ) || original ) );
			window.setTimeout( function () { $button.removeClass( 'is-copied' ); $label.text( original ); }, 1600 );
		} );
	}

	function filterCategories( value ) {
		var query = String( value || '' ).toLocaleLowerCase();
		$( '.tves-category-list > label' ).each( function () {
			var haystack = String( $( this ).data( 'search' ) || $( this ).text() ).toLocaleLowerCase();
			$( this ).prop( 'hidden', Boolean( query ) && -1 === haystack.indexOf( query ) );
		} );
	}

	function renderSelectedExclusions( select ) {
		var $select = $( select );
		var selectId = String( $select.attr( 'id' ) || '' );
		var $panel = $( '.tves-selected-exclusions[data-select-id="' + selectId + '"]' );
		var items = [];
		var $tbody;

		if ( ! selectId || ! $panel.length ) {
			return;
		}

		$select.find( 'option:selected' ).each( function () {
			items.push( {
				id: String( this.value ),
				name: String( $( this ).text() || '' ).trim()
			} );
		} );

		$tbody = $panel.find( 'tbody' ).empty();
		$.each( items, function ( index, item ) {
			var $row = $( '<tr>' ).attr( 'data-value', item.id );
			var removeText = String( tvesAdmin.removeExclusion || 'حذف' );
			var $removeButton = $( '<button>' ).attr( {
				type: 'button',
				'class': 'tves-remove-exclusion',
				'data-value': item.id,
				'aria-label': removeText + ' ' + item.name
			} );

			$removeButton.append( $( '<span>' ).addClass( 'dashicons dashicons-trash' ).attr( 'aria-hidden', 'true' ) );
			$removeButton.append( $( '<span>' ).addClass( 'tves-remove-exclusion-label' ).text( removeText ) );

			$( '<td>' ).text( item.name ).appendTo( $row );
			$( '<td>' ).addClass( 'tves-exclusion-id' ).attr( 'dir', 'ltr' ).text( '#' + item.id ).appendTo( $row );
			$( '<td>' ).addClass( 'tves-exclusion-actions' ).append( $removeButton ).appendTo( $row );
			$tbody.append( $row );
		} );

		$panel.prop( 'hidden', 0 === items.length );
		$panel.find( '[data-exclusion-count]' ).text( Number( items.length ).toLocaleString() );
		$select.next( '.select2' ).find( '.select2-search__field' ).attr( 'placeholder', String( $select.data( 'placeholder' ) || '' ) );
	}

	function initializeExclusionTables() {
		$( '.tves-exclusion-control select.wc-product-search' ).each( function () {
			renderSelectedExclusions( this );
		} );
	}

	function dashboardUrl( tab ) {
		var url = new window.URL( window.location.href );
		url.searchParams.set( 'page', 'tves-settings' );
		url.searchParams.set( 'tab', tab );
		url.searchParams.delete( 'status' );
		url.searchParams.delete( 'paged' );
		url.searchParams.delete( 'tves_notice' );
		return url;
	}

	function activateTab( tab ) {
		$( '.tves-dashboard-tabs a' ).removeClass( 'is-current' ).removeAttr( 'aria-current' ).filter( '[data-tab="' + tab + '"]' ).addClass( 'is-current' ).attr( 'aria-current', 'page' );
		$( '.tves-settings-screen' ).attr( 'data-active-tab', tab );
	}

	function initializeTab( tab ) {
		toggleTemplate();
		if ( 'overview' === tab ) { pollSyncStatus(); } else { window.clearTimeout( syncTimer ); }
		if ( 'exclusions' === tab ) {
			$( document.body ).trigger( 'wc-enhanced-select-init' );
			initializeExclusionTables();
			window.setTimeout( initializeExclusionTables, 50 );
		}
	}

	function loadDashboardTab( tab, options ) {
		var $shell = $( '#tves-dashboard-content' );
		var currentUrl = new window.URL( window.location.href );
		var requestData;
		options = options || {};
		if ( ! $shell.length || 'undefined' === typeof tvesAdmin ) { return; }
		if ( dashboardRequest ) { dashboardRequest.abort(); }
		window.clearTimeout( syncTimer );
		$shell.addClass( 'is-loading' ).attr( 'aria-busy', 'true' );
		$shell.find( '.tves-dashboard-error' ).prop( 'hidden', true ).empty();
		$( '.tves-dashboard-tabs a' ).attr( 'aria-disabled', 'true' );
		requestData = { action: 'tves_load_dashboard_tab', nonce: tvesAdmin.dashboardNonce, tab: tab };
		if ( 'logs' === tab ) {
			requestData.status = currentUrl.searchParams.get( 'status' ) || '';
			requestData.paged = currentUrl.searchParams.get( 'paged' ) || 1;
		}
		dashboardRequest = $.post( tvesAdmin.ajaxUrl, requestData, null, 'json' ).done( function ( response ) {
			if ( ! response.success || ! response.data || 'undefined' === typeof response.data.html ) {
				$shell.find( '.tves-dashboard-error' ).text( tvesAdmin.tabError ).prop( 'hidden', false ); return;
			}
			$shell.find( '.tves-dashboard-view' ).html( response.data.html );
			$shell.attr( 'data-tab', response.data.tab );
			activateTab( response.data.tab );
			initializeTab( response.data.tab );
			if ( options.history ) { window.history.pushState( { tvesTab: response.data.tab }, '', dashboardUrl( response.data.tab ).toString() ); }
		} ).fail( function ( xhr, textStatus ) {
			if ( 'abort' !== textStatus ) { $shell.find( '.tves-dashboard-error' ).text( tvesAdmin.tabError ).prop( 'hidden', false ); }
		} ).always( function () {
			$shell.removeClass( 'is-loading' ).attr( 'aria-busy', 'false' );
			$( '.tves-dashboard-tabs a' ).removeAttr( 'aria-disabled' );
			dashboardRequest = null;
		} );
	}

	function setLogHistory( status, paged ) {
		var url = dashboardUrl( 'logs' );
		if ( status ) { url.searchParams.set( 'status', status ); }
		if ( paged > 1 ) { url.searchParams.set( 'paged', paged ); }
		window.history.pushState( { tvesTab: 'logs', status: status, paged: paged }, '', url.toString() );
	}

	function updateLogControls( data ) {
		var status = data.status || '';
		$( '#tves-log-results' ).attr( 'data-status', status ).attr( 'data-paged', data.paged || 1 );
		$( '#tves-status-filter' ).val( status );
		$( '.tves-log-export input[name="status"]' ).val( status );
		$( '.tves-log-stat' ).removeClass( 'is-active' ).filter( function () { return String( $( this ).data( 'status' ) || '' ) === status; } ).addClass( 'is-active' );
		if ( data.counts ) { $.each( data.counts, function ( key, value ) { $( '[data-log-count="' + key + '"]' ).text( Number( value ).toLocaleString() ); } ); }
	}

	function setLogLoading( loading ) {
		$( '#tves-log-results' ).toggleClass( 'is-loading', loading ).attr( 'aria-busy', loading ? 'true' : 'false' );
		$( '#tves-refresh-logs' ).prop( 'disabled', loading ).toggleClass( 'is-loading', loading );
		$( '#tves-clear-logs, .tves-log-filter :input' ).prop( 'disabled', loading );
	}

	function showLogError( message ) { $( '#tves-log-error' ).text( message ).prop( 'hidden', false ); }
	function showLogNotice( message ) { $( '#tves-log-notice' ).text( message ).prop( 'hidden', false ); }

	function loadLogs( status, paged, options ) {
		var $results = $( '#tves-log-results' );
		options = options || {};
		if ( ! $results.length ) { return; }
		status = status || ''; paged = Math.max( 1, parseInt( paged, 10 ) || 1 );
		if ( logRequest ) { logRequest.abort(); }
		$( '#tves-log-error, #tves-log-notice' ).prop( 'hidden', true ).empty(); setLogLoading( true );
		logRequest = $.post( tvesAdmin.ajaxUrl, { action: 'tves_load_logs', nonce: tvesAdmin.logsNonce, status: status, paged: paged }, null, 'json' ).done( function ( response ) {
			if ( ! response.success || ! response.data || 'undefined' === typeof response.data.html ) { showLogError( tvesAdmin.logsError ); return; }
			$( '#tves-log-results-content' ).html( response.data.html ); updateLogControls( response.data );
			if ( options.history ) { setLogHistory( response.data.status, response.data.paged ); }
			if ( options.scroll ) { $results.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
		} ).fail( function ( xhr, textStatus ) { if ( 'abort' !== textStatus ) { showLogError( tvesAdmin.logsError ); } } ).always( function () { setLogLoading( false ); logRequest = null; } );
	}

	$( function () {
		initializeTab( String( $( '#tves-dashboard-content' ).data( 'tab' ) || 'overview' ) );
		$( document ).on( 'click', '.tves-dashboard-tabs a', function ( event ) { event.preventDefault(); loadDashboardTab( String( $( this ).data( 'tab' ) ), { history: true } ); } );
		$( document ).on( 'change', '#tves-title-format', toggleTemplate );
		$( document ).on( 'click', '.tves-copy-button', function () { copyEndpoint( this ); } );
		$( document ).on( 'input', '#tves-category-search', function () { filterCategories( this.value ); } );
		$( document ).on( 'change', '.tves-exclusion-control select.wc-product-search', function () { renderSelectedExclusions( this ); } );
		$( document ).on( 'click', '.tves-remove-exclusion', function () {
			var $panel = $( this ).closest( '.tves-selected-exclusions' );
			var $select = $( '#' + String( $panel.data( 'select-id' ) || '' ) );
			var value = String( $( this ).data( 'value' ) || '' );
			$select.find( 'option' ).filter( function () { return String( this.value ) === value; } ).prop( 'selected', false );
			$select.trigger( 'change' );
		} );
		$( document ).on( 'submit', '.tves-manual-sync', function () { return window.confirm( tvesAdmin.confirmSync ); } );
		$( document ).on( 'click', '.tves-log-stat', function ( event ) { event.preventDefault(); loadLogs( String( $( this ).data( 'status' ) || '' ), 1, { history: true } ); } );
		$( document ).on( 'submit', '.tves-log-filter', function ( event ) { event.preventDefault(); loadLogs( String( $( '#tves-status-filter' ).val() || '' ), 1, { history: true } ); } );
		$( document ).on( 'change', '#tves-status-filter', function () { loadLogs( String( this.value || '' ), 1, { history: true } ); } );
		$( document ).on( 'click', '#tves-refresh-logs', function () { var $r = $( '#tves-log-results' ); loadLogs( String( $r.attr( 'data-status' ) || '' ), $r.attr( 'data-paged' ) || 1, {} ); } );
		$( document ).on( 'click', '#tves-clear-logs', function () {
			var status = String( $( '#tves-log-results' ).attr( 'data-status' ) || '' );
			if ( ! window.confirm( tvesAdmin.confirmClearLogs ) ) { return; }
			setLogLoading( true );
			$.post( tvesAdmin.ajaxUrl, { action: 'tves_clear_logs', nonce: tvesAdmin.clearLogsNonce, status: status }, null, 'json' ).done( function ( response ) {
				if ( ! response.success || ! response.data ) { showLogError( tvesAdmin.clearLogsError ); return; }
				$( '#tves-log-results-content' ).html( response.data.html ); updateLogControls( response.data ); setLogHistory( response.data.status, 1 ); showLogNotice( response.data.message );
			} ).fail( function () { showLogError( tvesAdmin.clearLogsError ); } ).always( function () { setLogLoading( false ); } );
		} );
		$( document ).on( 'click', '.tves-log-pagination a', function ( event ) { event.preventDefault(); var url = new window.URL( this.href, window.location.href ); loadLogs( String( $( '#tves-log-results' ).attr( 'data-status' ) || '' ), url.searchParams.get( 'paged' ) || 1, { history: true, scroll: true } ); } );
		$( window ).on( 'popstate', function () {
			var url = new window.URL( window.location.href ); var tab = url.searchParams.get( 'tab' ) || 'overview';
			if ( String( $( '#tves-dashboard-content' ).data( 'tab' ) ) !== tab ) { loadDashboardTab( tab, {} ); }
			else if ( 'logs' === tab ) { loadLogs( url.searchParams.get( 'status' ) || '', url.searchParams.get( 'paged' ) || 1, {} ); }
		} );
	} );
}( jQuery ) );
