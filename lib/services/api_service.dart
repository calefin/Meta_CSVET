import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class ApiService {
  // ⚠️  Deve ser IGUAL ao define('API_SECRET', ...) em odujourney.com/api/config.php
  static const String _apiKey = 'tzHmtJpEo9MiEc9vyyxtsarSwx8up3ZseHZl4DZg';

  static const String _baseUrl = 'https://odujourney.com/api';
  static const Duration _timeout = Duration(seconds: 10);

  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  Map<String, String> get _headers => {
        'Content-Type': 'application/json',
        'X-Api-Key': _apiKey,
      };

  /// Cadastra ou atualiza o usuário no banco. Falha silenciosamente.
  Future<bool> registerUser({
    required String email,
    required String displayName,
    required String deviceId,
    required String language,
  }) async {
    try {
      final res = await http
          .post(
            Uri.parse('$_baseUrl/register.php'),
            headers: _headers,
            body: jsonEncode({
              'email': email,
              'display_name': displayName,
              'device_id': deviceId,
              'language': language,
            }),
          )
          .timeout(_timeout);

      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data['success'] == true;
    } catch (e) {
      debugPrint('ApiService.registerUser: $e');
      return false;
    }
  }

  /// Sincroniza XP, nível e status premium. Falha silenciosamente.
  Future<bool> syncProgress({
    required String deviceId,
    required int xp,
    required int level,
    required bool isPremium,
  }) async {
    try {
      final res = await http
          .post(
            Uri.parse('$_baseUrl/sync.php'),
            headers: _headers,
            body: jsonEncode({
              'device_id': deviceId,
              'xp': xp,
              'level': level,
              'is_premium': isPremium,
            }),
          )
          .timeout(_timeout);

      return res.statusCode == 200;
    } catch (e) {
      debugPrint('ApiService.syncProgress: $e');
      return false;
    }
  }
}
