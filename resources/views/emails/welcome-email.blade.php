<x-mail::message>
# Welcome, {{ $user->name }}! 🎉

Thank you for joining **{{ config('app.name') }}**.

Your account has been created successfully, and you can now start using all the features available on our platform.

<x-mail::button :url="config('app.url')">
Go to {{ config('app.name') }}
</x-mail::button>

If you have any questions or need assistance, feel free to contact our support team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
