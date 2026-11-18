import 'package:flutter/material.dart';
import '../models/app_models.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';

class EditKeyScreen extends StatefulWidget {
  final String quizId;
  const EditKeyScreen({Key? key, required this.quizId}) : super(key: key);

  @override
  State<EditKeyScreen> createState() => _EditKeyScreenState();
}

class _EditKeyScreenState extends State<EditKeyScreen> {
  final AppState _appState = AppState();
  final _globalPointsController = TextEditingController();
  late List<QuestionKey> _localKeys;
  late double _totalPoints;

  @override
  void initState() {
    super.initState();
    final quiz = _appState.quizzes.firstWhere((q) => q.id == widget.quizId);
    // Clone keys for local editing
    _localKeys = quiz.questionKeys.map((k) {
      return QuestionKey(
        questionNumber: k.questionNumber,
        correctOption: k.correctOption,
        points: k.points,
        isCancelled: k.isCancelled,
        redistributePoints: k.redistributePoints,
      );
    }).toList();
    _totalPoints = quiz.totalPoints;
    _globalPointsController.text = (_totalPoints / quiz.questionsCount).toStringAsFixed(2);
  }

  @override
  void dispose() {
    _globalPointsController.dispose();
    super.dispose();
  }

  void _applyGlobalPoints() {
    final double? val = double.tryParse(_globalPointsController.text);
    if (val != null && val >= 0) {
      setState(() {
        for (var key in _localKeys) {
          key.points = val;
        }
        _recalculateTotalPoints();
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Atribuído $val pontos globalmente a todas as questões!'),
          backgroundColor: AppTheme.success,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Valor global inválido'),
          backgroundColor: AppTheme.error,
        ),
      );
    }
  }

  void _recalculateTotalPoints() {
    double sum = 0.0;
    for (var k in _localKeys) {
      sum += k.points;
    }
    _totalPoints = double.parse(sum.toStringAsFixed(2));
  }

