<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_consulta_invitados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('video_consulta_id')
                ->constrained('video_consultas')
                ->cascadeOnDelete();

            $table->string('nombre')->nullable();
            $table->string('email');

            $table->string('token', 80)->unique();

            $table->string('estado')->default('pendiente');

            $table->timestamp('invitado_conectado_at')->nullable();
            $table->timestamp('ultimo_envio_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_consulta_invitados');
    }
};