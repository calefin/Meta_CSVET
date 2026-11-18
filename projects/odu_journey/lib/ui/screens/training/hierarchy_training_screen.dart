import 'dart:math';
import 'package:flutter/material.dart';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/repository/odu_repository.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/ui/widgets/odu_symbol.dart';
import 'package:odu_journey/utils/odu_hierarchy.dart';

class HierarchyTrainingScreen extends StatefulWidget {
  final bool isMejiOnly; 

  const HierarchyTrainingScreen({super.key, required this.isMejiOnly});

  @override
  State<HierarchyTrainingScreen> createState() => _HierarchyTrainingScreenState();
}

class _HierarchyTrainingScreenState extends State<HierarchyTrainingScreen> {
  final OduRepository _oduRepo = OduRepository();
  final ProgressRepository _progressRepo = ProgressRepository();
  final Random _rnd = Random();

  List<Odu> _allOdus = [];
  Odu? _oduA;
  Odu? _oduB;
  
  int _score = 0;
  int _rounds = 0;
  static const int MAX_ROUNDS = 10;
  
  bool _answered = false;
  String? _chosenId;
  bool _isCorrect = false;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    final list = await _oduRepo.loadOdu();
    if (widget.isMejiOnly) {
      _allOdus = list.where((o) => o.right.toString() == o.left.toString()).toList();
    } else {
      _allOdus = list;
    }
    _generateRound();
  }

  void _generateRound() {
    if (_rounds >= MAX_ROUNDS) {
      _finishGame();
      return;
    }

    setState(() {
      _answered = false;
      _chosenId = null;
      _oduA = _allOdus[_rnd.nextInt(_allOdus.length)];
      do {
        _oduB = _allOdus[_rnd.nextInt(_allOdus.length)];
      } while (_oduB == _oduA);
    });
  }

  void _checkAnswer(Odu chosen) {
    if (_answered) return;

    bool aIsSenior = OduHierarchy.isSenior(_oduA!, _oduB!);
    Odu correctOdu = aIsSenior ? _oduA! : _oduB!;

    bool hit = (chosen == correctOdu);

    if (hit) {
      // --- ADICIONADO XP (Movido para fora do setState) ---
      _progressRepo.incrementScore(amount: 10);
    }

    setState(() {
      _answered = true;
      _chosenId = chosen.id;
      _isCorrect = hit;
      if (hit) {
        _score++;
      }
      _rounds++;
    });

    Future.delayed(const Duration(milliseconds: 1500), _generateRound);
  }

  Future<void> _finishGame() async {
    bool isPremium = await _progressRepo.isPremium();
    if (!isPremium) {
      await _progressRepo.incrementDailyView();
    }

    if (!mounted) return;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        title: const Text("Treino Concluído"),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
             Text("Você acertou $_score de $MAX_ROUNDS."),
             const SizedBox(height: 10),
             Text("+ ${_score * 10} XP ganhos!", style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.deepPurple))
          ],
        ),
        actions: [
          TextButton(onPressed: () {
            Navigator.pop(context);
            Navigator.pop(context);
          }, child: const Text("SAIR"))
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_oduA == null) return const Scaffold(body: Center(child: CircularProgressIndicator()));

    return Scaffold(
      appBar: AppBar(title: Text(widget.isMejiOnly ? "Quem é o Mais Velho?" : "Hierarquia (256)")),
      body: Column(
        children: [
          LinearProgressIndicator(value: _rounds / MAX_ROUNDS),
          const SizedBox(height: 20),
          const Text("Toque no Odù mais velho", style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const Spacer(),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              _buildCard(_oduA!),
              const Text("VS", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
              _buildCard(_oduB!),
            ],
          ),
          const Spacer(),
          if (_answered)
            Text(
              _isCorrect ? "Correto!" : "Errado!",
              style: TextStyle(
                fontSize: 22, 
                fontWeight: FontWeight.bold, 
                color: _isCorrect ? Colors.green : Colors.red
              ),
            ),
          const SizedBox(height: 40),
        ],
      ),
    );
  }

  Widget _buildCard(Odu odu) {
    Color borderColor = Colors.transparent;
    double borderWidth = 0;

    if (_answered) {
      bool isThisSenior = OduHierarchy.isSenior(odu, (odu == _oduA ? _oduB! : _oduA!));
      
      if (isThisSenior) {
        borderColor = Colors.green; 
        borderWidth = 4;
      } else if (_chosenId == odu.id && !_isCorrect) {
        borderColor = Colors.red;
        borderWidth = 4;
      }
    }

    return GestureDetector(
      onTap: () => _checkAnswer(odu),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: borderColor, width: borderWidth == 0 ? 0.5 : borderWidth),
          boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 8)]
        ),
        child: Column(
          children: [
            SizedBox(
              width: 100, height: 100,
              child: OduSymbol(right: odu.right, left: odu.left),
            ),
            const SizedBox(height: 10),
            Text(odu.nome, style: const TextStyle(fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }
}