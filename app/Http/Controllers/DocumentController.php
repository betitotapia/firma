<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DocumentRecipient;
use App\Models\DocumentToken;
use App\Models\DocumentEvent;
use App\Models\DocumentHash;
use Carbon\Carbon;
use App\Models\DocumentSigner;


class DocumentController extends Controller
{
 public function index(Request $request)
{
    $q = trim((string) $request->query('q', ''));

    $documents = \App\Models\Document::query()
        ->with('recipient')
        ->when($q !== '', function ($query) use ($q) {
            $query->where('title', 'like', "%{$q}%")
                  ->orWhereHas('recipient', function ($rq) use ($q) {
                      $rq->where('name', 'like', "%{$q}%")
                         ->orWhere('email', 'like', "%{$q}%");
                  });
        })
        ->latest('id')
        ->paginate(15)
        ->withQueryString();

    return view('documents.index', [
        'documents' => $documents, // 👈 nombre que la vista espera
        'q' => $q,
    ]);
}
    public function create()
    {
        return view('documents.create');
    }

    public function store(Request $request)
{
    $request->validate([
        'signer_count' => ['required', 'integer', 'in:1,2'],
        'title' => ['required', 'string', 'max:255'],
        'recipient_name' => ['required', 'string', 'max:255'],
        'recipient_email' => ['required', 'email', 'max:255'],
        'recipient_display_role' => ['required', 'string', 'max:120'],
        'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],

        'director_name' => ['required_if:signer_count,2', 'nullable', 'string', 'max:240'],
        'director_email' => ['required_if:signer_count,2', 'nullable', 'email', 'max:240', 'different:recipient_email'],
        'director_display_role' => ['required_if:signer_count,2', 'nullable', 'string', 'max:120'],
    ]);

    if (!$request->hasFile('pdf') || !$request->file('pdf')->isValid()) {
        return back()
            ->withErrors(['pdf' => 'El archivo PDF no es válido o no se pudo subir.'])
            ->withInput();
    }

    $file = $request->file('pdf');

    \Storage::makeDirectory('contracts/original');
    $path = $file->store('contracts/original');

    if (!$path) {
        return back()
            ->withErrors(['pdf' => 'No se pudo guardar el PDF en storage.'])
            ->withInput();
    }

    $sha256 = hash_file('sha256', \Storage::path($path));

    $doc = \App\Models\Document::create([
        'title' => $request->title,
        'status' => 'draft',
        'created_by_user_id' => auth()->id(),
    ]);

    $recipient = \App\Models\DocumentRecipient::create([
        'document_id' => $doc->id,
        'name' => $request->recipient_name,
        'email' => $request->recipient_email,
        'status' => 'pending',
    ]);

    $signers = [
        [
            'document_id' => $doc->id,
            'role' => 'employee',
            'display_role' => $request->recipient_display_role,
            'sign_order' => 1,
            'name' => $request->recipient_name,
            'email' => $request->recipient_email,
            'status' => 'pending',
        ],
    ];

    if ((int) $request->input('signer_count') === 2) {
        $signers[] = [
            'document_id' => $doc->id,
            'role' => 'director',
            'display_role' => $request->director_display_role,
            'sign_order' => 2,
            'name' => $request->director_name,
            'email' => $request->director_email,
            'status' => 'pending',
        ];
    }

    foreach ($signers as $signerData) {
        DocumentSigner::create($signerData);
    }

    $version = \App\Models\DocumentVersion::create([
        'document_id' => $doc->id,
        'type' => 'original',
        'storage_disk' => config('filesystems.default', 'local'),
        'storage_path' => $path,
        'original_filename' => $file->getClientOriginalName(),
        'mime_type' => 'application/pdf',
        'size_bytes' => $file->getSize(),
    ]);

    \App\Models\DocumentHash::create([
        'document_id' => $doc->id,
        'original_sha256' => $sha256,
    ]);

    \App\Models\DocumentEvent::log($doc->id, 'created', $request, [
        'title' => $doc->title,
        'recipient_email' => $recipient->email,
        'version_id' => $version->id,
    ]);

    return redirect()
        ->route('documents.show', $doc)
        ->with('ok', 'Contrato creado');
}
    public function show(Document $document)
    {
        $document->load(['recipient','versions','tokens','events' => function($q){ $q->latest('occurred_at'); }]);
        return view('documents.show', compact('document'));
    }

