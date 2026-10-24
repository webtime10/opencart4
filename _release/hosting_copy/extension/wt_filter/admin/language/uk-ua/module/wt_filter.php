<?php
// Heading
$_['heading_title']            = 'WT Filter';

// Text
$_['text_extension']           = 'Доповнення';
$_['text_success']             = 'Налаштування модуля WT Filter збережено!';
$_['text_edit']                = 'Редагування модуля WT Filter';
$_['text_enabled']             = 'Увімкнено';
$_['text_disabled']            = 'Вимкнено';
$_['text_default']             = 'Основний магазин';
$_['text_yes']                 = 'Так';
$_['text_loading']             = 'Завантаження...';
$_['text_complete']            = 'Готово';
$_['text_progress_truncate']   = 'Очищення існуючих фільтрів...';
$_['text_progress_option']     = 'Копіювання опцій товарів...';
$_['text_progress_filter']     = 'Копіювання стандартних фільтрів...';
$_['text_progress_attribute']  = 'Копіювання атрибутів...';
$_['text_progress_finalize']   = 'Привʼязка категорій та keyword...';
$_['text_complete_update']     = 'Оновлення завершено';
$_['text_catalog_list']        = 'Відкрити список опцій фільтра';

// Tabs
$_['tab_general']              = 'Загальні';
$_['tab_copy']                 = 'Копіювання / Оновлення';
$_['tab_seo']                  = 'SEO Сторінки';

// SEO Pages
$_['text_seo_list']            = 'Посадкові сторінки';
$_['text_seo_empty']           = 'SEO-посадкових ще немає.';
$_['text_seo_saved']           = 'SEO-сторінку збережено.';
$_['text_seo_deleted']         = 'SEO-сторінку видалено.';
$_['text_seo_confirm_delete']  = 'Видалити цю SEO-сторінку?';
$_['text_seo_add']             = 'Додати SEO-сторінку';
$_['text_seo_edit']            = 'Редагувати SEO-сторінку';
$_['column_seo_url']           = 'URL';
$_['column_seo_h1']            = 'H1';
$_['column_seo_status']        = 'Статус';
$_['column_seo_action']        = 'Дія';
$_['entry_seo_url']            = 'URL фільтра';
$_['entry_seo_h1']             = 'H1 / Хлібна крихта';
$_['entry_seo_meta_title']     = 'Meta Title';
$_['entry_seo_meta_description'] = 'Meta Description';
$_['entry_seo_meta_keyword']   = 'Meta Keywords';
$_['entry_seo_description']    = 'SEO-текст';
$_['help_seo_url']             = 'Вставте URL з категорією або брендом і фільтром. Приклади: https://site.com/obuv/razmer-obuvi:41/ , https://site.com/adidas/czvet:red/ або http://site.com/index.php?route=product/category&path=10_11&filter_wt_filter=razmer-obuvi:39';
$_['button_seo_add']           = 'Додати сторінку';
$_['button_seo_import']        = 'Масовий імпорт';
$_['button_seo_import_run']    = 'Імпортувати';
$_['text_seo_import']          = 'Масовий імпорт SEO-сторінок';
$_['help_seo_import']          = 'Вставте посилання пачкою — по одному на рядок (Enter). H1 і Title заповняться автоматично з категорії/бренду та фільтрів. Дублікати буде пропущено.';
$_['text_seo_import_done']     = 'Готово: створено %s, пропущено %s, помилок %s.';
$_['text_seo_import_created']  = 'Створено';
$_['text_seo_import_skipped']  = 'Пропуск';
$_['text_seo_import_error']    = 'Помилка';
$_['error_seo_import_empty']   = 'Вставте хоча б одне посилання.';
$_['error_seo_import_limit']   = 'За один раз можна імпортувати не більше 200 посилань.';
$_['text_seo_preview']         = 'Прев’ю URL';
$_['text_seo_preview_empty']   = 'Вставте URL фільтра — тут з’явиться розбір.';
$_['text_seo_scope_category']  = 'Категорія';
$_['text_seo_scope_manufacturer'] = 'Виробник';
$_['text_seo_open_site']       = 'Відкрити на сайті';
$_['text_seo_facet_price']     = 'Ціна';
$_['text_seo_facet_manufacturer'] = 'Виробник';
$_['text_seo_facet_stock']     = 'Наявність';
$_['text_seo_facet_stock_in']  = 'В наявності';
$_['text_seo_facet_stock_out'] = 'Немає в наявності';
$_['text_seo_facet_discount']  = 'Акції';
$_['text_seo_facet_discount_yes'] = 'Акція';
$_['error_seo_url']            = 'Вкажіть коректний URL фільтра.';
$_['error_seo_filter']         = 'Не вдалося розібрати параметри фільтра з URL.';
$_['error_seo_category']       = 'У URL немає категорії або виробника. Потрібен шлях на кшталт /obuv/.../ або path=10_11 / manufacturer_id=… — не лише сегмент фільтра.';
$_['error_seo_h1']             = 'Вкажіть H1.';
$_['error_seo_not_found']      = 'SEO-сторінку не знайдено.';
$_['error_seo_duplicate']      = 'Уже є посадкова #%s (%s). Змініть URL або відредагуйте наявну.';

