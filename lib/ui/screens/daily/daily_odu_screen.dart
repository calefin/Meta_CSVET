// lib/ui/screens/daily_odu_screen.dart

import 'package:flutter/material.dart';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/repository/odu_repository.dart';
import 'package:odu_journey/ui/widgets/odu_symbol.dart';

class DailyOduScreen extends StatefulWidget {
  const DailyOduScreen({super.key});

  @override
  State<DailyOduScreen> createState() => _DailyOduScreenState();
}

class _DailyOduScreenState extends State<DailyOduScreen> {
  final OduRepository _repository = OduRepository();
  Odu? _oduDoDia;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _carregarOduDoDia();
  }

  Future<void> _carregarOduDoDia() async {
    final odu = await _repository.getOduOfTheDay();
    if (mounted) {
      setState(() {
        _oduDoDia = odu;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    // Formatação de data simples (Nativa do Dart)
    final now = DateTime.now();
    final String dataHoje = "${now.day}/${now.month}/${now.year}";

    return Scaffold(
      backgroundColor: Colors.amber.shade50,
      appBar: AppBar(
        title: const Text("Odù do Dia"),
        backgroundColor: Colors.amber.shade800,
        foregroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amber))
          : _oduDoDia == null
              ? const Center(child: Text("Não foi possível carregar o conselho."))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      // Data
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.amber.shade100,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          "Conselho para $dataHoje",
                          style: TextStyle(
                            color: Colors.amber.shade900,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      const SizedBox(height: 30),

                      // Símbolo
                      SizedBox(
                        width: 160,
                        height: 160,
                        child: OduSymbol(
                          right: _oduDoDia!.rightBits,
                          left: _oduDoDia!.leftBits,
                          color: Colors.amber.shade900,
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Nome
                      Text(
                        _oduDoDia!.nome,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.bold,
                          color: Colors.brown.shade800,
                        ),
                      ),
                      
                      const SizedBox(height: 30),

                      // Conselho
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(color: Colors.amber.withOpacity(0.2), blurRadius: 15, offset: const Offset(0, 5))
                          ],
                        ),
                        child: Column(
                          children: [
                            const Icon(Icons.format_quote, size: 40, color: Colors.amber),
                            const SizedBox(height: 10),
                            Text(
                              _oduDoDia!.descricao.isNotEmpty 
                                  ? _oduDoDia!.descricao 
                                  : "Reflita sobre os caminhos deste Odù hoje.",
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontSize: 18,
                                height: 1.5,
                                fontStyle: FontStyle.italic,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
}