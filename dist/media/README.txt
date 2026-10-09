收款码放这里。

把微信与支付宝的收款码图片保存为：
  media/wxpay-qr.png
  media/alipay-qr.png

然后在 api/config.local.php 中填写相对路径：

'pay' => [
  'manual' => [
    'wx_qr'     => 'media/wxpay-qr.png',
    'alipay_qr' => 'media/alipay-qr.png',
  ],
],

建议：图片宽度 600–800px 的正方形。不要包含手机号等敏感信息。
