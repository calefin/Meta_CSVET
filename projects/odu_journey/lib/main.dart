import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/l10n/manual_translations.dart';
import 'package:odu_journey/ui/screens/learning/learning_home_screen.dart';
import 'package:odu_journey/ui/screens/learning/odu_list_screen.dart';
import 'package:odu_journey/ui/screens/onboarding/onboarding_screen.dart';
import 'package:odu_journey/ui/screens/training/training_options_screen.dart';
import 'package:odu_journey/services/api_service.dart';
import 'package:odu_journey/services/notification_service.dart';
import 'package:odu_journey/services/revenuecat_service.dart';
import 'package:odu_journey/utils/app_locale.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await RevenueCatService().initialize();
  } catch (e) {
    debugPrint('Erro ao inicializar RevenueCat: $e');
  }

  await NotificationService().initialize();

  runApp(const OduJourneyApp());
}

class OduJourneyApp extends StatefulWidget {
  const OduJourneyApp({super.key});

  @override
  State<OduJourneyApp> createState() => _OduJourneyAppState();
}

class _OduJourneyAppState extends State<OduJourneyApp> {
  final _repo = ProgressRepository();
  bool _ready = false;
  bool _onboardingComplete = false;

  @override
  void initState() {
    super.initState();
    appLocaleNotifier.addListener(_onLocaleChanged);
    _init();
  }

  @override
  void dispose() {
    appLocaleNotifier.removeListener(_onLocaleChanged);
    super.dispose();
  }

  void _onLocaleChanged() => setState(() {});

  Future<void> _init() async {
    final lang     = await _repo.getUserLanguage();
    final complete = await _repo.isOnboardingComplete();

    if (lang != null) appLocaleNotifier.value = Locale(lang);

    setState(() {
      _onboardingComplete = complete;
      _ready = true;
    });

    if (complete) {
      _syncProgressInBackground();
      _scheduleNotificationIfNeeded(lang);
    }
  }

  Future<void> _scheduleNotificationIfNeeded(String? lang) async {
    await NotificationService().rescheduleForLanguage(lang ?? 'pt');
  }

  Future<void> _syncProgressInBackground() async {
    final email = await _repo.getUserEmail();
    if (email == null || email.isEmpty) return;

    final deviceId  = await _repo.getDeviceId();
    final xp        = await _repo.getScore();
    final isPremium = await _repo.isPremium();
    final level     = _repo.calculateLevel(xp);

    ApiService().syncProgress(
      deviceId: deviceId,
      xp: xp,
      level: level,
      isPremium: isPremium,
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      onGenerateTitle: (ctx) =>
          ManualTranslations.of(ctx)?.translate('appTitle') ?? 'Odù Journey',

      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.deepPurple),
        useMaterial3: true,
        appBarTheme: const AppBarTheme(
          centerTitle: true,
          elevation: 0,
        ),
      ),

      localizationsDelegates: const [
        ManualTranslations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],

      supportedLocales: const [
        Locale('pt', ''),
        Locale('en', ''),
        Locale('es', ''),
      ],

      locale: appLocaleNotifier.value,

      home: !_ready
          ? const Scaffold(
              body: Center(
                child: CircularProgressIndicator(color: Colors.amber),
              ),
            )
          : _onboardingComplete
              ? const LearningHomeScreen()
              : OnboardingScreen(
                  onComplete: () =>
                      setState(() => _onboardingComplete = true),
                ),

      routes: {
        '/odus': (context) => const OduListScreen(),
        '/training': (context) => const TrainingOptionsScreen(),
      },
    );
  }
}
