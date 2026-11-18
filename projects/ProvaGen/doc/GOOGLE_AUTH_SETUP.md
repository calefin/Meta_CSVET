# Sistema de Autenticação Google OAuth2 - Passport

## Visão Geral

Este projeto implementa autenticação Google OAuth2 usando Passport.js no Node.js/Express, integrado com tRPC e banco de dados MySQL.

## Configuração

### 1. Variáveis de Ambiente

Adicione as seguintes variáveis ao seu arquivo `.env`:

```env
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_CALLBACK_URL=http://localhost:3000/api/auth/google/callback

# Session Secret
JWT_SECRET=your-session-secret-key
```

### 2. Instalação de Dependências

```bash
npm install passport passport-google-oauth20 express-session
# ou
pnpm add passport passport-google-oauth20 express-session
```

### 3. Estrutura dos Arquivos

- **`server/_core/passport.ts`** - Configuração do Passport com estratégia Google
- **`server/_core/googleAuth.ts`** - Rotas de autenticação Google
- **`server/_core/index.ts`** - Integração do Passport com Express
- **`server/_core/env.ts`** - Variáveis de ambiente
- **`client/src/_core/hooks/useGoogleAuth.ts`** - Hook para frontend

## Fluxo de Autenticação

### Backend

1. **Rota Inicial**: `/api/auth/google`
   - Inicia o fluxo OAuth do Google
   - Redireciona para Google para autorização

2. **Callback**: `/api/auth/google/callback`
   - Google redireciona aqui após autorização
   - Passport verifica o token
   - User é criado/atualizado no banco de dados
   - Session token é criado e armazenado em cookie

3. **Logout**: `/api/auth/logout` (POST)
   - Limpa o cookie de sessão
   - Faz logout do usuário

4. **Get User**: `/api/auth/me` (GET)
   - Retorna dados do usuário autenticado

### Database

Os usuários Google são armazenados com:
- `openId`: `google:{googleId}` (identificador único)
- `email`: Email do Google
- `name`: Nome completo
- `loginMethod`: `"google"`
- `lastSignedIn`: Timestamp do último login

## Procedimentos tRPC

### `auth.getGoogleLoginUrl`
Retorna a URL para iniciar o login com Google:
```typescript
const response = await trpc.auth.getGoogleLoginUrl.query();
// { url: "/api/auth/google" }
```

### `auth.verifyGoogleSession`
Verifica se existe sessão válida:
```typescript
const response = await trpc.auth.verifyGoogleSession.query();
// { success: true, user: {...} } ou { success: false, user: null }
```

### `auth.logout`
Faz logout da sessão:
```typescript
await trpc.auth.logout.mutate();
```

## Uso no Frontend

```typescript
import { useGoogleAuth } from "@/_core/hooks/useGoogleAuth";

function LoginPage() {
  const { initiateGoogleLogin, logout, checkSession } = useGoogleAuth();

  return (
    <div>
      <button onClick={initiateGoogleLogin}>
        Entrar com Google
      </button>
      <button onClick={logout}>
        Sair
      </button>
    </div>
  );
}
```

## Fluxo Completo

1. Usuário clica em "Entrar com Google"
2. Frontend chama `initiateGoogleLogin()` que redireciona para `/api/auth/google`
3. Express redireciona para Google OAuth
4. Usuário autoriza na interface do Google
5. Google redireciona para `/api/auth/google/callback`
6. Passport valida o token e busca informações do usuário
7. Usuário é criado/atualizado no banco de dados
8. Session token é criado e armazenado em cookie seguro
9. Usuário é redirecionado para home `/`
10. Frontend detecta que existe sessão válida

## Segurança

- Cookies são `HttpOnly` e `Secure` em produção
- Session secret é armazenado em variável de ambiente
- Tokens expiram em 1 ano (configurável)
- CORS está habilitado com credentials

## Configuração do Google Cloud Console

1. Acesse [Google Cloud Console](https://console.cloud.google.com/)
2. Crie um novo projeto
3. Ative a API "Google+ API"
4. Crie credenciais OAuth 2.0:
   - Tipo: Aplicação Web
   - URIs autorizados: `http://localhost:3000`
   - URIs de redirecionamento: `http://localhost:3000/api/auth/google/callback`
5. Copie Client ID e Client Secret para seu `.env`

## Integração com Métodos de Login Existentes

O sistema funciona lado a lado com:
- Login local com email/senha (`auth.login`, `auth.signup`)
- Ambos os métodos criam user no mesmo banco de dados
- `openId` diferencia entre `local:email` e `google:googleId`
- Usuário pode ter múltiplos métodos de login usando o mesmo email

## Troubleshooting

### "Google OAuth Callback failed"
- Verificar se `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` estão corretos
- Confirmar que `GOOGLE_CALLBACK_URL` está registrado no Google Console

### "Invalid session"
- Verificar se `JWT_SECRET` está configurado
- Verificar se cookie está sendo enviado nas requisições (CORS credentials)

### Usuário não é redirecionado após callback
- Verificar se `registerGoogleAuthRoutes()` foi adicionado em `index.ts`
- Verificar se Express sessions estão configuradas
