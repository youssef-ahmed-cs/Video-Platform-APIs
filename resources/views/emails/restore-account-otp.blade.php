<x-mail::message>
# Restore Your Account

Use this OTP to restore your deleted account:

# {{ $otp }}

This code expires in **5 minutes**.

If you did not request account restoration, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
