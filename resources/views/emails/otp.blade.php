@php use App\Helpers\Trans; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $otp }}</title>
</head>
<body style="font-family: 'Cairo', sans-serif; background-color: #f4f4f4; padding: 20px; text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }};">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px;">
        <h2 style="color: #333;">{{ Trans::get('api.email_otp_greeting', ['name' => $name]) }}</h2>
        <p style="color: #666; font-size: 16px;">
            {{ Trans::get('api.email_otp_intro') }}
        </p>
        <div style="font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 5px; margin: 20px 0;">
            {{ $otp }}
        </div>
        <p style="color: #666; font-size: 14px;">
            {{ Trans::get('api.email_otp_expiry') }}
        </p>
        <p style="color: #999; font-size: 12px; margin-top: 30px;">
            {{ Trans::get('api.email_otp_ignore') }}
        </p>
    </div>
</body>
</html>
