<?php
$path = dirname(__DIR__) . '/extension/plum_store/catalog/view/javascript/plum_store/theme.js';
$js = file_get_contents($path);
$js = str_replace('wtshop-setka4-theme', 'plum_store-setka4-theme', $js);
if (strpos($js, 'data-theme-toggle') === false) {
	$js .= <<<'JS'

/* theme popup on logo */
(function ($) {
  function closeThemePop() {
    var $root = $("[data-theme-root]");
    $root.removeClass("is-open");
    $root.find("[data-theme-toggle]").attr("aria-expanded", "false");
    $root.find("[data-theme-pop]").prop("hidden", true);
  }
  function openThemePop() {
    var $root = $("[data-theme-root]");
    $root.addClass("is-open");
    $root.find("[data-theme-toggle]").attr("aria-expanded", "true");
    $root.find("[data-theme-pop]").prop("hidden", false);
  }
  $(document).on("click", "[data-theme-toggle]", function (e) {
    e.preventDefault();
    e.stopPropagation();
    if ($("[data-theme-root]").hasClass("is-open")) closeThemePop();
    else openThemePop();
  });
  $(document).on("click", "[data-theme-pop]", function (e) { e.stopPropagation(); });
  $(document).on("click", function () { closeThemePop(); });
  $(document).on("keydown", function (e) { if (e.key === "Escape") closeThemePop(); });
})(jQuery);

JS;
}
file_put_contents($path, $js);
echo "theme.js ok\n";

$cssPath = dirname(__DIR__) . '/extension/plum_store/catalog/view/stylesheet/plum_store/_layout.css';
$css = file_get_contents($cssPath);
if (strpos($css, '.theme-pop') === false) {
	$css .= <<<'CSS'

.topbar-left[data-theme-root] { position: relative; display: flex; align-items: center; flex: 0 0 auto; }
.theme-pop { position: absolute; top: calc(100% + 8px); left: 0; z-index: 120; min-width: 220px; padding: 10px; border: 1px solid var(--border, #ddd); border-radius: 12px; background: var(--surface, #fff); box-shadow: 0 12px 32px rgba(0,0,0,.18); }
.theme-pop .theme-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.theme-pop button { min-height: 32px; padding: 4px 11px; border: 1px solid var(--border, #ddd); border-radius: 999px; background: transparent; color: var(--text, #222); font-size: 13px; font-weight: 600; cursor: pointer; }
.theme-pop button.is-active, .theme-pop button:hover { border-color: var(--accent); color: var(--accent); }

CSS;
	file_put_contents($cssPath, $css);
	echo "layout css patched\n";
} else {
	echo "layout css has theme-pop\n";
}
