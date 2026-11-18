class OduTranslatedContent {
  final String descricao;
  final String ire;
  final String ibi;
  final String verso;

  OduTranslatedContent({
    required this.descricao,
    required this.ire,
    required this.ibi,
    required this.verso,
  });

  factory OduTranslatedContent.fromMap(Map<String, dynamic> map) {
    return OduTranslatedContent(
      descricao: map['descricao'] ?? '',
      ire: map['ire'] ?? '',
      ibi: map['ibi'] ?? '',
      verso: map['verso'] ?? '',
    );
  }
}

class Odu {
  final String id;
  final String nome;
  final List<int> right;
  final List<int> left;
  
  // Novos campos
  final OduTranslatedContent ptContent;
  final OduTranslatedContent enContent;
  final OduTranslatedContent esContent;
  final String versoYoruba;
  final String versoLegado;

  Odu({
    required this.id,
    required this.nome,
    required this.right,
    required this.left,
    required this.ptContent,
    required this.enContent,
    required this.esContent,
    required this.versoYoruba,
    this.versoLegado = '',
  });

  factory Odu.fromMap(Map<String, dynamic> map) {
    final liturgico = map['conteudo_liturgico'] ?? {};
    
    return Odu(
      id: map['id'] ?? '',
      nome: map['nome'] ?? '',
      right: List<int>.from(map['right'] ?? [1,1,1,1]),
      left: List<int>.from(map['left'] ?? [1,1,1,1]),
      
      versoYoruba: liturgico['yoruba'] ?? '',
      versoLegado: map['verso'] ?? '',

      ptContent: OduTranslatedContent.fromMap(liturgico['portugues'] ?? {}),
      enContent: OduTranslatedContent.fromMap(liturgico['ingles'] ?? {}),
      esContent: OduTranslatedContent.fromMap(liturgico['espanhol'] ?? {}),
    );
  }

  // --- MÉTODOS DE COMPATIBILIDADE (FIX PARA SEUS ERROS) ---
  
  // 1. Apelidos para as listas de bits (OduSymbol usa isso)
  List<int> get rightBits => right;
  List<int> get leftBits => left;

  // 2. Apelidos para texto (retorna Português por padrão para telas antigas)
  String get descricao => ptContent.descricao;
  String get ire => ptContent.ire;
  String get ibi => ptContent.ibi;

  // 3. Método inteligente de idioma
  OduTranslatedContent getContent(String langCode) {
    if (langCode.startsWith('en')) return enContent;
    if (langCode.startsWith('es')) return esContent;
    return ptContent;
  }
}