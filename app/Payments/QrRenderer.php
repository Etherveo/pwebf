<?php

namespace App\Payments;

// M3b PAY-58, blueprint §8.3. Renders a QR payload as HTML. The
// instruction page only calls render(QrPayload); it never knows what
// is inside the payload or how it is drawn.
//
// QR digambar di sisi klien (package npm `qrcode`):
// halaman instruksi membawa URL pembayaran di [data-qr-url] dan
// resources/js/app.js menggambarnya ke <img class="qr-image">.
interface QrRenderer
{
    // Returns the HTML that displays the payload. Must never throw
    // when a QR implementation is missing: fall back to labelled text.
    public function render(QrPayload $payload): string;
}
