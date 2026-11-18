import 'dart:async';
import 'package:flutter/material.dart';
import 'package:odu_journey/ui/widgets/revenuecat_paywall.dart';
import 'package:odu_journey/services/revenuecat_service.dart';

class SubscriptionScreen extends StatefulWidget {
  final VoidCallback? onPurchaseSuccess;
  
  const SubscriptionScreen({
    super.key,
    this.onPurchaseSuccess,
  });

  @override
  State<SubscriptionScreen> createState() => _SubscriptionScreenState();
}

class _SubscriptionScreenState extends State<SubscriptionScreen> {
  final RevenueCatService _revenueCat = RevenueCatService();
  bool _isPremium = false;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _checkPremiumStatus();
  }

  Future<void> _checkPremiumStatus() async {
    try {
      final isPremium = await _revenueCat.isPremium();
      
      if (mounted) {
        setState(() {
          _isPremium = isPremium;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  void _openPaywall() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RevenueCatPaywall(
          onPurchaseSuccess: () {
            _checkPremiumStatus();
            widget.onPurchaseSuccess?.call();
          },
        ),
      ),
    );
  }

  Future<void> _openCustomerCenter() async {
    await _revenueCat.showCustomerCenter(context);
    _checkPremiumStatus();
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        body: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [Color(0xFF1A1A2E), Color(0xFF16213E)],
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
            ),
          ),
          child: const Center(
            child: CircularProgressIndicator(color: Colors.amber),
          ),
        ),
      );
    }

    // Se o usuário já é premium, mostrar a tela de gerenciamento
    if (_isPremium) {
      return Scaffold(
        body: Stack(
          children: [
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xFF1A1A2E), Color(0xFF16213E)],
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                ),
              ),
            ),
            SafeArea(
              child: Column(
                children: [
                  Align(
                    alignment: Alignment.topLeft,
                    child: IconButton(
                      icon: const Icon(Icons.close, color: Colors.white),
                      onPressed: () => Navigator.pop(context),
                    ),
                  ),
                  const Spacer(),
                  const Icon(Icons.check_circle, size: 100, color: Colors.greenAccent),
                  const SizedBox(height: 20),
                  const Text(
                    "Você é Premium!",
                    style: TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 40),
                    child: Text(
                      "Aproveite acesso ilimitado a todos os 256 Odù e treinos sem restrições.",
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white70, fontSize: 16),
                    ),
                  ),
                  const Spacer(),
                  Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      children: [
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.amber,
                              foregroundColor: Colors.black,
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            onPressed: _openCustomerCenter,
                            child: const Text(
                              'Gerenciar Assinatura',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          child: OutlinedButton(
                            style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.white,
                              side: const BorderSide(color: Colors.white54),
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            onPressed: () => Navigator.pop(context),
                            child: const Text(
                              'Voltar',
                              style: TextStyle(fontSize: 14),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    // Se o usuário não é premium, mostrar o paywall
    return RevenueCatPaywall(
      onPurchaseSuccess: () {
        _checkPremiumStatus();
        widget.onPurchaseSuccess?.call();
      },
      onCancel: () => Navigator.pop(context),
    );
  }
}