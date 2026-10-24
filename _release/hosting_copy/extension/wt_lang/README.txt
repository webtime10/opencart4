WT Lang v1.0.0 — OpenCart 4
=============================

Lightweight multilanguage SEO: URL prefixes (/en/), hreflang, language switcher.

Install
-------
1. Extension files live in extension/wt_lang/
2. Run: php tools/install_wt_lang.php (from OpenCart root)
3. Admin → Extensions → Modifications → Refresh
4. Admin → Extensions → Extensions → Modules → WT Lang → Install → Enable → Save

Default setup
-------------
- Main language: no URL prefix — site.com/
- English: prefix en — site.com/en/
- hreflang tags enabled

Files
-----
- system/library/wt_lang/wt_lang.php — core router/rewriter
- catalog/controller/module/wt_lang.php — bootstrap, rewrite, events
- admin/controller/module/wt_lang.php — settings
- ocmod/wt_lang.ocmod.xml — seo_url bootstrap, header hreflang
