// lib/ui/screens/training/training_options_screen.dart

import 'package:flutter/material.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/services/revenuecat_service.dart';
import 'package:odu_journey/ui/screens/subscription/subscription_screen.dart';
import 'package:odu_journey/ui/screens/training/odu_training_screen.dart';
import 'package:odu_journey/ui/screens/training/training_recognize_screen.dart';
import 'package:odu_journey/ui/screens/training/hierarchy_training_screen.dart';
import 'package:odu_journey/ui/screens/training/ire_ibi_training_screen.dart';

class TrainingOptionsScreen extends StatefulWidget {
  const TrainingOptionsScreen({super.key});

  @override
  State<TrainingOptionsScreen> createState() => _TrainingOptionsScreenState();
}

class _TrainingOptionsScreenState extends State<TrainingOptionsScreen> {
  final ProgressRepository _progressRepo = ProgressRepository();
  final RevenueCatService _revenueCat = RevenueCatService();

  bool _isPremium = false;
  int _currentXp = 0;
  int _viewsUsed = 0;

  static const int UNLOCK_LEVEL_2_XP = 150;
  static const int UNLOCK_LEVEL_3_XP = 400;

  @override
  void initState() {
    super.initState();
    _loadStatus();
  }

  Future<void> _loadStatus() async {
    final premium = await _revenueCat.isPremium();
    final xp = await _progressRepo.getScore();
    final views = await _progressRepo.getDailyViewCount();

    if (mounted) {
      setState(() {
        _isPremium = premium;
        _currentXp = xp;
        _viewsUsed = views;
      });
    }
  }

  Future<void> _checkAccessAndNavigate(Widget targetScreen) async {
    bool isPremium = await _revenueCat.isPremium();
    if (isPremium) {
      if (!mounted) return;
      await Navigator.push(context, MaterialPageRoute(builder: (_) => targetScreen));
      _loadStatus();
      return;
    }

    bool allowed = await _progressRepo.checkDailyLimit();
    if (allowed) {
      if (!mounted) return;
      await Navigator.push(context, MaterialPageRoute(builder: (_) => targetScreen));
      _loadStatus();
    } else {
      _showLimitDialog(targetScreen);
    }
  }

