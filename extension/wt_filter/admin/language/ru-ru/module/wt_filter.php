<?php
// Heading
$_['heading_title']            = 'WT Filter';

// Text
$_['text_extension']           = 'Расширения';
$_['text_success']             = 'Настройки модуля WT Filter сохранены!';
$_['text_edit']                = 'Редактирование модуля WT Filter';
$_['text_enabled']             = 'Включено';
$_['text_disabled']            = 'Выключено';
$_['text_default']             = 'Основной магазин';
$_['text_yes']                 = 'Да';
$_['text_loading']             = 'Загрузка...';
$_['text_complete']            = 'Готово';
$_['text_progress_truncate']   = 'Очистка существующих фильтров...';
$_['text_progress_option']     = 'Копирование опций товаров...';
$_['text_progress_filter']     = 'Копирование стандартных фильтров...';
$_['text_progress_attribute']  = 'Копирование атрибутов...';
$_['text_progress_finalize']   = 'Привязка категорий и keyword...';
$_['text_complete_update']     = 'Обновление завершено';
$_['text_catalog_list']        = 'Открыть список опций фильтра';

// Tabs
$_['tab_general']              = 'Основные';
$_['tab_copy']                 = 'Копирование / Обновление';
$_['tab_seo']                  = 'SEO Страницы';

// SEO Pages
$_['text_seo_list']            = 'Посадочные страницы';
$_['text_seo_empty']           = 'SEO-посадочных пока нет.';
$_['text_seo_saved']           = 'SEO-страница сохранена.';
$_['text_seo_deleted']         = 'SEO-страница удалена.';
$_['text_seo_confirm_delete']  = 'Удалить эту SEO-страницу?';
$_['text_seo_add']             = 'Добавить SEO-страницу';
$_['text_seo_edit']            = 'Редактировать SEO-страницу';
$_['column_seo_url']           = 'URL';
$_['column_seo_h1']            = 'H1';
$_['column_seo_status']        = 'Статус';
$_['column_seo_action']        = 'Действие';
$_['entry_seo_url']            = 'URL фильтра';
$_['entry_seo_h1']             = 'H1 / Хлебная крошка';
$_['entry_seo_meta_title']     = 'Meta Title';
$_['entry_seo_meta_description'] = 'Meta Description';
$_['entry_seo_meta_keyword']   = 'Meta Keywords';
$_['entry_seo_description']    = 'SEO-текст';
$_['help_seo_url']             = 'Вставьте URL с категорией или брендом и фильтром. Примеры: https://site.com/obuv/razmer-obuvi:41/ , https://site.com/adidas/czvet:red/ или http://site.com/index.php?route=product/category&path=10_11&filter_wt_filter=razmer-obuvi:39';
$_['button_seo_add']           = 'Добавить страницу';
$_['button_seo_import']        = 'Массовый импорт';
$_['button_seo_import_run']    = 'Импортировать';
$_['text_seo_import']          = 'Массовый импорт SEO-страниц';
$_['help_seo_import']          = 'Вставьте ссылки пачкой — по одной на строку (Enter). H1 и Title заполнятся автоматически из категории/бренда и фильтров. Дубликаты будут пропущены.';
$_['text_seo_import_done']     = 'Готово: создано %s, пропущено %s, ошибок %s.';
$_['text_seo_import_created']  = 'Создано';
$_['text_seo_import_skipped']  = 'Пропуск';
$_['text_seo_import_error']    = 'Ошибка';
$_['error_seo_import_empty']   = 'Вставьте хотя бы одну ссылку.';
$_['error_seo_import_limit']   = 'За один раз можно импортировать не больше 200 ссылок.';
$_['text_seo_preview']         = 'Превью URL';
$_['text_seo_preview_empty']   = 'Вставьте URL фильтра — здесь появится разбор.';
$_['text_seo_scope_category']  = 'Категория';
$_['text_seo_scope_manufacturer'] = 'Производитель';
$_['text_seo_open_site']       = 'Открыть на сайте';
$_['text_seo_facet_price']     = 'Цена';
$_['text_seo_facet_manufacturer'] = 'Производитель';
$_['text_seo_facet_stock']     = 'Наличие';
$_['text_seo_facet_stock_in']  = 'В наличии';
$_['text_seo_facet_stock_out'] = 'Нет в наличии';
$_['text_seo_facet_discount']  = 'Акции';
$_['text_seo_facet_discount_yes'] = 'Акция';
$_['error_seo_url']            = 'Укажите корректный URL фильтра.';
$_['error_seo_filter']         = 'Не удалось разобрать параметры фильтра из URL.';
$_['error_seo_category']       = 'В URL нет категории или производителя. Нужен путь вида /obuv/.../ или path=10_11 / manufacturer_id=… — не только сегмент фильтра.';
$_['error_seo_h1']             = 'Укажите H1.';
$_['error_seo_not_found']      = 'SEO-страница не найдена.';
$_['error_seo_duplicate']      = 'Уже есть посадочная #%s (%s). Измените URL или отредактируйте существующую.';

