<?php

namespace App\Http\Controllers;

use App\Models\DocumentEvidence;
use Illuminate\Http\Request;

class EvidenceController extends Controller
{
    public function show(Request $request, string $evidenceId)
    {
        // evidence_id es UUID string, no el PK numérico
        $evidence = DocumentEvidence::where('evidence_id', $evidenceId)->latest('id')->first();

        if (!$evidence) {
            abort(404, 'Evidencia no encontrada');
        }

        // evidence_json lo guardaste como JSON canónico (string)
        $payload = json_decode($evidence->evidence_json, true) ?: [];

        return view('public.evidence', [
            'evidence' => $evidence,
            'payload' => $payload,
        ]);
    }
}