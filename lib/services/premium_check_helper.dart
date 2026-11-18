import 'package:flutter/material.dart';
import 'package:odu_journey/services/revenuecat_service.dart';
import 'package:odu_journey/ui/screens/subscription/subscription_screen.dart';

class PremiumCheckHelper {
  static final RevenueCatService _revenueCat = RevenueCatService();

  static Future<bool> checkAccessAndNavigate(
    BuildContext context,
    Widget targetScreen, {
    String? lockedMessage,
  }) async {
    try {
      final isPremium = await _revenueCat.isPremium();
      if (!context.mounted) return false;

      if (isPremium) {
        await Navigator.push(context, MaterialPageRoute(builder: (_) => targetScreen));
        return true;
      } else {
        _showPremiumDialog(context, targetScreen: targetScreen, customMessage: lockedMessage);
        return false;
      }
    } catch (e) {
      debugPrint('❌ Erro ao verificar acesso: $e');
      return false;
    }
  }

  static void _showPremiumDialog(
    BuildContext context, {
    Widget? targetScreen,
    Function()? onSuccessAction,
    String? customMessage,
  }) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Recurso Premium'),
        content: Text(customMessage ?? 'Este recurso está disponível apenas para membros premium.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.amber, foregroundColor: Colors.black),
            onPressed: () {
              Navigator.pop(context); 
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => SubscriptionScreen(
                    onPurchaseSuccess: () {
                      if (targetScreen != null) {
                        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => targetScreen));
                      } else if (onSuccessAction != null) {
                        Navigator.pop(context); 
                        onSuccessAction(); 
                      }
                    },
                  ),
                ),
              );
            },
            child: const Text('Ser Premium'),
          ),
        ],
      ),
    );
  }

  static Future<void> checkAndExecute(
    BuildContext context, {
    required Future<void> Function() action,
    String? lockedMessage,
  }) async {
    try {
      final isPremium = await _revenueCat.isPremium();
      if (!context.mounted) return;

      if (isPremium) {
        await action();
      } else {
        _showPremiumDialog(context, onSuccessAction: action, customMessage: lockedMessage);
      }
    } catch (e) {
      debugPrint('❌ Erro: $e');
    }
  }

  static Widget buildPremiumLock({
    required bool isPremium,
    required Widget child,
    double lockSize = 30,
  }) {
    if (isPremium) return child;
    return Stack(
      alignment: Alignment.center,
      children: [
        Opacity(opacity: 0.5, child: child),
        Container(
          decoration: BoxDecoration(color: Colors.black.withOpacity(0.6), shape: BoxShape.circle),
          child: Icon(Icons.lock, color: Colors.amber, size: lockSize),
        ),
      ],
    );
  }

  static void showPremiumSnackBar(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('Este recurso é exclusivo para membros premium'),
        backgroundColor: Colors.amber.shade700,
        action: SnackBarAction(
          label: 'Assinar',
          onPressed: () {
            Navigator.push(context, MaterialPageRoute(builder: (_) => const SubscriptionScreen()));
          },
        ),
      ),
    );
  }
}