<?php
// Extension: ocs_credit. Admin strings, en-gb. Owner: SPEC-036.

$_['heading_title']     = 'OCS Instalments';

$_['text_extension']    = 'Extensions';
$_['text_success']      = 'Success: instalment settings saved!';
$_['text_edit']         = 'Edit Instalments';

$_['entry_status']      = 'Status';
$_['entry_disclaimer']  = 'Disclaimer (empty = default text)';
$_['entry_terms']       = 'Terms (HTML, opens from the Details link)';

$_['column_bank']       = 'Bank / partner';
$_['column_months']     = 'Months';
$_['column_rate']       = 'Rate, % per year (0 = no interest)';
$_['column_fee']        = 'Commission, % of price';
$_['column_min']        = 'Price from (default currency)';
$_['column_max']        = 'Price up to (0 = no limit)';

$_['help_plans']        = 'The values are shown to visitors as an estimate: payment = (price + commission) amortised over the months at the yearly rate. Empty rows are ignored.';

$_['error_permission']  = 'Warning: You do not have permission to modify instalments!';
$_['error_bank']        = 'Enter the bank name (up to 64 characters) for every plan!';
$_['error_months']      = 'Months must be a whole number from 1 to 120!';
$_['error_rate']        = 'Rate must be a number from 0 to 100!';
$_['error_fee']         = 'Commission must be a number from 0 to 50!';
$_['error_min']         = '"Price from" must be a positive number!';
$_['error_max']         = '"Price up to" must be a positive number!';
$_['error_range']       = '"Price up to" must not be less than "Price from"!';
$_['error_count']       = 'No more than 12 plans!';
