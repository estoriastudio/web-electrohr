<?php

namespace App\Services;

use App\Models\Payment;

class PaymentFolioGenerator
{
    public function generate(): string
    {
        do {
            $folio = strtoupper('PAY-' . random_int(10000, 99999));
        } while (Payment::where('folio', $folio)->exists());

        return $folio;
    }
}