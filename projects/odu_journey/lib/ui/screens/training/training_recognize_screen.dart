import 'dart:async';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/repository/odu_repository.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/services/revenuecat_service.dart';
import 'package:odu_journey/ui/widgets/odu_symbol.dart';

class TrainingRecognizeScreen extends StatefulWidget {
  final bool isMejiOnly; 

  const TrainingRecognizeScreen({super.key, required this.isMejiOnly});

  @override
  State<TrainingRecognizeScreen> createState() => _TrainingRecognizeScreenState();
}

class _TrainingRecognizeScreenState extends State<TrainingRecognizeScreen> {
  final OduRepository _oduRepo = OduRepository();
  final ProgressRepository _progressRepo = ProgressRepository();
  final Random _rnd = Random();

  List<Odu> _odus = [];
  bool _isLoading = true;

  static const int TOTAL_QUESTIONS = 10;
  int _currentQuestionIndex = 0;
  int _score = 0;

  Odu? _currentOdu;
  List<String> _options = [];
  
  bool _answered = false;
  String? _selectedOption;
  bool _isCorrect = false;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    List<Odu> all = await _oduRepo.loadOdu();
    if (widget.isMejiOnly) {
      all = all.where((o) => o.right.toString() == o.left.toString()).toList();
    }
    if (all.length < 4) {
      if (mounted) Navigator.pop(context);
      return;
    }
    all.shuffle();
    if (mounted) {
      setState(() {
        _odus = all;
        _isLoading = false;
        _generateQuestion();
      });
    }
  }

  void _generateQuestion() {
    setState(() {
      _answered = false;
      _selectedOption = null;
      if (_currentQuestionIndex < TOTAL_QUESTIONS) {
        _currentOdu = _odus[_currentQuestionIndex % _odus.length];
        _generateOptions();
      } else {
        _finishGame();
      }
    });
  }

  void _generateOptions() {
    Set<String> opts = {_currentOdu!.nome};
    while (opts.length < 4) {
      final randomOdu = _odus[_rnd.nextInt(_odus.length)];
      opts.add(randomOdu.nome);
    }
    _options = opts.toList();
    _options.shuffle();
  }

  void _checkAnswer(String selected) {
    if (_answered) return;
    bool hit = (selected == _currentOdu!.nome);
    if (hit) _progressRepo.incrementScore(amount: 10);
    setState(() {
      _answered = true;
      _selectedOption = selected;
      _isCorrect = hit;
      if (hit) _score++;
    });
    Timer(const Duration(milliseconds: 1500), () {
      if (mounted) {
        setState(() {
          _currentQuestionIndex++;
          _generateQuestion();
        });
      }
    });
  }

  Future<void> _finishGame() async {
    // CORREÇÃO: Usando RevenueCatService direto
    bool isPremium = await RevenueCatService().isPremium();
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
            Text("Você acertou $_score de $TOTAL_QUESTIONS."),
            const SizedBox(height: 10),
            Text("+ ${_score * 10} XP", style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.deepPurple)),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              Navigator.pop(context); 
            },
            child: const Text("SAIR"),
          )
        ],
      ),
    );
  }

  Color _getButtonColor(String option) {
    if (!_answered) return Colors.blue.shade50;
    if (option == _currentOdu!.nome) return Colors.green.shade300; 
    if (option == _selectedOption && !_isCorrect) return Colors.red.shade300; 
    return Colors.grey.shade200;
  }

  Color _getButtonTextColor(String option) {
    if (_answered) {
      if (option == _currentOdu!.nome || (option == _selectedOption && !_isCorrect)) return Colors.white;
      return Colors.grey;
    }
    return Colors.black87;
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Scaffold(body: Center(child: CircularProgressIndicator()));

    return Scaffold(
      appBar: AppBar(title: Text("Reconhecimento (${_currentQuestionIndex + 1}/$TOTAL_QUESTIONS)")),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            LinearProgressIndicator(value: _currentQuestionIndex / TOTAL_QUESTIONS),
            const SizedBox(height: 30),
            Expanded(
              flex: 4,
              child: Center(
                child: SizedBox(
                  width: 160, height: 160,
                  child: OduSymbol(right: _currentOdu!.right, left: _currentOdu!.left),
                ),
              ),
            ),
            const SizedBox(height: 20),
            Expanded(
              flex: 5,
              child: ListView.separated(
                itemCount: _options.length,
                separatorBuilder: (_,__) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  final option = _options[index];
                  return SizedBox(
                    height: 55,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _getButtonColor(option),
                        foregroundColor: _getButtonTextColor(option),
                        elevation: _answered ? 0 : 2,
                      ),
                      onPressed: _answered ? () {} : () => _checkAnswer(option),
                      child: Text(option, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}