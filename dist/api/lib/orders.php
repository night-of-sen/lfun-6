<?php
declare(strict_types=1);

require_once LFUN_API_DIR . '/lib/settings.php';
require_once LFUN_API_DIR . '/lib/notify.php';

/**
 * 订单：状态机 pending -> paid / failed -> refunded（PRD 模块一 1.2）。
 * 金额一律由服务端按定价表核定，前端传来的金额只作参考。
 */
function order_amount_for(string $type, $requestedCents = null): int
{
    $p = pricing();
    if ($type === 'single_unlock') {
        return (int) $p['single_unlock_cents'];
    }
    if ($type === 'member_monthly') {
        return (int) $p['member_monthly_cents'];
    }
    if ($type === 'donation') {
        $cents = (int) round((float) $requestedCents);
        return lfun_clamp($cents, (int) $p['donation_min_cents'], (int) $p['donation_max_cents']);
    }
    throw new InvalidArgumentException('未知的订单类型');
}

function order_new_trade_no(): string
{
    return 'LF' . gmdate('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
}

function order_create(array $user, string $type, string $channel, $requestedCents = null, ?string $panKey = null, ?int $panId = null): array
{
    if (!in_array($type, LFUN_ORDER_TYPES, true)) {
        throw new InvalidArgumentException('未知的订单类型');
    }
    if ($type === 'single_unlock' && ($panKey === null || $panKey === '')) {
        throw new InvalidArgumentException('单次解锁必须绑定盘面');
    }
    if ($type === 'single_unlock' && isset($user['id'])) {
        // 已解锁的直接返回，不重复下单
        $existing = unlock_find($panKey);
        if ($existing && (int) $existing['user_id'] === (int) $user['id']) {
            return ['already_unlocked' => true, 'order' => null];
        }
    }
    if ($type === 'member_monthly' && user_is_member($user)) {
        // 会员可继续叠加购买，此处不拦截
    }

    $amount = order_amount_for($type, $requestedCents);
    $now = lfun_now();
    $tradeNo = order_new_trade_no();
    $pendingMinutes = (int) cfg('security.order_pending_minutes', 30);

    db_exec(
        'INSERT INTO orders (user_id, type, pan_key, pan_id, amount_cents, status, pay_channel, out_trade_no, meta, created_at, updated_at, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (int) $user['id'], $type, $panKey, $panId, $amount, 'pending', $channel, $tradeNo,
            json_encode(['requested_cents' => $requestedCents], JSON_UNESCAPED_UNICODE),
            $now, $now, lfun_plus_minutes($pendingMinutes),
        ]
    );
    $order = order_by_id(db_last_id());
    db_audit('user:' . $user['id'], 'order.create', $tradeNo, ['type' => $type, 'amount' => $amount, 'channel' => $channel]);
    return ['already_unlocked' => false, 'order' => $order];
}

function order_by_id(int $id): ?array
{
    $row = db_one('SELECT * FROM orders WHERE id = ?', [$id]);
    return $row ?: null;
}

function order_by_trade_no(string $tradeNo): ?array
{
    $row = db_one('SELECT * FROM orders WHERE out_trade_no = ?', [$tradeNo]);
    return $row ?: null;
}

