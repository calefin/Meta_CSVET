<?php

namespace App\Http\Controllers;

use App\Models\NecropsyRequest;
use App\Services\NecropsyNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class NecropsyRequestController extends Controller
{
    public function __construct(protected NecropsyNotificationService $notifier)
    {
    }

    /**
     * Passo 1: Formulário Público de Orçamento
     */
    public function index()
    {
        return view('necropsy.quote_form', ['direct' => false]);
    }

    /**
     * Link fácil (/autorizar-necropsia) para quem já combinou a necropsia
     * diretamente com o Prof. Atilio: mesmo formulário, sem o enfoque de
     * "orçamento", e o pedido já entra como respondido.
     */
    public function direct()
    {
        return view('necropsy.quote_form', ['direct' => true]);
    }

    /**
     * Processa o Passo 1, calcula o preço e cria o registro com token único
     */
    public function store(Request $request)
    {
        // Honeypot: campo invisível que só robô preenche. Como o passo 1 agora
        // avisa o professor, não deixamos spam disparar e-mail/Telegram.
        $direct = $request->boolean('direct');

        if ($request->filled('website')) {
            return redirect()->route($direct ? 'necropsy.direto' : 'necropsy.orcamento');
        }

        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'requester_phone' => 'required|string|min:8|max:255',
            'requester_email' => 'nullable|email|max:255',
            'species' => 'required|string|max:255',
            'breed' => 'nullable|string|max:255',
            'age' => 'nullable|string|max:255',
            'sex' => 'nullable|string|in:Macho,Fêmea,Indeterminado',
            'pelage' => 'nullable|string|max:255',
            'weight' => 'required|numeric|min:0.1',
            'has_cremation_plan' => 'required|boolean',
            'cremation_company' => 'nullable|required_if:has_cremation_plan,1|string|max:255',
            'is_euthanasia' => 'required|boolean',
            'requested_by_vet' => 'required|boolean',
            'vet_crmv' => 'nullable|required_if:requested_by_vet,1|string|max:255',
            'vet_state' => 'nullable|required_if:requested_by_vet,1|string|max:2',
            'vet_establishment' => 'nullable|string|max:255',
        ], [
            'requester_name.required' => 'Informe seu nome para podermos retornar.',
            'requester_phone.required' => 'Informe um telefone/WhatsApp para podermos retornar.',
            'requester_phone.min' => 'Informe o telefone com DDD.',
            'requester_email.email' => 'Informe um e-mail válido.',
            'species.required' => 'A espécie do animal é obrigatória.',
            'weight.required' => 'O peso do animal é obrigatório.',
            'weight.numeric' => 'O peso deve ser um valor numérico.',
            'cremation_company.required_if' => 'Por favor, informe a empresa do plano de cremação.',
            'vet_crmv.required_if' => 'O CRMV é obrigatório quando a solicitação é por médico veterinário.',
            'vet_state.required_if' => 'O estado do CRMV é obrigatório quando a solicitação é por médico veterinário.',
        ]);

        // Calculate Pricing
        $pricing = NecropsyRequest::calculatePricing($validated['weight'], $validated['requested_by_vet']);

        // Generate security token
        $token = Str::random(32);

        // Save request
        $necropsyRequest = NecropsyRequest::create(array_merge($validated, $pricing, [
            'token' => $token,
            'status' => 'quote_generated',
            // Pelo link direto a pessoa já falou com o professor: não fica "aguardando retorno".
            'contacted_at' => $direct ? now() : null,
        ]));

        // Avisa o professor na hora (e-mail + Telegram) para retornar rápido,
        // mesmo que o interessado não chegue a assinar a autorização.
        $this->notifier->notifyNewQuote($necropsyRequest, $direct);

        return redirect()->route('necropsy.autorizar', ['token' => $token]);
    }

    /**
     * Passo 2: Exibição do Orçamento e Formulário de Autorização/Assinatura
     */
    public function showAuthorization($token)
    {
        $necropsyRequest = NecropsyRequest::where('token', $token)->firstOrFail();

        // If already signed, redirect to success
        if ($necropsyRequest->status === 'authorized') {
            return redirect()->route('necropsy.sucesso', ['token' => $token]);
        }

        return view('necropsy.auth_form', compact('necropsyRequest'));
    }

    /**
     * Processa o Passo 2, gera a assinatura forense e o PDF final
     */
    public function saveAuthorization(Request $request, $token)
    {
        $necropsyRequest = NecropsyRequest::where('token', $token)->firstOrFail();

        if ($necropsyRequest->status === 'authorized') {
            return redirect()->route('necropsy.sucesso', ['token' => $token]);
        }

        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email|max:255',
            'requester_phone' => 'required|string|max:255',
            'requester_cpf' => 'required|string|max:14',
            'requester_rg' => 'required|string|max:20',
            'requester_cnpj' => 'nullable|string|max:18',
            'requester_address' => 'required|string|max:255',
            'requester_neighborhood' => 'required|string|max:255',
            'requester_complement' => 'nullable|string|max:255',
            'requester_city' => 'required|string|max:255',
            'requester_state' => 'required|string|max:2',
            'requester_zip' => 'required|string|max:10',
            
            // Animal history/details
            'animal_name' => 'required|string|max:255',
            'has_prosthesis' => 'required|boolean',
            'prosthesis_details' => 'nullable|required_if:has_prosthesis,1|string|max:255',
            'had_surgery' => 'required|boolean',
            'surgery_details' => 'nullable|required_if:had_surgery,1|string|max:255',
            
            // Witnesses (optional)
            'witness1_name' => 'nullable|string|max:255',
            'witness1_cpf' => 'nullable|required_with:witness1_name|string|max:14',
            'witness2_name' => 'nullable|string|max:255',
            'witness2_cpf' => 'nullable|required_with:witness2_name|string|max:14',

            // Signature
            'signature_name' => 'required|string|max:255',
            'signature_image' => 'required|string', // Drawn signature in Base64
            'terms_accepted' => 'required|accepted',
        ], [
            'requester_name.required' => 'O nome do requisitante é obrigatório.',
            'requester_email.required' => 'O e-mail é obrigatório.',
            'requester_phone.required' => 'O telefone de contato é obrigatório.',
            'requester_cpf.required' => 'O CPF é obrigatório.',
            'requester_rg.required' => 'O RG é obrigatório.',
            'requester_address.required' => 'O endereço é obrigatório.',
            'requester_neighborhood.required' => 'O bairro é obrigatório.',
            'requester_city.required' => 'A cidade é obrigatória.',
            'requester_zip.required' => 'O CEP é obrigatório.',
            'animal_name.required' => 'O nome do animal é obrigatório.',
            'signature_name.required' => 'O nome completo para a assinatura por extenso é obrigatório.',
            'signature_image.required' => 'A assinatura digital desenhada é obrigatória.',
            'terms_accepted.accepted' => 'Você precisa aceitar os termos de declaração e consentimento.',
            'prosthesis_details.required_if' => 'Por favor, detalhe as próteses do animal.',
            'surgery_details.required_if' => 'Por favor, detalhe as cirurgias anteriores do animal.',
            'witness1_cpf.required_with' => 'O CPF da Testemunha 1 é obrigatório se o nome for preenchido.',
            'witness2_cpf.required_with' => 'O CPF da Testemunha 2 é obrigatório se o nome for preenchido.',
        ]);

        // Forensic Capture
        $ip = $request->ip() ?? '127.0.0.1';
        $userAgent = $request->userAgent() ?? 'N/A';
        $timestamp = now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s.v');
        
        // Generate Forensic Hash (SHA-256 of concatenated data)
        $forensicRaw = implode('|', [
            $necropsyRequest->id,
            $validated['requester_name'],
            $validated['requester_cpf'],
            $validated['animal_name'],
            $necropsyRequest->species,
            $necropsyRequest->weight,
            $ip,
            $userAgent,
            $timestamp
        ]);
        $forensicHash = hash('sha256', $forensicRaw);

        // Update database record
        $necropsyRequest->update(array_merge($validated, [
            'status' => 'authorized',
            'signed_at' => now(),
            'forensic_hash' => $forensicHash,
            'forensic_ip' => $ip,
            'forensic_timestamp' => $timestamp,
            'forensic_user_agent' => $userAgent,
        ]));

        // Generate PDF Document
        try {
            $html = view('necropsy.auth_pdf', compact('necropsyRequest'))->render();
            
            // Render PDF via DomPDF
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            
            // Define directory and filename
            $dir = 'public/necropsias';
            if (!Storage::exists($dir)) {
                Storage::makeDirectory($dir);
            }
            
            $filename = "autorizacao_necropsia_{$necropsyRequest->id}_" . Str::slug($necropsyRequest->requester_name) . ".pdf";
            $filePath = "{$dir}/{$filename}";
            
            Storage::put($filePath, $pdf->output());
            
            // Save PDF path in database
            $necropsyRequest->update([
                'signed_pdf_path' => $filePath,
            ]);

        } catch (\Throwable $e) {
            Log::error("Necropsia #{$necropsyRequest->id}: erro na geração do PDF: " . $e->getMessage());
        }

        // Avisos fora do try do PDF: se o PDF falhar, o professor é avisado mesmo assim.
        $necropsyRequest->refresh();
        $this->notifier->notifyAuthorized($necropsyRequest);

        try {
            // Notify Requester/Tutor via Email
            $requesterBody = "Olá, {$necropsyRequest->requester_name}!\n\n" .
                "Confirmamos o preenchimento e assinatura digital do termo de Autorização para Exame Necroscópico do animal **{$necropsyRequest->animal_name}**.\n\n" .
                "O valor final calculado do serviço foi de **R$ " . number_format($necropsyRequest->final_price, 2, ',', '.') . "**.\n\n" .
                "Anexamos a este e-mail o seu recibo e termo de autorização assinado digitalmente para seus registros e comprovação jurídica.\n\n" .
                "Seu código de integridade digital (Hash SHA-256) é:\n" .
                "`{$forensicHash}`";

            Mail::to($necropsyRequest->requester_email)->send(
                new \App\Mail\AppNotificationMail(
                    title: 'Termo de Necropsia Assinado com Sucesso',
                    messageContent: $requesterBody,
                    actionUrl: route('necropsy.pdf', ['token' => $token]),
                    actionText: 'Download do Termo PDF',
                    subject: "Autorização de Necropsia Confirmada - Prof. Atilio"
                )
            );
        } catch (\Throwable $e) {
            Log::error("Necropsia #{$necropsyRequest->id}: falha ao enviar e-mail ao requisitante: " . $e->getMessage());
        }

        return redirect()->route('necropsy.sucesso', ['token' => $token]);
    }

    /**
     * Passo 3: Exibição da Tela de Sucesso
     */
    public function showSuccess($token)
    {
        $necropsyRequest = NecropsyRequest::where('token', $token)->firstOrFail();

        if ($necropsyRequest->status !== 'authorized') {
            return redirect()->route('necropsy.autorizar', ['token' => $token]);
        }

        return view('necropsy.success', compact('necropsyRequest'));
    }

    /**
     * Rota de download público/seguro do PDF
     */
    public function downloadPdf($token)
    {
        $necropsyRequest = NecropsyRequest::where('token', $token)->firstOrFail();

        if (!$necropsyRequest->signed_pdf_path || !Storage::exists($necropsyRequest->signed_pdf_path)) {
            abort(404, 'Arquivo PDF não encontrado.');
        }

        $path = Storage::path($necropsyRequest->signed_pdf_path);
        $filename = "autorizacao_necropsia_" . Str::slug($necropsyRequest->animal_name) . ".pdf";

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
