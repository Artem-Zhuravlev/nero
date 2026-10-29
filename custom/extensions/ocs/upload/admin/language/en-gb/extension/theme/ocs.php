<?php
// Extension: ocs (theme). Admin strings, en-gb. Owner: SPEC-006.

// Heading
$_['heading_title']                    = 'Nero Store Theme';

// Text
$_['text_extension']                   = 'Extensions';
$_['text_success']                     = 'Success: You have modified the Nero theme!';
$_['text_edit']                        = 'Edit Nero Theme';
$_['text_general']                     = 'General';
$_['text_product']                     = 'Products';
$_['text_image']                       = 'Images';

// Entry
$_['entry_directory']                  = 'Theme Directory';
$_['entry_status']                     = 'Status';
$_['entry_product_limit']              = 'Default Items Per Page';
$_['entry_product_description_length'] = 'List Description Limit';
$_['entry_show_stock']                  = 'Show stock availability';
$_['entry_show_sku']                    = 'Show article number (SKU)';
$_['entry_font']                        = 'Font';
$_['text_font_missing']                 = 'files not uploaded';
$_['entry_image_thumb']                = 'Product Image Thumb Size (W x H)';
$_['entry_image_popup']                = 'Product Image Popup Size (W x H)';
$_['entry_image_category']             = 'Category Image Size (W x H)';
$_['entry_image_manufacturer']         = 'Manufacturer Image Size (W x H)';
$_['entry_image_product']              = 'Product Image List Size (W x H)';
$_['entry_image_additional']           = 'Additional Product Image Size (W x H)';
$_['entry_image_related']              = 'Related Product Image Size (W x H)';
$_['entry_image_compare']              = 'Compare Image Size (W x H)';
$_['entry_image_wishlist']             = 'Wish List Image Size (W x H)';
$_['entry_image_cart']                 = 'Cart Image Size (W x H)';
$_['entry_image_location']             = 'Store Image Size (W x H)';
$_['entry_width']                      = 'Width';
$_['entry_height']                     = 'Height';

// Help
$_['help_directory']                   = 'Theme directory used for the image size settings; keep "ocs".';
$_['help_product_limit']               = 'How many catalog items are shown per page.';
$_['help_product_description_length']  = 'Short description character limit in list views.';
$_['help_show_stock']                   = 'Shows the "in stock" label on every page, block, list and search suggestion; off removes it everywhere.';
$_['help_show_sku']                     = 'Shows the article number (model) on every page, block, list, table and search suggestion; off removes it everywhere.';
$_['help_font']                         = 'Site-wide font. Montserrat, Inter, Manrope, Onest, Golos Text and Nunito Sans ship with the theme; Gilroy, Proxima Nova and Geometria become available once licensed woff2 files are added to catalog/view/theme/ocs/fonts/.';

// Error
$_['error_permission']                 = 'Warning: You do not have permission to modify the Nero theme!';
$_['error_limit']                      = 'Value required!';
$_['error_image']                      = 'Image dimensions required!';

// Contacts (SPEC-047)
$_['text_contacts'] = 'Contacts (footer and header)';
$_['entry_address'] = 'Address';
$_['entry_hours'] = 'Working hours';
$_['entry_phones'] = 'Phones';
$_['help_phones'] = 'One per line: number or "number | label". Numbers become tel: links.';
$_['error_contact_link'] = 'A messenger / social link was not saved: enter a valid URL (https://..., tg://, viber://, mailto:, tel:).';
$_['text_contact_links'] = 'Messengers and social networks';
$_['help_contact_links'] = 'Shown in the footer (and messengers in the header). Choose an icon, a type and enter a URL (https://, tg://, viber://, mailto:, tel:).';
$_['column_icon'] = 'Icon';
$_['column_kind'] = 'Type';
$_['column_title'] = 'Title (per language)';
$_['column_url'] = 'URL';
$_['column_sort'] = 'Sort';
$_['text_kind_messenger'] = 'Messenger';
$_['text_kind_social'] = 'Social network';
$_['button_add'] = 'Add';
$_['button_remove'] = 'Remove';

// Info blocks (SPEC-048)
$_['text_info_blocks'] = 'Info blocks of the product page';
$_['help_info_blocks'] = 'Shown once for every product under the buy form (delivery, returns, payment ...). A row needs a title or a text; the link is optional. Up to 10 blocks.';
$_['column_text'] = 'Text (per language)';

// Top menu (SPEC-050)
$_['text_top_menu'] = 'Top menu (header)';
$_['help_top_menu'] = 'Shown in the top bar: Sale, Blog and a dropdown with the chosen information pages and Contacts.';
$_['entry_top_enabled'] = 'Top menu';
$_['entry_top_title'] = 'Dropdown title (per language)';
$_['entry_top_items'] = 'Items';
$_['text_top_sale'] = 'Sale (highlighted)';
$_['text_top_blog'] = 'Blog';
$_['text_top_contacts'] = 'Contacts (in the dropdown)';
$_['entry_top_pages'] = 'Information pages';
$_['column_show'] = 'Show';

// Footer (SPEC-072)
$_['text_footer'] = 'Footer';
$_['help_footer'] = 'Ticked links are shown in the footer, unticked ones are hidden. A column without links disappears by itself.';
$_['text_footer_information'] = 'Information';
$_['text_footer_service'] = 'Customer service';
$_['text_footer_extra'] = 'Extras';
$_['text_footer_contact'] = 'Contact us';
$_['text_footer_return'] = 'Returns';
$_['text_footer_sitemap'] = 'Site map';
$_['text_footer_manufacturer'] = 'Brands';
$_['text_footer_voucher'] = 'Gift certificates';
$_['text_footer_affiliate'] = 'Affiliate programme';
$_['text_footer_special'] = 'Specials';
$_['text_footer_account'] = 'My account';
$_['text_footer_order'] = 'Order history';
$_['text_footer_wishlist'] = 'Wishlist';
$_['text_footer_newsletter'] = 'Newsletter';
$_['entry_footer_payments'] = 'Visa and Mastercard logos';

// Structured data (SPEC-080)
$_['text_schema']           = 'Structured data (schema.org)';
$_['entry_schema_status']   = 'Structured data (schema.org)';
$_['entry_schema_org_name'] = 'Organization name';
$_['entry_schema_org_logo'] = 'Organization logo';
$_['help_schema_status']    = 'Prints schema.org JSON-LD in the head of every page (organization, breadcrumbs, products, reviews, lists, articles) for search engines.';
$_['help_schema_org_name']  = 'Shown to search engines as the organization name; empty = store name.';
$_['help_schema_org_logo']  = 'Path of the logo inside the image directory, for example catalog/logo.png; empty = store logo.';

// Tabs and setup hints (SPEC-123)
$_['tab_general']       = 'General';
$_['tab_contacts']      = 'Contacts';
$_['tab_menu']          = 'Menu';
$_['tab_footer']        = 'Footer';
$_['tab_blocks']        = 'Blocks';
$_['tab_seo']           = 'SEO';
$_['tab_images']        = 'Images';
$_['text_setup']        = 'Setup';
$_['text_setup_pending'] = 'the Nero tables, settings and modules are not installed yet.';
