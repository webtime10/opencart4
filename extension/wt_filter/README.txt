WT Filter for OpenCart 4.1.x
============================

Ported 1:1 from OpenCart 3 (opencart3.doc) feature set:
- Copy/update options/filters/attributes into wt_filter_* tables
- Storefront AJAX filter (price, brands search, stock, sale, counts toggle)
- Live product reindex via Events (add/edit/delete)
- SEO landing pages + bulk URL import
- SEO text above/below products

Install
-------
1. Admin → Extensions → Installer → upload/install extension folder or zip
2. Extensions → Modules → Install "WT Filter", then Edit → Save
3. Extensions → Modifications → Refresh
4. Design → Layouts → assign module "WT Filter" to category column
5. Clear cache, re-login

Notes
-----
- Package path: extension/wt_filter/
- Menu via Event wt_filter_menu
- Product index Events: wt_filter
- Catalog bootstrap Event: wt_filter_catalog
