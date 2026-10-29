<?php
// Extension: ocs_stickers. Admin strings, en-gb. Owner: SPEC-029.

$_['heading_title']       = 'Nero Stickers';
$_['heading_add']         = 'Add sticker';
$_['heading_edit']        = 'Edit sticker';

$_['text_extension']      = 'Extensions';
$_['text_success']        = 'Success: saved!';
$_['text_deleted']        = 'Sticker deleted.';
$_['text_settings']       = 'Settings';
$_['text_list']           = 'Stickers';
$_['text_confirm']        = 'Delete this sticker?';
$_['text_no_results']     = 'No stickers yet.';
$_['text_contrast']       = 'Saved, but the text/background contrast is only %s:1 (4.5:1 is recommended for readable labels).';
$_['text_pos_tl']         = 'Top left';
$_['text_pos_tr']         = 'Top right';
$_['text_pos_bl']         = 'Bottom left';
$_['text_rule_manual']    = 'Manual only';
$_['text_rule_new']       = 'Added within N days';
$_['text_rule_discount']  = 'Has a special price';
$_['text_rule_out_of_stock'] = 'Out of stock';
$_['text_rule_top_sales'] = 'Sold at least N units';

$_['entry_status']        = 'Status';
$_['entry_limit']         = 'Stickers per corner';
$_['entry_name']          = 'Text';
$_['entry_text_color']    = 'Text colour';
$_['entry_bg_color']      = 'Background colour';
$_['entry_position']      = 'Position';
$_['entry_priority']      = 'Priority';
$_['entry_rule']          = 'Automatic rule';
$_['entry_rule_value']    = 'N for the rule';
$_['entry_show_pct']      = 'Show -N% instead of the text';
$_['entry_category_id']   = 'Only in category ID';
$_['entry_manufacturer_id'] = 'Only for manufacturer ID';
$_['entry_products']      = 'Always on products (IDs)';

$_['column_name']         = 'Sticker';
$_['column_position']     = 'Position';
$_['column_priority']     = 'Priority';
$_['column_rule']         = 'Rule';
$_['column_assigned']     = 'Manual products';
$_['column_status']       = 'Status';

$_['help_limit']          = 'At most this many stickers are shown in one corner of a card; higher priority wins.';
$_['help_priority']       = 'Higher number = shown first and kept when the corner is full.';
$_['help_rule']           = 'Manual assignments always apply; the rule adds the sticker automatically.';
$_['help_value']          = 'Days for "added within N days", units for "sold at least N". For the special-price rule N is ignored.';
$_['help_scope']          = '0 = everywhere. A category or manufacturer limits the automatic rule only.';
$_['help_products']       = 'Product IDs separated by commas; replaces the manual list of this sticker. Individual products can also be changed on the product form tab.';

$_['error_permission']    = 'Warning: You do not have permission to modify stickers!';
$_['error_limit']         = 'Stickers per corner must be 1-5!';
$_['error_name']          = 'Enter the text (up to 32 characters) for every language!';
$_['error_values']        = 'Check the position, rule and numeric fields!';
$_['error_products']      = 'Product IDs must be numbers separated by commas!';
