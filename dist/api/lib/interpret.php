<?php
declare(strict_types=1);

require_once LFUN_API_DIR . '/lib/content.php';

/** 三宫关系分析：与前端 analyzePath 逻辑一致（README_HANDOFF §4）。 */
function interpret_path(string $lang, array $pan): array
{
    $lib = content_library($lang);
    $m = (int) $pan['palace_m'];
    $d = (int) $pan['palace_d'];
    $t = (int) $pan['palace_t'];
    $names = [content_palace($lib, $m)['name'], content_palace($lib, $d)['name'], content_palace($lib, $t)['name']];
    $tone = [1, -1, 1, -1, 1, -1];
    $zh = $lang === 'zh';

    if ($m === $d && $d === $t) {
        return [
            'title' => $zh ? '三宫同位 · 主题集中' : 'All three palaces align',
            'text' => $zh
                ? '月、日、时都落在' . $names[0] . '，表示起点、过程与当前结果指向同一主题。这个特征会更明显，但仍需以现实证据验证。'
                : 'The month, day and hour palaces all land on ' . $names[0] . '. The theme repeats across the whole pattern, which makes it more visible but no more certain.',
        ];
    }
    if ($tone[$m] < 0 && $tone[$t] > 0) {
        return [
            'title' => $zh ? '前阻后顺 · 逐渐转开' : 'Blocked start, opening finish',
            'text' => $zh
                ? '由' . $names[0] . '起，经' . $names[1] . '而落' . $names[2] . '，整体从牵制转向较有利的行动条件。先处理前段卡点，再承接后段机会。'
                : 'The pattern starts at ' . $names[0] . ', passes through ' . $names[1] . ' and settles on ' . $names[2] . ': friction early, better conditions later. Clear the early blocker first, then take the opening.',
        ];
    }
    if ($tone[$m] > 0 && $tone[$t] < 0) {
        return [
            'title' => $zh ? '前顺后阻 · 需要收束' : 'Opening start, tightening finish',
            'text' => $zh
                ? '由' . $names[0] . '起，经' . $names[1] . '而落' . $names[2] . '，开始条件尚可，但后段出现阻力或信息缺口。宜在承诺前再检查一次。'
                : 'The pattern starts at ' . $names[0] . ', passes through ' . $names[1] . ' and ends at ' . $names[2] . ': a workable beginning, but resistance or missing information later. Re-check before committing.',
        ];
    }
    if ($m === $d || $d === $t || $m === $t) {
        return [
            'title' => $zh ? '宫位重复 · 主题强化' : 'Repeated palace, reinforced theme',
            'text' => $zh
                ? '路径为' . implode(' → ', $names) . '，其中宫位重复，说明相应主题在过程中再次出现。重复处就是最值得复查的环节。'
                : 'The path runs ' . implode(' → ', $names) . '. A palace repeats, so that theme returns during the process. The repeat point is what deserves a second look.',
        ];
    }
    return [
        'title' => $zh ? '三宫流转 · 边走边校准' : 'Three palaces in motion',
        'text' => $zh
            ? '路径为' . implode(' → ', $names) . '，起点、过程与结果各有侧重。适合分阶段判断，不宜只凭开端或某一次反馈下结论。'
            : 'The path runs ' . implode(' → ', $names) . '. Start, middle and result each carry a different emphasis: judge in stages rather than from the opening alone.',
    ];
}

/** 起盘三步说明（付费层）。 */
function interpret_steps(string $lang, array $pan): array
{
    $lib = content_library($lang);
    $zh = $lang === 'zh';
    $month = (int) $pan['lunar_month'];
    $day = (int) $pan['lunar_day'];
    $leap = !empty($pan['lunar_leap']);
    $m = content_palace($lib, (int) $pan['palace_m']);
    $dp = content_palace($lib, (int) $pan['palace_d']);
    $tp = content_palace($lib, (int) $pan['palace_t']);
    $hourIndex = (int) $pan['hour_index'];
    $branch = $zh ? (LFUN_BRANCHES[$hourIndex] ?? '') : (LFUN_BRANCHES_EN[$hourIndex] ?? '');

    if ($zh) {
        return [
            ['title' => '月上起日 · 找到起点', 'text' => '农历' . ($leap ? '闰' : '') . $month . ' 月，从大安作为正月起点，顺序数到第 ' . $month . ' 位，落在 ' . $m['name'] . '。月宫看事情的起始环境：' . $m['nature']],
            ['title' => '日上起时 · 观察过程', 'text' => '以月宫 ' . $m['name'] . ' 作为初一，再数到农历 ' . $day . ' 日，落在 ' . $dp['name'] . '。日宫看事情如何发展：' . $dp['process']],
            ['title' => '时辰落宫 · 得到主判断', 'text' => $branch . '时为第 ' . ($hourIndex + 1) . ' 个时辰，从日宫 ' . $dp['name'] . ' 起数，最终落在 ' . $tp['name'] . '。时宫作为本次判断的主结果：' . $tp['nature']],
        ];
    }
    return [
        ['title' => 'Month counts to the start', 'text' => 'Lunar month ' . ($leap ? '(leap) ' : '') . $month . ' counts from Da An and lands on ' . $m['name'] . ' — ' . $m['brief']],
        ['title' => 'Day shows the process', 'text' => 'Lunar day ' . $day . ' counts from ' . $m['name'] . ' and lands on ' . $dp['name'] . ' — ' . $dp['brief']],
        ['title' => 'Hour settles the answer', 'text' => 'The ' . $branch . ' hour counts from ' . $dp['name'] . ' and lands on ' . $tp['name'] . ' — ' . $tp['brief']],   // $branch 已按语言本地化
    ];
}

