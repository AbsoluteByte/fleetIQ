<?php

namespace App\Services;

use App\Models\AgreementCarRentalInvoice;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class AgreementCarRentalInvoiceSerialService
{
    /** @var list<string> */
    private const SUFFIX_WORDS = ['ltd', 'limited', 'plc', 'llp', 'inc', 'co'];

    public function prefixForCompany(Company $company): string
    {
        $name = trim((string) $company->name);
        if ($name === '') {
            return 'INV';
        }

        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $significant = [];
        foreach ($words as $word) {
            $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $word) ?? '');
            if ($normalized === '' || in_array($normalized, self::SUFFIX_WORDS, true)) {
                continue;
            }
            $significant[] = $word;
        }

        if ($significant === []) {
            $significant = $words;
        }

        if (count($significant) >= 2) {
            $first = mb_strtoupper(mb_substr($significant[0], 0, 1));
            $second = mb_strtoupper(mb_substr($significant[1], 0, 1));

            return $first.$second;
        }

        $word = $significant[0] ?? $name;
        $letters = preg_replace('/[^a-zA-Z]/', '', $word) ?? 'INV';
        $prefix = mb_strtoupper(mb_substr($letters, 0, 2));

        return $prefix !== '' ? $prefix : 'INV';
    }

    public function assignSerial(int $tenantId, int $companyId, Company $company): array
    {
        $prefix = $this->prefixForCompany($company);

        return DB::transaction(function () use ($tenantId, $companyId, $prefix) {
            $existing = AgreementCarRentalInvoice::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('serial_prefix', $prefix)
                ->lockForUpdate()
                ->get(['serial_number']);

            $max = 0;
            foreach ($existing as $row) {
                if (preg_match('/-(\d+)$/', (string) $row->serial_number, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            $next = $max + 1;
            $serialNumber = sprintf('%s-%04d', $prefix, $next);

            return [
                'serial_prefix' => $prefix,
                'serial_number' => $serialNumber,
            ];
        });
    }
}
