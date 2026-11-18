# Uso do ProvaGen

Resumo rápido:
- Frontend: `client/` (Vite + React + TypeScript).
- Backend: `server/` (Node + Express + tRPC). Algumas rotas usam scripts Python para processamento específico.

Como rodar localmente:

1. Backend (Node):

```bash
# instale dependências (Node)
pnpm install
# crie .env a partir de .env.example e ajuste SITE_BASE
cp .env.example .env
# inicie em modo dev
pnpm dev
```

2. Frontend (client):

```bash
cd client
pnpm install
pnpm dev
```

3. Geração de sitemap (opcional):

```bash
node scripts/generate-sitemap.mjs
```

Observações:
- Configure `SITE_BASE` em `.env` para o domínio canônico (`https://www.prof-aid.com`).
- O servidor expõe `/robots.txt` e `/sitemap.xml` dinamicamente.

## Testes manuais e de integração

Alguns testes manuais úteis para validar o sistema:

1. Teste de autenticação Google
	- Abra `http://localhost:3000/login` e clique em "Entrar com Google".
	- Autorize a aplicação e verifique se o usuário é autenticado e um cookie de sessão é criado.

2. Verificar sessão
	- No console: `fetch('/api/auth/me').then(r => r.json()).then(console.log)` deve retornar dados do usuário quando autenticado.

3. Logout
	- `fetch('/api/auth/logout', { method: 'POST' }).then(r => r.json()).then(console.log)` deve retornar `{ success: true }`.

4. Verificar banco de dados
	- SQL: `SELECT * FROM users WHERE loginMethod = 'google';` — deve mostrar usuários Google com `openId = 'google:...'`.

5. tRPC — Get Google Login URL
	- No cliente: `const { data } = await trpc.auth.getGoogleLoginUrl.useQuery();` deve expor a rota `/api/auth/google`.

6. tRPC — Verify Session
	- `trpc.auth.verifyGoogleSession.useQuery()` deve retornar `{ success: true, user: {...} }` quando autenticado.

7. Testes de componentes React
	- Verificar `GoogleLoginButton`, `GoogleLogoutButton`, `GoogleAuthStatus` e `useGoogleAuth` em cenários reais.

8. CORS e credentials
	- Testar: 
	  ```js
	  fetch('http://localhost:3000/api/auth/me', { method: 'GET', credentials: 'include' })
	  ```
	  Deve retornar dados quando o cookie for enviado.

9. Fluxos de criação de questões e uploads de imagens
	- Criar/Editar questões, adicionar imagens e validar pré-visualização e limite de quota.

Estes passos foram extraídos de `doc/TESTING_GUIDE.md` e consolidados aqui.
