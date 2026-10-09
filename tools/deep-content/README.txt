⚠️ 这个目录只用于「追溯第一批文案是怎么来的」，不是内容源。

内容源（唯一权威、可以直接手改）：
  dist/api/data/deep.zh.php    中文 288 条日宫×时宫组合断语
  dist/api/data/deep.en.php    英文 288 条
  dist/api/data/overrides.zh.php / overrides.en.php   人工覆盖层（寻物组合、英文补全）

build-deep-zh.mjs 是当初一次性生成 deep.zh.php 的脚本，内容已内联在脚本里。
**不要再运行它**：会把你在 deep.zh.php 里手改的文案全部覆盖掉。

要在哪些情况下用它：
  · 想把首批 288 条整体重写一遍（先在 git 里确认没有需要保留的手改）
  · 想批量改措辞后重新生成（改脚本再跑，跑完 diff 复核）

日常改单条文案：直接编辑 dist/api/data/deep.zh.php，
或者用管理后台 /api/admin/ → 「解读文案库」写入覆盖（覆盖优先级最高，存数据库，随改随生效）。
