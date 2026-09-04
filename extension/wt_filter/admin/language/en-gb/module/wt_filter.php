<?php
// Heading
$_['heading_title']            = 'WT Filter';

// Text
$_['text_extension']           = 'Extensions';
$_['text_success']             = 'Success: You have modified WT Filter module!';
$_['text_edit']                = 'Edit WT Filter Module';
$_['text_enabled']             = 'Enabled';
$_['text_disabled']            = 'Disabled';
$_['text_default']             = 'Default';
$_['text_yes']                 = 'Yes';
$_['text_loading']             = 'Loading...';
$_['text_complete']            = 'Complete';
$_['text_progress_truncate']   = 'Clearing existing filters...';
$_['text_progress_option']     = 'Copying product options...';
$_['text_progress_filter']     = 'Copying standard filters...';
$_['text_progress_attribute']  = 'Copying attributes...';
$_['text_progress_finalize']   = 'Linking categories and keywords...';
$_['text_complete_update']     = 'Update complete';
$_['text_catalog_list']        = 'Open filter options list';

// Tabs
$_['tab_general']              = 'General';
$_['tab_copy']                 = 'Copy / Update';
$_['tab_seo']                  = 'SEO Pages';

// SEO Pages
$_['text_seo_list']            = 'Landing pages';
$_['text_seo_empty']           = 'No SEO landing pages yet.';
$_['text_seo_saved']           = 'SEO page saved.';
$_['text_seo_deleted']         = 'SEO page deleted.';
$_['text_seo_confirm_delete']  = 'Delete this SEO page?';
$_['text_seo_add']             = 'Add SEO page';
$_['text_seo_edit']            = 'Edit SEO page';
$_['column_seo_url']           = 'URL';
$_['column_seo_h1']            = 'H1';
$_['column_seo_status']        = 'Status';
$_['column_seo_action']        = 'Action';
$_['entry_seo_url']            = 'Filter URL';
$_['entry_seo_h1']             = 'H1 / Breadcrumb';
$_['entry_seo_meta_title']     = 'Meta Title';
$_['entry_seo_meta_description'] = 'Meta Description';
$_['entry_seo_meta_keyword']   = 'Meta Keywords';
$_['entry_seo_description']    = 'SEO text';
$_['help_seo_url']             = 'Paste a URL that includes a category or brand plus the filter. Examples: https://site.com/shoes/size:41/ , https://site.com/adidas/color:red/ , or http://site.com/index.php?route=product/category&path=10_11&filter_wt_filter=size:39';
$_['button_seo_add']           = 'Add page';
$_['button_seo_import']        = 'Bulk import';
$_['button_seo_import_run']    = 'Import';
$_['text_seo_import']          = 'Bulk import SEO pages';
$_['help_seo_import']          = 'Paste links one per line (Enter). H1 and Title are filled automatically from category/brand and filters. Duplicates are skipped.';
$_['text_seo_import_done']     = 'Done: created %s, skipped %s, errors %s.';
$_['text_seo_import_created']  = 'Created';
$_['text_seo_import_skipped']  = 'Skipped';
$_['text_seo_import_error']    = 'Error';
$_['error_seo_import_empty']   = 'Paste at least one link.';
$_['error_seo_import_limit']   = 'You can import at most 200 links at once.';
$_['text_seo_preview']         = 'URL preview';
$_['text_seo_preview_empty']   = 'Paste a filter URL to see the parsed preview.';
$_['text_seo_scope_category']  = 'Category';
$_['text_seo_scope_manufacturer'] = 'Manufacturer';
$_['text_seo_open_site']       = 'Open on site';
$_['text_seo_facet_price']     = 'Price';
$_['text_seo_facet_manufacturer'] = 'Manufacturer';
$_['text_seo_facet_stock']     = 'Availability';
$_['text_seo_facet_stock_in']  = 'In stock';
$_['text_seo_facet_stock_out'] = 'Out of stock';
$_['text_seo_facet_discount']  = 'Sale';
$_['text_seo_facet_discount_yes'] = 'On sale';
$_['error_seo_url']            = 'Enter a valid filter URL.';
$_['error_seo_filter']         = 'Could not parse filter params from URL.';
$_['error_seo_category']       = 'The URL has no category or manufacturer. Use a path like /shoes/.../ or path=10_11 / manufacturer_id=… — not a filter segment alone.';
$_['error_seo_h1']             = 'H1 is required.';
$_['error_seo_not_found']      = 'SEO page not found.';
$_['error_seo_duplicate']      = 'Landing page #%s already exists (%s). Change the URL or edit the existing page.';

// Entry
$_['entry_status']             = 'Status';
$_['entry_store']              = 'Store';
$_['entry_type']               = 'Filter type (for new items only)';
$_['entry_attribute_separator']= 'Attribute separator';
$_['entry_price_special']      = 'Price in filter';
$_['entry_discount']           = 'Sale filter';
$_['entry_group_expanded']     = 'Group state';
$_['column_group_name']        = 'Group';
$_['column_expanded_desktop']  = 'Desk';
$_['column_expanded_mobile']   = 'Mob';
$_['text_group_price']         = 'Price';
$_['text_group_manufacturer']  = 'Manufacturer';
$_['text_group_stock']         = 'Stock';
$_['text_group_discount']      = 'Specials';
$_['help_group_expanded']      = 'Whether the group is open by default. On = open. Product options are set separately in the WT Filter list.';
$_['entry_accent_color']       = 'Accent color';
$_['help_accent_color']        = 'HEX color (#229ac8) for chevrons, spinner, price slider, Apply filter button and scrollbar.';
$_['entry_show_counts']        = 'Counts next to options';
$_['help_show_counts']         = 'Show (N) next to filter values. If disabled, COUNT queries are skipped and the filter is faster.';
$_['entry_seo_text_position']  = 'SEO text on page';
$_['text_seo_text_above']      = 'Above products';
$_['text_seo_text_below']      = 'Below products';
$_['help_seo_text_position']   = 'Where to show landing SEO text: above the product grid or below it.';
$_['entry_copy_truncate']      = 'Clear existing WT Filter data';
$_['entry_copy_attribute']     = 'Copy attributes';
$_['entry_copy_filter']        = 'Copy standard filters';
$_['entry_copy_option']        = 'Copy product options';

$_['text_price_special_yes']   = 'Use special (sale) price';
$_['text_price_special_no']    = 'Use base price (ignore special)';

// Help
$_['help_attribute_separator'] = 'Split attribute text into several values. Default: comma (,). Leave empty to disable.';
$_['help_price_special']       = 'When enabled, the price slider/filter uses the special (sale) price; otherwise the product base price.';
$_['help_discount']            = 'Show the Sale block in the filter (products with an active Special), when such products exist.';
$_['help_copy_btn']            = 'Use this if you are loading options/attributes into the filter for the first time. Check the sources above, then click Copy.';
$_['help_update_btn']          = 'Use this if the filter already has options/attributes and you added new ones in OpenCart. Click Update — it pulls only new data and keeps your types and images.';
$_['help_copy']                = 'Optional: enable “Clear existing data” only when you want a full rebuild from scratch.';

// Button
$_['button_copy']              = 'Copy';
$_['button_update']            = 'Update';

// Error
$_['error_permission']         = 'Warning: You do not have permission to modify WT Filter module!';
$_['error_copy_store']         = 'Please select at least one store.';
$_['error_copy_type']          = 'Please select filter type.';
$_['error_copy_step']          = 'Invalid copy step.';
$_['error_copy_source']        = 'Please select at least one source: options, filters or attributes.';