// Entry
$_['entry_status']             = 'Статус';
$_['entry_store']              = 'Магазин';
$_['entry_type']               = 'Тип фільтра (лише для нових)';
$_['entry_attribute_separator']= 'Роздільник атрибутів';
$_['entry_price_special']      = 'Ціна у фільтрі';
$_['entry_discount']           = 'Фільтр «Акції»';
$_['entry_group_expanded']     = 'Стан груп';
$_['column_group_name']        = 'Група';
$_['column_expanded_desktop']  = 'Деск';
$_['column_expanded_mobile']   = 'Моб';
$_['text_group_price']         = 'Ціна';
$_['text_group_manufacturer']  = 'Виробник';
$_['text_group_stock']         = 'Наявність';
$_['text_group_discount']      = 'Акції';
$_['help_group_expanded']      = 'Чи відкрита група за замовчуванням. Увімк = відкрита. Опції товарів налаштовуються окремо у списку WT Filter.';
$_['entry_accent_color']       = 'Колір акценту';
$_['help_accent_color']        = 'HEX-колір (#229ac8) для шевронів, спінера, слайдера ціни, кнопки «Застосувати фільтр» і скролу.';
$_['entry_show_counts']        = 'Кількість біля опцій';
$_['help_show_counts']         = 'Показувати цифри (N) біля значень фільтра. Якщо вимкнути — COUNT-запити не виконуються, фільтр працює швидше.';
$_['entry_seo_text_position']  = 'SEO-текст на сторінці';
$_['text_seo_text_above']      = 'Вище товарів';
$_['text_seo_text_below']      = 'Нижче товарів';
$_['help_seo_text_position']   = 'Де показувати SEO-текст посадкової: одразу над сіткою товарів або під нею.';
$_['entry_copy_truncate']      = 'Очистити існуючі дані WT Filter';
$_['entry_copy_attribute']     = 'Копіювати атрибути';
$_['entry_copy_filter']        = 'Копіювати стандартні фільтри';
$_['entry_copy_option']        = 'Копіювати опції товарів';

$_['text_price_special_yes']   = 'Враховувати знижку (акційну ціну)';
$_['text_price_special_no']    = 'Не враховувати знижку (базова ціна)';

// Help
$_['help_attribute_separator'] = 'Розбити текст атрибута на кілька значень. За замовчуванням: кома (,). Порожнє — не розбивати.';
$_['help_price_special']       = 'Якщо увімкнено — слайдер і фільтр ціни використовують ціну зі знижкою (Special), інакше звичайну ціну товару.';
$_['help_discount']            = 'Показувати у фільтрі блок «Акції» (лише товари з активною знижкою Special), якщо такі товари є.';
$_['help_copy_btn']            = 'Натисніть, якщо завантажуєте опції/атрибути у фільтр уперше. Позначте джерела вище і натисніть «Копіювати».';
$_['help_update_btn']          = 'Натисніть, якщо опції/атрибути вже були в фільтрі і ви додали нові в OpenCart. Кнопка «Оновити» підтягне тільки нове і збереже ваші типи та картинки.';
$_['help_copy']                = 'За потреби: «Очистити існуючі дані» — лише для повної пересборки з нуля.';

// Button
$_['button_copy']              = 'Копіювати';
$_['button_update']            = 'Оновити';

// Error
$_['error_permission']         = 'У вас немає прав для зміни модуля WT Filter!';
$_['error_copy_store']         = 'Вкажіть магазин для копіювання.';
$_['error_copy_type']          = 'Вкажіть тип майбутніх опцій.';
$_['error_copy_step']          = 'Невірний крок копіювання.';
$_['error_copy_source']        = 'Оберіть хоча б одне джерело: опції, фільтри або атрибути.';
