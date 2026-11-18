import 'package:flutter/material.dart';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/repository/odu_repository.dart';
import 'package:odu_journey/l10n/manual_translations.dart'; 
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
    
    // Ordena pela hierarquia
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

  void _onSearchChanged() {
    final query = _searchController.text.toLowerCase();
    setState(() {
      _filteredOdus = _allOdus.where((odu) {
        return odu.nome.toLowerCase().contains(query);
      }).toList();
    });
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
          fontWeight: FontWeight.bold
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Buscar Odu...',
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
              ),
            ),
          ),

          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
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
          // --- RESTAURADO: INVERSÃO PARA CORRIGIR VISUAL ---
          child: OduSymbol(
            right: odu.leftBits, 
            left: odu.rightBits  
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