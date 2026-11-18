import 'dart:math';
import 'package:flutter/material.dart';
import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/repository/odu_repository.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/ui/widgets/odu_symbol.dart';

class OduTrainingScreen extends StatefulWidget {
  final bool isMejiOnly; 
  final bool isFavoritesOnly; 
  final bool isQuizMode; 

  const OduTrainingScreen({
    super.key, 
    this.isMejiOnly = false,
    this.isFavoritesOnly = false,
    required this.isQuizMode,
  });

  @override
  State<OduTrainingScreen> createState() => _OduTrainingScreenState();
}

class _OduTrainingScreenState extends State<OduTrainingScreen> {
  final ProgressRepository _progressRepository = ProgressRepository();
  final OduRepository _oduRepository = OduRepository();
  final Random _random = Random();
  
  List<Odu> _odus = [];
  bool isLoading = true;

  static const int TOTAL_QUESTIONS = 10;
  int _currentQuestionIndex = 0;
  int _score = 0;
  
  Odu? _currentOdu;
  List<String> _quizOptions = []; 
  
  bool _answered = false;
  bool _isCorrect = false;
  String? _userChoice; 
  String _feedbackText = "";
  
  final TextEditingController _textController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    List<Odu> all = await _oduRepository.loadOdu();
    
    if (widget.isMejiOnly) {
      all = all.where((o) => o.id.endsWith('_meji') || o.right.toString() == o.left.toString()).toList();
    }
    
    if (widget.isFavoritesOnly) {
      final favs = await _progressRepository.getFavorites();
      all = all.where((o) => favs.contains(o.nome)).toList();
    }

