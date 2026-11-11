<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudServicio;
use App\Models\Notificacion;
use App\Models\Servicio;
use App\Models\Usuario;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SolicitudServicioController extends Controller
{
    /**
     * El cliente inicia una nueva solicitud de servicio/cotización.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function crearSolicitud(Request $request)
    {
        // Obtener usuario autenticado (debe ser cliente)
        $cliente = JWTAuth::user();

        if (!$cliente) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }
        if ($cliente->rol_id !== 1) {
            return response()->json(['mensaje' => 'Solo los clientes pueden solicitar servicios.'], 403);
        }

        // Validar datos de entrada (la hora es texto libre porque el panel web la escribe a mano)
        $datosValidados = $request->validate([
            'proveedor_id' => 'required|exists:usuarios,id',
            'servicio_id' => 'nullable|exists:servicios,id',
            'fecha' => 'required|date|after_or_equal:today',
            'hora' => 'required|string|max:50',
            'direccion' => 'required|string|min:5|max:255',
            'descripcion' => 'required|string|min:10|max:1000',
            'telefono' => 'required|string|min:7|max:30',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
        ], [
            'fecha.required' => 'Elige la fecha en que necesitas el servicio.',
            'fecha.after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            'hora.required' => 'Elige una hora.',
            'direccion.required' => 'Escribe la dirección donde se hará el trabajo.',
            'direccion.min' => 'Escribe una dirección más completa.',
            'descripcion.required' => 'Describe el trabajo que necesitas.',
            'descripcion.min' => 'Describe el trabajo con un poco más de detalle (mínimo 10 caracteres).',
            'descripcion.max' => 'La descripción puede tener máximo 1000 caracteres.',
            'telefono.required' => 'Escribe un teléfono de contacto.',
            'telefono.min' => 'Escribe un teléfono válido.',
        ]);

        // El proveedor debe existir, estar aprobado y con la cuenta activa
        $proveedor = Usuario::find($datosValidados['proveedor_id']);
        if (!$proveedor || $proveedor->rol_id !== 2 || !$proveedor->esta_aprobado
            || $proveedor->desactivado_en || $proveedor->eliminado_en) {
            return response()->json(['mensaje' => 'Este proveedor no está disponible en este momento.'], 422);
        }

        // Si se eligió un servicio, debe ser de ese proveedor y estar activo
        if (!empty($datosValidados['servicio_id'])) {
            $servicioValido = Servicio::where('id', $datosValidados['servicio_id'])
                ->where('usuario_id', $proveedor->id)
                ->where('esta_activo', true)
                ->exists();
            if (!$servicioValido) {
                return response()->json([
                    'mensaje' => 'Ese servicio ya no está disponible.',
                    'errors' => ['servicio_id' => ['Ese servicio ya no está disponible. Elige otro.']],
                ], 422);
            }
        }

        // Crear la solicitud
        $solicitud = SolicitudServicio::create([
            'cliente_id' => $cliente->id,
            'proveedor_id' => $datosValidados['proveedor_id'],
            'servicio_id' => $datosValidados['servicio_id'] ?? null,
            'fecha' => $datosValidados['fecha'],
            'hora' => $datosValidados['hora'],
            'direccion' => $datosValidados['direccion'],
            'descripcion' => $datosValidados['descripcion'],
            'telefono' => $datosValidados['telefono'],
            'latitud' => $datosValidados['latitud'] ?? null,
            'longitud' => $datosValidados['longitud'] ?? null,
            'estado' => 'pendiente'
        ]);

        $solicitud->eventos()->create(['estado' => 'pendiente', 'actor_id' => $cliente->id]);

        // Avisar al proveedor de la nueva solicitud
        Notificacion::crear(
            $proveedor->id,
            'solicitud',
            'Nueva solicitud de servicio',
            "{$cliente->nombre} te pidió una cotización para el " . Carbon::parse($solicitud->fecha)->format('d/m/Y') . '.'
        );

        return response()->json([
            'mensaje' => 'Solicitud de servicio enviada con éxito.',
            'solicitud' => $solicitud
        ], 201);
    }

    /**
     * El proveedor obtiene todas sus solicitudes de servicio recibidas.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerSolicitudesProveedor()
    {
        // Obtener proveedor autenticado
        $proveedor = JWTAuth::user();

        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json([
                'mensaje' => 'No autorizado o no es un proveedor.'
            ], 401);
        }

        // Obtener solicitudes
        $solicitudes = SolicitudServicio::where('proveedor_id', $proveedor->id)
            ->with(['cliente:id,nombre,apellido,telefono', 'servicio', 'eventos'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * El cliente obtiene todas las solicitudes de servicio que ha realizado.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerSolicitudesCliente()
    {
        // Obtener cliente autenticado
        $cliente = JWTAuth::user();

        if (!$cliente) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Obtener solicitudes
        // "resena" indica si el cliente ya calificó el servicio (una calificación por servicio)
        $solicitudes = SolicitudServicio::where('cliente_id', $cliente->id)
            ->with(['proveedor:id,nombre,apellido,telefono', 'proveedor.perfilProveedor:id,usuario_id,categoria_servicio,foto_perfil', 'servicio', 'resena:id,solicitud_id,calificacion', 'eventos'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * Detalle de una solicitud para el cliente o el proveedor (incluye la línea de tiempo).
     */
    public function obtenerSolicitud($id)
    {
        $usuario = JWTAuth::user();
        if (!$usuario) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = $this->buscarDeUsuario($id, $usuario);
        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada.'], 404);
        }

        $solicitud->load([
            'cliente:id,nombre,apellido',
            'proveedor:id,nombre,apellido',
            'proveedor.perfilProveedor:id,usuario_id,categoria_servicio,foto_perfil,calificacion,total_resenas',
            'servicio',
            'resena:id,solicitud_id,calificacion',
            'eventos',
        ]);

        return response()->json($solicitud);
    }

    /**
     * El proveedor cotiza una solicitud pendiente (o actualiza una cotización que el cliente aún no responde).
     */
    public function cotizarSolicitud(Request $request, $id)
    {
        $proveedor = JWTAuth::user();
        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json(['mensaje' => 'Solo los proveedores pueden cotizar.'], 403);
        }

        $solicitud = SolicitudServicio::where('id', $id)->where('proveedor_id', $proveedor->id)->first();
        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada.'], 404);
        }
        if (!in_array($solicitud->estado, ['pendiente', 'cotizado'], true)) {
            return $this->conflicto($solicitud, 'cotizar');
        }

        $datos = $this->datosCotizacion($request, true);
        $actualizada = $solicitud->estado === 'cotizado';

        $solicitud->cambiarEstado('cotizado', $proveedor->id, [
            'monto_cotizado' => $datos['monto_cotizado'],
            'tiempo_estimado' => $datos['tiempo_estimado'],
            'garantia' => $datos['garantia'] ?? null,
            'detalles_cotizacion' => $datos['detalles_cotizacion'] ?? null,
        ], $actualizada ? 'Cotización actualizada' : null);

        $this->notificarCotizacion($solicitud);

        return response()->json([
            'mensaje' => $actualizada ? 'Cotización actualizada.' : 'Cotización enviada con éxito.',
            'solicitud' => $solicitud->fresh(),
        ]);
    }

    /**
     * Aceptar una solicitud:
     * - el cliente acepta la cotización recibida (cotizado → aceptada);
     * - el proveedor acepta directamente una solicitud nueva (pendiente → aceptada), con precio opcional.
     */
    public function aceptarCotizacion(Request $request, $id)
    {
        $usuario = JWTAuth::user();
        if (!$usuario) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = $this->buscarDeUsuario($id, $usuario);
        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada.'], 404);
        }

        if ($solicitud->proveedor_id === $usuario->id) {
            if ($solicitud->estado !== 'pendiente') {
                return $this->conflicto($solicitud, 'aceptar');
            }
            $datos = $this->datosCotizacion($request, false);
            $cambios = array_filter([
                'monto_cotizado' => $datos['monto_cotizado'] ?? null,
                'tiempo_estimado' => $datos['tiempo_estimado'] ?? null,
                'detalles_cotizacion' => $datos['detalles_cotizacion'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
            $solicitud->cambiarEstado('aceptada', $usuario->id, $cambios);

            Notificacion::crear(
                $solicitud->cliente_id,
                'solicitud',
                'Solicitud aceptada',
                "{$usuario->nombre} aceptó tu solicitud para el " . Carbon::parse($solicitud->fecha)->format('d/m/Y') . '.'
            );

            return response()->json(['mensaje' => 'Aceptaste el trabajo.', 'solicitud' => $solicitud->fresh()]);
        }

        if ($solicitud->estado !== 'cotizado') {
            return $this->conflicto($solicitud, 'aceptar la cotización');
        }
        $solicitud->cambiarEstado('aceptada', $usuario->id);

        Notificacion::crear(
            $solicitud->proveedor_id,
            'solicitud',
            'Cotización aceptada',
            "{$usuario->nombre} aceptó tu cotización para el " . Carbon::parse($solicitud->fecha)->format('d/m/Y') . '.'
        );

        return response()->json([
            'mensaje' => 'Cotización aceptada con éxito. El servicio ha sido programado.',
            'solicitud' => $solicitud->fresh(),
        ]);
    }

    /**
     * Rechazar: el proveedor rechaza una solicitud (→ rechazada). Si lo hace el cliente
     * (p. ej. rechaza la cotización desde la web), equivale a cancelarla (→ cancelada).
     */
    public function rechazarSolicitud(Request $request, $id)
    {
        $usuario = JWTAuth::user();
        if (!$usuario) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = $this->buscarDeUsuario($id, $usuario);
        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada.'], 404);
        }

        if ($solicitud->cliente_id === $usuario->id) {
            return $this->cancelar($request, $solicitud, $usuario);
        }

        if (!in_array($solicitud->estado, ['pendiente', 'cotizado'], true)) {
            return $this->conflicto($solicitud, 'rechazar');
        }
        $motivo = $request->validate(['motivo' => 'nullable|string|max:300'])['motivo'] ?? null;
        $solicitud->cambiarEstado('rechazada', $usuario->id, [], $motivo);

        Notificacion::crear(
            $solicitud->cliente_id,
            'solicitud',
            'Solicitud rechazada',
            "{$usuario->nombre} no puede atender tu solicitud. Busca otro proveedor disponible."
        );

        return response()->json(['mensaje' => 'Solicitud rechazada.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * El cliente cancela su solicitud mientras el servicio no haya empezado.
     */
    public function cancelarSolicitud(Request $request, $id)
    {
        $cliente = JWTAuth::user();
        if (!$cliente) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = SolicitudServicio::where('id', $id)->where('cliente_id', $cliente->id)->first();
        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada.'], 404);
        }

        return $this->cancelar($request, $solicitud, $cliente);
    }

    /**
     * El proveedor indica que empezó el trabajo (aceptada → en_curso).
     */
    public function iniciarServicio($id)
    {
        $proveedor = JWTAuth::user();
        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json(['mensaje' => 'No autorizado.'], 403);
        }

        $solicitud = SolicitudServicio::where('id', $id)->where('proveedor_id', $proveedor->id)->first();
        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada.'], 404);
        }
        if ($solicitud->estado !== 'aceptada') {
            return $this->conflicto($solicitud, 'iniciar el servicio');
        }
        if ($solicitud->fecha && Carbon::now()->toDateString() < Carbon::parse($solicitud->fecha)->toDateString()) {
            return response()->json([
                'mensaje' => 'Podrás iniciar el servicio a partir del ' . Carbon::parse($solicitud->fecha)->format('d/m/Y') . '.',
            ], 422);
        }

        $solicitud->cambiarEstado('en_curso', $proveedor->id);

        Notificacion::crear(
            $solicitud->cliente_id,
            'solicitud',
            'Servicio en curso',
            "{$proveedor->nombre} empezó a trabajar en tu servicio."
        );

        return response()->json(['mensaje' => 'Servicio iniciado.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * El proveedor marca un servicio como completado y pagado (desde aceptada o en curso).
     */
    public function marcarCompletado(Request $request, $id)
    {
        $proveedor = JWTAuth::user();

        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = SolicitudServicio::where('id', $id)
            ->where('proveedor_id', $proveedor->id)
            ->whereIn('estado', ['aceptada', 'en_curso'])
            ->first();

        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada o no está aceptada.'], 404);
        }

        // No permitir marcar como completado antes de la fecha acordada
        if ($solicitud->fecha) {
            $fechaAcordada = Carbon::parse($solicitud->fecha)->toDateString();
            $hoy = Carbon::now()->toDateString();
            if ($hoy < $fechaAcordada) {
                return response()->json([
                    'mensaje' => 'No puedes marcar el servicio como completado antes de la fecha acordada (' . Carbon::parse($solicitud->fecha)->format('d/m/Y') . ').'
                ], 422);
            }
        }

        $solicitud->cambiarEstado('completada', $proveedor->id, [
            'fecha_completado' => Carbon::now(),
            'marcado_pagado' => true,
        ]);

        // Notificar al cliente
        Notificacion::crear(
            $solicitud->cliente_id,
            'sistema',
            'Servicio completado',
            "El proveedor marcó el servicio como completado. Por favor confirma si está de acuerdo."
        );

        return response()->json(['mensaje' => 'Servicio marcado como completado.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * El cliente confirma o rechaza la completitud del servicio.
     * Si rechaza, puede adjuntar evidencia (foto/archivo) y un motivo.
     */
    public function confirmarCompletado(Request $request, $id)
    {
        $cliente = JWTAuth::user();

        if (!$cliente || $cliente->rol_id !== 1) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = SolicitudServicio::where('id', $id)
            ->where('cliente_id', $cliente->id)
            ->where('estado', 'completada')
            ->first();

        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada.'], 404);
        }

        $datos = $request->validate([
            'accion'    => 'required|in:aceptar,rechazar',
            'queja'     => 'nullable|string|max:1000',
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $urlEvidencia = null;
        if ($request->hasFile('evidencia')) {
            $archivo = $request->file('evidencia');
            $nombre = time() . '_ev_cliente_' . preg_replace('/\s+/', '_', $archivo->getClientOriginalName());
            $ruta = $archivo->storeAs('disputas', $nombre, 'public');
            $urlEvidencia = '/storage/' . $ruta;
        }

        if ($datos['accion'] === 'aceptar') {
            $solicitud->update([
                'confirmacion_cliente' => 'aceptado',
            ]);

            Notificacion::crear(
                $solicitud->proveedor_id,
                'pago',
                'Servicio confirmado',
                "{$cliente->nombre} confirmó el servicio completado. ¡El pago queda registrado!"
            );
        } else {
            // Rechazar: abrir disputa
            $solicitud->update([
                'confirmacion_cliente' => 'rechazado',
                'queja_cliente'        => $datos['queja'] ?? null,
                'evidencia_cliente'    => $urlEvidencia,
                'estado_disputa'       => 'pendiente_proveedor',
            ]);

            // Notificar al proveedor para que aporte su evidencia
            Notificacion::crear(
                $solicitud->proveedor_id,
                'sistema',
                'Disputa abierta',
                "{$cliente->nombre} rechazó el servicio. Motivo: " . ($datos['queja'] ?? 'Sin descripción') . ". Tienes 48h para aportar tu evidencia."
            );

            // Notificar al admin
            $admins = \App\Models\Usuario::where('rol_id', 3)->get();
            foreach ($admins as $admin) {
                Notificacion::crear(
                    $admin->id,
                    'sistema',
                    'Disputa de servicio',
                    "El cliente {$cliente->nombre} reportó que el proveedor intenta confirmar un trabajo incompleto. Solicitud #{$solicitud->id}."
                );
            }
        }

        return response()->json(['mensaje' => 'Confirmación registrada.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * El proveedor aporta evidencia en una disputa activa.
     */
    public function aportarEvidenciaProveedor(Request $request, $id)
    {
        $proveedor = JWTAuth::user();

        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = SolicitudServicio::where('id', $id)
            ->where('proveedor_id', $proveedor->id)
            ->where('estado_disputa', 'pendiente_proveedor')
            ->first();

        if (!$solicitud) {
            return response()->json(['mensaje' => 'Disputa no encontrada o ya respondida.'], 404);
        }

        $datos = $request->validate([
            'descripcion' => 'required|string|max:2000',
            'evidencia'   => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $archivo = $request->file('evidencia');
        $nombre = time() . '_ev_proveedor_' . preg_replace('/\s+/', '_', $archivo->getClientOriginalName());
        $ruta = $archivo->storeAs('disputas', $nombre, 'public');

        $solicitud->update([
            'evidencia_proveedor' => '/storage/' . $ruta,
            'queja_cliente'       => $solicitud->queja_cliente . "\n\n[Proveedor] " . $datos['descripcion'],
            'estado_disputa'      => 'pendiente_admin',
        ]);

        // Notificar al admin para que tome la decisión final
        $admins = \App\Models\Usuario::where('rol_id', 3)->get();
        foreach ($admins as $admin) {
            Notificacion::crear(
                $admin->id,
                'sistema',
                'Disputa lista para resolver',
                "El proveedor {$proveedor->nombre} aportó evidencia en la disputa #{$solicitud->id}. Requiere tu decisión final."
            );
        }

        return response()->json(['mensaje' => 'Evidencia enviada. El admin tomará la decisión final.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * Admin resuelve una disputa, aprobando o rechazando el trabajo del proveedor.
     */
    public function resolverDisputa(Request $request, $id)
    {
        $admin = JWTAuth::user();

        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $solicitud = SolicitudServicio::where('id', $id)
            ->where('estado_disputa', 'pendiente_admin')
            ->first();

        if (!$solicitud) {
            return response()->json(['mensaje' => 'Disputa no encontrada o ya resuelta.'], 404);
        }

        $datos = $request->validate([
            'resolucion' => 'required|in:aprobado,rechazado',
            'nota'       => 'nullable|string|max:1000',
        ]);

        $solicitud->update([
            'resolucion_admin' => $datos['resolucion'],
            'nota_admin'       => $datos['nota'] ?? null,
            'estado_disputa'   => 'resuelto',
            'confirmacion_cliente' => $datos['resolucion'] === 'aprobado' ? 'aceptado' : 'rechazado',
        ]);

        // Notificar al proveedor
        Notificacion::crear(
            $solicitud->proveedor_id,
            'sistema',
            $datos['resolucion'] === 'aprobado' ? 'Disputa resuelta a tu favor' : 'Disputa resuelta en contra',
            $datos['resolucion'] === 'aprobado'
                ? "El administrador aprobó el trabajo. El pago queda confirmado. " . ($datos['nota'] ?? '')
                : "El administrador determinó que el trabajo no fue completado correctamente. " . ($datos['nota'] ?? '')
        );

        // Notificar al cliente
        Notificacion::crear(
            $solicitud->cliente_id,
            'sistema',
            'Resolución de disputa',
            $datos['resolucion'] === 'aprobado'
                ? "El administrador revisó la evidencia y aprobó el trabajo del proveedor. " . ($datos['nota'] ?? '')
                : "El administrador revisó la evidencia y determinó que el trabajo fue insatisfactorio. " . ($datos['nota'] ?? '')
        );

        return response()->json(['mensaje' => 'Disputa resuelta correctamente.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * Admin: lista todas las disputas activas.
     */
    public function listarDisputas()
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $disputas = SolicitudServicio::whereIn('estado_disputa', ['pendiente_proveedor', 'pendiente_admin'])
            ->with(['cliente:id,nombre,apellido', 'proveedor:id,nombre,apellido', 'servicio:id,nombre'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($disputas);
    }

    /** Cómo se nombra cada estado en los mensajes al usuario. */
    private const ETIQUETAS_ESTADO = [
        'pendiente' => 'pendiente',
        'cotizado' => 'cotizada',
        'aceptada' => 'aceptada',
        'en_curso' => 'en curso',
        'completada' => 'completada',
        'rechazada' => 'rechazada',
        'cancelada' => 'cancelada',
    ];

    /** Solicitud en la que el usuario participa como cliente o como proveedor. */
    private function buscarDeUsuario($id, $usuario): ?SolicitudServicio
    {
        return SolicitudServicio::where('id', $id)
            ->where(function ($query) use ($usuario) {
                $query->where('cliente_id', $usuario->id)->orWhere('proveedor_id', $usuario->id);
            })
            ->first();
    }

    /** La acción no aplica al estado actual (p. ej. otra persona ya la cambió). */
    private function conflicto(SolicitudServicio $solicitud, string $accion)
    {
        $estado = self::ETIQUETAS_ESTADO[$solicitud->estado] ?? $solicitud->estado;
        return response()->json([
            'mensaje' => "No puedes {$accion}: la solicitud está {$estado}.",
            'estado' => $solicitud->estado,
        ], 409);
    }

    /**
     * Valida los datos de una cotización. El monto se guarda solo con dígitos (pesos):
     * acepta "85000", "85.000" o "$85.000".
     */
    private function datosCotizacion(Request $request, bool $obligatoria): array
    {
        $requerido = $obligatoria ? 'required' : 'nullable';
        $datos = $request->validate([
            'monto_cotizado' => "{$requerido}|max:30",
            'tiempo_estimado' => "{$requerido}|string|max:100",
            'garantia' => 'nullable|string|max:100',
            'detalles_cotizacion' => 'nullable|string|max:1000',
        ], [
            'monto_cotizado.required' => 'Escribe el valor de la cotización.',
            'monto_cotizado.max' => 'Escribe un valor válido.',
            'tiempo_estimado.required' => 'Indica cuánto tardarás aproximadamente.',
            'tiempo_estimado.max' => 'El tiempo estimado puede tener máximo 100 caracteres.',
            'garantia.max' => 'La garantía puede tener máximo 100 caracteres.',
            'detalles_cotizacion.max' => 'Los detalles pueden tener máximo 1000 caracteres.',
        ]);

        if (isset($datos['monto_cotizado']) && $datos['monto_cotizado'] !== '') {
            $monto = (int) preg_replace('/\D/', '', (string) $datos['monto_cotizado']);
            if ($monto < 1000 || $monto > 100000000) {
                throw ValidationException::withMessages([
                    'monto_cotizado' => 'Escribe un valor entre $1.000 y $100.000.000.',
                ]);
            }
            $datos['monto_cotizado'] = (string) $monto;
        }

        return $datos;
    }

    /** El cliente cancela (también cuando rechaza la cotización). */
    private function cancelar(Request $request, SolicitudServicio $solicitud, $cliente)
    {
        if (!in_array($solicitud->estado, ['pendiente', 'cotizado', 'aceptada'], true)) {
            return $this->conflicto($solicitud, 'cancelar');
        }

        $motivo = $request->validate(['motivo' => 'nullable|string|max:300'])['motivo'] ?? null;
        if (!$motivo && $solicitud->estado === 'cotizado') {
            $motivo = 'Rechazó la cotización';
        }
        $solicitud->cambiarEstado('cancelada', $cliente->id, [], $motivo);

        Notificacion::crear(
            $solicitud->proveedor_id,
            'solicitud',
            'Solicitud cancelada',
            "{$cliente->nombre} canceló la solicitud del " . Carbon::parse($solicitud->fecha)->format('d/m/Y') . '.'
                . ($motivo ? " Motivo: {$motivo}." : '')
        );

        return response()->json(['mensaje' => 'Solicitud cancelada.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * El proveedor cotiza una solicitud y genera notificación al cliente.
     */
    private function notificarCotizacion(SolicitudServicio $solicitud): void
    {
        Notificacion::crear(
            $solicitud->cliente_id,
            'solicitud',
            'Cotización recibida',
            "Tu proveedor envió una cotización de $" . number_format((int) $solicitud->monto_cotizado, 0, ',', '.') . ". ¡Revísala!"
        );
    }
}
