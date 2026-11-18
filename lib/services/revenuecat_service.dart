import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:purchases_flutter/purchases_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

class RevenueCatService {
  static const String _apiKey = 'goog_gCdoPYCOYDOWlkxSbFoGLtcqDyS';

  static const String _entitlementId = 'CSVET Editora, Cursos e Treinamentos Pro';

  static const String _premiumKey = 'is_premium';

  // Singleton
  static final RevenueCatService _instance = RevenueCatService._internal();
  factory RevenueCatService() => _instance;
  RevenueCatService._internal();

  bool _isInitialized = false;
  CustomerInfo? _customerInfo;
  Offerings? _offerings;

  Future<void> initialize() async {
    if (_isInitialized) return;
    try {
      await Purchases.setLogLevel(LogLevel.debug);
      await Purchases.configure(PurchasesConfiguration(_apiKey));
      _isInitialized = true;
      debugPrint('✅ RevenueCat inicializado com sucesso');
      await _loadCustomerInfo();
      await _syncPremiumStatus();
      await _loadOfferings();
    } catch (e) {
      debugPrint('❌ Erro ao inicializar RevenueCat: $e');
    }
  }

  Future<bool> isPremium() async {
    try {
      await _loadCustomerInfo();
      if (_customerInfo == null) {
        // Sem conexão: usa cache local
        final prefs = await SharedPreferences.getInstance();
        return prefs.getBool(_premiumKey) ?? false;
      }
      final isActive = _customerInfo!.entitlements.active.containsKey(_entitlementId);
      await _writePremiumCache(isActive);
      return isActive;
    } catch (e) {
      debugPrint('❌ Erro isPremium: $e');
      final prefs = await SharedPreferences.getInstance();
      return prefs.getBool(_premiumKey) ?? false;
    }
  }

  Future<List<Package>> getAllPackages() async {
    try {
      final offerings = await getOfferings();
      if (offerings == null || offerings.current == null) return [];
      return offerings.current!.availablePackages;
    } catch (e) {
      return [];
    }
  }

  Future<bool> purchasePackage(Package package) async {
    try {
      // ignore: deprecated_member_use
      final result = await Purchases.purchasePackage(package);
      _customerInfo = result.customerInfo;
      final isActive = _customerInfo!.entitlements.active.containsKey(_entitlementId);
      await _writePremiumCache(isActive);
      return isActive;
    } on PlatformException catch (e) {
      final errorCode = PurchasesErrorHelper.getErrorCode(e);
      if (errorCode == PurchasesErrorCode.purchaseCancelledError) {
        debugPrint('⚠️ Compra cancelada pelo usuário');
      } else {
        debugPrint('❌ Erro na compra: $e');
      }
      return false;
    } catch (e) {
      debugPrint('❌ Erro inesperado na compra: $e');
      return false;
    }
  }

  Future<bool> restorePurchases() async {
    try {
      final customerInfo = await Purchases.restorePurchases();
      _customerInfo = customerInfo;
      final isActive = customerInfo.entitlements.active.containsKey(_entitlementId);
      await _writePremiumCache(isActive);
      return isActive;
    } catch (e) {
      debugPrint('❌ Erro ao restaurar compras: $e');
      return false;
    }
  }

  Future<void> showCustomerCenter(BuildContext context) async {
    try {
      await _loadCustomerInfo();
      final manageUrl = _customerInfo?.managementURL;

      if (manageUrl != null && manageUrl.isNotEmpty && context.mounted) {
        showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            backgroundColor: const Color(0xFF16213E),
            title: const Text('Gerenciar Assinatura', style: TextStyle(color: Colors.white)),
            content: const Text(
              'Para alterar ou cancelar seu plano, acesse o menu de "Assinaturas" na sua loja de aplicativos.',
              style: TextStyle(color: Colors.white70),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Entendi', style: TextStyle(color: Colors.amber)),
              ),
            ],
          ),
        );
      }
    } catch (e) {
      debugPrint('❌ Erro ao abrir gerenciador: $e');
    }
  }

  Future<void> _loadCustomerInfo() async {
    try {
      _customerInfo = await Purchases.getCustomerInfo();
    } catch (e) {
      debugPrint('❌ Erro CustomerInfo: $e');
    }
  }

  Future<void> _loadOfferings() async {
    try {
      _offerings = await Purchases.getOfferings();
    } catch (e) {
      debugPrint('❌ Erro Offerings: $e');
    }
  }

  Future<Offerings?> getOfferings() async {
    await _loadOfferings();
    return _offerings;
  }

  Future<void> _syncPremiumStatus() async {
    if (_customerInfo == null) return;
    final isActive = _customerInfo!.entitlements.active.containsKey(_entitlementId);
    await _writePremiumCache(isActive);
  }

  Future<void> _writePremiumCache(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_premiumKey, value);
  }
}
