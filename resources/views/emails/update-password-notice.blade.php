<x-mail::message>
# Password Updated

Hello {{ $user->name }},

Your password has been changed successfully.

If you made this change, you can safely ignore this email.

If you did not make this change, please reset your password immediately and contact support.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
