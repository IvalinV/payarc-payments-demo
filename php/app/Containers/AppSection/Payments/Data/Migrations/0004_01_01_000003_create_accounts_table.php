<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', static function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('dba_name');
            $table->string('legal_name');
            $table->string('email');
            $table->string('mid')->unique();
            $table->enum('status', ['active', 'suspended', 'closed']);
            $table->boolean('active_yn')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
