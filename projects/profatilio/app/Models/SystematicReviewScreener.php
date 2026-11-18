<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aluno que faz a triagem cega de um projeto. Sempre tem conta de aluno no
 * sistema (externo é obrigado a criar) e só faz a triagem depois de assinar o termo.
 */
class SystematicReviewScreener extends Model
{
    // 2026.10.6: cláusulas 5 e 6 alinhadas ao protocolo v1.1 (IA declarada).
    // 2026.10.7: cláusula 6 cita a conversa registrada do projeto (nada da revisão cega pode ser exposto).
    public const TERMS_VERSION = '2026.10.7';

    protected $fillable = [
        'systematic_review_id',
        'user_id',
        'name',
        'email',
        'label',
        'stages',
        'invited_at',
        'terms_accepted_at',
        'terms_version',
        'terms_sha256',
        'terms_text',
        'terms_signature',
        'terms_ip',
        'terms_user_agent',
        'finished_at',
        'removed_at',
        'chat_read_at',
    ];

    protected $casts = [
        'stages' => 'array',
        'finished_at' => 'array',
        'invited_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'removed_at' => 'datetime',
        'chat_read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (SystematicReviewScreener $screener) {
            if ($screener->isDirty('email')) {
                $screener->email = mb_strtolower(trim((string) $screener->email));
                $screener->user_id = User::where('email', $screener->email)->value('id') ?? $screener->user_id;
            }
        });
    }

    /**
     * Vincula convites de triagem à conta — chamado só depois que o e-mail foi
     * validado (senão qualquer um com outro convite poderia "tomar" o lugar).
     */
    public static function linkVerifiedUser(User $user): void
    {
        static::whereNull('user_id')
            ->where('email', mb_strtolower(trim((string) $user->email)))
            ->update(['user_id' => $user->id]);
    }

    /** Mesmo padrão do cadastro de usuários. O termo assinado guarda o nome como estava na assinatura. */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = User::normalizeName($value);
    }

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function decisions()
    {
        return $this->hasMany(SystematicReviewDecision::class);
    }

    public function hasSignedTerms(): bool
    {
        // Treino com dados fora dos projetos: não há o que proteger, e o aluno não assina termo de mentira.
        if ($this->systematicReview?->is_training) {
            return true;
        }

        return $this->terms_accepted_at !== null && $this->terms_version === self::TERMS_VERSION;
    }

    /** Mensagens dos outros (professor e dupla) que o aluno ainda não leu. Sem termo, não lê a conversa. */
    public function unreadMessages()
    {
        return $this->systematicReview->messages()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', (int) $this->user_id))
            ->when($this->chat_read_at, fn ($q) => $q->where('created_at', '>', $this->chat_read_at));
    }

    public function unreadMessagesCount(): int
    {
        return $this->hasSignedTerms() ? $this->unreadMessages()->count() : 0;
    }

    public function markChatRead(): void
    {
        $this->forceFill(['chat_read_at' => now()])->save();
    }

    /** Etapas em que este triador participa (padrão: as duas). */
    public function participatesIn(string $stage): bool
    {
        $stages = $this->stages ?: [SystematicReview::STAGE_TA, SystematicReview::STAGE_FT];

        return in_array($stage, $stages, true);
    }

    public function hasFinished(string $stage): bool
    {
        return filled($this->finished_at[$stage] ?? null);
    }

    public function hasAccount(): bool
    {
        return $this->user_id !== null;
    }

    public function accountStatus(): string
    {
        if (!$this->user_id) {
            return 'Convite enviado — falta criar a conta';
        }
        $student = $this->user?->student;
        if ($this->user?->role === 'admin') {
            return 'Conta ativa';
        }
        if (!$student) {
            return 'Conta sem cadastro de aluno';
        }

        return match ($student->status) {
            'active', 'inactive' => 'Conta ativa',
            'awaiting_email_validation' => 'Falta validar o e-mail',
            'awaiting_advisor_approval' => 'Aguardando sua aprovação',
            default => $student->status,
        };
    }

    public function canUsePortal(): bool
    {
        return $this->user !== null && $this->user->canAccessPanel(\Filament\Facades\Filament::getPanel('portal'));
    }

    /**
     * Texto integral do termo, personalizado com o projeto e o aluno. O que
     * o aluno assina é exatamente este texto (o SHA-256 dele fica guardado).
     */
    public static function termsText(SystematicReview $review, string $name, string $email): string
    {
        $title = $review->title;
        $version = self::TERMS_VERSION;

        return <<<TXT
TERMO DE CONFIDENCIALIDADE, PROPRIEDADE INTELECTUAL E PARTICIPAÇÃO EM PESQUISA
Versão {$version}

Projeto: "{$title}" ({$review->typeLabel()})
Coordenador/Orientador: Prof. Dr. Atilio Sersun Calefi
Participante: {$name} ({$email})

Ao assinar eletronicamente este termo, declaro que li, entendi e aceito as condições abaixo para participar deste projeto.

1. OBJETO. Participarei das etapas que me forem atribuídas (triagem de títulos e resumos, leitura de texto completo, extração de dados, avaliação de risco de viés ou outras), sempre sob a coordenação do Prof. Atilio, que é o orientador e o responsável pelo projeto e quem decide sobre o seu andamento. Reconheço o Prof. Dr. Atilio Sersun Calefi como orientador deste projeto e de todos os trabalhos dele derivados.

2. NATUREZA DA PARTICIPAÇÃO (MENTORIA CIENTÍFICA). A minha participação é voluntária, gratuita e de natureza exclusivamente educacional: trata-se de um processo de mentoria e orientação científica para a minha formação. Estou ciente e concordo que: (a) não há bolsa, salário, remuneração, ajuda de custo ou qualquer contraprestação financeira, nem ressarcimento de despesas; (b) não há jornada, horário fixo ou local de trabalho a cumprir: eu organizo livremente os meus horários, observando apenas os prazos acadêmicos das etapas, e a estimativa de horas semanais é só uma referência de dedicação; (c) a participação não configura relação de emprego (arts. 2º e 3º da Consolidação das Leis do Trabalho - CLT), estágio (Lei nº 11.788/2008) nem prestação de serviços, e não gera direitos trabalhistas, previdenciários ou indenizatórios; (d) aplica-se, no que couber, a Lei nº 9.608/1998 (serviço voluntário); (e) também não há nenhum custo para mim: nenhuma taxa ou cobrança é feita ao participante.

3. CONFIDENCIALIDADE. O tema, a pergunta de pesquisa, o protocolo, a estratégia de busca, a lista de estudos, as minhas decisões e as dos demais participantes, os dados extraídos, as análises e os resultados são CONFIDENCIAIS até a publicação do trabalho. Não vou divulgá-los a terceiros, inclusive colegas, outros professores, grupos de pesquisa, redes sociais ou aplicativos de mensagem, sem autorização expressa do Prof. Atilio.

4. PROIBIÇÃO DE REPRODUÇÃO. Não vou reproduzir, copiar, publicar, submeter, registrar ou distribuir, no todo ou em parte, o protocolo, os dados, as planilhas, os textos, as figuras ou os resultados deste projeto sem autorização prévia e por escrito do Prof. Atilio. Também não vou registrar protocolo próprio, nem iniciar ou propor a outro grupo um trabalho sobre o mesmo tema a partir das informações a que tive acesso aqui.

5. FERRAMENTAS EXTERNAS E INTELIGÊNCIA ARTIFICIAL. Os únicos usos de inteligência artificial neste projeto são os previstos e declarados no protocolo registrado: (a) a ordenação dos trabalhos na triagem de títulos e resumos, feita pela própria plataforma por um modelo que aprende apenas com as minhas decisões; e (b) quando eu for o(a) Revisor(a) 1 (líder), o pré-preenchimento da planilha de extração por um modelo de linguagem indicado pelo Prof. Atilio, que eu confiro valor a valor no texto original. Fora disso, não vou inserir textos, PDFs, dados ou resultados não publicados deste projeto em ferramentas de inteligência artificial, tradutores on-line, repositórios públicos ou plataformas de terceiros sem autorização do Prof. Atilio. O uso de inteligência artificial na revisão de linguagem do manuscrito só ocorre com autorização e é declarado à revista.

6. TRIAGEM CEGA E INTEGRIDADE. Farei as minhas decisões de forma independente, seguindo o protocolo e as instruções. Não vou combinar, comparar ou comentar decisões com o outro triador, nem tentar saber os votos dele(a), até o professor encerrar a etapa, por nenhum meio, inclusive na conversa do projeto no portal, que fica registrada e é lida pelo professor. Nessa conversa e fora dela, não vou expor quais estudos estou triando, como decidi, quantos incluí ou excluí, nem os dados que extraí. Sei que a concordância entre triadores é medida (coeficiente kappa) e relatada na publicação, e que todas as minhas decisões ficam registradas com data e hora. Não vou fabricar, falsificar ou copiar decisões ou dados. A decisão sobre cada estudo (incluir, excluir ou talvez), a leitura do texto completo, a conferência de cada dado extraído e a avaliação de risco de viés serão feitas por mim, pessoalmente: a ordenação da plataforma e o pré-preenchimento previstos na cláusula 5 não substituem a minha decisão, e não vou delegar essas tarefas, no todo ou em parte, a outras ferramentas de inteligência artificial (como ChatGPT, Gemini ou Copilot) nem a outra pessoa. Se eu for o(a) Revisor(a) 2, faço a extração sem nenhum pré-preenchimento.

7. USO ACADÊMICO AUTORIZADO. Com ciência prévia do Prof. Atilio, este projeto PODE ser utilizado por mim como Trabalho de Conclusão de Curso (TCC) e como Iniciação Científica (IC), e PODE ser apresentado em eventos científicos (congressos, simpósios, jornadas), sempre com o Prof. Atilio como orientador e com a citação do registro do protocolo, e sem antecipar resultados de forma que prejudique a publicação principal em periódico.

8. AUTORIA. A autoria e a ordem dos autores das publicações são definidas pelo Prof. Atilio conforme a contribuição efetiva de cada participante, segundo os critérios do ICMJE e a taxonomia CRediT. A participação em uma etapa não garante autoria automaticamente; contribuições que não atingirem os critérios de autoria serão reconhecidas nos agradecimentos.

9. PROPRIEDADE DOS DADOS E DO PROJETO. O protocolo, as bases de dados, as planilhas, os scripts e os resultados pertencem ao projeto coordenado pelo Prof. Atilio. Não posso publicar o trabalho, ou parte dele, como autor principal ou correspondente em outro veículo sem autorização por escrito.

10. PARTICIPAÇÃO PESSOAL E INTRANSFERÍVEL. A minha participação é pessoal e intransferível. Não posso repassar a minha vaga, as minhas tarefas, o tema, o protocolo, os dados ou os resultados a outra pessoa, nem conduzir este projeto, no todo ou em parte, sob a orientação de outro professor ou para outra finalidade (outro trabalho acadêmico, outro grupo de pesquisa, outra publicação ou fins comerciais), sem autorização prévia e por escrito do Prof. Atilio.

11. AUTORIA INTELECTUAL DOS PROJETOS, USO INDEVIDO E PENALIDADES. Reconheço que a concepção, a delimitação dos temas, as perguntas de pesquisa, as estratégias de busca e os protocolos deste projeto são de autoria intelectual do Prof. Dr. Atilio Sersun Calefi, foram elaborados e documentados por ele antes da minha participação e me são revelados apenas em razão deste termo. Se eu me apropriar, repassar, divulgar ou usar o projeto, o tema, o protocolo, os dados ou os resultados para outro fim, sem autorização por escrito, estou ciente de que, além do desligamento imediato e da perda de qualquer autoria, ficarei sujeito(a) às penalidades previstas na legislação aplicável, entre elas: (a) a reparação dos danos materiais e morais causados (arts. 186 e 927 do Código Civil); (b) as sanções civis e penais por violação de direitos autorais sobre o protocolo, os textos, as planilhas, as figuras e os scripts do projeto (Lei nº 9.610/1998 e art. 184 do Código Penal); (c) quando cabíveis, as sanções por uso ou divulgação não autorizados de informações confidenciais (art. 195 da Lei nº 9.279/1996). O fato também poderá ser comunicado à coordenação do meu curso e à minha instituição de ensino, e aos editores de periódicos e organizadores de eventos, conforme as diretrizes do COPE (Committee on Publication Ethics), com pedido de recusa, retirada ou retratação do trabalho.

12. PUBLICAÇÃO E PARTICIPAÇÃO NA ESCRITA. O artigo resultante será submetido a periódico científico internacional, em inglês, escolhido pelo Prof. Atilio. A leitura do manuscrito e a participação na escrita e na revisão do texto, inclusive nas respostas aos pareceristas, são obrigatórias. Quem não ler, não participar da escrita ou não aprovar a versão final não poderá constar como autor, conforme os critérios do ICMJE.

13. PRAZOS E COMUNICAÇÃO. Vou cumprir os prazos e as metas combinados para cada etapa, responder aos contatos do Prof. Atilio em até 2 (dois) dias úteis e avisá-lo, com antecedência, de qualquer impedimento, se não puder continuar ou se tiver qualquer conflito de interesses com o tema. Sei que o trabalho é feito em dupla e que o meu atraso impede o andamento da etapa do outro participante e do projeto.

14. DESLIGAMENTO. Estou ciente e concordo que posso ser desligado(a) do projeto, a critério do Prof. Atilio, nos seguintes casos: (a) atraso na entrega de etapas ou descumprimento de prazos e metas sem justificativa aceita; (b) atrasos repetidos, mesmo que justificados, que comprometam o cronograma; (c) falta de resposta aos contatos do Prof. Atilio no prazo da cláusula 13; (d) não atendimento ao objetivo da pesquisa apresentada ou às tarefas que me forem atribuídas, inclusive a leitura e a participação na escrita do artigo (cláusula 12); (e) violação de qualquer cláusula deste termo. O desligamento será comunicado a mim por e-mail, com o motivo. Com o desligamento: o meu acesso ao projeto é encerrado; as etapas que eu não concluí podem ser redistribuídas a outro participante; não terei direito à autoria por etapas que não concluí, e a minha eventual contribuição será avaliada pelos critérios da cláusula 8; e as obrigações de confidencialidade e de não reprodução continuam valendo. As contribuições que eu já tiver feito poderão continuar sendo usadas no projeto, com o devido reconhecimento. O mesmo vale se eu desistir do projeto.

15. PROTEÇÃO DE DADOS PESSOAIS (LGPD). O tratamento dos meus dados pessoais segue a Lei nº 13.709/2018 (LGPD). Controlador: Prof. Dr. Atilio Sersun Calefi (contato@profatilio.com.br). Dados tratados: nome, e-mail, telefone, formação, respostas da seleção, decisões e registros do projeto, e os dados de comprovação deste aceite (data e hora, endereço IP, navegador e a impressão digital SHA-256 deste texto). Finalidades: seleção, organização do projeto, comunicação comigo, definição e registro de autoria e comprovação do aceite. Bases legais: consentimento (art. 7º, I) e exercício regular de direitos (art. 7º, VI). Não são tratados dados sensíveis, e os dados não são vendidos nem compartilhados com terceiros, exceto com os provedores de hospedagem e de e-mail necessários ao funcionamento da plataforma e, quanto ao nome e à filiação, com as revistas na publicação. Posso, a qualquer momento, pedir confirmação, acesso, correção, anonimização, portabilidade ou eliminação dos meus dados e revogar o consentimento (art. 18), pelo e-mail acima, ressalvados os registros que precisam ser mantidos para comprovar a autoria, este aceite e as obrigações deste termo (art. 16).

16. VIGÊNCIA. Este termo vale desde a assinatura até a publicação do trabalho e, quanto às obrigações de confidencialidade e não reprodução de dados não publicados, por prazo indeterminado.
TXT;
    }
}
