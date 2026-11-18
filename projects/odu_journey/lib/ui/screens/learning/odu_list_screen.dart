import 'package:flutter/material.dart';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/repository/odu_repository.dart';
import 'package:odu_journey/l10n/manual_translations.dart'; 
// IMPORTANTE: Importa a tela de detalhes que acabamos de corrigir
import 'package:odu_journey/ui/screens/learning/odu_detail_screen.dart'; 
import 'package:odu_journey/ui/widgets/odu_symbol.dart';
import 'package:odu_journey/utils/odu_hierarchy.dart';

class OduListScreen extends StatefulWidget {
  const OduListScreen({super.key});

  @override
  State<OduListScreen> createState() => _OduListScreenState();
}

class _OduListScreenState extends State<OduListScreen> {
  final OduRepository _repository = OduRepository();
  List<Odu> _allOdus = [];
  List<Odu> _filteredOdus = [];
  bool _isLoading = true;
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadOdus();
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadOdus() async {
    final odus = await _repository.getAllOdus(null); 
    
    // Ordena pela hierarquia (Ogbe -> Oyeku...)
    odus.sort((a, b) {
      return OduHierarchy.isSenior(a, b) ? -1 : 1;
    });

    if (mounted) {
      setState(() {
        _allOdus = odus;
        _filteredOdus = odus;
        _isLoading = false;
      });
    }
  }

  // Normalizador Local (Usa U->O para consertar Oturupon)
  // Você pode trocar por sua classe Fuzzy depois, mas isso garante que funcione agora.
  String _normalize(String input) {
    if (input.isEmpty) return "";
    String text = input.toLowerCase().trim();
    text = text.replaceAll(RegExp(r'[áàâãä]'), 'a');
    text = text.replaceAll(RegExp(r'[éèêëẹ]'), 'e'); 
    text = text.replaceAll(RegExp(r'[íìîï]'), 'i');
    text = text.replaceAll(RegExp(r'[óòôõöọ]'), 'o'); 
    text = text.replaceAll(RegExp(r'[úùûüụ]'), 'u');
    text = text.replaceAll(RegExp(r'[ṣ]'), 's');     
    text = text.replaceAll(RegExp(r'[^a-z0-9]'), '');
    text = text.replaceAll('u', 'o'); // O segredo do Oturupon
    return text;
  }

  void _onSearchChanged() {
    final queryRaw = _searchController.text;
    if (queryRaw.isEmpty) {
      setState(() => _filteredOdus = _allOdus);
      return;
    }

    final queryNorm = _normalize(queryRaw);

    setState(() {
      _filteredOdus = _allOdus.where((odu) {
        final nameNorm = _normalize(odu.nome);
        return nameNorm.contains(queryNorm);
      }).toList();
    });
  }

  void _showStudyGuide(BuildContext context, ManualTranslations l10n) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _StudyGuideSheet(l10n: l10n),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = ManualTranslations.of(context)!;

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text(l10n.translate('menuList')),
        centerTitle: true,
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.deepPurple),
        titleTextStyle: const TextStyle(
          color: Colors.deepPurple,
          fontSize: 20,
          fontWeight: FontWeight.bold,
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.help_outline, color: Colors.deepPurple),
            tooltip: l10n.translate('study_guide'),
            onPressed: () => _showStudyGuide(context, l10n),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Buscar (ex: Ogbe, Oturupon...)',
                prefixIcon: const Icon(Icons.search),
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(30),
                  borderSide: BorderSide.none,
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(30),
                  borderSide: BorderSide(color: Colors.grey.shade200),
                ),
                suffixIcon: _searchController.text.isNotEmpty 
                  ? IconButton(
                      icon: const Icon(Icons.clear, color: Colors.grey),
                      onPressed: () {
                        _searchController.clear();
                        FocusScope.of(context).unfocus();
                      },
                    )
                  : null,
              ),
            ),
          ),

          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _filteredOdus.isEmpty 
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.search_off, size: 60, color: Colors.grey.shade300),
                          const SizedBox(height: 10),
                          Text("Nenhum Odu encontrado", style: TextStyle(color: Colors.grey.shade600)),
                        ],
                      ),
                    )
                  : ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: _filteredOdus.length,
                    itemBuilder: (context, index) {
                      final odu = _filteredOdus[index];
                      return _buildOduTile(context, odu);
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildOduTile(BuildContext context, Odu odu) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 2,
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        onTap: () {
          // Aqui chamamos o OduDetailScreen que agora existe corretamente
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => OduDetailScreen(odu: odu),
            ),
          );
        },
        leading: Container(
          width: 50,
          height: 50,
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            color: Colors.deepPurple.shade50,
            borderRadius: BorderRadius.circular(8),
          ),
          child: OduSymbol(
            right: odu.rightBits, // Inversão Visual para a Lista (se necessário)
            left: odu.leftBits  
          ),
        ),
        title: Text(
          odu.nome,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 18,
            color: Colors.black87,
          ),
        ),
        trailing: const Icon(Icons.arrow_forward_ios, size: 16, color: Colors.grey),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// GUIA DE ESTUDO — bottom sheet com instruções básicas
