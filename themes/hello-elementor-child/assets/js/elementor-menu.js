jQuery(document).ready(function ($) {
    if (!window.elementor) {
        console.warn('[KK Menu] Elementor not found — script halted.');
        return;
    }

    console.log('%c[KK Menu] Script loaded and waiting for widget panel...', 'color: #0bf;');

    elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view) {
        const widgetType = model.get('widgetType');
        console.log('[KK Menu] Widget opened:', widgetType);

        if (widgetType !== 'kk_submenu_grid') return;

        const menuControl = view.getControlView('menu');
        const parentControl = view.getControlView('parent');

        if (!menuControl || !parentControl) {
            console.warn('[KK Menu] Could not find menu/parent controls.');
            return;
        }

        // --- When menu changes ---
        menuControl.model.on('change:value', function (model) {
            const menu_id = model.get('value');
            console.log('[KK Menu] Menu changed →', menu_id);

            if (!menu_id) {
                console.warn('[KK Menu] No menu ID detected.');
                return;
            }

            $.get(kkAjax.ajaxurl, { action: 'kk_get_menu_items', menu_id }, function (response) {
                console.log('[KK Menu] AJAX response for menu_id', menu_id, ':', response);

                if (typeof response !== 'object') {
                    console.error('[KK Menu] Invalid response type:', typeof response);
                    return;
                }

                // Update parent dropdown options
                parentControl.model.set('options', response);
                parentControl.model.set('value', '');
                parentControl.render();

                console.log('[KK Menu] Parent dropdown updated with', Object.keys(response).length, 'items.');
            }).fail(function (xhr, status, error) {
                console.error('[KK Menu] AJAX failed:', status, error);
            });
        });

        // --- On initial open ---
        const initialMenu = menuControl.model.get('value');
        console.log('[KK Menu] Initial menu:', initialMenu);

        if (initialMenu) {
            $.get(kkAjax.ajaxurl, { action: 'kk_get_menu_items', menu_id: initialMenu }, function (response) {
                console.log('[KK Menu] Initial AJAX response:', response);

                if (typeof response === 'object') {
                    parentControl.model.set('options', response);
                    parentControl.render();
                    console.log('[KK Menu] Initial parent options loaded.');
                } else {
                    console.warn('[KK Menu] Unexpected initial response format.');
                }
            });
        }
    });
});
