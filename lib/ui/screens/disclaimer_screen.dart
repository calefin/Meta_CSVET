import 'package:flutter/material.dart';

class DisclaimerScreen extends StatelessWidget {
  const DisclaimerScreen({super.key});

  static const _items = [
    _DisclaimerItem(
      icon: Icons.history_edu_rounded,
      color: Color(0xFF5D4037),
      title: 'Não substitui a tradição',
      body:
          'Este aplicativo é uma ferramenta de estudo e apoio à memorização. '
          'Ele não substitui o conhecimento transmitido pela tradição oral, '
          'pelos mais velhos e pelos guardiões do saber ancestral de Ifá.',
    ),
    _DisclaimerItem(
      icon: Icons.handshake_rounded,
      color: Color(0xFF388E3C),
      title: 'Não substitui o treinamento prático',
      body:
          'O aprendizado de Ifá exige convivência, prática presencial e orientação '
          'direta de um babalawo experiente. Nenhum aplicativo pode reproduzir '
          'essa experiência.',
    ),
    _DisclaimerItem(
      icon: Icons.warning_amber_rounded,
      color: Color(0xFFF57F17),
      title: 'O app pode conter erros',
      body:
          'Embora tenhamos trabalhado com cuidado, este aplicativo pode conter '
          'imprecisões, erros de grafia ou interpretações incompletas. '
          'Sempre consulte fontes tradicionais e pessoas de conhecimento.',
    ),
    _DisclaimerItem(
      icon: Icons.person_off_rounded,
      color: Color(0xFF6A1B9A),
      title: 'Não forma nem substitui um babalawo',
      body:
          'O uso deste aplicativo não confere ao usuário o título, o status '
          'ou as responsabilidades de um babalawo. A iniciação e a formação '
          'sacerdotal ocorrem exclusivamente dentro da tradição.',
    ),
    _DisclaimerItem(
      icon: Icons.gavel_rounded,
      color: Color(0xFFC62828),
      title: 'Não autoriza a prática sacerdotal',
      body:
          'Este aplicativo não serve como formação, certificação ou autorização '
          'para o exercício de qualquer função sacerdotal ou religiosa. '
          'A autoridade religiosa é concedida pela tradição, não por ferramentas digitais.',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: const Text('Aviso Legal'),
        backgroundColor: const Color(0xFF4A148C),
        foregroundColor: Colors.white,
      ),
      body: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFF4A148C).withValues(alpha:0.08),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFF4A148C).withValues(alpha:0.2)),
            ),
            child: const Row(
              children: [
                Icon(Icons.info_outline, color: Color(0xFF4A148C)),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Leia com atenção as limitações e o propósito deste aplicativo.',
                    style: TextStyle(
                      color: Color(0xFF4A148C),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          ..._items.map((item) => _DisclaimerCard(item: item)),
          const SizedBox(height: 12),
          Text(
            'Odù Journey v${_currentVersion()} — CSVET Editora',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 12, color: Colors.grey),
          ),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  String _currentVersion() {
    try {
      return '1.0.2';
    } catch (_) {
      return '';
    }
  }
}

class _DisclaimerCard extends StatelessWidget {
  final _DisclaimerItem item;
  const _DisclaimerCard({required this.item});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha:0.06),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              backgroundColor: item.color.withValues(alpha:0.12),
              radius: 22,
              child: Icon(item.icon, color: item.color, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.title,
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                      color: item.color,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    item.body,
                    style: const TextStyle(
                      fontSize: 13,
                      color: Colors.black87,
                      height: 1.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DisclaimerItem {
  final IconData icon;
  final Color color;
  final String title;
  final String body;
  const _DisclaimerItem({
    required this.icon,
    required this.color,
    required this.title,
    required this.body,
  });
}
