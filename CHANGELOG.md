# Changelog — Odù Journey

## [1.0.3+9] — 2026-06-01
### Adicionado
- Integração com API REST (odujourney.com) — captura de e-mail no onboarding
- Sincronização silenciosa de XP/nível/premium em background
- Notificação local diária (flutter_local_notifications) — lembrete para treinar
- Toggle de notificações nas Configurações
- Suporte a Android 15 edge-to-edge

### Corrigido
- `odu_search.dart` continha código errado (OduListScreen duplicado)
- `odu_factory.dart` usava construtor antigo do modelo Odu
- `difficulty_manager.dart` chamava métodos inexistentes
- `training_type_selection_screen.dart` faltava `isQuizMode`
- `training_options_screen_updated.dart` duplicado removido

### Infraestrutura
- pubspec.yaml: entradas duplicadas e indentação incorreta corrigidas
- AndroidManifest.xml: permissões de notificação adicionadas
- CLAUDE.md e CHANGELOG.md criados

---

## [1.0.3+8] — 2026-05-xx
### Adicionado
- Versão para teste aberto na Play Store

---

## [1.0.2] — 2026-04-xx
### Adicionado
- Onboarding completo: seletor de idioma, nome de exibição, termos, aviso educacional
- Guia de estudo

---

## [1.0.1] — 2026-03-xx
### Adicionado
- Suporte a PT / EN / ES
- RevenueCat: mensal, anual e vitalício

---

## [1.0.0] — 2026-02-xx
### Lançamento inicial
- Biblioteca dos 16 Mejis (gratuito) e 256 Odù (premium)
- Treinos: reconhecimento, hierarquia, Ire/Ibi
- Odù do dia
- Sistema de XP e níveis
