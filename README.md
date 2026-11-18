# CSVET Editora: csvet.com.br

Site institucional da **CSVET Editora, Cursos e Treinamentos**. Concentra a autoridade da marca (dados estruturados `Organization`), apresenta os periódicos publicados em [journals.csvet.com.br](https://journals.csvet.com.br) e liga, com links diretos, as plataformas do ecossistema do **Prof. Dr. Atilio Sersun Calefi**.

A identidade visual é a mesma de `csvet.app` e do portal de periódicos: navy `#000080`/`#000067`, cinza `#c6c6c6`, destaque `#00aaff` e fonte Roboto.

## 🚀 Estrutura de Arquivos

*   `index.php`: Página principal que renderiza a interface do portal (hub) e também gerencia a tela de contagem regressiva para redirecionamento.
*   `data.php`: Arquivo de configuração contendo os metadados dos sites (links, cores, descrições, tags) e mapeamento de chaves para redirecionamentos.
*   `data.php`: também guarda `$organization` (dados da editora para o JSON-LD) e `$journals` (periódicos em destaque).
*   `css/style.css`: Folha de estilo com a identidade visual da CSVET Editora.
*   `img/`: logos (azul e branco), fundo do hero, imagem de compartilhamento `og-image.jpg` (1200×630) e ícones (`icon-192/512.png`, `apple-touch-icon.png`). O `favicon.ico` (16/32/48) e os ícones usam só o símbolo do livro, branco sobre navy.
*   `.htaccess`: força `https://csvet.com.br` (sem www), URLs amigáveis (ex: `csvet.com.br/vetrank`) e cache de estáticos.
*   `privacidade.php` e `termos.php`: Política de Privacidade (LGPD) e Termos de Uso, em `/privacidade` e `/termos`. Dados do controlador e do encarregado em `$legal` no `data.php`.
*   `partials/`: cabeçalho, rodapé e `<head>` compartilhados.
*   `fonts/`: Roboto servida localmente (sem Google Fonts, o site não envia dados a terceiros nem usa cookies).
*   `deploy.sh`: publica o site no servidor (`git pull` + cópia dos arquivos públicos).

---

## 🎨 Funcionalidades de Design & UX

1.  **Aparência Premium (Dark Theme)**: Cores balanceadas, sombras suaves e gradientes que garantem uma experiência visual incrível.
2.  **Glassmorphism & Glow Effects**: Cartões translúcidos com bordas brilhantes que reagem ao mouse e adotam a cor característica de cada marca/site.
3.  **Micro-animações**: Ícones interativos, transições suaves e blobs decorativos flutuantes em segundo plano para dar vida à interface.
4.  **Sistema de Redirecionamento com Countdown**: Tela dedicada com contagem regressiva visual e carregador circular caso o usuário acesse por um link direto ou URL amigável.
5.  **Responsividade Total**: Experiência otimizada e testada para smartphones, tablets e desktops.

---

## ⚙️ Como Usar e Configurar

### 1. Adicionando ou Modificando Portais
Para editar links, descrições, tags ou cores de destaque das plataformas, edite o arquivo [data.php](file:///D:/csvet/data.php):

```php
'chave-do-site' => [
    'title' => 'Nome do Site',
    'url' => 'https://exemplo.com.br',
    'badge' => 'Tag/Categoria',
    'color' => '#HEX_COLOR', // Cor de destaque do card/glow
    'description' => 'Descrição detalhada do site...',
    'icon' => 'app|academic|ranking|legal|genetic|exam' // Ícones pré-definidos no index.php
]
```

### 2. Mapeamento de Redirecionamentos (Redirecionamento Rápido)
Você pode configurar apelidos rápidos no vetor `$redirect_map` do arquivo [data.php](file:///D:/csvet/data.php):

```php
$redirect_map = [
    'apelido' => 'chave-do-site' // Mapeia o apelido à chave real descrita acima
];
```

*   **Exemplo**: Acessar `csvet.com.br/genetica` ou `csvet.com.br/plumora` redirecionará o usuário automaticamente para a página do Plumora Gen (`plumoragen.com`).

### 3. Modos de Redirecionamento
*   **Com Contador Visual (Recomendado)**: Apenas chame `csvet.com.br/vetrank` ou `csvet.com.br/index.php?go=vetrank`. O usuário verá a logo da plataforma, uma descrição do que ela faz e um contador de 3 segundos antes de ser levado ao destino.
*   **Imediato (Silencioso)**: Adicione o parâmetro `direct=1` na URL, por exemplo `csvet.com.br/vetrank?direct=1` ou `csvet.com.br/index.php?go=vetrank&direct=1`. O servidor fará um redirecionamento HTTP 302 direto e instantâneo sem exibir interface gráfica.

---

## 💻 Requisitos e Deploy

### Requisitos
*   Apache com `mod_rewrite` (hospedagem compartilhada cPanel)
*   PHP 7.4 ou superior

### Deploy via Git (servidor)
Repositório: `calefin/csvet.com.br` (privado). No servidor ele fica clonado em `~/repositories/csvet.com.br` (fora da pasta pública), com uma *deploy key* só leitura em `~/.ssh/csvet_deploy` configurada por `core.sshCommand` no próprio repositório. O `deploy.sh` copia apenas os arquivos públicos para `~/csvet.com.br`; o que já existe lá e não está no repositório (como `.well-known/`) não é apagado.

A cada atualização:
1.  No computador: `git push`.
2.  No Terminal do cPanel: `bash ~/repositories/csvet.com.br/deploy.sh`

### Teste local
Sem PHP instalado, use o Docker:

```powershell
docker run --rm -p 127.0.0.1:8090:8000 -v "${PWD}:/app" -w /app php:8.3-cli php -S 0.0.0.0:8000
```

Acesse http://localhost:8090 (o `.htaccess` não é aplicado pelo servidor embutido; use `index.php?go=vetrank` para testar redirecionamentos).
