Asunto: Problema con botones de maquetas + falta de confirmación de envío de correos

Te adjunto la captura del panel de maquetas donde antes estaban los botones de eliminar, aprobar y rechazar, y ya no aparecen.

Duda 1 – Botones desaparecidos:
Necesito saber qué pasó con ellos. ¿Se movieron a otro lugar? ¿Se eliminó la función? Porque yo necesito poder aprobar o rechazar cada maqueta que recibo.

Duda 2 – Correo automático al aprobar/rechazar:
Cuando yo apruebo una maqueta, el sistema debería enviar un correo automático a la banda diciéndoles que su canción fue aceptada (o rechazada, según corresponda).
Pero no tengo forma de saber si ese correo se envió o no, porque no hay ningún mensaje de confirmación, ni registro, ni notificación en el panel.

Lo que necesito:

Que me expliquen dónde están ahora esos botones o que los restablezcan.

Y que implementen o me confirmen si ya existe un sistema de notificación de envío de correos. Si no, que me digan cómo puedo verificar que el correo se envió correctamente.

Quedo atento a su respuesta.<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = \App\Models\User::where('email', 'admin@sevenrockradio.local')->first();
dump($u->getRoleNames());
