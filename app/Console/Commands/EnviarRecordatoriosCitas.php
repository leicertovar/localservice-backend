<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\SolicitudServicio;
use App\Mail\RecordatorioCitaMail;
use Illuminate\Support\Carbon;

class EnviarRecordatoriosCitas extends Command
{
    protected $signature = 'citas:recordatorios';
    protected $description = 'Envía correos de recordatorio a los proveedores con citas programadas para mañana';

    public function handle(): void
    {
        $manana = Carbon::tomorrow()->toDateString();

        $solicitudes = SolicitudServicio::where('estado', 'aceptada')
            ->where('fecha', $manana)
            ->with(['proveedor', 'cliente', 'servicio'])
            ->get();

        $this->info("Enviando recordatorios para {$solicitudes->count()} cita(s) del $manana...");

        foreach ($solicitudes as $solicitud) {
            try {
                Mail::to($solicitud->proveedor->email)->send(new RecordatorioCitaMail($solicitud));
                $this->info("Recordatorio enviado a: {$solicitud->proveedor->email}");
            } catch (\Exception $e) {
                Log::error("Error enviando recordatorio a {$solicitud->proveedor->email}: " . $e->getMessage());
                $this->error("Error con {$solicitud->proveedor->email}: " . $e->getMessage());
            }
        }

        $this->info('Proceso completado.');
    }
}
