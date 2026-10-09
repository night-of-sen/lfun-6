<?php
declare(strict_types=1);

/**
 * 解锁记录：同一用户同一盘只付一次，历史记录里可反复查看（PRD 模块二 2.2）。
 */
function unlock_find(string $panKey): ?array
{
    $row = db_one('SELECT * FROM unlocks WHERE pan_key = ?', [$panKey]);
    return $row ?: null;
}

function unlock_grant(int $userId, string $panKey, ?int $panId, ?int $orderId, string $source): void
{
    $existing = unlock_find($panKey);
    if ($existing) {
        if ((int) $existing['user_id'] === $userId) {
            return;
        }
        // 盘面换了主人：不允许覆盖，记录以便排查
        db_audit('system', 'unlock.conflict', $panKey, ['existing_user' => (int) $existing['user_id'], 'new_user' => $userId]);
        return;
    }
    try {
        db_exec(
            'INSERT INTO unlocks (user_id, pan_key, pan_id, order_id, source, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $panKey, $panId, $orderId, $source, lfun_now()]
        );
    } catch (Throwable $e) {
        // 并发下唯一键冲突：视为已解锁
        if (!unlock_find($panKey)) {
            throw $e;
        }
        return;
    }
    if ($panId) {
        db_exec('UPDATE divination_pans SET unlocked = 1, updated_at = ? WHERE id = ?', [lfun_now(), $panId]);
    }
    db_audit('system', 'unlock.grant', $panKey, ['source' => $source, 'order_id' => $orderId]);
}

function unlock_revoke(string $panKey): void
{
    db_exec('DELETE FROM unlocks WHERE pan_key = ?', [$panKey]);
    db_exec('UPDATE divination_pans SET unlocked = 0, updated_at = ? WHERE pan_key = ?', [lfun_now(), $panKey]);
}

/** 是否可以查看详细解读：已单次解锁，或会员在有效期内。 */
function unlock_state(array $user, array $pan): array
{
    $member = user_is_member($user);
    if ($member) {
        return ['unlocked' => true, 'reason' => 'member'];
    }
    $u = unlock_find((string) $pan['pan_key']);
    if ($u && (int) $u['user_id'] === (int) $user['id']) {
        return ['unlocked' => true, 'reason' => 'order'];
    }
    return ['unlocked' => false, 'reason' => 'locked'];
}