  void _saveKeys() async {
    // Save each key to AppState
    final quiz = _appState.quizzes.firstWhere((q) => q.id == widget.quizId);
    
    // Save list to database via app state
    final updatedQuiz = quiz.copyWith(
      questionKeys: _localKeys,
      totalPoints: _totalPoints,
    );
    await _appState.updateQuiz(updatedQuiz);

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Gabarito e pesos atualizados com sucesso!'),
          backgroundColor: AppTheme.success,
        ),
      );
      Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    final options = ['A', 'B', 'C', 'D', 'E'];

    return Scaffold(
      appBar: AppBar(
        title: const Text('Editar Gabarito'),
        actions: [
          IconButton(
            icon: const Icon(Icons.save, color: AppTheme.success),
            onPressed: _saveKeys,
          ),
        ],
      ),
      body: Column(
        children: [
          // Global configuration panel
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.surface,
              border: Border(bottom: BorderSide(color: Colors.white.withOpacity(0.08))),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Pontuação Total:',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                    Text(
                      '$_totalPoints pontos',
                      style: const TextStyle(color: AppTheme.secondary, fontWeight: FontWeight.bold, fontSize: 18),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _globalPointsController,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(
                          labelText: 'Pontos Globais (por questão)',
                          hintText: 'Ex: 1.0',
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    SizedBox(
                      height: 50,
                      child: ElevatedButton(
                        onPressed: _applyGlobalPoints,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.primary,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: const Text('Aplicar a Todas', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          
          // Question Keys List
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _localKeys.length,
              itemBuilder: (context, index) {
                final key = _localKeys[index];
                
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  color: key.isCancelled ? AppTheme.warning.withOpacity(0.05) : AppTheme.cardBg,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: BorderSide(
                      color: key.isCancelled 
                          ? AppTheme.warning.withOpacity(0.3) 
                          : Colors.white.withOpacity(0.03)
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Questão ${key.questionNumber}',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: key.isCancelled ? AppTheme.warning : AppTheme.textPrimary,
                              ),
                            ),
                            // Points per question input
                            SizedBox(
                              width: 100,
                              height: 38,
                              child: TextFormField(
                                initialValue: key.points.toString(),
                                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                textAlign: TextAlign.center,
                                decoration: const InputDecoration(
                                  contentPadding: EdgeInsets.zero,
                                  suffixText: 'pt',
                                ),
                                onChanged: (val) {
                                  final double? pts = double.tryParse(val);
                                  if (pts != null && pts >= 0) {
                                    key.points = pts;
                                    _recalculateTotalPoints();
                                  }
                                },
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        
                        // Options select Row
                        if (!key.isCancelled)
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: options.map((opt) {
                              final isSelected = key.correctOption == opt;
                              return GestureDetector(
                                onTap: () {
                                  setState(() {
                                    key.correctOption = opt;
                                  });
                                },
                                child: Container(
                                  width: 44,
                                  height: 44,
                                  alignment: Alignment.center,
                                  decoration: BoxDecoration(
                                    color: isSelected ? AppTheme.primary : AppTheme.surface,
                                    shape: BoxShape.circle,
                                    border: Border.all(
                                      color: isSelected ? Colors.transparent : Colors.white10,
                                      width: 1,
                                    ),
                                  ),
                                  child: Text(
                                    opt,
                                    style: TextStyle(
                                      color: isSelected ? Colors.white : AppTheme.textSecondary,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 16,
                                    ),
                                  ),
                                ),
                              );
                            }).toList(),
                          )
                        else
                          Container(
                            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
                            decoration: BoxDecoration(
                              color: AppTheme.warning.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Row(
                              children: [
                                Icon(Icons.warning_amber_rounded, color: AppTheme.warning, size: 20),
                                SizedBox(width: 8),
                                Text(
                                  'Questão Anulada',
                                  style: TextStyle(color: AppTheme.warning, fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                          ),
                        const SizedBox(height: 12),
                        const Divider(height: 1, color: Colors.white10),
                        const SizedBox(height: 12),
                        
                        // Cancel/Nullify switch
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Row(
                              children: [
                                Icon(Icons.cancel_presentation_outlined, color: AppTheme.textSecondary, size: 20),
                                SizedBox(width: 8),
                                Text('Anular Questão', style: TextStyle(color: AppTheme.textSecondary)),
                              ],
                            ),
                            Switch(
                              value: key.isCancelled,
                              activeColor: AppTheme.warning,
                              onChanged: (val) {
                                setState(() {
                                  key.isCancelled = val;
                                  if (val) {
                                    key.correctOption = 'N'; // cancelled code
                                  } else {
                                    key.correctOption = 'A';
                                  }
                                });
                              },
                            ),
                          ],
                        ),

                        // Redistribution settings if cancelled
                        if (key.isCancelled)
                          Padding(
                            padding: const EdgeInsets.only(top: 8.0),
                            child: Row(
                              children: [
                                const Expanded(
                                  child: Text(
                                    'Tratamento de Pontos:',
                                    style: TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                                  ),
                                ),
                                ChoiceChip(
                                  label: const Text('Dar ponto a todos', style: TextStyle(fontSize: 12)),
                                  selected: !key.redistributePoints,
                                  selectedColor: AppTheme.primary,
                                  onSelected: (selected) {
                                    if (selected) {
                                      setState(() {
                                        key.redistributePoints = false;
                                      });
                                    }
                                  },
                                ),
                                const SizedBox(width: 8),
                                ChoiceChip(
                                  label: const Text('Redistribuir', style: TextStyle(fontSize: 12)),
                                  selected: key.redistributePoints,
                                  selectedColor: AppTheme.warning,
                                  onSelected: (selected) {
                                    if (selected) {
                                      setState(() {
                                        key.redistributePoints = true;
                                      });
                                    }
                                  },
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
