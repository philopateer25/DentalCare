<?php

namespace App\Services\Dental;

use App\Models\ProcedureCode;

/**
 * Single source of truth for the clinical rules behind the 3D odontogram.
 *
 * - Charting a finding never creates treatment by itself. A tooth marked "needs treatment"
 *   gets a *suggested* procedure that the doctor confirms ("Add to plan").
 * - Completing a procedure maps it to the resulting tooth condition by CDT code range,
 *   not by words in the title (e.g. "pulp capping" is not a crown).
 */
class OdontogramRules
{
    public const STATUSES = ['existing', 'needs_treatment', 'watch'];

    public const CONDITIONS = ['healthy', 'active_caries', 'composite_filled', 'crown', 'root_canal', 'missing', 'implant', 'custom'];

    /** FDI: permanent 11–18, 21–28, 31–38, 41–48; primary 51–55, 61–65, 71–75, 81–85. */
    public static function isValidFdi(string|int $tooth): bool
    {
        $t = (string) $tooth;

        return (bool) preg_match('/^([1-4][1-8]|[5-8][1-5])$/', $t);
    }

    /** Incisors and canines (positions 1–3) are anterior; premolars and molars are posterior. */
    public static function isAnterior(string|int $tooth): bool
    {
        return (int) substr((string) $tooth, -1) <= 3;
    }

    public static function isMolar(string|int $tooth): bool
    {
        $pos = (int) substr((string) $tooth, -1);
        $quadrant = (int) substr((string) $tooth, 0, 1);

        // Primary teeth: positions 4–5 are molars.
        return $quadrant >= 5 ? $pos >= 4 : $pos >= 6;
    }

    /**
     * Candidate CDT codes for a tooth that needs treatment, most specific first.
     *
     * @return array{codes: string[], reason: string}|null
     */
    public static function suggestionRule(string $condition, string $tooth): ?array
    {
        $anterior = self::isAnterior($tooth);

        return match ($condition) {
            'active_caries' => [
                'codes' => $anterior ? ['D2330'] : ['D2391', 'D2330'],
                'reason' => 'Active caries: restore with composite resin.',
            ],
            'composite_filled' => [
                'codes' => $anterior ? ['D2330'] : ['D2391', 'D2330'],
                'reason' => 'Failing restoration: replace the filling.',
            ],
            'root_canal' => [
                'codes' => ['D2740'],
                'reason' => 'Endodontically treated tooth: protect with a full-coverage crown.',
            ],
            'crown' => [
                'codes' => ['D2740'],
                'reason' => 'Defective crown: replace the crown.',
            ],
            'missing' => [
                'codes' => ['D6010'],
                'reason' => 'Missing tooth: replace with an implant (or consider a bridge).',
            ],
            // Healthy / implant / custom: the doctor decides; no automatic suggestion.
            default => null,
        };
    }

    /**
     * Resolve a suggestion against the practice's procedure catalogue.
     *
     * @return array{code: string, title: string, fee: float, reason: string}|null
     */
    public static function suggestionFor(string $condition, string $status, string $tooth): ?array
    {
        if ($status !== 'needs_treatment') {
            return null;
        }

        $rule = self::suggestionRule($condition, $tooth);
        if (! $rule) {
            return null;
        }

        $codes = ProcedureCode::whereIn('code', $rule['codes'])->get()->keyBy('code');
        foreach ($rule['codes'] as $code) {
            if ($found = $codes->get($code)) {
                return [
                    'code' => $found->code,
                    'title' => $found->title,
                    'fee' => (float) $found->standard_fee,
                    'reason' => $rule['reason'],
                ];
            }
        }

        return null;
    }

    public const DIAGNOSIS_FIELDS = ['diagnosis', 'severity', 'urgency', 'mobility'];

    /**
     * Normalise a stored tooth record for the chart workspace.
     *
     * The legacy chart wrote diagnosis/severity/urgency/mobility into the free-text notes,
     * either as JSON or as "[Dx: …] [Severity: …]" tags. Those values move into
     * clinical_details and are stripped from the notes; they are persisted structured
     * on the next save of that tooth.
     *
     * @return array{notes: ?string, clinical_details: array<string, mixed>}
     */
    public static function normaliseRecord(?string $notes, mixed $clinicalDetails): array
    {
        $details = is_array($clinicalDetails) ? $clinicalDetails : [];
        $notes = $notes !== null ? trim($notes) : null;

        if ($notes) {
            $json = json_decode($notes, true);
            if (is_array($json) && array_intersect(array_keys($json), self::DIAGNOSIS_FIELDS)) {
                foreach (self::DIAGNOSIS_FIELDS as $field) {
                    if (! empty($json[$field]) && empty($details[$field])) {
                        $details[$field] = $json[$field];
                    }
                }
                $notes = isset($json['notes']) && is_string($json['notes']) ? $json['notes'] : null;
            } else {
                $tags = ['diagnosis' => 'Dx', 'severity' => 'Severity', 'urgency' => 'Urgency', 'mobility' => 'Mobility'];
                foreach ($tags as $field => $tag) {
                    if (preg_match('/\['.$tag.':\s*([^\]]+)\]/i', $notes, $m)) {
                        if (empty($details[$field])) {
                            $details[$field] = trim($m[1]);
                        }
                        $notes = trim(preg_replace('/\['.$tag.':\s*[^\]]+\]/i', '', $notes));
                    }
                }
            }
        }

        return ['notes' => $notes !== '' ? $notes : null, 'clinical_details' => $details];
    }

    /**
     * Surfaces after a procedure is completed: a restoration turns carious surfaces into the
     * restoring material; an extraction clears them. Other procedures leave surfaces unchanged.
     *
     * @param  array<string, string>  $surfaces
     * @return array<string, string>
     */
    public static function surfacesAfterProcedure(?string $code, array $surfaces): array
    {
        $code = strtoupper(trim((string) $code));
        if (! preg_match('/^D(\d{4})$/', $code, $m)) {
            return $surfaces;
        }
        $n = (int) $m[1];

        $material = match (true) {
            $n >= 2140 && $n <= 2161 => 'amalgam',
            ($n >= 2330 && $n <= 2394) || ($n >= 2510 && $n <= 2664) => 'composite_filled',
            default => null,
        };

        if ($material) {
            return array_map(fn ($v) => $v === 'active_caries' ? $material : $v, $surfaces);
        }

        if (self::conditionAfterProcedure($code) === 'missing') {
            return array_map(fn () => 'healthy', $surfaces);
        }

        return $surfaces;
    }

    /**
     * Tooth condition after a procedure is completed, decided by CDT code range.
     * Returns null when the procedure does not change the charted condition
     * (exams, cleanings, X-rays, pulp capping, perio, etc.).
     */
    public static function conditionAfterProcedure(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        if (! preg_match('/^D(\d{4})$/', $code, $m)) {
            return null;
        }
        $n = (int) $m[1];

        return match (true) {
            $n >= 2140 && $n <= 2161 => 'composite_filled',  // amalgam restorations
            $n >= 2330 && $n <= 2394 => 'composite_filled',  // resin composite restorations
            $n >= 2510 && $n <= 2664 => 'composite_filled',  // inlays / onlays
            $n >= 2710 && $n <= 2799 => 'crown',             // single crowns
            $n >= 3310 && $n <= 3353 => 'root_canal',        // endodontic therapy + retreatment
            $n >= 6010 && $n <= 6013 => 'implant',           // implant body placement
            $n >= 7111 && $n <= 7250 => 'missing',           // extractions
            default => null,
        };
    }
}
