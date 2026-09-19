<?php

namespace App\Services;

use App\Models\Agreement;
use Illuminate\Support\Facades\File;

class AgreementSignatureResetService
{
    public function reset(Agreement $agreement): void
    {
        $this->deleteStoredSignedPdf($agreement);

        $agreement->signatureTokens()
            ->whereIn('status', ['pending', 'signed'])
            ->update([
                'status' => 'expired',
                'signature_data' => null,
            ]);

        $agreement->update([
            'hellosign_request_id' => null,
            'hellosign_sign_url' => null,
            'hellosign_status' => null,
            'esign_sent_at' => null,
            'esign_completed_at' => null,
            'esign_document_path' => null,
        ]);
    }

    private function deleteStoredSignedPdf(Agreement $agreement): void
    {
        $absolutePath = $agreement->esignDocumentAbsolutePath();

        if ($absolutePath && is_file($absolutePath)) {
            File::delete($absolutePath);
        }
    }
}
