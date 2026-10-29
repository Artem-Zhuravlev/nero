<?php
// Extension: ocs_preorder. Admin strings, en-gb. Owner: SPEC-025.

$_['heading_title']      = 'Nero Pre-order';
$_['heading_requests']   = 'Pre-order requests';

$_['text_extension']     = 'Extensions';
$_['text_success']       = 'Success: pre-order settings saved!';
$_['text_saved']         = 'Saved.';
$_['text_edit']          = 'Edit Pre-order';
$_['text_off']           = 'Hidden';
$_['text_optional']      = 'Optional';
$_['text_required']      = 'Required';
$_['text_per_language']  = 'Texts per language';
$_['text_new']           = 'New';
$_['text_processed']     = 'Processed';
$_['text_cancelled']     = 'Cancelled';
$_['text_confirm']       = 'Delete this request?';
$_['text_no_results']    = 'No requests yet.';

$_['entry_status']       = 'Status';
$_['entry_global']       = 'Out-of-stock products';
$_['entry_overrides']    = 'Per-product overrides';
$_['entry_name']         = 'Name field';
$_['entry_phone']        = 'Phone field';
$_['entry_email']        = 'E-mail field';
$_['entry_comment']      = 'Comment field';
$_['entry_notify_admin'] = 'E-mail the administrator';
$_['entry_create_order'] = 'Also create an order';
$_['entry_order_status'] = 'Order status for pre-orders';
$_['entry_button']       = 'Button text';
$_['entry_message']      = 'Message to the customer';

$_['column_date']        = 'Date';
$_['column_product']     = 'Product';
$_['column_contact']     = 'Contact';
$_['column_comment']     = 'Comment';
$_['column_expected']    = 'Expected';
$_['column_order']       = 'Order';
$_['column_status']      = 'Status';

$_['help_global']        = 'On: every product with zero stock gets the pre-order button. Off: only products forced on in the overrides.';
$_['help_overrides']     = 'One line per product: product_id|on or off|expected date YYYY-MM-DD (optional). "on" shows the button for that product when its stock is zero, "off" never shows it.';
$_['help_create_order']  = 'Creates a normal order in the status below next to the request. Stock is never subtracted.';
$_['help_order_status']  = 'Must not be a processing or complete status (core subtracts stock for those); otherwise no order is created.';

$_['error_permission']   = 'Warning: You do not have permission to modify pre-orders!';
$_['error_values']       = 'Invalid field mode!';
$_['error_phone']        = 'The phone field cannot be hidden!';
