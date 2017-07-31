jQuery(document).ready(function($) {
	function getProductsSelectFormatString() {
			return {
				'language': {
					errorLoading: function() {
						return wc_products_select_params.i18n_searching;
					},
					inputTooLong: function( args ) {
						var overChars = args.input.length - args.maximum;

						if ( 1 === overChars ) {
							return wc_products_select_params.i18n_input_too_long_1;
						}

						return wc_products_select_params.i18n_input_too_long_n.replace( '%qty%', overChars );
					},
					inputTooShort: function( args ) {
						var remainingChars = args.minimum - args.input.length;

						if ( 1 === remainingChars ) {
							return wc_products_select_params.i18n_input_too_short_1;
						}

						return wc_products_select_params.i18n_input_too_short_n.replace( '%qty%', remainingChars );
					},
					loadingMore: function() {
						return wc_products_select_params.i18n_load_more;
					},
					maximumSelected: function( args ) {
						if ( args.maximum === 1 ) {
							return wc_products_select_params.i18n_selection_too_long_1;
						}

						return wc_products_select_params.i18n_selection_too_long_n.replace( '%qty%', args.maximum );
					},
					noResults: function() {
						return wc_products_select_params.i18n_no_matches;
					},
					searching: function() {
						return wc_products_select_params.i18n_searching;
					}
				}
			};
		}
		
	jQuery( ':input.woo-orders-search-by-product' ).each( function() {
		var select2_args = {
			allowClear:  jQuery( this ).data( 'allow_clear' ) ? true : false,
			placeholder: jQuery( this ).data( 'placeholder' ),
			minimumInputLength: jQuery( this ).data( 'minimum_input_length' ) ? jQuery( this ).data( 'minimum_input_length' ) : '3',
			escapeMarkup: function( m ) {
			return m;
			},
			ajax: {
			url:         wc_products_select_params.ajax_url,
			dataType:    'json',
			delay:       250,
			data:        function( params ) {
			return {
			term:     params.term,
			action:   jQuery( this ).data( 'action' ) || 'search_woo_products',
			security: wc_products_select_params.search_woo_products_nonce,
			exclude:  jQuery( this ).data( 'exclude' ),
			include:  jQuery( this ).data( 'include' ),
			limit:    jQuery( this ).data( 'limit' )
			};
			},
			processResults: function( data ) {
			var terms = [];
			if ( data ) {
			$.each( data, function( id, text ) {
			terms.push( { id: id, text: text } );
			});
			}
			return {
			results: terms
			};
			},
			cache: true
			}
		};

		select2_args = $.extend( select2_args, getProductsSelectFormatString() );

		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );

		if ( jQuery( this ).data( 'sortable' ) ) {
			var $select = jQuery(this);
			var $list   = jQuery( this ).next( '.select2-container' ).find( 'ul.select2-selection__rendered' );

			$list.sortable({
			placeholder : 'ui-state-highlight select2-selection__choice',
			forcePlaceholderSize: true,
			items       : 'li:not(.select2-search__field)',
			tolerance   : 'pointer',
			stop: function() {
			jQuery( $list.find( '.select2-selection__choice' ).get().reverse() ).each( function() {
			var id     = jQuery( this ).data( 'data' ).id;
			var option = $select.find( 'option[value="' + id + '"]' )[0];
			$select.prepend( option );
			} );
			}
			});
		}
	});
});