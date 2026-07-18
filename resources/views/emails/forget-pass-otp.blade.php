<x-mail::message>
    # Password Reset Request

    You requested to reset your password.

    ## OTP Code

    # {{ $otp }}

    This code will expire in **5 minutes**.

    If you did not request a password reset, you can safely ignore this email.

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>
