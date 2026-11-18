import 'dart:convert';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/odu_data.dart'; // Arquivo gerado pelo Python

class OduRepository {
  
  static List<Odu>? _cachedOdus;

  // Singleton (Opcional, mas recomendado para evitar recarregar dados)
  static final OduRepository _instance = OduRepository._internal();
  factory OduRepository() => _instance;
  OduRepository._internal();

  // Método principal
  Future<List<Odu>> loadOdu() async {
    if (_cachedOdus != null) return _cachedOdus!;
    try {
      final List<dynamic> jsonList = json.decode(oduJsonString);
      _cachedOdus = jsonList.map((json) => Odu.fromMap(json)).toList();
      return _cachedOdus!;
    } catch (e) {
      print("Erro ao carregar Odus: $e");
      return [];
    }
  }
  
  // Método auxiliar para criar um Odu vazio/erro com a NOVA estrutura
  Odu getEmptyOdu() {
    return Odu(
      id: 'error', 
      nome: 'Erro', 
      right: [1,1,1,1], 
      left: [1,1,1,1], 
      ptContent: OduTranslatedContent(descricao: 'Erro', ire: '', ibi: '', verso: ''),
      enContent: OduTranslatedContent(descricao: 'Error', ire: '', ibi: '', verso: ''),
      esContent: OduTranslatedContent(descricao: 'Error', ire: '', ibi: '', verso: ''),
      versoYoruba: ''
    );
  }

  // --- MÉTODOS DE COMPATIBILIDADE ---

  // 1. Apelido para getAllOdus
  Future<List<Odu>> getAllOdus(String? languageCode) async {
    return loadOdu();
  }

  // 2. Busca Odu do dia
  Future<Odu> getOduOfTheDay() async {
    final odus = await loadOdu();
    

    if (odus.isEmpty) return getEmptyOdu();
    
    // Lógica para pegar um Odu fixo por dia
    final now = DateTime.now();
    final startOfYear = DateTime(now.year, 1, 1);
    // Pega o dia do ano (0 a 365)
    final dayOfYear = now.difference(startOfYear).inDays;
    
    final index = dayOfYear % odus.length;
    return odus[index];
  }
}