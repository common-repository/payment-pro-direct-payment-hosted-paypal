<?php

$order = new WC_Order( $order_id );

?>

<iframe name="hss_iframe" id="pppro_iframe" border="0" style="border:none" width="100%" height="0"></iframe>
<form style="display:none" target="hss_iframe" name="form_iframe"
	method="post"
	<?php
		if ( $this->enable_sandbox == '1' ) {
			echo 'action="https://securepayments.sandbox.paypal.com/acquiringweb">';
		} else {
			echo 'action="https://securepayments.paypal.com/cgi-bin/acquiringweb">';
		}
	?>
	<input type="hidden" name="cmd" value="_hosted-payment">
	<input type="hidden" name="lc" value="<?php echo strtoupper( substr( get_bloginfo ( 'language' ), 0, 2 ) ); ?>">
	
	<input type="hidden" name="buyer_email" value="<?php echo $order->get_billing_email(); ?>">
	<input type="hidden" name="email" value="<?php echo $order->get_billing_email(); ?>">
	<input type="hidden" name="billing_first_name" value="<?php echo $order->get_billing_first_name(); ?>">
	<input type="hidden" name="billing_last_name" value="<?php echo $order->get_billing_last_name(); ?>">
	<input type="hidden" name="billing_address1" value="<?php echo $order->get_billing_address_1(); ?>">
	<input type="hidden" name="billing_address2" value="<?php echo $order->get_billing_address_2(); ?>">
	<input type="hidden" name="billing_city" value="<?php echo $order->get_billing_city(); ?>">
	<input type="hidden" name="billing_country" value="<?php echo $order->get_billing_country(); ?>">
	<input type="hidden" name="billing_state" value="<?php echo $order->get_billing_state(); ?>">
	<input type="hidden" name="billing_zip" value="<?php echo $order->get_billing_postcode(); ?>">
	
	<input type="hidden" name="first_name" value="<?php echo $order->get_shipping_first_name(); ?>">
	<input type="hidden" name="last_name" value="<?php echo $order->get_shipping_last_name(); ?>">
	<input type="hidden" name="address1" value="<?php echo $order->get_shipping_address_1(); ?>">
	<input type="hidden" name="address2" value="<?php echo $order->get_shipping_address_2(); ?>">
	<input type="hidden" name="city" value="<?php echo $order->get_shipping_city(); ?>">
	<input type="hidden" name="country" value="<?php echo $order->get_shipping_country(); ?>">
	<input type="hidden" name="state" value="<?php echo $order->get_shipping_state(); ?>">
	<input type="hidden" name="zip" value="<?php echo $order->get_shipping_postcode(); ?>">
	
	<input type="hidden" name="currency_code" value="<?php echo get_option('woocommerce_currency'); ?>">
	<input type="hidden" name="subtotal" value="<?php echo number_format( $order->get_subtotal(), 2, '.', '' ); ?>">
	<input type="hidden" name="shipping" value="<?php echo number_format( $order->get_shipping_total(), 2, '.', '' ); ?>">
	<input type="hidden" name="tax" value="<?php echo number_format( $order->get_total_tax(), 2, '.', '' ); ?>">
	
	<input type="hidden" name="business" value="<?php echo $this->account_email; ?>">
	<input type="hidden" name="paymentaction" value="<?php if ( $this->transaction_type ) { echo 'authorization'; } else { echo 'sale'; } ?>">
	
	<input type="hidden" name="template" value="minilayout">
	<?php
		if ( $this->pay_button_background_color ):
	?>
	<input type="hidden" name="pageButtonBgColor" value="<?php echo $this->pay_button_background_color; ?>">
	<?php
		endif;
	?>
	<?php
		if ( $this->pay_button_text_color ):
	?>
	<input type="hidden" name="pageButtonTextColor" value="<?php echo $this->pay_button_text_color; ?>">
	<?php
		endif;
	?>
	<input type="hidden" name="showHostedThankyouPage" value="false">

	<input type="hidden" name="notify_url" value="<?php echo $this->get_returnUrl(); ?>&id_order=<?php echo $order->get_id(); ?>">
	<input type="hidden" name="cancel_return" value="<?php echo $this->get_returnUrl(); ?>&cancel_return=1">
	<input type="hidden" name="return" value="<?php echo $this->get_returnUrl(); ?>&id_order=<?php echo $order->get_id(); ?>">
	<input type="hidden" name="bn" value="PrestoChangeo_SP">
</form>
<script type="text/javascript">
	document.form_iframe.submit();
</script>
<style type="text/css">
	#pppro_iframe{
		height: 526px;
	}
</style>