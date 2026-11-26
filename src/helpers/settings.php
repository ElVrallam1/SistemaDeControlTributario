<?php
declare(strict_types=1);

function settingGet(string $key, ?string $default = null): ?string {
    $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :k LIMIT 1');
    $st->execute(['k' => $key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function settingSet(string $key, string $value): void {
    $st = db()->prepare('INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (:k,:v,NOW())
        ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()');
    $st->execute(['k' => $key, 'v' => $value]);
}