// Entry
$_['entry_status']             = 'Статус';
$_['entry_store']              = 'Магазин';
$_['entry_type']               = 'Тип фильтра (только для новых)';
$_['entry_attribute_separator']= 'Разделитель атрибутов';
$_['entry_price_special']      = 'Цена в фильтре';
$_['entry_discount']           = 'Фильтр «Акции»';
$_['entry_group_expanded']     = 'Состояние групп';
$_['column_group_name']        = 'Группа';
$_['column_expanded_desktop']  = 'Деск';
$_['column_expanded_mobile']   = 'Моб';
$_['text_group_price']         = 'Цена';
$_['text_group_manufacturer']  = 'Производитель';
$_['text_group_stock']         = 'Наличие';
$_['text_group_discount']      = 'Акции';
$_['help_group_expanded']      = 'Открыта ли группа по умолчанию. Вкл = открыта. Опции товаров настраиваются отдельно в списке WT Filter.';
$_['entry_accent_color']       = 'Цвет акцента';
$_['help_accent_color']        = 'HEX-цвет (#229ac8) для шевронов, спиннера, слайдера цены, кнопки «Применить фильтр» и скролла.';
$_['entry_show_counts']        = 'Количество у опций';
$_['help_show_counts']         = 'Показывать цифры (N) рядом со значениями фильтра. Если выключить — COUNT-запросы не выполняются, фильтр работает быстрее.';
$_['entry_seo_text_position']  = 'SEO-текст на странице';
$_['text_seo_text_above']      = 'Выше товаров';
$_['text_seo_text_below']      = 'Ниже товаров';
$_['help_seo_text_position']   = 'Где показывать SEO-текст посадочной: сразу над сеткой товаров или под ней.';
$_['entry_copy_truncate']      = 'Очистить существующие данные WT Filter';
$_['entry_copy_attribute']     = 'Копировать атрибуты';
$_['entry_copy_filter']        = 'Копировать стандартные фильтры';
$_['entry_copy_option']        = 'Копировать опции товаров';

$_['text_price_special_yes']   = 'Учитывать скидку (акционную цену)';
$_['text_price_special_no']    = 'Не учитывать скидку (базовая цена)';

// Help
$_['help_attribute_separator'] = 'Разбить текст атрибута на несколько значений. По умолчанию: запятая (,). Пустое — не разбивать.';
$_['help_price_special']       = 'Если включено — слайдер и фильтр цены используют цену со скидкой (Special), иначе обычную цену товара.';
$_['help_discount']            = 'Показывать в фильтре блок «Акции» (только товары с активной скидкой Special), если такие товары есть.';
$_['help_copy_btn']            = 'Нажмите, если загружаете опции/атрибуты в фильтр впервые. Отметьте источники выше и нажмите «Копировать».';
$_['help_update_btn']          = 'Нажмите, если опции/атрибуты уже были в фильтре и вы добавили новые в OpenCart. Кнопка «Обновить» подтянет только новое и сохранит ваши типы и картинки.';
$_['help_copy']                = 'При необходимости: «Очистить существующие данные» — только для полной пересборки с нуля.';

// Button
$_['button_copy']              = 'Копировать';
$_['button_update']            = 'Обновить';

// Error
$_['error_permission']         = 'У вас нет прав для изменения модуля WT Filter!';
$_['error_copy_store']         = 'Укажите магазин для копирования.';
$_['error_copy_type']          = 'Укажите тип будущих опций.';
$_['error_copy_step']          = 'Неверный шаг копирования.';
$_['error_copy_source']        = 'Выберите хотя бы один источник: опции, фильтры или атрибуты.';