// ─────────────────────────────────────────────────────────────────────────────

class _StudyGuideSheet extends StatelessWidget {
  final ManualTranslations l10n;
  const _StudyGuideSheet({required this.l10n});

  String _t(String key) => l10n.translate(key);
  String get _lang => l10n.locale.languageCode;

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.88,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      builder: (_, controller) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          children: [
            // Handle
            Container(
              margin: const EdgeInsets.symmetric(vertical: 12),
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
              child: Row(
                children: [
                  const Icon(Icons.menu_book_rounded, color: Colors.deepPurple, size: 26),
                  const SizedBox(width: 10),
                  Text(
                    _t('study_guide'),
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: Colors.deepPurple,
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                controller: controller,
                padding: const EdgeInsets.all(20),
                children: [
                  _buildSection(
                    Icons.edit_note_rounded,
                    Colors.deepPurple,
                    _namesTitle,
                    _namesContent,
                  ),
                  const SizedBox(height: 20),
                  _buildSection(
                    Icons.balance_rounded,
                    Colors.teal,
                    _ireIbiTitle,
                    _ireIbiContent,
                  ),
                  const SizedBox(height: 20),
                  _buildSection(
                    Icons.stairs_rounded,
                    Colors.orange,
                    _hierarchyTitle,
                    _hierarchyContent,
                  ),
                  const SizedBox(height: 32),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSection(IconData icon, Color color, String title, List<_GuideEntry> entries) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: color,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        ...entries.map((e) => _buildEntry(e, color)),
      ],
    );
  }

  Widget _buildEntry(_GuideEntry entry, Color accentColor) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: accentColor.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: accentColor.withValues(alpha: 0.15)),
      ),
      child: entry.subtitle != null
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(entry.label,
                    style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                        color: Colors.black87)),
                const SizedBox(height: 4),
                Text(entry.subtitle!,
                    style: const TextStyle(
                        fontSize: 13, color: Colors.black54, height: 1.4)),
              ],
            )
          : Text(entry.label,
              style: const TextStyle(fontSize: 13, color: Colors.black87, height: 1.4)),
    );
  }

  // ── SEÇÃO 1: NOMES DOS ODÙ ───────────────────────────────────────────────

  String get _namesTitle {
    switch (_lang) {
      case 'en': return 'How to write Odù names';
      case 'es': return 'Cómo escribir los nombres Odù';
      default:   return 'Como escrever os nomes dos Odù';
    }
  }

  List<_GuideEntry> get _namesContent {
    final note = _lang == 'en'
        ? 'The app accepts variations without accents. Use the canonical form for the best results.'
        : _lang == 'es'
            ? 'La app acepta variaciones sin tildes. Use la forma canónica para mejores resultados.'
            : 'O app aceita variações sem acentos. Use a forma canônica para melhores resultados.';

    return [
      _GuideEntry(note),
      _GuideEntry('Ogbè', subtitle: 'Ogbe'),
      _GuideEntry('Oyèkú', subtitle: 'Oyeku, Oyecu'),
      _GuideEntry('Ìwòrì', subtitle: 'Iwori'),
      _GuideEntry('Odí', subtitle: 'Odi'),
      _GuideEntry('Ìròsùn', subtitle: 'Irosun'),
      _GuideEntry('Òwónrín', subtitle: 'Owonrin'),
      _GuideEntry('Óbàrà', subtitle: 'Obara'),
      _GuideEntry('Ókànràn', subtitle: 'Okanran'),
      _GuideEntry('Ògúndá', subtitle: 'Ogunda'),
      _GuideEntry('Òsá', subtitle: 'Osa'),
      _GuideEntry('Ìká', subtitle: 'Ika'),
      _GuideEntry('Òtúrúpòn', subtitle: 'Oturupon, Otoropon'),
      _GuideEntry('Òtúrà', subtitle: 'Otura'),
      _GuideEntry('Ìrètè', subtitle: 'Irete'),
      _GuideEntry('Òsé', subtitle: 'Ose'),
      _GuideEntry('Òfún', subtitle: 'Ofun'),
    ];
  }

  // ── SEÇÃO 2: IRÉ E IBI ───────────────────────────────────────────────────

  String get _ireIbiTitle {
    switch (_lang) {
      case 'en': return 'What is Iré and Ibi';
      case 'es': return 'Qué es Iré e Ibi';
      default:   return 'O que é Iré e Ibi';
    }
  }

  List<_GuideEntry> get _ireIbiContent {
    switch (_lang) {
      case 'en':
        return [
          _GuideEntry('Iré',
              subtitle: 'Manifestations of positive energy: health, prosperity, victory and peace. '
                  'Examples: Iré Owó (wealth), Iré Ariku (health), Iré Ayé (happiness).'),
          _GuideEntry('Ibi',
              subtitle: 'Manifestations of challenging energy: illness, loss and conflict. '
                  'Ibi is not a "curse" — it is a warning that calls for attention and care.'),
          _GuideEntry('Ebó',
              subtitle: 'Ritual of transformation. When Ifá indicates Ibi, the ebó '
                  'is the prescribed path to turn challenge into a positive flow.'),
        ];
      case 'es':
        return [
          _GuideEntry('Iré',
              subtitle: 'Manifestaciones de energía positiva: salud, prosperidad, victoria y paz. '
                  'Ejemplos: Iré Owó (riqueza), Iré Ariku (salud), Iré Ayé (felicidad).'),
          _GuideEntry('Ibi',
              subtitle: 'Manifestaciones de energía desafiante: enfermedad, pérdida y conflicto. '
                  'Ibi no es una "maldición" — es un aviso que llama a la atención y al cuidado.'),
          _GuideEntry('Ebó',
              subtitle: 'Ritual de transformación. Cuando Ifá indica Ibi, el ebó '
                  'es el camino prescrito para convertir el desafío en flujo positivo.'),
        ];
      default:
        return [
          _GuideEntry('Iré',
              subtitle: 'Manifestações de energia positiva: saúde, prosperidade, vitória e paz. '
                  'Exemplos: Iré Owó (riqueza), Iré Ariku (saúde), Iré Ayé (alegria).'),
          _GuideEntry('Ibi',
              subtitle: 'Manifestações de energia desafiadora: doenças, perdas e conflitos. '
                  'Ibi não é uma "maldição" — é um aviso que exige atenção e cuidado.'),
          _GuideEntry('Ebó',
              subtitle: 'Ritual de transformação. Quando Ifá aponta Ibi, o ebó '
                  'é o caminho prescrito para converter o desafio em fluxo positivo.'),
        ];
    }
  }

  // ── SEÇÃO 3: HIERARQUIA ──────────────────────────────────────────────────

  String get _hierarchyTitle {
    switch (_lang) {
      case 'en': return 'Senior and Junior Odù';
      case 'es': return 'Odù Mayor y Menor';
      default:   return 'Mais Velho e Mais Novo';
    }
  }

  List<_GuideEntry> get _hierarchyContent {
    switch (_lang) {
      case 'en':
        return [
          _GuideEntry('Fixed order',
              subtitle: 'The 16 Odù Meji have a fixed seniority order established by tradition. '
                  'Ogbè is the eldest and Òfún the youngest among the principal sixteen.'),
          _GuideEntry('In combinations',
              subtitle: 'The 256 Odù are formed by combining two Odù. '
                  'The "senior" one (Ojú Odù) appears on the right column and '
                  'governs the name — e.g., Ogbe Oyeku: Ogbe is senior.'),
          _GuideEntry('Not superiority',
              subtitle: 'Seniority indicates spiritual experience and ancestral authority, '
                  'not that one Odù is "better" than another. All are sacred.'),
        ];
      case 'es':
        return [
          _GuideEntry('Orden fijo',
              subtitle: 'Los 16 Odù Meji tienen un orden de antigüedad fijo establecido por la tradición. '
                  'Ogbè es el mayor y Òfún el menor entre los dieciséis principales.'),
          _GuideEntry('En combinaciones',
              subtitle: 'Los 256 Odù se forman combinando dos Odù. '
                  'El "mayor" (Ojú Odù) aparece en la columna derecha y '
                  'gobierna el nombre — ej., Ogbe Oyeku: Ogbe es el mayor.'),
          _GuideEntry('No es superioridad',
              subtitle: 'La antigüedad indica experiencia espiritual y autoridad ancestral, '
                  'no que un Odù sea "mejor" que otro. Todos son sagrados.'),
        ];
      default:
        return [
          _GuideEntry('Ordem fixa',
              subtitle: 'Os 16 Odù Meji têm uma ordem hierárquica fixa estabelecida pela tradição. '
                  'Ogbè é o mais velho e Òfún o mais novo entre os dezesseis principais.'),
          _GuideEntry('Nas combinações',
              subtitle: 'Os 256 Odù são formados pela combinação de dois Odù. '
                  'O "mais velho" (Ojú Odù) aparece na coluna da direita e '
                  'governa o nome — ex., Ogbe Oyeku: Ogbe é o mais velho.'),
          _GuideEntry('Não é superioridade',
              subtitle: 'A senioridade indica experiência espiritual e autoridade ancestral, '
                  'não que um Odù seja "melhor" que outro. Todos são sagrados.'),
        ];
    }
  }
}

class _GuideEntry {
  final String label;
  final String? subtitle;
  const _GuideEntry(this.label, {this.subtitle});
}