<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
:root {
color-scheme: light dark;
supported-color-schemes: light dark;
}

/* Our own dark palette, so clients that honour it stop auto-inverting the light theme. [data-ogsb] targets Outlook.com. */
@media (prefers-color-scheme: dark) {
body, .wrapper, .body { background-color: #0b1426 !important; border-color: #0b1426 !important; }
.header { background-color: #000e2f !important; }
.inner-body { background-color: #16213a !important; border-color: #2a3550 !important; }
body, p, td, .table td, .panel-content, .panel-content p { color: #e8ebef !important; }
h1, h2, h3, .detail-title { color: #ffffff !important; }
.footer p, .footer a, .muted, .label, .detail-meta { color: #a6b0bf !important; }
a { color: #5fc4d0 !important; }
.header a, .button { color: #ffffff !important; }
.panel { border-color: #2a3550 !important; }
.panel-content { background-color: #1d2944 !important; }
.subcopy, .table th { border-color: #2a3550 !important; }
}

[data-ogsb] .wrapper, [data-ogsb] .body { background-color: #0b1426 !important; }
[data-ogsb] .header { background-color: #000e2f !important; }
[data-ogsb] .inner-body { background-color: #16213a !important; }
[data-ogsb] .panel-content { background-color: #1d2944 !important; }
[data-ogsc] p, [data-ogsc] td { color: #e8ebef !important; }
[data-ogsc] h1, [data-ogsc] .header a, [data-ogsc] .button { color: #ffffff !important; }
[data-ogsc] .footer p, [data-ogsc] .mwuted { color: #a6b0bf !important; }

@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
