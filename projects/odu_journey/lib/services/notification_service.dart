import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:timezone/data/latest_all.dart' as tz;
import 'package:timezone/timezone.dart' as tz;

class NotificationService {
  static const int _dailyReminderId = 1;
  static const String _channelId   = 'odu_daily_reminder';
  static const String _channelName = 'Lembrete Diário';

  static final _plugin = FlutterLocalNotificationsPlugin();
  static bool _initialized = false;

  static final NotificationService _instance = NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  Future<void> initialize() async {
    if (_initialized) return;
    tz.initializeTimeZones();

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const settings = InitializationSettings(android: android);

    await _plugin.initialize(settings);
    _initialized = true;
  }

  /// Agenda lembrete diário às [hour]:[minute] no fuso local.
  Future<void> scheduleDailyReminder({
    int hour = 8,
    int minute = 0,
    required String title,
    required String body,
  }) async {
    await _plugin.zonedSchedule(
      _dailyReminderId,
      title,
      body,
      _nextTime(hour, minute),
      NotificationDetails(
        android: AndroidNotificationDetails(
          _channelId,
          _channelName,
          channelDescription: 'Lembrete para consultar o Odù do dia e praticar os treinos',
          importance: Importance.defaultImportance,
          priority: Priority.defaultPriority,
          icon: '@mipmap/ic_launcher',
        ),
      ),
      androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
      uiLocalNotificationDateInterpretation:
          UILocalNotificationDateInterpretation.absoluteTime,
      matchDateTimeComponents: DateTimeComponents.time,
    );
    debugPrint('NotificationService: lembrete agendado para $hour:${minute.toString().padLeft(2, '0')}');
  }

  Future<void> cancelDailyReminder() async {
    await _plugin.cancel(_dailyReminderId);
  }

  /// Solicita permissão de notificação no Android 13+.
  Future<bool> requestPermission() async {
    final android = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    return await android?.requestNotificationsPermission() ?? false;
  }

  /// Reagenda com as strings corretas para o idioma do usuário.
  Future<void> rescheduleForLanguage(String lang) async {
    final enabled = await ProgressRepository().getNotificationsEnabled();
    if (!enabled) return;

    final titles = {
      'pt': 'Odù Journey',
      'en': 'Odù Journey',
      'es': 'Odù Journey',
    };
    final bodies = {
      'pt': 'Consulte o Odù do dia e pratique os treinos 🌟',
      'en': 'Check your Odù of the day and train 🌟',
      'es': 'Consulta el Odù del día y entrena 🌟',
    };

    await scheduleDailyReminder(
      title: titles[lang] ?? titles['pt']!,
      body:  bodies[lang] ?? bodies['pt']!,
    );
  }

  static tz.TZDateTime _nextTime(int hour, int minute) {
    final now = tz.TZDateTime.now(tz.local);
    var t = tz.TZDateTime(tz.local, now.year, now.month, now.day, hour, minute);
    if (t.isBefore(now)) t = t.add(const Duration(days: 1));
    return t;
  }
}