function order_own(array $user, int $id): ?array
{
    $row = db_one('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$id, (int) $user['id']]);
    return $row ?: null;
}

function order_public(array $o): array
{
    return [
        'id'           => (int) $o['id'],
        'type'         => $o['type'],
        'pan_id'       => $o['pan_id'] !== null ? (int) $o['pan_id'] : null,
        'amount_cents' => (int) $o['amount_cents'],
        'amount_cny'   => lfun_cents_to_cny((int) $o['amount_cents']),
        'status'       => $o['status'],
        'channel'      => $o['pay_channel'],
        'out_trade_no' => $o['out_trade_no'],
        'claimed'      => !empty($o['claimed_at']),
        'paid_at'      => $o['paid_at'],
        'created_at'   => $o['created_at'],
        'expires_at'   => $o['expires_at'],
    ];
}

/**
 * 标记已支付。幂等：同一 out_trade_no 重复回调只生效一次（PRD 模块一 1.5）。
 * 返回 [order, unlocked(bool), first_time(bool)]
 */
function order_mark_paid(array $order, ?string $channelTxnId = null, string $source = 'notify'): array
{
    $pdo = db();
    $firstTime = false;
    $pdo->beginTransaction();
    try {
        $fresh = order_by_id((int) $order['id']);
        if (!$fresh) {
            throw new RuntimeException('订单不存在');
        }
        if ($fresh['status'] === 'paid') {
            $pdo->commit();
            return ['order' => $fresh, 'unlocked' => true, 'first_time' => false];
        }
        if ($fresh['status'] === 'refunded') {
            $pdo->commit();
            return ['order' => $fresh, 'unlocked' => false, 'first_time' => false, 'refunded' => true];
        }
        $now = lfun_now();
        db_exec(
            'UPDATE orders SET status = ?, paid_at = ?, channel_txn_id = COALESCE(?, channel_txn_id), updated_at = ? WHERE id = ? AND status <> ?',
            ['paid', $now, $channelTxnId, $now, (int) $fresh['id'], 'paid']
        );
        $firstTime = true;

        if ($fresh['type'] === 'single_unlock' && !empty($fresh['pan_key'])) {
            unlock_grant(
                (int) $fresh['user_id'],
                (string) $fresh['pan_key'],
                $fresh['pan_id'] !== null ? (int) $fresh['pan_id'] : null,
                (int) $fresh['id'],
                'order'
            );
        } elseif ($fresh['type'] === 'member_monthly') {
            $days = (int) cfg('pricing.member_days', 30);
            user_extend_membership((int) $fresh['user_id'], $days > 0 ? $days : 30);
        }
        $pdo->commit();
        $fresh = order_by_id((int) $fresh['id']);
        db_audit($source, 'order.paid', (string) $fresh['out_trade_no'], ['amount' => (int) $fresh['amount_cents']]);
        try {
            notify_order_paid($fresh);
        } catch (Throwable $e) {
            lfun_log_error($e);
        }
        return ['order' => $fresh, 'unlocked' => $fresh['type'] !== 'donation', 'first_time' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function order_mark_failed(array $order, string $reason = ''): void
{
    if ($order['status'] !== 'pending') {
        return;
    }
    db_exec(
        'UPDATE orders SET status = ?, note = ?, updated_at = ? WHERE id = ? AND status = ?',
        ['failed', lfun_text($reason, 200), lfun_now(), (int) $order['id'], 'pending']
    );
}

/** 用户点击“我已完成付款”（人工收款渠道）。 */
function order_claim(array $order): array
{
    if ($order['status'] === 'paid') {
        return $order;
    }
    if ($order['status'] !== 'pending' && $order['status'] !== 'failed') {
        return $order;
    }
    $now = lfun_now();
    db_exec(
        'UPDATE orders SET claimed_at = COALESCE(claimed_at, ?), updated_at = ? WHERE id = ?',
        [$now, $now, (int) $order['id']]
    );
    $fresh = order_by_id((int) $order['id']);
    db_audit('user:' . $order['user_id'], 'order.claim', (string) $order['out_trade_no']);
    try {
        notify_order_claim($fresh);
    } catch (Throwable $e) {
        // 通知失败不能影响下单流程
        lfun_log_error($e);
    }

    $manual = (array) cfg('pay.manual', []);
    if (!empty($manual['auto_grant'])) {
        return order_mark_paid($fresh, null, 'manual_auto')['order'];
    }
    return $fresh;
}

function order_refund(array $order, string $actor = 'admin'): array
{
    $now = lfun_now();
    db_exec('UPDATE orders SET status = ?, refunded_at = ?, updated_at = ? WHERE id = ?', ['refunded', $now, $now, (int) $order['id']]);
    if ($order['type'] === 'single_unlock' && !empty($order['pan_key'])) {
        unlock_revoke((string) $order['pan_key']);
    }
    db_audit($actor, 'order.refund', (string) $order['out_trade_no']);
    return order_by_id((int) $order['id']);
}

/** 未付款订单查询（前端轮询）。 */
function order_list_for_user(int $userId, int $limit = 50): array
{
    return db_all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit, [$userId]);
}

/** 掉单补偿：pending 超过 N 分钟的订单，向支付渠道主动查询（PRD 模块一 1.3）。 */
function orders_pending_reconcile(int $limit = 20): array
{
    $minutes = (int) cfg('security.order_query_before_paid_minutes', 10);
    $rows = db_all(
        'SELECT * FROM orders WHERE status = ? AND pay_channel <> ? AND created_at <= ? ORDER BY id ASC LIMIT ' . (int) $limit,
        ['pending', 'manual', lfun_plus_minutes(-$minutes)]
    );
    $results = [];
    foreach ($rows as $row) {
        $results[] = ['order' => $row, 'query' => payment_query($row)];
    }
    return $results;
}
