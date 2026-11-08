<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ __('Label') }} {{ $asset->tag }}</title>
<style>
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#eef1f5}
  .bar{padding:12px 16px;font-size:13px;color:#556} .bar button{margin-left:8px}
  .label{width:62mm;height:29mm;box-sizing:border-box;margin:16px;padding:2.5mm;background:#fff;border:1px dashed #bbb;display:flex;gap:2.5mm;align-items:center}
  .qr{width:24mm;height:24mm;flex:none} .qr svg,.qr img{width:100%;height:100%}
  .txt{overflow:hidden} .tag{font-family:Menlo,Consolas,monospace;font-weight:700;font-size:13pt;line-height:1.1}
  .name{font-size:8.5pt;line-height:1.2;margin-top:1mm;max-height:2.4em;overflow:hidden} .hint{font-size:6.5pt;color:#555;margin-top:1.2mm}
  @media print{body{background:#fff}.bar{display:none}.label{margin:0;border:0}@page{size:62mm 29mm;margin:0}}
</style>
</head>
<body>
<div class="bar">{{ __('Label 62 × 29 mm (e.g. a Brother or DYMO label printer).') }} <button onclick="window.print()">{{ __('Print') }}</button></div>
<div class="label">
    <div class="qr" id="qr" data-url="{{ route('mfsassets.qr', ['token' => $asset->qr_token]) }}"></div>
    <div class="txt">
        <div class="tag">{{ $asset->tag }}</div>
        <div class="name">{{ $asset->name }}</div>
        <div class="hint">{{ __('Scan to report a problem') }}</div>
    </div>
</div>
<script src="{{ \Module::getPublicPath('mfsassets') }}/js/vendor/qrcode.js?v=1.4.4"></script>
<script>
  (function () {
    var el = document.getElementById('qr'), q = qrcode(0, 'M');
    q.addData(el.getAttribute('data-url')); q.make();
    el.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  })();
</script>
</body>
</html>
