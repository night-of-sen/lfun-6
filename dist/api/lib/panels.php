<?php
declare(strict_types=1);

/**
 * 服务端起盘。规则与前端 dist/app.js 完全一致（README_HANDOFF §4），
 * 目的是让付费解读的盘面由服务端核定，客户端无法伪造。
 */

const LFUN_BRANCHES = ['子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥'];
const LFUN_BRANCHES_EN = ['Zi', 'Chou', 'Yin', 'Mao', 'Chen', 'Si', 'Wu', 'Wei', 'Shen', 'You', 'Xu', 'Hai'];

/** 按固定公式计算三宫。索引 0..5 = 大安、留连、速喜、赤口、小吉、空亡。 */
function pan_calculate(int $month, int $day, int $hour): array
{
    if ($month < 1 || $month > 12) {
        throw new InvalidArgumentException('农历月份无效');
    }
    if ($day < 1 || $day > 30) {
        throw new InvalidArgumentException('农历日期无效');
    }
    if ($hour < 0 || $hour > 23) {
        throw new InvalidArgumentException('小时无效');
    }
    $hourIndex = intdiv(($hour + 1) % 24, 2);
    $m = ($month - 1) % 6;
    $d = ($m + $day - 1) % 6;
    $t = ($d + $hourIndex) % 6;
    return ['m' => $m, 'd' => $d, 't' => $t, 'hourIndex' => $hourIndex];
}

/**
 * 中国农历换算。优先用 ICU（与浏览器 Intl 同一套数据），
 * 不可用时回退到客户端提交的农历值（仍由服务端校验范围与时辰）。
 */
function pan_lunar(DateTimeImmutable $utc): ?array
{
    if (!class_exists('IntlCalendar')) {
        return null;
    }
    try {
        $local = $utc->setTimezone(new DateTimeZone('Asia/Shanghai'));
        $cal = IntlCalendar::createInstance('Asia/Shanghai', 'en_US@calendar=chinese');
        if (!$cal) {
            return null;
        }
        $cal->setTime(((int) $local->format('U')) * 1000);
        $month = (int) $cal->get(IntlCalendar::FIELD_MONTH) + 1;
        $day = (int) $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH);
        $leap = false;
        // ICU 的 isLeapMonth() 在部分版本不可用，用月名判断更稳。
        $fmt = new IntlDateFormatter(
            'en_US@calendar=chinese',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Asia/Shanghai',
            IntlDateFormatter::TRADITIONAL,
            'MMMM'
        );
        if ($fmt) {
            $name = (string) $fmt->format($local);
            $leap = (bool) preg_match('/bis|闰|leap/i', $name);
        }
        if (method_exists($cal, 'isLeapMonth')) {
            try {
                $leap = $leap || (bool) $cal->isLeapMonth();
            } catch (Throwable $e) {
                // 忽略：以月名判断为准
            }
        }
        return ['month' => $month, 'day' => $day, 'leap' => $leap];
    } catch (Throwable $e) {
        return null;
    }
}

/** 由 ISO8601 时间生成完整盘面（服务端权威版本）。 */
function pan_build(string $iso, ?string $clientMonth = null, ?string $clientDay = null, ?bool $clientLeap = null): array
{
    try {
        $utc = new DateTimeImmutable($iso);
    } catch (Throwable $e) {
        throw new InvalidArgumentException('起盘时间无效');
    }
    $local = $utc->setTimezone(new DateTimeZone('Asia/Shanghai'));
    $year = (int) $local->format('Y');
    if ($year < 1901 || $year > 2100) {
        throw new InvalidArgumentException('请选择 1901 至 2100 年的时间');
    }

    $lunar = pan_lunar($utc);
    $source = 'server';
    if (!$lunar) {
        $source = 'client';
        if ($clientMonth === null || $clientDay === null) {
            throw new RuntimeException('服务器无法换算农历，请提交客户端农历数据');
        }
        $lunar = [
            'month' => (int) $clientMonth,
            'day'   => (int) $clientDay,
            'leap'  => (bool) $clientLeap,
        ];
    } elseif ($clientMonth !== null && $clientDay !== null) {
        // 交叉校验：差异过大说明客户端时间或时区异常，以服务端为准并记录。
        if (abs((int) $clientMonth - (int) $lunar['month']) > 0 || abs((int) $clientDay - (int) $lunar['day']) > 0) {
            $source = 'server_mismatch';
        }
    }

    $hour = (int) $local->format('G');
    $p = pan_calculate((int) $lunar['month'], (int) $lunar['day'], $hour);

    return [
        'iso'          => $utc->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        'lunar_month'  => (int) $lunar['month'],
        'lunar_day'    => (int) $lunar['day'],
        'lunar_leap'   => (bool) $lunar['leap'],
        'lunar_source' => $source,
        'hour_index'   => $p['hourIndex'],
        'branch'       => LFUN_BRANCHES[$p['hourIndex']],
        'solar_label'  => $local->format('Y-m-d H:i:s'),
        'lunar_label'  => '农历' . (!empty($lunar['leap']) ? '闰' : '') . (int) $lunar['month'] . '月' . (int) $lunar['day'] . '日',
        'palace_m'     => $p['m'],
        'palace_d'     => $p['d'],
        'palace_t'     => $p['t'],
    ];
}

/** 盘面唯一键：同一用户 + 同一时刻 + 同一问题类别 = 同一盘（PRD 模块二 2.2 解锁记录与盘绑定）。 */
function pan_key_for(int $userId, string $iso, string $questionType, string $lang = 'zh'): string
{
    $minute = substr($iso, 0, 16);
    return substr(sha1(implode('|', [$userId, $minute, $questionType, $lang])), 0, 40);
}
