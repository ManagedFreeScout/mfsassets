<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('Report a problem') }} – {{ $asset->tag }}</title>
<style>
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f4f6f9;color:#223;}
  .box{max-width:460px;margin:40px auto;background:#fff;border-radius:10px;padding:26px 28px;box-shadow:0 6px 24px rgba(0,0,0,.08)}
  h1{font-size:20px;margin:0 0 4px} .tag{font-family:Menlo,Consolas,monospace;color:#a84000;font-weight:600}
  label{display:block;font-size:13px;margin:14px 0 4px;color:#556} input,textarea{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid #ccd3dc;border-radius:6px;font:inherit}
  textarea{min-height:110px} button{margin-top:16px;width:100%;padding:11px;border:0;border-radius:6px;background:#ef6c00;color:#fff;font-size:15px;cursor:pointer}
  .err{color:#b00020;font-size:13px;margin-top:4px} .ok{background:#e3f4e5;color:#1b5e20;padding:12px 14px;border-radius:6px} .muted{color:#778;font-size:13px}
  .trap{position:absolute;left:-5000px}
</style>
</head>
<body>
<div class="box">
    <div class="muted">{{ __('Asset') }}</div>
    <h1><span class="tag">{{ $asset->tag }}</span> {{ $asset->name }}</h1>
    @if ($sent)
        <p class="ok">{{ __('Thank you. Your report has been sent; you will get an answer by e-mail.') }}</p>
    @elseif ($mailbox)
        <p class="muted">{{ __('Something wrong with this item? Tell us and we\'ll get back to you by e-mail.') }}</p>
        <form method="POST" action="{{ route('mfsassets.report', ['token' => $asset->qr_token]) }}">
            {{ csrf_field() }}
            <div class="trap" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <label for="r-name">{{ __('Your name') }}</label>
            <input id="r-name" type="text" name="name" maxlength="100" required value="{{ old('name') }}">
            @if ($errors->has('name'))<div class="err">{{ $errors->first('name') }}</div>@endif
            <label for="r-email">{{ __('Your e-mail address') }}</label>
            <input id="r-email" type="email" name="email" maxlength="191" required value="{{ old('email') }}">
            @if ($errors->has('email'))<div class="err">{{ $errors->first('email') }}</div>@endif
            <label for="r-desc">{{ __('What is the problem?') }}</label>
            <textarea id="r-desc" name="description" maxlength="5000" required>{{ old('description') }}</textarea>
            @if ($errors->has('description'))<div class="err">{{ $errors->first('description') }}</div>@endif
            <button type="submit">{{ __('Send report') }}</button>
        </form>
    @else
        <p class="muted">{{ __('This item is registered. Please contact your helpdesk if something is wrong with it.') }}</p>
    @endif
    <p class="muted" style="margin-top:20px"><a href="{{ route('login') }}">{{ __('Helpdesk agents: log in') }}</a></p>
</div>
</body>
</html>
