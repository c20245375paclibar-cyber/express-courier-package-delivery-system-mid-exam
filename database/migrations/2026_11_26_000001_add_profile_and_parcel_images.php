<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
        });

        Schema::create('parcel_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();

            $table->index(['parcel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_photos');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
