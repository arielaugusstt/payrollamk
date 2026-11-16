<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('outlet_code')->unique();
            $table->string('outlet_name');
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('uang_makan_utama', 15, 2)->default(0);
            $table->decimal('uang_makan_tambahan', 15, 2)->default(0);
            $table->decimal('uang_pulsa', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
