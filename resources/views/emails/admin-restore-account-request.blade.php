<x-mail::message>
# Account Activation Request

A user requested manual activation because they forgot their account number.

**User ID:** {{ $details['user_id'] }}  
**Name:** {{ $details['name'] }}  
**Email:** {{ $details['email'] }}  
**Username:** {{ $details['username'] ?? 'N/A' }}  
**Account Number (system record):** {{ $details['account_number'] ?? 'N/A' }}  
**Deleted At:** {{ $details['deleted_at'] ?? 'N/A' }}  
**Last Login At (provided):** {{ $details['last_login_at'] ?? 'N/A' }}

**Reason:**  
{{ $details['reason'] }}

Please review and activate the account if details are valid.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
