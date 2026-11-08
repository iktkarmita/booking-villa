<!DOCTYPE html>
<html>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;padding:24px;">
        @if ($brand !== '')
            <p style="margin:0 0 16px;font-weight:bold;color:#0f766e;font-size:18px;">{{ $brand }}</p>
        @endif
        <p style="margin:0;line-height:1.6;">{!! nl2br(e($body)) !!}</p>
        @if ($url)
            <p style="margin:24px 0 0;">
                <a href="{{ $url }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;">{{ $urlLabel ?: $url }}</a>
            </p>
        @endif
    </div>
</body>
</html>
