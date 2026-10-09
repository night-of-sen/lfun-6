<?php
declare(strict_types=1);

/**
 * 内容库：现有站内文案（由 tools/build-content.js 生成）+ 人工覆盖 + 付费组合断语。
 * 服务端是付费内容的唯一权威来源，未解锁时不会下发（PRD 模块二 2.5）。
 */

const LFUN_CATEGORY_ORDER = ['work', 'cooperation', 'relationship', 'finance', 'study', 'health', 'travel', 'lost', 'general'];

function content_merge(array $base, array $over): array
{
    foreach ($over as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
            $base[$k] = content_merge($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}

function content_library(string $lang = 'zh'): array
{
    static $cache = [];
    $lang = in_array($lang, ['zh', 'en'], true) ? $lang : 'zh';
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $base = require LFUN_API_DIR . '/data/content.' . $lang . '.php';
    $overFile = LFUN_API_DIR . '/data/overrides.' . $lang . '.php';
    if (is_file($overFile)) {
        $over = require $overFile;
        if (is_array($over)) {
            $base = content_merge($base, $over);
        }
    }
    $deepFile = LFUN_API_DIR . '/data/deep.' . $lang . '.php';
    $base['deep'] = is_file($deepFile) ? (require $deepFile) : [];
    if (!is_array($base['deep'])) {
        $base['deep'] = [];
    }
    $cache[$lang] = $base;
    return $base;
}

function content_palace(array $lib, int $i): array
{
    $p = $lib['palaces'][$i] ?? ['name' => '—', 'sub' => '', 'brief' => ''];
    return array_merge(['nature' => '', 'process' => '', 'focus' => '', 'avoid' => ''], $p);
}

function content_category(array $lib, string $type): array
{
    $defaults = [
        'label' => $type, 'base_actions' => [], 'guidance' => array_fill(0, 6, ''),
        'verdict' => array_fill(0, 6, ''), 'focus' => array_fill(0, 6, ''),
        'closing' => array_fill(0, 6, ''), 'signal' => '', 'subtypes' => [],
    ];
    $c = $lib['categories'][$type] ?? [];
    return array_merge($defaults, is_array($c) ? $c : []);
}

function content_at($arr, int $i, string $fallback = ''): string
{
    if (!is_array($arr) || !array_key_exists($i, $arr)) {
        return $fallback;
    }
    $v = $arr[$i];
    return is_string($v) ? $v : (string) $v;
}

/** 付费组合断语：后台覆盖 > 内置文案库 > 空（由调用方回退）。 */
function content_deep(string $lang, string $type, int $d, int $t): string
{
    $patternKey = 'day:' . $d . '|time:' . $t;
    try {
        $row = db_one(
            'SELECT rich_text FROM interpretations WHERE pattern_key = ? AND question_type = ? AND lang = ?',
            [$patternKey, $type, $lang]
        );
        if ($row && trim((string) $row['rich_text']) !== '') {
            return (string) $row['rich_text'];
        }
    } catch (Throwable $e) {
        // 表不存在时忽略，继续走文件库
    }
    $lib = content_library($lang);
    $table = $lib['deep'][$type] ?? null;
    if (is_array($table)) {
        $key = $d . '-' . $t;
        if (!empty($table[$key]) && is_string($table[$key])) {
            return $table[$key];
        }
    }
    return '';
}

/** 免费层简版断语（PRD 模块二 2.2）。 */
function content_brief(string $lang, string $type, array $pan): string
{
    $lib = content_library($lang);
    $t = (int) $pan['palace_t'];
    if ($type === 'lost') {
        $base = $lib['lost']['base'][$t] ?? null;
        if (is_array($base)) {
            return (string) ($base['chance'] ?? '');
        }
        return '';
    }
    $cat = content_category($lib, $type);
    $v = content_at($cat['verdict'], $t);
    if ($v !== '') {
        return $v;
    }
    return (string) (content_palace($lib, $t)['brief'] ?? '');
}

/** 匹配关键词子类。 */
function content_subtype(string $lang, string $type, string $text): array
{
    $lib = content_library($lang);
    $cat = content_category($lib, $type);
    $subs = is_array($cat['subtypes']) ? $cat['subtypes'] : [];
    foreach ($subs as $s) {
        $pattern = (string) ($s['pattern'] ?? '');
        if ($pattern === '') {
            continue;
        }
        $flags = (string) ($s['flags'] ?? 'iu');
        if (strpos($flags, 'u') === false) {
            $flags .= 'u';
        }
        $delimited = '#' . str_replace('#', '\\#', $pattern) . '#' . $flags;
        if (@preg_match($delimited, $text) === 1) {
            return ['label' => (string) ($s['label'] ?? ''), 'guidance' => (string) ($s['guidance'] ?? '')];
        }
    }
    return [
        'label' => (string) ($cat['label'] ?? ''),
        'guidance' => content_at($cat['base_actions'], 0, content_at($cat['guidance'], 0)),
    ];
}

/** 自动分类：与前端 dist/app.js 的 classifyQuestion 保持同一套词表。 */
function content_classify(string $lang, string $question, string $selected = 'auto'): array
{
    $lib = content_library($lang);
    if ($selected !== '' && $selected !== 'auto' && isset($lib['categories'][$selected])) {
        return ['category' => $selected, 'reason' => 'manual', 'score' => 99];
    }
    if ($selected === 'lost' || $selected === 'general') {
        return ['category' => $selected, 'reason' => 'manual', 'score' => 99];
    }
    $rules = is_array($lib['classify_rules'] ?? null) ? $lib['classify_rules'] : [];
    $text = function_exists('mb_strtolower') ? mb_strtolower($question, 'UTF-8') : strtolower($question);
    $scores = [];
    $hits = [];
    foreach ($rules as $cat => $words) {
        $scores[$cat] = 0;
        $hits[$cat] = [];
        foreach ((array) $words as $w) {
            $w = (string) $w;
            if ($w === '') {
                continue;
            }
            $needle = function_exists('mb_strtolower') ? mb_strtolower($w, 'UTF-8') : strtolower($w);
            if (strpos($text, $needle) !== false) {
                $scores[$cat] += (mb_strlen($w, 'UTF-8') >= 3) ? 3 : 2;
                $hits[$cat][] = $w;
            }
        }
    }
    arsort($scores);
    $cat = array_key_first($scores);
    $score = $cat !== null ? $scores[$cat] : 0;
    if (!$cat || $score <= 0) {
        return ['category' => 'general', 'reason' => 'no_keyword', 'score' => 0];
    }
    return ['category' => $cat, 'reason' => 'keyword', 'score' => (int) $score, 'hits' => array_slice($hits[$cat], 0, 2)];
}

function content_clarify(string $lang, string $type): string
{
    $lib = content_library($lang);
    $c = $lib['clarify'] ?? [];
    return (string) ($c[$type] ?? ($c['general'] ?? ''));
}
