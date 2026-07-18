<x-mail::message>
# Account Deleted

Hello {{ $user->name }},

We're writing to inform you that your account has been **permanently deleted** from **{{ config('app.name') }}**.

As a result, all associated account data has been removed and can no longer be recovered.

If you believe this action was taken in error or you have any questions, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
