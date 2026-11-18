import 'package:flutter/material.dart';

/// Notifier global de locale. Atualizar este valor reconstrói o [OduJourneyApp]
/// com o novo idioma sem perder o estado de navegação.
final ValueNotifier<Locale?> appLocaleNotifier = ValueNotifier(null);
