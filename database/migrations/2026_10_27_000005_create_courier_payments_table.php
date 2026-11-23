<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\PaymentMethod;
use App\Services\PaymentStatus;

require_once app_path('Services/CourierPaymentService.php');

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_status', array_column(PaymentStatus::cases(), 'value'))->default(PaymentStatus::Pending);
            $table->enum('payment_method', array_column(PaymentMethod::cases(), 'value'))->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payment_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_payments');
    }
};
