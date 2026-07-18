<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('amount');
            $table->string('auth_code')->nullable()->after('transaction_id');
            $table->string('card_type')->nullable()->after('auth_code');
            $table->string('card_last4', 8)->nullable()->after('card_type');
            $table->string('payment_status')->default('unpaid')->after('card_last4'); // unpaid | paid | declined | error
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['transaction_id', 'auth_code', 'card_type', 'card_last4', 'payment_status', 'paid_at']);
        });
    }
};
