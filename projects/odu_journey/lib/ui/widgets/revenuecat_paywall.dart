import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:purchases_flutter/purchases_flutter.dart';
import 'package:odu_journey/services/revenuecat_service.dart';

class RevenueCatPaywall extends StatefulWidget {
  final VoidCallback? onPurchaseSuccess;
  final VoidCallback? onCancel;
  
  const RevenueCatPaywall({
    super.key,
    this.onPurchaseSuccess,
    this.onCancel,
  });

  @override
  State<RevenueCatPaywall> createState() => _RevenueCatPaywallState();
}

class _RevenueCatPaywallState extends State<RevenueCatPaywall> {
  final RevenueCatService _revenueCat = RevenueCatService();
  bool _isLoading = true;
  List<Package> _packages = [];
  String? _selectedPackageId;
  bool _isPurchasing = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadPackages();
  }

  Future<void> _loadPackages() async {
    try {
      final packages = await _revenueCat.getAllPackages();
      
      if (mounted) {
        setState(() {
          _isLoading = false;

          // Anual primeiro, depois demais planos
          _packages = [...packages]
            ..sort((a, b) {
              if (a.identifier == '\$rc_annual') return -1;
              if (b.identifier == '\$rc_annual') return 1;
              return 0;
            });

          // Selecionar o plano anual por padrão
          if (_packages.isNotEmpty) {
            final annual = _packages.firstWhere(
              (p) => p.identifier == '\$rc_annual',
              orElse: () => _packages.first,
            );
            _selectedPackageId = annual.identifier;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Erro ao carregar pacotes: $e';
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _handlePurchase(Package package) async {
    setState(() {
      _isPurchasing = true;
      _errorMessage = null;
    });

    try {
      final success = await _revenueCat.purchasePackage(package);
      
      if (mounted) {
        if (success) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('✅ Assinatura ativada com sucesso!'),
              backgroundColor: Colors.green,
              duration: Duration(seconds: 2),
            ),
          );
          
          widget.onPurchaseSuccess?.call();
          
          // Fechar o paywall após sucesso
          if (mounted) {
            Navigator.pop(context);
          }
        } else {
          setState(() {
            _errorMessage = 'Erro ao processar a compra. Tente novamente.';
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Erro: $e';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _isPurchasing = false;
        });
      }
    }
  }

  Future<void> _restorePurchases() async {
    setState(() {
      _isPurchasing = true;
      _errorMessage = null;
    });

    try {
      final success = await _revenueCat.restorePurchases();
      
      if (mounted) {
        if (success) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('✅ Compras restauradas com sucesso!'),
              backgroundColor: Colors.green,
              duration: Duration(seconds: 2),
            ),
          );
          
          widget.onPurchaseSuccess?.call();
          
          if (mounted) {
            Navigator.pop(context);
          }
        } else {
          setState(() {
            _errorMessage = 'Nenhuma compra anterior encontrada.';
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Erro ao restaurar compras: $e';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _isPurchasing = false;
        });
      }
    }
  }

  String _planName(String identifier) {
    if (identifier == '\$rc_annual') return 'Plano Anual';
    if (identifier == '\$rc_monthly') return 'Plano Mensal';
    return identifier;
  }

  String _monthlyEquivalent(StoreProduct product) {
    final formatter = NumberFormat.simpleCurrency(
      name: product.currencyCode,
      decimalDigits: 2,
    );
    return formatter.format(product.price / 12);
  }

  // Retorna "37%" se conseguir calcular; null caso contrário.
  String? _savingsPercent() {
    try {
      final monthly = _packages.firstWhere((p) => p.identifier == '\$rc_monthly');
      final annual = _packages.firstWhere((p) => p.identifier == '\$rc_annual');
      final monthlyCost = monthly.storeProduct.price;
      if (monthlyCost == 0) return null;
      final annualMonthlyCost = annual.storeProduct.price / 12;
      final pct = ((monthlyCost - annualMonthlyCost) / monthlyCost * 100).round();
      return '$pct%';
    } catch (_) {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // Fundo com gradiente
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
            child: _isLoading
                ? const Center(
                    child: CircularProgressIndicator(color: Colors.amber),
                  )
                : Column(
                    children: [
                      // Header com botão fechar
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Odù Journey Premium',
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.close, color: Colors.white),
                              onPressed: () {
                                widget.onCancel?.call();
                                Navigator.pop(context);
                              },
                            ),
                          ],
                        ),
                      ),
                      
                      // Conteúdo principal
                      Expanded(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            children: [
                              // Ícone Premium
                              const Icon(
                                Icons.workspace_premium,
                                size: 80,
                                color: Colors.amber,
                              ),
                              const SizedBox(height: 20),
                              
                              // Título
                              const Text(
                                'Desbloqueie Todo o Potencial',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white,
                                ),
                              ),
                              const SizedBox(height: 10),
                              
                              const Text(
                                'Acesso ilimitado a todos os 256 Odù e treinos sem restrições',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  fontSize: 14,
                                  color: Colors.white70,
                                ),
                              ),
                              const SizedBox(height: 30),
                              
                              // Lista de benefícios
                              _buildBenefit(Icons.check, 'Acesso ilimitado aos 256 Odù'),
                              _buildBenefit(Icons.check, 'Treinos sem limite diário'),
                              _buildBenefit(Icons.check, 'Sem anúncios'),
                              _buildBenefit(Icons.check, 'Suporte prioritário'),
                              _buildBenefit(Icons.check, 'Atualizações exclusivas'),
                              
                              const SizedBox(height: 30),
                              
                              // Pacotes disponíveis
                              if (_packages.isNotEmpty) ...[
                                const Text(
                                  'Escolha seu plano:',
                                  style: TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.white,
                                  ),
                                ),
                                const SizedBox(height: 16),
                                ..._packages.map((package) {
                                  final isSelected = _selectedPackageId == package.identifier;
                                  final isAnnual = package.identifier == '\$rc_annual';
                                  final product = package.storeProduct;

                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 20),
                                    child: Stack(
                                      clipBehavior: Clip.none,
                                      children: [
                                        GestureDetector(
                                          onTap: () => setState(() {
                                            _selectedPackageId = package.identifier;
                                          }),
                                          child: Container(
                                            padding: const EdgeInsets.all(16),
                                            decoration: BoxDecoration(
                                              border: Border.all(
                                                color: isAnnual
                                                    ? Colors.amber
                                                    : (isSelected ? Colors.amber : Colors.grey.shade700),
                                                width: isAnnual ? 2.5 : (isSelected ? 2 : 1),
                                              ),
                                              borderRadius: BorderRadius.circular(12),
                                              color: isAnnual
                                                  ? Colors.amber.withValues(alpha: 0.12)
                                                  : (isSelected
                                                      ? Colors.amber.withValues(alpha: 0.08)
                                                      : Colors.transparent),
                                            ),
                                            child: Row(
                                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                              children: [
                                                Column(
                                                  crossAxisAlignment: CrossAxisAlignment.start,
                                                  children: [
                                                    Text(
                                                      _planName(package.identifier),
                                                      style: const TextStyle(
                                                        fontSize: 15,
                                                        fontWeight: FontWeight.bold,
                                                        color: Colors.white,
                                                      ),
                                                    ),
                                                    if (isAnnual) ...[
                                                      const SizedBox(height: 4),
                                                      Text(
                                                        'apenas ${_monthlyEquivalent(product)}/mês',
                                                        style: const TextStyle(
                                                          fontSize: 13,
                                                          fontWeight: FontWeight.w500,
                                                          color: Colors.amber,
                                                        ),
                                                      ),
                                                      const SizedBox(height: 2),
                                                      Text(
                                                        'economize ${_savingsPercent() ?? 'mais de 20'}% vs. mensal',
                                                        style: TextStyle(
                                                          fontSize: 11,
                                                          color: Colors.amber.shade300,
                                                          fontStyle: FontStyle.italic,
                                                        ),
                                                      ),
                                                    ],
                                                  ],
                                                ),
                                                Text(
                                                  product.priceString,
                                                  style: const TextStyle(
                                                    fontSize: 18,
                                                    fontWeight: FontWeight.bold,
                                                    color: Colors.amber,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ),
                                        if (isAnnual)
                                          Positioned(
                                            top: -12,
                                            left: 16,
                                            child: Container(
                                              padding: const EdgeInsets.symmetric(
                                                horizontal: 10,
                                                vertical: 4,
                                              ),
                                              decoration: BoxDecoration(
                                                color: Colors.amber,
                                                borderRadius: BorderRadius.circular(20),
                                              ),
                                              child: const Text(
                                                'MAIS POPULAR',
                                                style: TextStyle(
                                                  fontSize: 10,
                                                  fontWeight: FontWeight.bold,
                                                  color: Colors.black,
                                                  letterSpacing: 0.8,
                                                ),
                                              ),
                                            ),
                                          ),
                                      ],
                                    ),
                                  );
                                }).toList(),
                              ],
                              
                              // Mensagem de erro (se houver)
                              if (_errorMessage != null) ...[
                                const SizedBox(height: 16),
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: Colors.red.withValues(alpha:0.1),
                                    border: Border.all(color: Colors.red),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    _errorMessage!,
                                    style: const TextStyle(
                                      color: Colors.red,
                                      fontSize: 12,
                                    ),
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                      
                      // Botões de ação
                      Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          children: [
                            // Botão de compra
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
                                onPressed: _isPurchasing || _selectedPackageId == null
                                    ? null
                                    : () {
                                        final selectedPackage = _packages.firstWhere(
                                          (p) => p.identifier == _selectedPackageId,
                                        );
                                        _handlePurchase(selectedPackage);
                                      },
                                child: _isPurchasing
                                    ? const SizedBox(
                                        height: 20,
                                        width: 20,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          valueColor: AlwaysStoppedAnimation<Color>(Colors.black),
                                        ),
                                      )
                                    : Text(
                                        _selectedPackageId == '\$rc_annual'
                                            ? 'COMEÇAR 7 DIAS GRÁTIS'
                                            : 'ASSINAR AGORA',
                                        style: const TextStyle(
                                          fontSize: 16,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                              ),
                            ),
                            
                            const SizedBox(height: 10),

                            // Micro-copy de confiança
                            Text(
                              _selectedPackageId == '\$rc_annual'
                                  ? 'Sem cobrança nos primeiros 7 dias · Cancele quando quiser'
                                  : 'Cancele quando quiser',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontSize: 11,
                                color: Colors.white54,
                              ),
                            ),

                            // Restaurar compras como link discreto
                            TextButton(
                              onPressed: _isPurchasing ? null : _restorePurchases,
                              child: const Text(
                                'Restaurar Compras',
                                style: TextStyle(
                                  fontSize: 13,
                                  color: Colors.white54,
                                  decoration: TextDecoration.underline,
                                  decorationColor: Colors.white54,
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

  Widget _buildBenefit(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Icon(icon, color: Colors.greenAccent, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(color: Colors.white, fontSize: 14),
            ),
          ),
        ],
      ),
    );
  }
}