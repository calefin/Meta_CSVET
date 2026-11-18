import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/app_models.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';
import '../services/translation_service.dart';

class AccountTab extends StatefulWidget {
  const AccountTab({Key? key}) : super(key: key);

  @override
  State<AccountTab> createState() => _AccountTabState();
}

class _AccountTabState extends State<AccountTab> {
  final AppState _appState = AppState();

  @override
  void initState() {
    super.initState();
    _appState.addListener(_update);
  }

  @override
  void dispose() {
    _appState.removeListener(_update);
    super.dispose();
  }

  void _update() {
    if (mounted) setState(() {});
  }

  Future<void> _handleSync() async {
    try {
      await _appState.sync();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Dados sincronizados com a nuvem com sucesso!'),
            backgroundColor: AppTheme.success,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Falha ao sincronizar: $e'),
            backgroundColor: AppTheme.error,
          ),
        );
      }
    }
  }

  Future<void> _handleValidateSubscription() async {
    try {
      await _appState.validateUserSubscription();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Status verificado online: ${_appState.subscriptionStatus}'),
            backgroundColor: AppTheme.success,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Falha ao validar assinatura online: ${e.toString().replaceAll('Exception:', '')}'),
            backgroundColor: AppTheme.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final syncTimeStr = _appState.lastSyncTime != null
        ? DateFormat('dd/MM/yyyy HH:mm:ss').format(_appState.lastSyncTime!)
        : 'Nunca';

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16.0),
      child: Column(
        children: [
          // Email & Status Card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppTheme.cardBg,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withOpacity(0.05)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 30,
                  backgroundColor: AppTheme.primary.withOpacity(0.15),
                  child: const Icon(Icons.person, color: AppTheme.secondary, size: 36),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Professor Logado',
                        style: TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _appState.userEmail,
                        style: const TextStyle(color: AppTheme.textPrimary, fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Sync status card
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.cardBg,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.white.withOpacity(0.05)),
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Última Sincronização:', style: TextStyle(color: AppTheme.textSecondary)),
                    Text(syncTimeStr, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Dados pendentes de envio:', style: TextStyle(color: AppTheme.textSecondary)),
                    _appState.hasUnsynced
                        ? Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: AppTheme.warning.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text('Sim (Offline)', style: TextStyle(color: AppTheme.warning, fontWeight: FontWeight.bold, fontSize: 12)),
                          )
                        : Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: AppTheme.success.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text('Não (Sincronizado)', style: TextStyle(color: AppTheme.success, fontWeight: FontWeight.bold, fontSize: 12)),
                          ),
                  ],
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: _appState.isSyncing ? null : _handleSync,
                    icon: _appState.isSyncing
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                          )
                        : const Icon(Icons.sync, color: Colors.white),
                    label: Text(
                      _appState.isSyncing ? 'Sincronizando...' : 'Sincronizar Agora',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Subscription plan (Aesthetics: golden gradient card)
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFB45309), Color(0xFFF59E0B), Color(0xFFD97706)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: Colors.amber.withOpacity(0.2),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'ASSINATURA PROF-AID',
                      style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold, letterSpacing: 1),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(6)),
                      child: const Text('PRO', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 10)),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  _appState.subscriptionStatus,
                  style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Cobranças, notas e faturas integradas via Google Play e App Store.',
                  style: TextStyle(color: Colors.white70, fontSize: 12),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 38,
                        child: ElevatedButton(
                          onPressed: () {
                            // Trigger Google Pay in-app purchase dialog simulation
                            _showInAppBillingDialog(context);
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          child: const Text(
                            'Gerenciar',
                            style: TextStyle(color: Colors.black87, fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: SizedBox(
                        height: 38,
                        child: ElevatedButton(
                          onPressed: _handleValidateSubscription,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.white.withOpacity(0.2),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          child: const Text(
                            'Validar Online',
                            style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Settings & Tools
          Container(
            decoration: BoxDecoration(
              color: AppTheme.cardBg,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.white.withOpacity(0.05)),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: Material(
                color: Colors.transparent,
                child: Column(
                  children: [
                    ListTile(
                      leading: const Icon(Icons.settings, color: AppTheme.primary),
                      title: const Text('Configurações do Corretor'),
                      subtitle: const Text('Ajuste de contraste, vibração, focus e filtros'),
                      trailing: const Icon(Icons.chevron_right, color: AppTheme.textSecondary),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(builder: (context) => const AppSettingsScreen()),
                        );
                      },
                    ),
                    Divider(height: 1, color: Colors.white.withOpacity(0.05)),
                    ListTile(
                      leading: const Icon(Icons.bug_report_outlined, color: AppTheme.warning),
                      title: const Text('Resetar Dados de Demonstração'),
                      subtitle: const Text('Apaga todas as modificações e recarrega dados iniciais'),
                      onTap: () => _showResetDialog(context),
                    ),
                    Divider(height: 1, color: Colors.white.withOpacity(0.05)),
                    ListTile(
                      leading: const Icon(Icons.logout, color: AppTheme.error),
                      title: const Text('Sair do Aplicativo'),
                      onTap: () async {
                        await _appState.logout();
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Sessão encerrada com sucesso.')),
                          );
                        }
                      },
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 32),
          
          // App Version details
          const Center(
            child: Column(
              children: [
                Text(
                  'ProvaGen Corrector',
                  style: TextStyle(color: AppTheme.textSecondary, fontWeight: FontWeight.bold),
                ),
                SizedBox(height: 4),
                Text(
                  'Versão 1.2.4 (OpenCV Engine v4.7)',
                  style: TextStyle(color: Colors.white30, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  void _showInAppBillingDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppTheme.surface,
        title: const Row(
          children: [
            Icon(Icons.payment, color: Colors.amber),
            SizedBox(width: 10),
            Text('Faturamento do App'),
          ],
        ),
        content: const Text(
          'Os pagamentos são processados com segurança pelo gateway de faturamento do Google Play. Sua assinatura é cobrada mensalmente e renovada automaticamente.\n\nPara cancelar ou gerenciar, acesse a seção de inscrições na loja de aplicativos.',
          style: TextStyle(color: AppTheme.textPrimary),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Ok', style: TextStyle(color: AppTheme.primary)),
          ),
        ],
      ),
    );
  }

  void _showResetDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppTheme.surface,
        title: const Text('Confirmar Reset'),
        content: const Text('Isso apagará todas as provas e correções recentes para voltar aos dados padrão de demonstração. Deseja prosseguir?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Não', style: TextStyle(color: AppTheme.textSecondary)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.error),
            onPressed: () async {
              Navigator.pop(context);
              await _appState.resetAll();
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Dados reiniciados com sucesso!'), backgroundColor: AppTheme.success),
                );
              }
            },
            child: const Text('Sim, Resetar', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }
}

// Custom App Settings Screen
class AppSettingsScreen extends StatefulWidget {
  const AppSettingsScreen({Key? key}) : super(key: key);

  @override
  State<AppSettingsScreen> createState() => _AppSettingsScreenState();
}

class _AppSettingsScreenState extends State<AppSettingsScreen> {
  final AppState _appState = AppState();
  late AppSettings _tempSettings;

  @override
  void initState() {
    super.initState();
    // Copy configurations
    _tempSettings = AppSettings.fromJson(_appState.settings.toJson());
  }

  void _saveSettings() {
    _appState.updateSettings(_tempSettings);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Configurações salvas com sucesso!'),
        backgroundColor: AppTheme.success,
      ),
    );
    Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Configurações do Corretor'),
        actions: [
          IconButton(
            icon: const Icon(Icons.check, color: AppTheme.success),
            onPressed: _saveSettings,
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildSectionHeader('Comportamento da Câmera & Leitura'),
          _buildSwitchTile(
            title: 'Mostrar revisão na câmera',
            subtitle: 'Mostra a máscara e os resultados sobrepostos imediatamente após bater a foto',
            value: _tempSettings.showPaperReviewOnCamera,
            onChanged: (val) => setState(() => _tempSettings.showPaperReviewOnCamera = val),
          ),
          _buildSwitchTile(
            title: 'Uma avaliação por aluno',
            subtitle: 'Permite apenas um gabarito corrigido registrado por aluno no mesmo quiz',
            value: _tempSettings.oneExamPerStudent,
            onChanged: (val) => setState(() => _tempSettings.oneExamPerStudent = val),
          ),
          _buildSwitchTile(
            title: 'Confirmar sobreposição de dados',
            subtitle: 'Alerta o usuário caso detecte que o aluno já foi corrigido antes',
            value: _tempSettings.warnDuplicateStudent,
            onChanged: (val) => setState(() => _tempSettings.warnDuplicateStudent = val),
          ),
          
          _buildSectionHeader('Exibição de Resultados'),
          _buildSwitchTile(
            title: 'Ocultar resultado do resumo',
            subtitle: 'Não mostra o cabeçalho preto com resumo sobreposto no topo da foto',
            value: _tempSettings.hideSummaryResult,
            onChanged: (val) => setState(() => _tempSettings.hideSummaryResult = val),
          ),
          _buildSwitchTile(
            title: 'Mostrar quantidade de erros e acertos',
            subtitle: 'Exibe contagem numérica de questões erradas e certas junto à nota',
            value: _tempSettings.showCorrectWrongCount,
            onChanged: (val) => setState(() => _tempSettings.showCorrectWrongCount = val),
          ),

          _buildSectionHeader('Hardware & Feedback'),
          _buildSwitchTile(
            title: 'Som do obturador (Shutter)',
            subtitle: 'Toca som de câmera ao digitalizar gabarito',
            value: _tempSettings.shutterSound,
            onChanged: (val) => setState(() => _tempSettings.shutterSound = val),
          ),
          _buildSwitchTile(
            title: 'Vibrar ao escanear',
            subtitle: 'Vibração rápida quando a leitura do gabarito é confirmada',
            value: _tempSettings.vibrateOnScan,
            onChanged: (val) => setState(() => _tempSettings.vibrateOnScan = val),
          ),
          _buildSwitchTile(
            title: 'Detector de luz forte',
            subtitle: 'Avisa se o ambiente estiver excessivamente claro gerando reflexo',
            value: _tempSettings.brightLightDetector,
            onChanged: (val) => setState(() => _tempSettings.brightLightDetector = val),
          ),

          _buildSectionHeader('Ajustes OpenCV Avançados'),
          _buildSwitchTile(
            title: 'Melhorar contraste de impressão',
            subtitle: 'Aplica equalização de histograma OpenCV para gabaritos impressos fracos',
            value: _tempSettings.adjustContrast,
            onChanged: (val) => setState(() => _tempSettings.adjustContrast = val),
          ),
          
          Card(
            margin: const EdgeInsets.symmetric(vertical: 6),
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'IP / URL do Backend Python (OMR)',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Digite a URL do seu servidor Python (ex: http://192.168.x.x:5000 se testando em dispositivo real na mesma rede Wi-Fi).',
                    style: TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    initialValue: _tempSettings.backendUrl,
                    decoration: const InputDecoration(
                      hintText: 'Ex: http://192.168.1.15:5000',
                      prefixIcon: Icon(Icons.link, color: AppTheme.textSecondary),
                    ),
                    style: const TextStyle(color: Colors.white, fontSize: 14),
                    onChanged: (val) {
                      _tempSettings.backendUrl = val.trim();
                    },
                  ),
                ],
              ),
            ),
          ),
          
          // Focus requirement slider (1-10)
          Card(
            margin: const EdgeInsets.symmetric(vertical: 6),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Requerimento de Foco Câmera',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                      Text(
                        '${_tempSettings.focusRequirement}',
                        style: const TextStyle(color: AppTheme.secondary, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                  const Text(
                    'Define a rigidez com que a imagem deve estar focada para leitura (1: baixo, 10: alto)',
                    style: TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                  ),
                  Slider(
                    value: _tempSettings.focusRequirement.toDouble(),
                    min: 1,
                    max: 10,
                    divisions: 9,
                    activeColor: AppTheme.primary,
                    inactiveColor: Colors.white12,
                    onChanged: (val) {
                      setState(() {
                        _tempSettings.focusRequirement = val.toInt();
                      });
                    },
                  ),
                ],
              ),
            ),
          ),

          // Sheet strictness (Choices)
          _buildChoiceCard(
            title: 'Sheet Strictness (Rigor da folha)',
            subtitle: 'Define o rigor na detecção de marcas e distorção geométrica',
            value: _tempSettings.sheetStrictness,
            choices: ['Most Strict', 'Least Strict'],
            onChanged: (val) => setState(() => _tempSettings.sheetStrictness = val),
          ),

          // Resolution Selection
          _buildChoiceCard(
            title: 'Resolução da Câmera',
            subtitle: 'Método de seleção da resolução de captura no sensor Android',
            value: _tempSettings.resolutionSelection,
            choices: ['Force Highest', 'Dynamic'],
            onChanged: (val) => setState(() => _tempSettings.resolutionSelection = val),
          ),

          _buildSectionHeader('Relatórios & Crash Reports'),
          _buildSwitchTile(
            title: 'Compartilhar logs de erros',
            subtitle: 'Envia relatórios de crash anônimos para o prof-aid.com',
            value: _tempSettings.crashReports,
            onChanged: (val) => setState(() => _tempSettings.crashReports = val),
          ),
          _buildSwitchTile(
            title: 'Exibir imagens de debug',
            subtitle: 'Mostra no log e na galeria as etapas intermediárias do OpenCV (grayscaled, thresholded)',
            value: _tempSettings.showDebugImages,
            onChanged: (val) => setState(() => _tempSettings.showDebugImages = val),
          ),
          
          _buildSectionHeader(AppTranslations.translate('app_language')),
          _buildLanguageChoiceCard(
            title: AppTranslations.translate('app_language'),
            subtitle: AppTranslations.translate('language_desc'),
            value: _tempSettings.language,
            onChanged: (val) => setState(() => _tempSettings.language = val),
          ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildLanguageChoiceCard({
    required String title,
    required String subtitle,
    required String value,
    required ValueChanged<String> onChanged,
  }) {
    final Map<String, String> langMap = {
      'pt': 'Português',
      'en': 'English',
      'es': 'Español',
    };
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
            const SizedBox(height: 4),
            Text(subtitle, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
            const SizedBox(height: 12),
            Row(
              children: langMap.entries.map((entry) {
                final langCode = entry.key;
                final langName = entry.value;
                final isSelected = langCode == value;
                return Expanded(
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    child: ChoiceChip(
                      label: Container(
                        alignment: Alignment.center,
                        child: Text(
                          langName,
                          style: TextStyle(
                            color: isSelected ? Colors.white : AppTheme.textSecondary,
                            fontWeight: FontWeight.bold,
                            fontSize: 11,
                          ),
                        ),
                      ),
                      selected: isSelected,
                      selectedColor: AppTheme.primary,
                      backgroundColor: AppTheme.surface,
                      onSelected: (selected) {
                        if (selected) {
                          onChanged(langCode);
                        }
                      },
                    ),
                  ),
                );
              }).toList(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(top: 20, bottom: 8, left: 4),
      child: Text(
        title.toUpperCase(),
        style: const TextStyle(
          color: AppTheme.secondary,
          fontSize: 12,
          fontWeight: FontWeight.bold,
          letterSpacing: 0.8,
        ),
      ),
    );
  }

  Widget _buildSwitchTile({
    required String title,
    required String subtitle,
    required bool value,
    required ValueChanged<bool> onChanged,
  }) {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 4),
      child: SwitchListTile(
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        subtitle: Text(subtitle, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
        value: value,
        onChanged: onChanged,
        activeColor: AppTheme.primary,
      ),
    );
  }

  Widget _buildChoiceCard({
    required String title,
    required String subtitle,
    required String value,
    required List<String> choices,
    required ValueChanged<String> onChanged,
  }) {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
            const SizedBox(height: 4),
            Text(subtitle, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
            const SizedBox(height: 12),
            Row(
              children: choices.map((choice) {
                final isSelected = choice == value;
                return Expanded(
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    child: ChoiceChip(
                      label: Container(
                        alignment: Alignment.center,
                        child: Text(
                          choice,
                          style: TextStyle(
                            color: isSelected ? Colors.white : AppTheme.textSecondary,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      selected: isSelected,
                      selectedColor: AppTheme.primary,
                      backgroundColor: AppTheme.surface,
                      onSelected: (selected) {
                        if (selected) {
                          onChanged(choice);
                        }
                      },
                    ),
                  ),
                );
              }).toList(),
            ),
          ],
        ),
      ),
    );
  }
}
