<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_number')->nullable()->unique()->after('email');
        });

        DB::table('users')->whereNull('account_number')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['account_number' => $this->generateUniqueAccountNumber()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_account_number_unique');
            $table->dropColumn('account_number');
        });
    }

    private function generateUniqueAccountNumber(): string
    {
        do {
            $accountNumber = 'ACC-'.strtoupper(Str::random(10));
        } while (DB::table('users')->where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }
};
