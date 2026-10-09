<?php
declare(strict_types=1);

/** PDO 单例。 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $driver = (string) cfg('db.driver', 'sqlite');
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if ($driver === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) cfg('db.host', 'localhost'),
            (int) cfg('db.port', 3306),
            (string) cfg('db.name', ''),
            (string) cfg('db.charset', 'utf8mb4')
        );
        $pdo = new PDO($dsn, (string) cfg('db.user', ''), (string) cfg('db.pass', ''), $opts);
    } else {
        $path = (string) cfg('db.path', LFUN_API_DIR . '/storage/lfun.sqlite');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    return $pdo;
}

function db_driver(): string
{
    return (string) cfg('db.driver', 'sqlite');
}

function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = [], $default = null)
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

function db_last_id(): int
{
    return (int) db()->lastInsertId();
}

/** 表结构。SQLite 与 MySQL 双份 DDL，保证本地可测、线上可用。 */
function db_schema(): array
{
    if (db_driver() === 'mysql') {
        return [
            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                device_id VARCHAR(64) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                phone VARCHAR(32) NULL,
                member_expire_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                last_seen_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_device (device_id),
                UNIQUE KEY uq_users_token (token_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS divination_pans (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                pan_key CHAR(40) NOT NULL,
                local_id VARCHAR(64) NULL,
                iso VARCHAR(40) NOT NULL,
                lunar_month INT NOT NULL,
                lunar_day INT NOT NULL,
                lunar_leap TINYINT NOT NULL DEFAULT 0,
                hour_index INT NOT NULL,
                solar_label VARCHAR(40) NOT NULL,
                question TEXT NOT NULL,
                details TEXT NULL,
                question_type VARCHAR(24) NOT NULL,
                subtype VARCHAR(64) NULL,
                palace_m INT NOT NULL,
                palace_d INT NOT NULL,
                palace_t INT NOT NULL,
                unlocked TINYINT NOT NULL DEFAULT 0,
                lang VARCHAR(8) NOT NULL DEFAULT 'zh',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_pans_key (pan_key),
                KEY idx_pans_user (user_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(24) NOT NULL,
                pan_key CHAR(40) NULL,
                pan_id BIGINT UNSIGNED NULL,
                amount_cents INT NOT NULL,
                status VARCHAR(16) NOT NULL,
                pay_channel VARCHAR(16) NOT NULL,
                out_trade_no VARCHAR(48) NOT NULL,
                channel_txn_id VARCHAR(96) NULL,
                claimed_at DATETIME NULL,
                paid_at DATETIME NULL,
                refunded_at DATETIME NULL,
                note VARCHAR(255) NULL,
                meta TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                expires_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_orders_no (out_trade_no),
                KEY idx_orders_user (user_id, id),
                KEY idx_orders_status (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS unlocks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                pan_key CHAR(40) NOT NULL,
                pan_id BIGINT UNSIGNED NULL,
                order_id BIGINT UNSIGNED NULL,
                source VARCHAR(16) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_unlocks_pan (pan_key),
                KEY idx_unlocks_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS share_clicks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pan_key CHAR(40) NOT NULL,
                referrer VARCHAR(255) NULL,
                ip_hash CHAR(32) NULL,
                ua_hash CHAR(32) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_share_pan (pan_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS settings (
                k VARCHAR(64) NOT NULL,
                v TEXT NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (k)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS interpretations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pattern_key VARCHAR(64) NOT NULL,
                question_type VARCHAR(24) NOT NULL,
                lang VARCHAR(8) NOT NULL,
                rich_text TEXT NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_interp (pattern_key, question_type, lang)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS admin_users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(64) NOT NULL,
                pass_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_admin_user (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor VARCHAR(64) NOT NULL,
                action VARCHAR(48) NOT NULL,
                target VARCHAR(96) NULL,
                detail TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_audit_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }

    return [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id TEXT NOT NULL UNIQUE,
            token_hash TEXT NOT NULL UNIQUE,
            phone TEXT,
            member_expire_at TEXT,
            created_at TEXT NOT NULL,
            last_seen_at TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS divination_pans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            pan_key TEXT NOT NULL UNIQUE,
            local_id TEXT,
            iso TEXT NOT NULL,
            lunar_month INTEGER NOT NULL,
            lunar_day INTEGER NOT NULL,
            lunar_leap INTEGER NOT NULL DEFAULT 0,
            hour_index INTEGER NOT NULL,
            solar_label TEXT NOT NULL,
            question TEXT NOT NULL,
            details TEXT,
            question_type TEXT NOT NULL,
            subtype TEXT,
            palace_m INTEGER NOT NULL,
            palace_d INTEGER NOT NULL,
            palace_t INTEGER NOT NULL,
            unlocked INTEGER NOT NULL DEFAULT 0,
            lang TEXT NOT NULL DEFAULT 'zh',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_pans_user ON divination_pans (user_id, id)",
        "CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            pan_key TEXT,
            pan_id INTEGER,
            amount_cents INTEGER NOT NULL,
            status TEXT NOT NULL,
            pay_channel TEXT NOT NULL,
            out_trade_no TEXT NOT NULL UNIQUE,
            channel_txn_id TEXT,
            claimed_at TEXT,
            paid_at TEXT,
            refunded_at TEXT,
            note TEXT,
            meta TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            expires_at TEXT
        )",
        "CREATE INDEX IF NOT EXISTS idx_orders_user ON orders (user_id, id)",
        "CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status, created_at)",
        "CREATE TABLE IF NOT EXISTS unlocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            pan_key TEXT NOT NULL UNIQUE,
            pan_id INTEGER,
            order_id INTEGER,
            source TEXT NOT NULL,
            created_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_unlocks_user ON unlocks (user_id)",
        "CREATE TABLE IF NOT EXISTS share_clicks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pan_key TEXT NOT NULL,
            referrer TEXT,
            ip_hash TEXT,
            ua_hash TEXT,
            created_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_share_pan ON share_clicks (pan_key)",
        "CREATE TABLE IF NOT EXISTS settings (
            k TEXT PRIMARY KEY,
            v TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS interpretations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pattern_key TEXT NOT NULL,
            question_type TEXT NOT NULL,
            lang TEXT NOT NULL,
            rich_text TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (pattern_key, question_type, lang)
        )",
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            pass_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            actor TEXT NOT NULL,
            action TEXT NOT NULL,
            target TEXT,
            detail TEXT,
            created_at TEXT NOT NULL
        )",
    ];
}

function db_install(): array
{
    $done = [];
    foreach (db_schema() as $sql) {
        db()->exec($sql);
        $done[] = strtok($sql, "\n");
    }
    return $done;
}

function db_audit(string $actor, string $action, ?string $target = null, $detail = null): void
{
    db_exec(
        'INSERT INTO audit_logs (actor, action, target, detail, created_at) VALUES (?, ?, ?, ?, ?)',
        [$actor, $action, $target, is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE), lfun_now()]
    );
}
