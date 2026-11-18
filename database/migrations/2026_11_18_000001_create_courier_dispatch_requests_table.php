<?php

use App\Enums\CourierDispatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_dispatch_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_hub_id')->nullable()->constrained('transit_hubs')->nullOnDelete();
            $table->enum('status', array_column(CourierDispatchStatus::cases(), 'value'))->default(CourierDispatchStatus::Pending->value);
            $table->timestamp('queued_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'queued_at']);
        });

        Schema::create('dispatch_rotation_cursors', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('last_hub_id')->nullable()->constrained('transit_hubs')->nullOnDelete();
        });

        DB::table('dispatch_rotation_cursors')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_rotation_cursors');
        Schema::dropIfExists('courier_dispatch_requests');
    }
};
