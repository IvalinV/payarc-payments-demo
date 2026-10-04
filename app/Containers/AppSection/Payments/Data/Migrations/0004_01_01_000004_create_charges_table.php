<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('charges', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedBigInteger('amount_captured')->default(0);
            $table->unsignedBigInteger('amount_refunded')->default(0);
            $table->unsignedBigInteger('amount_voided')->default(0);
            $table->char('currency', 3);
            $table->enum('status', ['authorized', 'captured', 'refunded', 'voided', 'failed']);
            $table->string('external_transaction_id')->unique();
            $table->string('customer_email');
            $table->boolean('refunded')->default(false);
            $table->boolean('disputed')->default(false);
            $table->string('void_reason')->nullable();
            $table->text('description')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->foreignId('created_by')->constrained('users');

            $table->unique(['account_id', 'idempotency_key']);
            $table->index(['account_id', 'created_at']);
            $table->index('status');
            $table->index('customer_email');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
