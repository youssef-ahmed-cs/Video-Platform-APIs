<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('podcasts', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('description');
            $table->string('cover_image_url')->nullable()->after('cover_image_path');
            $table->unsignedInteger('episode_number')->nullable()->after('duration');
            $table->unsignedInteger('season_number')->nullable()->after('episode_number');
        });
    }

    public function down(): void
    {
        Schema::table('podcasts', function (Blueprint $table) {
            $table->dropColumn([
                'cover_image_path',
                'cover_image_url',
                'episode_number',
                'season_number',
            ]);
        });
    }
};
