<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentEvent;
use App\Models\DocumentEvidence;
use App\Models\DocumentHash;
use App\Models\DocumentLiveness;
use App\Models\DocumentSigner;
use App\Models\DocumentToken;
use App\Models\DocumentVersion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicReviewController extends Controller
{
    public function show(Request $request, string $token)
    {
        [$tok, $document, $signer] = $this->resolveTokenOrFail($token);

        $token_status = $tok->status;
        $otp_verified = (bool) $request->session()->get('otp_verified_'.$tok->id, false);

        // ¿Hay PDFs firmados?
        $employeeSigned = $document->versions()->where('type', 'signed_employee')->exists();
        $finalSigned = $document->versions()->where('type', 'signed_final')->exists();

        // Selfie (liveness) para el firmante actual
        $liveness_ok = \App\Models\DocumentLiveness::where('document_id', $document->id)
            ->where('signer_id', $signer->id)
            ->exists();

        // Permisos de descarga (puedes relajarlos si quieres)
        $canDownloadEmployee = $employeeSigned && in_array($signer->role, ['employee', 'director'], true);
        $canDownloadFinal = $finalSigned && in_array($signer->role, ['director'], true);

        // Para el Blade: ¿hay algo que descargar?
        $download_ready = $canDownloadFinal || $canDownloadEmployee;

        // Etiqueta dinámica y “qué” descargar (opcional)
        $download_label = $canDownloadFinal
            ? 'Descargar PDF final'
            : ($canDownloadEmployee ? 'Descargar PDF intermedio' : 'Descargar PDF');

        // El blade puede mandar un hidden input type=... (si lo usas)
        $download_type = $canDownloadFinal ? 'signed_final' : ($canDownloadEmployee ? 'signed_employee' : 'original');

        return view('public.review', [
            'token' => $token,
            'token_status' => $token_status,
            'document' => $document,
            'signer' => $signer,

            'otp_verified' => $otp_verified,
            'liveness_ok' => $liveness_ok,

            'employeeSigned' => $employeeSigned,
            'finalSigned' => $finalSigned,

            'canDownloadEmployee' => $canDownloadEmployee,
            'canDownloadFinal' => $canDownloadFinal,

            'download_ready' => $download_ready,
            'download_label' => $download_label,
            'download_type' => $download_type,
        ]);
    }

    public function requestOtp(Request $request, string $token)
    {
        [$tok, $document, $signer] = $this->resolveTokenOrFail($token);

        // Si el link ya fue usado (consumed) no permitir OTP
        if ($tok->status !== 'active') {
            return back()->withErrors(['token' => 'Este link ya fue usado y no permite solicitar OTP.']);
        }

        // Throttle básico (60s)
        $lastAt = $request->session()->get('otp_last_sent_'.$tok->id);
        if ($lastAt && now()->diffInSeconds(Carbon::parse($lastAt)) < 60) {
            return back()->withErrors(['otp' => 'Espera un momento antes de solicitar otro OTP.']);
        }

        $code = (string) random_int(100000, 999999);
        $request->session()->put('otp_code_hash_'.$tok->id, hash('sha256', $code));
        $request->session()->put('otp_expires_'.$tok->id, now()->addMinutes(10)->toDateTimeString());
        $request->session()->put('otp_last_sent_'.$tok->id, now()->toDateTimeString());

        DocumentEvent::log($document->id, 'otp_sent', $request, [
            'token_id' => $tok->id,
            'signer_id' => $signer->id,
            'signer_email' => $signer->email,
        ]);

        $subject = 'Código OTP para firmar tu contrato';
        $body = "Hola {$signer->name},\n\nTu código OTP es: {$code}\nVence en 10 minutos.\n\nSi no lo solicitaste, ignora este mensaje.";

        Mail::raw($body, function ($m) use ($signer, $subject) {
            $m->to($signer->email, $signer->name)->subject($subject);
        });

        return back()->with('ok', 'OTP enviado a tu correo.');
    }

    public function verifyOtp(Request $request, string $token)
    {
        [$tok, $document, $signer] = $this->resolveTokenOrFail($token);
        if ($tok->status !== 'active') {
            return back()->withErrors(['token' => 'Este link ya fue usado y no permite firmar nuevamente.']);
        }

        $request->validate([
            'otp' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $hash = $request->session()->get('otp_code_hash_'.$tok->id);
        $exp = $request->session()->get('otp_expires_'.$tok->id);

        if (! $hash || ! $exp) {
            return back()->withErrors(['otp' => 'Primero solicita un OTP.']);
        }

        if (now()->greaterThan(Carbon::parse($exp))) {
            return back()->withErrors(['otp' => 'OTP expirado. Solicita uno nuevo.']);
        }

        if (! hash_equals($hash, hash('sha256', trim((string) $request->input('otp'))))) {
            return back()->withErrors(['otp' => 'OTP incorrecto.']);
        }

        $request->session()->put('otp_verified_'.$tok->id, true);

        DocumentEvent::log($document->id, 'otp_verified', $request, [
            'token_id' => $tok->id,
            'signer_id' => $signer->id,
            'occurred_at' => now()->toDateTimeString(),
        ]);

        return back()->with('ok', 'OTP verificado. Ya puedes firmar.');
    }

    public function signInApp(Request $request, string $token)
    {
        [$tok, $document, $signer] = $this->resolveTokenOrFail($token);

        if ($tok->status !== 'active') {
            return back()->withErrors(['token' => 'Este link ya fue usado y no permite firmar nuevamente.']);
        }

        $request->validate([
            'signature_data' => ['required', 'string'],
            'accept' => ['required', 'accepted'],
        ]);

        if (! $request->session()->get('otp_verified_'.$tok->id, false)) {
            return back()->withErrors(['otp' => 'Primero verifica el OTP.']);
        }

        // 1) Guardar firma PNG (dataURL)
        $dataUrl = (string) $request->input('signature_data');
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return back()->withErrors(['signature' => 'Formato de firma inválido.']);
        }

        $pngData = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')));
        if ($pngData === false || strlen($pngData) < 2000) {
            return back()->withErrors(['signature' => 'Firma vacía o inválida. Vuelve a firmar.']);
        }

        Storage::makeDirectory('contracts/signatures');
        $sigPath = 'contracts/signatures/signer_'.$signer->id.'_'.time().'.png';
        Storage::put($sigPath, $pngData);

        $now = now();

        $signer->signature_path = $sigPath;
        $signer->status = 'signed';
        $signer->signed_at = $now;
        $signer->signed_ip = $request->ip();
        $signer->save();

        // 2) Consumir token
        $tok->status = 'consumed';
        $tok->consumed_at = $now;
        $tok->save();

        // Limpia OTP de sesión de este token (recomendado)
        $request->session()->forget('otp_verified_'.$tok->id);
        $request->session()->forget('otp_code_hash_'.$tok->id);
        $request->session()->forget('otp_expires_'.$tok->id);

        // 3) Etapa
        $signers = $this->getSigners($document);
        $stage = $signer->role === 'employee' ? 'employee' : 'final';

        if ($stage === 'final') {
            $employee = collect($signers)->firstWhere('role', 'employee');
            if (! $employee || $employee->status !== 'signed' || ! $employee->signature_path) {
                return back()->withErrors(['signature' => 'Primero debe firmar el colaborador.']);
            }
        }

        // 4) Evidence (Opción A)
        $evidenceId = (string) Str::uuid();
        $payload = $this->buildEvidencePayload($document, $signers, $stage, $request, $tok, $evidenceId);
        $canonical = $this->canonicalJson($payload);
        $evidenceSha = hash('sha256', $canonical);

        // 5) Generar PDF e imprimir evidenceId/evidenceSha dentro del PDF
        [$signedPath, $signedFullPath, $signedSha] = $this->buildSignedPdf(
            $document, $signers, $stage, $request, $evidenceId, $evidenceSha
        );
            $evidenceUrl = route('evidence.public.show', ['evidenceId' => $evidenceId]);

                $employee = collect($signers)->firstWhere('role', 'employee');
                $director = collect($signers)->firstWhere('role', 'director');

                if ($stage === 'employee') {
                    // 1) Enviar el PDF intermedio al trabajador
                    if ($employee) {
                        $this->emailSignedPdfTo(
                            toList: [[ 'email' => $employee->email, 'name' => $employee->name ]],
                            subject: 'Contrato firmado (trabajador) - copia',
                            title: $document->title,
                            stageLabel: 'Firma del trabajador (PDF intermedio)',
                            signedFullPath: $signedFullPath,
                            fileName: 'contrato_intermedio_'.$document->id.'.pdf',
                            evidenceUrl: $evidenceUrl
                        );
                    }
                }

                if ($stage === 'final') {
                    // 2) Enviar el PDF final al patrón y copia al trabajador
                    $to = [];

                    if ($director) $to[] = ['email' => $director->email, 'name' => $director->name];
                    if ($employee) $to[] = ['email' => $employee->email, 'name' => $employee->name];

                    if (!empty($to)) {
                        $this->emailSignedPdfTo(
                            toList: $to,
                            subject: 'Contrato firmado (final) - copia',
                            title: $document->title,
                            stageLabel: 'Contrato final firmado por ambas partes',
                            signedFullPath: $signedFullPath,
                            fileName: 'contrato_final_'.$document->id.'.pdf',
                            evidenceUrl: $evidenceUrl
                        );
                    }
}
        $versionType = $stage === 'employee' ? 'signed_employee' : 'signed_final';

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'type' => $versionType,
            'storage_disk' => config('filesystems.default', 'local'),
            'storage_path' => $signedPath,
            'original_filename' => $versionType.'_doc_'.$document->id.'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => filesize($signedFullPath) ?: 0,
        ]);

        // Guardar evidencia ligada a la versión
        DocumentEvidence::create([
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'stage' => $stage,
            'evidence_id' => $evidenceId,
            'evidence_sha256' => $evidenceSha,
            'evidence_json' => $canonical,
        ]);

        // Hash PDF (integridad del archivo) - opcional pero útil
        $hashRow = DocumentHash::firstOrCreate(['document_id' => $document->id]);
        $hashRow->signed_sha256 = $signedSha;
        $hashRow->save();

        // 6) Siguiente firmante
        $next = DocumentSigner::where('document_id', $document->id)
            ->where('status', 'pending')
            ->orderBy('sign_order')
            ->first();

        if ($next) {
            $rawNextToken = $this->createSignerToken($document, $next, 72);

            DocumentEvent::log($document->id, 'token_created', $request, [
                'signer_id' => $next->id,
                'signer_email' => $next->email,
            ]);

            $link = route('public.review', ['token' => $rawNextToken]);

            Mail::raw(
                "Hola {$next->name},\n\nTienes un contrato pendiente por firmar.\n\nLink:\n{$link}\n\n",
                function ($m) use ($next) {
                    $m->to($next->email, $next->name)->subject('Firma pendiente de contrato');
                }
            );

            $document->status = 'pending_next_signature';
            $document->save();
        } else {
            $document->status = 'signed';
            $document->save();
        }

        DocumentEvent::log($document->id, 'signed_in_app', $request, [
            'token_id' => $tok->id,
            'signer_id' => $signer->id,
            'stage' => $stage,
            'signed_version_id' => $version->id,
            'signed_sha256' => $signedSha,
            'signature_path' => $sigPath,
            'evidence_id' => $evidenceId,
            'evidence_sha256' => $evidenceSha,
        ]);

        return redirect()->route('public.review', ['token' => $token])
            ->with('ok', $stage === 'employee'
                ? 'Firma registrada. Se generó el PDF intermedio.'
                : 'Firma registrada. Se generó el PDF final.');
    }

    private function buildEvidencePayload(
        \App\Models\Document $document,
        array $signers,
        string $stage,
        \Illuminate\Http\Request $request,
        \App\Models\DocumentToken $tok,
        string $evidenceId
    ): array {
        $hashRow = $document->hashes;
        $originalSha = $hashRow?->original_sha256 ?? null;

        $otpEvent = \App\Models\DocumentEvent::where('document_id', $document->id)
            ->where('event_type', 'otp_verified')
            ->latest('occurred_at')
            ->first();

        // --- Liveness: selfie por firmante (última registrada) ---
        // Traemos todas las selfies del documento y nos quedamos con la última por signer_id
        $livenessBySigner = \App\Models\DocumentLiveness::where('document_id', $document->id)
            ->orderByDesc('id')
            ->get()
            ->groupBy('signer_id')
            ->map(fn ($rows) => $rows->first()); // último por signer_id

        $liveness = collect($signers)->map(function ($s) use ($livenessBySigner) {
            $row = $livenessBySigner->get($s->id);
            if (! $row) {
                return null;
            }

            return [
                'signer_id' => $s->id,
                'type' => $row->type,          // selfie
                'challenge' => $row->challenge,      // reto mostrado
                'sha256' => $row->sha256,         // hash del archivo selfie
                'captured_at' => $row->captured_at?->toIso8601String(),
                'mime' => $row->mime_type,
                'size_bytes' => (int) $row->size_bytes,
                'storage' => [
                    'disk' => $row->storage_disk,
                    'path' => $row->storage_path,     // OJO: es privado; está bien incluirlo si solo admins ven JSON
                ],
            ];
        })->filter()->values()->all();

        return [
            'schema' => 'contracts-evidence-v1',
            'evidence_id' => $evidenceId,
            'stage' => $stage,

            'document' => [
                'id' => $document->id,
                'title' => $document->title,
            ],

            'hashes' => [
                'original_sha256' => $originalSha,
            ],

            'otp' => [
                'verified' => true,
                'verified_at' => $otpEvent?->occurred_at?->toIso8601String(),
            ],

            'accept' => true,

            'request' => [
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 180),
            ],

            'token' => [
                'id' => $tok->id,
                'status' => $tok->status,
            ],

            'signers' => collect($signers)->map(function ($s) {
                return [
                    'id' => $s->id,
                    'role' => $s->role,
                    'order' => $s->sign_order,
                    'name' => $s->name,
                    'email' => $s->email,
                    'status' => $s->status,
                    'signed_at' => $s->signed_at?->toIso8601String(),
                    'signed_ip' => $s->signed_ip,
                ];
            })->values()->all(),

            // ✅ selfies (evidencia de vida) incluidas en la cadena
            'liveness' => $liveness,
        ];
    }

    public function downloadSigned(Request $request, string $token, string $type)
    {
        [$tok, $document, $signer] = $this->resolveTokenOrFail($token);

        $want = $type === 'employee' ? 'signed_employee' : 'signed_final';

        $version = $document->versions()
            ->where('type', $want)
            ->latest('id')
            ->first();

        if (! $version) {
            abort(404, 'Aún no existe el PDF solicitado.');
        }

        $fullPath = Storage::disk($version->storage_disk)->path($version->storage_path);
        if (! file_exists($fullPath)) {
            abort(404, 'Archivo no encontrado.');
        }

        $dlName = $type === 'employee'
            ? 'contrato_firmado_intermedio_'.$document->id.'.pdf'
            : 'contrato_firmado_final_'.$document->id.'.pdf';

        return response()->download($fullPath, $dlName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function download(Request $request, string $token)
    {
        [$tok, $document, $signer] = $this->resolveTokenOrFail($token);

        // Descargar el PDF original (última versión type=original)
        $version = $document->versions()
            ->where('type', 'original')
            ->latest('id')
            ->first();

        if (! $version) {
            abort(404, 'No existe PDF original para este documento.');
        }

        $fullPath = Storage::disk($version->storage_disk)->path($version->storage_path);

        if (! file_exists($fullPath)) {
            abort(404, 'Archivo no encontrado.');
        }

        $dlName = 'contrato_original_'.$document->id.'.pdf';

        return response()->download($fullPath, $dlName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    // -------------------------
    // Helpers
    // -------------------------

    private function resolveTokenOrFail(string $rawToken): array
    {
        $hash = hash('sha256', $rawToken);
        $tok = \App\Models\DocumentToken::where('token_hash', $hash)->firstOrFail();

        // Revocado siempre bloquea
        if ($tok->status === 'revoked') {
            abort(410, 'Token inactivo');
        }

        // Expiración siempre bloquea
        if ($tok->expires_at && now()->greaterThan($tok->expires_at)) {
            abort(410, 'Token expirado');
        }

        // ✅ OJO: NO abortamos si está "consumed".
        // consumed se permite para:
        // - mostrar la página
        // - permitir descarga del PDF (si existe)
        // Acciones como OTP/firma/selfie se bloquean en sus métodos si tok->status !== 'active'.

        $document = \App\Models\Document::with(['versions', 'hashes'])->findOrFail($tok->document_id);

        // Si ya trae signer_id, perfecto
        if (! empty($tok->signer_id)) {
            $signer = \App\Models\DocumentSigner::findOrFail($tok->signer_id);

            return [$tok, $document, $signer];
        }

        // Compatibilidad con tokens antiguos basados en recipient_id:
        $rec = \App\Models\DocumentRecipient::findOrFail($tok->recipient_id);

        $signer = \App\Models\DocumentSigner::where('document_id', $document->id)
            ->where('email', $rec->email)
            ->orderBy('sign_order')
            ->first();

        if (! $signer) {
            abort(410, 'Token sin firmante asociado');
        }

        return [$tok, $document, $signer];
    }

    private function getOriginalPdfPath(Document $document): string
    {
        $original = $document->versions()
            ->where('type', 'original')
            ->latest('id')
            ->firstOrFail();

        return Storage::disk($original->storage_disk)->path($original->storage_path);
    }

    private function getSigners(Document $document): array
    {
        return DocumentSigner::where('document_id', $document->id)
            ->orderBy('sign_order')
            ->get()
            ->all();
    }

    /**
     * Genera PDF firmado importando original + estampa firmas disponibles en última página.
     * $stage: employee|final (solo para texto de evidencia)
     */
    private function buildSignedPdf(
        \App\Models\Document $document,
        array $signers,
        string $stage,
        \Illuminate\Http\Request $request,
        string $evidenceId,
        string $evidenceSha
    ): array {
        $originalFullPath = $this->getOriginalPdfPath($document);

        $hashRow = $document->hashes;
        $originalSha = $hashRow?->original_sha256 ?? 'N/A';
        $folio = 'DOC-'.$document->id;

        $otpEvent = \App\Models\DocumentEvent::where('document_id', $document->id)
            ->where('event_type', 'otp_verified')
            ->latest('occurred_at')
            ->first();
        $otpVerifiedAt = $otpEvent?->occurred_at ?? now();

        $employee = collect($signers)->firstWhere('role', 'employee');
        $director = collect($signers)->firstWhere('role', 'director');

        // Firmas
        $employeeSigFull = ($employee && $employee->signature_path) ? \Storage::path($employee->signature_path) : null;
        $directorSigFull = ($director && $director->signature_path) ? \Storage::path($director->signature_path) : null;

        // Selfies (liveness)
        $empLive = $employee ? \App\Models\DocumentLiveness::where('document_id', $document->id)
            ->where('signer_id', $employee->id)->latest('id')->first() : null;
        $dirLive = $director ? \App\Models\DocumentLiveness::where('document_id', $document->id)
            ->where('signer_id', $director->id)->latest('id')->first() : null;

        $empLivePath = $empLive ? \Storage::path($empLive->storage_path) : null;
        $dirLivePath = $dirLive ? \Storage::path($dirLive->storage_path) : null;

        \Storage::makeDirectory('contracts/signed');

        $fileName = $stage === 'employee'
            ? 'signed_employee_doc_'.$document->id.'_'.time().'.pdf'
            : 'signed_final_doc_'.$document->id.'_'.time().'.pdf';

        $signedPath = 'contracts/signed/'.$fileName;
        $signedFullPath = \Storage::path($signedPath);

        // --------------------------------------------
        // PASO A: Generar PDF (1a pasada) SIN hash firmado impreso
        // --------------------------------------------
        $this->renderPdfWithEvidencePage(
            $originalFullPath,
            $signedFullPath,
            $document,
            $folio,
            $stage,
            $originalSha,
            null, // signedSha = null en 1a pasada
            $evidenceId,
            $evidenceSha,
            $otpVerifiedAt,
            $request,
            $employee,
            $director,
            $employeeSigFull,
            $directorSigFull,
            $empLive,
            $dirLive,
            $empLivePath,
            $dirLivePath
        );

        // Calcular hash del PDF firmado ya generado
        $signedSha256 = hash_file('sha256', $signedFullPath) ?: null;

        // --------------------------------------------
        // PASO B: Generar PDF (2a pasada) YA con hash firmado impreso
        // --------------------------------------------
        $this->renderPdfWithEvidencePage(
            $originalFullPath,
            $signedFullPath,
            $document,
            $folio,
            $stage,
            $originalSha,
            $signedSha256,
            $evidenceId,
            $evidenceSha,
            $otpVerifiedAt,
            $request,
            $employee,
            $director,
            $employeeSigFull,
            $directorSigFull,
            $empLive,
            $dirLive,
            $empLivePath,
            $dirLivePath
        );

        // Recalcular hash final (ya con el hash impreso)
        $signedSha256 = hash_file('sha256', $signedFullPath) ?: $signedSha256;

        return [$signedPath, $signedFullPath, $signedSha256];
    }

    // //// metodos nuevos de prueba de vida ///////////////
    private function renderPdfWithEvidencePage(
        string $originalFullPath,
        string $outFullPath,
        \App\Models\Document $document,
        string $folio,
        string $stage,
        string $originalSha,
        ?string $signedSha,
        string $evidenceId,
        string $evidenceSha,
        $otpVerifiedAt,
        \Illuminate\Http\Request $request,
        $employee,
        $director,
        ?string $employeeSigFull,
        ?string $directorSigFull,
        $empLive,
        $dirLive,
        ?string $empLivePath,
        ?string $dirLivePath
    ): void {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi;
        $pdf->SetCreator('Contratos Firma');
        $pdf->SetAuthor('Contratos Firma');
        $pdf->SetTitle($document->title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Evitar brincos / páginas extra
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false, 0);

        $pageCount = $pdf->setSourceFile($originalFullPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            // Footer compacto (todas las páginas)
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(90, 90, 90);

            $footer = sprintf(
                '%s | Hash original: %s | OTP: %s | Página %d/%d',
                $folio,
                substr($originalSha, 0, 16).'…',
                \Carbon\Carbon::parse($otpVerifiedAt)->format('Y-m-d H:i:s'),
                $pageNo,
                $pageCount
            );

            $pdf->SetXY(10, $size['height'] - 6);
            $pdf->Cell(0, 4, $footer, 0, 0, 'L', false);

            // Última página del contrato: estampar firmas (si aplica)
           if ($pageNo === $pageCount) {
                $pageW = $size['width'];
                $pageH = $size['height'];

                // Tamaño firma (mm)
                $sigW = 55;

                // ✅ Subir firma un poco (antes 0.78)
                $ySig = $pageH * 0.74;

                // Carta: patrón izquierda, trabajador derecha
                $xPatron = $pageW * 0.14;
                $xTrab   = $pageW * 0.58;

                // ✅ Estampar firmas
                if ($directorSigFull && file_exists($directorSigFull)) {
                    $pdf->Image($directorSigFull, $xPatron, $ySig, $sigW, 0, 'PNG');
                }
                if ($employeeSigFull && file_exists($employeeSigFull)) {
                    $pdf->Image($employeeSigFull, $xTrab, $ySig, $sigW, 0, 'PNG');
                }

                // -------------------------
                // ✅ Línea + nombre + leyenda
                // -------------------------
                $pdf->SetTextColor(40, 40, 40);
                $pdf->SetDrawColor(60, 60, 60);
                $pdf->SetLineWidth(0.3);

                // Ajustes verticales (puedes afinar 1-2mm si lo necesitas)
                $yLine   = $ySig + 19;  // línea debajo de firma
                $yName   = $yLine + 2;  // nombre debajo de línea
                $yRole   = $yName + 5;  // leyenda debajo del nombre

                // Longitud de la línea (aprox ancho del nombre)
                // Puedes dejarlo igual al ancho del bloque ($sigW) para que sea consistente.
                $lineW = $sigW;

                // Fuente
                $pdf->SetFont('helvetica', '', 8);

                // --- PATRÓN (izquierda) ---
                // Línea
                $pdf->Line($xPatron, $yLine, $xPatron + $lineW, $yLine);

                // Nombre centrado
                $pdf->SetXY($xPatron, $yName);
                $pdf->MultiCell($sigW, 4, ($director?->name ?? '—'), 0, 'C', false);

                // Leyenda centrada
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($xPatron, $yRole);
                $pdf->MultiCell($sigW, 4, 'EL PATRÓN', 0, 'C', false);

                // --- TRABAJADOR (derecha) ---
                // Línea
                $pdf->Line($xTrab, $yLine, $xTrab + $lineW, $yLine);

                // Nombre centrado
                $pdf->SetXY($xTrab, $yName);
                $pdf->MultiCell($sigW, 4, ($employee?->name ?? '—'), 0, 'C', false);

                // Leyenda centrada
                $pdf->SetXY($xTrab, $yRole);
                $pdf->MultiCell($sigW, 4, 'EL TRABAJADOR', 0, 'C', false);
            }
        }

        // --------------------------------------------
        // HOJA EXTRA: EVIDENCIAS + PRUEBA DE VIDA (SIEMPRE)
        // --------------------------------------------
        $pdf->AddPage('P', 'LETTER');

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetXY(10, 12);
        $pdf->Cell(0, 8, 'HOJA DE EVIDENCIAS DE FIRMA ELECTRÓNICA', 0, 1, 'L', false);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(40, 40, 40);

        $lines = [];
        $lines[] = "Documento: {$folio} - {$document->title}";
        $lines[] = 'Etapa: '.strtoupper($stage);
        $lines[] = 'Fecha generación: '.now()->format('Y-m-d H:i:s');
        $lines[] = "Evidence ID: {$evidenceId}";
        $lines[] = "Evidence-SHA256: {$evidenceSha}";
        $lines[] = "SHA-256 PDF original: {$originalSha}";
        $lines[] = 'SHA-256 PDF firmado: '.($signedSha ?? '(calculando...)');
        $lines[] = 'OTP verificado: '.\Carbon\Carbon::parse($otpVerifiedAt)->format('Y-m-d H:i:s');
        $lines[] = 'IP (request): '.$request->ip();
        $lines[] = 'User-Agent: '.substr((string) $request->userAgent(), 0, 120);

        if ($employee) {
            $lines[] = "Trabajador: {$employee->name} ({$employee->email}) | Status: {$employee->status} | Firmó: ".($employee->signed_at ? $employee->signed_at->format('Y-m-d H:i:s') : '—');
        }
        if ($director) {
            $lines[] = "Patrón: {$director->name} ({$director->email}) | Status: {$director->status} | Firmó: ".($director->signed_at ? $director->signed_at->format('Y-m-d H:i:s') : '—');
        }

        $pdf->SetXY(10, 24);
        $pdf->MultiCell(0, 4.6, implode("\n", $lines), 0, 'L', false);
        // ----------------------------
        // QR de verificación (TCPDF nativo)
            // ----------------------------
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(30, 30, 30);

            // Payload del QR (corto, estable y verificable)
           $qrPayload = route('evidence.public.show', ['evidenceId' => $evidenceId]);

            // Posición (arriba derecha de la hoja de evidencia)
            $qrX = 160;  // mm (ajusta si quieres)
            $qrY = 10;   // mm
            $qrSize = 30; // mm (tamaño del QR)

            // Estilo QR
            $style = [
                'border' => 0,
                'padding' => 0,
                'fgcolor' => [0,0,0],
                'bgcolor' => false, // transparente
                'module_width' => 1,
                'module_height' => 1,
            ];

            // Dibuja el QR (QRCODE, nivel M)
            $pdf->write2DBarcode($qrPayload, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, $style, 'N');

            // Etiqueta bajo el QR
            $pdf->SetXY($qrX, $qrY + $qrSize + 2);
            $pdf->MultiCell($qrSize, 4,
                "Verificar evidencia\n".
                substr($qrPayload, 0, 60)."...",
                0, 'L', false
            );

        // Selfies (2 columnas)
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(10, 80);
        $pdf->Cell(0, 6, 'PRUEBA DE VIDA (SELFIE)', 0, 1, 'L', false);

        $pdf->SetFont('helvetica', '', 9);

        $leftX = 10;
        $rightX = 112;
        $imgY = 98;
        $imgW = 90;
        $imgH = 60;

        // Trabajador
        $pdf->SetXY($leftX, $imgY - 6);
        $pdf->Cell(0, 5, 'Trabajador: '.($employee?->name ?? 'N/A'), 0, 1, 'L', false);

        if ($empLive && $empLivePath && file_exists($empLivePath)) {
            $pdf->Image($empLivePath, $leftX, $imgY, $imgW, $imgH);
            $pdf->SetXY($leftX, $imgY + $imgH + 2);
            $pdf->MultiCell($imgW, 4.2,
                "Challenge: {$empLive->challenge}\n".
                "Selfie-SHA256: {$empLive->sha256}\n".
                'Capturada: '.($empLive->captured_at?->format('Y-m-d H:i:s') ?? '—'),
                0, 'L', false
            );
        } else {
            $pdf->SetXY($leftX, $imgY);
            $pdf->MultiCell($imgW, 5, 'Sin selfie registrada.', 1, 'L', false);
        }

        // Patrón
        $pdf->SetXY($rightX, $imgY - 6);
        $pdf->Cell(0, 5, 'Patrón: '.($director?->name ?? 'N/A'), 0, 1, 'L', false);

        if ($dirLive && $dirLivePath && file_exists($dirLivePath)) {
            $pdf->Image($dirLivePath, $rightX, $imgY, $imgW, $imgH);
            $pdf->SetXY($rightX, $imgY + $imgH + 2);
            $pdf->MultiCell($imgW, 4.2,
                "Challenge: {$dirLive->challenge}\n".
                "Selfie-SHA256: {$dirLive->sha256}\n".
                'Capturada: '.($dirLive->captured_at?->format('Y-m-d H:i:s') ?? '—'),
                0, 'L', false
            );
        } else {
            $pdf->SetXY($rightX, $imgY);
            $pdf->MultiCell($imgW, 5, 'Sin selfie registrada.', 1, 'L', false);
        }

        // Guardar archivo
        $pdf->Output($outFullPath, 'F');
    }

    public function livenessChallenge(Request $request, string $token)
    {
        [$tok, $document, $signer] = $this->resolveTokenOrFail($token);

        if ($tok->status !== 'active') {
            return response()->json(['ok' => false, 'message' => 'Token inactivo.'], 410);
        }

        // reto corto, fácil de mostrar
        $challenge = strtoupper(Str::random(6));

        $request->session()->put('liveness_challenge_'.$tok->id, $challenge);
        $request->session()->put('liveness_challenge_at_'.$tok->id, now()->toDateTimeString());

        return response()->json(['ok' => true, 'challenge' => $challenge]);
    }

    public function uploadLiveness(Request $request, string $token)
    {
        [$tok, $document, $signer] = $this->resolveTokenOrFail($token);

        if ($tok->status !== 'active') {
            return back()->withErrors(['liveness' => 'Token inactivo.']);
        }

        // Recomendación: exige OTP verificado antes de selfie
        if (! $request->session()->get('otp_verified_'.$tok->id, false)) {
            return back()->withErrors(['liveness' => 'Primero verifica el OTP.']);
        }

        $request->validate([
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
            'consent_liveness' => ['required', 'accepted'],
        ]);

        $challenge = (string) $request->session()->get('liveness_challenge_'.$tok->id, '');
        if ($challenge === '') {
            return back()->withErrors(['liveness' => 'Falta el reto de selfie. Vuelve a intentar.']);
        }

        $file = $request->file('selfie');

        Storage::makeDirectory('contracts/liveness');
        $path = $file->storeAs(
            'contracts/liveness',
            'selfie_signer_'.$signer->id.'_'.time().'.'.$file->getClientOriginalExtension()
        );

        $full = Storage::path($path);
        $sha = hash_file('sha256', $full) ?: '';

        DocumentLiveness::create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'token_id' => $tok->id,
            'type' => 'selfie',
            'challenge' => $challenge,
            'storage_disk' => config('filesystems.default', 'local'),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'image/jpeg',
            'size_bytes' => $file->getSize() ?: 0,
            'sha256' => $sha,
            'captured_at' => now(),
            'captured_ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 200),
        ]);

        // opcional: registra evento
        DocumentEvent::log($document->id, 'liveness_selfie_uploaded', $request, [
            'token_id' => $tok->id,
            'signer_id' => $signer->id,
            'challenge' => $challenge,
            'sha256' => $sha,
            'path' => $path,
        ]);

        return back()->with('ok', 'Selfie capturada correctamente.');
    }

    // //Fin prueba de vida  ////////////////////////

//////inicia mail a destinatarios///////////

private function emailSignedPdfTo(
    array $toList,
    string $subject,
    string $title,
    string $stageLabel,
    string $signedFullPath,
    string $fileName,
    ?string $evidenceUrl
): void {
    $attach = $this->shouldAttachPdf($signedFullPath, 10 * 1024 * 1024);

    foreach ($toList as $to) {
        $mailable = new \App\Mail\ContractSignedMail(
            subjectLine: $subject,
            title: $title,
            stageLabel: $stageLabel,
            filePath: $signedFullPath,
            fileName: $fileName,
            evidenceUrl: $evidenceUrl
        );

        // Si es grande, no adjuntamos (la vista ya muestra evidenceUrl)
        if (!$attach) {
            $mailable->withoutAttachment = true; // bandera que usaremos en el mailable
        }

        \Mail::to($to['email'], $to['name'] ?? null)
            ->bcc('sistemas@sumed.com.mx')
            ->send($mailable);
    }
}

        private function shouldAttachPdf(string $fullPath, int $maxBytes = 10485760): bool
{
    if (!file_exists($fullPath)) return false;
    $size = filesize($fullPath);
    if ($size === false) return false;
    return $size <= $maxBytes; // 10 MB
}
/////fin mail destinatarios////////////////////

    private function canonicalJson(array $data): string
    {
        // Ordenar llaves recursivamente para que el JSON sea determinista
        $sortRecursive = function (&$arr) use (&$sortRecursive) {
            if (! is_array($arr)) {
                return;
            }

            // Si es array asociativo, ordena por key
            $isAssoc = array_keys($arr) !== range(0, count($arr) - 1);
            if ($isAssoc) {
                ksort($arr);
            }

            foreach ($arr as &$v) {
                if (is_array($v)) {
                    $sortRecursive($v);
                }
            }
        };

        $sortRecursive($data);

        // JSON estable (sin escapes raros)
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function createSignerToken(Document $document, DocumentSigner $signer, int $expiresHours = 72): string
    {
        $raw = Str::random(64);

        // En tu modelo actual el
        // "recipient principal" existe:
        // Normalmente es el colaborador (o el recipient del documento).
        // Si tu documento solo tiene 1 recipient en document_recipients, tomamos ese.
        $recipient = \App\Models\DocumentRecipient::where('document_id', $document->id)
            ->orderBy('id')
            ->first();

        if (! $recipient) {
            abort(500, 'No existe recipient para este documento. (document_recipients vacío)');
        }

        DocumentToken::create([
            'document_id' => $document->id,
            'recipient_id' => $recipient->id,  // ✅ requerido por  schema
            'signer_id' => $signer->id,        // ✅ para flujo multi-firma
            'token_hash' => hash('sha256', $raw),
            'purpose' => 'review_sign',
            'status' => 'active',
            'expires_at' => now()->addHours($expiresHours),
        ]);

        return $raw;
    }
}
