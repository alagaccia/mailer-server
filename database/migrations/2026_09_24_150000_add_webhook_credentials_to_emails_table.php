<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Credenziali del webhook della singola email, arrivate con la
            // richiesta API insieme all'URL: token e segreto sono cifrati
            // con la APP_KEY (cast `encrypted` sul model). Se valorizzate
            // prendono il posto di quelle di default delle impostazioni.
            $table->text('webhook_token')->nullable()->after('webhook');
            $table->text('webhook_secret')->nullable()->after('webhook_token');
            $table->string('webhook_signature_header', 128)->nullable()->after('webhook_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn(['webhook_token', 'webhook_secret', 'webhook_signature_header']);
        });
    }
};
