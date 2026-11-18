<?php

namespace Database\Seeders;

use App\Models\PressMention;
use Illuminate\Database\Seeder;

/**
 * Popula as matérias de jornal/coluna em que o Prof. Atilio já apareceu como
 * fonte especializada (principalmente a coluna "É o Bicho", do Metrópoles).
 * Título, autor e data foram conferidos na matéria original, não inventados.
 *
 * Idempotente: usa updateOrCreate por URL, pode ser rodado de novo com segurança.
 */
class PressMentionSeeder extends Seeder
{
    public function run(): void
    {
        $mentions = [
            [
                'title' => 'Ração não é tudo igual: saiba escolher a ideal para seu pet',
                'url' => 'https://www.metropoles.com/colunas/e-o-bicho/racao-nao-e-tudo-igual-saiba-escolher-a-ideal-para-seu-pet',
                'outlet' => 'Metrópoles',
                'section' => 'É o Bicho',
                'author' => 'Daniel Lima',
                'published_at' => '2026-08-13',
                'excerpt' => 'Como avaliar os três principais ingredientes do rótulo e escolher a ração certa para cada fase da vida do animal.',
            ],
            [
                'title' => 'Gato "gigante"? Aposte em prateleiras e nichos para o bem-estar do pet',
                'url' => 'https://www.metropoles.com/colunas/e-o-bicho/gato-gigante-aposte-em-prateleiras-e-nichos-para-o-bem-estar-do-pet',
                'outlet' => 'Metrópoles',
                'section' => 'É o Bicho',
                'author' => 'Daniel Lima',
                'published_at' => '2026-07-05',
                'excerpt' => 'Origem e temperamento dócil do Maine Coon, e como o enriquecimento vertical ajuda no bem-estar em apartamentos.',
            ],
            [
                'title' => 'Criar joaninhas e borboletas? Expert revela benefícios da prática',
                'url' => 'https://www.metropoles.com/colunas/e-o-bicho/criar-joaninhas-e-borboletas-expert-revela-beneficios-da-pratica',
                'outlet' => 'Metrópoles',
                'section' => 'É o Bicho',
                'author' => 'Julia de Mesquita',
                'published_at' => '2026-06-05',
                'excerpt' => 'Como joaninhas e borboletas ajudam a criar jardins sustentáveis e livres de agrotóxicos, com atenção aos riscos de espécies exóticas.',
            ],
            [
                'title' => 'Criação de animais não convencionais exige preparo e responsabilidade',
                'url' => 'https://www.metropoles.com/colunas/e-o-bicho/criacao-de-animais-nao-convencionais-exige-preparo-e-responsabilidade',
                'outlet' => 'Metrópoles',
                'section' => 'É o Bicho',
                'author' => 'Julia de Mesquita',
                'published_at' => '2026-05-12',
                'excerpt' => 'Animais não convencionais exigem conhecimento específico e documentação legal — não passaram pelo processo de domesticação de cães e gatos.',
            ],
            [
                'title' => 'Vacinação em dia: por que o começo do ano é decisivo para cães e gatos',
                'url' => 'https://www.metropoles.com/colunas/e-o-bicho/vacinacao-em-dia-por-que-o-comeco-do-ano-e-decisivo-para-caes-e-gatos',
                'outlet' => 'Metrópoles',
                'section' => 'É o Bicho',
                'author' => 'Camila Santos',
                'published_at' => '2026-02-16',
                'excerpt' => 'Por que o início do ano é o momento ideal para atualizar o calendário vacinal de cães e gatos.',
            ],
            [
                'title' => 'Cães com medo de trovões: entenda o comportamento e o que não fazer',
                'url' => 'https://www.metropoles.com/colunas/e-o-bicho/caes-com-medo-de-trovoes-entenda-o-comportamento-e-o-que-nao-fazer',
                'outlet' => 'Metrópoles',
                'section' => 'É o Bicho',
                'author' => 'Julia de Mesquita',
                'published_at' => '2026-02-01',
                'excerpt' => 'Por que cães desenvolvem medo de trovões e quais atitudes dos tutores pioram o quadro.',
            ],
            [
                'title' => 'Adotei um cachorro de rua, e agora? Veja cuidados e primeiros passos',
                'url' => 'https://www.metropoles.com/colunas/e-o-bicho/adotei-um-cachorro-de-rua-e-agora-veja-cuidados-e-primeiros-passos',
                'outlet' => 'Metrópoles',
                'section' => 'É o Bicho',
                'author' => 'Beatriz Mascarenhas',
                'published_at' => '2025-09-01',
                'excerpt' => 'Exames veterinários, transição alimentar gradual e protocolo de vacinação para quem acabou de adotar um cão de rua.',
            ],
            [
                'title' => 'Os gatos destas 3 raças são os mais calmos, segundo um veterinário',
                'url' => 'https://www.metropoles.com/colunas/e-o-bicho/os-gatos-destas-3-racas-sao-os-mais-calmos-segundo-um-veterinario',
                'outlet' => 'Metrópoles',
                'section' => 'É o Bicho',
                'author' => 'Beatriz Mascarenhas',
                'published_at' => '2025-08-22',
                'excerpt' => 'British Shorthair, Persa e Maine Coon estão entre as raças de temperamento mais calmo — mas o cuidado do tutor também molda o comportamento.',
            ],
            [
                'title' => 'Saiba como descobrir se tem escorpião-amarelo em casa, segundo biólogo',
                'url' => 'https://www.metropoles.com/vida-e-estilo/saiba-como-descobrir-se-tem-escorpiao-amarelo-em-casa-segundo-biologo',
                'outlet' => 'Metrópoles',
                'section' => 'Vida e Estilo',
                'author' => 'Roberta Pinheiro, Beatriz Mascarenhas',
                'published_at' => '2025-08-17',
                'excerpt' => 'Sinais de infestação por escorpião-amarelo em casa e recomendações de prevenção.',
            ],
            [
                'title' => 'Especialista ensina como identificar rastros de escorpiões amarelos',
                'url' => 'https://www.metropoles.com/vida-e-estilo/especialista-ensina-como-identificar-rastros-de-escorpioes-amarelos',
                'outlet' => 'Metrópoles',
                'section' => 'Vida e Estilo',
                'author' => 'Beatriz Mascarenhas',
                'published_at' => '2025-07-16',
                'excerpt' => 'Como identificar sinais da presença de escorpiões amarelos em casa e por que não tentar removê-los sozinho.',
            ],
        ];

        foreach ($mentions as $mention) {
            PressMention::updateOrCreate(['url' => $mention['url']], $mention);
        }
    }
}
