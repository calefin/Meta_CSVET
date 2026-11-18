import 'package:diacritic/diacritic.dart';

String normalize(String input) {
  return removeDiacritics(input)
      .toLowerCase()
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim();
}
