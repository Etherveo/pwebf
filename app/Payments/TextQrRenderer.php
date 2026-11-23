<?php

namespace App\Payments;

use Illuminate\Support\Facades\Log;

// Fallback render QR di sisi server bila JavaScript QR di browser belum siap/dimatikan.
class TextQrRenderer implements QrRenderer
{
    public function render(QrPayload $payload): string
    {
        Log::warning('QrRenderer: fallback teks (QR digambar JS di app.js; tampil bila JS mati/gagal).');

        $value = e($payload->value);

        return '<div class="qr-fallback" role="img" aria-label="Tautan atau payload pembayaran">'
            .'<p><strong>QR belum tersedia &mdash; gunakan tautan atau kode di bawah ini.</strong></p>'
            .'<p><code>'.$value.'</code></p>'
            .'</div>';
    }
}
