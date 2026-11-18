# Odù Journey — Contexto do Projeto

## O que é
App Flutter educacional sobre os 256 Odù do sistema oracular de Ifá (espiritualidade Yoruba).  
Disponível no Google Play: https://play.google.com/store/apps/details?id=app.csvet.odujourney  
Site: https://odujourney.com

## Stack
- **Flutter** 3.10.4+ — Android principal, iOS/Web/Windows/macOS suportados
- **Sem Firebase** — offline-first com `shared_preferences`
- **RevenueCat** — assinaturas premium (`purchases_flutter ^10`)
- **PHP + MySQL** no HostGator — API REST em `odujourney.com/api/`
- **flutter_local_notifications** — lembrete diário sem Firebase

## Estrutura de pastas relevante
```
lib/
├── config/app_info.dart          # versão atual (CURRENT_VERSION)
├── data/
│   ├── models/odu.dart           # modelo Odu + OduTranslatedContent
│   ├── repository/
│   │   ├── progress_repository.dart  # SharedPreferences: XP, idioma, email, device_id
│   │   ├── odu_repository.dart       # carrega 256 Odù do JSON embutido
│   │   ├── difficulty_manager.dart   # controla acesso Meji vs 256
│   │   └── odu_factory.dart          # gera Odù para treinos
│   └── odu_data.dart             # JSON dos 256 Odù (gerado por gerar_base.py)
├── l10n/manual_translations.dart # traduções PT/EN/ES
├── services/
│   ├── api_service.dart          # POST /api/register.php e /sync.php
│   ├── notification_service.dart # lembrete diário (flutter_local_notifications)
│   ├── revenuecat_service.dart   # isPremium(), purchase, restore
│   └── premium_check_helper.dart # UI gates para premium
├── ui/screens/                   # 16 telas
└── utils/
    ├── odu_search.dart           # função searchOdu(odus, query)
    ├── text_normalizer.dart      # normalize() com diacritics
    └── odu_hierarchy.dart        # ordenação por senioridade
```

## Modelo de dados
```dart
Odu { id, nome, right: List<int>, left: List<int>,
      ptContent, enContent, esContent: OduTranslatedContent,
      versoYoruba }
OduTranslatedContent { descricao, ire, ibi, verso }
```
Odu.getContent(langCode) retorna o conteúdo no idioma certo.

## SharedPreferences — chaves em uso
| Chave | Tipo | Descrição |
|---|---|---|
| `user_xp` | int | XP total |
| `user_language` | String | pt/en/es |
| `user_display_name` | String | nome exibido |
| `user_email` | String | e-mail (capturado no onboarding) |
| `device_id` | String | UUID hex gerado na primeira abertura |
| `is_premium` | bool | cache do status premium |
| `onboarding_complete` | bool | flag de onboarding |
| `favorites` | List\<String\> | nomes dos Odù favoritos |
| `daily_view_date` | String | data da última sessão |
| `daily_view_count` | int | ciclos usados hoje (free: max 3) |
| `notifications_enabled` | bool | lembrete diário ativo? |

## API REST (odujourney.com/api/)
Toda requisição precisa do header `X-Api-Key: <API_SECRET>`.  
**⚠️ API_SECRET** deve ser igual em `api/config.php` e `lib/services/api_service.dart`.

| Endpoint | Método | Body / Params |
|---|---|---|
| `/register.php` | POST | email, display_name, device_id, language |
| `/sync.php` | POST | device_id, xp, level, is_premium |
| `/user.php` | GET | ?device_id= |

## Monetização — RevenueCat
- Entitlement ID: `CSVET Editora, Cursos e Treinamentos Pro`
- Planos: mensal R$9,90 / anual R$99,90 / vitalício R$199,90
- isPremium() → consulta RevenueCat, fallback local se offline

## Idiomas
PT (primário), EN, ES.  
Traduzir sempre os 3 ao adicionar chave em `manual_translations.dart`.

## Gamificação
- XP Base: 100, Expoente: 1.5
- Nível = `(XP/100)^(1/1.5) + 1`
- Free: 3 ciclos de treino por dia

## Convenções importantes
- Novo Odù sempre via `Odu.fromMap()` — nunca construtor direto em produção
- `OduFactory.create()` só para Odù de treino (sem conteúdo real)
- Chamadas de API (`ApiService`) falham silenciosamente — nunca bloqueiam a UI
- Nunca usar `withOpacity()` — usar `.withValues(alpha: x)`

## Comandos úteis
```powershell
# Verificar erros
flutter analyze lib/

# Build release AAB (Play Store)
flutter build appbundle --release

# Bump de versão
.\scripts\bump_version.ps1 -Type patch

# Instalar dependências novas
flutter pub get
```
