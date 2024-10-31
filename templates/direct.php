<?php

$countries_obj   = new WC_Countries();
$countries = $countries_obj->__get('countries');
// Get default country
$default_country = $countries_obj->get_base_country();
// Get states in default country
$states = $countries_obj->get_states( $default_country );
echo '<p class="ppp-title">' . __( 'Billing Information - We Accept:', 'woocommerce-gateway-paypal-pro' ) . '<img src="'.$this->icon.'"></p>';

?>

<fieldset id="<?php echo $this->id; ?>-cc-form">
	<input type="hidden" name="confirm" value="1">
	<div class="form-row form-row-first">
		<label for="pppro_cc_fname"><?php echo __( 'First name:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<input type="text" name="pppro_cc_fname" id="pppro_cc_fname">
	</div>
	<div class="form-row form-row-last">
		<label for="pppro_cc_lname"><?php echo __( 'Last name:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<input type="text" name="pppro_cc_lname" id="pppro_cc_lname">
	</div>
	<?php
		if ( $this->require_address == 'yes' ):
	?>
	<div class="form-row form-row-wide">
		<label for="pppro_cc_address"><?php echo __( 'Address:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<input type="text" name="pppro_cc_address" id="pppro_cc_address">
	</div>
	<div class="form-row form-row-first">
		<label for="pppro_cc_city"><?php echo __( 'City:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<input type="text" name="pppro_cc_city" id="pppro_cc_city">
	</div>
	<div class="form-row form-row-last">
		<label for="pppro_cc_zip"><?php echo __( 'Zipcode:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<input type="text" name="pppro_cc_zip" id="pppro_cc_zip">
	</div>
	<div class="form-row form-row-first">
		<label for="pppro_country"><?php echo __( 'Country:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<select name="pppro_country" id="pppro_country">
			<?php foreach ($countries as $code => $country): ?>
				<option value="<?php echo $code; ?>" <?php selected( $code, $default_country ); ?> ><?php echo $country; ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="form-row form-row-last">
		<label for="pppro_state"><?php echo __( 'State:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<select name="pppro_state" id="pppro_state">
			
		</select>
	</div>
	<script type="text/javascript">
		jQuery( function( $ ) {
			var states_json       = wc_country_select_params.countries.replace( /&quot;/g, '"' ),
			states = $.parseJSON( states_json );

			$( document.body ).on( 'change refresh', 'select#pppro_country', function() {
				$wrapper = $( this ).closest('.form-row').parent();

				var country     = $( this ).val(),
				$statebox   = $wrapper.find( '#pppro_state' ),
				$parent     = $statebox.closest( '.form-row' ),
				input_name  = $statebox.attr( 'name' ),
				input_id    = $statebox.attr( 'id' ),
				value       = $statebox.val(),
				placeholder = $statebox.attr( 'placeholder' ) || $statebox.attr( 'data-placeholder' ) || '',
				$newstate;

				if ( states[ country ] ) {
					if ( $.isEmptyObject( states[ country ] ) ) {
						$newstate = $( '<input type="hidden" />' )
							.prop( 'id', input_id )
							.prop( 'name', input_name )
							.prop( 'placeholder', placeholder )
							.addClass( 'hidden' );
						$parent.hide().find( '.select2-container' ).remove();
						$statebox.replaceWith( $newstate );
						$( document.body ).trigger( 'country_to_state_changed', [ country, $wrapper ] );
					} else {
						var state          = states[ country ],
							$defaultOption = $( '<option value=""></option>' ).text( wc_country_select_params.i18n_select_state_text );

						if ( ! placeholder ) {
							placeholder = wc_country_select_params.i18n_select_state_text;
						}

						$parent.show();

						if ( $statebox.is( 'input' ) ) {
							$newstate = $( '<select></select>' )
								.prop( 'id', input_id )
								.prop( 'name', input_name )
								.data( 'placeholder', placeholder )
								.addClass( 'state_select' );
							$statebox.replaceWith( $newstate );
							$statebox = $wrapper.find( '#pppro_state' );
						}

						$statebox.empty().append( $defaultOption );

						$.each( state, function( index ) {
							var $option = $( '<option></option>' )
								.prop( 'value', index )
								.text( state[ index ] );
							$statebox.append( $option );
						} );

						$statebox.val( value ).change();

						$( document.body ).trigger( 'country_to_state_changed', [country, $wrapper ] );
					}
				} else {
					if ( $statebox.is( 'select, input[type="hidden"]' ) ) {
						$newstate = $( '<input type="text" />' )
							.prop( 'id', input_id )
							.prop( 'name', input_name )
							.prop( 'placeholder', placeholder )
							.addClass( 'input-text' );
						$parent.show().find( '.select2-container' ).remove();
						$statebox.replaceWith( $newstate );
						$( document.body ).trigger( 'country_to_state_changed', [country, $wrapper ] );
					}
				}

				$( document.body ).trigger( 'country_to_state_changing', [country, $wrapper ] );
			});

			$( '#pppro_country' ).trigger( 'refresh' );
		});
	</script>
	<?php
		endif;
	?>
	<div class="form-row form-row-wide">
		<label for="pppro_cc_number"><?php echo __( 'Card Number:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<input type="text" name="pppro_cc_number" id="pppro_cc_number">
	</div>
	<div class="form-row form-row-first">
		<label for="pppro_cc_Month"><?php echo __( 'Expiration:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<select name="pppro_cc_Month" id="pppro_cc_Month">
			<?php
				for ( $i = 1; $i < 13; $i++ ) {
					$pi = $i < 10 ? '0' . $i : $i;
					?>
						<option value="<?php echo $pi; ?>"><?php echo $pi; ?></option>
					<?php
				}
			?>
		</select>
	</div>
	<div class="form-row form-row-last">
		<label for="pppro_cc_Year">&nbsp;</label>
		<select name="pppro_cc_Year" id="pppro_cc_Year">
			<?php
				for ( $i = date( 'Y' ); $i < date( 'Y' ) + 10; $i++ ) {
					?>
						<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
					<?php
				}
			?>
		</select>
	</div>
	<?php
		if ( $this->require_cvn == 'yes' ):
	?>
	<div class="form-row form-row-first">
		<label for="pppro_cc_Year"><?php echo __( 'CVV code:', 'woocommerce-gateway-paypal-pro' ) ?></label>
		<input type="text" name="pppro_cc_cvm" id="pppro_cc_cvm">
		<span class="form-caption"><?php echo __( '3-4 digit number from the back of your card.', 'woocommerce-gateway-paypal-pro' ) ?></span>
	</div>
	<?php
		endif;
	?>
</fieldset>

<style type="text/css">
	#poco_paypal_pro-cc-form input,
	.woocommerce #payment .form-row select{
		width: 100%;
		height: 2em;
	}
	#poco_paypal_pro-cc-form{
		padding-bottom: 1.5rem;
	}
	.ppp-title img{
		padding-left: 10px;
		display: inline-block;
		margin-bottom: -7px;
	}
	#poco_paypal_pro-cc-form .form-caption{
		font-size: 15px;
		display: block;
	}
	.woocommerce-checkout #payment #poco_paypal_pro-cc-form div.form-row{
		padding: 0;
	}
	.woocommerce-checkout #payment #poco_paypal_pro-cc-form .select2{
		width: 100% !important;
	}
</style>
<script type="text/javascript">
	jQuery( function( $ ) {
		$( '#pppro_country' ).select2();
		$( '#pppro_state' ).select2();
		$( '#pppro_cc_Month' ).select2();
		$( '#pppro_cc_Year' ).select2();
	});
</script>