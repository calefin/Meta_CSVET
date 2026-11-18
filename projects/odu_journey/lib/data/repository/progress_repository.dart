import 'dart:math';
import 'package:shared_preferences/shared_preferences.dart';

class ProgressRepository {
  // Constantes de Chaves (Keys) para salvar no celular
  static const String KEY_XP = 'user_xp';
  static const String KEY_FAVORITES = 'favorites';
  static const String KEY_PREMIUM = 'is_premium';
  static const String KEY_DAILY_DATE = 'daily_view_date';
  static const String KEY_DAILY_COUNT = 'daily_view_count';
  static const String KEY_ONBOARDING = 'onboarding_complete';
  static const String KEY_LANGUAGE = 'user_language';
  static const String KEY_DISPLAY_NAME = 'user_display_name';
  static const String KEY_USER_EMAIL = 'user_email';
  static const String KEY_DEVICE_ID  = 'device_id';

  // Constante para compatibilidade com telas antigas
  static const int UNLOCK_TARGET = 100; 

  // --- CONFIGURAÇÃO DA CURVA DE NÍVEL (RPG) ---
  // Fórmula: XP Necessário = BASE * (Nivel-1) ^ EXPOENTE
  // Com Base 100 e Expoente 1.5, a progressão fica:
  // Nível 1: 0 XP
  // Nível 2: 100 XP (Gap de 100)
  // Nível 3: 282 XP (Gap de 182)
  // Nível 4: 520 XP (Gap de 238)
  // Nível 5: 800 XP (Gap de 280)
  static const int XP_BASE = 100;
  static const double XP_EXPONENT = 1.5;

  // ==========================================
  //  SISTEMA DE XP E NÍVEIS (GAMIFICAÇÃO)
  // ==========================================

