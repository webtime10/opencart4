WT Exchange for OpenCart 4.1.x
================================

Import/export module ported from OpenCart 3:
- Products, categories, manufacturers, customers, orders
- CSV, JSON, XML, XLSX formats
- AJAX batch import, field mappings, macros
- Scheduler (cron export), import logs

Install
-------
1. Copy extension folder to your shop: extension/wt_exchange/
   (or Admin → Extensions → Installer → upload zip of this package)
2. Admin → Extensions → Modules → WT Exchange → Install → Edit → Save
3. Clear cache if needed, re-login

Cron (scheduled exports)
------------------------
Set cron key in WT Exchange → Scheduler, then call:

  index.php?route=extension/wt_exchange/module/wt_exchange.cron&key=YOUR_KEY

Notes
-----
- SEO URLs use OC4 seo_url schema (key/value columns, not query).
- Work files default to storage/wt_exchange/
- Package path: extension/wt_exchange/
