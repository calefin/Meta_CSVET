<x-filament-panels::page>
    <div class="space-y-6">
        @if(auth()->user()->role === 'admin')
            <div class="p-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl">
                <h3 class="text-lg font-medium text-yellow-800 dark:text-yellow-200 font-bold">Acesso Administrativo</h3>
                <p class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                    Como Administrador (Prof. Atilio), você tem acesso completo a todos os logs no menu <strong>Mural de E-mails</strong> e nas ações de auditoria de cada trabalho. Esta página de transparência é dedicada à visualização direta dos dados de cada aluno logado.
                </p>
            </div>
        @else
            <!-- Dados Cadastrais do Aluno -->
            <div class="fi-card p-6 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                <h3 class="text-lg font-semibold text-teal-600 dark:text-teal-400 mb-4">Seus Dados Pessoais & Governança de Privacidade</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div>
                        <p class="text-slate-500 dark:text-slate-400">Nome Completo</p>
                        <p class="font-medium text-slate-800 dark:text-slate-200 text-base">{{ auth()->user()->name }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400">Registro Geral de Matrícula (RGM)</p>
                        <p class="font-medium text-slate-800 dark:text-slate-200 text-base">{{ $student->registration ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400">Universidade / Instituição</p>
                        <p class="font-medium text-slate-800 dark:text-slate-200 text-base">{{ $student->institution ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400">Campus</p>
                        <p class="font-medium text-slate-800 dark:text-slate-200 text-base">{{ $student->campus ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400">E-mail de Contato</p>
                        <p class="font-medium text-slate-800 dark:text-slate-200 text-base">{{ auth()->user()->email }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400">Telefone</p>
                        <p class="font-medium text-slate-800 dark:text-slate-200 text-base">{{ $student->phone ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- Registro de Consentimento LGPD -->
            <div class="fi-card p-6 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                <h3 class="text-lg font-semibold text-teal-600 dark:text-teal-400 mb-2">Consentimento Jurídico <a href="https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm" target="_blank" class="underline hover:text-teal-500">Lei nº 13.709 (LGPD)</a></h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Histórico do seu termo de consentimento obrigatório para tratamento de dados pessoais.</p>
                
                <div class="p-4 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-850 rounded-lg mb-4 text-xs font-mono text-slate-600 dark:text-slate-300">
                    "Consinto que meus dados pessoais e acadêmicos (incluindo RGM, campus, instituição de ensino, e-mail, telefone e, quando aplicável a cursos/treinamentos, CPF) sejam coletados e armazenados pela plataforma profatilio.com.br sob a supervisão do Prof. Atilio, exclusivamente para finalidades de acompanhamento de orientação acadêmica, cursos/treinamentos e emissão de certificados, relatórios de estágio/pesquisa e auditorias digitais de compromissos assumidos, em conformidade com as regras de guarda temporal de logs do Marco Civil da Internet."
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm border-t border-slate-100 dark:border-slate-850 pt-4">
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                            Consentimento Ativo e Válido
                        </span>
                    </div>
                    <div class="text-right text-slate-500 dark:text-slate-400 text-xs">
                        Aceito em: {{ $student->terms_accepted_at ? \Carbon\Carbon::parse($student->terms_accepted_at)->format('d/m/Y H:i:s') : 'N/A' }}
                    </div>
                </div>
            </div>

            <!-- Assinaturas e Histórico Forense (Fluxo 8) -->
            <div class="fi-card p-6 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                <h3 class="text-lg font-semibold text-teal-600 dark:text-teal-400 mb-2">Suas Assinaturas Digitais e Registros Forenses</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Trabalhos e termos homologados por assinatura eletrônica criptográfica (SHA-256).</p>

                @if($submissions->isEmpty())
                    <div class="text-center py-6 text-slate-500 dark:text-slate-400 text-sm">
                        Nenhum Termo de Aceite de IC assinado digitalmente até o momento.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 pb-2 text-slate-500 dark:text-slate-400">
                                    <th class="py-2">Processo</th>
                                    <th class="py-2">Título do Projeto</th>
                                    <th class="py-2">Data/Hora (UTC)</th>
                                    <th class="py-2 font-mono">Hash SHA-256</th>
                                    <th class="py-2 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($submissions as $sub)
                                    <tr class="border-b border-slate-100 dark:border-slate-900 hover:bg-slate-50/50 dark:hover:bg-slate-950/50">
                                        <td class="py-3 font-semibold text-slate-800 dark:text-slate-200">{{ $sub->process_id }}</td>
                                        <td class="py-3 text-slate-700 dark:text-slate-300">{{ $sub->title }}</td>
                                        <td class="py-3 text-slate-500 dark:text-slate-450">{{ $sub->metadata['forensic_timestamp'] ?? 'N/A' }}</td>
                                        <td class="py-3 font-mono text-xs text-slate-500 dark:text-slate-400" title="{{ $sub->metadata['forensic_hash'] ?? '' }}">
                                            {{ substr($sub->metadata['forensic_hash'] ?? 'N/A', 0, 16) }}...
                                        </td>
                                        <td class="py-3 text-right">
                                            <a href="{{ url('/documento/recibo/' . $sub->id) }}" target="_blank" 
                                               class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-teal-600 hover:text-white border border-teal-600 hover:bg-teal-600 rounded-md transition duration-200">
                                                Visualizar Recibo
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