  /// Retorna o XP total acumulado pelo usuário
  Future<int> getScore() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(KEY_XP) ?? 0;
  }

  /// Adiciona XP ao usuário
  Future<void> incrementScore({int amount = 10}) async {
    final prefs = await SharedPreferences.getInstance();
    int current = prefs.getInt(KEY_XP) ?? 0;
    await prefs.setInt(KEY_XP, current + amount);
  }

  /// Calcula o Nível atual com base no XP total (Inverso da fórmula de potência)
  int calculateLevel(int totalXp) {
    if (totalXp < XP_BASE) return 1;
    // Matemática: Nivel = (XP / Base)^(1/Expoente) + 1
    return pow((totalXp / XP_BASE), (1 / XP_EXPONENT)).floor() + 1;
  }

  /// Retorna quanto XP total é necessário para alcançar o início do nível X
  int getXpRequiredForLevel(int level) {
    if (level <= 1) return 0;
    // Matemática: XP = Base * (Nivel-1)^Expoente
    return (XP_BASE * pow((level - 1), XP_EXPONENT)).floor();
  }

  /// Calcula a porcentagem (0.0 a 1.0) percorrida dentro do nível atual
  /// Usado para encher a barra de progresso visualmente
  double calculateProgressToNextLevel(int totalXp) {
    int currentLevel = calculateLevel(totalXp);
    int nextLevel = currentLevel + 1;

    int xpStartOfLevel = getXpRequiredForLevel(currentLevel); // Onde começou o nível atual
    int xpNextLevel = getXpRequiredForLevel(nextLevel);       // Onde começa o próximo

    int xpGainedInThisLevel = totalXp - xpStartOfLevel;       // Quanto já andou
    int xpNeededForThisLevel = xpNextLevel - xpStartOfLevel;  // Quanto precisa andar no total

    if (xpNeededForThisLevel <= 0) return 1.0; // Prevenção de erro matemático

    double progress = xpGainedInThisLevel / xpNeededForThisLevel;
    
    // Garante que o valor fique entre 0.0 (vazio) e 1.0 (cheio)
    return progress.clamp(0.0, 1.0);
  }

  // ==========================================
  //  SISTEMA DE FAVORITOS
  // ==========================================

  Future<List<String>> getFavorites() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getStringList(KEY_FAVORITES) ?? [];
  }

  Future<bool> isFavorite(String oduName) async {
    final favs = await getFavorites();
    return favs.contains(oduName);
  }

  Future<void> toggleFavorite(String oduName) async {
    final prefs = await SharedPreferences.getInstance();
    List<String> favs = prefs.getStringList(KEY_FAVORITES) ?? [];

    if (favs.contains(oduName)) {
      favs.remove(oduName);
    } else {
      favs.add(oduName);
    }
    await prefs.setStringList(KEY_FAVORITES, favs);
  }

  Future<void> clearAllFavorites() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(KEY_FAVORITES);
  }

  // ==========================================
  //  SISTEMA PREMIUM & FREEMIUM (BLOQUEIO DIÁRIO)
  // ==========================================

  Future<bool> isPremium() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(KEY_PREMIUM) ?? false;
  }

  Future<void> setPremium(bool status) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(KEY_PREMIUM, status);
  }

  /// Retorna quantas vezes o usuário usou o app hoje (para usuários grátis)
  Future<int> getDailyViewCount() async {
    final prefs = await SharedPreferences.getInstance();
    final today = DateTime.now().toIso8601String().split('T')[0]; // Pega data de hoje YYYY-MM-DD
    final lastDate = prefs.getString(KEY_DAILY_DATE) ?? '';

    // Se mudou o dia, reseta o contador para 0
    if (lastDate != today) {
      await prefs.setString(KEY_DAILY_DATE, today);
      await prefs.setInt(KEY_DAILY_COUNT, 0);
      return 0;
    }
    return prefs.getInt(KEY_DAILY_COUNT) ?? 0;
  }

  /// Incrementa o uso diário (chamado ao terminar um treino)
  Future<void> incrementDailyView() async {
    final prefs = await SharedPreferences.getInstance();
    // Garante que a data está atualizada antes de incrementar
    int current = await getDailyViewCount(); 
    await prefs.setInt(KEY_DAILY_COUNT, current + 1);
  }

  // ==========================================
  //  ONBOARDING, IDIOMA E PERFIL DO USUÁRIO
  // ==========================================

  Future<bool> isOnboardingComplete() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(KEY_ONBOARDING) ?? false;
  }

  Future<void> setOnboardingComplete() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(KEY_ONBOARDING, true);
  }

  Future<String?> getUserLanguage() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(KEY_LANGUAGE);
  }

  Future<void> setUserLanguage(String languageCode) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(KEY_LANGUAGE, languageCode);
  }

  Future<String> getUserDisplayName() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(KEY_DISPLAY_NAME) ?? '';
  }

  Future<void> setUserDisplayName(String name) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(KEY_DISPLAY_NAME, name.trim());
  }

  Future<String?> getUserEmail() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(KEY_USER_EMAIL);
  }

  Future<void> setUserEmail(String email) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(KEY_USER_EMAIL, email.trim());
  }

  /// Retorna o device_id único — gera e persiste na primeira chamada.
  /// Retorna true se o usuário tem acesso aos 256 Odù (premium).
  Future<bool> is256Unlocked() => isPremium();

  Future<String> getDeviceId() async {
    final prefs = await SharedPreferences.getInstance();
    String? id = prefs.getString(KEY_DEVICE_ID);
    if (id == null) {
      final rng = Random.secure();
      id = List.generate(32, (_) => rng.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
      await prefs.setString(KEY_DEVICE_ID, id);
    }
    return id;
  }

  Future<bool> getNotificationsEnabled() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('notifications_enabled') ?? true;
  }

  Future<void> setNotificationsEnabled(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('notifications_enabled', value);
  }

  /// Verifica se pode usar (Premium = Sim, Grátis = Se uso < 3)
  Future<bool> checkDailyLimit() async {
    bool premium = await isPremium();
    if (premium) return true; // Premium não tem limites

    int views = await getDailyViewCount();
    return views < 3; // Limite de 3 ciclos por dia para grátis
  }
}