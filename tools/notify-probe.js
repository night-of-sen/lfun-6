/* 本地 webhook 接收端：用于实测站长通知推送链路。
 * 用法：node tools/notify-probe.js [port]
 * 每收到一条推送就打印一行 JSON；按 Ctrl+C 结束。 */
const http = require('node:http');
const PORT = Number(process.argv[2] || 8123);
let n = 0;
http.createServer((req, res) => {
  let body = '';
  req.on('data', (c) => { body += c; });
  req.on('end', () => {
    n++;
    console.log('--- #' + n + ' ' + req.method + ' ' + req.url);
    console.log(body);
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end('{"ok":true}');
  });
}).listen(PORT, '127.0.0.1', () => console.log('notify probe listening on http://127.0.0.1:' + PORT));
