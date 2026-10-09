<?php
/* 内容库自检：条目完整性、覆盖率、合规红线。
 * 用法：php tests/verify-content.php
 */
declare(strict_types=1);
require_once __DIR__ . '/../dist/api/lib/bootstrap.php';
require_once __DIR__ . '/../dist/api/lib/content.php';

$fail = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$fail) {
    printf("  %s  %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $detail !== '' ? ' :: ' . $detail : '');
    if (!$ok) {
        $fail++;
    }
};

$order = ['work', 'cooperation', 'relationship', 'finance', 'study', 'health', 'travel', 'general'];
$banned = ['改运', '化解', '注定', '一定能', '成功率', '必应', '百分百', '保证准确', '预测准确', '包过'];

foreach (['zh', 'en'] as $lang) {
    echo "== $lang ==\n";
    $lib = content_library($lang);
    $check("palaces = 6", count($lib['palaces']) === 6, (string) count($lib['palaces']));
    $check("emotions = 6", count($lib['emotions']) === 6, (string) count($lib['emotions']));
    $check("lost base = 6", count($lib['lost']['base']) === 6, (string) count($lib['lost']['base']));

    $deepTotal = 0;
    $missing = [];
    foreach ($order as $cat) {
        $table = $lib['deep'][$cat] ?? null;
        $n = is_array($table) ? count($table) : 0;
        $deepTotal += $n;
        if ($n !== 36) {
            $missing[] = $cat . '(' . $n . ')';
        }
        for ($d = 0; $d < 6; $d++) {
            for ($t = 0; $t < 6; $t++) {
                $text = content_deep($lang, $cat, $d, $t);
                if ($text === '') {
                    $missing[] = $cat . ":$d-$t";
                }
            }
        }
        $c = content_category($lib, $cat);
        $check("$cat 有 6 条直接判断", count($c['verdict']) === 6 && $c['verdict'][5] !== '');
        $check("$cat 有应验信号", $c['signal'] !== '');
        $check("$cat 有子类", count($c['subtypes']) > 0, (string) count($c['subtypes']));
    }
    $check('付费组合断语 8 x 36 = 288', $deepTotal === 288, (string) $deepTotal);
    $check('所有组合均有回退（无空白）', count($missing) === 0, implode(',', array_slice($missing, 0, 8)));

    // 合规红线。先把「传统文化解释」这类无害搭配归一化，避免「化解」被跨词误伤。
    $blob = str_replace(['传统文化解释', '传统文化解读', '文化解释'], '[culture]', json_encode($lib, JSON_UNESCAPED_UNICODE));
    foreach ($banned as $w) {
        $check("禁用词「{$w}」零命中", strpos($blob, $w) === false);
    }
    if ($lang === 'zh') {
        foreach (['health', 'finance', 'travel'] as $cat) {
            $table = $lib['deep'][$cat] ?? [];
            $bad = 0;
            foreach ($table as $text) {
                $need = $cat === 'health'
                    ? ['医', '检查', '评估', '体检', '专业']
                    : ($cat === 'finance'
                        ? ['到账', '确认', '合同', '对账', '凭据', '可退出', '账户', '流水', '记账', '预算', '授权', '回报']
                        : ['官方', 'official']);
                $hit = false;
                foreach ($need as $n) {
                    if (strpos($text, $n) !== false) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    $bad++;
                    echo "      · 缺锚点: " . mb_substr($text, 0, 60, 'UTF-8') . "
";
                }
            }
            $check("$cat 组合断语均有专业/事实锚点", $bad === 0, "缺锚点 $bad 条");
        }
    }
}

echo "\n" . ($fail === 0 ? "全部通过。\n" : "失败 $fail 项。\n");
exit($fail === 0 ? 0 : 1);
