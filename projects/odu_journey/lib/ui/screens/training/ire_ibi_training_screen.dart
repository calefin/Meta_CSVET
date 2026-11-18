import 'dart:math';
import 'package:flutter/material.dart';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/repository/odu_repository.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/ui/widgets/odu_symbol.dart';
import 'package:odu_journey/utils/odu_hierarchy.dart';

class IreIbiTrainingScreen extends StatefulWidget {
  const IreIbiTrainingScreen({super.key});

  @override
  State<IreIbiTrainingScreen> createState() => _IreIbiTrainingScreenState();
}

class _IreIbiTrainingScreenState extends State<IreIbiTrainingScreen> {
  final OduRepository _oduRepo = OduRepository();
  final ProgressRepository _progressRepo = ProgressRepository();
  final Random _rnd = Random();

  List<Odu> _allOdus = [];
  Odu? _main;   
  Odu? _right;  
  Odu? _left;   

  int _rounds = 0;
  int _score = 0;
  static const int MAX_ROUNDS = 10;
  
  bool _answered = false;
  bool _isIreResult = false; 
  bool _userGuessedIre = false; 

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    _allOdus = await _oduRepo.loadOdu();
    _generateRound();
  }

  void _generateRound() {
    if (_rounds >= MAX_ROUNDS) {
      _finishGame();
      return;
    }

    setState(() {
      _answered = false;
      _main = _allOdus[_rnd.nextInt(_allOdus.length)];
      _right = _allOdus[_rnd.nextInt(_allOdus.length)];
      _left = _allOdus[_rnd.nextInt(_allOdus.length)];
      
      _isIreResult = OduHierarchy.isIre(_main!, _right!, _left!);
    });
  }

  void _checkAnswer(bool userSaidIre) {
    if (_answered) return;

    bool hit = (userSaidIre == _isIreResult);

    if (hit) {
      // --- ADICIONADO XP EXTRA (DIFÍCIL) (Movido para fora do setState) ---
      _progressRepo.incrementScore(amount: 15);
    }

    setState(() {
      _answered = true;
      _userGuessedIre = userSaidIre;
      if (hit) {
        _score++;
      }
      _rounds++;
    });
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
             Text("+ ${_score * 15} XP ganhos!", style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.deepPurple))
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
    if (_main == null) return const Scaffold(body: Center(child: CircularProgressIndicator()));

    return Scaffold(
      appBar: AppBar(title: const Text("Iré ou Ibi?")),
      body: Column(
        children: [
          LinearProgressIndicator(value: _rounds / MAX_ROUNDS),
          const SizedBox(height: 10),
          
          Expanded(
            flex: 4,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Text("1. Consulta", style: TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 5),
                _buildBigCard(_main!),
              ],
            ),
          ),
          
          const Divider(),

          Expanded(
            flex: 3,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                Column(
                  children: [
                    const Text("2. Direita", style: TextStyle(fontSize: 12)),
                    _buildSmallCard(_right!),
                  ],
                ),
                const Icon(Icons.compare_arrows, size: 30, color: Colors.grey),
                Column(
                  children: [
                    const Text("3. Esquerda", style: TextStyle(fontSize: 12)),
                    _buildSmallCard(_left!),
                  ],
                ),
              ],
            ),
          ),

          if (!_answered)
            Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  Expanded(
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(backgroundColor: Colors.green, padding: const EdgeInsets.all(20), foregroundColor: Colors.white),
                      onPressed: () => _checkAnswer(true), 
                      child: const Text("IRÉ", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                    ),
                  ),
                  const SizedBox(width: 20),
                  Expanded(
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(backgroundColor: Colors.red, padding: const EdgeInsets.all(20), foregroundColor: Colors.white),
                      onPressed: () => _checkAnswer(false), 
                      child: const Text("IBI", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                    ),
                  ),
                ],
              ),
            )
          else
            _buildFeedback(),
            
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _buildFeedback() {
    bool hit = (_userGuessedIre == _isIreResult);
    return Container(
      padding: const EdgeInsets.all(20),
      color: hit ? Colors.green.shade50 : Colors.red.shade50,
      width: double.infinity,
      child: Column(
        children: [
          Text(
            hit ? "Correto! É ${_isIreResult ? 'IRÉ' : 'IBI'}" : "Errado!",
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: hit ? Colors.green : Colors.red),
          ),
          const SizedBox(height: 5),
          if (OduHierarchy.getLegSeniority(_main!.right) == 7)
            const Text("Motivo: Odu de consulta é Obara (Iré Próprio).", style: TextStyle(fontStyle: FontStyle.italic))
          else
            Text(
              "Motivo: ${_isIreResult ? 'Direita' : 'Esquerda'} é mais velho.",
              style: const TextStyle(fontStyle: FontStyle.italic),
            ),
          const SizedBox(height: 10),
          ElevatedButton(onPressed: _generateRound, child: const Text("PRÓXIMO"))
        ],
      ),
    );
  }

  Widget _buildBigCard(Odu odu) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.deepPurple, width: 2),
        borderRadius: BorderRadius.circular(10)
      ),
      child: SizedBox(width: 80, height: 80, child: OduSymbol(right: odu.right, left: odu.left)),
    );
  }

  Widget _buildSmallCard(Odu odu) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(8.0),
        child: Column(
          children: [
            SizedBox(width: 50, height: 50, child: OduSymbol(right: odu.right, left: odu.left)),
            const SizedBox(height: 5),
            Text(odu.nome, style: const TextStyle(fontSize: 10)),
          ],
        ),
      ),
    );
  }
}