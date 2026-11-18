import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/odu_patterns.dart';

class OduFactory {
  static List<int> _patternFor(String name) {
    try {
      if (oduPatterns.containsKey(name)) {
        return List<int>.from(oduPatterns[name]!);
      }
    } catch (_) {}
    return [1, 1, 1, 1];
  }

  static Odu create(String left, String right) {
    final leftPattern  = _patternFor(left);
    final rightPattern = _patternFor(right);
    final nome = '$right $left';
    final empty = OduTranslatedContent(descricao: nome, ire: '', ibi: '', verso: '');

    return Odu(
      id: '${right}_$left'.toLowerCase().replaceAll(' ', '_'),
      nome: nome,
      right: rightPattern,
      left: leftPattern,
      ptContent: empty,
      enContent: empty,
      esContent: empty,
      versoYoruba: '',
    );
  }

  static List<Odu> generate256(List<String> oduNames) {
    return [
      for (final left in oduNames)
        for (final right in oduNames) create(left, right),
    ];
  }
}