    if (all.length < 4) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Mínimo de 4 Odùs necessários.")));
        Navigator.pop(context);
      }
      return;
    }

    all.shuffle();

    if (mounted) {
      setState(() {
        _odus = all;
        isLoading = false;
        _generateQuestion();
      });
    }
  }

  void _generateQuestion() {
    setState(() {
      _answered = false;
      _userChoice = null;
      _feedbackText = "";
      _textController.clear();
      
      if (_currentQuestionIndex < TOTAL_QUESTIONS) {
        _currentOdu = _odus[_currentQuestionIndex % _odus.length];
        if (widget.isQuizMode) {
          _generateQuizOptions();
        }
      } else {
        _finishTraining();
      }
    });
  }

  void _generateQuizOptions() {
    Set<String> options = {_currentOdu!.nome};
    int attempts = 0;
    while (options.length < 4 && attempts < 100) {
      final randomOdu = _odus[_random.nextInt(_odus.length)];
      options.add(randomOdu.nome);
      attempts++;
    }
    _quizOptions = options.toList();
    _quizOptions.shuffle();
  }

  String _normalize(String input) {
    if (input.isEmpty) return "";
    String text = input.toLowerCase().trim();
    text = text.replaceAll(RegExp(r'[áàâãä]'), 'a');
    text = text.replaceAll(RegExp(r'[éèêëẹ]'), 'e'); 
    text = text.replaceAll(RegExp(r'[íìîï]'), 'i');
    text = text.replaceAll(RegExp(r'[óòôõöọ]'), 'o'); 
    text = text.replaceAll(RegExp(r'[úùûüụ]'), 'u');
    text = text.replaceAll(RegExp(r'[ṣ]'), 's');     
    text = text.replaceAll(RegExp(r'[^a-z0-9]'), '');
    text = text.replaceAll('u', 'o'); 
    return text;
  }

  void _checkAnswer(String userAnswer) {
    if (_answered) return; 

    final correctNameNorm = _normalize(_currentOdu!.nome);
    final userAnsNorm = _normalize(userAnswer);

    bool hit = userAnsNorm == correctNameNorm;
    
    if (!hit && userAnsNorm.length >= 3) {
      if (correctNameNorm.startsWith(userAnsNorm)) {
        hit = true;
      }
    }

    if (hit) {
      _progressRepository.incrementScore(amount: 10);
    }

    setState(() {
      _answered = true;
      _userChoice = userAnswer; 
      _isCorrect = hit;
      
      if (hit) {
        _score++;
        _feedbackText = "Correto! É ${_currentOdu!.nome}";
      } else {
        _feedbackText = "Errado! A resposta era ${_currentOdu!.nome}";
      }
    });
  }

  void _nextQuestion() {
    setState(() {
      _currentQuestionIndex++;
      _generateQuestion();
    });
  }

  Future<void> _finishTraining() async {
    bool isPremium = await _progressRepository.isPremium();
    if (!isPremium) {
      await _progressRepository.incrementDailyView();
    }

    if (!mounted) return;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        title: const Text("Treino Concluído!"),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text("Você acertou $_score de $TOTAL_QUESTIONS."),
            const SizedBox(height: 10),
            Text("+ ${_score * 10} XP ganhos!", style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.deepPurple)),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context); 
              Navigator.pop(context); 
            },
            child: const Text("SAIR"),
          ),
        ],
      ),
    );
  }

  Color _getButtonColor(String option) {
    if (!_answered) return Colors.blue.shade50;
    if (option == _currentOdu!.nome) return Colors.green.shade300; 
    if (option == _userChoice && !_isCorrect) return Colors.red.shade300; 
    return Colors.grey.shade200; 
  }

  Color _getButtonTextColor(String option) {
    if (_answered) {
      if (option == _currentOdu!.nome || (option == _userChoice && !_isCorrect)) {
        return Colors.white;
      }
      return Colors.grey; 
    }
    return Colors.black87;
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) return const Scaffold(body: Center(child: CircularProgressIndicator()));

    return Scaffold(
      appBar: AppBar(title: Text("Treino: ${_currentQuestionIndex + 1}/$TOTAL_QUESTIONS")),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            LinearProgressIndicator(value: (_currentQuestionIndex) / TOTAL_QUESTIONS),
            const SizedBox(height: 30),
            Expanded(
              flex: 4,
              child: Center(
                child: SizedBox(
                  width: 160, height: 160,
                  // --- CORREÇÃO: SEM INVERSÃO ---
                  // Tudo alinhado: Right -> Right, Left -> Left.
                  child: OduSymbol(
                    right: _currentOdu!.right, 
                    left: _currentOdu!.left
                  ),
                ),
              ),
            ),
            const SizedBox(height: 30),
            
            if (widget.isQuizMode) ...[
              Expanded(
                flex: 6,
                child: ListView.separated(
                  itemCount: _quizOptions.length,
                  separatorBuilder: (_,__) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final option = _quizOptions[index];
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
              )
            ] else ...[
              TextField(
                controller: _textController,
                enabled: !_answered,
                decoration: InputDecoration(
                  labelText: "Digite o nome do Odù",
                  hintText: "Ex: Ogbe, Oyeku...",
                  border: const OutlineInputBorder(),
                  suffixIcon: IconButton(
                    icon: const Icon(Icons.send),
                    onPressed: _answered ? null : () => _checkAnswer(_textController.text),
                  )
                ),
                onSubmitted: (val) => _answered ? null : _checkAnswer(val),
              ),
              const Spacer(flex: 3),
            ],
            
            if (_answered)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: _isCorrect ? Colors.green.shade50 : Colors.red.shade50,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: _isCorrect ? Colors.green.shade200 : Colors.red.shade200)
                ),
                child: Column(
                  children: [
                    Text(_feedbackText, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: _isCorrect ? Colors.green.shade800 : Colors.red.shade800)),
                    const SizedBox(height: 12),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(backgroundColor: _isCorrect ? Colors.green : Colors.red, foregroundColor: Colors.white),
                      onPressed: _nextQuestion,
                      child: const Text("PRÓXIMO"),
                    )
                  ],
                ),
              )
          ],
        ),
      ),
    );
  }
}