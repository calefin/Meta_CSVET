# Guia de Configuração do RevenueCat - ODU JOURNEY

Este documento fornece instruções passo a passo para configurar o RevenueCat no seu app Flutter.

## 📋 Pré-requisitos

- Conta no [RevenueCat](https://www.revenuecat.com/)
- Conta de desenvolvedor no [Google Play Console](https://play.google.com/console)
- API Key do RevenueCat: `test_tcNXjteLCsvIhwwgogCMmrysBjq`

## 🔧 Configuração do Projeto

### 1. Dependências Instaladas

As seguintes dependências foram adicionadas ao `pubspec.yaml`:

```yaml
dependencies:
  purchases_flutter: ^7.0.0
  purchases_ui_flutter: ^7.0.0
```

### 2. Inicialização no main.dart

O RevenueCat é inicializado automaticamente no `main()`:

```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  try {
    await RevenueCatService().initialize();
  } catch (e) {
    print('Erro ao inicializar RevenueCat: $e');
  }
  
  runApp(const OduJourneyApp());
}
```

## 🎯 Configuração do Android

### Passo 1: Configurar Google Play Billing

1. Acesse [Google Play Console](https://play.google.com/console)
2. Selecione seu app
3. Vá para **Monetização** → **Produtos** → **Assinaturas**
4. Crie os seguintes produtos:

| ID do Produto | Nome | Preço | Período |
|---|---|---|---|
| `monthly` | Odù Journey Mensal | R$ 9,90 | 1 mês |
| `yearly` | Odù Journey Anual | R$ 99,90 | 1 ano |
| `lifetime` | Odù Journey Vitalício | R$ 199,90 | Sem renovação |

### Passo 2: Configurar Entitlement no RevenueCat

1. Acesse [RevenueCat Dashboard](https://app.revenuecat.com/)
2. Vá para **Project Settings** → **Entitlements**
3. Crie um novo entitlement com o ID: `CSVET Editora, Cursos e Treinamentos Pro`
4. Vincule os produtos criados no Google Play a este entitlement

### Passo 3: Conectar Google Play ao RevenueCat

1. No RevenueCat, vá para **Project Settings** → **API Keys**
2. Adicione sua API Key do Google Play:
   - Vá para Google Play Console
   - **Configurações** → **Chaves de API**
   - Copie a chave JSON
   - Cole no RevenueCat

### Passo 4: Configurar Offerings

1. No RevenueCat, vá para **Offerings**
2. Crie uma offering chamada "default" (padrão)
3. Adicione os pacotes:
   - `monthly` (Mensal)
   - `yearly` (Anual)
   - `lifetime` (Vitalício)

## 📱 Estrutura de Código

### RevenueCatService (`lib/services/revenuecat_service.dart`)

Serviço singleton que gerencia todas as operações do RevenueCat:

```dart
// Verificar se o usuário é premium
bool isPremium = await RevenueCatService().isPremium();

// Obter informações do cliente
CustomerInfo? info = await RevenueCatService().getCustomerInfo();

// Listar pacotes disponíveis
List<Package> packages = await RevenueCatService().getAllPackages();

// Realizar compra
bool success = await RevenueCatService().purchasePackage(package);

// Restaurar compras
bool restored = await RevenueCatService().restorePurchases();

// Abrir Customer Center
await RevenueCatService().showCustomerCenter(context);
```

### RevenueCatPaywall (`lib/ui/widgets/revenuecat_paywall.dart`)

Widget que exibe o paywall com:
- Lista de pacotes disponíveis
- Seleção de plano
- Botão de compra
- Restauração de compras
- Tratamento de erros

### SubscriptionScreen (`lib/ui/screens/subscription/subscription_screen.dart`)

Tela de assinatura que:
- Verifica status premium do usuário
- Exibe paywall se não for premium
- Oferece gerenciamento de assinatura se for premium
- Integra com o Customer Center

## 🔐 Variáveis de Ambiente

A API Key está configurada no `RevenueCatService`:

```dart
static const String _apiKey = 'test_tcNXjteLCsvIhwwgogCMmrysBjq';
static const String _entitlementId = 'CSVET Editora, Cursos e Treinamentos Pro';
```

**⚠️ IMPORTANTE:** Para produção, mude para a chave de produção do RevenueCat.

## 🧪 Testando Localmente

### Modo de Teste

1. Use a API Key de teste fornecida
2. Configure um usuário de teste no Google Play Console
3. Instale o app no dispositivo de teste
4. Acesse a tela de assinatura e teste a compra

### Logs de Debug

O RevenueCat está configurado com `PurchasesLogLevel.debug`. Verifique o console para mensagens como:

```
✅ RevenueCat inicializado com sucesso
🔍 Verificação de Premium: true
🛒 Iniciando compra do pacote: yearly
✅ Compra realizada com sucesso!
```

## 📊 Verificação de Entitlements

O app verifica automaticamente o entitlement em:

1. **Ao iniciar**: `RevenueCatService().initialize()`
2. **Ao abrir a tela de assinatura**: `isPremium()`
3. **Após compra**: Atualização automática de `CustomerInfo`
4. **Ao restaurar compras**: `restorePurchases()`

## 🎨 Customização

### Cores do Paywall

Edite `RevenueCatPaywall` para customizar cores:

```dart
// Gradiente de fundo
gradient: LinearGradient(
  colors: [Color(0xFF1A1A2E), Color(0xFF16213E)],
  begin: Alignment.topCenter,
  end: Alignment.bottomCenter,
),

// Cor do botão
backgroundColor: Colors.amber,

// Cor de destaque
color: Colors.greenAccent,
```

### Textos

Todos os textos podem ser customizados diretamente no código dos widgets.

## 🚀 Deployment para Produção

1. Mude a API Key para a chave de produção
2. Configure os produtos finais no Google Play Console
3. Teste completamente em modo de produção
4. Publique o app na Play Store

## 📚 Recursos Úteis

- [Documentação do RevenueCat para Flutter](https://www.revenuecat.com/docs/getting-started/installation/flutter)
- [RevenueCat Paywalls](https://www.revenuecat.com/docs/tools/paywalls)
- [Customer Center](https://www.revenuecat.com/docs/tools/customer-center)
- [Google Play Billing](https://developer.android.com/google/play/billing)

## 🐛 Troubleshooting

### "Offerings não carregadas"
- Verifique se os produtos estão criados no Google Play Console
- Confirme que o entitlement está vinculado aos produtos no RevenueCat

### "Compra não funciona"
- Verifique se está usando um usuário de teste no Google Play
- Confirme que o dispositivo tem Google Play Services atualizado

### "Premium não é reconhecido"
- Limpe o cache do app
- Chame `restorePurchases()` para sincronizar
- Verifique os logs no RevenueCat Dashboard

## 📞 Suporte

Para dúvidas sobre RevenueCat, acesse:
- [Documentação Oficial](https://www.revenuecat.com/docs)
- [Community Slack](https://community.revenuecat.com)
- [Email Support](support@revenuecat.com)