  void _showLimitDialog(Widget targetScreen) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text("Limite Diário Atingido"),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.lock, size: 50, color: Colors.amber),
            SizedBox(height: 10),
            Text("Você completou seus ciclos de estudo gratuitos por hoje.", textAlign: TextAlign.center),
            SizedBox(height: 10),
            Text("Assine o Premium ou aguarde até amanhã!", textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: Colors.grey)),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text("Voltar")),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.amber.shade700, foregroundColor: Colors.white),
            onPressed: () {
              Navigator.pop(context);
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => SubscriptionScreen(
                    onPurchaseSuccess: () {
                      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => targetScreen));
                      _loadStatus();
                    },
                  ),
                ),
              );
            },
            child: const Text("SEJA PREMIUM"),
          )
        ],
      ),
    );
  }

  void _showLockedMessage(int requiredXp) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text("Bloqueado! Você precisa de $requiredXp XP para acessar."),
        backgroundColor: Colors.grey.shade800,
        action: SnackBarAction(label: "OK", onPressed: () {}),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    bool level2Unlocked = _currentXp >= UNLOCK_LEVEL_2_XP;
    bool level3Unlocked = _currentXp >= UNLOCK_LEVEL_3_XP;

    return Scaffold(
      appBar: AppBar(title: const Text("Área de Treino")),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            margin: const EdgeInsets.only(bottom: 20),
            decoration: BoxDecoration(
              color: _isPremium ? Colors.green.shade50 : Colors.deepPurple.shade50,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text("Seu XP: $_currentXp", style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.deepPurple)),
                    if (_isPremium) const Text("✅ Premium Ativo", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.green)),
                  ],
                ),
                if (!_isPremium)
                  Text("Ciclos hoje: $_viewsUsed/3", style: TextStyle(color: _viewsUsed >= 3 ? Colors.red : Colors.grey[700])),
              ],
            ),
          ),

          _buildSectionHeader("1. Reconhecimento (Iniciante)"),
          _buildOptionCard(
            title: "Memorizar os 16 Mejis", subtitle: "Comece por aqui.", icon: Icons.grid_view, color: Colors.brown, isLocked: false,
            onTap: () => _checkAccessAndNavigate(const TrainingRecognizeScreen(isMejiOnly: true)),
          ),
          _buildOptionCard(
            title: "Memorizar os 256 Odù", subtitle: "Identifique pelo desenho.", icon: Icons.apps, color: Colors.indigo, isLocked: false,
            onTap: () => _checkAccessAndNavigate(const TrainingRecognizeScreen(isMejiOnly: false)),
          ),
          _buildOptionCard(
            title: "Escrever o Nome", subtitle: "Teste sua ortografia.", icon: Icons.edit, color: Colors.teal, isLocked: false,
            onTap: () => _checkAccessAndNavigate(const OduTrainingScreen(isQuizMode: false)),
          ),

          const SizedBox(height: 20),

          _buildSectionHeader("2. Hierarquia (Intermediário)"),
          _buildOptionCard(
            title: "Hierarquia dos Mejis", subtitle: "Quem é mais velho?", icon: Icons.sort, color: Colors.orange, isLocked: !level2Unlocked,
            lockedMessage: "Atinja $UNLOCK_LEVEL_2_XP XP para liberar.",
            onTap: () => _checkAccessAndNavigate(const HierarchyTrainingScreen(isMejiOnly: true)),
          ),
          _buildOptionCard(
            title: "Hierarquia Geral (256)", subtitle: "Regras de idade e senioridade.", icon: Icons.compare_arrows, color: Colors.deepOrange, isLocked: !level2Unlocked,
            lockedMessage: "Atinja $UNLOCK_LEVEL_2_XP XP para liberar.",
            onTap: () => _checkAccessAndNavigate(const HierarchyTrainingScreen(isMejiOnly: false)),
          ),

          const SizedBox(height: 20),

          _buildSectionHeader("3. Prática de Ifá (Avançado)"),
          _buildOptionCard(
            title: "Iré ou Ibi?", subtitle: "Simule uma tiragem completa.", icon: Icons.thumbs_up_down, color: Colors.green, isLocked: !level3Unlocked,
            lockedMessage: "Atinja $UNLOCK_LEVEL_3_XP XP para liberar.",
            onTap: () => _checkAccessAndNavigate(const IreIbiTrainingScreen()),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10, left: 5),
      child: Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.grey)),
    );
  }

  Widget _buildOptionCard({
    required String title, required String subtitle, required IconData icon, required Color color, required bool isLocked,
    String? lockedMessage, required VoidCallback onTap,
  }) {
    return Card(
      elevation: isLocked ? 0 : 2,
      color: isLocked ? Colors.grey.shade100 : Colors.white,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        leading: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: isLocked ? Colors.grey.shade300 : color.withOpacity(0.1), shape: BoxShape.circle),
          child: Icon(isLocked ? Icons.lock : icon, color: isLocked ? Colors.grey : color),
        ),
        title: Text(title, style: TextStyle(fontWeight: FontWeight.bold, color: isLocked ? Colors.grey : Colors.black87)),
        subtitle: Text(isLocked ? lockedMessage ?? "Bloqueado" : subtitle, style: TextStyle(color: isLocked ? Colors.red.shade300 : Colors.grey.shade600)),
        trailing: isLocked ? const Icon(Icons.lock, color: Colors.grey) : const Icon(Icons.chevron_right),
        onTap: isLocked ? () => _showLockedMessage(UNLOCK_LEVEL_2_XP) : onTap,
      ),
    );
  }
}