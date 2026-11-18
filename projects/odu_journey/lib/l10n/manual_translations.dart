import 'package:flutter/material.dart';

class ManualTranslations {
  final Locale locale;

  ManualTranslations(this.locale);

  static ManualTranslations? of(BuildContext context) {
    return Localizations.of<ManualTranslations>(context, ManualTranslations);
  }

  static const LocalizationsDelegate<ManualTranslations> delegate = _ManualTranslationsDelegate();

  static final Map<String, Map<String, String>> _localizedValues = {
    'en': {
      'appTitle': 'Odu Journey',
      'level': 'Level',
      'unlock': 'Unlock Everything',
      'menuStudy': 'Library',
      'menuTrain': 'Memorization',
      'menuDaily': 'Daily Advice',
      'menuWebsite': 'Official Website',
      'menuList': 'Odu List',
      'menuTraining': 'Training',
      'titleDescription': 'Description',
      'titleVerse': 'Verse',
      'labelIre': 'IRE (Positive)',
      'labelIbi': 'IBI (Negative)',
      // Onboarding
      'onboarding_name_title': 'What should\nwe call you?',
      'onboarding_name_hint': 'Your name or nickname',
      'onboarding_name_optional': 'Optional — you can change it later in Settings',
      'onboarding_terms_title': 'Terms of Service',
      'onboarding_terms_accept': 'I have read and accept the Terms of Service',
      'onboarding_disclaimer_title': 'Educational Notice',
      'onboarding_disclaimer_accept': 'I understand this app is exclusively for educational purposes',
      'onboarding_email_hint': 'your@email.com',
      'onboarding_email_label': 'Email (optional)',
      'onboarding_email_sub': 'Save your progress and receive updates',
      'onboarding_start': 'Start Journey',
      'next': 'Next',
      // Settings
      'settings_profile': 'Profile',
      'settings_language': 'Language',
      'settings_language_sub': 'Change app language',
      'settings_name': 'Display name',
      'settings_name_sub': 'How you appear in the app',
      'settings_name_dialog_title': 'Change display name',
      'settings_name_dialog_hint': 'Your name or nickname',
      'settings_cancel': 'Cancel',
      'settings_save': 'Save',
      // Study guide
      'study_guide': 'Study Guide',
      // Changelog
      'whats_new': "What's New",
      'cl_103_1': 'Optional email registration to save your progress',
      'cl_103_2': 'Daily notification reminder to train the Odù',
      'cl_103_3': 'Cloud progress sync (XP, level and premium status)',
      'cl_103_4': 'Bug fixes and performance improvements',
      'cl_102_1': 'Production release.',
      'cl_102_2': 'Added legal notice and app limitations.',
      'cl_100_1': 'Initial launch of Odù Journey.',
      'cl_100_2': 'Complete library with 256 Odù.',
      'cl_100_3': 'Training system (Recognition and Writing).',
      'cl_100_4': 'Level separation (Meji vs 256).',
      'cl_100_5': 'Gamification and XP system.',
      'cl_100_6': 'Premium design and Dashboard mode.',
    },
    'es': {
      'appTitle': 'Jornada Odù',
      'level': 'Nivel',
      'unlock': 'Desbloquear Todo',
      'menuStudy': 'Biblioteca',
      'menuTrain': 'Memorización',
      'menuDaily': 'Consejo Diario',
      'menuWebsite': 'Sitio Oficial',
      'menuList': 'Lista de Odus',
      'menuTraining': 'Entrenamiento',
      'titleDescription': 'Descripción',
      'titleVerse': 'Verso',
      'labelIre': 'IRE (Positivo)',
      'labelIbi': 'IBI (Negativo)',
      // Onboarding
      'onboarding_name_title': '¿Cómo le gustaría\nque le llamemos?',
      'onboarding_name_hint': 'Su nombre o apodo',
      'onboarding_name_optional': 'Opcional — puede cambiarlo después en Ajustes',
      'onboarding_terms_title': 'Términos de Uso',
      'onboarding_terms_accept': 'He leído y acepto los Términos de Uso',
      'onboarding_disclaimer_title': 'Aviso Educativo',
      'onboarding_disclaimer_accept': 'Entiendo que esta app es exclusivamente para fines educativos',
      'onboarding_email_hint': 'tu@email.com',
      'onboarding_email_label': 'Correo (opcional)',
      'onboarding_email_sub': 'Guarda tu progreso y recibe novedades',
      'onboarding_start': 'Iniciar Viaje',
      'next': 'Siguiente',
      // Settings
      'settings_profile': 'Perfil',
      'settings_language': 'Idioma',
      'settings_language_sub': 'Cambiar idioma de la app',
      'settings_name': 'Nombre de usuario',
      'settings_name_sub': 'Cómo apareces en la app',
      'settings_name_dialog_title': 'Cambiar nombre de usuario',
      'settings_name_dialog_hint': 'Tu nombre o apodo',
      'settings_cancel': 'Cancelar',
      'settings_save': 'Guardar',
      // Study guide
      'study_guide': 'Guía de Estudio',
      // Changelog
      'whats_new': 'Novedades',
      'cl_103_1': 'Registro de correo opcional para guardar tu progreso',
      'cl_103_2': 'Notificación diaria para recordar entrenar los Odù',
      'cl_103_3': 'Sincronización de progreso en la nube (XP, nivel y estado premium)',
      'cl_103_4': 'Correcciones de errores y mejoras de rendimiento',
      'cl_102_1': 'Versión de producción.',
      'cl_102_2': 'Agregado aviso legal y limitaciones de la app.',
      'cl_100_1': 'Lanzamiento inicial de Odù Journey.',
      'cl_100_2': 'Biblioteca completa con 256 Odù.',
      'cl_100_3': 'Sistema de entrenamiento (Reconocimiento y Escritura).',
      'cl_100_4': 'Separación por niveles (Meji vs 256).',
      'cl_100_5': 'Sistema de gamificación y XP.',
      'cl_100_6': 'Diseño premium y modo Dashboard.',
    },
    'pt': {
      'appTitle': 'Jornada Odù',
      'level': 'Nível',
      'unlock': 'Desbloqueie Tudo',
      'menuStudy': 'Biblioteca',
      'menuTrain': 'Memorização',
      'menuDaily': 'Conselho',
      'menuWebsite': 'Site Oficial',
      'menuList': 'Lista de Odùs',
      'menuTraining': 'Treino',
      'titleDescription': 'Descrição',
      'titleVerse': 'Verso / Sentença',
      'labelIre': 'IRÉ (Positivo)',
      'labelIbi': 'IBI (Negativo)',
      // Onboarding
      'onboarding_name_title': 'Como gostaria\nde ser chamado?',
      'onboarding_name_hint': 'Seu nome ou apelido',
      'onboarding_name_optional': 'Opcional — pode alterar depois nas Configurações',
      'onboarding_terms_title': 'Termos de Uso',
      'onboarding_terms_accept': 'Li e aceito os Termos de Uso',
      'onboarding_disclaimer_title': 'Aviso Educacional',
      'onboarding_disclaimer_accept': 'Compreendo que este app é exclusivamente para fins educacionais',
      'onboarding_email_hint': 'seu@email.com',
      'onboarding_email_label': 'E-mail (opcional)',
      'onboarding_email_sub': 'Salve seu progresso e receba novidades',
      'onboarding_start': 'Iniciar Jornada',
      'next': 'Próximo',
      // Settings
      'settings_profile': 'Perfil',
      'settings_language': 'Idioma',
      'settings_language_sub': 'Alterar idioma do aplicativo',
      'settings_name': 'Nome de exibição',
      'settings_name_sub': 'Como você aparece no app',
      'settings_name_dialog_title': 'Alterar nome de exibição',
      'settings_name_dialog_hint': 'Seu nome ou apelido',
      'settings_cancel': 'Cancelar',
      'settings_save': 'Salvar',
      // Study guide
      'study_guide': 'Guia de Estudo',
      // Changelog
      'whats_new': 'Novidades',
      'cl_103_1': 'Cadastro de e-mail opcional para salvar seu progresso',
      'cl_103_2': 'Notificação diária para lembrar de treinar os Odù',
      'cl_103_3': 'Sincronização de progresso na nuvem (XP, nível e status premium)',
      'cl_103_4': 'Correções de bugs e melhorias de desempenho',
      'cl_102_1': 'Versão de produção.',
      'cl_102_2': 'Adicionado aviso legal e limitações do app.',
      'cl_100_1': 'Lançamento inicial do Odù Journey.',
      'cl_100_2': 'Biblioteca completa com 256 Odù.',
      'cl_100_3': 'Sistema de Treino (Reconhecimento e Escrita).',
      'cl_100_4': 'Separação por Níveis (Meji vs 256).',
      'cl_100_5': 'Sistema de Gamificação e XP.',
      'cl_100_6': 'Design Premium e Modo Dashboard.',
    },
  };

  String translate(String key) {
    return _localizedValues[locale.languageCode]?[key] ?? 
           _localizedValues['pt']?[key] ?? 
           key;
  }
}

class _ManualTranslationsDelegate extends LocalizationsDelegate<ManualTranslations> {
  const _ManualTranslationsDelegate();

  @override
  bool isSupported(Locale locale) {
    return ['en', 'es', 'pt'].contains(locale.languageCode);
  }

  @override
  Future<ManualTranslations> load(Locale locale) async {
    return ManualTranslations(locale);
  }

  @override
  bool shouldReload(_ManualTranslationsDelegate old) => false;
}