    public function sendLink(Request $request, Document $document)
{
    $request->validate([
        'expires_hours' => ['nullable','integer','min:1','max:168'],
    ]);

    $document->load('recipient');

    if (!$document->recipient) {
        return back()->withErrors(['recipient' => 'El documento no tiene firmante principal (recipient).']);
    }

    $expiresHours = (int) ($request->input('expires_hours', 72));
    $expiresAt = Carbon::now()->addHours($expiresHours);

    // 1) Determinar el "siguiente firmante" pendiente
    $nextSigner = DocumentSigner::where('document_id', $document->id)
        ->where('status', 'pending')
        ->orderBy('sign_order')
        ->first();

    // Compatibilidad: si NO existen signers aún, crea al menos el del colaborador usando el recipient
    if (!$nextSigner) {
        $existsAny = DocumentSigner::where('document_id', $document->id)->exists();

        if (!$existsAny) {
            // crea firmante 1 (colaborador) con la info del recipient
            $nextSigner = DocumentSigner::create([
                'document_id' => $document->id,
                'role' => 'employee',
                'sign_order' => 1,
                'name' => $document->recipient->name,
                'email' => $document->recipient->email,
                'status' => 'pending',
            ]);
        } else {
            // Ya hay signers pero ninguno pending
            return back()->withErrors(['signers' => 'No hay firmantes pendientes. El documento ya fue firmado por todos.']);
        }
    }

    // 2) (Opcional recomendado) revocar tokens activos anteriores del MISMO signer
    DocumentToken::where('document_id', $document->id)
        ->where('signer_id', $nextSigner->id)
        ->where('status', 'active')
        ->update(['status' => 'revoked']);

    // 3) Crear token para ESE firmante
    $tokenValue = Str::random(64);
    $tokenHash  = hash('sha256', $tokenValue);

    $token = DocumentToken::create([
        'document_id'  => $document->id,
        'recipient_id' => $document->recipient->id, // requerido por tu schema
        'signer_id'    => $nextSigner->id,          // requerido para multi-firma
        'token_hash'   => $tokenHash,
        'purpose'      => 'review_sign',
        'expires_at'   => $expiresAt,
        'status'       => 'active',
    ]);



    $link = route('public.review', ['token' => $tokenValue]);

    // 4) Log
    DocumentEvent::log($document->id, 'sent', $request, [
        'recipient_email' => $document->recipient->email,
        'token_id' => $token->id,
        'signer_id' => $nextSigner->id,
        'signer_email' => $nextSigner->email,
        'signer_role' => $nextSigner->role,
        'expires_at' => $expiresAt->toIso8601String(),
        'link' => $link,
    ]);

    // Aquí puedes enviar email real si ya lo tienes configurado.
    return back()->with('ok', 'Link generado')->with('link', $link);
}

    public function verifyEvidence(string $evidenceId)
            {
                $row = \App\Models\DocumentEvidence::where('evidence_id', $evidenceId)->firstOrFail();

                $calc = hash('sha256', $row->evidence_json);

                return response()->json([
                    'evidence_id' => $row->evidence_id,
                    'stage' => $row->stage,
                    'document_id' => $row->document_id,
                    'document_version_id' => $row->document_version_id,
                    'evidence_sha256_stored' => $row->evidence_sha256,
                    'evidence_sha256_calc' => $calc,
                    'matches' => hash_equals($row->evidence_sha256, $calc),
                    'evidence_json' => json_decode($row->evidence_json, true),
                ]);
            }

    public function download(Request $request, Document $document, DocumentVersion $version)
    {
        abort_unless($version->document_id === $document->id, 404);

        DocumentEvent::log($document->id, 'admin_download', $request, [
            'version_id' => $version->id,
            'type' => $version->type,
        ]);

        return Storage::disk($version->storage_disk)->download($version->storage_path, $version->original_filename ?? basename($version->storage_path));
    }

    public function evidenceJson(Request $request, Document $document)
    {
        $document->load(['recipient','versions','hashes','events']);

        $payload = [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status,
                'created_at' => optional($document->created_at)->toIso8601String(),
            ],
            'recipient' => [
                'name' => optional($document->recipient)->name,
                'email' => optional($document->recipient)->email,
                'status' => optional($document->recipient)->status,
                'signed_at' => optional($document->recipient)->signed_at?->toIso8601String(),
            ],
            'hashes' => optional($document->hashes)->toArray(),
            'versions' => $document->versions->map(fn($v) => [
                'id' => $v->id,
                'type' => $v->type,
                'filename' => $v->original_filename,
                'mime' => $v->mime_type,
                'size_bytes' => $v->size_bytes,
                'created_at' => optional($v->created_at)->toIso8601String(),
            ])->toArray(),
            'events' => $document->events->map(fn($e) => [
                'type' => $e->event_type,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'ip' => $e->ip,
                'user_agent' => $e->user_agent,
                'metadata' => $e->metadata,
            ])->toArray(),
        ];

        DocumentEvent::log($document->id, 'evidence_exported', $request, []);

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="evidence-document-'.$document->id.'.json"',
        ]);
    }
    public function destroy(\App\Models\Document $document)
{
    $document->delete(); // soft delete

    return redirect()
        ->route('dashboard')
        ->with('ok', 'Contrato eliminado (archivado).');
}
}
