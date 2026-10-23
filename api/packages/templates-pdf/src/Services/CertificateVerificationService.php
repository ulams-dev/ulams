<?php

namespace Ulams\TemplatesPdf\Services;

use Illuminate\Support\Str;
use Ulams\Core\Models\User;
use Ulams\TemplatesPdf\Events\CertificateRevoked;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

/**
 * Public view of a certificate (what the QR code leads to) and revocation. The public view carries
 * only what is needed to check a printed copy.
 */
class CertificateVerificationService
{
    public function find(string $certificateId): ?FabricPDF
    {
        return FabricPDF::query()->where('certificate_id', $certificateId)->first();
    }

    public function publicView(FabricPDF $pdf): array
    {
        $vars = $pdf->vars ?? [];

        return [
            'certificate_id' => $pdf->certificate_id,
            'status' => $pdf->status(),
            'title' => $pdf->title,
            'course_title' => $vars['@VarCourseTitle'] ?? null,
            'holder_name' => $this->holderName($pdf->user),
            'issued_at' => $pdf->created_at?->toIso8601String(),
            'expires_at' => $pdf->expires_at?->toIso8601String(),
            'revoked_at' => $pdf->revoked_at?->toIso8601String(),
            'superseded_by_policy_version' => self::supersededByPolicyVersion($pdf),
            'issuer' => $vars['@VarAppName'] ?? config('app.name'),
        ];
    }

    /**
     * The policy version that replaced what a revoked certificate certified, when it was revoked for that reason
     * (`policy_superseded:v<N>`, written by the policy lifecycle). Only this number is public, never the free text of a reason.
     */
    private static function supersededByPolicyVersion(FabricPDF $pdf): ?int
    {
        return $pdf->revoked_at !== null && preg_match('/^policy_superseded:v(\d{1,6})$/', (string) $pdf->revoked_reason, $m) ? (int) $m[1] : null;
    }

    public function revoke(FabricPDF $pdf, ?User $by, string $reason): FabricPDF
    {
        if ($pdf->revoked_at === null) {
            $pdf->forceFill([
                'revoked_at' => now(),
                'revoked_reason' => Str::limit($reason, 500, ''),
                'revoked_by' => $by?->getKey(),
            ])->save();
            event(new CertificateRevoked($pdf));
        }

        return $pdf;
    }

    private function holderName(?User $user): ?string
    {
        if (!$user) {
            return null;
        }
        $mode = config(UlamsTemplatesPdfServiceProvider::CONFIG_KEY . '.verify.holder_name', 'full');

        return match ($mode) {
            'none' => null,
            'initials' => collect(preg_split('/\s+/', trim((string) $user->name)))
                ->filter()
                ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)) . '.')
                ->implode(' '),
            default => trim((string) $user->name),
        };
    }
}
