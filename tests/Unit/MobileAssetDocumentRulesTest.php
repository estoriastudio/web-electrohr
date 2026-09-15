<?php

namespace Tests\Unit;

use App\Models\MobileAsset;
use App\Models\MobileAssetDocument;
use PHPUnit\Framework\TestCase;

class MobileAssetDocumentRulesTest extends TestCase
{
    public function test_maquinaria_pesada_does_not_require_verification_or_driver_license(): void
    {
        $documents = MobileAssetDocument::DOCS_BY_TYPE['maquinaria_pesada'];

        $this->assertSame([
            'poliza_seguro',
            'ficha_tecnica',
            'manual',
        ], $documents);
        $this->assertNotContains('verificacion', $documents);
        $this->assertNotContains('licencia_conducir', $documents);
    }

    public function test_semiremolque_keeps_plates_as_a_technical_field_not_a_document(): void
    {
        $asset = new MobileAsset([
            'type' => 'semiremolque',
            'plates' => 'ABC-123-A',
        ]);

        $this->assertSame('ABC-123-A', $asset->plates);
        $this->assertSame([
            'tarjeta_circulacion',
            'inspeccion_fisico_mecanica',
        ], $asset->getApplicableDocumentTypes());
        $this->assertNotContains('placas', $asset->getApplicableDocumentTypes());
    }
}