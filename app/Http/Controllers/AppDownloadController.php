<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppDownloadController extends Controller
{
    public function download(Request $request)
    {
        // Guardar registro de la descarga
        \App\Models\AppDownload::create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
        ]);

        // URL del archivo a descargar (cámbiala si es necesario)
        $downloadUrl = 'https://play.google.com/store/apps/details?id=com.sevenrockradio.app';
        
        return redirect()->away($downloadUrl);
    }
}
