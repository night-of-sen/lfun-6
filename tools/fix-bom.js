/* 给 tools/*.ps1 补 UTF-8 BOM。
 * PowerShell 5.1 没有 BOM 时会把中文读成乱码，报「字符串缺少终止符」之类莫名其妙的语法错。
 * 用法：node tools/fix-bom.js
 */
const fs = require('node:fs');
const path = require('node:path');
const dir = __dirname;
let fixed = 0, ok = 0;
for (const f of fs.readdirSync(dir).filter((n) => n.endsWith('.ps1'))) {
  const p = path.join(dir, f);
  const buf = fs.readFileSync(p);
  if (buf[0] === 0xEF && buf[1] === 0xBB && buf[2] === 0xBF) { ok++; continue; }
  fs.writeFileSync(p, Buffer.concat([Buffer.from([0xEF, 0xBB, 0xBF]), buf]));
  console.log('已补 BOM: ' + f);
  fixed++;
}
console.log('完成：新补 ' + fixed + ' 个，本来就正常 ' + ok + ' 个。');