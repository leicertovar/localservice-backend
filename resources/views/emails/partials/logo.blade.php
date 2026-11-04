{{-- La API de Brevo no admite imágenes incrustadas: ahí se usa la URL pública (requiere APP_URL correcto). --}}
<img src="{{ isset($message) && config('mail.default') !== 'brevo' ? $message->embed(public_path('brand/logo-correo.png')) : asset('brand/logo-correo.png') }}" alt="LocalService" width="220" style="display:block; margin:0 auto; border:0; height:auto;">
