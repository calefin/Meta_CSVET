import 'package:flutter/material.dart';
import '../models/app_models.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';

class ItemAnalysisScreen extends StatefulWidget {
  final String quizId;
  const ItemAnalysisScreen({Key? key, required this.quizId}) : super(key: key);

  @override
  State<ItemAnalysisScreen> createState() => _ItemAnalysisScreenState();
}

class _ItemAnalysisScreenState extends State<ItemAnalysisScreen> {
  final AppState _appState = AppState();
  List<CorrectedPaper> _papers = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    final list = await _appState.getCorrectedPapers(widget.quizId);
    setState(() {
      _papers = list;
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final quiz = _appState.quizzes.firstWhere((q) => q.id == widget.quizId);

    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Análise de Itens')),
        body: const Center(child: CircularProgressIndicator(color: AppTheme.primary)),
      );
    }

    if (_papers.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Análise de Itens')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.analytics_outlined, size: 64, color: AppTheme.textSecondary.withOpacity(0.5)),
                const SizedBox(height: 16),
                const Text(
                  'Nenhuma prova corrigida para calcular estatísticas.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppTheme.textSecondary, fontSize: 16),
                ),
              ],
            ),
          ),
        ),
      );
    }

    // Process statistics
    final int questionsCount = quiz.questionsCount;
    final int totalPapersCount = _papers.length;
    
    // Map of questionNumber -> count of correct answers
    final correctCounts = <int, int>{};
    // Map of questionNumber -> Map of option -> count
    final optionDistributions = <int, Map<String, int>>{};

    // Initialize map
    for (int i = 1; i <= questionsCount; i++) {
      correctCounts[i] = 0;
      optionDistributions[i] = {'A': 0, 'B': 0, 'C': 0, 'D': 0, 'E': 0, 'N': 0, '': 0};
    }

    // Populate counts
    for (var paper in _papers) {
      paper.answers.forEach((qNum, markedOption) {
        if (qNum > questionsCount) return;

        // Keep distribution
        final dist = optionDistributions[qNum]!;
        dist[markedOption] = (dist[markedOption] ?? 0) + 1;

        final key = quiz.questionKeys.firstWhere(
          (k) => k.questionNumber == qNum,
          orElse: () => QuestionKey(questionNumber: qNum, correctOption: 'A', points: 0.0),
        );

        if (key.isCancelled || markedOption == key.correctOption) {
          correctCounts[qNum] = (correctCounts[qNum] ?? 0) + 1;
        }
      });
    }

    // List of question rates
    final ratesList = <_QuestionRate>[];
    correctCounts.forEach((qNum, corrects) {
      final key = quiz.questionKeys.firstWhere(
        (k) => k.questionNumber == qNum,
        orElse: () => QuestionKey(questionNumber: qNum, correctOption: 'A', points: 0.0),
      );
      final rate = corrects / totalPapersCount;
      ratesList.add(_QuestionRate(qNum: qNum, rate: rate, isCancelled: key.isCancelled));
    });

    // Compute average grade
    double gradeSum = 0;
    // Score distribution count: [0-4.9], [5.0-6.9], [7.0-8.9], [9.0-10.0]
    int countCritical = 0;
    int countRegular = 0;
    int countGood = 0;
    int countExcellent = 0;

    for (var p in _papers) {
      gradeSum += p.grade;
      final percentGrade = (p.grade / quiz.totalPoints) * 10.0; // scale to 10
      if (percentGrade < 5.0) {
        countCritical++;
      } else if (percentGrade < 7.0) {
        countRegular++;
      } else if (percentGrade < 9.0) {
        countGood++;
      } else {
        countExcellent++;
      }
    }
    final avgGrade = gradeSum / totalPapersCount;

    // Filter cancelled out from easiest/hardest calculation
    final activeRates = ratesList.where((r) => !r.isCancelled).toList();
    
    // Sort to find easiest & hardest questions
    final sortedByDifficulty = List<_QuestionRate>.from(activeRates);
    sortedByDifficulty.sort((a, b) => a.rate.compareTo(b.rate)); // hardest first

    final hardestQuestions = sortedByDifficulty.take(3).toList();
    final easiestQuestions = sortedByDifficulty.reversed.take(3).toList();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Análise de Itens'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Section 1: Dashboard metrics
            _buildHighlightsCard(avgGrade, quiz.totalPoints, totalPapersCount),
            const SizedBox(height: 16),

            // Section 2: Pedagogical insights
            _buildPedagogicalInsightsCard(hardestQuestions, easiestQuestions),
            const SizedBox(height: 16),

            // Section 3: Grade distribution histogram
            _buildGradeDistributionCard(countCritical, countRegular, countGood, countExcellent, totalPapersCount),
            const SizedBox(height: 24),

            const Text(
              'Detalhamento por Questão',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
            ),
            const SizedBox(height: 6),
            const Text(
              'Escolhas dos alunos e taxas de acertos:',
              style: TextStyle(color: AppTheme.textSecondary, fontSize: 13),
            ),
            const SizedBox(height: 12),

            // Section 4: Question by question detailed analysis with option distribution graphs
            ListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: ratesList.length,
              itemBuilder: (context, index) {
                final rateItem = ratesList[index];
                final percent = rateItem.rate * 100;
                final key = quiz.questionKeys.firstWhere(
                  (k) => k.questionNumber == rateItem.qNum,
                  orElse: () => QuestionKey(questionNumber: rateItem.qNum, correctOption: 'A', points: 0.0),
                );

                return _buildQuestionDetailCard(rateItem, percent, key, optionDistributions[rateItem.qNum]!, totalPapersCount);
              },
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildHighlightsCard(double avg, double maxTotal, int totalExams) {
    final double classPercentage = (avg / maxTotal) * 10.0; // scale to 10
    final isPassing = classPercentage >= 6.0;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppTheme.cardBg,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white.withOpacity(0.05)),
      ),
      child: Row(
        children: [
          // Circular Progress Indicator for Class Average
          Stack(
            alignment: Alignment.center,
            children: [
              SizedBox(
                width: 76,
                height: 76,
                child: CircularProgressIndicator(
                  value: avg / maxTotal,
                  color: isPassing ? AppTheme.success : AppTheme.error,
                  backgroundColor: Colors.white12,
                  strokeWidth: 8,
                ),
              ),
              Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    avg.toStringAsFixed(1),
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                  Text(
                    '/$maxTotal',
                    style: const TextStyle(fontSize: 10, color: AppTheme.textSecondary),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(width: 20),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'DESEMPENHO GERAL',
                  style: TextStyle(color: AppTheme.secondary, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 0.8),
                ),
                const SizedBox(height: 6),
                Text(
                  isPassing ? 'Rendimento Adequado' : 'Abaixo da Média',
                  style: TextStyle(color: isPassing ? AppTheme.success : AppTheme.error, fontWeight: FontWeight.bold, fontSize: 16),
                ),
                const SizedBox(height: 4),
                Text(
                  'Calculado sobre um total de $totalExams avaliações corrigidas.',
                  style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPedagogicalInsightsCard(List<_QuestionRate> hardest, List<_QuestionRate> easiest) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.cardBg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withOpacity(0.05)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.lightbulb_outline, color: Colors.amber, size: 20),
              SizedBox(width: 8),
              Text(
                'Pontos Críticos e Destaques',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Text(
            'Questões mais erradas (Menor acerto):',
            style: TextStyle(color: AppTheme.textSecondary, fontSize: 12, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          ...hardest.map((item) {
            final ratePercent = (item.rate * 100).toStringAsFixed(0);
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 4.0),
              child: Row(
                children: [
                  Container(
                    width: 24,
                    height: 24,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: AppTheme.error.withOpacity(0.15), shape: BoxShape.circle),
                    child: Text('${item.qNum}', style: const TextStyle(color: AppTheme.error, fontSize: 11, fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Questão ${item.qNum} - Apenas $ratePercent% de acerto',
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(color: AppTheme.error.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
                    child: const Text('Crítica', style: TextStyle(color: AppTheme.error, fontSize: 9, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            );
          }).toList(),
          const Divider(height: 20, color: Colors.white10),
          const Text(
            'Questões mais acertadas (Maior acerto):',
            style: TextStyle(color: AppTheme.textSecondary, fontSize: 12, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          ...easiest.map((item) {
            final ratePercent = (item.rate * 100).toStringAsFixed(0);
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 4.0),
              child: Row(
                children: [
                  Container(
                    width: 24,
                    height: 24,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: AppTheme.success.withOpacity(0.15), shape: BoxShape.circle),
                    child: Text('${item.qNum}', style: const TextStyle(color: AppTheme.success, fontSize: 11, fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Questão ${item.qNum} - $ratePercent% de acerto',
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(color: AppTheme.success.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
                    child: const Text('Fácil', style: TextStyle(color: AppTheme.success, fontSize: 9, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            );
          }).toList(),
        ],
      ),
    );
  }

  Widget _buildGradeDistributionCard(int crit, int reg, int good, int exc, int total) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.cardBg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withOpacity(0.05)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'DISTRIBUIÇÃO DE NOTAS DA TURMA',
            style: TextStyle(color: AppTheme.secondary, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 0.5),
          ),
          const SizedBox(height: 16),
          _buildDistributionRow('Excelente (9.0 - 10.0)', exc, total, AppTheme.success),
          const SizedBox(height: 8),
          _buildDistributionRow('Bom (7.0 - 8.9)', good, total, AppTheme.primary),
          const SizedBox(height: 8),
          _buildDistributionRow('Regular (5.0 - 6.9)', reg, total, AppTheme.warning),
          const SizedBox(height: 8),
          _buildDistributionRow('Crítico (0.0 - 4.9)', crit, total, AppTheme.error),
        ],
      ),
    );
  }

  Widget _buildDistributionRow(String range, int count, int total, Color color) {
    final double pct = total > 0 ? count / total : 0.0;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(range, style: const TextStyle(fontSize: 12, color: Colors.white70)),
            Text('$count Alunos (${(pct * 100).toStringAsFixed(0)}%)', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.white)),
          ],
        ),
        const SizedBox(height: 4),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: pct,
            minHeight: 8,
            color: color,
            backgroundColor: Colors.white12,
          ),
        ),
      ],
    );
  }

  Widget _buildQuestionDetailCard(
    _QuestionRate rateItem,
    double percent,
    QuestionKey key,
    Map<String, int> distribution,
    int totalCount,
  ) {
    Color progressColor = AppTheme.success;
    String difficulty = 'Fácil';
    if (rateItem.rate < 0.45) {
      progressColor = AppTheme.error;
      difficulty = 'Difícil';
    } else if (rateItem.rate < 0.75) {
      progressColor = AppTheme.warning;
      difficulty = 'Média';
    }

    if (rateItem.isCancelled) {
      progressColor = const Color(0xFF94A3B8);
      difficulty = 'Anulada';
    }

    // Detect if there is a strong distractor (an incorrect answer selected by more students than the correct one!)
    String? strongDistractor;
    int correctCount = distribution[key.correctOption] ?? 0;
    
    if (!key.isCancelled) {
      distribution.forEach((opt, count) {
        if (opt != key.correctOption && opt != 'N' && opt != '' && count > correctCount) {
          strongDistractor = opt;
        }
      });
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Card Title & Badge
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Questão ${rateItem.qNum} (Gabarito: ${key.isCancelled ? 'ANULADA' : key.correctOption})',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Colors.white),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: progressColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    difficulty,
                    style: TextStyle(color: progressColor, fontWeight: FontWeight.bold, fontSize: 11),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            
            // Overall correct percentage progress bar
            Row(
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: rateItem.rate,
                      backgroundColor: Colors.white.withOpacity(0.05),
                      color: progressColor,
                      minHeight: 8,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  rateItem.isCancelled ? '100%' : '${percent.toStringAsFixed(0)}% acertos',
                  style: TextStyle(
                    color: progressColor,
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Divider(height: 1, color: Colors.white10),
            const SizedBox(height: 12),

            // Choice distributions header
            const Text(
              'Distribuição das Alternativas:',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.textSecondary),
            ),
            const SizedBox(height: 8),

            // Horizontal graph bars for options A, B, C, D, E
            ...['A', 'B', 'C', 'D', 'E'].map((opt) {
              final count = distribution[opt] ?? 0;
              final double optPercent = totalCount > 0 ? count / totalCount : 0.0;
              final bool isCorrect = key.correctOption == opt && !key.isCancelled;
              final bool isDistractor = strongDistractor == opt;

              Color barColor = isCorrect 
                  ? AppTheme.success 
                  : (isDistractor ? AppTheme.warning : AppTheme.textSecondary.withOpacity(0.3));

              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 4.0),
                child: Row(
                  children: [
                    // Option letter bubble
                    Container(
                      width: 22,
                      height: 22,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: isCorrect 
                            ? AppTheme.success.withOpacity(0.15) 
                            : (isDistractor ? AppTheme.warning.withOpacity(0.15) : Colors.transparent),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: isCorrect 
                              ? AppTheme.success 
                              : (isDistractor ? AppTheme.warning : Colors.white10),
                          width: 1,
                        ),
                      ),
                      child: Text(
                        opt,
                        style: TextStyle(
                          color: isCorrect ? AppTheme.success : (isDistractor ? AppTheme.warning : AppTheme.textSecondary),
                          fontWeight: isCorrect || isDistractor ? FontWeight.bold : FontWeight.normal,
                          fontSize: 11,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    // Progress Bar
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(3),
                        child: LinearProgressIndicator(
                          value: optPercent,
                          color: barColor,
                          backgroundColor: Colors.white.withOpacity(0.04),
                          minHeight: 12,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    // Count & Pct
                    SizedBox(
                      width: 76,
                      child: Text(
                        '$count (${(optPercent * 100).toStringAsFixed(0)}%)',
                        textAlign: TextAlign.end,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: isCorrect ? FontWeight.bold : FontWeight.normal,
                          color: isCorrect ? AppTheme.success : (isDistractor ? AppTheme.warning : Colors.white70),
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }).toList(),

            // Pedagogy warning if there is a strong distractor!
            if (strongDistractor != null)
              Container(
                margin: const EdgeInsets.only(top: 12),
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppTheme.warning.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppTheme.warning.withOpacity(0.2)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.warning_amber_rounded, color: AppTheme.warning, size: 16),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Atenção: A alternativa $strongDistractor (incorreta) teve mais marcações do que o gabarito oficial (${key.correctOption}). Verifique se há dubiedade no enunciado.',
                        style: const TextStyle(color: AppTheme.warning, fontSize: 11, height: 1.4),
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

class _QuestionRate {
  final int qNum;
  final double rate;
  final bool isCancelled;

  _QuestionRate({required this.qNum, required this.rate, required this.isCancelled});
}
