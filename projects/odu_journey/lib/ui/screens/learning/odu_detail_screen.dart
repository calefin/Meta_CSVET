import 'package:flutter/material.dart';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/ui/widgets/odu_symbol.dart';
import 'package:odu_journey/l10n/manual_translations.dart'; 

class OduDetailScreen extends StatefulWidget {
  final Odu odu;

  const OduDetailScreen({super.key, required this.odu});

  @override
  State<OduDetailScreen> createState() => _OduDetailScreenState();
}

class _OduDetailScreenState extends State<OduDetailScreen> {
  final ProgressRepository _progressRepo = ProgressRepository();
  bool _isFavorite = false;
  
  @override
  void initState() {
    super.initState();
    _checkFavorite();
  }

  void _checkFavorite() async {
    bool fav = await _progressRepo.isFavorite(widget.odu.nome);
    if (mounted) setState(() => _isFavorite = fav);
  }

  void _toggleFavorite() async {
    await _progressRepo.toggleFavorite(widget.odu.nome);
    setState(() => _isFavorite = !_isFavorite);
  }

  @override
  Widget build(BuildContext context) {
    final odu = widget.odu;
    final colorScheme = Theme.of(context).colorScheme;
    final String currentLang = Localizations.localeOf(context).languageCode;
    
    final content = odu.getContent(currentLang);
    final l10n = ManualTranslations.of(context)!;

    return Scaffold(
      appBar: AppBar(
        title: Text(odu.nome),
        actions: [
          IconButton(
            icon: Icon(_isFavorite ? Icons.favorite : Icons.favorite_border),
            color: _isFavorite ? Colors.red : null,
            onPressed: _toggleFavorite,
          )
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Center(
              child: SizedBox(
                width: 120, 
                height: 120,
                // VOLTAMOS AO PADRÃO QUE FUNCIONAVA NO ESTUDO:
                // Se no seu banco 'right' é a perna da direita visual, mantemos assim.
                child: OduSymbol(
                  right: odu.right, 
                  left: odu.left
                ),
              ),
            ),
            const SizedBox(height: 20),
            
            Row(
              crossAxisAlignment: CrossAxisAlignment.start, 
              children: [
                Expanded(child: _buildInfoCard(
                  context, 
                  l10n.translate('labelIre'),
                  content.ire,
                  Colors.green.shade50, 
                  Colors.green.shade900
                )),
                const SizedBox(width: 10),
                Expanded(child: _buildInfoCard(
                  context, 
                  l10n.translate('labelIbi'),
                  content.ibi,
                  Colors.red.shade50, 
                  Colors.red.shade900
                )),
              ],
            ),
            const SizedBox(height: 20),

            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                l10n.translate('titleDescription'), 
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: colorScheme.primary)
              ),
            ),
            const SizedBox(height: 8),

            Text(
              content.descricao,
              style: const TextStyle(fontSize: 16, height: 1.5),
              textAlign: TextAlign.justify,
            ),
            
            const SizedBox(height: 20),
            const Divider(),

            const SizedBox(height: 10),
            Align(
              alignment: Alignment.centerLeft, 
              child: Text(
                l10n.translate('titleVerse'), 
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: colorScheme.primary)
              )
            ),
            const SizedBox(height: 10),

            if (odu.versoYoruba.isNotEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: colorScheme.surfaceContainerHighest.withOpacity(0.3), 
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: colorScheme.outlineVariant),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text("YORÙBÁ", style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.5, color: colorScheme.secondary)),
                    const SizedBox(height: 8),
                    Text(
                      odu.versoYoruba, 
                      style: const TextStyle(fontStyle: FontStyle.italic, fontSize: 16, height: 1.6, fontFamily: 'Serif', fontWeight: FontWeight.w600)
                    ),
                  ],
                ),
              ),

            if (content.verso.isNotEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: colorScheme.surface, 
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: colorScheme.outlineVariant),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text("TRADUÇÃO (${currentLang.toUpperCase()})", style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.5, color: colorScheme.primary)),
                    const SizedBox(height: 8),
                    Text(
                      content.verso, 
                      style: const TextStyle(fontSize: 15, height: 1.6)
                    ),
                  ],
                ),
              ),
            
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoCard(BuildContext context, String title, String text, Color bg, Color txt) {
    final content = text.isEmpty ? "-" : text;
    
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: TextStyle(fontWeight: FontWeight.bold, color: txt, fontSize: 12)),
          const SizedBox(height: 6),
          Text(content, style: TextStyle(color: txt, fontSize: 13, height: 1.2)),
        ],
      ),
    );
  }
}