/** 主渲染：生成付费详细解读的通用区块（前端只负责渲染，不再内置文案）。 */
function interpret_detail(string $lang, string $type, array $pan, string $question, string $details): array
{
    $lib = content_library($lang);
    $zh = $lang === 'zh';
    $t = (int) $pan['palace_t'];
    $d = (int) $pan['palace_d'];
    $tp = content_palace($lib, $t);
    $dp = content_palace($lib, $d);
    $sub = content_subtype($lang, $type, trim($question . ' ' . $details));
    $deep = content_deep($lang, $type, $d, $t);
    $cat = content_category($lib, $type);
    $sections = [];

    $sections[] = ['type' => 'steps', 'title' => $zh ? '起盘过程 · 可以逐步复查' : 'How the pan was cast', 'items' => interpret_steps($lang, $pan)];

    $path = interpret_path($lang, $pan);
    $sections[] = [
        'type'  => 'path',
        'title' => $path['title'],
        'text'  => $path['text'],
        'line'  => [
            content_palace($lib, (int) $pan['palace_m'])['name'],
            $dp['name'],
            $tp['name'],
        ],
    ];

    if ($type === 'lost') {
        $base = $lib['lost']['base'][$t] ?? [];
        $combo = $lib['lost']['combos'][$d . '-' . $t] ?? [];
        $reading = array_merge(
            ['chance' => '', 'direction' => '', 'position' => '', 'timing' => ''],
            is_array($base) ? $base : [],
            is_array($combo) ? $combo : []
        );
        $actions = $lib['lost']['actions'][$lang] ?? null;
        if (!is_array($actions)) {
            $actions = $zh
                ? ['从最后一次确定见到的位置开始倒序。', '按指示方位分区搜索，每一区彻底翻查一次。', '联系同行人、工作人员和失物招领。']
                : ['Start from the last place you are sure you saw it.', 'Search one zone completely, then move outward.', 'Contact companions, staff and lost-property services.'];
        }
        $sections[] = [
            'type'      => 'lost',
            'title'     => $zh ? '寻物专断 · 日时合参' : 'Lost item reading',
            'combination' => $dp['name'] . ($zh ? '＋' : ' + ') . $tp['name'],
            'chance'    => (string) $reading['chance'],
            'items'     => [
                ['label' => $zh ? '优先方位' : 'Direction', 'text' => (string) $reading['direction']],
                ['label' => $zh ? '可能位置' : 'Likely place', 'text' => (string) $reading['position']],
                ['label' => $zh ? '时间与节奏' : 'Timing', 'text' => (string) $reading['timing']],
            ],
            'actions'   => array_values($actions),
            'deep'      => $deep,
            'note'      => $zh
                ? '本断法先看日宫与时宫组合，再以落宫补充位置。方位属于传统象意，只用于缩小范围；贵重物品、证件或电子设备请同步使用定位、挂失、监控和失物招领。'
                : 'This reading combines the day and hour palaces. Directions are traditional symbolism used only to narrow a search; use tracking, account protection and lost-property services where relevant.',
        ];
    } else {
        $verdict = content_at($cat['verdict'], $t, $tp['brief']);
        $focus = content_at($cat['focus'], $t, $tp['focus']);
        $pace = content_at($lib['paces'], $t, '');
        $signal = (string) $cat['signal'];
        $scores = [2, -1, 1, -2, 2, -2];
        $delta = $scores[$t] - $scores[$d];
        $transition = ($zh)
            ? ($d === $t
                ? '日宫与时宫同落' . $tp['name'] . '，主题被强化。'
                : ($delta > 0
                    ? '由' . $dp['name'] . '转' . $tp['name'] . '，后段条件比过程阶段改善。'
                    : ($delta < 0
                        ? '由' . $dp['name'] . '转' . $tp['name'] . '，后段阻力增加，需要收缩承诺。'
                        : '由' . $dp['name'] . '转' . $tp['name'] . '，有得有失，应以现实信号定夺。')))
            : ($d === $t
                ? $tp['name'] . ' repeats from the day palace into the result, so its theme is reinforced.'
                : ($delta > 0
                    ? 'The pattern moves from ' . $dp['name'] . ' to ' . $tp['name'] . '; conditions improve toward the result.'
                    : ($delta < 0
                        ? 'The pattern moves from ' . $dp['name'] . ' to ' . $tp['name'] . '; resistance increases toward the result.'
                        : 'The pattern moves from ' . $dp['name'] . ' to ' . $tp['name'] . '; the trade-off remains mixed.')));

        $sections[] = ['type' => 'verdict', 'label' => $zh ? '直接判断' : 'Direct answer', 'text' => $verdict];
        $sections[] = [
            'type'  => 'grid',
            'items' => [
                ['label' => $zh ? '发展过程' : 'How it moves', 'text' => $transition],
                ['label' => $zh ? '时间节奏' : 'Timing', 'text' => $pace . ($zh ? '' : ' The reading describes a rhythm, not a guaranteed deadline.')],
                ['label' => $zh ? '关键着眼点' : 'What to watch', 'text' => $focus],
                ['label' => $zh ? '出现什么才算应验' : 'Confirming signal', 'text' => $signal],
            ],
        ];

        $sections[] = [
            'type'  => 'palace_focus',
            'title' => $zh ? $tp['name'] . ' 的重点 · 该看什么' : $tp['name'] . ' — what to look at',
            'items' => [
                ['label' => $zh ? '关注' : 'Focus', 'text' => $tp['focus']],
                ['label' => $zh ? '避免' : 'Avoid', 'text' => $tp['avoid']],
            ],
        ];

        $candidates = array_merge(
            [$sub['guidance']],
            is_array($cat['base_actions']) ? $cat['base_actions'] : [],
            [content_at($lib['palace_actions'], $t, '')]
        );
        $actions = [];
        foreach ($candidates as $a) {
            $a = trim((string) $a);
            if ($a !== '' && !in_array($a, $actions, true)) {
                $actions[] = $a;
            }
            if (count($actions) >= 3) {
                break;
            }
        }
        $sections[] = ['type' => 'actions', 'title' => $zh ? '现在怎么做' : 'Practical next steps', 'items' => $actions];

        $emotion = $lib['emotions'][$t] ?? ['state' => '', 'risk' => '', 'balance' => ''];
        $sections[] = [
            'type'  => 'emotion',
            'title' => $zh ? '心理与情绪' : 'Emotional pattern',
            'text'  => (string) $emotion['state'],
            'items' => [
                ['label' => $zh ? '容易陷入' : 'Risk', 'text' => (string) $emotion['risk']],
                ['label' => $zh ? '稳定方法' : 'Balance', 'text' => (string) $emotion['balance']],
            ],
        ];

        $closing = content_at($cat['closing'], $t, '');
        if ($closing === '') {
            $closing = $zh
                ? '此课落' . $tp['name'] . '。' . $verdict . ' 以' . $focus . '为重点，按现实反馈调整下一步。'
                : 'This reading ends in ' . $tp['name'] . '. ' . $verdict . ' Work with ' . lcfirst($focus) . ' and let verified events decide what follows.';
        }
        $sections[] = ['type' => 'closing', 'label' => $zh ? '解卦语' : 'Closing', 'text' => $closing];
    }

    if ($deep !== '') {
        array_unshift($sections, [
            'type'  => 'combination',
            'title' => $zh
                ? '日时合参 · ' . $dp['name'] . '＋' . $tp['name']
                : 'Day–hour combination · ' . $dp['name'] . ' + ' . $tp['name'],
            'text'  => $deep,
        ]);
    }

    return [
        'lang'        => $lang,
        'category'    => $type,
        'label'       => (string) ($cat['label'] ?? $type),
        'subtype'     => $sub,
        'palace'      => ['name' => $tp['name'], 'sub' => $tp['sub'], 'brief' => $tp['brief']],
        'combination' => $dp['name'] . ($zh ? '＋' : ' + ') . $tp['name'],
        'pattern_key' => 'day:' . $d . '|time:' . $t,
        'has_deep'    => $deep !== '',
        'sections'    => $sections,
        'notice'      => $zh
            ? '以上为传统文化娱乐解读，仅供娱乐，不构成医疗、法律、财务或安全建议，也不保证任何结果发生。'
            : 'This is a traditional-culture entertainment reading only. It is not medical, legal, financial or safety advice and guarantees no outcome.',
    ];
}
