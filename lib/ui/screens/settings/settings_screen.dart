import 'package:flutter/material.dart';
import 'package:odu_journey/config/app_info.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/l10n/manual_translations.dart';
import 'package:odu_journey/services/notification_service.dart';
import 'package:odu_journey/ui/screens/disclaimer_screen.dart';
import 'package:odu_journey/utils/app_locale.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final ProgressRepository _repo = ProgressRepository();
  String _displayName = '';
  String _currentLanguage = 'pt';
  bool _notificationsEnabled = true;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  Future<void> _loadProfile() async {
    final name          = await _repo.getUserDisplayName();
    final lang          = await _repo.getUserLanguage() ?? 'pt';
    final notifications = await _repo.getNotificationsEnabled();
    if (mounted) setState(() {
      _displayName           = name;
      _currentLanguage       = lang;
      _notificationsEnabled  = notifications;
    });
  }

  String _t(String key) =>
      ManualTranslations.of(context)?.translate(key) ?? key;

  // ─── LANGUAGE ────────────────────────────────────────────────────────────

  void _showLanguageDialog() {
    String selected = _currentLanguage;
    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setInner) => AlertDialog(
          title: Text(_t('settings_language')),
          content: RadioGroup<String>(
            groupValue: selected,
            onChanged: (v) => setInner(() => selected = v ?? selected),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: const [
                RadioListTile<String>(
                  value: 'pt',
                  title: Text('🇧🇷  Português'),
                  contentPadding: EdgeInsets.zero,
                ),
                RadioListTile<String>(
                  value: 'en',
                  title: Text('🇺🇸  English'),
                  contentPadding: EdgeInsets.zero,
                ),
                RadioListTile<String>(
                  value: 'es',
                  title: Text('🇪🇸  Español'),
                  contentPadding: EdgeInsets.zero,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text(_t('settings_cancel')),
            ),
            TextButton(
              onPressed: () async {
                await _repo.setUserLanguage(selected);
                appLocaleNotifier.value = Locale(selected);
                if (mounted) setState(() => _currentLanguage = selected);
                if (ctx.mounted) Navigator.pop(ctx);
              },
              child: Text(_t('settings_save')),
            ),
          ],
        ),
      ),
    );
  }

  // ─── DISPLAY NAME ────────────────────────────────────────────────────────

  void _showNameDialog() {
    final controller = TextEditingController(text: _displayName);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(_t('settings_name_dialog_title')),
        content: TextField(
          controller: controller,
          autofocus: true,
          decoration: InputDecoration(
            hintText: _t('settings_name_dialog_hint'),
          ),
          textCapitalization: TextCapitalization.words,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(_t('settings_cancel')),
          ),
          TextButton(
            onPressed: () async {
              await _repo.setUserDisplayName(controller.text);
              if (mounted) setState(() => _displayName = controller.text.trim());
              if (ctx.mounted) Navigator.pop(ctx);
            },
            child: Text(_t('settings_save')),
          ),
        ],
      ),
    );
  }

  // ─── MISC ─────────────────────────────────────────────────────────────────

  void _confirmResetPremium() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Remover Premium?'),
        content: const Text(
            'Isso fará você voltar ao plano Gratuito (limite de 3 visualizações).'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancelar')),
          TextButton(
            onPressed: () async {
              await _repo.setPremium(false);
              if (mounted) {
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Modo Premium desativado.')));
              }
            },
            style: TextButton.styleFrom(foregroundColor: Colors.orange),
            child: const Text('Remover'),
          ),
        ],
      ),
    );
  }

  void _confirmClearFavorites() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Limpar Favoritos?'),
        content: const Text(
            'Todos os Odùs marcados com ❤️ serão removidos da sua lista de estudo.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancelar')),
          TextButton(
            onPressed: () async {
              await _repo.clearAllFavorites();
              if (mounted) {
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Lista de favoritos limpa.')));
              }
            },
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Limpar'),
          ),
        ],
      ),
    );
  }

  void _showChangelog() {
    final lang = _currentLanguage;
    final dateKey = 'date_$lang';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.6,
        maxChildSize: 0.92,
        minChildSize: 0.4,
        expand: false,
        builder: (_, controller) => Column(
          children: [
            const SizedBox(height: 12),
            Container(
              width: 40, height: 4,
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  const Icon(Icons.new_releases_outlined, color: Colors.amber),
                  const SizedBox(width: 12),
                  Text(
                    _t('whats_new'),
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView.builder(
                controller: controller,
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                itemCount: appChangelog.length,
                itemBuilder: (_, i) {
                  final entry = appChangelog[i];
                  final date = entry[dateKey] ?? entry['date_pt'] ?? '';
                  final keys = List<String>.from(entry['changes'] as List);
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: Colors.amber,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              'v${entry["version"]}',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                                color: Colors.black,
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Text(date, style: const TextStyle(color: Colors.grey, fontSize: 13)),
                        ],
                      ),
                      const SizedBox(height: 10),
                      ...keys.map((key) => Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('• ', style: TextStyle(color: Colors.amber, fontSize: 14)),
                            Expanded(child: Text(_t(key), style: const TextStyle(fontSize: 14))),
                          ],
                        ),
                      )),
                      const SizedBox(height: 16),
                      if (i < appChangelog.length - 1) const Divider(),
                      const SizedBox(height: 8),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  String get _languageLabel {
    switch (_currentLanguage) {
      case 'en':
        return '🇺🇸  English';
      case 'es':
        return '🇪🇸  Español';
      default:
        return '🇧🇷  Português';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_t('settingsTitle').isNotEmpty ? _t('settingsTitle') : 'Ajustes')),
      body: ListView(
        children: [
          const SizedBox(height: 20),

          // ── PERFIL ────────────────────────────────────────────────────────
          _buildSectionHeader(_t('settings_profile')),

          ListTile(
            leading: const Icon(Icons.person_outline, color: Colors.deepPurple),
            title: Text(_t('settings_name')),
            subtitle: Text(
              _displayName.isNotEmpty ? _displayName : '—',
              style: const TextStyle(color: Colors.grey),
            ),
            trailing: const Icon(Icons.chevron_right, color: Colors.grey),
            onTap: _showNameDialog,
          ),

          ListTile(
            leading: const Icon(Icons.language, color: Colors.deepPurple),
            title: Text(_t('settings_language')),
            subtitle: Text(
              _languageLabel,
              style: const TextStyle(color: Colors.grey),
            ),
            trailing: const Icon(Icons.chevron_right, color: Colors.grey),
            onTap: _showLanguageDialog,
          ),

          const Divider(),

          // ── NOTIFICAÇÕES ─────────────────────────────────────────────────
          _buildSectionHeader('Notificações'),

          SwitchListTile(
            secondary: const Icon(Icons.notifications_outlined, color: Colors.deepPurple),
            title: const Text('Lembrete diário'),
            subtitle: const Text('Receba um aviso para treinar os Odù'),
            value: _notificationsEnabled,
            activeColor: Colors.amber,
            onChanged: (value) async {
              await _repo.setNotificationsEnabled(value);
              setState(() => _notificationsEnabled = value);
              if (value) {
                final granted = await NotificationService().requestPermission();
                if (granted) {
                  await NotificationService().rescheduleForLanguage(_currentLanguage);
                }
              } else {
                await NotificationService().cancelDailyReminder();
              }
            },
          ),

          const Divider(),

          // ── INFORMAÇÕES ───────────────────────────────────────────────────
          _buildSectionHeader('Informações'),

          ListTile(
            leading: const Icon(Icons.info_outline),
            title: const Text('Versão do App'),
            subtitle: Text(CURRENT_VERSION),
          ),

          ListTile(
            leading: const Icon(Icons.new_releases_outlined, color: Colors.amber),
            title: Text(_t('whats_new')),
            subtitle: Text('v$CURRENT_VERSION', style: const TextStyle(color: Colors.grey)),
            trailing: const Icon(Icons.chevron_right, color: Colors.grey),
            onTap: _showChangelog,
          ),

          ListTile(
            leading: const Icon(Icons.gavel_rounded, color: Color(0xFF4A148C)),
            title: const Text('Aviso Legal'),
            subtitle: const Text('Limitações e propósito do app'),
            trailing: const Icon(Icons.chevron_right, color: Colors.grey),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const DisclaimerScreen()),
            ),
          ),

          const Divider(),

          // ── ESTUDOS ───────────────────────────────────────────────────────
          _buildSectionHeader('Meus Estudos'),

          ListTile(
            leading: const Icon(Icons.bookmark_remove_outlined,
                color: Colors.deepPurple),
            title: const Text('Limpar Lista de Favoritos'),
            subtitle: const Text('Remove todos os itens marcados'),
            onTap: _confirmClearFavorites,
          ),

          const Divider(),

          // ── DEBUG ─────────────────────────────────────────────────────────
          _buildSectionHeader('Zona de Testes (Debug)'),

          ListTile(
            leading: const Icon(Icons.money_off, color: Colors.orange),
            title: const Text('Remover Premium'),
            subtitle: const Text('Voltar para o plano Gratuito'),
            onTap: _confirmResetPremium,
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Text(
        title.toUpperCase(),
        style: TextStyle(
          color: Colors.grey[600],
          fontWeight: FontWeight.bold,
          fontSize: 12,
        ),
      ),
    );
  }
}
