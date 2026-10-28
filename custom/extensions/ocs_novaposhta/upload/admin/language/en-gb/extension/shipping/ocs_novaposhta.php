<?php
// Extension: ocs_novaposhta. Admin strings, en-gb. Owner: SPEC-085.

$_['heading_title']        = 'OCS: Nova Poshta';

$_['text_extension']       = 'Extensions';
$_['text_success']         = 'Nova Poshta settings saved';
$_['text_edit']            = 'Nova Poshta settings';
$_['text_directories']     = 'Directories (areas, cities, branches)';
$_['text_cron']            = 'Schedule (cron)';
$_['text_price']           = 'Price and conditions';
$_['text_checkout']        = 'Checkout form';
$_['text_sync']            = 'Loading from the API';
$_['text_regenerate']      = 'Generate a new cron token on save';
$_['text_key_set']         = 'Key is set — leave empty to keep it';
$_['text_all_zones']       = 'All zones';
$_['text_preset_default']  = 'Standard checkout of the theme';
$_['text_preset_simple']   = 'Simple Checkout';
$_['text_preset_custom']   = 'Own selectors (field below)';
$_['text_working']         = 'Loading…';
$_['text_partial']         = 'A portion is loaded, press the button again to continue';
$_['text_entity_areas']    = 'Areas';
$_['text_entity_cities']   = 'Cities';
$_['text_entity_warehouses'] = 'Branches and postomats';

$_['column_directory']     = 'Directory';
$_['column_status']        = 'Status';
$_['column_finished']      = 'Last full load';
$_['column_rows']          = 'Active / total';

$_['button_run']           = 'Load a portion';
$_['button_reset']         = 'Reset cursor';

$_['entry_status']         = 'Status';
$_['entry_api_key']        = 'Nova Poshta API key';
$_['entry_cron_token']     = 'Cron token';
$_['entry_cost']           = 'Delivery cost';
$_['entry_free_total']     = 'Free delivery from';
$_['entry_tax_class']      = 'Tax class';
$_['entry_geo_zone']       = 'Geo zone';
$_['entry_only_ua']        = 'Only for Ukraine';
$_['entry_require_point']  = 'Require a branch';
$_['entry_sort_order']     = 'Sort order';
$_['entry_title']          = 'Method name';
$_['entry_preset']         = 'Form type';
$_['entry_selectors']      = 'Own selectors';
$_['entry_batch_size']     = 'Page size';
$_['entry_pause_ms']       = 'Pause between pages, ms';

$_['help_directories']     = 'Every directory is loaded separately, in pages, and only changed rows are written. Records that disappear are deactivated, not deleted.';
$_['help_cron']            = 'Command line (recommended). One line per directory, at different times.';
$_['help_cron_http']       = 'No command line on the host? Call these addresses from an external cron service; repeat until the answer is "done".';
$_['help_api_key']         = 'Personal cabinet of Nova Poshta → Settings → Security → API keys. Never shown after saving.';
$_['help_free_total']      = 'Cart subtotal from which delivery is free. 0 disables it.';
$_['help_require_point']   = 'Yes: the method cannot be chosen until a branch or postomat is selected in the address field. No: it can (for example when JS is blocked).';
$_['help_title']           = 'Empty = the default name';
$_['help_selectors']       = 'One form per line: city | address | zone | country. CSS selectors, several may be separated with commas. Used with “Own selectors”.';
$_['help_batch_size']      = '10–500. Smaller pages put less load on the database.';
$_['help_pause_ms']        = 'Wait between API pages to spare the database and the API.';

$_['error_permission']     = 'You have no permission to change Nova Poshta settings!';
$_['error_form']           = 'Please check the form: there are errors!';
$_['error_key']            = 'Enter the API key before enabling the method.';
$_['error_entity']         = 'Unknown directory.';
$_['error_request']        = 'Request failed. Check the connection and the PHP error log.';
