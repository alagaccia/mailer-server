<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->text('key');
            $table->timestamps();
        });

        // Migra la chiave singola salvata in `settings` nella nuova tabella,
        // conservandola cifrata così com'è (stesso schema di cifratura).
        $existing = DB::table('settings')->where('key', 'api_key')->value('value');

        if (is_string($existing) && $existing !== '') {
            DB::table('api_keys')->insert([
                'name' => 'default',
                'key' => $existing,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('settings')->where('key', 'api_key')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
