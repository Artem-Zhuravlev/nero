<?php
declare(strict_types=1);

// ocs_cookie 001: register the module, grant admin permission, defaults (status 0, REQ-EXT-031), footer view event (SPEC-083).
// Rollback: set module_ocs_cookie_status to 0; the event then returns immediately and no popup is printed.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_cookie');
    $m->permission->grant(1, 'extension/module/ocs_cookie');

    $defaults = [
        'module_ocs_cookie_status' => 0,
        'module_ocs_cookie_position' => 'card',
        'module_ocs_cookie_version' => 1,
        'module_ocs_cookie_days' => 365,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_cookie', $key) === null) {
            $m->setting->set('module_ocs_cookie', $key, $value);
        }
    }
    if ($m->setting->get('module_ocs_cookie', 'module_ocs_cookie_l') === null) {
        $m->setting->set('module_ocs_cookie', 'module_ocs_cookie_l', [
            'uk-ua' => [
                'title' => 'Ми використовуємо cookie',
                'text' => 'Ми використовуємо файли cookie, щоб сайт працював коректно, а також для аналітики та покращення сервісу. Продовжуючи користуватися сайтом, ви погоджуєтеся з умовами використання cookie.',
                'link_label' => 'Умови використання cookie',
                'link_url' => '',
                'accept' => 'Прийняти',
                'necessary' => 'Лише необхідні',
            ],
            'ru-ru' => [
                'title' => 'Мы используем cookie',
                'text' => 'Мы используем файлы cookie, чтобы сайт работал корректно, а также для аналитики и улучшения сервиса. Продолжая пользоваться сайтом, вы соглашаетесь с условиями использования cookie.',
                'link_label' => 'Условия использования cookie',
                'link_url' => '',
                'accept' => 'Принять',
                'necessary' => 'Только необходимые',
            ],
            'en-gb' => [
                'title' => 'We use cookies',
                'text' => 'We use cookies to make the site work properly, and for analytics and to improve our service. By continuing to use the site you agree to our cookie terms.',
                'link_label' => 'Cookie terms',
                'link_url' => '',
                'accept' => 'Accept',
                'necessary' => 'Necessary only',
            ],
        ]);
    }

    $m->event->register('ocs_cookie_footer_before', 'catalog/view/common/footer/before', 'extension/ocs_cookie/events/beforeFooter', 1);
};
