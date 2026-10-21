<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->string('name')->collation('utf8mb4_bin')->change();
        });
    }

    public function down(): void
    {
        $collision = DB::selectOne('SELECT 1 FROM domains GROUP BY name COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1 LIMIT 1');
        if ($collision !== null) {
            throw new RuntimeException('Domain names would collide under utf8mb4_unicode_ci; rollback refused without changing data.');
        }

        Schema::table('domains', function (Blueprint $table): void {
            $table->string('name')->collation('utf8mb4_unicode_ci')->change();
        });
    }
};
