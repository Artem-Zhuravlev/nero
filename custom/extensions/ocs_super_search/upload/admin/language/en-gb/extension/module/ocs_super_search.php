<?php
// Extension: ocs_super_search. Admin strings, en-gb. Owner: SPEC-079.

$_['heading_title']       = 'Nero Super Search';

$_['text_extension']      = 'Extensions';
$_['text_success']        = 'Success: super search settings saved!';
$_['text_ignored']        = 'Ignored synonym lines (no "="): %s';
$_['text_edit']           = 'Edit Super Search';
$_['text_index']          = 'Search index';
$_['text_built']          = 'Index built:';
$_['text_tokens']         = 'tokens:';
$_['text_rebuilt']        = 'Index rebuilt.';
$_['text_not_built']      = 'The index has not been built yet: build it before enabling the module (until then the standard search is used).';

$_['entry_status']        = 'Status';
$_['entry_min_length']    = 'Minimum query length';
$_['entry_layout']        = 'Wrong keyboard layout';
$_['entry_translit']      = 'Transliteration';
$_['entry_typo']          = 'One typo';
$_['entry_cache_ttl']     = 'Result cache, seconds';
$_['entry_synonyms']      = 'Synonyms';

$_['button_rebuild']      = 'Rebuild index';

$_['placeholder_synonyms'] = "clothes=garments\nsocks=hosiery, shoes=footwear";

$_['help_layout']         = 'A query typed on the wrong layout is also searched as it would read on the other one (ghbdtn = привет).';
$_['help_translit']       = 'Latin and Cyrillic spellings of one word match each other (apple, эпл, аппле).';
$_['help_typo']           = 'One mistake is tolerated in words of 4+ letters (aple, одиг).';
$_['help_cache_ttl']      = 'How long the answer to an identical query is reused. 0 disables the cache.';
$_['help_synonyms']       = 'Words joined with "=" mean the same, both ways: "одяг=вбрання". Separate groups with a new line or a comma: "одяг=вбрання,шкарпетки=гольфи". A group may have several words and short phrases ("верхній одяг"). Applies at once, no rebuild needed.';
$_['help_rebuild']        = 'Products are re-indexed automatically when saved in the admin. Rebuild after bulk imports, direct database edits, or renaming categories and brands. It runs in batches and is safe to repeat.';

$_['error_permission']    = 'Warning: You do not have permission to modify super search!';
$_['error_values']        = 'Minimum length must be 1-5 and cache time 0-86400 seconds!';
$_['error_rebuild']       = 'The index could not be rebuilt, see the error log.';
