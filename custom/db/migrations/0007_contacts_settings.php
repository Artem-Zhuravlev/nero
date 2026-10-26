<?php
declare(strict_types=1);

// Migration 0007: shop contacts move to the theme settings (SPEC-047 REQ-SET-003, REQ-FTR-004).
// Copies the old ocs_shell phones / address / hours / messengers / social links into theme_ocs_contact and theme_ocs_contact_links once
// (only when the new settings are empty), deletes the apps setting. The old values stay and are read as a fallback.
// Rollback: delete the two theme settings; the footer falls back to the module fields.

return function (\Ocs\Migrate\Context $m): void {
    if ($m->setting->get('theme_ocs', 'theme_ocs_contact') === null) {
        $old = $m->setting->get('module_ocs_shell', 'module_ocs_shell_l');
        $phones = (string) $m->setting->get('module_ocs_shell', 'module_ocs_shell_phones');
        $contact = [];
        foreach ((is_array($old) ? $old : []) as $code => $values) {
            $contact[$code] = [
                'address' => (string) ($values['address'] ?? ''),
                'hours' => (string) ($values['hours'] ?? ''),
                'phones' => $phones,
            ];
        }
        if (!$contact && $phones !== '') {
            $contact['uk-ua'] = ['address' => '', 'hours' => '', 'phones' => $phones];
        }
        $m->setting->set('theme_ocs', 'theme_ocs_contact', $contact);
    }

    if ($m->setting->get('theme_ocs', 'theme_ocs_contact_links') === null) {
        $links = [];
        $sort = 1;
        foreach (['telegram' => 'Telegram', 'viber' => 'Viber', 'messenger' => 'Messenger'] as $key => $name) {
            $url = trim((string) $m->setting->get('module_ocs_shell', 'module_ocs_shell_' . $key));
            if ($url !== '') {
                $links[] = ['icon' => $key, 'kind' => 'messenger', 'title' => ['uk-ua' => $name, 'en-gb' => $name, 'ru-ru' => $name], 'url' => $url, 'sort' => $sort++];
            }
        }
        foreach (preg_split('/\r\n|\r|\n/', (string) $m->setting->get('module_ocs_shell', 'module_ocs_shell_social')) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if ($parts[0] !== '' && !empty($parts[1])) {
                $links[] = ['icon' => 'site', 'kind' => 'social', 'title' => ['uk-ua' => $parts[0], 'en-gb' => $parts[0], 'ru-ru' => $parts[0]], 'url' => $parts[1], 'sort' => $sort++];
            }
        }
        $m->setting->set('theme_ocs', 'theme_ocs_contact_links', $links);
    }

    $m->setting->delete('module_ocs_shell', 'module_ocs_shell_apps');
};
