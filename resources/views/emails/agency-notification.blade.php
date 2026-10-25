<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $details['category'] }} | Culbertson Agency</title></head>
<body style="margin:0;padding:0;background:#f3f7fa;font-family:Arial,Helvetica,sans-serif;color:#15364b">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f7fa"><tr><td style="padding:24px 12px">
<table role="presentation" width="900" align="center" cellspacing="0" cellpadding="0" style="width:100%;max-width:900px;background:#ffffff;border:1px solid #cbd9e3;border-collapse:collapse">
<tr><td style="padding:24px;background:#102c3e;border-top:5px solid #087faf;color:#ffffff">
<p style="margin:0 0 22px;font-size:19px;font-weight:bold">Culbertson<span style="color:#67b9e3">Agency</span></p>
<p style="margin:0 0 18px;font-size:14px;font-weight:bold">Dear Admin,</p>
<p style="margin:0 0 7px;font-size:14px">{{ $details['heading'] }}</p>
<p style="margin:0;font-size:18px;line-height:1.5;font-weight:bold">{{ $details['category'] }}</p>
</td></tr>
<tr><td style="padding:16px 24px;background:#edf6fb;border-bottom:1px solid #cbd9e3;font-size:13px;line-height:1.6">{{ $details['intro'] }}</td></tr>
<tr><td>
<table width="100%" cellspacing="0" cellpadding="0" aria-label="Submission details" style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:14px">
@foreach($details['rows'] as $label=>$value)
<tr>
<th scope="row" width="32%" align="left" valign="top" style="width:32%;padding:14px 20px;background:#f6f9fc;border-right:1px solid #cbd9e3;border-bottom:1px solid #cbd9e3;font-weight:normal;line-height:1.6;overflow-wrap:anywhere">{{ $label }}</th>
<td valign="top" style="padding:14px 20px;border-bottom:1px solid #cbd9e3;font-weight:bold;line-height:1.6;overflow-wrap:anywhere;word-break:break-word;white-space:pre-line">@if($label==='Email' && filter_var($value,FILTER_VALIDATE_EMAIL))<a href="mailto:{{ $value }}" style="color:#087faf;text-decoration:underline">{{ $value }}</a>@else{{ $value }}@endif</td>
</tr>
@endforeach
</table>
</td></tr>
<tr><td style="padding:24px">
<table role="presentation" cellspacing="0" cellpadding="0"><tr><td bgcolor="#087faf" style="border-radius:4px"><a href="{{ $details['url'] }}" style="display:inline-block;padding:14px 22px;border:1px solid #087faf;border-radius:4px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none">{{ $details['linkLabel'] }} &rarr;</a></td></tr></table>
<p style="margin:18px 0 0;font-size:12px;color:#61788a;line-height:1.7">{{ $details['note'] }}</p>
</td></tr>
<tr><td style="padding:18px 24px;background:#102c3e;color:#dce9f1;font-size:11px;line-height:1.7">Culbertson Agency &bull; Website lead notifications<br>Private agency notification. Full quote answers and uploaded documents stay in the authenticated admin inbox.</td></tr>
</table>
</td></tr></table>
</body></html>
