# Integração do Aplicativo Móvel (Flutter) com o Prof-Aid

Este guia orienta a organização do repositório e a arquitetura de comunicação para o desenvolvimento do aplicativo móvel utilizando o framework **Flutter**.

---

## 1. Organização do Repositório (Monorepo)

Sim, a melhor prática é criar uma pasta dedicada para o Flutter na raiz do repositório atual. Isso unifica o ecossistema do **Prof-Aid** em um **Monorepo**, facilitando a gestão do Git, histórico de versões e documentação.

Estrutura recomendada de diretórios:

```text
ProvaGen/                     <-- Raiz do Repositório
├── client/                   <-- Frontend Web (React, Vite, TS)
├── server/                   <-- Backend API (Express, tRPC, Node.js)
├── mobile/                   <-- [NOVA PASTA] Projeto Flutter (Dart)
│   ├── android/              <-- Código nativo Android
│   ├── ios/                  <-- Código nativo iOS
│   ├── lib/                  <-- Código fonte do aplicativo em Dart
│   │   ├── main.dart
│   │   ├── screens/          <-- Telas (Login, Gabaritos, Perfil, etc.)
│   │   ├── services/         <-- Comunicação com a API do servidor
│   │   └── models/           <-- Modelos de dados
│   └── pubspec.yaml          <-- Dependências do Flutter
├── drizzle/                  <-- Migrações e Schemas do Banco de Dados
├── doc/                      <-- Documentação Centralizada
├── package.json
└── VERSION
```

### Como gerar o projeto Flutter:
Navegue até a raiz do seu projeto local no terminal e rode o comando de criação do Flutter apontando para a nova pasta `mobile`:
```bash
flutter create --org com.profaid --project-name mobile mobile
```

---

## 2. Comunicação: Flutter (Dart) com Backend tRPC

O tRPC foi desenhado para projetos TypeScript de ponta a ponta (como o nosso frontend React). No entanto, em termos de transporte de rede, o tRPC funciona sob requisições HTTP tradicionais (GET e POST) trafegando objetos JSON. 

O Flutter pode consumir os roteadores do servidor de forma transparente fazendo chamadas HTTP convencionais utilizando pacotes como **`http`** ou **`dio`**.

### Padrão de URL de requisição do tRPC:
As requisições no tRPC seguem a estrutura:
`https://sua-api.com/api/trpc/<roteador>.<procedimento>`

### Exemplo Prático de Integração em Dart

#### 1. Chamando a rota pública de Orçamentos (`quotes.create`):
Para enviar uma solicitação de orçamento a partir do Flutter:

* **Método**: `POST`
* **URL**: `https://www.prof-aid.com/api/trpc/quotes.create`
* **Headers**: `Content-Type: application/json`
* **Body** (JSON formatado no padrão tRPC):
  ```json
  {
    "name": "Nome do Solicitante",
    "email": "email@empresa.com",
    "phone": "(11) 99999-9999",
    "company": "Escola Exemplo",
    "role": "Coordenador",
    "projectType": "questions",
    "estimatedVolume": "v1",
    "timeframe": "now",
    "description": "Descrição das necessidades do banco de questões...",
    "features": ["lms", "ai"]
  }
  ```

* **Código de Exemplo no Dart**:
```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

Future<bool> sendQuoteRequest({
  required String name,
  required String email,
  required String phone,
  required String company,
  required String role,
  required String projectType,
  required String estimatedVolume,
  required String timeframe,
  required String description,
  List<String>? features,
}) async {
  final url = Uri.parse('https://www.prof-aid.com/api/trpc/quotes.create');
  
  final payload = {
    'name': name,
    'email': email,
    'phone': phone,
    'company': company,
    'role': role,
    'projectType': projectType,
    'estimatedVolume': estimatedVolume,
    'timeframe': timeframe,
    'description': description,
    if (features != null) 'features': features,
  };

  try {
    final response = await http.post(
      url,
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode(payload),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      // tRPC retorna os dados embrulhados na chave 'result'
      return data['result'] != null;
    }
    return false;
  } catch (e) {
    print('Erro ao enviar orçamento: $e');
    return false;
  }
}
```

#### 2. Chamadas Autenticadas (Rotas Protegidas)
Para rotas protegidas (como criar provas ou listar alunos), o Flutter precisará repassar o cookie de sessão ou cabeçalho de autenticação (Bearer Token / Cookie) que o Passport configurou no backend do Prof-Aid.
* Ao realizar o login via HTTP POST em `/api/trpc/auth.login`, armazene o cookie de sessão (`profaid_session` ou similar) localmente no dispositivo usando pacotes como `flutter_secure_storage`.
* Nas requisições subsequentes, injete esse cookie no cabeçalho `Cookie` das chamadas de API.
