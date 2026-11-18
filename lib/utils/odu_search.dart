import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/utils/text_normalizer.dart';

String _normalizeQuery(String input) {
  return normalize(input).replaceAll('oturupon', 'otoropon');
}

List<Odu> searchOdu(List<Odu> odus, String query) {
  final q = _normalizeQuery(query);
  if (q.isEmpty) return odus;
  return odus.where((odu) => _normalizeQuery(odu.nome).contains(q)).toList();
}
