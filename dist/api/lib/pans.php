<?php
declare(strict_types=1);

require_once LFUN_API_DIR . '/lib/panels.php';

function pan_store(array $user, array $pan, string $question, string $details, string $type, string $lang, ?string $localId = null): array
{
    $key = pan_key_for((int) $user['id'], (string) $pan['iso'], $type, $lang);
    $existing = db_one('SELECT * FROM divination_pans WHERE pan_key = ?', [$key]);
    $now = lfun_now();
    if ($existing) {
        if ($question !== '') {
            db_exec(
                'UPDATE divination_pans SET question = ?, details = ?, updated_at = ? WHERE id = ?',
                [lfun_text($question, 300), lfun_text($details, 300), $now, (int) $existing['id']]
            );
            $existing = db_one('SELECT * FROM divination_pans WHERE id = ?', [(int) $existing['id']]);
        }
        return $existing;
    }
    db_exec(
        'INSERT INTO divination_pans (user_id, pan_key, local_id, iso, lunar_month, lunar_day, lunar_leap, hour_index, solar_label,
            question, details, question_type, palace_m, palace_d, palace_t, unlocked, lang, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
        [
            (int) $user['id'], $key, $localId, $pan['iso'], (int) $pan['lunar_month'], (int) $pan['lunar_day'],
            !empty($pan['lunar_leap']) ? 1 : 0, (int) $pan['hour_index'], $pan['solar_label'],
            lfun_text($question, 300), lfun_text($details, 300), $type,
            (int) $pan['palace_m'], (int) $pan['palace_d'], (int) $pan['palace_t'], $lang, $now, $now,
        ]
    );
    return db_one('SELECT * FROM divination_pans WHERE id = ?', [db_last_id()]);
}

function pan_by_key(string $key): ?array
{
    $row = db_one('SELECT * FROM divination_pans WHERE pan_key = ?', [$key]);
    return $row ?: null;
}

function pan_public(array $p, bool $unlocked = false): array
{
    return [
        'id'           => (int) $p['id'],
        'pan_key'      => $p['pan_key'],
        'iso'          => $p['iso'],
        'lunar_month'  => (int) $p['lunar_month'],
        'lunar_day'    => (int) $p['lunar_day'],
        'lunar_leap'   => (bool) $p['lunar_leap'],
        'hour_index'   => (int) $p['hour_index'],
        'solar_label'  => $p['solar_label'],
        'question'     => $p['question'],
        'details'      => $p['details'],
        'category'     => $p['question_type'],
        'palace_m'     => (int) $p['palace_m'],
        'palace_d'     => (int) $p['palace_d'],
        'palace_t'     => (int) $p['palace_t'],
        'lang'         => $p['lang'],
        'unlocked'     => $unlocked,
        'created_at'   => $p['created_at'],
    ];
}

/** 云同步（PRD 模块四）：按 pan_key 去重，只写自己的数据。 */
function pan_sync(array $user, array $items): array
{
    $created = 0;
    $skipped = 0;
    $max = (int) cfg('security.max_pans_per_user', 500);
    $count = (int) db_value('SELECT COUNT(*) FROM divination_pans WHERE user_id = ?', [(int) $user['id']], 0);

    foreach ($items as $it) {
        if ($count >= $max) {
            $skipped++;
            continue;
        }
        if (!is_array($it)) {
            $skipped++;
            continue;
        }
        $iso = (string) ($it['iso'] ?? '');
        $type = (string) ($it['category'] ?? 'general');
        $lang = (string) ($it['lang'] ?? 'zh');
        if ($iso === '' || strtotime($iso) === false) {
            $skipped++;
            continue;
        }
        try {
            $pan = pan_build($iso);
        } catch (Throwable $e) {
            $skipped++;
            continue;
        }
        if (!isset(content_library($lang)['categories'][$type]) && $type !== 'lost' && $type !== 'general') {
            $type = 'general';
        }
        $before = db_value('SELECT id FROM divination_pans WHERE pan_key = ?', [pan_key_for((int) $user['id'], $pan['iso'], $type, $lang)]);
        pan_store($user, $pan, (string) ($it['question'] ?? ''), (string) ($it['details'] ?? ''), $type, $lang, (string) ($it['local_id'] ?? ''));
        if (!$before) {
            $created++;
            $count++;
        }
    }
    return ['created' => $created, 'skipped' => $skipped];
}

function pan_list(array $user, int $limit = 50, int $beforeId = 0): array
{
    $limit = max(1, min(200, $limit));
    if ($beforeId > 0) {
        return db_all(
            'SELECT * FROM divination_pans WHERE user_id = ? AND id < ? ORDER BY id DESC LIMIT ' . $limit,
            [(int) $user['id'], $beforeId]
        );
    }
    return db_all('SELECT * FROM divination_pans WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit, [(int) $user['id']]);
}
