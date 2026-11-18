// lib/ui/screens/learning/learning_home_screen.dart

import 'package:flutter/material.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/ui/screens/subscription/subscription_screen.dart';
import 'package:odu_journey/ui/screens/daily/daily_odu_screen.dart'; 
import 'package:odu_journey/ui/screens/settings/settings_screen.dart'; 
import 'package:url_launcher/url_launcher.dart';
// CORREÇÃO 1: Importando o tradutor manual correto
import 'package:odu_journey/l10n/manual_translations.dart';

class LearningHomeScreen extends StatefulWidget {
  const LearningHomeScreen({super.key});

  @override
  State<LearningHomeScreen> createState() => _LearningHomeScreenState();
}

class _LearningHomeScreenState extends State<LearningHomeScreen> {
  final ProgressRepository _progressRepository = ProgressRepository();
  
  int _xp = 0;
  int _level = 1;
  double _progress = 0.0;

  @override
  void initState() {
    super.initState();
    _loadProgress();
  }

  Future<void> _loadProgress() async {
    final xp = await _progressRepository.getScore();
    if (mounted) {
      setState(() {
        _xp = xp;
        _level = _progressRepository.calculateLevel(xp);
        _progress = _progressRepository.calculateProgressToNextLevel(xp);
      });
    }
  }

  void _refreshOnReturn() {
    _loadProgress();
  }

  Future<void> _launchSite() async {
    final Uri url = Uri.parse('https://www.odujourney.com'); 
    if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Não foi possível abrir o site')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    // CORREÇÃO 2: Usando ManualTranslations com segurança (!)
    final l10n = ManualTranslations.of(context)!;

    return Scaffold(
      backgroundColor: Colors.grey[50],
      body: SafeArea(
        child: Stack(
          children: [
            SingleChildScrollView(
              padding: const EdgeInsets.only(bottom: 40),
              child: Column(
                children: [
                  // CABEÇALHO
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 30, horizontal: 20),
                    child: Column(
                      children: [
                        SizedBox(
                          height: 80,
                          // Se não tiver logo, mostra icone quebrado sem crashar
                          child: Image.asset('assets/logo.png', fit: BoxFit.contain, errorBuilder: (_,__,___) => const Icon(Icons.auto_stories, size: 50, color: Colors.deepPurple)),
                        ),
                        const SizedBox(height: 10),
                        
                        // BARRA DE NÍVEL
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(
                            color: Colors.deepPurple.shade50,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: Colors.deepPurple.shade100),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.stars, color: Colors.deepPurple.shade400, size: 18),
                              const SizedBox(width: 6),
                              Text(
                                // CORREÇÃO 3: Tradução do Nível
                                "${l10n.translate('level')} $_level  •  $_xp XP",
                                style: TextStyle(color: Colors.deepPurple.shade700, fontWeight: FontWeight.bold),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 10),
                        
                        SizedBox(
                          width: 150,
                          child: LinearProgressIndicator(
                            value: _progress,
                            backgroundColor: Colors.grey[200],
                            color: Colors.deepPurple,
                            minHeight: 4,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ],
                    ),
                  ),

                  // CARD PREMIUM
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: InkWell(
                      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SubscriptionScreen())),
                      child: Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(colors: [Colors.amber.shade700, Colors.orange.shade400], begin: Alignment.topLeft, end: Alignment.bottomRight),
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: [BoxShadow(color: Colors.orange.shade700.withOpacity(0.3), blurRadius: 12, offset: const Offset(0, 6))],
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.auto_awesome, color: Colors.white, size: 28),
                            const SizedBox(width: 15),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // CORREÇÃO: Tradução do Desbloqueio
                                  Text(l10n.translate('unlock'), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 17)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 30),

                  // GRID DE MENUS
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: GridView.count(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      crossAxisCount: 2,
                      crossAxisSpacing: 16,
                      mainAxisSpacing: 16,
                      children: [
                        _MenuCard(
                          icon: Icons.menu_book_rounded, 
                          color: const Color(0xFF5D4037), 
                          title: l10n.translate('menuStudy'), // "Biblioteca"
                          subtitle: "16 Meji & 240 Odu", // Subtítulo pode deixar fixo ou adicionar no dict
                          onTap: () => Navigator.pushNamed(context, '/odus'),
                        ),
                        
                        _MenuCard(
                          icon: Icons.psychology, 
                          color: const Color(0xFF388E3C), 
                          title: l10n.translate('menuTrain'), // "Memorização"
                          subtitle: "Flashcards",
                          onTap: () => Navigator.pushNamed(context, '/training').then((_) => _refreshOnReturn()), 
                        ),
                        
                        _MenuCard(
                          icon: Icons.wb_sunny_rounded, 
                          color: Colors.amber.shade800, 
                          title: l10n.translate('menuDaily'), // "Conselho"
                          subtitle: "Ifá Daily", 
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const DailyOduScreen())),
                        ),
                        
                        _MenuCard(
                          icon: Icons.public, 
                          color: Colors.blueGrey.shade600, 
                          title: l10n.translate('menuWebsite'), // "Site Oficial"
                          subtitle: "odujourney.com", 
                          onTap: _launchSite,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            
            // Botão de Ajustes (Canto Superior Direito)
            Positioned(
              top: 10, right: 10,
              child: IconButton(
                icon: const Icon(Icons.settings, color: Colors.grey),
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const SettingsScreen()),
                  ).then((_) => _refreshOnReturn());
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// Widget do Cartão de Menu
class _MenuCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _MenuCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      elevation: 2,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircleAvatar(
                backgroundColor: color.withOpacity(0.1),
                radius: 28,
                child: Icon(icon, color: color, size: 32),
              ),
              const SizedBox(height: 12),
              Text(title, 
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                textAlign: TextAlign.center
              ),
              const SizedBox(height: 4),
              Text(subtitle, 
                style: const TextStyle(fontSize: 12, color: Colors.grey),
                textAlign: TextAlign.center
              ),
            ],
          ),
        ),
      ),
    );
  }
}